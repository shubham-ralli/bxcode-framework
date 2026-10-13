<?php

declare(strict_types=1);

namespace BXCode\View;

use Stringable;

/**
 * A renderable view. It is Stringable, so returning one from a route handler
 * yields an HTML response (the Router casts it to a string). Rendering is
 * deferred until the view is converted to a string.
 */
final class View implements Stringable
{
    /** @param array<string,mixed> $data */
    public function __construct(
        private readonly Factory $factory,
        private readonly string $name,
        private array $data = [],
    ) {
    }

    /** Add or override view data fluently. */
    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function render(): string
    {
        return $this->factory->render($this->name, $this->data);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
