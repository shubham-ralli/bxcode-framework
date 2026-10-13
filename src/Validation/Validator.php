<?php

declare(strict_types=1);

namespace BXCode\Validation;

use Closure;

/**
 * Rule-string validator.
 *
 *   $validated = (new Validator())->validate($request->all(), [
 *       'name'  => 'required|string|max:100',
 *       'email' => 'required|email',
 *       'age'   => 'integer|between:18,120',
 *   ]);
 *
 * Rules are a "|"-separated string; parameters follow a ":" and are comma
 * separated (e.g. between:1,10, in:a,b,c). On failure a ValidationException is
 * thrown with per-field messages. On success the validated fields are returned.
 *
 * A field that is absent/empty and not "required" skips its remaining rules, so
 * optional fields validate only when provided.
 */
final class Validator
{
    /** @var array<string,Closure(mixed,list<string>):bool> */
    private array $custom = [];

    /** @var array<string,string> name => message template with {field}/{param} tokens. */
    private array $messages = [
        'required' => 'The {field} field is required.',
        'string'   => 'The {field} must be a string.',
        'integer'  => 'The {field} must be an integer.',
        'numeric'  => 'The {field} must be a number.',
        'boolean'  => 'The {field} must be true or false.',
        'email'    => 'The {field} must be a valid email address.',
        'url'      => 'The {field} must be a valid URL.',
        'date'     => 'The {field} must be a valid date.',
        'array'    => 'The {field} must be an array.',
        'max'      => 'The {field} may not be greater than {p0}.',
        'min'      => 'The {field} must be at least {p0}.',
        'between'  => 'The {field} must be between {p0} and {p1}.',
        'in'       => 'The {field} is invalid.',
    ];

    /**
     * Register a custom rule. The callback receives the value and its params and
     * returns true when valid.
     *
     * @param Closure(mixed,list<string>):bool $callback
     */
    public function extend(string $name, Closure $callback, ?string $message = null): self
    {
        $this->custom[$name] = $callback;
        $this->messages[$name] = $message ?? 'The {field} is invalid.';

        return $this;
    }

    /**
     * Validate $data against $rules. Returns the validated fields, or throws.
     *
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules field => "rule|rule:param"
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public function validate(array $data, array $rules): array
    {
        $errors    = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $parsed  = $this->parse($ruleString);
            $value   = $data[$field] ?? null;
            $present = array_key_exists($field, $data) && !$this->isEmpty($value);

            if (!$present) {
                if (isset($parsed['required'])) {
                    $errors[$field][] = $this->message('required', $field, []);
                }
                continue; // optional + absent: nothing else to check
            }

            foreach ($parsed as $rule => $params) {
                if (!$this->passes($rule, $value, $params)) {
                    $errors[$field][] = $this->message($rule, $field, $params);
                }
            }

            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    /** @return array<string,list<string>> rule => params */
    private function parse(string $ruleString): array
    {
        $rules = [];
        foreach (explode('|', $ruleString) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            [$name, $paramStr] = array_pad(explode(':', $segment, 2), 2, '');
            $rules[$name] = $paramStr === '' ? [] : array_map('trim', explode(',', $paramStr));
        }

        return $rules;
    }

    /** @param list<string> $params */
    private function passes(string $rule, mixed $value, array $params): bool
    {
        if (isset($this->custom[$rule])) {
            return ($this->custom[$rule])($value, $params);
        }

        return match ($rule) {
            'required' => !$this->isEmpty($value),
            'string'   => is_string($value),
            'integer'  => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'numeric'  => is_numeric($value),
            'boolean'  => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true),
            'email'    => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'      => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date'     => is_string($value) && strtotime($value) !== false,
            'array'    => is_array($value),
            'max'      => $this->size($value) <= (float) ($params[0] ?? 0),
            'min'      => $this->size($value) >= (float) ($params[0] ?? 0),
            'between'  => $this->size($value) >= (float) ($params[0] ?? 0) && $this->size($value) <= (float) ($params[1] ?? 0),
            'in'       => in_array((string) $value, $params, true),
            default    => throw new \InvalidArgumentException("Unknown validation rule [{$rule}]."),
        };
    }

    /** Numeric value → itself; string → length; array → count. */
    private function size(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return (float) count($value);
        }

        return (float) mb_strlen((string) $value);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @param list<string> $params */
    private function message(string $rule, string $field, array $params): string
    {
        $message = $this->messages[$rule] ?? 'The {field} is invalid.';
        $message = str_replace('{field}', str_replace('_', ' ', $field), $message);

        foreach ($params as $i => $param) {
            $message = str_replace('{p' . $i . '}', $param, $message);
        }

        return $message;
    }
}
