<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * In-memory cart facade for Phase A Web API journey (HTTP boundary only).
 *
 * Bags are keyed by API token so guest→login transfer is testable.
 */
final class JourneyCart
{
    private string $token = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $bags = [];

    public function initialize(string $ctx, string $token): bool
    {
        unset($ctx);
        $this->token = $token;
        $this->bags[$token] ??= [];

        return $token !== '';
    }

    public function transfer(string $fromToken, string $toToken): void
    {
        if ($fromToken === '' || $toToken === '' || $fromToken === $toToken) {
            return;
        }

        $this->bags[$toToken] = $this->bags[$fromToken] ?? [];
        unset($this->bags[$fromToken]);
        $this->token = $toToken;
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function add(int $id, int $count, array $options = []): array
    {
        if ($id < 1 || $count < 1) {
            return JourneyResult::fail('ms3_cart_add_err_count');
        }

        $key = $this->makeKey($id, $options);
        $price = 100.0;
        $lineCost = $price * $count;

        $items = &$this->currentBag();
        $items[$key] = [
            'id' => $id,
            'product_key' => $key,
            'count' => $count,
            'price' => $price,
            'cost' => $lineCost,
            'options' => $options,
        ];

        return JourneyResult::ok('ms3_cart_add_success', [
            'cart' => $items,
            'status' => $this->status($items),
            'last_key' => $key,
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function change(string $productKey, int $count): array
    {
        $items = &$this->currentBag();
        if ($productKey === '' || !isset($items[$productKey])) {
            return JourneyResult::fail('ms3_cart_change_error');
        }

        if ($count < 1) {
            unset($items[$productKey]);

            return JourneyResult::ok('ms3_cart_change_success', [
                'cart' => $items,
                'status' => $this->status($items),
            ]);
        }

        $items[$productKey]['count'] = $count;
        $items[$productKey]['cost'] = (float) $items[$productKey]['price'] * $count;

        return JourneyResult::ok('ms3_cart_change_success', [
            'cart' => $items,
            'status' => $this->status($items),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function changeOption(string $productKey, array $options): array
    {
        if ($options === []) {
            return JourneyResult::fail('ms3_cart_change_options_error');
        }

        $items = &$this->currentBag();
        if ($productKey === '' || !isset($items[$productKey])) {
            return JourneyResult::fail('ms3_cart_change_options_error');
        }

        $old = $items[$productKey];
        unset($items[$productKey]);
        $newKey = $this->makeKey((int) $old['id'], $options);
        $old['product_key'] = $newKey;
        $old['options'] = $options;
        $items[$newKey] = $old;

        return JourneyResult::ok('ms3_cart_change_options_success', [
            'cart' => $items,
            'status' => $this->status($items),
            'last_key' => $newKey,
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function get(): array
    {
        $items = $this->currentBag();

        return JourneyResult::ok('', [
            'cart' => $items,
            'status' => $this->status($items),
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function clean(): array
    {
        $this->bags[$this->token] = [];

        return JourneyResult::ok('ms3_cart_clean_success', [
            'cart' => [],
            'status' => $this->status([]),
        ]);
    }

    public function isEmpty(?string $token = null): bool
    {
        $t = $token ?? $this->token;

        return ($this->bags[$t] ?? []) === [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function &currentBag(): array
    {
        $this->bags[$this->token] ??= [];

        return $this->bags[$this->token];
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @return array{total_count: int, total_cost: float}
     */
    private function status(array $items): array
    {
        $totalCount = 0;
        $totalCost = 0.0;
        foreach ($items as $item) {
            $totalCount += (int) $item['count'];
            $totalCost += (float) $item['cost'];
        }

        return [
            'total_count' => $totalCount,
            'total_cost' => $totalCost,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeKey(int $id, array $options): string
    {
        return md5((string) $id . json_encode($options, JSON_THROW_ON_ERROR));
    }
}
