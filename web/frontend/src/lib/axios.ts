import axios, { AxiosError, AxiosResponse } from 'axios';
import type { ApiErrorResponse } from '../types.ts';

/**
 * Cookie 認証対応済みの axios インスタンス
 *
 * Sanctum SPA 認証に必要な設定を含む。
 */
const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

/** response が必ず存在する AxiosError (型ガード用) */
type ApiError = AxiosError<ApiErrorResponse> & {
  response: AxiosResponse<ApiErrorResponse>;
};

/**
 * API エラーの型ガード
 *
 * catch ブロックで使用し、err.response.data に型安全にアクセスできるようにする。
 */
export function isApiError(err: unknown): err is ApiError {
  return axios.isAxiosError(err) && err.response !== undefined;
}

/**
 * Sanctum の CSRF Cookie を取得する
 *
 * ログイン・登録などの認証リクエスト前に呼び出す。
 * /sanctum/csrf-cookie は Sanctum 固定パスのため baseURL を経由しない。
 */
export async function getCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

let onUnauthorized: (() => void) | null = null;
let onNavigateError: ((path: string) => void) | null = null;

export function setupInterceptors(
  handleUnauthorized: () => void,
  handleNavigateError: (path: string) => void,
) {
  onUnauthorized = handleUnauthorized;
  onNavigateError = handleNavigateError;
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response) {
      const { status } = error.response;
      if (status === 401 || status === 419) {
        onUnauthorized?.();
      } else if (status === 403) {
        onNavigateError?.('/403');
      } else if (status === 500) {
        onNavigateError?.('/500');
      }
    }
    return Promise.reject(error);
  },
);

export default api;
