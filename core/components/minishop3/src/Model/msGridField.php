<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msGridField
 * @package MiniShop3\Model
 *
 * Модель для хранения конфигурации колонок гридов (customers, orders, products и т.д.)
 *
 * @property int $id
 * @property string $grid_key Ключ грида (customers, orders, products)
 * @property string $field_name Имя поля
 * @property string|null $label Прямой label (переопределяет lexicon)
 * @property string|null $lexicon_key Ключ лексикона для label
 * @property bool $visible Видимость колонки
 * @property int $sort_order Порядок отображения
 * @property bool $sortable Можно ли сортировать
 * @property bool $filterable Можно ли фильтровать
 * @property bool $frozen Закреплена ли колонка (слева/справа)
 * @property string|null $width Ширина колонки (например: 150px, 20%)
 * @property string|null $min_width Минимальная ширина
 * @property array|null $config Дополнительная конфигурация (template, type, format)
 * @property bool $is_system Системное поле (нельзя удалить)
 * @property bool $is_default Дефолтное поле (из seed)
 * @property string $created_at
 * @property string $updated_at
 */
class msGridField extends xPDOSimpleObject
{
}
