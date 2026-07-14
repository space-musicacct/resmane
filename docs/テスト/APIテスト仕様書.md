# 最新型家計簿！「レスマネ」　APIテスト仕様書

本書は[テスト仕様書](テスト仕様書.md)の一部であり、.http ファイルによるAPIテスト（`tests/Http/`）の仕様を定義する。

Docker 上の実環境（`localhost:50080` 等）で各エンドポイントを手動確認する。JetBrains IDE（PhpStorm 等）または VS Code の REST Client 拡張で実行する。

---

## 1. ファイル構成

```
tests/Http/
├── auth.http          認証（登録・ログイン・ログアウト）
├── records.http       家計簿レコード（CRUD）
├── reviews.http       自己レビュー（CRUD）
├── posts.http         スレッド・AIメッセージ
├── settings.http      基準値設定
├── user.http          ユーザー情報（編集・退会）
└── master.http        マスタデータ（カテゴリ・収支区分）
```

---

## 2. テストシナリオ

.http ファイルは以下の順序で実行することで、一連の操作フローを手動確認できる。

### シナリオ1: 基本フロー

| 順序 | ファイル | リクエスト | 確認内容 |
|------|---------|-----------|---------|
| 1 | auth.http | GET /sanctum/csrf-cookie | CSRF Cookie 取得 |
| 2 | auth.http | POST /api/v1/register | ユーザー登録 → 201 |
| 3 | master.http | GET /api/v1/amountTypes | 収支区分一覧 → 200 |
| 4 | master.http | GET /api/v1/categories?amountTypeId=1 | 支出カテゴリ一覧 → 200 |
| 5 | records.http | POST /api/v1/records | 家計簿登録 → 201 |
| 6 | records.http | GET /api/v1/records | 家計簿一覧 → 200, 登録したレコードが含まれる |
| 7 | records.http | GET /api/v1/records/{id} | 家計簿詳細 → 200 |
| 8 | records.http | PUT /api/v1/records/{id} | 家計簿編集 → 200 |
| 9 | reviews.http | POST /api/v1/records/{id}/reviews | 自己レビュー投稿（reviewComment + evaluation）→ 201, evaluation が含まれる |
| 10 | reviews.http | GET /api/v1/records/{id}/reviews | 自己レビュー一覧 → 200, 各レビューに evaluation が含まれる |
| 11 | posts.http | POST /api/v1/records/{id}/posts | AIフィードバック要求 → 201 |
| 12 | posts.http | GET /api/v1/records/{id}/posts | スレッド一覧 → 200, pending の AI投稿が含まれる |
| 13 | settings.http | PUT /api/v1/settings/limit | 基準値設定 → 200 |
| 14 | settings.http | GET /api/v1/settings/limit | 基準値取得 → 200 |
| 15 | auth.http | POST /api/v1/logout | ログアウト → 204 |

### シナリオ2: エラーパターン確認

| 順序 | ファイル | リクエスト | 確認内容 |
|------|---------|-----------|---------|
| 1 | records.http | GET /api/v1/records（未認証） | 401 |
| 2 | auth.http | POST /api/v1/login（認証情報不正） | 401 |
| 3 | auth.http | POST /api/v1/login（成功） | 200 |
| 4 | records.http | POST /api/v1/records（バリデーションエラー） | 422, フィールド別エラー |
| 5 | records.http | GET /api/v1/records/999 | 404 |
| 6 | reviews.http | DELETE /api/v1/records/{id}/reviews/{id} | 204, 画面遷移なし確認用 |
| 7 | records.http | DELETE /api/v1/records/{id} | 204, 紐づくデータも論理削除 |
| 8 | posts.http | POST /api/v1/records/{id}/posts（content省略, 2回目） | 409, 重複拒否 |

### シナリオ3: ユーザー情報・退会

| 順序 | ファイル | リクエスト | 確認内容 |
|------|---------|-----------|---------|
| 1 | auth.http | POST /api/v1/login | 200 |
| 2 | user.http | GET /api/v1/user | 200, ユーザー情報 |
| 3 | user.http | PUT /api/v1/user（loginId変更） | 200 |
| 4 | user.http | PUT /api/v1/user（パスワード変更） | 200 |
| 5 | user.http | DELETE /api/v1/user（パスワード不一致） | 422 |
| 6 | user.http | DELETE /api/v1/user（正しいパスワード） | 204 |
| 7 | user.http | GET /api/v1/user（退会後） | 401 |
