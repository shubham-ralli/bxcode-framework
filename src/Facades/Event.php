<?php

declare(strict_types=1);

namespace BXCode\Facades;

use BXCode\Application;
use BXCode\Events\Dispatcher;

/**
 * Static entry point to the event/hook/filter dispatcher.
 *
 *   Event::listen('user.registered', fn ($user) => Mail::welcome($user));
 *   Event::dispatch('user.registered', $user);
 *   $title = Event::applyFilter('post.title', $title);
 *
 * @method static void listen(string $event, callable $listener, int $priority = 10)
 * @method static bool hasListeners(string $event)
 * @method static array dispatch(string $event, mixed ...$args)
 * @method static void addFilter(string $name, callable $filter, int $priority = 10)
 * @method static mixed applyFilter(string $name, mixed $value, mixed ...$args)
 */
final class Event
{
    /** @param array<int,mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return Application::getInstance()->events()->{$name}(...$arguments);
    }

    public static function dispatcher(): Dispatcher
    {
        return Application::getInstance()->events();
    }
}
