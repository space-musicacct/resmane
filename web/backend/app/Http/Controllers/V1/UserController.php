<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\AuthController;
use App\Http\Requests\V1\UserUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\KakeiboRecord;
use App\Models\Post;
use App\Models\SelfReview;
use App\Models\UpperLimitSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UserUpdateRequest $request): JsonResponse
    {
        $user = $this->findAuthUserOrFail($request);
        $validated = $request->validated();

        if (isset($validated['loginId'])) {
            $exists = User::withTrashed()
                ->where('login_id', $validated['loginId'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'このログインIDは既に使用されています',
                    'errors' => (object) [],
                ], 409);
            }
        }

        if (isset($validated['email'])) {
            $exists = User::withTrashed()
                ->where('email', $validated['email'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'このメールアドレスは既に使用されています',
                    'errors' => (object) [],
                ], 409);
            }
        }

        if (isset($validated['password'])) {
            if (!Hash::check($validated['currentPassword'], $user->password_hash)) {
                return response()->json([
                    'message' => '現在のパスワードが正しくありません',
                    'errors' => (object) [],
                ], 422);
            }
        }

        $updateData = [];

        if (isset($validated['loginId'])) {
            $updateData['login_id'] = $validated['loginId'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse|Response
    {
        $user = $this->findAuthUserOrFail($request);

        $request->validate([
            'currentPassword' => ['required', 'string'],
        ], [
            'currentPassword.required' => '現在のパスワードは必須です',
        ]);

        if (!Hash::check($request->input('currentPassword'), $user->password_hash)) {
            return response()->json([
                'message' => '現在のパスワードが正しくありません',
                'errors' => (object) [],
            ], 422);
        }

        $recordIds = KakeiboRecord::where('user_id', $user->id)->pluck('id');

        if ($recordIds->isNotEmpty()) {
            SelfReview::whereIn('kakeibo_record_id', $recordIds)->delete();
            Post::whereIn('kakeibo_record_id', $recordIds)->delete();
            KakeiboRecord::whereIn('id', $recordIds)->delete();
        }

        UpperLimitSetting::where('user_id', $user->id)->delete();

        $user->delete();

        (new AuthController())->destroy($request);

        return response()->noContent();
    }

    private function findAuthUserOrFail(Request $request): User
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'ログインが必要です');
        }

        return $user;
    }
}
