<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\View\Factory;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Factory(dirname(__DIR__, 2) . '/resources/views');
    }

    public function test_renders_view_through_layout_with_partial(): void
    {
        $html = $this->factory->render('home', ['title' => 'Hello']);

        self::assertStringContainsString('<h1>Hello</h1>', $html);     // section content
        self::assertStringContainsString('<title>Hello</title>', $html); // layout used the data
        self::assertStringContainsString('Powered by BXCode', $html);   // partial inserted
    }

    public function test_escapes_output(): void
    {
        $html = $this->factory->render('home', ['title' => '<script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_view_object_is_stringable(): void
    {
        $view = $this->factory->make('home', ['title' => 'Str']);

        self::assertStringContainsString('<h1>Str</h1>', (string) $view);
    }

    public function test_missing_view_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->factory->render('does-not-exist');
    }
}
