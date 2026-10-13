<?php

declare(strict_types=1);

namespace BXCode\View;

/**
 * Resolves and renders native PHP templates from a base directory. Dotted view
 * names map to subdirectories: "pages.home" -> pages/home.php.
 */
final class Factory
{
    public function __construct(
        private readonly string $viewPath,
        private readonly string $extension = '.php',
    ) {
    }

    /** Absolute filesystem path for a view name. */
    public function path(string $name): string
    {
        // Reject anything that could escape the view directory. "." is the
        // folder separator, so a valid name is letters, digits, _, -, and ".".
        if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.\-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid view name [{$name}].");
        }

        $relative = str_replace('.', '/', $name);

        return rtrim($this->viewPath, '/') . '/' . $relative . $this->extension;
    }

    public function exists(string $name): bool
    {
        return is_file($this->path($name));
    }

    /** @param array<string,mixed> $data */
    public function make(string $name, array $data = []): View
    {
        return new View($this, $name, $data);
    }

    /**
     * Render a view to a string, including any layout it extends.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        if (!$this->exists($name)) {
            throw new \RuntimeException("View [{$name}] not found at {$this->path($name)}.");
        }

        return (new TemplateRenderer($this))->render($name, $data);
    }
}
