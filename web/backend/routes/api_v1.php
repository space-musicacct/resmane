<?php

use App\Http\Controllers\V1\AmountTypeController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\KakeiboRecordController;
use App\Http\Controllers\V1\PostController;
use App\Http\Controllers\V1\SelfReviewController;
use App\Http\Controllers\V1\SettingLimitController;
use App\Http\Controllers\V1\UserController;
use Illuminate\Support\Facades\Route;

// 認証 (ゲスト)
Route::post('register', [AuthController::class, 'register'])->name('auth.register');
Route::post('login', [AuthController::class, 'login'])->name('auth.login');

// 認証必須
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');

    // 家計簿レコード (API設計書は PUT のみ定義のため update を分離)
    Route::apiResource('records', KakeiboRecordController::class)
        ->except(['update'])
        ->names('kakeibo')
        ->parameters(['records' => 'id']);
    Route::put('records/{id}', [KakeiboRecordController::class, 'update'])->name('kakeibo.update');

    // 自己レビュー (家計簿レコードにネスト、同上)
    Route::apiResource('records.reviews', SelfReviewController::class)
        ->except(['show', 'update'])
        ->names('review')
        ->parameters(['records' => 'recordId', 'reviews' => 'id']);
    Route::put('records/{recordId}/reviews/{id}', [SelfReviewController::class, 'update'])->name('review.update');

    // スレッド・AIメッセージ (家計簿レコードにネスト)
    Route::apiResource('records.posts', PostController::class)
        ->only(['index', 'store'])
        ->names('post')
        ->parameters(['records' => 'recordId']);

    // 基準値設定
    Route::get('settings/limit', [SettingLimitController::class, 'show'])->name('settings.limit.show');
    Route::put('settings/limit', [SettingLimitController::class, 'update'])->name('settings.limit.update');

    // ユーザー情報
    Route::get('user', [UserController::class, 'show'])->name('user.show');
    Route::put('user', [UserController::class, 'update'])->name('user.update');
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // マスタデータ
    Route::get('categories', [CategoryController::class, 'index'])->name('category.index');
    Route::get('amountTypes', [AmountTypeController::class, 'index'])->name('amount_type.index');
});
