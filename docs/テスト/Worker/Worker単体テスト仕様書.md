# 最新型家計簿！「レスマネ」　Worker 単体テスト仕様書

本書は [Worker テスト仕様書](Workerテスト仕様書.md) の一部であり、pytest による単体テスト（`worker/tests/unit/`）の仕様を定義する。

Repository・Client はモック化し、Service 層のビジネスロジックを個別に検証する。

---

## 1. Config

テストクラス: `tests/unit/test_config.py`

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UC-001 | from_env: 環境変数から全フィールドを読み取る | 全環境変数を設定 | 各フィールドが対応する値 |
| UC-002 | from_env: 未設定の環境変数はデフォルト値 | 環境変数なし | db_host="db", poll_interval_sec=30 等 |
| UC-003 | コンストラクタ: 全引数がそのまま保持される | 直接生成 | 各フィールドが引数と一致 |

---

## 2. GeminiClient

テストクラス: `tests/unit/test_gemini_client.py`

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UGC-001 | generate: 正常応答 | API が有効な JSON を返す | 応答テキストを返す |
| UGC-002 | generate: system_instruction 付き | system_instruction を指定 | リクエストボディに systemInstruction が含まれる |
| UGC-003 | generate: system_instruction なし | system_instruction=None | リクエストボディに systemInstruction がない |
| UGC-004 | generate: role 変換 | role="assistant" のメッセージ | Gemini 形式で role="model" に変換される |
| UGC-005 | generate: HTTP エラー | API が 500 を返す | requests.HTTPError が発生 |
| UGC-006 | generate: タイムアウト | API が応答しない | requests.Timeout が発生 |
| UGC-007 | generate: API キーがヘッダーで送信される | 任意のリクエスト | x-goog-api-key ヘッダーに API キー、URL にキーが含まれない |
| UGC-008 | generate: maxOutputTokens がリクエストに含まれる | デフォルト設定 | generationConfig.maxOutputTokens=1500 |

---

## 3. FeedbackService

テストクラス: `tests/unit/test_feedback_service.py`

Repository・Client・DB を全てモック化して検証する。

### 3.1 claim

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-001 | claim 成功: 初回 | upsert → (1, 1), find_for_update → 行あり | (job_id, claim_version) を返す、update_status(PROCESSING) が呼ばれる |
| UFS-002 | claim 失敗: upsert が None | 他 Worker が claim 済み | None を返す |
| UFS-003 | claim 失敗: 投稿が見つからない | find_for_update → None | None を返す、mark_cancelled が呼ばれる |
| UFS-004 | claim 失敗: 投稿が削除済み | find_for_update → None, get_ai_status → deleted | mark_cancelled(TARGET_DELETED) |

### 3.2 process_one 正常系

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-010 | 初回フィードバック: 正常完了 | parent_id=None, AI が応答 | save_response + mark_completed |
| UFS-011 | 追加チャット: 正常完了 | parent_id あり, AI が応答 | 背景情報が messages 先頭の参照データに、system_instruction に「追加チャット」タスク指示 |
| UFS-012 | AI 応答をそのまま保存 | AI が 2000 文字を返す | save_response に 2000 文字が渡される |

### 3.3 process_one 異常系

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-020 | AI 応答が空 | AI が空文字を返す | handle_failure("empty ai response") |
| UFS-021 | AI 応答が空白のみ | AI が "  \n  " を返す | handle_failure("empty ai response") |
| UFS-022 | AI 応答が 3001 文字 | AI が 3001 文字を返す | handle_failure("content exceeded max length") |
| UFS-023 | AI 応答がちょうど 3000 文字 | AI が 3000 文字を返す | save_response が呼ばれる（境界値） |
| UFS-024 | AI API がエラー | generate() が HTTPError を送出 | handle_failure、エラーが _sanitize_error で正規化 |
| UFS-025 | 家計簿レコードが見つからない | fetch_record → None | mark_failed + mark_cancelled(TARGET_DELETED) |
| UFS-026 | save_response が False (削除済み) | save_response → False, get_ai_status → deleted | mark_cancelled(TARGET_DELETED) |
| UFS-027 | save_response が False (状態不整合) | save_response → False, get_ai_status → PENDING | mark_cancelled(STATE_INCONSISTENCY) |

### 3.4 所有権保護

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-030 | save_with_ownership: 所有権喪失 | lock_for_ownership → False | save_response が呼ばれない |
| UFS-031 | handle_failure: 所有権喪失 | lock_for_ownership → False | recover_to_pending / mark_failed が呼ばれない |
| UFS-032 | save_with_ownership: 所有権あり | lock_for_ownership → True | save_response が呼ばれる |

### 3.5 リトライ

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-040 | リトライ: 上限未満 | retry_count=0, max_retries=3 | recover_to_pending + increment_retry_and_pend |
| UFS-041 | リトライ: 上限到達 | retry_count=3, max_retries=3 | mark_failed(post) + mark_failed(job) |
| UFS-042 | リトライ: recover_to_pending 失敗 | recover_to_pending → False | _sync_to_post_state で再評価 |

### 3.6 stale recovery

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-050 | stale: 投稿が削除済み | get_ai_status → deleted_at あり | mark_cancelled(TARGET_DELETED) |
| UFS-051 | stale: 投稿が COMPLETED | get_ai_status → COMPLETED | mark_completed(job) |
| UFS-052 | stale: 投稿が FAILED | get_ai_status → FAILED | mark_failed(job) |
| UFS-053 | stale: 投稿が PROCESSING, リトライ可 | get_ai_status → PROCESSING, retry < max | recover_to_pending + increment_retry_and_pend |
| UFS-054 | stale: 投稿が PENDING, リトライ可 | get_ai_status → PENDING, retry < max | increment_retry_and_pend（recover_to_pending は呼ばない） |
| UFS-055 | stale: 上限到達, 投稿が PENDING | retry >= max, get_ai_status → PENDING | force_fail + mark_failed(job) |
| UFS-056 | stale: 上限到達, force_fail 失敗 | force_fail → False | _sync_to_post_state で再評価 |
| UFS-057 | stale: 所有権喪失 | lock_for_ownership → False | 何もしない |
| UFS-058 | stale: recover_to_pending 失敗 | recover_to_pending → False | _sync_to_post_state で再評価 |
| UFS-059 | stale_timeout_sec <= 0 | config.stale_timeout_sec=0 | recover_stale が即座に return |

### 3.7 メッセージ組み立て

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-060 | 初回: 家計簿情報がユーザーメッセージに含まれる | 初回フィードバック | 金額・カテゴリ・内容が含まれる |
| UFS-061 | 初回: 自己レビューがユーザーメッセージに含まれる | self_reviews あり | 評価・コメントが含まれる |
| UFS-062 | 初回: 自己レビューなし | self_reviews=[] | 自己レビューセクションがない |
| UFS-063 | 追加: スレッド履歴が messages に含まれる | thread に 3 投稿 | messages が 4 件（先頭に参照データ + thread 3 件） |
| UFS-064 | 追加: 背景情報が messages 先頭の user メッセージに含まれる | 追加チャット | messages[0] に家計簿情報（金額・カテゴリ等） |

### 3.9 上限設定

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-080 | 初回: 固定額の上限情報が含まれる | upper_limit type=固定額, max_value=50000 | 「支出上限設定」「固定額」「50,000円」 |
| UFS-081 | 初回: 割合の上限情報 (ave_monthly_income から算出) | type=割合, max_value=30, ave_monthly_income=200000 | 「30%」「200,000円」「60,000円」 |
| UFS-082 | 初回: 上限設定なし | upper_limit=None | 「支出上限設定」セクションなし |
| UFS-083 | 追加チャット: 背景メッセージに上限情報 | upper_limit あり | messages[0] に「支出上限設定」 |
| UFS-084 | 割合: ave_monthly_income が None | ave_monthly_income=None | 「0円」 |
| UFS-085 | _build_context に upper_limit が含まれる | fetch_upper_limit が値を返す | ctx["upper_limit"] が正しい |

### 3.8 エラー正規化

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UFS-070 | sanitize_error: HTTPError | response.status_code=500 | "HTTPError: HTTP 500" |
| UFS-071 | sanitize_error: Timeout | requests.Timeout | "Timeout" |
| UFS-072 | sanitize_error: 汎用例外 | RuntimeError | "RuntimeError" |

---

## 4. DeleteSyncService

テストクラス: `tests/unit/test_delete_sync_service.py`

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UDS-001 | sync: 差分なし | fetch_deleted_since → [] | watermark 更新なし、commit |
| UDS-002 | sync: 差分あり | 削除済み投稿 3 件 | cancel + soft_delete + watermark 更新 |
| UDS-003 | sync: 失敗時に rollback + re-raise | cancel が例外 | rollback、例外が再送出 |
| UDS-004 | reconcile: 1 バッチで完了 | 削除済み 50 件 | 1 回のバッチで処理 |
| UDS-005 | reconcile: 複数バッチ | 削除済み 1500 件 | 2 回のバッチで全件処理 |
| UDS-006 | reconcile: 差分なし | fetch_deleted_since → [] | 何もしない |
| UDS-007 | reconcile: バッチ途中で失敗 | 2 バッチ目で例外 | 1 バッチ目は commit 済み、2 バッチ目で rollback、例外が再送出 |
| UDS-008 | reconcile: カーソルが次バッチへ引き継がれる | 1 バッチ完了後 | 次回取得に前バッチ末尾の (deleted_at, id) が渡される |

---

## 5. PostRepository (単体)

テストクラス: `tests/unit/test_post_repository.py`

DB をモック化し、SQL の組み立てと rowcount の判定ロジックを検証する。

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UPR-001 | save_response: rowcount=1 | cursor.rowcount=1 | True を返す |
| UPR-002 | save_response: rowcount=0 | cursor.rowcount=0 | False を返す |
| UPR-003 | mark_failed: rowcount=1 | cursor.rowcount=1 | True を返す |
| UPR-004 | mark_failed: rowcount=0 | cursor.rowcount=0 | False を返す |
| UPR-005 | force_fail: rowcount=1 | cursor.rowcount=1 | True を返す |
| UPR-006 | force_fail: rowcount=0 | cursor.rowcount=0 | False を返す |
| UPR-007 | recover_to_pending: rowcount=1 | cursor.rowcount=1 | True を返す |
| UPR-008 | recover_to_pending: rowcount=0 | cursor.rowcount=0 | False を返す |

---

## 6. WorkerJobRepository (単体)

テストクラス: `tests/unit/test_worker_job_repository.py`

| ID | テスト名 | 前提条件 | 期待結果 |
|----|---------|---------|---------|
| UWR-001 | upsert: 初回 INSERT 成功 | INSERT IGNORE rowcount=1 | (lastrowid, 1) を返す |
| UWR-002 | upsert: リトライ UPDATE 成功 | INSERT rowcount=0, UPDATE rowcount=1 | (id, claim_version) を返す |
| UWR-003 | upsert: claim 失敗 | INSERT rowcount=0, UPDATE rowcount=0 | None を返す |
| UWR-004 | lock_for_ownership: 成功 | fetchone → 行あり | True |
| UWR-005 | lock_for_ownership: 失敗 | fetchone → None | False |
| UWR-006 | mark_completed: 所有権あり | rowcount=1 | True |
| UWR-007 | mark_completed: 所有権なし | rowcount=0 | False |
