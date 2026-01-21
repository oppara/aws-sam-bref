<?php

declare(strict_types=1);

namespace App\Service\Session;

use App\Config;
use Psr\Container\ContainerInterface;

/**
 * セッションサービスファクトリー
 *
 * 設定に基づいて適切なセッションハンドラーを生成する。
 * RecaptchaFactory のパターンに従う。
 *
 * @package App\Service\Session
 */
class SessionFactory
{
    /**
     * CONFIG の設定に基づいて適切なセッションハンドラーを生成
     *
     * @param Config $config 設定オブジェクト
     * @param ContainerInterface $container DI container
     * @return SessionManagerInterface セッションインスタンス
     * @throws \InvalidArgumentException サポートされていないハンドラーの場合
     */
    public static function create(Config $config, ContainerInterface $container): SessionManagerInterface
    {
        return match ($config->sessionHandler) {
            'file' => new FileSessionHandler($config, $container),
            'dynamodb' => new DynamodbSessionHandler($config, $container),
            default => throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported session handler: %s. Supported: file, dynamodb',
                    $config->sessionHandler
                )
            ),
        };
    }
}
