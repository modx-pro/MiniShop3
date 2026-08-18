<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Middleware\CorsMiddleware;
use MiniShop3\Utils\CorsConfig;
use PHPUnit\Framework\TestCase;

/**
 * CORS smoke adjacent to journey suite (#574 / #335).
 *
 * Full Router OPTIONS cannot run in-process: CorsMiddleware exits after 200.
 */
final class HeadlessStorefrontCorsTest extends TestCase
{
    public function testNormalizeRejectsWildcardWithCredentials(): void
    {
        $cfg = CorsConfig::normalizeCorsConfig([
            'allowed_origins' => '*',
            'allow_credentials' => true,
        ]);

        self::assertSame(['*'], $cfg['allowed_origins']);
        self::assertFalse($cfg['allow_credentials']);
    }

    public function testPreflightOptionsExitsWithHttp200ForAllowlistedOrigin(): void
    {
        $script = <<<'PHP'
<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
require $argv[1];
use MiniShop3\Middleware\CorsMiddleware;
use MiniShop3\Utils\CorsConfig;

$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['HTTP_ORIGIN'] = 'https://shop.example';

$mw = new CorsMiddleware([
    'allowed_origins' => 'https://shop.example',
    'allow_credentials' => true,
]);
$originOk = CorsConfig::isOriginAllowed('https://shop.example', ['https://shop.example']) ? '1' : '0';

register_shutdown_function(static function () use ($originOk): void {
    echo 'HTTP_CODE=' . (string) http_response_code() . "\n";
    echo 'ORIGIN_OK=' . $originOk . "\n";
});

$mw->handle([]);
fwrite(STDERR, "OPTIONS did not exit\n");
exit(2);
PHP;

        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

        $tmp = tempnam(sys_get_temp_dir(), 'ms3-cors-');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, $script);

        $cmd = sprintf(
            '%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($tmp),
            escapeshellarg($autoload)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, dirname($tmp));
        self::assertIsResource($proc);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        @unlink($tmp);

        self::assertSame(0, $code, $stderr);
        self::assertStringContainsString('HTTP_CODE=200', (string) $stdout);
        self::assertStringContainsString('ORIGIN_OK=1', (string) $stdout);
        self::assertStringNotContainsString('OPTIONS did not exit', (string) $stderr);
    }

    public function testNormalizeEmptyOriginsDisallowsAll(): void
    {
        $cfg = CorsConfig::normalizeCorsConfig([
            'allowed_origins' => '',
            'allow_credentials' => true,
        ]);

        self::assertSame([], $cfg['allowed_origins']);
    }

    public function testCorsMiddlewareContinuesForNonOptions(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://shop.example';

        $mw = new CorsMiddleware([
            'allowed_origins' => 'https://shop.example',
            'allow_credentials' => true,
        ]);

        self::assertNull($mw->handle([]));
    }
}
