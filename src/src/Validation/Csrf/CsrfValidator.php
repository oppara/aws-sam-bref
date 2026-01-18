<?php

declare(strict_types=1);

namespace App\Validation\Csrf;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * CSRF検証クラス
 *
 * リクエストボディに含まれるCSRFトークンとクッキーに含まれるCSRFトークンを比較し、
 * 一致性を検証します。トークンが一致しない場合は CsrfException を発生させます。
 *
 * @package App\Validation\Csrf
 */
class CsrfValidator
{
    /**
     * コンストラクタ
     *
     * @param LoggerInterface $logger ロガー
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * CSRF トークンを検証
     *
     * @param Request $request リクエストオブジェクト
     * @throws CsrfException 検証に失敗した場合
     */
    public function validate(Request $request): void
    {
        $bodyToken   = $request->getParsedBody()['csrf_token'] ?? null;
        $cookieToken = $request->getCookieParams()['csrf_token'] ?? null;

        if (!$bodyToken || !$cookieToken || !hash_equals($cookieToken, $bodyToken)) {
            $this->logger->error('CSRF validation failed', [
                'bodyToken' => $bodyToken,
                'cookieToken' => $cookieToken,
            ]);
            throw new CsrfException('CSRF validation failed');
        }
    }
}
