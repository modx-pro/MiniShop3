<?php

namespace MiniShop3\Services\Validation;

/**
 * Canonical input validation for MiniShop3 (pipe rules, Rakit-compatible API).
 *
 * Callers use this service instead of a third-party validator directly.
 */
class ValidationService
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages
     */
    public function make(array $inputs, array $rules, array $messages = []): ValidationResult
    {
        return new ValidationResult($inputs, $rules, $messages);
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages
     */
    public function validate(array $inputs, array $rules, array $messages = []): ValidationResult
    {
        $result = $this->make($inputs, $rules, $messages);
        $result->validate();

        return $result;
    }
}
