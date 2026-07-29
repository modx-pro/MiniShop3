<?php

namespace MiniShop3\Services\Validation;

/**
 * Result of validating input against pipe-delimited rules.
 */
class ValidationResult
{
    protected ValidationErrorBag $errors;

    public function __construct(
        protected array $inputs,
        protected array $rules,
        protected array $messages = [],
    ) {
        $this->errors = new ValidationErrorBag();
    }

    public function validate(): void
    {
        $this->errors = new ValidationErrorBag();
        $validator = new PipeRuleValidator($this->messages);

        foreach ($this->rules as $field => $ruleString) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $value = $this->inputs[$field] ?? null;
            $parsedRules = PipeRuleValidator::parseRules((string) $ruleString);

            foreach ($validator->validateField($field, $value, $parsedRules, $this->inputs) as $rule => $message) {
                $this->errors->add($field, $rule, $message);
            }
        }
    }

    public function passes(): bool
    {
        return $this->errors->count() === 0;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): ValidationErrorBag
    {
        return $this->errors;
    }
}
