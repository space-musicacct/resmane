# 最新型家計簿！「レスマネ」　APIテスト仕様書

本書は[テスト仕様書](テスト仕様書.md)の一部であり、.http ファイルによるAPIテスト（`tests/Http/`）の仕様を定義する。

Docker 上の実環境で各エンドポイントを手動確認する。JetBrains IDE（PhpStorm 等）または ijhttp CLI で実行する。

---

## 1. ファイル構成

```
tests/Http/
├── scenario1.http                  シナリオ1: 基本フロー
├── scenario2.http                  シナリオ2: エラーパターン確認
├── scenario3.http                  シナリオ3: ユーザー情報・退会
└── http-client.env.example.json    環境変数テンプレート
```

### 1.1 環境設定

`http-client.env.example.json` を `http-client.env.json` にコピーし、`host` をローカル環境のURLに設定する。

```bash
cp tests/Http/http-client.env.example.json tests/Http/http-client.env.json
```

### 1.2 CSRF トークンの扱い

Sanctum SPA 認証のため、各 POST / PUT / DELETE リクエストの前に CSRF Cookie を取得し、`X-XSRF-TOKEN` ヘッダーで送信する必要がある。各シナリオファイルにはこの手順が組み込まれている。

### 1.3 実行方法

#### JetBrains IDE

シナリオファイルを開き、「Run All Requests in File」で全件実行する。

#### ijhttp CLI

```bash
# DB を初期化してからシナリオを実行する
docker compose exec backend php artisan migrate:refresh --seed

# シナリオ1
./ijhttp-cli/ijhttp/ijhttp scenario1.http -v http-client.env.json -e dev

# シナリオ2
docker compose exec backend php artisan migrate:refresh --seed
./ijhttp-cli/ijhttp/ijhttp scenario2.http -v http-client.env.json -e dev

# シナリオ3
docker compose exec backend php artisan migrate:refresh --seed
./ijhttp-cli/ijhttp/ijhttp scenario3.http -v http-client.env.json -e dev
```

各シナリオは DB の状態に依存するため、実行前に `migrate:refresh --seed` でデータを初期化する。

---

## 2. テストシナリオ

### シナリオ1: 基本フロー

| 順序 | リクエスト | 確認内容 |
|------|-----------|---------|
| 1 | GET /sanctum/csrf-cookie | CSRF Cookie 取得 |
| 2 | POST /api/v1/register | ユーザー登録 → 201 |
| 3 | GET /api/v1/amountTypes | 収支区分一覧 → 200 |
| 4 | GET /api/v1/categories?amountTypeId=1 | 支出カテゴリ一覧 → 200 |
| 5 | POST /api/v1/records | 家計簿登録 → 201 |
| 6 | GET /api/v1/records | 家計簿一覧 → 200, 登録したレコードが含まれる |
| 7 | GET /api/v1/records/{id} | 家計簿詳細 → 200 |
| 8 | PUT /api/v1/records/{id} | 家計簿編集 → 200 |
| 9 | POST /api/v1/records/{id}/reviews | 自己レビュー投稿（reviewComment + evaluation）→ 201, evaluation が含まれる |
| 10 | GET /api/v1/records/{id}/reviews | 自己レビュー一覧 → 200, 各レビューに evaluation が含まれる |
| 11 | POST /api/v1/records/{id}/posts | AIフィードバック要求 → 201 |
| 12 | GET /api/v1/records/{id}/posts | スレッド一覧 → 200, pending の AI投稿が含まれる |
| 13 | PUT /api/v1/settings/limit | 基準値設定 → 200 |
| 14 | GET /api/v1/settings/limit | 基準値取得 → 200 |
| 15 | POST /api/v1/logout | ログアウト → 204 |

### シナリオ2: エラーパターン確認

| 順序 | リクエスト | 確認内容 |
|------|-----------|---------|
| 1 | GET /api/v1/records（未認証） | 401 |
| 2 | POST /api/v1/login（認証情報不正） | 401 |
| 3 | POST /api/v1/login（成功） | 200 |
| 4 | POST /api/v1/records（バリデーションエラー） | 422, フィールド別エラー |
| 5 | GET /api/v1/records/999 | 404 |
| 6 | DELETE /api/v1/records/{id}/reviews/{id} | 204, 画面遷移なし確認用 |
| 7 | DELETE /api/v1/records/{id} | 204, 紐づくデータも論理削除 |
| 8 | POST /api/v1/records/{id}/posts（content省略, 2回目） | 404, 削除済みレコード |
| 9 | POST /api/v1/records/{id}/posts（別レコード, 1回目） | 201 |
| 10 | POST /api/v1/records/{id}/posts（同レコード, 2回目） | 409, 重複拒否 |

### シナリオ3: ユーザー情報・退会

| 順序 | リクエスト | 確認内容 |
|------|-----------|---------|
| 1 | POST /api/v1/login | 200 |
| 2 | GET /api/v1/user | 200, ユーザー情報 |
| 3 | PUT /api/v1/user（loginId変更） | 200 |
| 4 | PUT /api/v1/user（パスワード変更） | 200 |
| 5 | POST /api/v1/login（変更後のloginId・パスワードで再ログイン） | 200 |
| 6 | DELETE /api/v1/user（パスワード不一致） | 422 |
| 7 | DELETE /api/v1/user（正しいパスワード） | 204 |
| 8 | GET /api/v1/user（退会後） | 401 |
