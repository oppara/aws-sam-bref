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
use App\Service\Session\SessionInterface;
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
 * - `complete()` - 完了画面表示
 *
 * **セキュリティ機能：**
 * - reCAPTCHA v3によるボット対策
 * - CSRF トークン検証
 * - セッションベースの送信済み管理 */
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
     * @param SessionInterface $session セッション
     */
    public function __construct(
        private Config $config,
        private Twig $twig,
        private Mailer $mailer,
        private CsrfValidator $csrfValidator,
        private LoggerInterface $logger,
        private SessionInterface $session,
    ) {
    }

    /**
     * 入力画面を表示
     *
     * 初期状態のお問い合わせフォーム（input.twig）を表示します。
     * reCAPTCHA サイトキーをテンプレートに渡します。
     *
     * セッションから `contact_data` と `contact_errors` を取得して表示し、
     * 1回限りで削除します。
     *
     * @param Request $request HTTP リクエスト
     * @param Response $response HTTP レスポンス
     * @return Response 入力画面のレンダリング済みレスポンス
     */
    public function input(Request $request, Response $response): Response
    {
        $this->logger->info('input start', [
            'method' => __METHOD__,
        ]);

        $data = $this->session->get('contact_data') ?? [];
        $errors = $this->session->get('contact_errors') ?? [];
        $this->session->delete('contact_data');
        $this->session->delete('contact_errors');

        $this->logger->debug('input session', [
            'data' => $data,
            'errors' => $errors,
        ]);

        $this->logger->info('input complete', [
            'method' => __METHOD__,
        ]);

        return $this->render($request, $response, 'input.twig', [
            'data' => $data,
            'errors' => $errors,
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
     * いずれかの検証に失敗した場合、Flashエラーを追加して
     * 入力データをセッション保存して入力画面にリダイレクトします。
     * 検証成功時は確認画面（confirm.twig）を表示します。
     *
     * @param Request $request HTTP リクエスト（POSTデータを含む）
     * @param Response $response HTTP レスポンス
     * @return Response 確認画面またはリダイレクトレスポンス
     */
    public function confirm(Request $request, Response $response): Response
    {
        $this->logger->info('confirm start', [
            'method' => __METHOD__,
        ]);

        $data = (array) $request->getParsedBody();
        $this->logger->debug('confirm data', [
            'data' => $data,
        ]);

        try {
            $recaptchaToken = $data['g-recaptcha-response'] ?? null;
            if (!$recaptchaToken) {
                $this->logger->error('reCAPTCHA token not found');
                $this->addFlashError('reCAPTCHAトークンが見つかりません');
                return $this->redirectToContact($response, $data);
            }

            $recaptchaService = RecaptchaFactory::create($this->config, $this->logger);
            $results = $recaptchaService->verify($recaptchaToken);

            if (!$results['success']) {
                $this->logger->warning('reCAPTCHA verification failed', $results);
                $this->addFlashError('reCAPTCHA検証に失敗しました。もう一度お試しください。');
                return $this->redirectToContact($response, $data);
            }

            $this->csrfValidator->validate($request);

        } catch (RecaptchaException $e) {
            $this->logger->error('reCAPTCHA verification error', [
                'error' => $e->getMessage(),
            ]);
            $this->addFlashError('reCAPTCHA検証中にエラーが発生しました。');
            return $this->redirectToContact($response, $data);
        } catch (CsrfException $e) {
            $this->logger->error('CSRF validation error', [
                'error' => $e->getMessage(),
            ]);
            $this->addFlashError('CSRF検証に失敗しました。もう一度お試しください。');
            return $this->redirectToContact($response, $data);
        }

        [$errors, $clean] = ContactValidator::validate($data);
        if ($errors) {
            $this->logger->info('validation failed', [
                'errors' => $errors
            ]);
            $this->addFlashError('入力内容を確認してください。');

            // バリデーションエラーをセッションに保存してリダイレクト
            return $this->redirectToContact($response, $data, $errors);
        }

        // 検証済みデータをセッションに保存
        $this->session->set('contact_data', $clean);
        // エラーをクリア
        $this->session->delete('contact_errors');

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
     * 4. 送信済みフラグをセッションに保存して完了画面へリダイレクト
     *
     * @param Request $request HTTP リクエスト（POSTデータにメール内容を含む）
     * @param Response $response HTTP レスポンス
     * @throws CsrfException CSRF検証失敗時
     * @throws PHPMailerException メール送信失敗時
     * @return Response 完了画面へのリダイレクトレスポンス
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

            $this->addFlashError('CSRF検証に失敗しました。もう一度お試しください。');
            return $this->redirectToContact($response);
        }

        // セッションからデータを取得
        $data = $this->session->get('contact_data');
        if (!$data || !is_array($data)) {
            $this->logger->error('contact_data not found in session');
            return $this->redirectToContact($response);
        }

        try {
            $this->mailer->sendAdmin($data);
            $this->mailer->sendUser($data);
        } catch (PHPMailerException $e) {
            $this->logger->error('send email failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->info('execute complete', [
            'method' => __METHOD__,
        ]);

        // 送信済みだからcontact_dataを削除し、送信済みフラグを保存
        $this->session->delete('contact_data');
        $this->session->set('contact_sent', true);

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/contact/complete');
    }

    /**
     * 完了画面を表示
     *
     * セッション内の送信済みフラグを検査し、有効な場合は完了画面を表示します。
     * フラグがない場合は入力画面にリダイレクトします。
     *
     * @param Request $request HTTP リクエスト
     * @param Response $response HTTP レスポンス
     * @return Response 完了画面またはリダイレクトレスポンス
     */
    public function complete(Request $request, Response $response): Response
    {
        $this->logger->info('complete start', [
            'method' => __METHOD__,
        ]);

        // 送信済みフラグを確認
        $sent = $this->session->get('contact_sent');
        if (!$sent) {
            $this->logger->warning('contact not sent', [
                'method' => __METHOD__,
            ]);

            return $this->redirectToContact($response);
        }

        $this->session->regenerateId(true);

        $this->logger->info('complete complete', [
            'method' => __METHOD__,
        ]);

        // 送信済みフラグを削除
        $this->session->delete('contact_sent');

        return $this->render($request, $response, 'complete.twig');
    }

    /**
     * 入力画面へリダイレクト
     *
     * Flash エラーメッセージを追加し、入力データとバリデーション エラーを
     * セッションに保存して入力画面にリダイレクトします。
     *
     * @param Response $response HTTP レスポンス
     * @param array<string, mixed> $data 入力データ
     * @param array<string, mixed> $errors エラーメッセージとバリデーション エラー
     * @return Response 入力画面へのリダイレクトレスポンス
     */
    private function redirectToContact(Response $response, array $data = [], array $errors = []): Response
    {
        $this->session->set('contact_errors', $errors);
        $this->session->set('contact_data', $data);

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/contact');
    }

    /**
     * Flashエラーメッセージを追加
     *
     * @param string $message エラーメッセージ
     * @return void
     */
    private function addFlashError(string $message): void
    {
        $this->session->getFlash()->add('error', $message);
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
        // フラッシュメッセージを取得
        $flash = $this->session->getFlash()->all();

        $response = $this->twig->render($response, $template, array_merge($params, [
            'csrf_token' => $request->getAttribute('csrf_token'),
            'categoryLabels' => Config::getCategoryLabels(),
            'recaptchaType' => $this->config->recaptchaType,
            'flash' => $flash,
            'session' => $this->session,
        ]));

        return $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache');
    }
}
