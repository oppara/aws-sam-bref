<?php

declare(strict_types=1);

namespace App\Service\Recaptcha;

/** @package App\Service\Recaptcha */
interface RecaptchaServiceInterface
{
    /**
     * reCAPTCHAトークンを検証して評価結果を返す
     *
     * @param string $token クライアントから送信されたreCAPTCHAトークン
     * @return array{score: float, success: bool, errors: array<string>}
     * @throws RecaptchaException 検証に失敗した場合
     */
    public function verify(string $token): array;
}
