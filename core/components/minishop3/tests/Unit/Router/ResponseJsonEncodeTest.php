<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Router;

use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use PHPUnit\Framework\TestCase;

/**
 * Response JSON encoding must never echo an empty body on invalid UTF-8 (#654).
 */
final class ResponseJsonEncodeTest extends TestCase
{
    public function testInvalidUtf8YieldsNonEmptySubstitutedJson(): void
    {
        $modx = $this->makeLogger();
        $response = Response::success(['label' => "ab\xC3\x28cd"]);
        $body = $response->encodeJsonBody($modx);

        self::assertNotSame('', $body);
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['success']);
        self::assertIsString($decoded['data']['label']);
        self::assertStringContainsString("\u{FFFD}", $decoded['data']['label']);
        self::assertNotEmpty($modx->logs);
        self::assertStringContainsString('json_encode failed', $modx->logs[0]);
        self::assertStringContainsString('data.label', $modx->logs[0]);
        self::assertStringContainsString('hex=', $modx->logs[0]);
    }

    public function testValidCyrillicAndSlashesStayUnescaped(): void
    {
        $response = Response::success([
            'title' => 'Товар',
            'url' => 'https://shop.example/a/b',
        ]);
        $body = $response->encodeJsonBody();

        self::assertStringContainsString('"title":"Товар"', $body);
        self::assertStringNotContainsString('\\u', $body);
        self::assertStringContainsString('"url":"https://shop.example/a/b"', $body);
        self::assertStringNotContainsString('\\/', $body);
    }

    public function testStaticEncodeJsonMatchesInstanceHelper(): void
    {
        $payload = ['ok' => true, 'path' => '/x/y'];
        self::assertSame(
            (new Response($payload))->encodeJsonBody(),
            Response::encodeJson($payload)
        );
    }

    public function testNonUtf8EncodeFailureFallsBackTo500Envelope(): void
    {
        $modx = $this->makeLogger();
        // NAN cannot be JSON-encoded even with UTF-8 substitute.
        $response = new Response(['value' => NAN]);
        $body = $response->encodeJsonBody($modx);

        self::assertNotSame('', $body);
        self::assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $decoded = json_decode($body, true);
        self::assertFalse($decoded['success']);
        self::assertSame(ApiErrorCode::INTERNAL_ERROR, $decoded['error_code']);
        self::assertGreaterThanOrEqual(1, count($modx->logs));
    }

    private function makeLogger(): object
    {
        return new class {
            /** @var list<string> */
            public array $logs = [];

            public function log($level, $message): void
            {
                $this->logs[] = (string) $message;
            }
        };
    }
}
