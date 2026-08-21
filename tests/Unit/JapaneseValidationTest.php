<?php

namespace Tests\Unit;

use Tests\TestCase;

class JapaneseValidationTest extends TestCase
{
    public function test_required_validation_message_is_japanese(): void
    {
        $this->assertSame(':attributeは必須です。', __('validation.required'));
    }

    public function test_integer_and_max_validation_messages_are_japanese(): void
    {
        $this->assertSame(':attributeは整数で指定してください。', __('validation.integer'));
        $this->assertSame(':attributeは:max文字以下にしてください。', __('validation.max.string'));
        $this->assertSame(':attributeは:min以上の値にしてください。', __('validation.min.numeric'));
    }
}
