# Slim Framework Contact Form - AI Coding Guide

## プロジェクト概要

Slim Framework 4 ベースのお問い合わせフォームアプリケーション。入力→確認→完了の3ステップフローを実装。

## アーキテクチャ

### ディレクトリ構造
- `src/Action/` - アクションクラス(コントローラー相当)
- `src/Validation/` - バリデーションルール定義
- `templates/` - Twig テンプレート
- `public/` - Webルート(index.phpがエントリーポイント)

### 主要コンポーネント

**Action クラスパターン** ([src/Action/ContactAction.php](src/Action/ContactAction.php))
- 1つのActionクラスに複数のメソッド(`form`, `confirm`, `complete`)を定義
- コンストラクタで依存性注入(Twigなど)
- PSR-7 Request/Response を使用
- 各メソッドは `Response` を返す

## コーディング規約

### PHP スタイル
- **strict types**: 全ファイルで `declare(strict_types=1);` を宣言
- **型宣言**: すべての引数・戻り値に型を明示
- **プロパティプロモーション**: コンストラクタで `private Twig $twig` のような省略記法を使用
- **配列分割代入**: `[$errors, $clean] = $this->validate($data);` パターンを活用

### 命名規則
- **日本語コメント**: メソッドの説明は日本語DocBlockを使用 (`/** 入力画面 */`)
- **変数の整列**: 同種の変数は `=` で位置を揃える
  ```php
  $errors = [];
  $clean  = [];
  ```

### バリデーション実装
- `ContactValidator::rules()` が全フィールドのルールを返す静的メソッド
- 各ルールは `['rule' => Validator, 'message' => string]` 形式の配列
- バリデーションエラーは最初の1つでbreak、成功時に `$clean` 配列に格納

### CSRF対策
- Cookie と POSTボディの両方でトークンを検証
- `hash_equals()` を使用したタイミングセーフな比較
- カスタム実装(外部ライブラリ不使用)
- Request属性からcsrf_tokenを取得し、テンプレートに自動注入

### レスポンス処理
- **キャッシュ制御**: すべてのレスポンスに以下を付与
  ```php
  ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
  ->withHeader('Pragma', 'no-cache')
  ```
- **Twigレンダリング**: プライベート`render()`メソッドでcsrf_tokenを自動注入

## データフロー

1. **入力画面** (`form()`) → `input.twig`
2. **確認画面** (`confirm()`) → CSRF検証 → バリデーション → 成功: `confirm.twig` / 失敗: `input.twig`(エラー表示)
3. **完了画面** (`complete()`) → CSRF検証 → メール送信/DB保存 → `complete.twig`

## 新機能追加時の注意点

### 新しいフォームフィールド追加
1. `ContactValidator::rules()` にルールを追加
2. テンプレート(`input.twig`, `confirm.twig`)を更新
3. バリデーションロジックは自動的に適用される

### 新しいActionクラス作成
- `App\Action` 名前空間を使用
- コンストラクタでTwigを注入
- 各メソッドは `Request $request, Response $response` を受け取り `Response` を返す
- CSRF検証が必要な場合は `checkCsrf()` パターンを踏襲

### セキュリティ実装
- グローバル変数 `$_COOKIE` への直接アクセスはCSRF検証のみ
- ユーザー入力は必ず `getParsedBody()` 経由で取得
- 出力前に `trim()` でサニタイズ(追加のエスケープはTwig側で実施)

## 開発ワークフロー

### ローカル実行
- PHPビルトインサーバーまたはDockerを使用(詳細は要確認)
- `public/index.php` がエントリーポイント

### 依存関係
- Slim Framework 4
- Twig (テンプレートエンジン)
- PSR-7/PSR-15 準拠

## よくある実装パターン

**エラーハンドリング**
```php
if ($errors) {
    return $this->render($request, $response, 'input.twig', [
        'data'   => $data,
        'errors' => $errors,
    ]);
}
```

**配列型キャスト**
```php
$data = (array)$request->getParsedBody();
```

このパターンでnullセーフティを確保。
