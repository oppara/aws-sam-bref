<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Config;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Exception\AwsException;
use JsonException;
use Psr\Container\ContainerInterface;

/**
 * DynamoDB ベースのセッションハンドラー
 *
 * セッションデータを AWS DynamoDB に永続化します。
 * Lambda 環境での分散セッション管理に適しています。
 *
 * **環境変数：**
 * - SESSION_DYNAMODB_TABLE: DynamoDB テーブル名
 *
 * **DynamoDB テーブル設定：**
 * - パーティションキー: session_id (String)
 * - ソートキー: なし
 * - TTL 属性: expires_at (Unix timestamp、自動削除用)
 *
 * @package App\Service\Session
 */
class DynamodbSessionHandler implements SessionManagerInterface
{
    private DynamoDbClient $dynamodb;
    private Marshaler $marshaler;
    private string $tableName;
    private array $sessionData = [];
    private array $flashData = [];
    private string $sessionId = '';
    private bool $isStarted = false;
    private array $sessionOptions = [];
    private ?Flash $flash = null;

    /**
     * コンストラクタ
     *
     * @param Config $config 設定オブジェクト
     * @param ContainerInterface $container DI container
     */
    public function __construct(Config $config, ContainerInterface $container)
    {
        // DynamoDB クライアントを初期化（リージョンは AWS_REGION 環境変数から自動検出）
        $this->dynamodb = new DynamoDbClient([
            'version' => 'latest',
        ]);

        // Marshaler を初期化
        $this->marshaler = new Marshaler();

        // テーブル名を設定から取得
        $this->tableName = $config->sessionDynamodbTable;

        // セッションオプションを設定
        $this->sessionOptions = [
            'name' => 'PHPSESSID',
            'lifetime' => 7200,
            'path' => '/',
            'domain' => '',
            'secure' => !$config->isDevelop,
            'httponly' => true,
            'samesite' => 'Lax',
            'cache_limiter' => 'nocache',
        ];

        // SessionManagerInterface のDI定義
        $container->set(SessionManagerInterface::class, fn () => $this);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->sessionData[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->sessionData;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): void
    {
        $this->sessionData[$key] = $value;
        $this->saveSessionData();
    }

    /**
     * @inheritDoc
     */
    public function setValues(array $values): void
    {
        $this->sessionData = array_merge($this->sessionData, $values);
        $this->saveSessionData();
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return isset($this->sessionData[$key]);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        unset($this->sessionData[$key]);
        $this->saveSessionData();
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->sessionData = [];
        $this->saveSessionData();
    }

    /**
     * @inheritDoc
     */
    public function getFlash(): FlashInterface
    {
        if ($this->flash === null) {
            if (!isset($this->sessionData['_flash'])) {
                $this->sessionData['_flash'] = [];
            }
            $this->flashData = &$this->sessionData['_flash'];
            $this->flash = new Flash(
                $this->flashData,
                fn () => $this->saveSessionData()
            );
        }
        return $this->flash;
    }

    /**
     * セッションデータを保存
     *
     * @return void
     */
    public function save(): void
    {
        $this->saveSessionData();
    }

    // SessionManagerInterface のメソッド

    /**
     * セッションを開始
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->isStarted) {
            return;
        }

        $this->applyOptions();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->sessionId = session_id();

        $this->loadSessionData();

        $this->isStarted = true;
    }

    /**
     * セッションが開始されているか確認
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * セッション ID を取得
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->sessionId;
    }

    /**
     * セッション ID を設定
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->sessionId = $id;
    }

    /**
     * セッション名を取得
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->sessionOptions['name'] ?? 'PHPSESSID';
    }

    /**
     * セッション名を設定
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->sessionOptions['name'] = $name;
    }

    /**
     * セッションを破棄
     *
     * @return void
     */
    public function destroy(): void
    {
        if ($this->sessionId) {
            $this->dynamodb->deleteItem([
                'TableName' => $this->tableName,
                'Key' => $this->marshaler->marshalItem(['session_id' => $this->sessionId]),
            ]);
        }
        $this->sessionData = [];
        $this->isStarted = false;
    }

    /**
     * セッション ID を再生成
     *
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerateId(bool $deleteOldSession = false): void
    {
        $oldSessionId = $this->sessionId;
        $this->sessionId = bin2hex(random_bytes(16));

        if ($deleteOldSession && $oldSessionId) {
            $this->dynamodb->deleteItem([
                'TableName' => $this->tableName,
                'Key' => $this->marshaler->marshalItem(['session_id' => $oldSessionId]),
            ]);
        }

        // 新しいセッション ID でデータを保存
        $this->saveSessionData();
    }

    /**
     * セッションオプションを取得
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->sessionOptions;
    }

    /**
     * セッションオプションを設定
     *
     * @param array<string, mixed> $options
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->sessionOptions = array_merge($this->sessionOptions, $options);
    }

    /**
     * DynamoDB からセッションデータを読み込み
     *
     * @return void
     * @throws AwsException DynamoDB操作に失敗した場合
     * @throws JsonException JSON デコードに失敗した場合
     */
    private function loadSessionData(): void
    {
        $result = $this->dynamodb->getItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem(['session_id' => $this->sessionId]),
        ]);

        if (isset($result['Item'])) {
            $item = $this->marshaler->unmarshalItem($result['Item']);
            $this->sessionData = json_decode(
                $item['data'] ?? '{}',
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        // フラッシュメッセージを初期化
        if (!isset($this->sessionData['_flash'])) {
            $this->sessionData['_flash'] = [];
        }
        $this->flashData = &$this->sessionData['_flash'];
    }

    /**
     * DynamoDB にセッションデータを保存
     *
     * @return void
     * @throws AwsException DynamoDB操作に失敗した場合
     * @throws JsonException JSON エンコードに失敗した場合
     */
    public function saveSessionData(): void
    {
        $expiresAt = time() + ($this->sessionOptions['lifetime'] ?? 7200);

        $item = [
            'session_id' => $this->sessionId,
            'data' => json_encode($this->sessionData, JSON_THROW_ON_ERROR),
            'created_at' => time(),
            'updated_at' => time(),
            'expires_at' => $expiresAt,
        ];

        $this->dynamodb->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaler->marshalItem($item),
        ]);
    }

    /**
     * セッションオプションを PHP に適用
     *
     * @return void
     */
    private function applyOptions(): void
    {
        // セッション名を設定
        if (isset($this->sessionOptions['name'])) {
            session_name($this->sessionOptions['name']);
        }

        // セッションクッキーオプションを設定
        $cookieOptions = [
            'lifetime' => $this->sessionOptions['lifetime'] ?? 0,
            'path' => $this->sessionOptions['path'] ?? '/',
            'domain' => $this->sessionOptions['domain'] ?? '',
            'secure' => $this->sessionOptions['secure'] ?? false,
            'httponly' => $this->sessionOptions['httponly'] ?? true,
            'samesite' => $this->sessionOptions['samesite'] ?? 'Lax',
        ];

        session_set_cookie_params($cookieOptions);

        // キャッシュリミッターを設定
        if (isset($this->sessionOptions['cache_limiter'])) {
            session_cache_limiter($this->sessionOptions['cache_limiter']);
        }
    }
}
