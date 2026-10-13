<?php

declare(strict_types=1);

namespace BXCode\Events;

/**
 * Events, hooks, and filters in one predictable dispatcher.
 *
 * - Events / action hooks: listen() + dispatch(). Listeners run in priority
 *   order (lower first; registration order breaks ties) and receive the
 *   dispatched arguments. dispatch() returns each listener's return value.
 * - Filters: addFilter() + applyFilter(). A value is threaded through each
 *   filter, which returns the (possibly modified) value for the next.
 *
 * Listener exceptions propagate — failures are never silently swallowed. This
 * is the extension point a future CMS plugin system builds on, with no coupling
 * to CMS code.
 */
final class Dispatcher
{
    /** @var array<string,list<array{priority:int,seq:int,cb:callable}>> */
    private array $listeners = [];

    /** @var array<string,list<array{priority:int,seq:int,cb:callable}>> */
    private array $filters = [];

    private int $seq = 0;

    /** Register an event/hook listener. Lower priority runs first. */
    public function listen(string $event, callable $listener, int $priority = 10): void
    {
        $this->listeners[$event][] = ['priority' => $priority, 'seq' => $this->seq++, 'cb' => $listener];
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Fire an event, running every listener in order with $args. Returns the
     * list of listener return values.
     *
     * @return list<mixed>
     */
    public function dispatch(string $event, mixed ...$args): array
    {
        $results = [];
        foreach ($this->ordered($this->listeners[$event] ?? []) as $cb) {
            $results[] = $cb(...$args);
        }

        return $results;
    }

    /** Register a filter for $name. Lower priority runs first. */
    public function addFilter(string $name, callable $filter, int $priority = 10): void
    {
        $this->filters[$name][] = ['priority' => $priority, 'seq' => $this->seq++, 'cb' => $filter];
    }

    /**
     * Thread $value through every filter registered for $name. Each filter is
     * called as filter($value, ...$args) and returns the next value.
     */
    public function applyFilter(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->ordered($this->filters[$name] ?? []) as $cb) {
            $value = $cb($value, ...$args);
        }

        return $value;
    }

    /**
     * @param list<array{priority:int,seq:int,cb:callable}> $entries
     * @return list<callable>
     */
    private function ordered(array $entries): array
    {
        usort($entries, static function (array $a, array $b): int {
            return [$a['priority'], $a['seq']] <=> [$b['priority'], $b['seq']];
        });

        return array_map(static fn (array $e): callable => $e['cb'], $entries);
    }
}
