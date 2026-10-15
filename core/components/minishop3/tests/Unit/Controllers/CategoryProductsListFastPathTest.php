<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers;

use MiniShop3\Controllers\Api\Manager\CategoryProductsController;
use MODX\Revolution\modAccessResourceGroup;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Быстрый путь листинга товаров категории (регрессия производительности #473).
 *
 * #473 добавил ACL уровня документа и ради него заменил LIMIT/OFFSET
 * сканированием: на каждый запрос страницы вычитывалась вся категория.
 * Быстрый путь возвращает один SELECT с LIMIT и один COUNT, но только когда
 * построчная проверка заведомо никого не отбрасывает.
 *
 * Здесь проверяется именно условие переключения: ошибка в нём либо вернёт
 * медленный листинг, либо, что хуже, обойдёт проверку прав.
 */
final class CategoryProductsListFastPathTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    public function testInertWhenNoResourceGroupAclExists(): void
    {
        // Типичный сайт: группы ресурсов не настроены — отбрасывать нечего.
        self::assertTrue($this->isInert(aclRows: 0, groupsEnabled: true));
    }

    public function testNotInertWhenResourceGroupAclExists(): void
    {
        // Появилась хоть одна запись доступа — возвращаемся к проверке прав.
        self::assertFalse($this->isInert(aclRows: 1, groupsEnabled: true));
        self::assertFalse($this->isInert(aclRows: 25, groupsEnabled: true));
    }

    public function testInertWhenResourceGroupAccessDisabledInContext(): void
    {
        // modResource::findPolicy() при выключенной настройке отдаёт пустую
        // политику, то есть ограничений нет даже при наличии записей.
        self::assertTrue($this->isInert(aclRows: 5, groupsEnabled: false));
    }

    /**
     * Результат кэшируется на запрос: условие дёргается один раз на листинг.
     */
    public function testResultIsMemoisedPerRequest(): void
    {
        $modx = $this->makeModx(aclRows: 0, groupsEnabled: true);
        $controller = new CategoryProductsController($modx);
        $method = new ReflectionMethod($controller, 'documentAclIsInert');
        $method->setAccessible(true);

        self::assertTrue($method->invoke($controller));

        // Меняем ответ хранилища: закэшированное решение не должно поехать.
        $modx->counts[modAccessResourceGroup::class] = 3;

        self::assertTrue($method->invoke($controller));
    }

    private function isInert(int $aclRows, bool $groupsEnabled): bool
    {
        $controller = new CategoryProductsController($this->makeModx($aclRows, $groupsEnabled));
        $method = new ReflectionMethod($controller, 'documentAclIsInert');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller);
    }

    private function makeModx(int $aclRows, bool $groupsEnabled): modX
    {
        $modx = new modX();
        $modx->optionOverrides['access_resource_group_enabled'] = $groupsEnabled ? '1' : '0';
        $modx->counts[modAccessResourceGroup::class] = $aclRows;

        return $modx;
    }
}
