# BE向け連絡: モデルクラス作成時の注意点

**日付:** 2026-06-12
**宛先:** 髙木さん（バックエンド担当）
**送信者:** 小川

---

## テーブル名とモデル名の対応

Laravel の Eloquent はモデル名から自動的にテーブル名を推測する（モデル名の複数形スネークケース）。以下のテーブルは自動推測が効かないため、モデル内で `$table` を明示的に指定する必要がある。

### `$table` の明示指定が必要なモデル

| モデルクラス名            | テーブル名（DB）              | 自動推測される名前             | 指定が必要な理由                      |
| ------------------------- | ----------------------------- | ------------------------------ | ------------------------------------- |
| `KakeiboRecord`           | `kakeibo_records`             | `kakeibo_records`              | 一致するので不要（確認用）            |
| `AmountType`              | `amount_types`                | `amount_types`                 | 一致するので不要（確認用）            |
| `KakeiboDefaultCategory`  | `kakeibo_default_categories`  | `kakeibo_default_categories`   | 一致するので不要（確認用）            |
| `UpperLimitSetting`       | `upper_limit_settings`        | `upper_limit_settings`         | 一致するので不要（確認用）            |
| `UpperLimitType`          | `upper_limit_types`           | `upper_limit_types`            | 一致するので不要（確認用）            |
| `SelfReview`              | `self_reviews`                | `self_reviews`                 | 一致するので不要（確認用）            |
| `Post`                    | `posts`                       | `posts`                        | 一致するので不要（確認用）            |
| `AiStatus`                | `ai_statuses`                 | `ai_statuses`                  | 一致するので不要（確認用）            |

確認したところ、現状のテーブル名はすべて Laravel の命名規則（モデル名の複数形スネークケース）に沿っているため、`$table` の明示指定は不要。ただし、**テーブル定義書やER図と実装のテーブル名が一致しているか、マイグレーション作成時に必ず確認してください**。

## SoftDeletes の適用

すべてのテーブルに `deleted_at` カラムがあるため、全モデルで `SoftDeletes` トレイトを使用する。

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class KakeiboRecord extends Model
{
    use SoftDeletes;
}
```

## API レスポンスのキー変換（camelCase）

API設計書ではリクエスト・レスポンスの JSON キーをキャメルケース（`camelCase`）で定義している。DB カラム名（`snake_case`）との変換は API Resource 層で行う。

```php
// app/Http/Resources/KakeiboRecordResource.php
class KakeiboRecordResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'purchaseDate' => $this->purchase_date,
            'amountTypeId' => $this->amount_type_id,
            // ...
        ];
    }
}
```

## posts テーブルの注意点

- `user_id` は NOT NULL。AI投稿（`is_ai = 1`）の場合も、対象の家計簿レコードの所有者のユーザーIDを格納する（認可チェック用）
- `content` は VARCHAR(3000)
- `parent_id` は自己参照FK（リプライ先）。バリデーション時に同一 `kakeibo_record_id` 内の投稿に限定すること

## upper_limit_settings テーブルの注意点

- `user_id` FK あり（ユーザーごとに基準値を持つ）
- GET/PUT `/api/v1/settings/limit` は認証ユーザーの設定のみ返す・更新する
- 未登録の場合は PUT で新規作成（upsert）
