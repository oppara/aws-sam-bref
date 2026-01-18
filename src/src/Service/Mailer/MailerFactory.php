<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use App\Config;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * PHPMailer インスタンス生成ファクトリ
 *
 * 設定ファイルに基づいて、SMTP接続を備えた PHPMailer インスタンスを生成する。
 */
class MailerFactory
{
    /**
     * PHPMailer インスタンスを生成
     *
     * 設定ファイルから SMTP サーバー設定及びメールアドレス設定を読み込み、
     * 初期化された PHPMailer インスタンスを返す。
     * 生成時に受信者及び Reply-To をクリアする。
     *
     * @param Config $config 設定ファイル
     * @return PHPMailer SMTP 接続済みの PHPMailer インスタンス
     */
    public static function create(Config $config): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $config->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $config->smtpUser;
        $mail->Password = $config->smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config->smtpPort;

        $mail->CharSet = 'UTF-8';

        $mail->Sender = $config->emailFrom;
        if ($config->emailFromName) {
            $mail->setFrom($config->emailFrom, $config->emailFromName);
        } else {
            $mail->setFrom($config->emailFrom);
        }

        // 受信者とReply-Toをクリア
        $mail->clearAllRecipients();
        $mail->clearReplyTos();

        return $mail;
    }
}
