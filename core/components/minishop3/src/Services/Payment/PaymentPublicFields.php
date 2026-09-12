<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

/**
 * Allowlisted payment-method fields for storefront, cabinet, and snippets.
 * Never includes class or properties (credentials).
 */
final class PaymentPublicFields
{
    /**
     * @var list<string>
     */
    public const FIELDS = ['id', 'name', 'description', 'price', 'logo'];

    /**
     * @return array{id: int, name: string, description: string, price: mixed, logo: string}|null
     */
    public static function fromEntity(mixed $entity): ?array
    {
        if (!is_object($entity) || !method_exists($entity, 'get')) {
            return null;
        }

        return [
            'id' => (int) $entity->get('id'),
            'name' => (string) $entity->get('name'),
            'description' => (string) $entity->get('description'),
            'price' => $entity->get('price'),
            'logo' => (string) $entity->get('logo'),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string, price: mixed, logo: string}|array{}
     */
    public static function fromEntityOrEmpty(mixed $entity): array
    {
        return self::fromEntity($entity) ?? [];
    }
}
