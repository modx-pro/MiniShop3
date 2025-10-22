<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msProductField
 *
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property string $xtype
 * @property string|null $section
 * @property bool $visible
 * @property bool $required
 * @property int $sort_order
 * @property int $width
 * @property string|null $description
 * @property array|null $config
 * @property bool $is_system
 * @property bool $is_default
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @package MiniShop3\Model
 */
class msProductField extends xPDOSimpleObject
{
    /**
     * Получить конфигурацию из JSON
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = $this->get('config');
        if (empty($config)) {
            return [];
        }

        // xPDO 3 с phptype='json' может вернуть массив или строку
        if (is_array($config)) {
            return $config;
        }

        $decoded = json_decode($config, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Установить конфигурацию (автоматически конвертирует в JSON)
     *
     * @param array $config
     * @return bool
     */
    public function setConfig(array $config): bool
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        return $this->set('config', $json);
    }
}
