<?php

namespace Tests\Unit;

use App\Services\StripePaymentService;
use RuntimeException;
use Tests\TestCase;

class StripeTestModeTest extends TestCase
{
    public function test_live_secret_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sk_test_');

        StripePaymentService::assertTestModeKeys('sk_live_example', 'pk_test_example');
    }

    public function test_live_publishable_key_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pk_test_');

        StripePaymentService::assertTestModeKeys('sk_test_example', 'pk_live_example');
    }

    public function test_test_keys_and_blank_keys_are_accepted(): void
    {
        StripePaymentService::assertTestModeKeys('sk_test_example', 'pk_test_example');
        StripePaymentService::assertTestModeKeys(null, null);

        $this->assertTrue(true);
    }

    public function test_is_configured_requires_test_secret(): void
    {
        $service = app(StripePaymentService::class);

        config(['services.stripe.secret' => 'sk_live_example']);
        $this->assertFalse($service->isConfigured());

        config(['services.stripe.secret' => 'sk_test_example']);
        $this->assertTrue($service->isConfigured());
    }
}
