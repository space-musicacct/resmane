# 最新型家計簿！「レスマネ」　Worker 結合テスト仕様書

本書は [Worker テスト仕様書](Workerテスト仕様書.md) の一部であり、pytest による結合テスト（`worker/tests/integration/`）の仕様を定義する。

実際の MySQL（テスト用 DB）を使い、Repository → DB のクエリ検証、排他制御、デッドロック検証を行う。

---

## 1. テスト環境

| 項目           | 内容                                                     |
| -------------- | -------------------------------------------------------- |
| データベース   | MySQL 8.4（テスト専用 DB: `resmane_test` / `resmane_worker_test`） |
| 初期化方式     | 各テストクラスの `setup_method` で TRUNCATE して初期化     |
| テーブル作成   | `conftest.py` で migration SQL を実行して CREATE TABLE    |
| 並行テスト     | `threading` で複数接続を同時操作                          |

### 1.1 テスト用 DB

結合テストは本番・開発用 DB とは別のテスト専用 DB を使用する。

| DB 名                | 用途                     |
| -------------------- | ------------------------ |
| `resmane_test`        | Laravel DB のテスト代替  |
| `resmane_worker_test` | Worker DB のテスト代替   |

---

## 2. PostRepository 結合テスト

テストクラス: `tests/integration/test_post_repository.py`

### 2.1 fetch_pending

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IPR-001 | pending な AI 投稿を取得 | is_ai=1, ai_status_id=PENDING, deleted_at=NULL の投稿 2 件 | 2 件取得、id 昇順 |
| IPR-002 | 削除済みの投稿は除外 | deleted_at が設定済み | 0 件 |
| IPR-003 | PROCESSING の投稿は除外 | ai_status_id=PROCESSING | 0 件 |
| IPR-004 | is_ai=0 の投稿は除外 | ユーザー投稿 | 0 件 |

### 2.2 条件付き UPDATE

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IPR-010 | save_response: PROCESSING → COMPLETED | ai_status_id=PROCESSING, deleted_at=NULL | True, content と ai_status_id が更新される |
| IPR-011 | save_response: 削除済みなら False | deleted_at あり | False, レコード変更なし |
| IPR-012 | save_response: PENDING なら False | ai_status_id=PENDING | False |
| IPR-013 | mark_failed: PROCESSING → FAILED | ai_status_id=PROCESSING | True |
| IPR-014 | mark_failed: PENDING なら False | ai_status_id=PENDING | False |
| IPR-015 | force_fail: PENDING → FAILED | ai_status_id=PENDING | True |
| IPR-016 | force_fail: PROCESSING → FAILED | ai_status_id=PROCESSING | True |
| IPR-017 | force_fail: COMPLETED なら False | ai_status_id=COMPLETED | False |
| IPR-018 | recover_to_pending: PROCESSING → PENDING | ai_status_id=PROCESSING | True |
| IPR-019 | recover_to_pending: PENDING なら False | ai_status_id=PENDING | False |

### 2.3 排他ロック

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IPR-020 | find_for_update: PENDING な行をロック | PENDING の投稿 | 行を取得、他トランザクションがブロックされる |
| IPR-021 | find_for_update: 削除済みなら None | deleted_at あり | None |

### 2.4 fetch_deleted_since

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IPR-030 | 複合カーソルで差分取得 | deleted_at が異なる 3 件 | watermark 以降の件のみ取得 |
| IPR-031 | 同一 deleted_at の id 差分 | 同時刻に削除された 2 件 | last_id より大きい件のみ |
| IPR-032 | LIMIT で件数制限 | 削除済み 5 件, limit=3 | 3 件のみ |

---

## 3. WorkerJobRepository 結合テスト

テストクラス: `tests/integration/test_worker_job_repository.py`

### 3.1 upsert (原子的 claim)

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IWR-001 | 初回 claim 成功 | ジョブなし | (job_id, 1) を返す、status=PROCESSING |
| IWR-002 | 重複 INSERT は無視 | 既存ジョブ PROCESSING | None を返す |
| IWR-003 | RETRY_PENDING → PROCESSING で再 claim | 既存ジョブ RETRY_PENDING | (job_id, old_version+1)、status=PROCESSING |
| IWR-004 | COMPLETED ジョブは再 claim 不可 | 既存ジョブ COMPLETED | None |
| IWR-005 | FAILED ジョブは再 claim 不可 | 既存ジョブ FAILED | None |

### 3.2 所有権確認 (claim_version)

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IWR-010 | lock_for_ownership: 正しい version | status=PROCESSING, cv 一致 | True |
| IWR-011 | lock_for_ownership: version 不一致 | cv が古い | False |
| IWR-012 | lock_for_ownership: status が RETRY_PENDING | status ≠ PROCESSING | False |
| IWR-013 | mark_completed: 所有権あり | cv 一致 | True, status=COMPLETED |
| IWR-014 | mark_completed: 所有権なし | cv 不一致 | False, status 変更なし |
| IWR-015 | mark_failed: 所有権あり | cv 一致 | True, last_error が設定される |
| IWR-016 | mark_cancelled: 所有権あり | cv 一致 | True, termination_reason が設定される |
| IWR-017 | increment_retry_and_pend: 所有権あり | cv 一致 | True, retry_count+1, status=RETRY_PENDING |
| IWR-018 | increment_retry_and_pend: 所有権なし | cv 不一致 | False, retry_count 変更なし |

### 3.3 stale recovery 対象取得

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IWR-020 | fetch_stale: タイムアウト超過 | claimed_at が 400 秒前 | 検出される、claim_version が含まれる |
| IWR-021 | fetch_stale: タイムアウト未到達 | claimed_at が 100 秒前 | 検出されない |
| IWR-022 | fetch_stale: RETRY_PENDING は除外 | status=RETRY_PENDING | 検出されない |
| IWR-023 | fetch_stale: 削除済みは除外 | deleted_at あり | 検出されない |

### 3.4 削除同期

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| IWR-030 | cancel_processing_by_post_ids | PROCESSING ジョブ 2 件 | 2 件キャンセル、termination_reason=target_deleted |
| IWR-031 | cancel_processing_by_post_ids: COMPLETED は対象外 | COMPLETED ジョブ | 0 件 |
| IWR-032 | soft_delete_by_post_ids | ジョブ 3 件 | 3 件に deleted_at が設定される |
| IWR-033 | soft_delete_by_post_ids: 既に削除済みは対象外 | deleted_at あり | 0 件 |

---

## 4. SyncWatermarkRepository 結合テスト

テストクラス: `tests/integration/test_sync_watermark_repository.py`

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| ISW-001 | get_for_update: 初回は初期行を作成 | テーブル空 | last_deleted_at=1970-01-01, last_id=0 |
| ISW-002 | get_for_update: 既存行を取得 | save 済み | 保存した値を返す |
| ISW-003 | save: 新規作成 | テーブル空 | INSERT される |
| ISW-004 | save: 既存更新 | 既存行あり | UPDATE される |
| ISW-005 | get_for_update: 排他ロック | 2 トランザクション同時 | 後発がブロックされる |

---

## 5. デッドロック検証

テストクラス: `tests/integration/test_deadlock.py`

`threading` で複数接続を同時操作し、ロック順の統一によりデッドロックが発生しないことを検証する。

### 5.1 claim 競合

| ID | テスト名 | シナリオ | 期待結果 |
|----|---------|---------|---------|
| IDL-001 | 2 Worker が同じ post_id を同時 claim | Thread A, B が同時に upsert | 一方だけ成功、他方は None。デッドロックなし |
| IDL-002 | claim と stale recovery の同時実行 | Thread A が claim、Thread B が同一ジョブを stale 回収 | 一方だけ所有権取得。デッドロックなし |

### 5.2 書き戻し競合

| ID | テスト名 | シナリオ | 期待結果 |
|----|---------|---------|---------|
| IDL-010 | save_response と stale recovery の同時実行 | Thread A が save_with_ownership、Thread B が recover_one | 所有権を持つ方だけが Laravel DB を更新。デッドロックなし |
| IDL-011 | 2 Worker が同じジョブを mark_completed | Thread A (旧 cv), Thread B (新 cv) | 新 cv のみ成功。デッドロックなし |
| IDL-012 | 旧 Worker の _handle_failure が新 claim を破壊しない | Thread A (旧 cv) が失敗処理、Thread B (新 cv) が処理中 | A の lock_for_ownership が失敗し、Laravel DB (posts) は変更されない。B の投稿は PROCESSING のまま |

### 5.3 削除同期の競合

| ID | テスト名 | シナリオ | 期待結果 |
|----|---------|---------|---------|
| IDL-020 | sync と claim の同時実行 | Thread A が sync、Thread B が同じ post_id を claim | 両方完了。sync が先なら claim 失敗、claim が先なら sync でキャンセル。デッドロックなし |
| IDL-021 | 2 sync の同時実行 | Thread A, B が同時に sync | watermark の FOR UPDATE で直列化。デッドロックなし |

---

## 6. 状態遷移の網羅テスト

テストクラス: `tests/integration/test_state_transitions.py`

BG 設計書の状態表（§6.7）を結合テストで網羅的に検証する。

### 6.1 stale recovery 状態表

| ID | 投稿の状態 | ジョブの状態 | retry | 期待される遷移 |
|----|-----------|------------|-------|--------------|
| IST-001 | PENDING | PROCESSING | < max | job → RETRY_PENDING |
| IST-002 | PROCESSING | PROCESSING | < max | post → PENDING, job → RETRY_PENDING |
| IST-003 | COMPLETED | PROCESSING | any | job → COMPLETED |
| IST-004 | FAILED | PROCESSING | any | job → FAILED |
| IST-005 | 削除済み | PROCESSING | any | job → CANCELLED (TARGET_DELETED) |
| IST-006 | 存在しない | PROCESSING | any | job → CANCELLED (TARGET_DELETED) |
| IST-007 | PENDING | PROCESSING | >= max | post → FAILED (force_fail), job → FAILED |
| IST-008 | PROCESSING | PROCESSING | >= max | post → FAILED (force_fail), job → FAILED |

### 6.2 2DB 間の中間停止・復旧

| ID | 停止点 | 初期状態 | 復旧後の期待 |
|----|-------|---------|------------|
| IST-010 | upsert 後、posts 更新前 | post=PENDING, job=PROCESSING | stale recovery → job=RETRY_PENDING |
| IST-011 | posts PROCESSING 後、AI 呼び出し前 | post=PROCESSING, job=PROCESSING | stale recovery → post=PENDING, job=RETRY_PENDING |
| IST-012 | AI 完了後、save_response 前 | post=PROCESSING, job=PROCESSING | stale recovery → post=PENDING, job=RETRY_PENDING |
| IST-013 | save_response 後、mark_completed 前 | post=COMPLETED, job=PROCESSING | stale recovery → job=COMPLETED |
