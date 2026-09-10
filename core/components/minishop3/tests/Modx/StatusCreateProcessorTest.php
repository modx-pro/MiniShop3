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
        $this->skipUnlessProcessorPoliciesAreEnforced();
        $user = $this->createUser(['username' => 'editor-no-settings']);
        $this->actingAs($user);

        $response = $this->runProcessor(Create::class, [
            'name' => 'testbench-denied',
        ]);

        $this->assertProcessorFailure($response);
        self::assertStringContainsStringIgnoringCase('access denied', $response->getMessage());
        $this->assertObjectMissing(msOrderStatus::class, ['name' => 'testbench-denied']);
    }

    public function testCreateSucceedsForSudoUser(): void
    {
        $user = $this->createUser(['username' => 'sudo-settings', 'sudo' => true]);
        $this->actingAs($user);

        $response = $this->runProcessor(Create::class, [
            'name' => 'testbench-status',
        ], [
            'processors_path' => dirname(__DIR__, 2) . '/src/Processors/',
        ]);

        $this->assertProcessorSuccess($response);
        $this->assertObjectExists(msOrderStatus::class, ['name' => 'testbench-status']);
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
