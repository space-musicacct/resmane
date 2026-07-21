# 最新型家計簿！「レスマネ」

学生向け AI 連動家計簿 Web アプリケーション。

## 技術スタック

| 層 | 技術 |
|----|------|
| フロントエンド | React 18 + TypeScript 5 + Tailwind CSS 3 (Vite 5) |
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
| http://localhost:50080 | React (フロントエンド) |
| http://localhost:50080/api | Laravel API |
| localhost:53306 | MySQL (DB クライアントから接続) |
| http://localhost:58080 | phpMyAdmin |

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

```diff
- BROADCAST_DRIVER=log
+ BROADCAST_CONNECTION=log
- CACHE_DRIVER=file
+ CACHE_STORE=file
```

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

#### 主な変更点

- **PHP 8.5**: Docker イメージを `php:8.5-fpm-alpine` に変更。Dockerfile の拡張インストールに `install-php-extensions` を採用
- **アプリケーション構造の刷新**: Laravel 11 で導入されたスリム構造に移行。`app/Http/Kernel.php`・`app/Console/Kernel.php`・`app/Exceptions/Handler.php`・個別 Middleware ファイルは削除され、`bootstrap/app.php` に集約
- **config ファイルの整理**: カスタマイズのない config ファイル（auth, cache, database 等）は削除し、フレームワーク内蔵のデフォルトを使用
- **依存パッケージの更新**: Sanctum 4, Tinker 3, PHPUnit 12, Collision 8 等
- **Carbon 3**: Carbon 2 から 3 に更新（`diffIn*` メソッドが float を返すように変更）

---

## トラブルシューティング

### `localhost:50080` にアクセスできない (WSL2)

Nginx コンテナが `address already in use` で起動に失敗する場合、Windows の Hyper-V がポート 50080 を予約している可能性がある。

```powershell
# Windows PowerShell で予約ポートを確認
netsh interface ipv4 show excludedportrange protocol=tcp
```

50080 を含む範囲が表示された場合、`.env` でポート番号を変更する。

```dotenv
NGINX_PORT=30080
```

`web/backend/.env` の `APP_URL` と `SANCTUM_STATEFUL_DOMAINS` も合わせて変更する。

```dotenv
APP_URL=http://localhost:30080
# ...
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

2026年6月26日(金)のチーム開発の授業内で、チームメンバー全員の本プロジェクト開発用の WSL2 に以下のエイリアスを `~/.bashrc` に追加した。現在は `source ~/.bashrc` を実行済みである。

```bash
alias dc='docker compose'
alias dce='docker compose exec'
```

### 使用例

```bash
# 起動
dc up -d --build
```
```bash
# 停止
dc down
```
```bash
# Laravel
dce backend php artisan route:list --path=records
```

---

## 関連ドキュメント

- [要件定義書](docs/要件定義/要件定義書.md)
- [技術構成書](docs/技術構成/技術構成書.md)
- [コーディング規約](docs/開発ルール/コーディング規約.md)
