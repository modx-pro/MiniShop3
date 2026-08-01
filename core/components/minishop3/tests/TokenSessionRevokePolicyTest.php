<?php

/**
 * Guards session bypass after API token revoke (issue #409).
 *
 * Run: php tests/TokenSessionRevokePolicyTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$repoRoot = dirname(__DIR__, 4);
$middleware = $repoRoot . '/core/components/minishop3/src/Middleware/TokenMiddleware.php';
$trait = $repoRoot . '/core/components/minishop3/src/Controllers/Api/Web/AuthorizedCustomerTrait.php';
$webRoutes = $repoRoot . '/core/components/minishop3/config/routes/web.php';
$logout = $repoRoot . '/core/components/minishop3/src/Processors/Api/Customer/Logout.php';

foreach ([$middleware, $trait, $webRoutes, $logout] as $path) {
    if (!is_readable($path)) {
        $fail('cannot read ' . basename($path));
    }
}

$middlewareSource = file_get_contents($middleware);
$traitSource = file_get_contents($trait);
$webRoutesSource = file_get_contents($webRoutes);
$logoutSource = file_get_contents($logout);

if ($middlewareSource === false || $traitSource === false || $webRoutesSource === false || $logoutSource === false) {
    $fail('cannot read sources');
}

if (preg_match('/getObject\s*\(\s*\\\\MiniShop3\\\\Model\\\\msCustomer::class,\s*\$_SESSION\s*\[\s*[\'"]ms3[\'"]\s*\]\s*\[\s*[\'"]customer_id[\'"]\s*\]/', $middlewareSource) === 1) {
    $fail('TokenMiddleware must not authorize via session customer_id alone');
}

if (!str_contains($middlewareSource, '$_SESSION[\'ms3\'][\'customer_token\']')) {
    $fail('TokenMiddleware resolveToken must read session customer_token cache');
}

if (!str_contains($middlewareSource, 'clearClientTokenState')) {
    $fail('TokenMiddleware must clear stale session identity without valid token');
}

if (preg_match('/Fall back to session customer_id|Method 2:/', $traitSource) === 1) {
    $fail('AuthorizedCustomerTrait must not fall back to session customer_id');
}

if (!str_contains($traitSource, 'resolveApiToken')) {
    $fail('AuthorizedCustomerTrait must resolve customer via validated API token');
}

if (!preg_match('#post\s*\(\s*[\'"]/logout[\'"]#', $webRoutesSource)) {
    $fail('web routes must register POST /customer/logout');
}

if (!str_contains($webRoutesSource, 'MiniShop3\\Processors\\Api\\Customer\\Logout')) {
    $fail('logout route must invoke Logout processor');
}

if (!str_contains($logoutSource, 'session_regenerate_id')) {
    $fail('Logout processor must regenerate session id after logout');
}

if (!str_contains($logoutSource, 'resolveCustomerForLogout')) {
    $fail('Logout processor must resolve customer from validated token when session is empty');
}

if (!str_contains($middlewareSource, '/api/v1/customer/logout')) {
    $fail('TokenMiddleware publicRoutes must include customer logout');
}

if (!preg_match('#post\s*\(\s*[\'"]/logout[\'"][\s\S]*?\[\s*\$tokenMiddleware\s*\]#', $webRoutesSource)) {
    $fail('logout route must use tokenMiddleware for Bearer/cookie identity');
}

fwrite(STDOUT, "OK TokenSessionRevokePolicyTest\n");
exit(0);
