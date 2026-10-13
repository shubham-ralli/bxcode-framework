<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventsTest extends TestCase
{
    public function test_dispatch_runs_listeners_with_args_and_returns_values(): void
    {
        $events = new Dispatcher();
        $events->listen('sum', fn (int $a, int $b): int => $a + $b);
        $events->listen('sum', fn (int $a, int $b): int => $a * $b);

        self::assertSame([5, 6], $events->dispatch('sum', 2, 3));
    }

    public function test_listeners_run_in_priority_then_registration_order(): void
    {
        $events = new Dispatcher();
        $order  = [];

        $events->listen('e', function () use (&$order) { $order[] = 'default-first'; });       // priority 10
        $events->listen('e', function () use (&$order) { $order[] = 'low'; }, priority: 1);
        $events->listen('e', function () use (&$order) { $order[] = 'default-second'; });       // priority 10
        $events->listen('e', function () use (&$order) { $order[] = 'high'; }, priority: 99);

        $events->dispatch('e');

        self::assertSame(['low', 'default-first', 'default-second', 'high'], $order);
    }

    public function test_has_listeners(): void
    {
        $events = new Dispatcher();
        self::assertFalse($events->hasListeners('x'));
        $events->listen('x', fn () => null);
        self::assertTrue($events->hasListeners('x'));
    }

    public function test_filters_thread_value_through_chain(): void
    {
        $events = new Dispatcher();
        $events->addFilter('price', fn (float $p): float => $p * 2);
        $events->addFilter('price', fn (float $p): float => $p + 1);

        self::assertSame(11.0, $events->applyFilter('price', 5.0));
    }

    public function test_filter_receives_extra_args(): void
    {
        $events = new Dispatcher();
        $events->addFilter('greeting', fn (string $v, string $name): string => "{$v}, {$name}!");

        self::assertSame('Hello, Ada!', $events->applyFilter('greeting', 'Hello', 'Ada'));
    }

    public function test_listener_exception_propagates(): void
    {
        $events = new Dispatcher();
        $events->listen('boom', function (): void { throw new RuntimeException('fail'); });

        $this->expectException(RuntimeException::class);
        $events->dispatch('boom');
    }

    public function test_dispatch_with_no_listeners_is_empty(): void
    {
        self::assertSame([], (new Dispatcher())->dispatch('nothing'));
    }
}
