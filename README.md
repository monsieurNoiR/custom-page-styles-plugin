# Custom Page Styles Manager

ページ固有のカスタムスタイルシート管理機能を提供するWordPressプラグインです。各ページにカスタムCSSを記述し、過去のスタイルシートを再利用できます。

## 概要

このプラグインは、WordPressの各ページに個別のカスタムCSSを適用できる機能を提供します。以下の特徴があります。

- ページごとに独自のCSSを記述・保存
- 過去に作成したスタイルシートを他のページで再利用
- 適用するスタイルシートは最大2つまで（新規作成1つ + 既存選択1つ）
- 機能を有効化する投稿タイプを選択可能
- セキュアなファイル管理とデータ検証

## 主な機能

### 1. カスタムCSSの記述と保存
- 投稿編集画面に専用のメタボックスを追加
- テキストエリアで直接CSSコードを記述
- 保存時に自動的にCSSファイルを生成（`wp-content/uploads/custom-page-styles/post-styles-{投稿ID}.css`）
- データベースにもカスタムフィールドとして保存

### 2. 既存スタイルシートの再利用
- 他のページで作成したスタイルシートをセレクトボックスから選択
- 「投稿タイトル (ID: XXX, 投稿タイプ)」形式で表示
- 最大1つまで選択可能

### 3. スタイルシート適用ルール
1つのページに適用できるスタイルシートは以下の2種類まで:
- **新規作成**: 現在のページで新しく記述したCSS（1つ）
- **既存選択**: 過去に作成されたスタイルシートから選択（1つ、オプション）

### 4. 投稿タイプ選択
- 管理画面の「設定 > Custom Page Styles」から設定
- チェックボックスで機能を有効化する投稿タイプを複数選択可能
- デフォルトでは「投稿」と「固定ページ」が有効

## インストール方法

### 手動インストール

1. このプラグインフォルダ全体を `/wp-content/plugins/` ディレクトリにコピー
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定 > Custom Page Styles」から対象投稿タイプを選択（任意）

### 要件

- **WordPress**: 5.0以上
- **PHP**: 7.4以上
- **書き込み権限**: `wp-content/uploads/` ディレクトリへの書き込み権限

## 使い方

### 基本的な使い方

1. **投稿タイプの設定**
   - 「設定 > Custom Page Styles」にアクセス
   - 機能を有効化したい投稿タイプにチェックを入れる
   - 「設定を保存」をクリック

2. **カスタムCSSの記述**
   - 投稿編集画面を開く
   - 「Custom Page Styles」メタボックスを探す
   - 「Custom CSS for this page:」のテキストエリアにCSSを記述
   - 投稿を保存または更新

3. **既存スタイルシートの選択**
   - 同じメタボックス内の「Or select an existing stylesheet:」セレクトボックスから選択
   - 過去に作成したスタイルシートが一覧表示されます
   - 1つまで選択可能

4. **フロントエンドでの確認**
   - 投稿を表示すると、記述したCSSが自動的に適用されます
   - ブラウザの開発者ツールで `<link>` タグを確認できます

### CSS記述例

```css
/* ページ固有の背景色 */
body {
    background-color: #f0f8ff;
}

/* カスタム見出しスタイル */
.entry-content h2 {
    color: #2c3e50;
    border-left: 4px solid #3498db;
    padding-left: 10px;
}

/* 特定のセクションのスタイル */
.custom-section {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
}
```

## セキュリティ機能

このプラグインは以下のセキュリティ対策を実装しています。

### 1. Nonce検証
- すべてのフォーム送信でWordPress Nonceを使用
- CSRF攻撃を防止

### 2. 権限チェック
- `current_user_can()` で編集権限を確認
- 権限のないユーザーはデータを保存できません

### 3. データサニタイゼーション
- `wp_strip_all_tags()` でHTMLタグを除去
- 危険なCSSプロパティを自動削除:
  - `@import`（外部ファイル読み込み）
  - `javascript:`（XSS攻撃）
  - `expression()`（IE特有の脆弱性）
  - `behavior`、`-moz-binding`（バインディング攻撃）
- 基本的なCSS構文検証（括弧のバランスチェック）

### 4. XSS対策
- 出力時に `esc_html()`, `esc_attr()`, `esc_textarea()` を使用
- すべてのユーザー入力をエスケープ処理

### 5. SQLインジェクション対策
- `$wpdb->prepare()` でパラメータをバインド
- プレースホルダーを使用した安全なクエリ実行

### 6. 安全なファイル操作
- WordPress Filesystem API (`WP_Filesystem`) を使用
- 直接的な `file_put_contents()` は使用しない
- CSSファイル保存ディレクトリに `index.php` と `.htaccess` を自動配置
- パストラバーサル攻撃対策（`realpath()` による検証）
- ファイルパスの厳格な検証

### 7. 追加のセキュリティ機能
- POSTデータの`wp_unslash()`処理
- すべての投稿IDを`absint()`で検証
- ファイル操作前のディレクトリ検証
- `.htaccess`によるPHP実行防止

## ファイル構成

```
custom-page-styles-plugin/
├── custom-page-styles.php    # メインプラグインファイル
├── uninstall.php             # アンインストール時のクリーンアップ
└── README.md                 # このファイル
```

### 生成されるファイル

プラグインが自動的に以下のファイルを生成します。

```
wp-content/uploads/
└── custom-page-styles/
    ├── index.php                    # セキュリティ用（ディレクトリリスティング防止）
    ├── .htaccess                    # PHP実行防止
    ├── post-styles-123.css          # 投稿ID 123のスタイル
    ├── post-styles-456.css          # 投稿ID 456のスタイル
    └── ...
```

## データベース構造

### カスタムフィールド（Post Meta）

| メタキー | 説明 | データ型 |
|---------|------|---------|
| `_custom_page_styles_css` | カスタムCSS内容 | string (text) |
| `_custom_page_styles_selected` | 選択された既存スタイルシートのID | int |

### オプション

| オプション名 | 説明 | データ型 |
|------------|------|---------|
| `custom_page_styles_enabled_post_types` | 有効な投稿タイプ配列 | array |

## 制限事項と注意点

### 制限事項

1. **スタイルシート数の制限**
   - 1ページあたり最大2つまで（新規1つ + 既存選択1つ）

2. **CSS検証**
   - 基本的な構文チェックのみ実施
   - CSS Lintのような高度な検証は行いません

3. **対象ページ**
   - シングルページ（`is_singular()`）でのみ適用
   - アーカイブページや検索結果ページでは適用されません

### 注意点

1. **パフォーマンス**
   - 多数のページでカスタムCSSを使用すると、CSSファイル数が増加します
   - 定期的に不要なスタイルシートを削除することを推奨

2. **CSS記述**
   - セレクタの詳細度に注意してください
   - テーマやプラグインのCSSと競合する可能性があります
   - `!important` の多用は避けることを推奨

3. **バックアップ**
   - プラグイン削除前に重要なCSSをバックアップしてください
   - CSSファイルは `wp-content/uploads/custom-page-styles/` に保存されています

4. **キャッシュ**
   - キャッシュプラグイン使用時は、CSS変更後にキャッシュをクリアしてください

5. **権限**
   - `wp-content/uploads/` ディレクトリへの書き込み権限が必要です
   - サーバー設定で権限が制限されている場合、CSSファイル生成が失敗する可能性があります

## トラブルシューティング

### CSSが適用されない

1. **ファイルの確認**
   - `wp-content/uploads/custom-page-styles/` ディレクトリが存在するか確認
   - 該当するCSSファイルが生成されているか確認

2. **権限の確認**
   - uploadsディレクトリの書き込み権限を確認
   - 通常は `755` (ディレクトリ) と `644` (ファイル) が推奨

3. **キャッシュのクリア**
   - ブラウザキャッシュをクリア
   - WordPressキャッシュプラグインをクリア

4. **投稿タイプの確認**
   - 「設定 > Custom Page Styles」で該当の投稿タイプが有効化されているか確認

### エラーメッセージが表示される

1. **CSS validation error: Unbalanced braces detected**
   - CSS内の `{` と `}` の数が一致していません
   - CSSコードを確認し、括弧を修正してください

2. **Failed to create CSS directory**
   - uploadsディレクトリへの書き込み権限がありません
   - サーバー管理者に権限設定を確認してください

3. **Failed to write CSS file**
   - ファイル書き込みに失敗しました
   - ディスク容量とファイル権限を確認してください

## 開発者向け情報

### フック

プラグインは標準的なWordPressフックを使用しています。

#### アクションフック

- `admin_menu` - 設定ページ追加
- `admin_init` - 設定登録
- `add_meta_boxes` - メタボックス追加
- `save_post` - メタデータ保存
- `wp_enqueue_scripts` - フロントエンドでスタイル読み込み

### 定数

プラグイン内で使用される主要な定数:

```php
Custom_Page_Styles_Manager::VERSION                    // プラグインバージョン
Custom_Page_Styles_Manager::META_KEY_CSS              // CSSメタキー
Custom_Page_Styles_Manager::META_KEY_SELECTED         // 選択スタイルシートメタキー
Custom_Page_Styles_Manager::OPTION_ENABLED_POST_TYPES // 有効投稿タイプオプション
Custom_Page_Styles_Manager::CSS_DIR_NAME              // CSSディレクトリ名
```

### カスタマイズ例

#### 生成されるCSSファイル名を変更

`generate_css_file()` メソッド内の以下の行を変更:

```php
$css_file = trailingslashit( $css_dir ) . 'custom-styles-' . $post_id . '.css';
```

#### 追加のCSS検証ルールを追加

`sanitize_css()` メソッド内の `$dangerous_patterns` 配列に追加:

```php
$dangerous_patterns = array(
    '/@import/i',
    '/javascript:/i',
    '/expression\s*\(/i',
    '/behavior\s*:/i',
    '/-moz-binding/i',
    '/your-custom-pattern/i', // 追加
);
```

## アンインストール

プラグインを削除する場合:

1. WordPressダッシュボードからプラグインを無効化
2. 「削除」をクリック

プラグインは `uninstall.php` を使用して、以下を**自動的にクリーンアップ**します:
- すべてのカスタムフィールド（`_custom_page_styles_css`, `_custom_page_styles_selected`）
- プラグインオプション（`custom_page_styles_enabled_post_types`）
- `wp-content/uploads/custom-page-styles/` ディレクトリとその中のすべてのファイル

**注意**: アンインストール後、すべてのカスタムCSSデータが完全に削除されます。重要なCSSは事前にバックアップしてください。

## サポート

このプラグインに関する質問や問題がある場合:

- GitHubのIssuesページで報告
- WordPress.orgサポートフォーラムで質問

## ライセンス

GPL v2 or later

## 変更履歴

### 1.0.0
- 初回リリース
- カスタムCSS記述機能
- 既存スタイルシート選択機能
- 投稿タイプ選択機能
- セキュリティ機能実装

## クレジット

開発: Claude Code
