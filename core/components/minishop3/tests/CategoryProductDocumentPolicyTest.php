<?php

/**
 * Resource policy map for category product document ACL (#445).
 *
 * Run: php tests/CategoryProductDocumentPolicyTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\HttpStatus;
use MiniShop3\Services\Category\CategoryProductDocumentPolicy;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame(
    [CategoryProductDocumentPolicy::POLICY_PUBLISH],
    CategoryProductDocumentPolicy::policiesForPublish(true),
    'policiesForPublish(true)'
);
$assertSame(
    [CategoryProductDocumentPolicy::POLICY_SAVE, CategoryProductDocumentPolicy::POLICY_UNPUBLISH],
    CategoryProductDocumentPolicy::policiesForPublish(false),
    'policiesForPublish(false)'
);
$assertSame(
    [CategoryProductDocumentPolicy::POLICY_SAVE],
    CategoryProductDocumentPolicy::sortPolicies(),
    'sortPolicies'
);
$assertSame(
    CategoryProductDocumentPolicy::POLICY_VIEW,
    CategoryProductDocumentPolicy::categoryViewPolicy(),
    'categoryViewPolicy'
);

$allowed = new class {
    public function checkPolicy(string $policy): bool
    {
        return $policy === 'view';
    }
};

$denied = new class {
    public function checkPolicy(string $policy): bool
    {
        return false;
    }
};

$partial = new class {
    public function checkPolicy(string $policy): bool
    {
        return $policy === 'save';
    }
};

$assertSame(true, CategoryProductDocumentPolicy::isAllowed($allowed, 'view'), 'isAllowed view');
$assertSame(false, CategoryProductDocumentPolicy::isAllowed($denied, 'view'), 'isAllowed denied');
$assertSame(
    true,
    CategoryProductDocumentPolicy::isAllowedAll($partial, ['save']),
    'isAllowedAll single save'
);
$assertSame(
    false,
    CategoryProductDocumentPolicy::isAllowedAll($partial, ['save', 'unpublish']),
    'isAllowedAll compound partial failure'
);

$viewDenied = CategoryProductDocumentPolicy::evaluate($denied, 'view');
$assertSame(HttpStatus::FORBIDDEN, $viewDenied['status'] ?? null, 'evaluate denied status');
$assertSame(
    'View permission denied for this document',
    $viewDenied['message'] ?? null,
    'evaluate denied message'
);

$noPolicyObject = new stdClass();
$noPolicyDenied = CategoryProductDocumentPolicy::evaluate($noPolicyObject, 'view');
$assertSame(HttpStatus::FORBIDDEN, $noPolicyDenied['status'] ?? null, 'evaluate without checkPolicy status');

fwrite(STDOUT, "OK: CategoryProductDocumentPolicyTest\n");
