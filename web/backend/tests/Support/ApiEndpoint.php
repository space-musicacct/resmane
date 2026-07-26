<?php

declare(strict_types=1);

namespace Tests\Support;

interface ApiEndpoint
{
    public function login(): string;

    public function logout(): string;

    public function register(): string;

    public function records(): string;

    public function user(): string;

    public function categories(): string;

    public function amountTypes(): string;

    public function settingsLimit(): string;
}
