# WordPress Plugin Development Rules

このプロジェクトはWordPress.orgで公開するプラグインです。
以下のルールを厳守してコードを生成してください。

## 重要: 作業開始前に読むファイル

**セッション開始時に必ず以下のファイルを確認すること:**

1. `.claude/workflow.md` - ワークフロールール（Plan Mode、検証、タスク管理）
2. `tasks/lessons.md` - 過去の教訓（存在する場合）
3. `SESSION_LOG_*.md` - 直近の作業ログ（継続作業の場合）

## Security & Sanitization Rules (必須)

### 1. Nonce Verification
Nonce検証には必ず `sanitize_text_field()` を追加:
```php
// ❌ NG
wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'action' )

// ✅ OK
wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' )
```

### 2. Output Escaping
全ての出力に適切なエスケープ関数を使用:
```php
// ❌ NG
echo $variable;

// ✅ OK - コンテキストに応じて選択
echo esc_html( $variable );      // HTML要素内
echo esc_attr( $variable );      // HTML属性内
echo esc_url( $variable );       // URL
echo esc_js( $variable );        // JavaScript内
echo esc_textarea( $variable );  // textarea内
```

### 3. Database Queries
全てのDBクエリで `$wpdb->prepare()` を使用:
```php
// ❌ NG
$wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE ID = {$id}" );

// ✅ OK
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
    $id
) );

// プレースホルダー
%d  // 整数
%f  // 浮動小数点
%s  // 文字列
```

### 4. Input Validation & Sanitization
全ての入力値を検証・サニタイゼーション:
```php
// ❌ NG
$value = $_POST['field'];

// ✅ OK
$value = isset( $_POST['field'] )
    ? sanitize_text_field( wp_unslash( $_POST['field'] ) )
    : '';

// サニタイゼーション関数
sanitize_text_field()     // 一般的なテキスト
sanitize_textarea_field() // テキストエリア
sanitize_email()          // メールアドレス
sanitize_url()            // URL
sanitize_key()            // キー（英数字とアンダースコア）
absint()                  // 正の整数
```

## Coding Standards

### Prefix Usage
衝突を避けるため、全ての関数・クラス・定数・オプション・メタキーに固有のPrefixを使用:
```
このプロジェクトのPrefix:
- 関数・変数: sn_cps_
- クラス・定数: SN_CPS_

例:
function sn_cps_init() { ... }
class SN_CPS_Manager { ... }
const SN_CPS_VERSION = '1.0.0';
update_option( 'sn_cps_enabled', ... );
```

### Text Domain
翻訳関数で使用するText Domainを統一:
```
このプロジェクトのText Domain: studio-noir-page-styles

全ての __(), _e(), esc_html__() 等で使用
```

## WordPress API Preference

可能な限りWordPress APIを優先使用:
```php
// ✅ 推奨
$posts = get_posts( array( 'post_type' => 'page' ) );
$query = new WP_Query( $args );

// ⚠️ 最終手段
$wpdb->get_results( ... );  // カスタムテーブル等、やむを得ない場合のみ
```

## File Structure
```
custom-page-styles-plugin/
├── custom-page-styles.php  // メインファイル
├── uninstall.php          // アンインストール処理
├── readme.txt             // WordPress.org用README
├── README.md              // GitHub用README
└── languages/             // 翻訳ファイル
    └── index.php
```

## WordPress.org Review Checklist

コード生成時に常に確認:

- [ ] 全てのNonce検証に `sanitize_text_field()` 使用
- [ ] 全ての出力に適切なエスケープ関数使用
- [ ] 全てのDBクエリで `$wpdb->prepare()` 使用
- [ ] 全ての入力値にサニタイゼーション実装
- [ ] Prefixが全箇所で統一（sn_cps_ / SN_CPS_）
- [ ] Text Domainが全箇所で統一（studio-noir-page-styles）
- [ ] 直接DBクエリは正当な理由がある場合のみ
- [ ] WordPress APIを優先使用

## Common Mistakes to Avoid

### 1. Nonce検証でのサニタイゼーション漏れ
```php
// ❌ これが最も多い間違い
wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'action' )

// ✅ 必ず sanitize_text_field() を追加
wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'action' )
```

### 2. 出力時のエスケープ漏れ
```php
// ❌ 変数をそのまま出力
echo $variable;

// ✅ 必ずエスケープ
echo esc_html( $variable );
```

### 3. 一般的な単語をPrefixに使用
```php
// ❌ "custom" は一般的すぎる
function custom_init() { ... }

// ✅ 固有のPrefixを使用
function sn_cps_init() { ... }
```

## Version Management

- プラグインヘッダーとreadme.txtでバージョンを統一
- GitHubタグとWordPress.orgバージョンを一致させる
- セマンティックバージョニング使用（x.y.z）

## Documentation & Date Handling

### 日付の取り扱い（重要）

**変更履歴やドキュメントに日付を記載する際は、必ずbashコマンドで現在日付を取得すること。**

手動で年を書くと間違いやすい（2025年/2026年の混同を防ぐため）。

### 使用例：
```bash
# 現在の日付を取得
CURRENT_DATE=$(date +%Y-%m-%d)

# README.mdに追記する例
echo "### v1.x.x ($CURRENT_DATE)" >> README.md

# 年だけ取得する場合
CURRENT_YEAR=$(date +%Y)
echo "### v1.x.x ($CURRENT_YEAR-XX-XX)" >> SECURITY.md
```

### 適用対象：
- README.md の変更履歴セクション
- SECURITY.md の更新履歴
- readme.txt の Changelog セクション
- その他日付が必要なドキュメント

### ❌ NG - 手動で年を記載
```bash
echo "### v1.1.1 (2025-02-08)" >> README.md  # 年が間違っている
```

### ✅ OK - bashコマンドで自動取得
```bash
CURRENT_DATE=$(date +%Y-%m-%d)
echo "### v1.1.1 ($CURRENT_DATE)" >> README.md
```

## README.md の二重管理ルール

このリポジトリには README.md が2箇所に存在する（二重管理）:

| ファイル | 用途 |
|---------|------|
| `/README.md` | GitHubリポジトリトップページに表示される |
| `/studio-noir-page-styles/README.md` | プラグイン本体に同梱・SVNにも存在 |

**ルール: どちらか一方を編集したら、もう一方も必ず同じ内容に更新してからコミット・pushすること。**

## Notes

このルールはWordPress.orgのレビューで実際に指摘された内容に基づいています。
新しいコードを生成する際は、必ずこれらのルールを適用してください。

## 現在の状態

**最新バージョン:** v2.0.1（実装完了・GitHub push済み）

**リリース状況:**
- GitHub: `main` ブランチ（v2.0.1 バグ修正済み）
- WordPress.org SVN: v2.0.0 = `r3482556`（v2.0.1 はまだ未反映）

**直近の作業 (2026-03-20):**
- バグ修正: Save to Library / Sync to Library がtextareaの最新CSS値を使用するよう修正
  - AJAXリクエストに `css: $('#sn_cps_css').val()` を追加
  - `ajax_save_to_library()` / `ajax_sync_to_library()` でPOSTのcss値を優先使用
  - 同時にポスト自身のCSSもDBとファイルに保存するよう修正
- バージョンを v2.0.1 に更新（custom-page-styles.php ヘッダー・定数・readme.txt・README.md）
- 配布用 studio-noir-page-styles.zip を v2.0.1 内容で更新

**直近の作業 (2026-04-09):**
- ルートに `README.md` を追加してGitHub push（リポジトリトップに表示されるよう対応）
- `studio-noir-page-styles/README.md` との二重管理ルールを `CLAUDE.md` に追記

**次のアクション:**
- なし
