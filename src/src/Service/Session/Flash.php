<?php

declare(strict_types=1);

namespace App\Service\Session;

/**
 * フラッシュメッセージ実装
 *
 * セッション内のフラッシュメッセージを管理します。
 * メッセージは取得すると自動的に削除されます。
 *
 * @package App\Service\Session
 */
class Flash implements FlashInterface
{
    /**
     * フラッシュメッセージデータ
     *
     * @var array<string, array<int, string>>
     */
    private array $flash = [];

    /**
     * コンストラクタ
     *
     * @param array<string, array<int, string>> $data フラッシュメッセージデータへの参照
     * @param callable $saveCallback セッション保存用のコールバック関数
     */
    public function __construct(
        array &$data,
        private readonly mixed $saveCallback
    ) {
        // 参照先のデータを初期化
        $this->flash = &$data;
    }

    /**
     * @inheritDoc
     */
    public function add(string $key, string $message): void
    {
        if (!isset($this->flash[$key])) {
            $this->flash[$key] = [];
        }
        $this->flash[$key][] = $message;
        ($this->saveCallback)();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): array
    {
        $messages = $this->flash[$key] ?? [];
        // 取得後はメッセージを削除
        unset($this->flash[$key]);
        if (!empty($messages)) {
            ($this->saveCallback)();
        }
        return $messages;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return isset($this->flash[$key]) && count($this->flash[$key]) > 0;
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->flash = [];
        ($this->saveCallback)();
    }

    /**
     * @inheritDoc
     */
    public function setAll(string $key, array $messages): void
    {
        $this->flash[$key] = $messages;
        ($this->saveCallback)();
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $all = $this->flash;
        $this->flash = [];
        if (!empty($all)) {
            ($this->saveCallback)();
        }
        return $all;
    }

    /**
     * 内部データへの参照を取得
     *
     * セッションハンドラーが直接データを参照・更新する場合に使用します。
     *
     * @return array<string, array<int, string>>
     */
    public function &getData(): array
    {
        return $this->flash;
    }
}
