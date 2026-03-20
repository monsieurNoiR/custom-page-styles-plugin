# Studio Noir Custom Page Styles

ページ固有のカスタムスタイルシート管理機能を提供するWordPressプラグインです。v2.0では Style Library（専用カスタム投稿タイプ）を導入し、CSSを独立したエンティティとして管理できるようになりました。

## 概要

このプラグインは、WordPressの各ページに個別のカスタムCSSを適用できる機能を提供します。以下の特徴があります。

- ページごとに独自のCSSを記述・保存
- CSS/JavaScriptファイルのアップロード対応
- **Style Library** — CSSを独立エンティティとして管理（v2.0）
- **Save to Library / Sync to Library** — ページのCSSを名前付きスタイルとして登録・更新（v2.0）
- ドラッグ&ドロップで読み込み順序を自由に変更
- 機能を有効化する投稿タイプを選択可能
- セキュアなファイル管理とデータ検証

## 主な機能

### 1. カスタムCSSの記述と保存
- 投稿編集画面に専用のメタボックスを追加
- テキストエリアで直接CSSコードを記述
- 保存時に自動的にCSSファイルを生成（`wp-content/uploads/sn-cps-styles/post-styles-{投稿ID}.css`）
- データベースにもカスタムフィールドとして保存

### 2. ファイルアップロード機能（v1.1.0）
- **CSS/JavaScriptファイルのアップロード対応**
- 投稿ごとに専用ディレクトリで管理（`/sn-cps-styles/{投稿ID}/`）
- 元のファイル名を保持
- ファイルタイプ検証（CSS/JSのみ）
- ファイルサイズ制限（5MB）
- **JavaScript読み込み位置選択** (header/footer)

### 3. Style Library（v2.0）
- **Style Library** — `sn_cps_style` カスタム投稿タイプでCSSを独立エンティティとして管理
- **Save to Library** — ページのCSSに名前を付けてLibraryに登録
- **Sync to Library** — 既存のLibraryエントリを上書き更新、または新しいエントリとして保存
- Library経由でページに適用可能、**ドラッグ&ドロップで読み込み順序を変更**
- v1.x データは有効化時に自動マイグレーション

### 4. 最適化された読み込み順序（v1.1.0）
スタイルは以下の順序で読み込まれます:

1. **選択スタイル** - ベーステンプレート、再利用パーツ
2. **アップロードファイル** - ライブラリ、フレームワーク
3. **直接記述CSS** - 最終調整、上書き

この順序により、ライブラリを最初に読み込み、最後に細かい調整を加えることができます。

### 5. 投稿タイプ選択
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

2. **ファイルのアップロード（v1.1.0）**
   - 投稿編集画面を開く
   - 「Upload CSS/JS Files」セクションで「Choose File」をクリック
   - CSS または JS ファイルを選択
   - 「Add File」をクリック
   - JSファイルの場合、header/footerを選択可能

3. **既存スタイルシートの選択**
   - 「Add existing stylesheets」セクションのドロップダウンから選択
   - 「+ Add」をクリック
   - 追加したスタイルをドラッグ&ドロップで並び替え
   - 不要なスタイルは「Remove」で削除

4. **カスタムCSSの記述**
   - 「Custom CSS for this page」のテキストエリアにCSSを記述
   - 投稿を保存または更新

5. **フロントエンドでの確認**
   - 投稿を表示すると、すべてのスタイルが自動的に適用されます
   - ブラウザの開発者ツールで `<link>` / `<script>` タグを確認できます

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
- すべてのフォーム送信とAJAXリクエストでWordPress Nonceを使用
- CSRF攻撃を防止

### 2. 権限チェック
- `current_user_can()` で編集権限を確認
- 権限のないユーザーはデータを保存できません

### 3. データサニタイゼーション
- `wp_strip_all_tags()` でHTMLタグを除去
- ファイル名の `sanitize_file_name()` 処理
- 危険なCSSプロパティを自動削除:
  - `@import`（外部ファイル読み込み）
  - `javascript:`（XSS攻撃）
  - `expression()`（IE特有の脆弱性）
  - `behavior`、`-moz-binding`（バインディング攻撃）
- 基本的なCSS構文検証（括弧のバランスチェック）

### 4. ファイルアップロードセキュリティ（v1.1.0）
- **ファイルタイプ検証**: CSS/JSファイルのみ許可
- **ファイルサイズ制限**: 5MB以内
- **ファイル名サニタイゼーション**: パストラバーサル対策
- **投稿別ディレクトリ分離**: `/sn-cps-styles/{post_id}/`

### 5. XSS対策
- 出力時に `esc_html()`, `esc_attr()`, `esc_textarea()` を使用
- すべてのユーザー入力をエスケープ処理
- JavaScriptでのDOM操作に `.text()` / `.val()` 使用（自動エスケープ）

### 6. SQLインジェクション対策
- `$wpdb->prepare()` でパラメータをバインド
- プレースホルダーを使用した安全なクエリ実行

### 7. 安全なファイル操作
- WordPress Filesystem API (`WP_Filesystem`) を使用
- 直接的な `file_put_contents()` は使用しない
- CSSファイル保存ディレクトリに `index.php` と `.htaccess` を自動配置
- パストラバーサル攻撃対策（`realpath()` による検証）
- ファイルパスの厳格な検証

## ファイル構成

```
custom-page-styles-project/
├── studio-noir-page-styles/      # プラグイン本体
│   ├── custom-page-styles.php    # メインプラグインファイル
│   ├── uninstall.php             # アンインストール時のクリーンアップ
│   ├── README.md                 # このファイル
│   ├── readme.txt                # WordPress.org用README
│   └── languages/                # 翻訳ファイル
├── custom-page-styles.zip        # 配布用zipファイル
└── wordpress-org-svn/            # WordPress.org SVN用
```

### 生成されるファイル（v1.1.0）

プラグインが自動的に以下のファイルを生成します。

```
wp-content/uploads/
└── sn-cps-styles/
    ├── index.php                    # セキュリティ用（ディレクトリリスティング防止）
    ├── .htaccess                    # PHP実行防止
    ├── post-styles-123.css          # 投稿ID 123の自動生成CSS
    ├── 123/                         # 投稿ID 123用アップロードファイル
    │   ├── animation.js
    │   ├── custom-library.css
    │   └── effects.js
    ├── post-styles-456.css          # 投稿ID 456の自動生成CSS
    └── 456/                         # 投稿ID 456用アップロードファイル
        └── special-style.css
```

## データベース構造

### カスタムフィールド（Post Meta）

| メタキー | 説明 | データ型 | バージョン |
|---------|------|---------|-----------|
| `_sn_cps_css` | カスタムCSS内容 | string (text) | v1.0.0+ |
| `_sn_cps_selected` | 選択されたスタイルシートID配列 | array | v1.0.0+ (v1.1.0で配列化) |
| `_sn_cps_uploaded_files` | アップロードファイル情報配列 | array | v1.1.0+ |

#### `_sn_cps_uploaded_files` 構造例（v1.1.0）

```php
array(
    array(
        'filename' => 'animation.js',
        'type' => 'js',
        'load_in' => 'footer'
    ),
    array(
        'filename' => 'custom-style.css',
        'type' => 'css',
        'load_in' => 'header'
    )
)
```

### オプション

| オプション名 | 説明 | データ型 |
|------------|------|---------|
| `sn_cps_enabled_post_types` | 有効な投稿タイプ配列 | array |

## トラブルシューティング

### CSSが適用されない

1. **ファイルの確認**
   - `wp-content/uploads/sn-cps-styles/` ディレクトリが存在するか確認
   - 該当するCSSファイルが生成されているか確認

2. **権限の確認**
   - uploadsディレクトリの書き込み権限を確認
   - 通常は `755` (ディレクトリ) と `644` (ファイル) が推奨

3. **キャッシュのクリア**
   - ブラウザキャッシュをクリア
   - WordPressキャッシュプラグインをクリア

4. **投稿タイプの確認**
   - 「設定 > Custom Page Styles」で該当の投稿タイプが有効化されているか確認

### アップロードファイルが読み込まれない（v1.1.0）

1. **ファイルの存在確認**
   - `/sn-cps-styles/{投稿ID}/` ディレクトリにファイルが存在するか確認

2. **ファイルタイプ確認**
   - CSS/JSファイルのみサポート
   - 拡張子が正しいか確認

3. **読み込み位置確認**
   - JSファイルの場合、header/footerの設定を確認
   - ブラウザの開発者ツールでscriptタグを確認

## 開発者向け情報

### フック

プラグインは標準的なWordPressフックを使用しています。

#### アクションフック

- `admin_menu` - 設定ページ追加
- `admin_init` - 設定登録
- `add_meta_boxes` - メタボックス追加
- `save_post` - メタデータ保存
- `wp_enqueue_scripts` - フロントエンドでスタイル読み込み
- `admin_enqueue_scripts` - 管理画面でjQuery UI Sortableエンキュー（v1.1.0）
- `wp_ajax_sn_cps_upload_file` - ファイルアップロードAJAXハンドラー（v1.1.0）
- `wp_ajax_sn_cps_remove_file` - ファイル削除AJAXハンドラー（v1.1.0）

### 定数

プラグイン内で使用される主要な定数:

```php
SN_CPS_Manager::VERSION                    // プラグインバージョン
SN_CPS_Manager::SN_CPS_META_KEY_CSS              // CSSメタキー
SN_CPS_Manager::SN_CPS_META_KEY_SELECTED         // 選択スタイルシートメタキー
SN_CPS_Manager::SN_CPS_META_KEY_UPLOADED         // アップロードファイルメタキー (v1.1.0)
SN_CPS_Manager::SN_CPS_OPTION_ENABLED_POST_TYPES // 有効投稿タイプオプション
SN_CPS_Manager::SN_CPS_CSS_DIR_NAME              // CSSディレクトリ名
```

## アンインストール

プラグインを削除する場合:

1. WordPressダッシュボードからプラグインを無効化
2. 「削除」をクリック

プラグインは `uninstall.php` を使用して、以下を**自動的にクリーンアップ**します:
- すべてのカスタムフィールド（`_sn_cps_css`, `_sn_cps_selected`, `_sn_cps_uploaded_files`）
- プラグインオプション（`sn_cps_enabled_post_types`）
- `wp-content/uploads/sn-cps-styles/` ディレクトリとその中のすべてのファイル

**注意**: アンインストール後、すべてのカスタムCSSデータとアップロードファイルが完全に削除されます。重要なファイルは事前にバックアップしてください。

## 質問・問題報告

このプラグインに関する質問や問題がある場合:

- [GitHubのIssuesページ](https://github.com/monsieurNoiR/custom-page-styles-plugin)で報告
- [WordPress.orgサポートフォーラム](https://wordpress.org/support/plugin/studio-noir-page-styles/)で質問

## ライセンス

GPL v2 or later

## 変更履歴

### 2.0.1 (2026-03-20)
- **バグ修正: Save to Library / Sync to Library がtextareaの最新CSS値を使用するよう修正**
  - 従来はDBに保存済みの値を参照していたため、投稿を保存する前にボタンを押すと空のCSSが登録されていた
  - AJAXリクエストにtextareaの現在値を含め、サーバー側でサニタイズ・使用するよう変更
  - Save to Library / Sync to Library 実行時にポスト自身のCSSも同時に保存・CSS ファイルを生成するよう修正

### 2.0.0 (2026-03-12)
- **新機能: Style Library（スタイルライブラリ）**
  - `sn_cps_style` カスタム投稿タイプを導入
  - CSSを「ページ」ではなく「独立エンティティ」として管理
  - ページ削除時でもスタイルが保持される
- **新機能: Save to Library / Sync to Library**
  - ページのCSSをLibraryに名前付きで登録
  - 既存Libraryエントリの上書き更新または新規保存
- **新機能: v1.x → v2.0 自動マイグレーション**
  - 有効化時に既存データを自動変換
  - 失敗時はリトライ/Dismiss UIで管理
- **新機能: ゴミ箱移動時の未保存CSS警告**
- **改善: uninstall.phpを新データ構造に対応（CPT・メタキー・オプション削除）**

### 1.1.1 (2026-02-08)
- **セキュリティ強化: パストラバーサル対策**
  - `ajax_remove_file()` に `realpath()` による厳格なパス検証を追加
  - 削除対象ファイルが投稿ディレクトリ内に存在することを確認
  - パストラバーサル攻撃による意図しないファイル削除を防止

- **セキュリティ強化: MIMEタイプ検証**
  - `ajax_upload_file()` に `finfo_file()` によるMIMEタイプ検証を追加
  - 拡張子偽装されたファイルのアップロードを防止
  - 許可MIMEタイプ: text/css, text/javascript, application/javascript

- **機能追加: ファイル名衝突対応**
  - 同名ファイルアップロード時に自動リネーム機能を実装
  - ファイル名に連番を付与（例: style.css → style-1.css → style-2.css）
  - 最大100個まで自動リネーム対応

### 1.1.0 (2026-02-07)
- **新機能: 無制限スタイル選択**
  - 以前は最大2つまでだった制限を撤廃
  - ドラッグ&ドロップで読み込み順序を変更可能
  - ACF風のソート可能UIを実装
  - jQuery UI Sortableを使用

- **新機能: ファイルアップロード**
  - CSS/JavaScriptファイルのアップロード対応
  - 投稿ごとに専用ディレクトリ管理（`/sn-cps-styles/{post_id}/`）
  - 元のファイル名を保持
  - ファイルタイプ検証（CSS/JSのみ）
  - ファイルサイズ制限（5MB）
  - JavaScript読み込み位置選択（header/footer）
  - AJAXによるファイル追加・削除

- **改善: CSS読み込み順序の最適化**
  - 選択スタイル → アップロードファイル → 直接記述CSS の順で読み込み
  - より柔軟なカスタマイズが可能に

- **セキュリティ強化**
  - ファイルアップロードのセキュリティ検証
  - XSS対策の強化（JavaScriptでのDOM操作）
  - ファイル名サニタイゼーション

### 1.0.2 (2024-12-18)
- **スタイル読み込み優先度の最適化**
  - `wp_enqueue_scripts` フックの優先度を20に設定
  - テーマのメインCSSより後に読み込むことで、カスタムCSSでテーマスタイルを確実に上書き可能に

### 1.0.1 (2024-12-18)
- **セキュリティ強化パッチ**
  - CSSサニタイゼーションの大幅強化（WP_Error対応）
  - 追加の危険パターン検出（data:text/html, vbscript:, イベントハンドラー等）
  - ファイルサイズ制限追加（1MB）
  - エラーハンドリングの改善（Transient API使用でユーザーフィードバック向上）
  - ファイルパス検証の強化（パストラバーサル攻撃対策の改善）

### 1.0.0 (2024-12-18)
- 初回リリース
- カスタムCSS記述機能
- 既存スタイルシート選択機能
- 投稿タイプ選択機能
- セキュリティ機能実装

## 開発をサポート

このプラグインが役立った場合は、開発をサポートしていただけると嬉しいです:

☕ **[Ko-fiでコーヒーを奢る](https://ko-fi.com/studio_noir)**

あなたのサポートが、無料のオープンソースWordPressプラグインの開発を継続する原動力になります!

## クレジット

開発: Masaki (studioNoiR) with Claude Code
