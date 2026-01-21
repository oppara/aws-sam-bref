<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Config;
use Psr\Container\ContainerInterface;

/**
 * ファイルベースのセッションハンドラー
 *
 * PHP標準のセッション関数を使用してセッションをファイルに保存します。
 * 開発環境やシングルサーバー環境に適しています。
 *
 * @package App\Service\Session
 */
class FileSessionHandler implements SessionManagerInterface
{
    /**
     * セッションデータ
     *
     * @var array<string, mixed>
     */
    private array $sessionData = [];

    /**
     * フラッシュメッセージデータ
     *
     * @var array<string, array<int, string>>
     */
    private array $flashData = [];

    /**
     * セッション開始フラグ
     *
     * @var bool
     */
    private bool $isStarted = false;

    /**
     * セッションオプション
     *
     * @var array<string, mixed>
     */
    private array $options = [
        'name' => 'PHPSESSID',
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
        'cache_limiter' => 'nocache',
    ];

    /**
     * Flash インスタンス
     *
     * @var Flash|null
     */
    private ?Flash $flash = null;

    /**
     * コンストラクタ
     *
     * @param Config $config 設定オブジェクト
     * @param ContainerInterface $container DI container
     */
    public function __construct(
        Config $config,
        ContainerInterface $container
    ) {
        // セッションオプション設定
        $this->options = [
            'name' => 'PHPSESSID',
            'lifetime' => 7200,
            'path' => '/',
            'domain' => '',
            'secure' => !$config->isDevelop,
            'httponly' => true,
            'samesite' => 'Lax',
            'cache_limiter' => 'nocache',
        ];

        // SessionManagerInterface として DI コンテナに登録
        $container->set(SessionManagerInterface::class, fn () => $this);
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        if ($this->isStarted) {
            return;
        }

        // セッション設定を適用
        $this->applyOptions();

        // セッションを開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // $_SESSION からセッションデータを読み込み
        $this->sessionData = $_SESSION ?? [];

        // フラッシュメッセージを読み込み
        if (!isset($this->sessionData['_flash'])) {
            $this->sessionData['_flash'] = [];
        }
        $this->flashData = &$this->sessionData['_flash'];

        $this->isStarted = true;
    }

    /**
     * @inheritDoc
     */
    public function isStarted(): bool
    {
        return $this->isStarted || session_status() === PHP_SESSION_ACTIVE;
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
        $_SESSION[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function setValues(array $values): void
    {
        $this->sessionData = array_merge($this->sessionData, $values);
        $_SESSION = $this->sessionData;
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
        unset($this->sessionData[$key], $_SESSION[$key]);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->sessionData = [];
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    public function getFlash(): FlashInterface
    {
        if ($this->flash === null) {
            $this->flash = new Flash(
                $this->flashData,
                fn () => $_SESSION['_flash'] = $this->flashData
            );
        }
        return $this->flash;
    }

    /**
     * @inheritDoc
     */
    public function save(): void
    {
        if ($this->isStarted && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = $this->sessionData;
        }
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * @inheritDoc
     */
    public function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->options['name'] ?? 'PHPSESSID';
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): void
    {
        $this->options['name'] = $name;
        session_name($name);
    }

    /**
     * @inheritDoc
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->sessionData = [];
        $this->flashData = [];
        $this->isStarted = false;
    }

    /**
     * @inheritDoc
     */
    public function regenerateId(bool $deleteOldSession = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
        }
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * セッションオプションを PHP に適用
     *
     * @return void
     */
    private function applyOptions(): void
    {
        // セッション名を設定
        if (isset($this->options['name'])) {
            session_name($this->options['name']);
        }

        // セッションクッキーオプションを設定
        $cookieOptions = [
            'lifetime' => $this->options['lifetime'] ?? 0,
            'path' => $this->options['path'] ?? '/',
            'domain' => $this->options['domain'] ?? '',
            'secure' => $this->options['secure'] ?? false,
            'httponly' => $this->options['httponly'] ?? true,
            'samesite' => $this->options['samesite'] ?? 'Lax',
        ];

        session_set_cookie_params($cookieOptions);

        // キャッシュリミッターを設定
        if (isset($this->options['cache_limiter'])) {
            session_cache_limiter($this->options['cache_limiter']);
        }
    }
}
