<?php

declare(strict_types=1);

namespace MiniShop3\Processors\Resource;

use MODX\Revolution\modResource;

/**
 * Allows Resource Update processors to load the object when class_key is being changed
 * (e.g. modDocument → msProduct) before parent::initialize() runs getObject($classKey).
 *
 * @property \MODX\Revolution\modX $modx
 * @property string $classKey
 * @property string $primaryKeyField
 */
trait EnsureTargetClassKeyTrait
{
    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $err = $this->ensureTargetClassKeyExists();
        if ($err !== null) {
            return $err;
        }

        return parent::initialize();
    }

    /**
     * @return string|null Error lexicon string or null on success
     */
    protected function ensureTargetClassKeyExists(): ?string
    {
        $primaryKey = $this->getProperty($this->primaryKeyField, false);
        if (empty($primaryKey)) {
            return $this->modx->lexicon($this->classKey . '_err_ns');
        }

        if (!$this->modx->getCount($this->classKey, ['id' => $primaryKey, 'class_key' => $this->classKey])) {
            $res = $this->modx->getObject(modResource::class, ['id' => $primaryKey]);
            if ($res) {
                $res->set('class_key', $this->classKey);
                $res->save();
            }
        }

        return null;
    }
}
