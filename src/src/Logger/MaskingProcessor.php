<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\LogRecord;

/**
 * Monologログレコードの秘匿情報をマスクするプロセッサ
 *
 * ログレコードのcontextとextraから秘匿情報（トークン、シークレットキー、パスワード等）を検出し、
 * 自動的にマスク処理を施す。これにより、ログ出力時にセンシティブ情報が外部に露出することを防ぐ。
 *
 * マスク対象のキー：token, secret, password, key を含むキー名
 *
 * マスク方法：
 * - 空文字列：(empty)
 * - 4文字以下：****
 * - 5文字以上：最初の2文字+****+最後の2文字 (例: ab****89)
 *
 * 使用例：
 * ```php
 * $logger->pushProcessor(new MaskingProcessor());
 * $logger->info('User login', ['csrf_token' => 'secret-token-123']);
 * // ログ出力：csrf_token => "se****23"
 * ```
 *
 * @package App\Logger
 */
class MaskingProcessor
{
    /**
     * 秘匿情報を含むキー名のパターン
     */
    private const SENSITIVE_KEYS = ['token', 'secret', 'password', 'key'];

    /**
     * ログレコードの秘匿情報をマスク
     *
     * @param LogRecord $record ログレコード
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;

        if (!empty($context)) {
            $context = $this->maskArray($context);
        }

        if (!empty($extra)) {
            $extra = $this->maskArray($extra);
        }

        return $record->with(context: $context, extra: $extra);
    }

    /**
     * 配列内の秘匿情報をマスク
     *
     * @param array $data マスク対象のデータ
     * @return array マスク済みのデータ
     */
    private function maskArray(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string)$key)) {
                $masked[$key] = $this->maskValue($value);
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskArray($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * キーが秘匿情報を含むかチェック
     *
     * @param string $key キー名
     * @return bool
     */
    private function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (strpos($lowerKey, $sensitiveKey) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 値をマスク
     *
     * @param mixed $value マスク対象の値
     * @return string マスク済みの値
     */
    private function maskValue(mixed $value): string
    {
        if ($value === null || $value === false) {
            return (string)$value;
        }

        $valueStr = (string)$value;

        if (empty($valueStr)) {
            return '(empty)';
        }

        if (strlen($valueStr) <= 4) {
            return '****';
        }

        // 最初の2文字と最後の2文字を表示
        return substr($valueStr, 0, 2) . '****' . substr($valueStr, -2);
    }
}
