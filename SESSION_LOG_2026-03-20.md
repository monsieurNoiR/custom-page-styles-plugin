# Studio Noir Custom Page Styles - 作業ログ
## 2026-03-20

### v2.0.1 バグ修正リリース

#### 1. 不具合の調査

実機インストールで発覚した以下の不具合を調査：

- 「Custom CSS for this page:」欄への入力が反映されない
- Style Library の「CSS for this style:」欄が空白になる
- 「Sync to Library → 上書き」を実行すると既存の Library CSS が全て消える

**根本原因：**
Save to Library / Sync to Library の AJAX コールが、textarea の現在値ではなく DB に保存済みの値（`get_post_meta()`）を読んでいた。投稿を Update する前にボタンを押すと、DB には古い/空のCSS が残っており、それが Library エントリに書き込まれる。

#### 2. コード修正 (custom-page-styles.php)

| 修正箇所 | 内容 |
|---------|------|
| JS: Save to Library AJAX | `css: $('#sn_cps_css').val()` を data に追加 |
| JS: Sync to Library AJAX | 同上 |
| `ajax_save_to_library()` | `$_POST['css']` を受け取りサニタイズ、ポスト本体と Library エントリ両方に保存 |
| `ajax_sync_to_library()` | 同上。ポスト自身のCSSも同時に保存・CSSファイル生成 |
| バージョン定数・ヘッダー | `2.0.0` → `2.0.1` |

#### 3. ドキュメント更新

| ファイル | 内容 |
|---------|------|
| `readme.txt` | Stable tag を 2.0.1 に変更、2.0.1 Changelog エントリ追加 |
| `README.md` | 2.0.1 変更履歴追記、ファイル構成修正、v2.0メタキー追記、v2.0フック追記 |
| `CLAUDE.md` | 現在の状態セクションを更新 |

#### 4. コミット履歴

| コミット | 内容 |
|---------|------|
| `8e0d978` | Fix: Save/Sync to Library now uses current textarea CSS |
| `00e57f1` | v2.0.1: version bump + doc updates |
| `7e8905a` | docs(README): fix file structure, add v2.0 meta keys and AJAX hooks |

#### 5. リリース作業

| 作業 | 結果 |
|-----|------|
| GitHub push | `main` ブランチ、最新コミット `7e8905a` |
| 配布 ZIP 更新 | `studio-noir-page-styles.zip`（v2.0.1内容） |
| WordPress.org SVN trunk | `r3487046` |
| WordPress.org SVN tags/2.0.1 | `r3487048` |

### 使用モデル

Claude Sonnet 4.6 (claude-sonnet-4-6)
