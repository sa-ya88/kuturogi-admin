<?php

namespace Tests\Unit;

use App\Support\FieldLimits;
use Tests\TestCase;

class FieldLimitsTest extends TestCase
{
    public function test_string_limits_are_within_varchar_columns(): void
    {
        $this->assertLessThanOrEqual(255, FieldLimits::TITLE);
        $this->assertLessThanOrEqual(255, FieldLimits::EMAIL);
        $this->assertLessThanOrEqual(50, FieldLimits::ROOM_CODE);
        $this->assertGreaterThan(0, FieldLimits::DESCRIPTION);
    }
}
