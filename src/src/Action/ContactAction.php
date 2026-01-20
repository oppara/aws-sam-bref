<?php

declare(strict_types=1);

namespace App\Action;

use App\Config;
use App\Service\Mailer\Mailer;
use App\Service\Recaptcha\RecaptchaException;
use App\Service\Recaptcha\RecaptchaFactory;
use App\Validation\ContactValidator;
use App\Validation\Csrf\CsrfException;
use App\Validation\Csrf\CsrfValidator;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

/**
 * お問い合わせフォーム処理アクション
 *
 * **処理フロー：**
 * - `input()` - 入力画面表示
 * - `confirm()` - 入力値検証・確認画面表示
 * - `execute()` - メール送信処理実行
 * - `complete()` - 完了画面表示（トークン検証） *
 * **セキュリティ機能：**
 * - reCAPTCHA v3によるボット対策
 * - CSRF トークン検証
 * - 完了画面アクセス用トークン検証 */
class ContactAction
{
    /**
     * コンストラクタ
     *
     * @param Config $config アプリケーション設定
     * @param Twig $twig Twig テンプレートエンジン
     * @param Mailer $mailer メール送信クラス
     * @param CsrfValidator $csrfValidator CSRF検証クラス
     * @param LoggerInterface $logger ロガー
     */
    public function __construct(
        private Config $config,
        private Twig $twig,
        private Mailer $mailer,
        private CsrfValidator $csrfValidator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 入力画面を表示
     *
     * 初期状態のお問い合わせフォーム（input.twig）を表示します。
     * reCAPTCHA サイトキーをテンプレートに渡します。
     *
     * @param Request $request HTTP リクエスト
     * @param Response $response HTTP レスポンス
     * @return Response 入力画面のレンダリング済みレスポンス
     */
    public function input(Request $request, Response $response): Response
    {
        $this->logger->info('input complete', [
            'method' => __METHOD__,
        ]);

        return $this->render($request, $response, 'input.twig', [
            'data' => (array) $request->getParsedBody(),
            'errors' => [],
            'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
        ]);
    }

    /**
     * 入力内容の確認画面を表示
     *
     * 以下の検証を実行します：
     * 1. reCAPTCHA トークンの検証
     * 2. CSRF トークンの検証
     * 3. 入力値の検証
     *
     * いずれかの検証に失敗した場合は、エラーメッセージ付きで入力画面を表示します。
     * 検証成功時は確認画面（confirm.twig）を表示します。
     *
     * @param Request $request HTTP リクエスト（POSTデータを含む）
     * @param Response $response HTTP レスポンス
     * @return Response 確認画面またはエラー付き入力画面のレスポンス
     */
    public function confirm(Request $request, Response $response): Response
    {
        $this->logger->info('confirm start', [
            'method' => __METHOD__,
        ]);

        $data = (array) $request->getParsedBody();

        // reCAPTCHA検証（CSRFチェックの前）
        $recaptchaToken = $data['g-recaptcha-response'] ?? null;
        if (!$recaptchaToken) {
            $this->logger->error('reCAPTCHA token not found');
            $errors = ['flash' => 'reCAPTCHAトークンが見つかりません'];

            return $this->render($request, $response, 'input.twig', [
                'data' => $data,
                'errors' => $errors,
                'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
            ]);
        }

        try {
            $recaptchaService = RecaptchaFactory::create($this->config, $this->logger);
            $results = $recaptchaService->verify($recaptchaToken);

            if (!$results['success']) {
                $this->logger->warning('reCAPTCHA verification failed', $results);

                $errors = ['flash' => 'reCAPTCHA検証に失敗しました。もう一度お試しください。'];

                return $this->render($request, $response, 'input.twig', [
                    'data' => $data,
                    'errors' => ['flash' => 'reCAPTCHA検証に失敗しました。もう一度お試しください。'],
                    'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
                ]);
            }
        } catch (RecaptchaException $e) {
            $this->logger->error('reCAPTCHA verification error', [
                'error' => $e->getMessage(),
            ]);
            $errors = ['flash' => 'reCAPTCHA検証中にエラーが発生しました。'];

            return $this->render($request, $response, 'input.twig', [
                'data' => $data,
                'errors' => $errors,
                'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
            ]);
        }

        try {
            $this->csrfValidator->validate($request);
        } catch (CsrfException $e) {
            $this->logger->error('CSRF validation error', [
                'error' => $e->getMessage(),
            ]);
            $errors = ['flash' => 'CSRF検証に失敗しました。もう一度お試しください。'];

            return $this->render($request, $response, 'input.twig', [
                'data' => $data,
                'errors' => $errors,
                'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
            ]);
        }

        [$errors, $clean] = ContactValidator::validate($data);

        if ($errors) {
            $this->logger->info('validation failed', [
                'errors' => $errors
            ]);

            return $this->render($request, $response, 'input.twig', [
                'data' => $data,
                'errors' => $errors,
                'recaptchaSiteKey' => $this->config->recaptchaSiteKey,
            ]);
        }

        $this->logger->info('confirm complete', [
            'method' => __METHOD__,
        ]);

        return $this->render($request, $response, 'confirm.twig', [
            'data' => $clean,
        ]);
    }

    /**
     * お問い合わせを送信して完了画面へリダイレクト
     *
     * 以下の処理を実行します：
     * 1. CSRF トークンの検証
     * 2. 管理者へのメール送信
     * 3. ユーザーへの自動返信メール送信
     * 4. 完了トークンを生成してリダイレクト
     *
     * @param Request $request HTTP リクエスト（POSTデータにメール内容を含む）
     * @param Response $response HTTP レスポンス
     * @throws CsrfException CSRF検証失敗時
     * @throws PHPMailerException メール送信失敗時
     * @return Response 完了画面へのリダイレクトレスポンス（Location: /contact/complete?token=...）
     */
    public function execute(Request $request, Response $response): Response
    {
        $this->logger->info('execute start', [
            'method' => __METHOD__,
        ]);

        try {
            $this->csrfValidator->validate($request);
        } catch (CsrfException $e) {
            $this->logger->error('CSRF validation error', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $data = (array) $request->getParsedBody();
        if ($data) {
            try {
                $this->mailer->sendAdmin($data);
                $this->mailer->sendUser($data);
            } catch (PHPMailerException $e) {
                $this->logger->error('send email failed', [
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->logger->info('execute complete', [
            'method' => __METHOD__,
        ]);

        $token = $this->generateCompletionToken();

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/contact/complete?token=' . urlencode($token));
    }

    /**
     * 完了画面を表示
     *
     * クエリパラメータ `token` を検証し、有効な場合は完了画面を表示します。
     * トークンが無効または期限切れの場合は入力画面にリダイレクトします。
     *
     * @param Request $request HTTP リクエスト（GETパラメータにtokenを含む）
     * @param Response $response HTTP レスポンス
     * @return Response 完了画面またはリダイレクトレスポンス
     */
    public function complete(Request $request, Response $response): Response
    {
        $this->logger->info('complete start', [
            'method' => __METHOD__,
        ]);

        // クエリパラメータからトークンを取得して検証
        $token = $request->getQueryParams()['token'] ?? null;
        if (!$token || !$this->verifyCompletionToken($token)) {
            $this->logger->warning('invalid or expired token', [
                'method' => __METHOD__,
                'token' => $token,
            ]);

            return $this->redirectInput($request, $response);
        }

        $this->logger->info('complete complete', [
            'method' => __METHOD__,
        ]);

        return $this->render($request, $response, 'complete.twig');
    }

    /**
     * 入力画面にリダイレクト
     *
     * エラー時や無効なトークン時に入力画面へリダイレクトします。
     *
     * @param Request $request HTTP リクエスト
     * @param Response $response HTTP レスポンス
     * @return Response 入力画面へのリダイレクトレスポンス（Location: /contact）
     */
    public function redirectInput(Request $request, Response $response): Response
    {
        $this->logger->info('complete', [
            'method' => __METHOD__,
        ]);

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/contact');
    }

    /**
     * 完了画面アクセス用トークンを生成
     *
     * タイムスタンプと HMAC-SHA256 署名を含むトークンを生成します。
     * トークン形式: timestamp.signature
     *
     * @return string 署名付きトークン
     */
    private function generateCompletionToken(): string
    {
        $timestamp = (int) (microtime(true) * 1000); // ミリ秒
        $signature = hash_hmac('sha256', (string) $timestamp, 'contact_form_secret');

        return $timestamp . '.' . $signature;
    }

    /**
     * 完了画面アクセス用トークンを検証
     *
     * トークンの形式とHMAC-SHA256署名を検証します。
     * 指定秒数以上前のトークンは無効と判定します。
     *
     * **検証項目：**
     * - トークン形式: timestamp.signature
     * - タイムスタンプ: 数値であること
     * - 署名: HMAC-SHA256が一致すること
     * - 有効期限: 指定秒数以内であること（デフォルト: 10秒）
     *
     * @param string $token 検証対象のトークン（timestamp.signature 形式）
     * @param int $sec トークンの有効期限（秒）。デフォルト: 10秒
     * @return bool トークンが有効な場合 true、無効な場合 false
     */
    private function verifyCompletionToken(string $token, $sec = 10): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        [$timestamp, $signature] = $parts;

        // タイムスタンプが数値であるか確認
        if (!is_numeric($timestamp)) {
            return false;
        }

        // 署名を検証
        $expectedSignature = hash_hmac('sha256', $timestamp, 'contact_form_secret');
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }

        // タイムスタンプが有効期限内か確認（ $sec 秒以内）
        $currentTimestamp = (int) (microtime(true) * 1000);
        $tokenAge = $currentTimestamp - (int) $timestamp;
        $maxAge = $sec * 1000; // ミリ秒

        return $tokenAge <= $maxAge && $tokenAge >= 0;
    }

    /**
     * テンプレートをレンダリング
     *
     * Twig でテンプレートをレンダリングし、CSRFトークンを埋め込みます。
     * また、キャッシュを無効化するヘッダーを追加します。
     *
     * @param Request $request HTTP リクエスト（CSRFトークンを属性から取得）
     * @param Response $response HTTP レスポンス
     * @param string $template テンプレートファイル名（templates/ 相対パス）
     * @param array $params テンプレートに渡すパラメータ
     * @return Response レンダリング済みレスポンス
     */
    private function render(Request $request, Response $response, string $template, array $params = []): Response
    {
        $response = $this->twig->render($response, $template, array_merge($params, [
            'csrf_token' => $request->getAttribute('csrf_token'),
            'categoryLabels' => Config::getCategoryLabels(),
            'recaptchaType' => $this->config->recaptchaType,
        ]));

        return $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache');
    }
}
