<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * AWS Lambda リクエストIDをログに追加するプロセッサ
 *
 * AWS Lambda 環境で実行される際、LAMBDA_INVOCATION_CONTEXT から AWS リクエストIDを取得し、
 * ログレコードの extra に追加する。
 * また、HTTP リクエストのヘッダー情報（クライアントIP、User-Agent）も一緒に記録する。
 *
 * @package App\Logger
 */
class LambdaRequestIdProcessor implements ProcessorInterface
{
    /**
     * ログレコードに Lambda コンテキスト情報を追加
     *
     * @param LogRecord $record ログレコード
     * @return LogRecord 拡張されたログレコード
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $con = $_SERVER['LAMBDA_INVOCATION_CONTEXT'] ?? null;
        if (is_null($con)) {
            return $record;
        }

        $o = json_decode($con, false);

        return $record->with(
            extra: array_merge(
                $record->extra,
                [
                    'requestId' => $o->awsRequestId,
                    'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]
            )
        );
    }
}
