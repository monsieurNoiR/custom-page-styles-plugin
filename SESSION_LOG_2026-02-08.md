# Studio Noir Custom Page Styles - 作業ログ
## 2026-02-08

### v1.1.1 セキュリティ修正リリース

#### 1. コード修正 (custom-page-styles.php)

| 優先度 | 修正内容 | 行番号 |
|--------|----------|--------|
| HIGH | ajax_remove_file() パストラバーサル対策 | 1297-1304 |
| MEDIUM | ajax_upload_file() MIMEタイプ検証 | 1192-1207 |
| MEDIUM | ファイル名衝突時の自動リネーム | 1227-1242 |

**バージョン更新:** 1.1.0 → 1.1.1

#### 2. ドキュメント更新

- **readme.txt**: Stable tag 1.1.1、v1.1.1 changelog追加、Tags修正（5個制限対応）
- **README.md**: v1.1.1 changelog追加
- **SECURITY.md**: v1.1.1セキュリティ強化の詳細追加

#### 3. 配布ファイル

- `custom-page-styles.zip` (24KB) 作成済み

#### 4. リリース

| プラットフォーム | 状態 | リビジョン/コミット |
|-----------------|------|-------------------|
| GitHub | ✅ | タグ v1.1.1、コミット ab6903c |
| WordPress.org SVN | ✅ | r3456178 (trunk + tags/1.1.1) |

#### 5. スクリーンショット更新 (SVN assets)

| ファイル | 内容 |
|---------|------|
| screenshot-1.jpg | メタボックス（ファイルアップロードUI） |
| screenshot-2.jpg | ドラッグ&ドロップUI |
| screenshot-3.jpg | 設定ページ |

- 古い screenshot-1.png を削除
- SVN r3456057 でコミット

#### 6. Tags修正

```
Before: css, custom css, page styles, post styles, reusable css, file upload (6個)
After:  css, custom css, page styles, reusable css, file upload (5個)
```

WordPress.org の5タグ制限に準拠。

---

### コミット履歴 (Git)

```
ab6903c Fix tags: Remove 'post styles' to comply with 5 tag limit
22ba479 Update documentation for v1.1.1
8146e38 Update SECURITY.md for v1.1.0
ffb1233 Update documentation for v1.1.0
7892890 v1.1.0: Add file upload feature
```

### 使用モデル

Claude Opus 4.5 (claude-opus-4-5-20251101)
