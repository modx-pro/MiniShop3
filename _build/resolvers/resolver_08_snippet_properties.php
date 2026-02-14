<?php

use MODX\Revolution\modCategory;
use MODX\Revolution\modSnippet;
use xPDO\Transport\xPDOTransport;

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var \MODX\Revolution\modX $modx */

if (!$transport->xpdo) {
    return true;
}

$modx = $transport->xpdo;

/**
 * Converts camelCase to snake_case for lexicon keys (same as in build.php).
 */
$camelToSnake = static function (string $str): string {
    return strtolower(preg_replace('/([A-Z])/', '_$1', $str));
};

switch ($options[xPDOTransport::PACKAGE_ACTION] ?? null) {
    case xPDOTransport::ACTION_UPGRADE:
        $category = $modx->getObject(modCategory::class, ['category' => 'MiniShop3']);
        if (!$category) {
            break;
        }

        $snippets = $modx->getCollection(modSnippet::class, ['category' => $category->get('id')]);
        foreach ($snippets as $snippet) {
            /** @var modSnippet $snippet */
            $properties = $snippet->get('properties');
            if (!is_array($properties)) {
                continue;
            }

            $changed = false;
            foreach ($properties as $propName => $propDef) {
                if (!is_array($propDef)) {
                    continue;
                }
                $propKey = $propName === 'includeTVs' ? 'include_tvs' : $camelToSnake($propName);
                $newDesc = 'ms3_prop_' . $propKey;
                $currentDesc = $propDef['desc'] ?? '';
                if ($currentDesc !== $newDesc) {
                    $properties[$propName]['desc'] = $newDesc;
                    $changed = true;
                }
            }

            if ($changed) {
                $snippet->set('properties', $properties);
                $snippet->save();
            }
        }
        break;
}

return true;
