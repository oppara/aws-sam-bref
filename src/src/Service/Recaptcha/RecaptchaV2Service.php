<?php

declare(strict_types=1);

namespace App\Service\Recaptcha;

use App\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
* reCAPTCHA v2 検証サービス
 *
 * reCAPTCHA v2 の検証処理を提供する。
 * Checkbox（チェックボックス）と Invisible（非表示）の共通処理を実装する。
 */
class RecaptchaV2Service implements RecaptchaServiceInterface
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    protected Client $httpClient;

    /**
     * コンストラクタ
     *
     * @param Config $config アプリケーション設定
     * @param LoggerInterface $logger ロガー
     * @param String $type
     */
    public function __construct(
        protected Config $config,
        protected LoggerInterface $logger,
        protected String $type,
    ) {
        $this->httpClient = new Client();
    }

    /**
     * reCAPTCHA v2で検証
     *
     * @param string $token クライアントから送信されたreCAPTCHAトークン
     * @throws \RuntimeException 検証に失敗した場合
     * @return array{score: float, success: bool, errors: array<string>}
     */
    public function verify(string $token): array
    {
        try {
            $response = $this->httpClient->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret' => $this->config->recaptchaSecretKey,
                    'response' => $token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->logger->debug($this->type, $data);

            if (!isset($data['success']) || !$data['success']) {
                $data['success'] = false;
                $data['score'] = 0.0;

                return $data;
            }

            // reCAPTCHA v2はスコアを返さない（成功/失敗のみ）
            // 互換性のためスコアを1.0で返す
            $data['score'] = 1.0;

            return $data;
            
        } catch (GuzzleException $e) {
            $this->logger->error($this->type . ' verification error', [
                'error' => $e->getMessage(),
            ]);

            throw new RecaptchaException($this->type . ' verification failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
