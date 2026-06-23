
/** バリデーションエラーのフィールド別メッセージ */
export interface ValidationErrors {
  [field: string]: string[];
}

/** API エラーレスポンスの共通形式 (Laravel が返す JSON) */
export interface ApiErrorResponse {
  message: string;
  errors?: ValidationErrors;
}

/** メッセージのみのレスポンス (ログアウト等) */
export interface MessageResponse {
  message: string;
}

/** JsonResource のラッパー形式 ({ data: T }) */
export interface ResourceResponse<T> {
  data: T;
}
