# 最新型家計簿！「レスマネ」　Worker テスト仕様書

## 1. 文書情報

| 項目           | 内容                       |
| -------------- | -------------------------- |
| 文書名         | Worker テスト仕様書         |
| プロジェクト名 | 最新型家計簿！「レスマネ」 |
| チーム名       | 節約志向になりたい         |
| 作成日         | 2026-07-21                 |
| 最終更新日     | 2026-07-21                 |
| 作成者         | 小川 悠馬                  |
| 版数           | v1.0                       |

### 1.1 改訂履歴

| 版数 | 日付       | 更新者    | 更新内容 |
| ---- | ---------- | --------- | -------- |
| v1.0 | 2026-07-21 | 小川 悠馬 | 初版作成 |

---

## 2. 前提

本書は、BG設計書（`docs/BG設計/BG設計書.md`）に基づき、Python Worker によるバックグラウンド処理のテスト仕様を定義する。

Laravel 側のテスト仕様書（`docs/テスト/テスト仕様書.md` 等）とは独立しており、相互に変更を加えない。

### 2.1 テスト対象の範囲

| 対象                                     | 含む/除く |
| ---------------------------------------- | --------- |
| FeedbackService（claim・AI 呼び出し・書き戻し・リトライ・stale recovery） | 含む |
| DeleteSyncService（削除同期・全件照合）   | 含む      |
| PostRepository（条件付き UPDATE・排他ロック） | 含む   |
| KakeiboContextRepository（コンテキスト取得） | 含む   |
| WorkerJobRepository（upsert・claim_version・所有権） | 含む |
| SyncWatermarkRepository（watermark 取得・更新） | 含む  |
| GeminiClient（API 呼び出し・レスポンス変換） | 含む   |
| Config（環境変数読み取り）                | 含む      |
| main.py（ポーリングループ）              | **除く**  |
| 外部 AI API の実際の呼び出し             | **除く**  |

### 2.2 テスト種別と関連文書

| 種別       | ツール  | ディレクトリ      | 目的                                                             | 仕様書                                                 |
| ---------- | ------- | ----------------- | ---------------------------------------------------------------- | ------------------------------------------------------ |
| 単体テスト | pytest  | `worker/tests/unit/`    | Repository・Client・Service のビジネスロジックをモック差し替えで個別検証 | [Worker 単体テスト仕様書](Worker単体テスト仕様書.md)   |
| 結合テスト | pytest  | `worker/tests/integration/` | 実 DB (MySQL) を使った Repository → DB のクエリ検証、デッドロック検証   | [Worker 結合テスト仕様書](Worker結合テスト仕様書.md)   |

### 2.3 テスト環境

| 項目               | 内容                                             |
| ------------------ | ------------------------------------------------ |
| Python             | 3.11                                             |
| pytest             | 8.3.5                                            |
| データベース       | MySQL 8.4（テスト用 DB を使用）                  |
| DB 初期化          | 各テストで `CREATE TABLE` / `TRUNCATE` で初期化   |
| モック             | `unittest.mock`（標準ライブラリ）                |

### 2.4 テスト実行方法

```bash
# Worker コンテナ内で実行
docker compose exec resmane-worker pytest

# 単体テストのみ
docker compose exec resmane-worker pytest tests/unit/

# 結合テストのみ
docker compose exec resmane-worker pytest tests/integration/

# 特定のテストファイル
docker compose exec resmane-worker pytest tests/unit/test_feedback_service.py

# 特定のテスト関数
docker compose exec resmane-worker pytest tests/unit/test_feedback_service.py::test_process_one_success -v
```

### 2.5 テスト失敗時の取り扱い

テストが失敗した場合、以下の手順で対応する。

1. **失敗内容を確認**: テスト名・期待値・実際の値の差分を確認
2. **原因を切り分け**: テスト側の不備か、実装側のバグかを BG 設計書を基準に判断
3. **修正する**: テスト不備ならテストブランチで修正、実装バグなら fix ブランチで修正後にテストブランチへ取り込み
4. **回帰テストを実行**: 修正後は全テストを再実行

---

## 3. 関連文書

- [Worker 単体テスト仕様書](Worker単体テスト仕様書.md)
- [Worker 結合テスト仕様書](Worker結合テスト仕様書.md)
- BG設計書（`docs/BG設計/BG設計書.md`）
- 要件定義書（`docs/要件定義/要件定義書.md`）
- API設計書（`docs/API設計/API設計書.md`）
