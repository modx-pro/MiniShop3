<?php

/**
 * Functional dispatch tests for customer auth Web API endpoints (#422).
 *
 * Uses WebApiModxStub + Router dispatch (no MODX/MySQL).
 *
 * Run: php tests/CustomerAuthEndpointsTest.php
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/WebApiModxStub.php';
require __DIR__ . '/stubs/ProcessorResponseStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Api\Web\CustomerAuthController;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Router;
use MiniShop3\Tests\Stubs\ProcessorResponseStub;
use MODX\Revolution\WebApiModxStub;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $label) use ($fail): void {
    if ($actual !== $expected) {
        $fail(sprintf(
            "%s:\nexpected: %s\nactual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};

$buildRouter = static function (WebApiModxStub $modx): Router {
    $router = new Router($modx);
    $router->loadRoutes(dirname(__DIR__) . '/config/routes/web.php');
    $router->build();

    return $router;
};

$dispatchPost = static function (Router $router, string $route): array {
    $_REQUEST['route'] = $route;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = $route;

    $response = $router->dispatch($route, 'POST');
    $data = $response->getData();

    return [
        'status' => $response->getStatusCode(),
        'success' => $data['success'] ?? null,
        'message' => (string) ($data['message'] ?? ''),
        'code' => $data['code'] ?? null,
    ];
};

// --- CustomerAuthController: request/response mapping ---

$modx = new WebApiModxStub();
$controller = new CustomerAuthController($modx);

$modx->runProcessorHandler = static function (string $action, array $data): ProcessorResponseStub {
    return match ($action) {
        'MiniShop3\\Processors\\Api\\Customer\\ForgotPassword' => ProcessorResponseStub::failure(
            'email required',
            ['code' => HttpStatus::BAD_REQUEST]
        ),
        'MiniShop3\\Processors\\Api\\Customer\\ResetPassword' => ProcessorResponseStub::failure(
            'too many attempts',
            ['code' => HttpStatus::TOO_MANY_REQUESTS]
        ),
        'MiniShop3\\Processors\\Api\\Customer\\Logout' => ProcessorResponseStub::success(null, 'logged out'),
        default => ProcessorResponseStub::failure('unexpected processor: ' . $action),
    };
};

$response = $controller->forgotPassword([]);
$assertSame(HttpStatus::BAD_REQUEST, $response->getStatusCode(), 'forgotPassword empty email status');
$assertSame(false, $response->getData()['success'] ?? null, 'forgotPassword empty email success flag');

$response = $controller->resetPassword([
    'token' => 'reset-token',
    'password' => 'short',
    'password_confirm' => 'short',
]);
$assertSame(HttpStatus::TOO_MANY_REQUESTS, $response->getStatusCode(), 'resetPassword rate limit status');
$assertSame(429, $response->getData()['code'] ?? null, 'resetPassword rate limit body code');

$response = $controller->logout();
$assertSame(HttpStatus::OK, $response->getStatusCode(), 'logout controller success status');
$assertSame('logged out', $response->getData()['message'] ?? null, 'logout controller message');

$modx->runProcessorHandler = static function (string $action, array $data): ProcessorResponseStub {
    return match ($action) {
        'MiniShop3\\Processors\\Api\\Customer\\ForgotPassword' => ProcessorResponseStub::success(
            ['sent' => true],
            'reset email queued'
        ),
        default => ProcessorResponseStub::failure('unexpected processor: ' . $action),
    };
};

$response = $controller->forgotPassword(['email' => 'user@example.com']);
$assertSame(HttpStatus::OK, $response->getStatusCode(), 'forgotPassword success status');
$assertSame(true, $response->getData()['success'] ?? null, 'forgotPassword success flag');

// --- Router dispatch: logout with TokenMiddleware + session ---

$modx = new WebApiModxStub();
$modx->customers[42] = (object) ['id' => 42];
$modx->runProcessorHandler = static function (string $action): ProcessorResponseStub {
    if ($action === 'MiniShop3\\Processors\\Api\\Customer\\Logout') {
        return ProcessorResponseStub::success(null, 'You have been logged out');
    }

    return ProcessorResponseStub::failure('unexpected: ' . $action);
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['ms3'] = ['customer_id' => 42];

$router = $buildRouter($modx);
$result = $dispatchPost($router, '/api/v1/customer/logout');
$assertSame(HttpStatus::OK, $result['status'], 'dispatch logout HTTP status');
$assertSame(true, $result['success'], 'dispatch logout success');
$assertSame('You have been logged out', $result['message'], 'dispatch logout message');

// --- Router dispatch: forgot-password (body via stdin pipe subprocess) ---

$subprocessScript = <<<'PHP'
<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/WebApiModxStub.php';
require __DIR__ . '/stubs/ProcessorResponseStub.php';
require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Router;
use MiniShop3\Tests\Stubs\ProcessorResponseStub;
use MODX\Revolution\WebApiModxStub;

$route = $argv[1] ?? '';
$rawBody = stream_get_contents(STDIN);
$expectedProcessor = $argv[2] ?? '';

$modx = new WebApiModxStub();
$modx->runProcessorHandler = static function (string $action, array $data) use ($expectedProcessor, $rawBody): ProcessorResponseStub {
    if ($action !== $expectedProcessor) {
        fwrite(STDERR, "processor mismatch: {$action}\n");
        exit(2);
    }

    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "invalid json body\n");
        exit(3);
    }

    if ($expectedProcessor === 'MiniShop3\\Processors\\Api\\Customer\\ForgotPassword') {
        if (($decoded['email'] ?? '') !== 'user@example.com') {
            fwrite(STDERR, "unexpected email\n");
            exit(4);
        }

        return ProcessorResponseStub::success(null, 'Password reset instructions have been sent to your email');
    }

    if ($expectedProcessor === 'MiniShop3\\Processors\\Api\\Customer\\ResetPassword') {
        if (($decoded['token'] ?? '') !== 'reset-token-abc') {
            fwrite(STDERR, "unexpected token\n");
            exit(5);
        }

        return ProcessorResponseStub::success(null, 'Password successfully changed');
    }

    return ProcessorResponseStub::failure('unknown');
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = $route;
$_REQUEST['route'] = $route;

$router = new Router($modx);
$router->loadRoutes(dirname(__DIR__) . '/config/routes/web.php');
$router->build();

$response = $router->dispatch($route, 'POST');
$data = $response->getData();

echo json_encode([
    'status' => $response->getStatusCode(),
    'success' => $data['success'] ?? null,
    'message' => $data['message'] ?? null,
], JSON_THROW_ON_ERROR);
PHP;

$subprocessPath = __DIR__ . '/_customer_auth_dispatch_subprocess.php';
file_put_contents($subprocessPath, $subprocessScript);

$runWithBody = static function (string $route, string $processor, string $jsonBody) use ($subprocessPath, $fail, $assertSame): void {
    $command = sprintf(
        '%s %s %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($subprocessPath),
        escapeshellarg($route),
        escapeshellarg($processor)
    );

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, __DIR__);
    if (!is_resource($process)) {
        $fail('unable to start subprocess for ' . $route);
    }

    fwrite($pipes[0], $jsonBody);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $fail("subprocess {$route} exit {$exitCode}: {$stderr}");
    }

    $payload = json_decode($stdout, true);
    if (!is_array($payload)) {
        $fail("subprocess {$route} returned invalid json: {$stdout}");
    }

    $assertSame(HttpStatus::OK, $payload['status'], "{$route} dispatch status");
    $assertSame(true, $payload['success'], "{$route} dispatch success");
};

$runWithBody(
    '/api/v1/customer/forgot-password',
    'MiniShop3\\Processors\\Api\\Customer\\ForgotPassword',
    '{"email":"user@example.com"}'
);

$runWithBody(
    '/api/v1/customer/reset-password',
    'MiniShop3\\Processors\\Api\\Customer\\ResetPassword',
    '{"token":"reset-token-abc","password":"Str0ngPass!","password_confirm":"Str0ngPass!"}'
);

@unlink($subprocessPath);

echo "OK CustomerAuthEndpointsTest\n";
