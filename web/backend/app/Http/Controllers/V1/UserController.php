<?php

namespace App\Http\Controllers\V1;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Http\Requests\UserUpdateRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    /**
     * ログインユーザー情報取得
     *
     * 認証済みユーザーの情報を返す
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * @param UserUpdateRequest $request
     * @return JsonResponse
     */
    public function update(UserUpdateRequest $request, int $id): JsonResponse
    {
        $user = $this->findUserOrFail($id);

        $validated = $request->validated();

        $user->update([
            'login_id' => $validated['loginId'] ?? $user->login_id,
            'email' => $validated['email'] ?? $user->email,
            'name' => $validated['name'] ?? $user->name,
            'password_hash' => isset($validated['passwordHash']) ? Hash::make($validated['passwordHash']) : $user->password_hash,
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     */
    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $user = $this->findUserOrFail($id);

        if($request->session()->has('resmane_session')){
            $request->session()->forget('resmane_session');
        }else{
            return redirect()->route('login');
        }

        $user->delete();

        return response()->noContent();
    }

    private function findUserOrFail(int $id): User
    {
        $user = User::find($id);

        if (!$user){
            abort(404,'指定したユーザーが見つかりません');
        }
        if($user->user_id != auth()->id()){
            abort(403,'権限がありません');
        }

        return $user;
    }
}
