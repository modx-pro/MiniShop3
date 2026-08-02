<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

use MiniShop3\Utils\EventGate;
use PHPUnit\Framework\TestCase;

final class EventGateTest extends TestCase
{
    public function testMergeReturnedValuesShallowMerge(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            EventGate::mergeReturnedValues(['a' => 1], ['b' => 2]),
        );
    }

    public function testMergeReturnedValuesEmptyChannel(): void
    {
        $this->assertSame(['a' => 1], EventGate::mergeReturnedValues(['a' => 1], []));
    }

    public function testApplyReturnedArrayAssociativePatch(): void
    {
        $this->assertSame(
            ['foo' => 'bar', 'price' => 99],
            EventGate::applyReturnedArray(
                ['foo' => 'bar', 'price' => 10],
                ['data' => ['price' => 99]],
                'data',
            ),
        );
    }

    public function testApplyReturnedArrayListReplacement(): void
    {
        $this->assertSame(
            ['one', 'two'],
            EventGate::applyReturnedArray(['old'], ['data' => ['one', 'two']], 'data'),
        );
    }

    public function testApplyReturnedArrayMissingChannel(): void
    {
        $this->assertSame(
            ['foo' => 'bar'],
            EventGate::applyReturnedArray(['foo' => 'bar'], ['other' => ['x' => 1]], 'data'),
        );
    }

    public function testIsCancelledDetectsFalseAndCancel(): void
    {
        $this->assertTrue(EventGate::isCancelled([false]));
        $this->assertTrue(EventGate::isCancelled(['cancel']));
        $this->assertFalse(EventGate::isCancelled(['ok', 'fine']));
        $this->assertFalse(EventGate::isCancelled('error'));
    }

    public function testNormalizeMessage(): void
    {
        $this->assertSame('', EventGate::normalizeMessage([]));
        $this->assertSame('a<br/>b', EventGate::normalizeMessage(['a', 'b']));
        $this->assertSame('single', EventGate::normalizeMessage('single'));
        $this->assertSame('a<br/>b', EventGate::normalizeMessage(['a', '', 'b']));
    }

    public function testBuildInvokeResultSuccessAndFailure(): void
    {
        $ok = EventGate::buildInvokeResult(['x' => 1], ['y' => 2], '');
        $this->assertTrue($ok['success']);
        $this->assertSame(['x' => 1, 'y' => 2], $ok['data']);
        $this->assertSame(['y' => 2], $ok['values']);

        $blocked = EventGate::buildInvokeResult(['x' => 1], [], 'blocked');
        $this->assertFalse($blocked['success']);

        $zeroMessage = EventGate::buildInvokeResult(['x' => 1], [], '0');
        $this->assertTrue($zeroMessage['success']);
    }
}
