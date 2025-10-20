<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msFieldConfigOverride
 *
 * Переопределения конфигурации полей для страниц
 *
 * @property int $id
 * @property string $page_key Ключ страницы (product_data, product_left, etc)
 * @property string $field_name Имя поля (article, price, etc)
 * @property bool $hidden Скрыто ли поле
 * @property int $sort_order Порядок сортировки
 * @property string $config JSON с дополнительными переопределениями
 * @property string $context_key Ключ контекста (web, mgr)
 * @property string $created_at Дата создания
 * @property string $updated_at Дата обновления
 *
 * @package MiniShop3\Model
 */
class msFieldConfigOverride extends xPDOSimpleObject
{
    /**
     * Получить конфигурацию как массив
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = $this->get('config');

        // xPDO автоматически декодирует JSON для phptype='json'
        if (is_array($config)) {
            return $config;
        }

        // Если по какой-то причине это строка, пытаемся декодировать
        if (is_string($config) && !empty($config)) {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Установить конфигурацию из массива
     *
     * @param array $config
     * @return bool
     */
    public function setConfig(array $config): bool
    {
        // xPDO автоматически кодирует массив в JSON для phptype='json'
        return $this->set('config', $config);
    }
}
