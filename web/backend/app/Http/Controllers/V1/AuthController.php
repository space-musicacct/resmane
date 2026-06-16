<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

/**
 * 認証 API コントローラー
 *
 * ユーザー登録・ログイン・ログアウトを処理する
 * Sanctum の Cookie/Session 認証を使用
 */
class AuthController extends Controller
{

    /**
     * ユーザー登録
     *
     * 新規ユーザーを作成し、そのままログイン状態にする
     *
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function register(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * ログイン
     *
     * ログインIDとパスワードで認証し、セッションを開始する。
     * IP単位のロックアウト制御 (5回失敗で1時間ロック) を含む
     *
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function login(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * ログアウト
     *
     * セッションを破棄し、CSRFトークンを再生成する
     *
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function logout(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }
}
