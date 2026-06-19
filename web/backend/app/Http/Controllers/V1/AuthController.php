<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    private const int MAX_LOGIN_ATTEMPTS = 5;
    private const int LOCKOUT_SECONDS = 3600;
    /**
     * ユーザー登録
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (User::withTrashed()->where('login_id', $validated['loginId'])->exists()) {
            return response()->json([
                'message' => 'このログインIDは既に使用されています',
                'errors' => (object) [],
            ], 409);
        }

        if (User::withTrashed()->where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'このメールアドレスは既に使用されています',
                'errors' => (object) [],
            ], 409);
        }

        try {
            $user = User::create([
                'login_id' => $validated['loginId'],
                'email' => $validated['email'],
                'name' => $validated['name'],
                'password_hash' => Hash::make($validated['password']),
            ]);
        } catch (QueryException) {
            return response()->json([
                'message' => 'このログインIDまたはメールアドレスは既に使用されています',
                'errors' => (object) [],
            ], 409);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * ログイン（IP単位のロックアウト制御: 5回失敗で1時間ロック）
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'ログイン試行回数が上限に達しました。' . ceil($seconds / 60) . '分後に再試行してください',
                'errors' => (object) [],
            ], 429);
        }

        $validated = $request->validated();

        if (!Auth::attempt(['login_id' => $validated['loginId'], 'password' => $validated['password']])) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            return response()->json([
                'message' => 'ログインIDまたはパスワードが正しくありません',
                'errors' => (object) [],
            ], 401);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json([
            'user' => new UserResource(Auth::user()),
        ]);
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
