# Studio Noir Custom Page Styles - 作業ログ
## 2026-04-09

### README.md のGitHub表示対応

#### 経緯

`studio-noir-page-styles/README.md` はgit管理・SVN管理されていたが、リポジトリルートに README.md がなかったため、GitHubのリポジトリトップページに何も表示されない状態だった。

#### 調査・判断

- WordPress.org は `readme.txt` しか参照しないため、`studio-noir-page-styles/README.md` はSVNに存在しても実質意味がない
- ただしSVN側に古いファイルが残るため削除は困難
- 結論: **二重管理を受け入れる**（ルートとサブディレクトリの両方を常に同じ内容に保つ）

#### 作業内容

| 作業 | 内容 |
|-----|------|
| `README.md` 追加 | `studio-noir-page-styles/README.md` をリポジトリルートにコピー |
| GitHub push | `49ae32e` |
| `CLAUDE.md` 更新 | 二重管理ルールを「README.md の二重管理ルール」セクションとして追記 |

#### 二重管理ルール（次回以降の注意）

README.md を編集する際は必ず両方を更新すること:

1. `/README.md`
2. `/studio-noir-page-styles/README.md`

### 使用モデル

Claude Sonnet 4.6 (claude-sonnet-4-6)
