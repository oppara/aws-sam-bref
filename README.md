# AWS SAM お問い合わせフォーム

AWS SAM で管理する Lambda 上で動作する PHP のお問い合わせフォーム。

- [Slim](https://github.com/slimphp/Slim) : マイクロフレームワーク
- [Bref](https://bref.sh/) : AWS Lambda Layer 用 PHP ランタイム
- Google reCAPTCHA による検証
- AWS SES を使用したメール送信

Lambda 環境はセッションが使用できない。
DynamoDB を使用すればセッション管理も可能だが、今回は使用しないシンプルな構成とする。

## システム要件

- AWS SAM CLI
- AWS SAM を実行できる AWS プロファイル
- 使用する PHP バージョンは Bref のランタイム（Layers）から選択する。
  - [Bref runtime versions](https://runtimes.bref.sh/?region=ap-northeast-1)
- Docker（開発時）

## SAM で作成される AWS リソース

- Lambda 関数
- API Gateway（HTTP API）
- CloudWatch Logs のロググループ
- API Gateway のカスタムドメイン
  - ACM 証明書（TLS）を紐づける

## 事前に作成しておく AWS リソース

- S3 バケット（ソースコードのアップロード用）
- カスタムドメイン用の Route 53 のホストゾーン
- カスタムドメイン用の ACM 証明書
- AWS SES SMTP 設定
- AWS SES ID（管理者のメールアドレス）
- AWS Secrets Manager シークレット

### AWS Secrets Manager のシークレット一覧

作成するシークレットと対応する PHP 環境変数の一覧。

| シークレット名 | 説明 | PHP 環境変数 |
| ------------ | ---- | -------- |
| {システム名}/{環境名}/ses-smtp-user | SES SMTP ユーザー | SES_SMTP_USER |
| {システム名}/{環境名}/ses-smtp-pass | SES SMTP パスワード | SES_SMTP_PASS |
| {システム名}/{環境名}/recaptocha-secret-key | reCAPTCHA シークレットキー | RECAPTCHA_SECRET_KEY |

## SAM パラメータ一覧

template.yaml で使用するパラメータ一覧。
上書きする場合は、 `samconfig.toml` の `parameter-overrides` で上書きする。

| パラメータ名 | デフォルト | 説明 | 必須 | PHP 環境変数 |
| ------------ | ---------- | ---- | ---- | -------- |
| CustomDomainName | - | カスタムドメイン名（例: form.example.com） | ○ | - |
| CertificateArn | - | ACM 証明書の ARN | ○ | - |
| HostedZoneName | - | Route 53 ホストゾーン名（末尾にドットが必要。例: example.com.） | ○ | - |
| AppEnv | - | アプリケーション環境（stg または prod） | ○ | APP_ENV |
| AppDebug | - | デバッグモード有効化（true でデバッグログ、false で本番運用） | ○ | APP_DEBUG |
| SmtpHost | email-smtp.ap-northeast-1.amazonaws.com | SMTP サーバのホスト名 | ○ | SMTP_HOST |
| SmtpPort | 587 | SMTP サーバポート番号 | ○ | SMTP_PORT |
| EmailFrom | - | SES 検証済み送信元メールアドレス | ○ | EMAIL_FROM |
| EmailFromName | - | EmailFrom の表示名 | - | EMAIL_FROM_NAME |
| EmailAdmin | - | SES 検証済み管理者のメールアドレス | ○ | EMAIL_ADMIN |
| MailSubjectAdmin | - | 管理者向けメール件名 | ○ | MAIL_SUBJECT_ADMIN |
| MailSubjectUser | - | ユーザー向けメール件名 | ○ | MAIL_SUBJECT_USER |
| RecaptchaType | v3 | reCAPTCHA タイプ（enterprise、v3、v2-checkbox、v2-invisible） | ○ | RECAPTCHA_TYPE |
| RecaptchaSiteKey | - | reCAPTCHA サイトキー | ○ | RECAPTCHA_SITE_KEY |
| RecaptchaProjectId | - | GCP プロジェクト ID（reCAPTCHA Enterprise 用） | ○ | RECAPTCHA_PROJECT_ID |
| RecaptchaScoreThreshold | 0.5 | reCAPTCHA スコア閾値 | ○ | RECAPTCHA_SCORE_THRESHOLD |


## AWS へのデプロイ

本番環境へデプロイを実行する。

```bash
make prod
```

## 開発環境

手元のマシンで動作確認を行う手順。
開発環境の環境変数は `.env` ファイルで設定する。
**`.env` ファイルはバージョン管理に含めないこと。**
`.env` ファイル修正時はコンテナの再起動が必要です。

### 1. 依存パッケージのインストール

```bash
make composer-install
```

### 2. 環境変数の設定

`.env.example` をコピーして `.env` ファイルを作成し、設定値を入力する。

```bash
cp .env.example .env
```

設定例

```env:.env
# Application
APP_ENV=develop
APP_DEBUG=true

# SMTP & Mail
SMTP_HOST=email-smtp.ap-northeast-1.amazonaws.com
SMTP_PORT=587
SES_SMTP_USER=your-ses-smtp-user
SES_SMTP_PASS=your-ses-smtp-password
EMAIL_FROM=noreply@example.com
EMAIL_ADMIN=admin@example.com

# reCAPTCHA
RECAPTCHA_TYPE=v3
RECAPTCHA_SITE_KEY=your-site-key...
RECAPTCHA_SECRET_KEY=your-secret-key...
RECAPTCHA_SCORE_THRESHOLD=0.5
```

### 3. Docker の起動

コンテナを起動する。

```bash
make up
```

フォアグラウンドで起動するので、停止する場合は `Ctrl + C` を押す。

### 4. 動作確認

`http://localhost:8088/contact` にブラウザでアクセスする。

## reCAPTCHA

以下、実装する

- [ ] enterprise
- [x] v3
- [ ] v2 invisible
- [ ] v2 checkbox

#### reCAPTCHA v3

| 環境変数 | 説明 | 必須 |
|---------|------|------|
| `RECAPTCHA_TYPE` | reCAPTCHA タイプ（v3） | ✓ |
| `RECAPTCHA_SITE_KEY` | reCAPTCHA サイトキー | ✓ |
| `RECAPTCHA_SECRET_KEY` | reCAPTCHA シークレットキー | ✓ |
| `RECAPTCHA_SCORE_THRESHOLD` | スコア閾値（0.0～1.0、デフォルト: 0.5） | - |


## 設定例


## API エンドポイント

### GET /contact

入力画面を表示する。

### POST /contact/confirm

入力内容を検証し問題なければ確認画面を表示する。
問題があれあば、エラーメッセージ付きの入力画面を表示する。

- reCAPTCHA 検証
- CSRF トークン検証
- 入力値検証

### POST /contact/execute

メール送信と完了画面へのリダイレクトを行う。

- CSRF トークン検証
- 管理者メール送信
- ユーザー自動返信メール送信

### GET /contact/complete?token={token}

完了画面表示を表示する。

- トークン検証（有効期限: 10 秒）

## ディレクトリ構成

```text
src/
├── public/
│   └── index.php              # アプリケーションエントリポイント
├── src/
│   ├── Action/
│   │   ├── ContactAction.php  # お問い合わせフォーム処理
│   │   └── ErrorAction.php    # エラーハンドリング
│   ├── Config.php             # 設定クラス
│   ├── Logger/
│   │   ├── LoggerFactory.php
│   │   ├── LambdaRequestIdProcessor.php
│   │   └── MaskingProcessor.php
│   ├── Service/
│   │   ├── Mailer/            # メール送信
│   │   └── Recaptcha/         # reCAPTCHA 検証
│   ├── Validation/            # 入力値検証
│   └── Config.php
├── templates/                  # Twig テンプレート
│   ├── input.twig
│   ├── confirm.twig
│   ├── complete.twig
│   └── mail/
│       ├── admin.twig
│       └── user.twig
├── composer.json
├── vendor/                     # Composer の依存パッケージ
└── php-conf/
    └── zzz-security.ini       # PHP セキュリティ設定
```
