<?php

declare(strict_types=1);

namespace App\Service\Recaptcha;

use App\Config;
use Psr\Log\LoggerInterface;

/** @package App\Service\Recaptcha */
class RecaptchaFactory
{
    /**
     * 環境変数RECAPTCHA_TYPEに基づいて適切なreCAPTCHAサービスを生成
     *
     * @param Config $config 設定オブジェクト
     * @param LoggerInterface $logger ロガー
     * @return RecaptchaServiceInterface reCAPTCHAサービスインスタンス
     * @throws \InvalidArgumentException サポートされていないタイプの場合
     */
    public static function create(Config $config, LoggerInterface $logger): RecaptchaServiceInterface
    {
        return match ($config->recaptchaType) {
            'enterprise' => new RecaptchaEnterpriseService($config, $logger),
            'v3' => new RecaptchaV3Service($config, $logger),
            'v2-checkbox' => new RecaptchaV2Service($config, $logger, 'reCAPTCHA v2-checkbox'),
            'v2-invisible' => new RecaptchaV2Service($config, $logger, 'reCAPTCHA v2-invisible'),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported reCAPTCHA type: %s. Supported types: enterprise, v3, v2-checkbox, v2-invisible', $config->recaptchaType)
            ),
        };
    }
}
