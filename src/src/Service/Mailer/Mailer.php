<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Config;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

/**
 * メール送信クラス
 *
 * 管理者通知メール及びユーザー自動返信メール送信機能を提供する。
 * Twig テンプレートを使用してメール本文を生成し、PHPMailer で送信する。
 *
 * @package App\Service\Mailer
 */
class Mailer
{
    /**
     * コンストラクタ
     *
     * @param Config $config アプリケーション設定
     * @param LoggerInterface $logger ロガー
     * @param Twig $twig Twig テンプレートエンジン
     */
    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
        private Twig $twig,
    ) {}

    /**
     * 管理者宛メール送信
     *
     * @param array $data メール本文に渡すテンプレートデータ
     * @param string $to 送信先メールアドレス（省略時は設定ファイルの管理者メールアドレスを使用）
     * @throws Exception メール送信失敗時
     */
    public function sendAdmin(array $data, string $to = ''): void
    {
        $to = $to ?: $this->config->emailAdmin;
        $this->sendMail(
            $to,
            $this->config->mailSubjectAdmin,
            'mail/admin.twig',
            $data
        );
    }

    /**
     * ユーザー宛自動返信メール送信
     *
     * @param array $data メール本文に渡すテンプレートデータ（email キーが送信先メールアドレス）
     * @throws Exception メール送信失敗時
     */
    public function sendUser(array $data): void
    {
        $this->sendMail(
            $data['email'],
            $this->config->mailSubjectUser,
            'mail/user.twig',
            $data
        );
    }

    /**
     * メール送信（共通処理）
     *
     * @param string $to 送信先メールアドレス
     * @param string $subject メール件名
     * @param string $templateName テンプレートファイル名
     * @param array $data テンプレートに渡すデータ
     *
     * @return void
     * @throws Exception メール送信失敗時
     */
    private function sendMail(string $to, string $subject, string $templateName, array $data): void
    {
        try {
            $mail = MailerFactory::create($this->config);
            $mail->addAddress($to);

            $mail->Subject = $subject;
            $mail->Body = $this->twig->fetch($templateName, array_merge($data, [
                'categoryLabels' => Config::getCategoryLabels(),
            ]));

            $mail->send();
            $this->logger->info('Email sent', ['to' => $to]);
        } catch (Exception $e) {
            $this->logger->error('Send email failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
