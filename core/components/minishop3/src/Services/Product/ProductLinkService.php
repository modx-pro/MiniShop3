<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msLink;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductLink;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Product↔product link instances (msProductLink) for the manager product form.
 *
 * Canonical CRUD used by Manager REST; processors may delegate here.
 */
class ProductLinkService
{
    public function __construct(private modX $modx)
    {
    }

    /**
     * @return array{results: list<array<string, mixed>>, total: int}
     */
    public function listForProduct(int $productId, string $query = '', int $start = 0, int $limit = 20): array
    {
        $c = $this->modx->newQuery(msProductLink::class);
        $c->orCondition(['master' => $productId, 'slave' => $productId]);
        $c->innerJoin(msLink::class, 'msLink', 'msProductLink.link = msLink.id');
        $c->leftJoin(msProduct::class, 'Master', 'Master.id = msProductLink.master');
        $c->leftJoin(msProduct::class, 'Slave', 'Slave.id = msProductLink.slave');
        $c->select($this->modx->getSelectColumns(msProductLink::class, 'msProductLink'));
        $c->select($this->modx->getSelectColumns(msLink::class, 'msLink', '', ['id'], true));
        $c->select('Master.pagetitle as master_pagetitle, Slave.pagetitle as slave_pagetitle');

        $query = trim($query);
        if ($query !== '') {
            $c->where([
                'msLink.name:LIKE' => '%' . $query . '%',
                'OR:Master.pagetitle:LIKE' => '%' . $query . '%',
                'OR:Slave.pagetitle:LIKE' => '%' . $query . '%',
            ]);
        }

        $total = (int) $this->modx->getCount(msProductLink::class, $c);
        $c->sortby('msLink.name', 'ASC');
        if ($limit > 0) {
            $c->limit($limit, max(0, $start));
        }

        $results = [];
        foreach ($this->modx->getIterator(msProductLink::class, $c) as $row) {
            $results[] = $row->toArray();
        }

        return ['results' => $results, 'total' => $total];
    }

    /**
     * All msLink type definitions for manager combos (product form, settings).
     *
     * @return list<array{id: int, name: string, type: string, description: string}>
     */
    public function listLinkTypes(): array
    {
        $c = $this->modx->newQuery(msLink::class);
        $c->sortby('name', 'ASC');
        $c->select('id,name,type,description');

        $out = [];
        foreach ($this->modx->getIterator(msLink::class, $c) as $link) {
            $out[] = [
                'id' => (int) $link->get('id'),
                'name' => (string) $link->get('name'),
                'type' => (string) $link->get('type'),
                'description' => (string) $link->get('description'),
            ];
        }

        return $out;
    }

    /**
     * Whether a product participates in the master/slave pair.
     */
    public static function belongsToProduct(int $productId, int $master, int $slave): bool
    {
        return $productId > 0 && ($master === $productId || $slave === $productId);
    }

    /**
     * Known msLink.type values handled by create/remove.
     */
    public static function supportsLinkType(string $type): bool
    {
        return in_array($type, ['one_to_many', 'many_to_one', 'one_to_one', 'many_to_many'], true);
    }

    /**
     * Master/slave rows to insert when creating a link (before many_to_many mesh expansion).
     *
     * @return list<array{master: int, slave: int}>|null null when type is unknown
     */
    public static function initialPairsForType(string $type, int $master, int $slave): ?array
    {
        return match ($type) {
            'one_to_many' => [['master' => $master, 'slave' => $slave]],
            'many_to_one' => [['master' => $slave, 'slave' => $master]],
            'one_to_one', 'many_to_many' => [
                ['master' => $master, 'slave' => $slave],
                ['master' => $slave, 'slave' => $master],
            ],
            default => null,
        };
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function create(int $master, int $slave, int $linkId): array
    {
        $this->modx->lexicon->load('minishop3:default');

        if ($master <= 0 || $slave <= 0 || $linkId <= 0) {
            return $this->fail('ms3_err_ns');
        }
        if ($master === $slave) {
            return $this->fail('ms3_err_link_equal');
        }

        $msLink = $this->findLink($linkId);
        if (!$msLink) {
            return $this->fail('ms3_err_no_link');
        }

        $type = (string) $msLink->get('type');
        $pairs = self::initialPairsForType($type, $master, $slave);
        if ($pairs === null) {
            return $this->fail('ms3_err_no_link');
        }

        foreach ($pairs as $pair) {
            if (!$this->addLink($linkId, $pair['master'], $pair['slave'])) {
                return $this->fail('ms3_err_link_save');
            }
        }

        if ($type === 'many_to_many' && !$this->meshManyToMany($linkId, $master, $slave)) {
            return $this->fail('ms3_err_link_save');
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function remove(int $linkId, int $master, int $slave): array
    {
        $this->modx->lexicon->load('minishop3:default');

        if ($linkId <= 0 || $master <= 0 || $slave <= 0) {
            return ['ok' => false, 'message' => 'Wrong object key'];
        }

        $msLink = $this->findLink($linkId);
        if (!$msLink) {
            return $this->fail('ms3_err_no_link');
        }

        $q = $this->modx->newQuery(msProductLink::class);
        $q->command('DELETE');
        $q->where(['link' => $linkId]);

        if (!$this->applyRemoveFilter($q, (string) $msLink->get('type'), $master, $slave)) {
            return $this->fail('ms3_err_no_link');
        }

        if (!$q->prepare() || !$q->stmt || !$q->stmt->execute()) {
            return $this->fail('ms3_err_unknown');
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: false, message: string}
     */
    private function fail(string $lexiconKey): array
    {
        return ['ok' => false, 'message' => $this->modx->lexicon($lexiconKey)];
    }

    private function findLink(int $linkId): ?msLink
    {
        /** @var msLink|null $msLink */
        $msLink = $this->modx->getObject(msLink::class, ['id' => $linkId]);

        return $msLink;
    }

    private function applyRemoveFilter(
        xPDOQuery $query,
        string $type,
        int $master,
        int $slave
    ): bool {
        if (!self::supportsLinkType($type)) {
            return false;
        }

        switch ($type) {
            case 'many_to_many':
                $query->where(['master' => $slave, 'OR:slave:=' => $slave]);
                return true;
            case 'one_to_one':
                $query->where([
                    ['master' => $master, 'AND:slave:=' => $slave],
                    ['master' => $slave, 'AND:slave:=' => $master],
                ], xPDOQuery::SQL_OR);
                return true;
            case 'many_to_one':
            case 'one_to_many':
                $query->where(['master' => $master, 'slave' => $slave]);
                return true;
            default:
                return false;
        }
    }

    private function addLink(int $linkId, int $master, int $slave): bool
    {
        if ($linkId <= 0 || $master <= 0 || $slave <= 0) {
            return false;
        }

        $existing = $this->modx->getObject(msProductLink::class, [
            'link' => $linkId,
            'master' => $master,
            'slave' => $slave,
        ]);
        if ($existing) {
            return true;
        }

        $object = $this->modx->newObject(msProductLink::class);
        // Composite PK: xPDO fromArray() skips PK fields unless setPrimaryKeys is true.
        $object->set('link', $linkId);
        $object->set('master', $master);
        $object->set('slave', $slave);

        if (!$object->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ProductLinkService] failed to save msProductLink link={$linkId} master={$master} slave={$slave}"
            );

            return false;
        }

        return true;
    }

    private function meshManyToMany(int $linkId, int $master, int $slave): bool
    {
        $q = $this->modx->newQuery(msProductLink::class, ['link' => $linkId]);
        $q->andCondition(['master:IN' => [$master, $slave]]);
        $q->select('slave');

        if (!$q->prepare() || !$q->stmt || !$q->stmt->execute()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ProductLinkService] failed to mesh many_to_many msProductLink link={$linkId} master={$master} slave={$slave}"
            );

            return false;
        }

        /** @var list<int|string> $slaves */
        $slaves = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        $slaves = array_values(array_unique(array_map('intval', $slaves)));

        foreach ($slaves as $left) {
            foreach ($slaves as $right) {
                if ($left !== $right && !$this->addLink($linkId, $left, $right)) {
                    return false;
                }
            }
        }

        return true;
    }
}
