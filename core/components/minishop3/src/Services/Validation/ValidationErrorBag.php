<?php

namespace MiniShop3\Services\Validation;

/**
 * Field-level validation errors (Rakit-compatible shape for API consumers).
 */
class ValidationErrorBag
{
    /** @var array<string, array<string, string>> */
    protected array $messages = [];

    public function add(string $field, string $rule, string $message): void
    {
        if (!isset($this->messages[$field])) {
            $this->messages[$field] = [];
        }

        $this->messages[$field][$rule] = $message;
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->messages as $fieldMessages) {
            $total += count($fieldMessages);
        }

        return $total;
    }

    public function first(string $field): ?string
    {
        $fieldMessages = $this->messages[$field] ?? [];

        if ($fieldMessages === []) {
            return null;
        }

        return array_values($fieldMessages)[0];
    }

    /**
     * @return array<string, string>
     */
    public function firstOfAll(): array
    {
        $results = [];
        foreach ($this->messages as $field => $fieldMessages) {
            if ($fieldMessages === []) {
                continue;
            }
            $results[$field] = array_values($fieldMessages)[0];
        }

        return $results;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function toArray(): array
    {
        return $this->messages;
    }
}
