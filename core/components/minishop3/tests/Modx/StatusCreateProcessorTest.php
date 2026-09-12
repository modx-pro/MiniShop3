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

    public function testCreateSucceedsForUserWithGrantedMssettingSave(): void
    {
        $this->withProcessorPoliciesEnforced(function (): void {
            $user = $this->createUser(['username' => 'granted-' . bin2hex(random_bytes(3))]);
            $policy = $this->grantContextPermissions($user, ['mssetting_save']);
            $this->actingAs($user);

            $response = $this->runExtraProcessor(Create::class, [
                'name' => 'testbench-granted',
                'action' => 'ms3_acl_grant',
            ]);

            $this->assertProcessorSuccess($response);
            $this->assertObjectExists(msOrderStatus::class, ['name' => 'testbench-granted']);

            $this->revokeContextPermissions($policy);

            $denied = $this->runExtraProcessor(Create::class, [
                'name' => 'testbench-revoked',
                'action' => 'ms3_acl_revoke',
            ]);

            $this->assertProcessorPermissionDenied($denied, 'mssetting_save', 'ms3_acl_revoke');
            $this->assertObjectMissing(msOrderStatus::class, ['name' => 'testbench-revoked']);
        });
    }

    public function testStringActionWithoutProcessorsPathIsNotFound(): void
    {
        $user = $this->createUser(['username' => 'sudo-missing-path', 'sudo' => true]);
        $this->actingAs($user);

        $response = $this->runProcessor('settings/status/create', [
            'name' => 'testbench-string-action',
        ]);

        $this->assertProcessorFailure($response);
        self::assertStringContainsStringIgnoringCase('not found', $response->getMessage());
        $this->assertObjectMissing(msOrderStatus::class, ['name' => 'testbench-string-action']);
    }
}
