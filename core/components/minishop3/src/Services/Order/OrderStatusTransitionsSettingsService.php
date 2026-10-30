<?php

declare(strict_types=1);

namespace MiniShop3\Services\Order;

use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;

/**
 * Read/write ms3_order_status_transitions for the manager matrix editor (#785).
 *
 * Persists plain CSV (or empty string) — never Utils::updateSetting JSON.
 */
final class OrderStatusTransitionsSettingsService
{
    public const SETTING_KEY = 'ms3_order_status_transitions';

    public function __construct(private readonly modX $modx)
    {
    }

    /**
     * @return array{
     *   mode: int,
     *   invalid: bool,
     *   statuses: list<array<string, mixed>>,
     *   edges: list<array{0: int, 1: int}>,
     *   unreachable: list<int>
     * }
     */
    public function getMatrix(): array
    {
        $statuses = $this->loadStatuses();
        $resolved = OrderStatusTransitionPolicy::resolve(
            $this->modx->getOption(self::SETTING_KEY, null, '')
        );
        $modeOn = $resolved['mode'] === OrderStatusTransitionPolicy::MODE_ON;

        return [
            'mode' => $resolved['mode'],
            'invalid' => $resolved['mode'] === OrderStatusTransitionPolicy::MODE_INVALID,
            'statuses' => $statuses,
            'edges' => OrderStatusTransitionPolicy::toPairList($resolved['edges']),
            'unreachable' => $modeOn
                ? $this->unreachableStatusIds($statuses, $resolved['edges'])
                : [],
        ];
    }

    /**
     * @param list<mixed> $rawEdges
     * @return array{
     *   mode: int,
     *   invalid: bool,
     *   statuses: list<array<string, mixed>>,
     *   edges: list<array{0: int, 1: int}>,
     *   unreachable: list<int>
     * }
     */
    public function saveEdges(array $rawEdges): array
    {
        $statuses = $this->loadStatuses();
        $byId = [];
        foreach ($statuses as $status) {
            $byId[(int) $status['id']] = $status;
        }

        $edges = [];
        foreach ($rawEdges as $pair) {
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }
            $from = (int) $pair[0];
            $to = (int) $pair[1];
            if ($from < 1 || $to < 1 || $from === $to) {
                continue;
            }
            if (!isset($byId[$from], $byId[$to])) {
                continue;
            }
            if ($this->isStructurallyForbidden($byId[$from], $byId[$to])) {
                continue;
            }
            $edges[$from][$to] = true;
        }

        $csv = OrderStatusTransitionPolicy::toCsv($edges);
        $this->persistSettingValue($csv);

        return $this->getMatrix();
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    public function isStructurallyForbidden(array $from, array $to): bool
    {
        if (!empty($from['final'])) {
            return true;
        }

        if (!empty($from['fixed']) && (int) $to['position'] <= (int) $from['position']) {
            return true;
        }

        return false;
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   color: string,
     *   active: bool,
     *   final: bool,
     *   fixed: bool,
     *   position: int
     * }>
     */
    private function loadStatuses(): array
    {
        $q = $this->modx->newQuery(msOrderStatus::class);
        $q->sortby('position', 'ASC');

        $results = [];
        foreach ($this->modx->getIterator(msOrderStatus::class, $q) as $status) {
            $results[] = [
                'id' => (int) $status->get('id'),
                'name' => (string) $status->get('name'),
                'color' => (string) $status->get('color'),
                'active' => (bool) $status->get('active'),
                'final' => (bool) $status->get('final'),
                'fixed' => (bool) $status->get('fixed'),
                'position' => (int) $status->get('position'),
            ];
        }

        return $results;
    }

    /**
     * @param list<array<string, mixed>> $statuses
     * @param array<int, array<int, true>> $edges
     * @return list<int>
     */
    private function unreachableStatusIds(array $statuses, array $edges): array
    {
        $incoming = [];
        foreach ($edges as $tos) {
            foreach (array_keys($tos) as $to) {
                $incoming[(int) $to] = true;
            }
        }

        $unreachable = [];
        foreach ($statuses as $status) {
            $id = (int) $status['id'];
            if (!isset($incoming[$id])) {
                $unreachable[] = $id;
            }
        }

        return $unreachable;
    }

    private function persistSettingValue(string $value): void
    {
        $setting = $this->modx->getObject(modSystemSetting::class, ['key' => self::SETTING_KEY]);
        if (!$setting) {
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->fromArray([
                'key' => self::SETTING_KEY,
                'namespace' => 'minishop3',
                'area' => 'ms3_statuses',
                'xtype' => 'textfield',
            ], '', true, true);
        }

        $setting->set('value', $value);
        $setting->save();

        $this->modx->setOption(self::SETTING_KEY, $value);
        if ($this->modx->cacheManager) {
            $this->modx->cacheManager->refresh(['system_settings' => []]);
        }
    }
}
