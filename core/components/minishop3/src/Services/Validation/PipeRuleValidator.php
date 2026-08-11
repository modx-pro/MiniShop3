<?php

namespace MiniShop3\Services\Validation;

/**
 * Validates a single field against Rakit-style pipe rules (required|min:2|email).
 */
class PipeRuleValidator
{
    /** @var array<string, string> */
    private array $messages;

    /** @var array<string, string> */
    private const DEFAULT_MESSAGES = [
        'required' => 'The :attribute is required',
        'email' => 'The :attribute is not valid email',
        'numeric' => 'The :attribute must be numeric',
        'integer' => 'The :attribute must be integer',
        'min' => 'The :attribute minimum is :min',
        'max' => 'The :attribute maximum is :max',
        'between' => 'The :attribute must be between :min and :max',
        'url' => 'The :attribute must be valid url',
        'alpha' => 'The :attribute must be alphabetic',
        'alpha_num' => 'The :attribute must be alphanumeric',
        'alpha_dash' => 'The :attribute must be alpha dash',
        'alpha_spaces' => 'The :attribute must be alpha spaces',
        'boolean' => 'The :attribute must be boolean',
        'json' => 'The :attribute must be valid json',
        'array' => 'The :attribute must be array',
        'in' => 'The :attribute must be in list',
        'not_in' => 'The :attribute must not be in list',
        'regex' => 'The :attribute format is invalid',
        'digits' => 'The :attribute must be :digits digits',
        'same' => 'The :attribute must match :field',
        'different' => 'The :attribute must differ from :field',
        'date' => 'The :attribute must be valid date',
        'ip' => 'The :attribute must be valid ip',
        'ipv4' => 'The :attribute must be valid ipv4',
        'ipv6' => 'The :attribute must be valid ipv6',
        'accepted' => 'The :attribute must be accepted',
        'present' => 'The :attribute must be present',
        'uppercase' => 'The :attribute must be uppercase',
        'lowercase' => 'The :attribute must be lowercase',
        'extension' => 'The :attribute must have valid extension',
        'mimes' => 'The :attribute must have valid mime type',
        'unsupported' => 'The :attribute has unsupported validation rule',
    ];

    /** @var list<string> */
    private const IMPLICIT_RULES = [
        'required',
        'required_if',
        'required_unless',
        'required_with',
        'required_without',
        'required_with_all',
        'required_without_all',
        'accepted',
        'present',
    ];

    /**
     * @param array<string, string> $messages
     */
    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * @return list<array{name: string, params: list<string>}>
     */
    public static function parseRules(string $ruleString): array
    {
        $parts = array_map('trim', explode('|', $ruleString));
        $parsed = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_contains($part, ':')) {
                [$name, $paramString] = explode(':', $part, 2);
                if ($name === 'regex') {
                    $parsed[] = ['name' => $name, 'params' => [$paramString]];
                } else {
                    $params = array_map('trim', explode(',', $paramString));
                    $parsed[] = ['name' => $name, 'params' => $params];
                }
            } else {
                $parsed[] = ['name' => $part, 'params' => []];
            }
        }

        return $parsed;
    }

    /**
     * @param list<array{name: string, params: list<string>}> $rules
     * @param array<string, mixed> $allInputs
     * @return array<string, string> rule name => message
     */
    public function validateField(string $field, mixed $value, array $rules, array $allInputs): array
    {
        $errors = [];
        $ruleNames = array_column($rules, 'name');
        $isEmpty = $this->isEmpty($value);

        if (in_array('nullable', $ruleNames, true) && $isEmpty) {
            return [];
        }

        $hasNumericRule = $this->hasAnyRule($ruleNames, ['numeric', 'integer']);

        foreach ($rules as $rule) {
            $name = $rule['name'];
            $params = $rule['params'];

            if ($name === 'nullable') {
                continue;
            }

            if ($isEmpty && !$this->isImplicitRule($name) && $name !== 'required') {
                continue;
            }

            if (!$this->checkRule($name, $value, $params, $field, $allInputs, $hasNumericRule)) {
                $messageRule = isset(self::DEFAULT_MESSAGES[$name]) || isset($this->messages[$name])
                    ? $name
                    : 'unsupported';
                $errors[$messageRule] = $this->resolveMessage($messageRule, $field, $params);
                if ($this->isImplicitRule($name)) {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $ruleNames
     * @param list<string> $needles
     */
    private function hasAnyRule(array $ruleNames, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $ruleNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function isImplicitRule(string $name): bool
    {
        return in_array($name, self::IMPLICIT_RULES, true)
            || str_starts_with($name, 'required_');
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return mb_strlen(trim($value), 'UTF-8') === 0;
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRule(
        string $name,
        mixed $value,
        array $params,
        string $field,
        array $allInputs,
        bool $hasNumericRule,
    ): bool {
        return match ($name) {
            'required' => !$this->isEmpty($value),
            'present' => array_key_exists($field, $allInputs),
            'accepted' => in_array(strtolower((string) $value), ['yes', 'on', '1', 'true'], true),
            'nullable' => true,
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var((string) $value, FILTER_VALIDATE_URL) !== false,
            'numeric' => is_numeric($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean' => is_bool($value) || in_array(strtolower((string) $value), ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'], true),
            'alpha' => (bool) preg_match('/^\p{L}+$/u', (string) $value),
            'alpha_num' => (bool) preg_match('/^[\p{L}\p{N}]+$/u', (string) $value),
            'alpha_dash' => (bool) preg_match('/^[\p{L}\p{N}_-]+$/u', (string) $value),
            'alpha_spaces' => (bool) preg_match('/^[\p{L}\s]+$/u', (string) $value),
            'uppercase' => (string) $value === mb_strtoupper((string) $value, 'UTF-8'),
            'lowercase' => (string) $value === mb_strtolower((string) $value, 'UTF-8'),
            'json' => $this->isValidJson($value),
            'array' => is_array($value),
            'ip' => filter_var((string) $value, FILTER_VALIDATE_IP) !== false,
            'ipv4' => filter_var((string) $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => filter_var((string) $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'min' => $this->checkMin($value, $params[0] ?? '0', $hasNumericRule),
            'max' => $this->checkMax($value, $params[0] ?? '0', $hasNumericRule),
            'between' => $this->checkBetween($value, $params, $hasNumericRule),
            'digits' => $this->checkDigits($value, (int) ($params[0] ?? -1)),
            'digits_between' => $this->checkDigitsBetween($value, $params),
            'in' => in_array((string) $value, $params, true),
            'not_in' => !in_array((string) $value, $params, true),
            'same' => isset($allInputs[$params[0] ?? '']) && (string) $value === (string) $allInputs[$params[0]],
            'different' => !isset($allInputs[$params[0] ?? '']) || (string) $value !== (string) $allInputs[$params[0]],
            'regex' => $this->checkRegex($value, $params[0] ?? ''),
            'date' => $this->checkDate($value, $params[0] ?? null),
            'after' => $this->checkDateCompare($value, $params[0] ?? '', $allInputs, fn ($a, $b) => $a > $b),
            'before' => $this->checkDateCompare($value, $params[0] ?? '', $allInputs, fn ($a, $b) => $a < $b),
            'required_if' => $this->checkRequiredIf($value, $params, $allInputs),
            'required_unless' => $this->checkRequiredUnless($value, $params, $allInputs),
            'required_with' => $this->checkRequiredWith($value, $params, $allInputs),
            'required_without' => $this->checkRequiredWithout($value, $params, $allInputs),
            'required_with_all' => $this->checkRequiredWithAll($value, $params, $allInputs),
            'required_without_all' => $this->checkRequiredWithoutAll($value, $params, $allInputs),
            'extension' => $this->checkExtension($value, $params),
            'mimes' => $this->checkMimes($value, $params),
            default => false,
        };
    }

    private function checkMin(mixed $value, string $minParam, bool $hasNumericRule): bool
    {
        $min = (float) $minParam;
        $size = $this->getValueSize($value, $hasNumericRule);

        return $size !== false && $size >= $min;
    }

    private function checkMax(mixed $value, string $maxParam, bool $hasNumericRule): bool
    {
        $max = (float) $maxParam;
        $size = $this->getValueSize($value, $hasNumericRule);

        return $size !== false && $size <= $max;
    }

    /**
     * @param list<string> $params
     */
    private function checkBetween(mixed $value, array $params, bool $hasNumericRule): bool
    {
        if (count($params) < 2) {
            return false;
        }

        $size = $this->getValueSize($value, $hasNumericRule);

        return $size !== false && $size >= (float) $params[0] && $size <= (float) $params[1];
    }

    private function getValueSize(mixed $value, bool $hasNumericRule): float|false
    {
        if ($hasNumericRule && is_numeric($value)) {
            return (float) $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) mb_strlen($value, 'UTF-8');
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        return false;
    }

    private function checkDigits(mixed $value, int $length): bool
    {
        $stringValue = (string) $value;

        return !preg_match('/[^0-9]/', $stringValue) && strlen($stringValue) === $length;
    }

    private function checkDigitsBetween(mixed $value, array $params): bool
    {
        if (count($params) < 2) {
            return false;
        }

        $stringValue = (string) $value;
        if (preg_match('/[^0-9]/', $stringValue)) {
            return false;
        }

        $length = strlen($stringValue);
        $min = (int) $params[0];
        $max = (int) $params[1];

        return $length >= $min && $length <= $max;
    }

    private function checkRegex(mixed $value, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $delimiter = $pattern[0];
        if (strlen($pattern) > 2 && ($pattern[strlen($pattern) - 1] ?? '') === $delimiter) {
            return @preg_match($pattern, (string) $value) === 1;
        }

        return @preg_match('/' . str_replace('/', '\/', $pattern) . '/u', (string) $value) === 1;
    }

    private function isValidJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function checkDate(mixed $value, ?string $format): bool
    {
        if ($format === null || $format === '') {
            return strtotime((string) $value) !== false;
        }

        $date = \DateTimeImmutable::createFromFormat($format, (string) $value);

        return $date !== false && $date->format($format) === (string) $value;
    }

    /**
     * @param array<string, mixed> $allInputs
     * @param callable(int, int): bool $compare
     */
    private function checkDateCompare(mixed $value, string $other, array $allInputs, callable $compare): bool
    {
        $valueTs = strtotime((string) $value);
        if ($valueTs === false) {
            return false;
        }

        $otherValue = array_key_exists($other, $allInputs) ? (string) $allInputs[$other] : $other;
        $otherTs = strtotime($otherValue);
        if ($otherTs === false) {
            return false;
        }

        return $compare($valueTs, $otherTs);
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredIf(mixed $value, array $params, array $allInputs): bool
    {
        if ($params === []) {
            return true;
        }

        $otherField = array_shift($params);
        $otherValue = $allInputs[$otherField] ?? null;

        if (in_array((string) $otherValue, $params, true)) {
            return !$this->isEmpty($value);
        }

        return true;
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredUnless(mixed $value, array $params, array $allInputs): bool
    {
        if ($params === []) {
            return true;
        }

        $otherField = array_shift($params);
        $otherValue = $allInputs[$otherField] ?? null;

        if (!in_array((string) $otherValue, $params, true)) {
            return !$this->isEmpty($value);
        }

        return true;
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredWith(mixed $value, array $params, array $allInputs): bool
    {
        foreach ($params as $otherField) {
            if (!$this->isEmpty($allInputs[$otherField] ?? null)) {
                return !$this->isEmpty($value);
            }
        }

        return true;
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredWithout(mixed $value, array $params, array $allInputs): bool
    {
        foreach ($params as $otherField) {
            if ($this->isEmpty($allInputs[$otherField] ?? null)) {
                return !$this->isEmpty($value);
            }
        }

        return true;
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredWithAll(mixed $value, array $params, array $allInputs): bool
    {
        if ($params === []) {
            return true;
        }

        foreach ($params as $otherField) {
            if ($this->isEmpty($allInputs[$otherField] ?? null)) {
                return true;
            }
        }

        return !$this->isEmpty($value);
    }

    /**
     * @param list<string> $params
     * @param array<string, mixed> $allInputs
     */
    private function checkRequiredWithoutAll(mixed $value, array $params, array $allInputs): bool
    {
        if ($params === []) {
            return true;
        }

        foreach ($params as $otherField) {
            if (!$this->isEmpty($allInputs[$otherField] ?? null)) {
                return true;
            }
        }

        return !$this->isEmpty($value);
    }

    /**
     * @param list<string> $params
     */
    private function checkExtension(mixed $value, array $params): bool
    {
        if ($params === [] || $this->isEmpty($value)) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));
        $allowed = array_map(static fn (string $item): string => strtolower(ltrim($item, '.')), $params);

        return $extension !== '' && in_array($extension, $allowed, true);
    }

    /**
     * @param list<string> $params
     */
    private function checkMimes(mixed $value, array $params): bool
    {
        if ($params === [] || $this->isEmpty($value)) {
            return true;
        }

        if (is_array($value) && isset($value['type'])) {
            return in_array(strtolower((string) $value['type']), array_map('strtolower', $params), true);
        }

        return $this->checkExtension($value, $params);
    }

    /**
     * @param list<string> $params
     */
    private function resolveMessage(string $rule, string $field, array $params): string
    {
        $template = $this->messages[$rule]
            ?? self::DEFAULT_MESSAGES[$rule]
            ?? 'The :attribute is invalid';

        $replacements = [
            ':attribute' => $field,
            ':field' => $params[0] ?? '',
            ':min' => $params[0] ?? '',
            ':max' => $params[1] ?? ($params[0] ?? ''),
            ':digits' => $params[0] ?? '',
        ];

        foreach ($replacements as $placeholder => $replacement) {
            $template = str_replace($placeholder, (string) $replacement, $template);
        }

        return $template;
    }
}
