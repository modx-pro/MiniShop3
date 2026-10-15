<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Notifications;

use MiniShop3\Notifications\Channels\EmailChannel;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #888: теги из данных покупателя не должны доживать до парсера.
 *
 * Тело письма собирается Fenom с данными заказа, а потом прогоняется
 * парсером. С установленным pdoTools `$modx->getParser()` — это Fenom, а не
 * разбор MODX-тегов, поэтому из комментария покупателя исполнялись и
 * `[[++setting]]`, и `{$_modx->runSnippet(...)}`.
 */
final class EmailChannelTagHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
    }

    #[DataProvider('payloads')]
    public function testNeutralizedValueCarriesNoTagOpeners(string $payload): void
    {
        $channel = $this->makeChannel();
        $nonce = 'NONCE';

        $neutralized = $channel->probeNeutralize($payload, $nonce);

        self::assertIsString($neutralized);
        self::assertStringNotContainsString('[[', $neutralized);
        self::assertStringNotContainsString('{', $neutralized);
    }

    #[DataProvider('payloads')]
    public function testRestoreReturnsTheOriginalText(string $payload): void
    {
        $channel = $this->makeChannel();
        $nonce = $channel->probeNonce();

        $neutralized = $channel->probeNeutralize($payload, $nonce);

        self::assertSame($payload, $channel->probeRestore((string) $neutralized, $nonce));
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
     * Плейсхолдеры письма — вложенные массивы (order, address, customer,
     * products), обход должен доставать строки на любой глубине.
     */
    public function testNeutralizationGoesThroughNestedPlaceholders(): void
    {
        $channel = $this->makeChannel();
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

        $result = $channel->probeNeutralize($placeholders, $nonce);

        self::assertIsArray($result);
        self::assertSame('2610/1', $result['num']);
        self::assertStringNotContainsString('[[', $result['order']['order_comment']);
        self::assertStringNotContainsString('{', $result['address']['comment']);
        self::assertSame('Томск', $result['address']['city']);
        self::assertStringNotContainsString('{', $result['products'][0]['name']);
        // Нестроковые значения проходят как есть: иначе Fenom потерял бы тип.
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
        $channel = $this->makeChannel();

        self::assertNotSame($channel->probeNonce(), $channel->probeNonce());
    }

    /**
     * Fenom понимает только {$key}, поэтому темы вида «Заказ {num}» держатся
     * на плоской подстановке. Она идёт последней — после парсеров.
     */
    public function testFlatPlaceholdersFillOnlyBracedKeys(): void
    {
        $channel = $this->makeChannel();
        $placeholders = [
            'num' => '2610/1',
            'status_name' => 'Новый',
            'order' => ['num' => '2610/1'],
            'cost' => 7451.0,
        ];

        self::assertSame(
            'Заказ 2610/1 — Новый',
            $channel->probeFlat('Заказ {num} — {status_name}', $placeholders)
        );

        // Без скобок подстановка затронула бы любое вхождение имени ключа.
        self::assertSame('num и cost', $channel->probeFlat('num и cost', $placeholders));

        // Массивы плоской подстановке не поддаются — их разбирает Fenom.
        self::assertSame('{order}', $channel->probeFlat('{order}', $placeholders));

        self::assertSame('7451', $channel->probeFlat('{cost}', $placeholders));
    }

    /**
     * Подстановка идёт после парсеров, поэтому значение уже не шаблон: теги
     * из данных остаются текстом даже в теме.
     */
    public function testFlatPlaceholderValueIsNotReinterpreted(): void
    {
        $channel = $this->makeChannel();

        self::assertSame(
            'Заказ: {$_modx->runSnippet("evil")}',
            $channel->probeFlat('Заказ: {order_comment}', [
                'order_comment' => '{$_modx->runSnippet("evil")}',
            ])
        );
    }

    private function makeChannel(): EmailChannel
    {
        return new class ($this->createStub(modX::class)) extends EmailChannel {
            public function probeNonce(): string
            {
                return $this->tagNonce();
            }

            public function probeNeutralize(mixed $value, string $nonce): mixed
            {
                return $this->neutralizeTags($value, $nonce);
            }

            public function probeRestore(string $text, string $nonce): string
            {
                return $this->restoreTags($text, $nonce);
            }

            /**
             * @param array<string, mixed> $placeholders
             */
            public function probeFlat(string $subject, array $placeholders): string
            {
                return $this->fillFlatPlaceholders($subject, $placeholders);
            }
        };
    }
}
