<?php

declare(strict_types=1);

namespace App\Service\Session;

/**
 * セッション管理インターフェース
 *
 * セッションライフサイクルとメタデータの管理を定義します。
 *
 * @package App\Service\Session
 */
interface SessionManagerInterface extends SessionInterface
{
    /**
     * セッションを開始
     *
     * PHP のセッション関数に相当する処理を実行します。
     * 既に開始されている場合は何もしません。
     *
     * @return void
     */
    public function start(): void;

    /**
     * セッションが開始されているか確認
     *
     * @return bool セッションが開始されている場合は true
     */
    public function isStarted(): bool;

    /**
     * セッション ID を取得
     *
     * @return string セッション ID
     */
    public function getId(): string;

    /**
     * セッション ID を設定
     *
     * セッション開始前に呼び出す必要があります。
     *
     * @param string $id セッション ID
     * @return void
     */
    public function setId(string $id): void;

    /**
     * セッション名を取得
     *
     * @return string セッション名（デフォルト: PHPSESSID）
     */
    public function getName(): string;

    /**
     * セッション名を設定
     *
     * セッション開始前に呼び出す必要があります。
     *
     * @param string $name セッション名
     * @return void
     */
    public function setName(string $name): void;

    /**
     * セッションを破棄
     *
     * セッションデータとクッキーを削除します。
     *
     * @return void
     */
    public function destroy(): void;

    /**
     * セッション ID を再生成
     *
     * セッション固定攻撃への対策用。
     * 古いセッション ID のデータを削除することもできます。
     *
     * @param bool $deleteOldSession 古いセッションを削除するか（デフォルト: false）
     * @return void
     */
    public function regenerateId(bool $deleteOldSession = false): void;

    /**
     * セッションオプションを取得
     *
     * @return array<string, mixed> セッションオプション
     */
    public function getOptions(): array;

    /**
     * セッションオプションを設定
     *
     * @param array<string, mixed> $options 設定するオプション
     * @return void
     */
    public function setOptions(array $options): void;
}
