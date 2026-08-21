<?php

namespace App\Support;

class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('app.demo_mode');
    }

    public static function allowsDeletes(): bool
    {
        return ! static::enabled();
    }

    public static function refreshHours(): int
    {
        return max(1, (int) config('app.demo_refresh_hours', 4));
    }

    public static function dummyName(): string
    {
        return '山田 太郎';
    }

    public static function dummyEmail(): string
    {
        return 'taro@example.com';
    }

    public static function dummyTel(): string
    {
        return '090-0000-0000';
    }

    public static function stripeTestCard(): string
    {
        return '4242 4242 4242 4242';
    }
}
