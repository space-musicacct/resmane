<?php

namespace App\Providers;

use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;
use App\Repositories\V1\Contracts\UserRepositoryInterface;
use App\Repositories\V1\KakeiboRecordRepository;
use App\Repositories\V1\PostRepository;
use App\Repositories\V1\SelfReviewRepository;
use App\Repositories\V1\UpperLimitSettingRepository;
use App\Repositories\V1\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(KakeiboRecordRepositoryInterface::class, KakeiboRecordRepository::class);
        $this->app->bind(SelfReviewRepositoryInterface::class, SelfReviewRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(UpperLimitSettingRepositoryInterface::class, UpperLimitSettingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
