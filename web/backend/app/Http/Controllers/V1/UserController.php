<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UserUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\V1\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UserUpdateRequest $request): JsonResponse
    {
        $result = $this->userService->update($request->user(), $request->validated());

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'errors' => (object) [],
            ], $result['status']);
        }

        Auth::setUser($result['user']);

        return response()->json([
            'data' => new UserResource($result['user']),
        ]);
    }

    public function destroy(Request $request): JsonResponse|Response
    {
        $request->validate([
            'currentPassword' => ['required', 'string'],
        ], [
            'currentPassword.required' => '現在のパスワードは必須です',
        ]);

        $result = $this->userService->destroy($request->user(), $request->input('currentPassword'));

        if ($result !== null) {
            return response()->json([
                'message' => $result['error'],
                'errors' => (object) [],
            ], $result['status']);
        }

        new AuthController()->destroy($request);

        return response()->noContent();
    }
}
