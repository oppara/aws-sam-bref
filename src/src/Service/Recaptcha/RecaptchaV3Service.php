<?php

declare(strict_types=1);

namespace App\Service\Recaptcha;

use App\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * reCAPTCHA v3 検証サービス
 *
 * Google reCAPTCHA v3 を使用してユーザーのリクエストがボットではないことを検証する。
 * スコアベースの検証を行い、スコア値に基づいて信頼性を判定する。
 *
 * **スコアについて：**
 * - スコア範囲: 0.0 ～ 1.0
 * - 1.0：人間による操作の可能性が高い
 * - 0.0：ボットによる操作の可能性が高い
 *
 * @package App\Service\Recaptcha
 */
class RecaptchaV3Service implements RecaptchaServiceInterface
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private Client $httpClient;

    /**
     * コンストラクタ
     *
     * @param Config $config アプリケーション設定
     */
    public function __construct(
        private Config $config,
        protected LoggerInterface $logger,
    ) {
        $this->httpClient = new Client();
    }

    /**
     * reCAPTCHA v3で検証
     *
     * クライアントから送信されたトークンを Google のサーバーに送信し、スコアベースの検証を実行する。
     * 設定されたスコア閾値（recaptchaScoreThreshold）よりスコアが低い場合、検証に失敗したと判定する。
     *
     * **返却値：**
     * - success: Google の検証結果（true = 有効なトークン、false = 無効なトークン）
     * - score: ボット可能性スコア（0.0-1.0、高いほど人間による操作の可能性が高い）
     * - errors: エラーメッセージ配列（存在する場合）
     * - challenge_ts: チャレンジのタイムスタンプ（ISO 8601形式）
     * - hostname: リクエスト元ホスト名
     * - action: reCAPTCHAリクエストの action パラメータ値
     *
     * @param string $token クライアントから送信されたreCAPTCHAトークン
     * @return array{score: float, success: bool, errors: array<string>, challenge_ts?: string, hostname?: string, action?: string} 検証結果
     * @throws RecaptchaException 検証通信に失敗した場合
     */
    public function verify(string $token): array
    {
        try {
            // Google reCAPTCHA API にトークンを検証
            $response = $this->httpClient->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret' => $this->config->recaptchaSecretKey,
                    'response' => $token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->logger->debug('reCAPTCHA v3 results',  $data);

            // Google による検証が失敗した場合
            if (!isset($data['success']) || !$data['success']) {
                $data['success'] = false;

                return $data;
            }

            // スコア閾値による判定：スコアが閾値より低い場合は検証失敗と見なす
            $score = (float) $data['score'] ?? 0.0;
            if ($score < $this->config->recaptchaScoreThreshold) {
                $data['success'] = false;

                return $data;
            }

            return $data;

        } catch (GuzzleException $e) {
            $this->logger->error('reCAPTCHA v3 verification error', [
                'error' => $e->getMessage(),
            ]);

            throw new RecaptchaException('reCAPTCHA v3 verification failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
