<?php

declare(strict_types=1);

namespace Tests\Support;

class V1ApiEndpoint implements ApiEndpoint
{
    private const string PREFIX = '/api/v1';

    public function login(): string
    {
        return self::PREFIX . '/login';
    }

    public function logout(): string
    {
        return self::PREFIX . '/logout';
    }

    public function register(): string
    {
        return self::PREFIX . '/register';
    }

    public function records(): string
    {
        return self::PREFIX . '/records';
    }

    public function user(): string
    {
        return self::PREFIX . '/user';
    }

    public function categories(): string
    {
        return self::PREFIX . '/categories';
    }

    public function amountTypes(): string
    {
        return self::PREFIX . '/amountTypes';
    }

    public function settingsLimit(): string
    {
        return self::PREFIX . '/settings/limit';
    }
}
