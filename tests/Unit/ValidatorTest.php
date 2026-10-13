<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Validation\ValidationException;
use BXCode\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_passes_and_returns_validated_subset(): void
    {
        $validated = (new Validator())->validate(
            ['name' => 'Ada', 'email' => 'ada@x.io', 'extra' => 'ignored'],
            ['name' => 'required|string|max:100', 'email' => 'required|email'],
        );

        self::assertSame(['name' => 'Ada', 'email' => 'ada@x.io'], $validated);
    }

    public function test_required_fields_fail_with_messages(): void
    {
        try {
            (new Validator())->validate([], ['name' => 'required', 'email' => 'required|email']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('name', $e->errors());
            self::assertArrayHasKey('email', $e->errors());
            self::assertStringContainsString('required', $e->first()['name']);
        }
    }

    public function test_optional_absent_field_skips_rules(): void
    {
        $validated = (new Validator())->validate(
            ['name' => 'Ada'],
            ['name' => 'required|string', 'nickname' => 'string|max:10'],
        );

        self::assertSame(['name' => 'Ada'], $validated);
    }

    public function test_rule_set_integer_numeric_email_url_in_between(): void
    {
        $data = [
            'age'   => '25',
            'score' => 3.5,
            'email' => 'a@b.io',
            'site'  => 'https://bxcode.dev',
            'role'  => 'admin',
        ];
        $rules = [
            'age'   => 'integer|between:18,120',
            'score' => 'numeric',
            'email' => 'email',
            'site'  => 'url',
            'role'  => 'in:admin,editor',
        ];

        self::assertSame($data, (new Validator())->validate($data, $rules));
    }

    public function test_invalid_values_collect_errors(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(
            ['age' => 'old', 'email' => 'nope', 'role' => 'ghost'],
            ['age' => 'integer', 'email' => 'email', 'role' => 'in:admin,editor'],
        );
    }

    public function test_max_applies_to_string_length(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(['name' => 'abcdef'], ['name' => 'max:3']);
    }

    public function test_custom_rule(): void
    {
        $validator = (new Validator())->extend(
            'even',
            fn (mixed $value): bool => is_numeric($value) && ((int) $value) % 2 === 0,
            'The {field} must be even.',
        );

        self::assertSame(['n' => '4'], $validator->validate(['n' => '4'], ['n' => 'even']));

        $this->expectException(ValidationException::class);
        $validator->validate(['n' => '3'], ['n' => 'even']);
    }
}
