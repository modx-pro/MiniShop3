<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Processors\Product\Multiple;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

/** Product\Multiple is an ACL canary: without $permission it returns success() for empty ids (#696). */
final class ProductProcessorAclTest extends ExtraTestCase
{
    public function testMultipleDeniesPlainUserWithoutMsproductSave(): void
    {
        $this->withProcessorPoliciesEnforced(function (): void {
            $this->actingAsPlain();

            $response = $this->runExtraProcessor(Multiple::class, [
                'method' => 'Delete',
                'ids' => '[]',
                'action' => 'ms3_acl_deny',
            ]);

            $this->assertProcessorPermissionDenied($response, 'msproduct_save', 'ms3_acl_deny');
        });
    }
}
