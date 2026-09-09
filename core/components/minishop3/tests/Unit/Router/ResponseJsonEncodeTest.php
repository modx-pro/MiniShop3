<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Router;

use MiniShop3\Processors\Api\ProcessesManagerConnectorRouteTrait;
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

    public function testSanitizeUtf8ForJsonSubstitutesInvalidBytes(): void
    {
        $modx = $this->makeLogger();
        $clean = Response::sanitizeUtf8ForJson(['label' => "ab\xC3\x28cd"], $modx);

        self::assertIsArray($clean);
        self::assertIsString($clean['label']);
        self::assertStringContainsString("\u{FFFD}", $clean['label']);
        self::assertNotFalse(json_encode($clean));
        self::assertNotEmpty($modx->logs);
        self::assertStringContainsString('label', $modx->logs[0]);
        self::assertStringContainsString('hex=', $modx->logs[0]);
    }

    public function testSanitizeUtf8ForJsonLeavesValidDataUnchanged(): void
    {
        $payload = ['title' => 'Товар', 'n' => 1];
        self::assertSame($payload, Response::sanitizeUtf8ForJson($payload));
    }

    public function testSanitizeUtf8ForJsonReturnsNullWhenUnencodable(): void
    {
        $modx = $this->makeLogger();
        self::assertNull(Response::sanitizeUtf8ForJson(['value' => NAN], $modx));
        self::assertNotEmpty($modx->logs);
    }

    /**
     * Manager connector path: trait respondFromRouter → Processor success → xPDO-style json_encode (#671).
     */
    public function testManagerConnectorRespondFromRouterSurvivesBareJsonEncode(): void
    {
        $modx = $this->makeLogger();
        $harness = $this->makeConnectorHarness($modx);
        $apiData = [
            'success' => true,
            'message' => null,
            'data' => ['fields' => [['label' => "bad\xC3\x28"]]],
        ];

        $result = $harness->exposeRespond($apiData, 200);

        self::assertTrue($result['success']);
        self::assertArrayHasKey('object', $result);
        // Mirrors xPDO::toJSON / modConnectorResponse (no flags, no false check).
        $encoded = json_encode($result);
        self::assertNotFalse($encoded);
        self::assertNotSame('', $encoded);
        $decoded = json_decode($encoded, true);
        self::assertStringContainsString("\u{FFFD}", $decoded['object']['fields'][0]['label']);
        self::assertNotEmpty($modx->logs);
    }

    public function testManagerConnectorRespondFromRouterFailsClosedOnNan(): void
    {
        $modx = $this->makeLogger();
        $harness = $this->makeConnectorHarness($modx);
        $result = $harness->exposeRespond(['success' => false, 'message' => 'x', 'value' => NAN], 400);

        self::assertFalse($result['success']);
        self::assertSame('Internal server error', $result['message']);
        self::assertSame(['code' => 500], $result['object']);
        $encoded = json_encode($result);
        self::assertNotFalse($encoded);
        self::assertNotSame('', $encoded);
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

    /**
     * Minimal Processor stand-in that exercises trait respondFromRouter (#671).
     */
    private function makeConnectorHarness(object $modx): object
    {
        return new class ($modx) {
            use ProcessesManagerConnectorRouteTrait;

            public object $modx;

            public function __construct(object $modx)
            {
                $this->modx = $modx;
            }

            public function exposeRespond(mixed $responseData, int $statusCode): mixed
            {
                return $this->respondFromRouter($responseData, $statusCode);
            }

            public function success($message = '', $object = null): array
            {
                return [
                    'success' => true,
                    'message' => $message,
                    'object' => $object,
                ];
            }

            public function failure($message = '', $object = null): array
            {
                return [
                    'success' => false,
                    'message' => $message,
                    'object' => $object,
                ];
            }

            public function getProperty($key, $default = null): mixed
            {
                return $default;
            }
        };
    }
}
