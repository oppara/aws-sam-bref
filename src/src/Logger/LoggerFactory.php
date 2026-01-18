<?php

declare(strict_types=1);

namespace App\Logger;

use App\Config;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * ロガーインスタンスを生成するファクトリクラス
 *
 * Monolog のロガーを設定し、複数のプロセッサとハンドラーを組み合わせて、
 * JSON 形式でログを標準出力に出力するロガーを作成します。
 *
 * 構成：
 * - ハンドラー：StreamHandler（php://stdout に JSON 形式で出力）
 * - プロセッサ：LambdaRequestIdProcessor（AWS Lambda コンテキスト情報を追加）
 * - プロセッサ：MaskingProcessor（秘匿情報をマスク）
 *
 * @package App\Logger
 */
class LoggerFactory
{
    /**
     * ロガーインスタンスを生成
     *
     * Config の isDebug 設定に基づいて適切なログレベルを設定する：
     * - isDebug が true：Debug レベル（すべてのログを出力）
     * - isDebug が false：Info レベル以上のログを出力）
     *
     * @param Config $config アプリケーション設定
     * @return LoggerInterface PSR-3 標準のロガーインスタンス
     */
    public static function create(Config $config): LoggerInterface
    {
        $logger = new Logger('app');
        $logLevel = $config->isDebug ? Level::Debug : Level::Info;
        $newLine = $config->isDebug ? true : false;

        $handler = new StreamHandler('php://stdout', $logLevel);
        $handler->setFormatter(new JsonFormatter(
            JsonFormatter::BATCH_MODE_JSON,
            $newLine
        ));

        $logger->pushHandler($handler);
        $logger->pushProcessor(new LambdaRequestIdProcessor());
        $logger->pushProcessor(new MaskingProcessor());

        return $logger;
    }
}
