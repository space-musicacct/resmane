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
```

## アクセス

| URL | 内容 |
|-----|------|
| http://localhost:50080 | React (フロントエンド) |
| http://localhost:50080/api | Laravel API |
| localhost:53306 | MySQL (DB クライアントから接続) |

## よく使うコマンド

```bash
# 起動
docker compose up -d

# 停止
docker compose down

# コンテナ状態確認
docker compose ps

# ログ確認
docker compose logs -f

# 再ビルド
docker compose up -d --build

# ベースイメージを最新に更新 (セキュリティパッチ適用)
docker compose build --pull --no-cache

# フロントエンドに npm パッケージを追加
docker compose exec frontend npm install <package>

# バックエンドで artisan コマンド実行
docker compose exec backend php artisan <command>
```

## 既存環境の更新手順

### Node.js 20 → 24 (2026-06-07)

Node 20 が EOL のため Node 24 LTS に変更しました。構築済みの環境は以下で更新してください。

```bash
docker compose down
docker volume rm resmane_frontend_node_modules
docker compose build --pull --no-cache frontend
docker compose up -d
```

---

## 関連ドキュメント

- [要件定義書](docs/要件定義/要件定義書.md)
- [技術構成書](docs/技術構成/技術構成書.md)
- [コーディング規約](docs/開発ルール/コーディング規約.md)
