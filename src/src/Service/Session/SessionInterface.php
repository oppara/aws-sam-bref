<?php

declare(strict_types=1);

namespace App\Service\Session;

/**
 * セッション操作インターフェース
 *
 * PHP標準のセッション仕様をベースにした、
 * セッションデータへのアクセスと管理を定義します。
 *
 * @package App\Service\Session
 */
interface SessionInterface
{
    /**
     * セッションから値を取得
     *
     * @param string $key キー名
     * @param mixed $default キーが存在しない場合のデフォルト値
     * @return mixed セッション値またはデフォルト値
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * セッションのすべての値を取得
     *
     * @return array<string, mixed> セッション内のすべてのデータ
     */
    public function all(): array;

    /**
     * セッションに値を設定
     *
     * @param string $key キー名
     * @param mixed $value 設定する値
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * セッションに複数の値を設定
     *
     * @param array<string, mixed> $values 設定する値の配列
     * @return void
     */
    public function setValues(array $values): void;

    /**
     * セッションにキーが存在するか確認
     *
     * @param string $key キー名
     * @return bool キーが存在する場合は true
     */
    public function has(string $key): bool;

    /**
     * セッションからキーを削除
     *
     * @param string $key キー名
     * @return void
     */
    public function delete(string $key): void;

    /**
     * セッションのすべてのデータを削除
     *
     * @return void
     */
    public function clear(): void;

    /**
     * フラッシュメッセージオブジェクトを取得
     *
     * フラッシュメッセージは1回の要求の後に自動的に削除されます。
     *
     * @return FlashInterface フラッシュメッセージオブジェクト
     */
    public function getFlash(): FlashInterface;

    /**
     * セッションを保存
     *
     * 必要に応じてバックエンドにセッションデータを永続化します。
     *
     * @return void
     */
    public function save(): void;
}
