<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msPageSection
 *
 * @property int $id
 * @property string $page_key
 * @property string $section_key
 * @property bool $hidden
 * @property int $sort_order
 * @property string $config
 * @property bool $is_default
 * @property string $created_at
 * @property string $updated_at
 *
 * @property msProductField[] $ProductFields Related product fields in this section
 *
 * @package MiniShop3\Model
 */
class msPageSection extends xPDOSimpleObject
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
