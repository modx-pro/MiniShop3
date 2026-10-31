<?php

declare(strict_types=1);

/**
 * Минимальная заглушка MODX ResourceUpdateManagerController для unit-тестов
 * legacy-контроллеров карточки товара без установки MODX.
 *
 * Используется только в тестах: позволяют загрузить
 * controllers/product/update.class.php и проверить чистую логику подготовки
 * полей, не поднимая весь менеджер MODX.
 */
class msResourceUpdateController
{
    /** @var object */
    public $modx;

    /** @var array<string, mixed> */
    public array $resourceArray = [];

    /**
     * @param object $modx
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
    }
}
