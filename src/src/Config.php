<?php

declare(strict_types=1);

namespace App;

/**
 * アプリケーション設定クラス
 *
 * 環境変数から読み込んだ設定値を一元管理する。
 * SMTP、メールアドレス、reCAPTCHA設定などを保持する。
 */
class Config
{
    /**
     * 問い合わせカテゴリーのラベルマッピングを取得
     *
     * @return array<string, string> カテゴリー値 => ラベルの接連配列
     */
    public static function getCategoryLabels(): array
    {
        return [
            'product' => '製品について',
            'service' => 'サービスについて',
            'pricing' => '料金について',
            'technical' => '技術サポート',
            'other' => 'その他',
        ];
    }

    /**
     * コンストラクタ
     *
     * @param bool $isDebug デバッグモード有効フラグ（APP_DEBUG）
     * @param bool $isDevelop 開発環境フラグ（APP_ENV が 'develop' の場合 true）
     * @param string $smtpHost SMTP サーバーホスト（デフォルト: email-smtp.ap-northeast-1.amazonaws.com）
     * @param string $smtpUser SMTP ユーザー名
     * @param string $smtpPassword SMTP パスワード
     * @param int $smtpPort SMTP サーバーポート（デフォルト: 587）
     * @param string $emailFrom From メールアドレス（SES検証済み）
     * @param string $emailFromName From 表示名
     * @param string $emailAdmin 管理者メールアドレス
     * @param string $mailSubjectAdmin 管理者通知メール件名
     * @param string $mailSubjectUser ユーザー自動返信メール件名
     * @param string $recaptchaSiteKey reCAPTCHA サイトキー
     * @param string $recaptchaSecretKey reCAPTCHA シークレットキー
     * @param string $recaptchaType reCAPTCHA タイプ（v3, v2, enterprise）
     * @param string $recaptchaProjectId reCAPTCHA Enterprise プロジェクトID
     * @param float $recaptchaScoreThreshold reCAPTCHA v3 スコア閾値（0.0-1.0）
     */
    private function __construct(
        public readonly bool $isDebug = false,
        public readonly bool $isDevelop = false,
        public readonly string $smtpHost = 'email-smtp.ap-northeast-1.amazonaws.com',
        public readonly string $smtpUser = '',
        public readonly string $smtpPassword = '',
        public readonly int $smtpPort = 587,
        public readonly string $emailFrom = '',
        public readonly string $emailFromName = '',
        public readonly string $emailAdmin = '',
        public readonly string $mailSubjectAdmin = '',
        public readonly string $mailSubjectUser = '',
        public readonly string $recaptchaSiteKey = '',
        public readonly string $recaptchaSecretKey = '',
        public readonly string $recaptchaType = 'v3',
        public readonly string $recaptchaProjectId = '',
        public readonly float $recaptchaScoreThreshold = 0.5,
    ) {
    }

    /**
     * 環境変数から設定値を読み込んで Config インスタンスを生成
     *
     * 環境変数（$_ENV）から各設定値を取得し、不足している場合はデフォルト値を使用する。
     *
     * @return self 読み込んだ設定を保持する Config インスタンス
     */
    public static function fromEnv(): self
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'false');

        return new self(
            isDebug: ((string) $debug === 'true'),
            isDevelop: self::getEnv('APP_ENV', 'develop') === 'develop',
            smtpHost: self::getEnv('SMTP_HOST', 'email-smtp.ap-northeast-1.amazonaws.com'),
            smtpPort: (int) self::getEnv('SMTP_PORT', 587),
            smtpUser: self::getEnv('SES_SMTP_USER'),
            smtpPassword: self::getEnv('SES_SMTP_PASS'),
            emailFrom: self::getEnv('EMAIL_FROM'),
            emailFromName: self::getEnv('EMAIL_FROM_NAME'),
            emailAdmin: self::getEnv('EMAIL_ADMIN'),
            mailSubjectAdmin: self::getEnv('MAIL_SUBJECT_ADMIN', '【お問い合わせ】がありました'),
            mailSubjectUser: self::getEnv('MAIL_SUBJECT_USER', '【自動返信】お問い合わせありがとうございます'),
            recaptchaSiteKey: self::getEnv('RECAPTCHA_SITE_KEY'),
            recaptchaSecretKey: self::getEnv('RECAPTCHA_SECRET_KEY'),
            recaptchaType: self::getEnv('RECAPTCHA_TYPE', 'v3'),
            recaptchaProjectId: self::getEnv('RECAPTCHA_PROJECT_ID'),
            recaptchaScoreThreshold: (float) self::getEnv('RECAPTCHA_SCORE_THRESHOLD', 0.5),
        );
    }

    /**
     * 環境変数から値を取得（空文字列の場合はデフォルト値を返す）
     *
     * @param string $key 環境変数キー
     * @param mixed $default デフォルト値
     *
     * @return mixed 環境変数の値またはデフォルト値
     */
    private static function getEnv(string $key, mixed $default = ''): mixed
    {
        $value = $_ENV[$key] ?? '';

        return ($value !== '') ? $value : $default;
    }
}
