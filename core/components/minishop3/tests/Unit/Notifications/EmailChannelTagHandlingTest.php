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
     * Метка состоит только из цифр: модификатор шаблона вроде |upper не должен
     * её изменить, иначе разворачивание пройдёт мимо и читатель увидит маркер.
     */
    public function testNonceSurvivesCaseModifiers(): void
    {
        $channel = $this->makeChannel();
        $nonce = $channel->probeNonce();

        self::assertMatchesRegularExpression('/^\d+$/', $nonce);

        $neutralized = (string) $channel->probeNeutralize('x[[++site_url]]y', $nonce);

        self::assertSame(
            mb_strtoupper('x[[++site_url]]y'),
            $channel->probeRestore(mb_strtoupper($neutralized), $nonce)
        );
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
        $nonce = $channel->probeNonce();
        $placeholders = [
            'num' => '2610/1',
            'status_name' => 'Новый',
            'order' => ['num' => '2610/1'],
            'cost' => 7451.0,
        ];

        [$masked, $values] = $channel->probeMask('Заказ {num} — {status_name}', $placeholders, $nonce);

        // В шаблоне не осталось фигурных скобок — Fenom не на чем спотыкаться.
        self::assertStringNotContainsString('{', $masked);
        self::assertCount(2, $values);
        self::assertSame('Заказ 2610/1 — Новый', strtr($masked, $values));

        // Массивы не маскируем: {order} разбирает Fenom.
        [$maskedArray, $arrayValues] = $channel->probeMask('{order}', $placeholders, $nonce);
        self::assertSame('{order}', $maskedArray);
        self::assertSame([], $arrayValues);

        // Fenom-форма остаётся Fenom-форме.
        [$maskedFenom] = $channel->probeMask('Заказ {$order.num}', $placeholders, $nonce);
        self::assertSame('Заказ {$order.num}', $maskedFenom);

        // Без скобок подстановка затронула бы любое вхождение имени ключа.
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
        $nonce = $channel->probeNonce();

        [$masked, $values] = $channel->probeMask('Заказ: {order_comment}', [
            'order_comment' => '{$_modx->runSnippet("evil")}',
        ], $nonce);

        self::assertSame('Заказ: {$_modx->runSnippet("evil")}', strtr($masked, $values));
    }

    /**
     * strtr не идёт по только что вставленному тексту, поэтому значение с
     * {другим_ключом} внутри остаётся как есть.
     */
    public function testFlatValueContainingAnotherKeyIsNotExpanded(): void
    {
        $channel = $this->makeChannel();
        $nonce = $channel->probeNonce();

        [$masked, $values] = $channel->probeMask('{order_comment} / {num}', [
            'order_comment' => 'смотрите {num}',
            'num' => '2610/1',
        ], $nonce);

        self::assertSame('смотрите {num} / 2610/1', strtr($masked, $values));
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
             * @return array{0: string, 1: array<string, string>}
             */
            public function probeMask(string $subject, array $placeholders, string $nonce): array
            {
                return $this->maskFlatPlaceholders($subject, $placeholders, $nonce);
            }
        };
    }
}
