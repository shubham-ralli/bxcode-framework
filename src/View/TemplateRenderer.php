<?php

declare(strict_types=1);

namespace BXCode\View;

/**
 * Renders one view (and its layout, if any). Templates run with $this bound to
 * this renderer, so they call $this->e(), $this->section(), $this->insert(),
 * and $this->extend(). A fresh renderer is used per top-level render, so section
 * and layout state never leak between unrelated views.
 *
 * In a template:
 *
 *   <?php $this->extend('layouts.app') ?>
 *   <?php $this->section('content') ?>
 *       <h1><?= $this->e($title) ?></h1>
 *       <?php $this->insert('partials.footer') ?>
 *   <?php $this->endSection() ?>
 *
 * And in the layout: <?= $this->yieldSection('content') ?>
 */
final class TemplateRenderer
{
    /** @var array<string,string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    private ?string $layout = null;

    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(private readonly Factory $factory)
    {
    }

    /** @param array<string,mixed> $data */
    public function render(string $name, array $data): string
    {
        $this->data = $data;
        $content    = $this->evaluate($this->factory->path($name), $data);

        if ($this->layout === null) {
            return $content;
        }

        // The child's loose output becomes "content" unless it defined sections.
        if (!isset($this->sections['content'])) {
            $this->sections['content'] = $content;
        }

        $layout       = $this->layout;
        $this->layout = null;

        return $this->evaluate($this->factory->path($layout), $data);
    }

    /** Declare the layout this view extends. */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /** Begin capturing a named section. */
    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /** Finish the current section. */
    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('endSection() called without a matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    /** Output a captured section (used in layouts). */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** Render a partial inline. Partials get their own isolated renderer. */
    public function insert(string $name, array $data = []): void
    {
        echo (new self($this->factory))->render($name, [...$this->data, ...$data]);
    }

    /** Escape a value for safe HTML output. */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** @param array<string,mixed> $__data */
    private function evaluate(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException("View file not found: {$__file}.");
        }

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
