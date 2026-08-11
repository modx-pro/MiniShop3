<?php

namespace MiniShop3\Services\Validation;

use MODX\Revolution\modX;

/**
 * Resolves the canonical validation service from MODX DI.
 */
final class ValidationServiceLocator
{
    public static function fromModx(modX $modx): ValidationService
    {
        $service = $modx->services->get('ms3_validation_service');

        return $service instanceof ValidationService ? $service : new ValidationService();
    }
}
