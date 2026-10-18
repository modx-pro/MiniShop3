<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Notifications;

use MiniShop3\Notifications\TemplateTagGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #888/#929: теги из данных покупателя не доживают до парсера.
 *
 * Fenom подставляет значения как есть, а следующий парсер читает тело как
 * шаблон. С установленным pdoTools из комментария исполнялись и
 * `[[++setting]]`, и `{$_modx->runSnippet(...)}`.
 */
final class TemplateTagGuardTest extends TestCase
{
    #[DataProvider('payloads')]
    public function testNeutralizedValueCarriesNoTagOpeners(string $payload): void
    {
        $guard = new TemplateTagGuard();
        $nonce = 'NONCE';

        $neutralized = $guard->neutralize($payload, $nonce);

        self::assertIsString($neutralized);
        self::assertStringNotContainsString('[[', $neutralized);
        self::assertStringNotContainsString('{', $neutralized);
    }

    #[DataProvider('payloads')]
    public function testRestoreReturnsTheOriginalText(string $payload): void
    {
        $guard = new TemplateTagGuard();
        $nonce = $guard->nonce();

        $neutralized = $guard->neutralize($payload, $nonce);

        self::assertSame($payload, $guard->restore((string) $neutralized, $nonce));
    }

    /**
     * @return list<array{string}>
     */
    public static function payloads(): array
    {
        return [
            ['[[++emailsender]]'],
            ['[[!someSnippet? &a=`1`]]'],
            ['{$_modx->runSnippet("evil")}'],
            ['{"zz"|upper}'],
            ['{$order.num}'],
            ['обычный комментарий без тегов'],
            ['смешанный [[++site_name]] и {$num} в одной строке'],
        ];
    }

    /**
     * Плейсхолдеры — вложенные массивы (order, address, customer, products),
     * обход должен доставать строки на любой глубине.
     */
    public function testNeutralizationGoesThroughNestedPlaceholders(): void
    {
        $guard = new TemplateTagGuard();
        $nonce = 'NONCE';

        $placeholders = [
            'num' => '2610/1',
            'order' => ['order_comment' => '[[++emailsender]]'],
            'address' => ['comment' => '{$_modx->runSnippet("evil")}', 'city' => 'Томск'],
            'products' => [
                ['name' => 'Диван {$num}', 'count' => 2],
            ],
            'cost' => 7451.0,
            'flag' => true,
        ];

        $result = $guard->neutralize($placeholders, $nonce);

        self::assertIsArray($result);
        self::assertSame('2610/1', $result['num']);
        self::assertStringNotContainsString('[[', $result['order']['order_comment']);
        self::assertStringNotContainsString('{', $result['address']['comment']);
        self::assertSame('Томск', $result['address']['city']);
        self::assertStringNotContainsString('{', $result['products'][0]['name']);
        self::assertSame(2, $result['products'][0]['count']);
        self::assertSame(7451.0, $result['cost']);
        self::assertTrue($result['flag']);
    }

    /**
     * Метка разовая: с постоянной покупатель вписал бы её в комментарий и
     * получил обратно `[[` или `{` после разворачивания.
     */
    public function testNonceDiffersBetweenMessages(): void
    {
        $guard = new TemplateTagGuard();

        self::assertNotSame($guard->nonce(), $guard->nonce());
    }

    /**
     * Метка состоит только из цифр: модификатор шаблона вроде |upper не должен
     * её изменить, иначе разворачивание пройдёт мимо и читатель увидит маркер.
     */
    public function testNonceSurvivesCaseModifiers(): void
    {
        $guard = new TemplateTagGuard();
        $nonce = $guard->nonce();

        self::assertMatchesRegularExpression('/^\d+$/', $nonce);

        $neutralized = (string) $guard->neutralize('x[[++site_url]]y', $nonce);

        self::assertSame(
            mb_strtoupper('x[[++site_url]]y'),
            $guard->restore(mb_strtoupper($neutralized), $nonce)
        );
    }

    public function testEscapeHtmlLeavesNonStringsAndEscapesMarkup(): void
    {
        $guard = new TemplateTagGuard();

        $result = $guard->escapeHtml([
            'comment' => 'Coat <b class="x">',
            'nested' => ['city' => 'Томск'],
            'count' => 2,
            'cost' => 7451.0,
            'flag' => true,
        ]);

        self::assertIsArray($result);
        self::assertSame('Coat &lt;b class=&quot;x&quot;&gt;', $result['comment']);
        self::assertSame('Томск', $result['nested']['city']);
        self::assertSame(2, $result['count']);
        self::assertSame(7451.0, $result['cost']);
        self::assertTrue($result['flag']);
    }

    /**
     * HTML-экранирование не заменяет маскировку тегов: скобки остаются,
     * пока neutralize() их не спрячет. После restore тег снова текст,
     * а угловая скобка остаётся сущностью.
     */
    public function testHtmlEscapeThenNeutralizeKeepsEntities(): void
    {
        $guard = new TemplateTagGuard();
        $nonce = $guard->nonce();
        $payload = 'a < b [[++site_name]]';

        $neutralized = $guard->neutralize($guard->escapeHtml($payload), $nonce);

        self::assertIsString($neutralized);
        self::assertStringNotContainsString('[[', $neutralized);
        self::assertStringNotContainsString('<', $neutralized);

        self::assertSame(
            'a &lt; b [[++site_name]]',
            $guard->restore($neutralized, $nonce)
        );
    }
}
