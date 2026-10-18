<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Notifications;

use MiniShop3\Notifications\Channels\EmailChannel;
use MiniShop3\Notifications\TemplateTagGuard;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #888: плоские {key} в теме письма маскируются до парсера.
 *
 * Fenom понимает только {$key}. Тема «Заказ {num}» спотыкается при компиляции,
 * а pdoTools на ошибке отдаёт пустую строку. Ключи маскируются в шаблоне и
 * разворачиваются после парсеров, до restore().
 */
final class EmailChannelTagHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    /**
     * Fenom понимает только {$key}, поэтому темы вида «Заказ {num}» держатся
     * на подстановке плоских ключей. Fenom при этом спотыкается на {num} при
     * компиляции, а pdoTools на ошибке отдаёт пустую строку — поэтому ключи
     * маскируются в шаблоне и разворачиваются после парсеров.
     */
    public function testFlatKeysAreMaskedInTheTemplate(): void
    {
        $channel = $this->makeChannel();
        $nonce = (new TemplateTagGuard())->nonce();
        $placeholders = [
            'num' => '2610/1',
            'status_name' => 'Новый',
            'order' => ['num' => '2610/1'],
            'cost' => 7451.0,
        ];

        [$masked, $values] = $channel->probeMask('Заказ {num} — {status_name}', $placeholders, $nonce);

        self::assertStringNotContainsString('{', $masked);
        self::assertCount(2, $values);
        self::assertSame('Заказ 2610/1 — Новый', strtr($masked, $values));

        [$maskedArray, $arrayValues] = $channel->probeMask('{order}', $placeholders, $nonce);
        self::assertSame('{order}', $maskedArray);
        self::assertSame([], $arrayValues);

        [$maskedFenom] = $channel->probeMask('Заказ {$order.num}', $placeholders, $nonce);
        self::assertSame('Заказ {$order.num}', $maskedFenom);

        [$maskedBare, $bareValues] = $channel->probeMask('num и cost', $placeholders, $nonce);
        self::assertSame('num и cost', $maskedBare);
        self::assertSame([], $bareValues);
    }

    /**
     * Значение разворачивается после парсеров, поэтому шаблоном уже не станет.
     */
    public function testFlatValueIsNotReinterpreted(): void
    {
        $channel = $this->makeChannel();
        $nonce = (new TemplateTagGuard())->nonce();

        [$masked, $values] = $channel->probeMask('Заказ: {order_comment}', [
            'order_comment' => '{$_modx->runSnippet("evil")}',
        ], $nonce);

        self::assertSame('Заказ: {$_modx->runSnippet("evil")}', strtr($masked, $values));
    }

    /**
     * Маркер плоского ключа заканчивается меткой, поэтому стоящая сразу за ним
     * цифра из открывателей складывалась с ней в то, что ищет restore():
     * тема «Заказ {num}1 шт» уезжала с мусором вместо номера. Значения
     * разворачиваются до восстановления последовательностей — тогда висячей
     * метки в строке не остаётся.
     *
     * @param string $suffix текст сразу за плоским ключом
     */
    #[DataProvider('adjacentSuffixes')]
    public function testFlatMarkerSurvivesAdjacentDigits(string $suffix): void
    {
        $channel = $this->makeChannel();
        $guard = new TemplateTagGuard();
        $nonce = $guard->nonce();

        [$masked, $values] = $channel->probeMask(
            'Заказ {num}' . $suffix,
            ['num' => '2610/1'],
            $nonce
        );

        $result = $guard->restore(strtr($masked, $values), $nonce);

        self::assertSame('Заказ 2610/1' . $suffix, $result);
        self::assertStringNotContainsString($nonce, $result);
    }

    /**
     * @return list<array{string}>
     */
    public static function adjacentSuffixes(): array
    {
        return [
            ['1 шт'],
            ['2026'],
            ['3 шт'],
            [' 1 шт'],
            [''],
            ['12'],
            ['21'],
        ];
    }

    /**
     * strtr не идёт по только что вставленному тексту, поэтому значение с
     * {другим_ключом} внутри остаётся как есть.
     */
    public function testFlatValueContainingAnotherKeyIsNotExpanded(): void
    {
        $channel = $this->makeChannel();
        $nonce = (new TemplateTagGuard())->nonce();

        [$masked, $values] = $channel->probeMask('{order_comment} / {num}', [
            'order_comment' => 'смотрите {num}',
            'num' => '2610/1',
        ], $nonce);

        self::assertSame('смотрите {num} / 2610/1', strtr($masked, $values));
    }

    private function makeChannel(): EmailChannel
    {
        return new class ($this->createStub(modX::class)) extends EmailChannel {
            /**
             * @param array<string, mixed> $placeholders
             * @return array{0: string, 1: array<string, string>}
             */
            public function probeMask(string $subject, array $placeholders, string $nonce): array
            {
                return $this->maskFlatPlaceholders($subject, $placeholders, $nonce);
            }
        };
    }
}
