<?php

declare(strict_types=1);

namespace App\Service\Session;

/**
 * フラッシュメッセージインターフェース
 *
 * 1回の要求後に自動的に削除されるメッセージの管理を定義します。
 * 通常、エラーメッセージやサクセスメッセージの保存に使用されます。
 *
 * @package App\Service\Session
 */
interface FlashInterface
{
    /**
     * フラッシュメッセージを追加
     *
     * 同じキーに複数のメッセージを追加できます。
     *
     * @param string $key メッセージキー（例: 'error', 'success', 'info'）
     * @param string $message メッセージ本文
     * @return void
     */
    public function add(string $key, string $message): void;

    /**
     * フラッシュメッセージを取得
     *
     * 指定キーのメッセージを取得します。
     * このメソッドは次のリクエストで削除されます。
     *
     * @param string $key メッセージキー
     * @return array<int, string> メッセージの配列
     */
    public function get(string $key): array;

    /**
     * フラッシュメッセージが存在するか確認
     *
     * @param string $key メッセージキー
     * @return bool メッセージが存在する場合は true
     */
    public function has(string $key): bool;

    /**
     * すべてのフラッシュメッセージをクリア
     *
     * @return void
     */
    public function clear(): void;

    /**
     * 指定キーのメッセージを設定
     *
     * 既存のメッセージを置き換えます。
     *
     * @param string $key メッセージキー
     * @param array<int, string> $messages メッセージの配列
     * @return void
     */
    public function setAll(string $key, array $messages): void;

    /**
     * すべてのフラッシュメッセージを取得
     *
     * @return array<string, array<int, string>> すべてのメッセージ
     */
    public function all(): array;
}
