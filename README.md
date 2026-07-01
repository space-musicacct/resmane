# 最新型家計簿！「レスマネ」

学生向け AI 連動家計簿 Web アプリケーション。

## 技術スタック

| 層 | 技術 |
|----|------|
| フロントエンド | React 18 + TypeScript 5 + Tailwind CSS 3 (Vite 5) |
| バックエンド | PHP 8.3 + Laravel 10 (API サーバー) |
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

---

## 関連ドキュメント

- [要件定義書](docs/要件定義/要件定義書.md)
- [技術構成書](docs/技術構成/技術構成書.md)
- [コーディング規約](docs/開発ルール/コーディング規約.md)
