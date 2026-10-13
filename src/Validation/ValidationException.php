<?php

declare(strict_types=1);

namespace BXCode\Validation;

use RuntimeException;

/**
 * Thrown when validation fails. Carries per-field error messages and maps to a
 * 422 response. The central exception handler renders it as JSON (for API
 * requests) or can be caught to redirect back with the errors.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string,list<string>> $errors field => messages */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    public int $status = 422;

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** The first error message for each field. @return array<string,string> */
    public function first(): array
    {
        return array_map(static fn (array $messages): string => $messages[0], $this->errors);
    }
}
