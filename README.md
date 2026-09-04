# 最新型家計簿！「レスマネ」

**AI × SNS型で支出の後悔を減らす家計簿アプリ**

## タイトルの由来

- **レス** → SNS における「Response」
- **マネ** → お金の「マネー」と管理の「マネジメント」

## 企画の動機

- 「給料日前にお金を使い込んでしまう」
- 「趣味や娯楽でどうしても浪費してしまう」

従来の家計簿は、お金の使い方を確認するのみの機能であることが多く、「今月使いすぎたな…」などと考えはするものの、行動変容につながりにくいと考えた。

### エビデンス

- [ASMARQの「衝動買いに関するアンケート調査」](https://www.asmarq.co.jp/data/mr201202_1shopping/)によると、衝動買いを「よくする」「たまにする」人の割合は、全体の800人中 **70.2%** であった。一般的にも衝動買いをする人が多い傾向にあると推定できる
- [博報堂生活総研「生活定点」調査](https://seikatsusoken.jp/teiten/)において「褒めると成長する」が **90.3%** と、人は褒められると成長やモチベーションアップ、維持につながることがわかった。衝動買いを減らすには行動変容が必要であり、この「褒めると成長する」ことがエビデンスとして利用できると考えた

### 課題放置のリスク

- 本来買いたいものが買えないことが起こる
- 一人暮らしにおいて生活費が不足し、緊急時に資金が活用できないなど、将来的に自身の首を絞める事態を招く

## ターゲット

お金の管理が苦手な20代前後の学生

## キーワード

- **家計簿** — お金の管理を可視化する
- **自分自身で評価** — 購入したものに対する評価を自分自身で客観的に分析する
- **AI × SNS型** — 自分自身で評価したものをさらにAIが褒めたりツッコミを入れる形でさらに評価（フィードバック）し、スレッド形式でまとめる

## コンセプト

日々の収入支出の管理に加え、購入したものに対して自分自身で評価を行う。

支出の管理を自身による「一人反省会」で終わらせず、その評価に対してAIがSNSの返信のように反応し、良い買い物をしたり節約の努力を褒めたり、逆に無駄遣いや浪費などは改善点をフィードバックしてコミュニケーションを取る形で提示することで、ユーザーがエンタメ感覚で衝動買いの抑止や自身のお金の管理の見直しを図ることを目標とする。

### フィードバックの基準

- 使いすぎ自体は否定しない
- 原則優しく、なるべく褒める
- 無駄遣いには改善点を提示するが、責めない

## 技術スタック

| 層 | 技術 |
|----|------|
| フロントエンド | React 18 + TypeScript 5 + Tailwind CSS 3 (Vite 8) |
| バックエンド | PHP 8.5 + Laravel 13 (API サーバー) |
| AI / バックグラウンド | Python 3.11 + schedule (常駐 worker) |
| データベース | MySQL 8.4 |
| リバースプロキシ | Nginx |
| 実行環境 | Docker Compose |

## ディレクトリ構成

```
resmane/
├── web/
│   ├── frontend/    React SPA (Vite)
│   └── backend/     Laravel API
├── worker/          Python バックグラウンド worker
├── docker/          Dockerfile / Nginx 設定
├── docs/            要件定義・技術構成書・コーディング規約
├── compose.example.yml
└── .env.example
```

## 前提条件

- WSL2 (Ubuntu 26.04)
- Docker CE + docker-compose-plugin
- Git

## 環境構築

```bash
# 1. リポジトリをクローン
git clone git@github.com:space-musicacct/resmane.git
cd resmane

# 2. テンプレートをコピー
cp compose.example.yml compose.yml
cp .env.example .env
cp web/backend/.env.example web/backend/.env
cp docker/db/init.example.sql docker/db/init.sql

# 3. コンテナをビルド・起動
docker compose up -d --build

# 4. Laravel 初期設定 (初回のみ)
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
```

## アクセス

| URL | 内容 |
|-----|------|
| http://localhost:40080 | React (フロントエンド) |
| http://localhost:40080/api | Laravel API |
| localhost:43306 | MySQL (DB クライアントから接続) |
| http://localhost:48080 | phpMyAdmin |

## ダミーデータ

`php artisan db:seed` で以下のダミーデータが投入される。

### ダミーユーザー

| ログインID | 名前 | メールアドレス | パスワード |
|---|---|---|---|
| `taro_yamada` | 山田太郎 | taro@example.com | `hogehoge` |
| `hanako_sato` | 佐藤花子 | hanako@example.com | `hogehoge` |
| `yuki_tanaka` | 田中ゆき | yuki@example.com | `hogehoge` |

### ダミーデータ内容（全ユーザー共通）

- 家計簿レコード: 先月分 + 今月分（食費・交通費・推し活・サブスク・固定費・収入等）
- 上限値設定: 固定額 80,000円/月
- 自己レビュー: 12件（★2〜5の評価分布）

---

## 起動方法

起動は原則 `--build` 付きで行う。`--build` によりイメージが再ビルドされ、コンテナが再作成されるため、起動時に実行される `npm ci` で `package-lock.json` の状態に依存が同期される。

```bash
docker compose up -d --build
```

パッケージの追加・更新後にコンテナが再作成されない場合は、明示的に再作成する。

```bash
docker compose up -d --build --force-recreate frontend
```

---

## フロントエンドでの画像の使い方

### `public/` に置く場合（そのまま配信）

`web/frontend/public/` に置いたファイルはパスを変えずにそのまま配信される。favicon や OGP 画像など、ビルドに巻き込みたくないファイル向け。

```
web/frontend/public/images/logo.png
```

```tsx
// JSX で参照（パスは / から始める）
<img src="/images/logo.png" alt="ロゴ" />
```

### `src/assets/` に置く場合（import して使う）

`web/frontend/src/assets/` に置いたファイルは `import` で読み込む。Vite がハッシュ付きファイル名に変換してバンドルするため、キャッシュ破棄が自動で効く。コンポーネントから使う画像はこちら。

```
web/frontend/src/assets/images/icon.png
```

```tsx
import icon from '../assets/images/icon.png'

<img src={icon} alt="アイコン" />
```

---

## よく使うコマンド

```bash
# 起動（原則こちらを使う）
docker compose up -d --build

# 停止
docker compose down

# コンテナ状態確認
docker compose ps

# ログ確認
docker compose logs -f

# ベースイメージを最新に更新 (セキュリティパッチ適用)
docker compose build --pull --no-cache

# フロントエンドに npm パッケージを追加
docker compose exec frontend npm install <package>

# バックエンドで artisan コマンド実行
docker compose exec backend php artisan <command>

# IDE Helper (PhpStorm / VS Code 補完用ファイル生成)
docker compose exec backend php artisan ide-helper:generate
docker compose exec backend php artisan ide-helper:models -N
docker compose exec backend php artisan ide-helper:eloquent
```

## 既存環境の更新手順

### テスト用データベースの追加 (2026-09-04)

テスト実行時に本番 DB を破壊しないよう、テスト専用の `resmane_test` データベースを使用するようになりました。

新規構築の場合は `init.example.sql` に含まれているため追加作業は不要です。既存の `db_data` ボリュームを持つ環境では、以下を一度だけ実行してください。

```bash
docker compose exec db mysql -u root -proot_password -e "
CREATE DATABASE IF NOT EXISTS resmane_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON resmane_test.* TO 'resmane_user'@'%';
FLUSH PRIVILEGES;
"
```

### デフォルトポート変更: 5万台 → 4万台 (2026-09-04)

ポート競合のリスクを軽減するため、デフォルトポートを変更しました。

| 用途 | 旧ポート | 新ポート |
|---|---|---|
| Nginx (Web) | 50080 | 40080 |
| MySQL | 53306 | 43306 |
| phpMyAdmin | 58080 | 48080 |

既存環境は `.env` と `web/backend/.env` のポート番号を更新してください。

```bash
# .env
NGINX_PORT=40080
DB_EXTERNAL_PORT=43306

# web/backend/.env
APP_URL=http://localhost:40080
SANCTUM_STATEFUL_DOMAINS=localhost:40080
```

### Node.js 20 → 24 (2026-06-07)

Node 20 が EOL のため Node 24 LTS に変更しました。構築済みの環境は以下で更新してください。

```bash
docker compose down
docker compose build --pull --no-cache frontend
docker compose up -d
```

### Laravel 10 → 13 / PHP 8.3 → 8.5 (2026-07-10)

Laravel 10（セキュリティサポート終了済み）から Laravel 13 へ、PHP 8.3 から 8.5 へアップグレードしました。構築済みの環境は以下の手順で更新してください。

#### 1. ブランチを取得してコンテナを再ビルド

```bash
git pull
docker compose down
docker compose build --pull --no-cache backend
docker compose up -d
```

#### 2. Composer の依存関係を更新

```bash
docker compose exec backend composer install
```

#### 3. `web/backend/.env` の環境変数名を修正

以下の変数名が変更されています。`web/backend/.env` を手動で修正してください。

| 旧 (Laravel 10) | 新 (Laravel 13) |
|---|---|
| `BROADCAST_DRIVER` | `BROADCAST_CONNECTION` |
| `CACHE_DRIVER` | `CACHE_STORE` |

値はそのままで、キー名だけ変更すれば OK です。

また、以下の変数は不要になったため削除して構いません（残っていても動作に影響はありません）。

- `LOG_DEPRECATIONS_CHANNEL`
- `MEMCACHED_HOST`
- `REDIS_*` (Redis を使っていない場合)
- `AWS_*` (AWS を使っていない場合)
- `PUSHER_*` / `VITE_PUSHER_*` (Pusher を使っていない場合)

#### 4. 動作確認

```bash
docker compose exec backend php -v
# → PHP 8.5.x と表示されれば OK

docker compose exec backend php artisan --version
# → Laravel Framework 13.x.x と表示されれば OK
```

---

## トラブルシューティング

### `localhost:40080` にアクセスできない (WSL2)

Nginx コンテナが `address already in use` で起動に失敗する場合、Windows の Hyper-V がポートを予約している可能性がある。

```powershell
# Windows PowerShell で予約ポートを確認
netsh interface ipv4 show excludedportrange protocol=tcp
```

40080 を含む範囲が表示された場合、`.env` でポート番号を変更する。

```dotenv
NGINX_PORT=30080
```

`web/backend/.env` の `APP_URL` と `SANCTUM_STATEFUL_DOMAINS` も合わせて変更する。

```dotenv
APP_URL=http://localhost:30080
SANCTUM_STATEFUL_DOMAINS=localhost:30080
```

```bash
docker compose up -d --build
```

もし、`web/backend/.env` の **`APP_KEY` が空の場合は**、以下も併せて実行する。

```bash
docker compose exec backend php artisan key:generate
```

---

## `docker compose` と入力するのがだるいよ〜という方へ

`~/.bashrc` に以下のエイリアスを追加すると楽になります。

```bash
alias dc='docker compose'
alias dce='docker compose exec'
```

### 使用例

```bash
dc up -d --build
dc down
dce backend php artisan route:list --path=records
```

---

## ライセンス

[MIT License](LICENSE)

## 関連ドキュメント

- [要件定義書](docs/要件定義/要件定義書.md)
- [技術構成書](docs/技術構成/技術構成書.md)
- [コーディング規約](docs/開発ルール/コーディング規約.md)
- [依存関係更新ルール](docs/開発ルール/依存関係更新ルール.md)
