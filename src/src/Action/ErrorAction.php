<?php

declare(strict_types=1);

namespace App\Action;

use App\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Throwable;

/**
 * エラーハンドリングアクション
 *
 * Slim フレームワークで発生した例外やエラーを処理し、
 * 適切なエラーページを表示する。
 *
 * エラーの種類に応じて異なるテンプレートを使用する：
 * - 404 Not Found → error/404.twig
 * - 500 Internal Server Error → error/500.twig
 *
 * デバッグモード時は、スタックトレースをテンプレートに渡す。
 */
final class ErrorAction implements ErrorHandlerInterface
{
    /**
     * コンストラクタ
     *
     * @param Config $config アプリケーション設定
     * @param Twig $twig Twig テンプレートエンジン
     * @param LoggerInterface $logger ロガー
     */
    public function __construct(
        private Config $config,
        private Twig $twig,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * エラーを処理してレスポンスを生成
     *
     * 例外の種類に応じて適切なHTTPステータスコードとテンプレートを選択する。
     * エラー情報をログに記録し、レンダリング済みレスポンスを返す。
     *
     * @param ServerRequestInterface $request HTTP リクエスト
     * @param Throwable $exception 発生した例外
     * @param bool $displayErrorDetails エラー詳細を表示するかどうか
     * @param bool $logErrors ログに記録するかどうか
     * @param bool $logErrorDetails ログに詳細情報を含めるかどうか
     * @return ResponseInterface エラーページのレスポンス
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = 500;
        $template = 'error/500.twig';

        if ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $template = 'error/404.twig';
        }

        $ctx = [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($status === 500 && $this->config->isDebug) {
            $ctx['trace'] = $exception->gettrace();
        }
        $this->logger->error((string) $status, $ctx);

        $html = $this->twig->fetch($template, [
            'exception' => $displayErrorDetails ? $exception : null,
        ]);

        $response = new Response($status);
        $response->getBody()->write($html);

        return $response->withHeader(
            'Content-Type',
            'text/html; charset=UTF-8'
        );
    }
}
