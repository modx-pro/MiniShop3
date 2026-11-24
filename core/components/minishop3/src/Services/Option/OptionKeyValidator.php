<?php

namespace MiniShop3\Services\Option;

use MiniShop3\Model\mysql\msOption as msOptionMeta;

/**
 * Option key rules from {@see msOptionMeta::$metaMap} validation (#841).
 *
 * Manager API used to skip xPDO validate() on create/update, so reserved keys
 * like `color` / `size` were saved and then silently shadowed by msProductData
 * columns in {@see \MiniShop3\Model\msProduct::get()}.
 */
final class OptionKeyValidator
{
    /**
     * @return string|null Lexicon key of the first failing rule, or null when the key is allowed
     */
    public static function lexiconErrorFor(string $key): ?string
    {
        $rules = msOptionMeta::$metaMap['validation']['rules']['key'] ?? [];
        if (!is_array($rules)) {
            return null;
        }

        foreach ($rules as $rule) {
            if (!is_array($rule) || ($rule['type'] ?? '') !== 'preg_match') {
                continue;
            }

            $pattern = (string) ($rule['rule'] ?? '');
            if ($pattern === '') {
                continue;
            }

            if (@preg_match($pattern, $key) !== 1) {
                $message = (string) ($rule['message'] ?? 'ms3_option_err_invalid_key');

                return $message !== '' ? $message : 'ms3_option_err_invalid_key';
            }
        }

        return null;
    }

    public static function isReserved(string $key): bool
    {
        return self::lexiconErrorFor($key) === 'ms3_option_err_reserved_key';
    }
}
