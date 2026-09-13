<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msOrderStatus;
use MiniShop3\Processors\Settings\Status\Create;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class StatusCreateProcessorTest extends ExtraTestCase
{
    public function testCreateRequiresMssettingSave(): void
    {
        $this->withProcessorPoliciesEnforced(function (): void {
            $this->actingAsPlain();

            $response = $this->runExtraProcessor(Create::class, [
                'name' => 'testbench-denied',
                'action' => 'ms3_acl_deny',
            ]);

            $this->assertProcessorPermissionDenied($response, 'mssetting_save', 'ms3_acl_deny');
            $this->assertObjectMissing(msOrderStatus::class, ['name' => 'testbench-denied']);
        });
    }

    public function testCreateSucceedsForSudoUser(): void
    {
        $this->actingAsSudo();

        $response = $this->runExtraProcessor(Create::class, [
            'name' => 'testbench-status',
        ]);

        $this->assertProcessorSuccess($response);
        $this->assertObjectExists(msOrderStatus::class, ['name' => 'testbench-status']);
    }
}
