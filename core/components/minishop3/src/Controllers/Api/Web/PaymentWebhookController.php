<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Controllers\Payment\PaymentWebhookHandlerInterface;
use MiniShop3\Model\msPayment;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Payment\PaymentLifecycleException;
use MiniShop3\Services\Payment\PaymentLifecycleService;
use MiniShop3\Services\Payment\PaymentService;
use MODX\Revolution\modX;

/**
 * Public async payment callback. Auth is provider signature, not customer token.
 */
class PaymentWebhookController
{
    public function __construct(protected modX $modx)
    {
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * POST /api/v1/payment/webhook/{payment_method_id}
     *
     * @param array<string, mixed> $params
     */
    public function handle(array $params = []): Response
    {
        $methodId = (int) ($params['payment_method_id'] ?? 0);
        if ($methodId <= 0) {
            return $this->fail('ms3_err_payment_id_required', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        $method = $this->modx->getObject(msPayment::class, ['id' => $methodId, 'active' => 1]);
        if (!$method instanceof msPayment) {
            return $this->fail('ms3_err_payment_nf', HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        /** @var PaymentService $paymentService */
        $paymentService = $this->modx->services->get('ms3_payment_service');
        $handler = $paymentService->loadPaymentHandler($method);
        if (!$handler instanceof PaymentWebhookHandlerInterface) {
            return $this->fail(
                'ms3_err_payment_webhook_unsupported',
                HttpStatus::BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        }

        $rawBody = $this->readRawRequestBody();
        $payload = $this->decodeJsonObject($rawBody);
        $headers = $this->requestHeaders();
        if ($payload === null) {
            return $this->fail('ms3_err_payment_webhook_invalid', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }
        if (!$handler->verifyWebhook($rawBody, $payload, $headers, $method)) {
            return $this->fail(
                'ms3_err_payment_webhook_unauthorized',
                HttpStatus::UNAUTHORIZED,
                ApiErrorCode::UNAUTHORIZED
            );
        }

        $event = $handler->parseWebhook($payload, $headers);
        if ($event === null) {
            return $this->fail('ms3_err_payment_webhook_invalid', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        /** @var PaymentLifecycleService $lifecycle */
        $lifecycle = $this->modx->services->get('ms3_payment_lifecycle');
        $class = $method->get('class');
        $provider = is_string($class) && $class !== '' ? $class : $handler::class;

        try {
            $attempt = $lifecycle->applyWebhook($event, $methodId, $provider);
        } catch (PaymentLifecycleException $exception) {
            return $this->fromLifecycle($exception);
        } catch (\Throwable $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Payment webhook failed: ' . $exception->getMessage()
            );
            return $this->fail(
                'ms3_err_unknown',
                HttpStatus::INTERNAL_SERVER_ERROR,
                ApiErrorCode::INTERNAL_ERROR
            );
        }

        return Response::success([
            'attempt_id' => $attempt['id'],
            'status' => $attempt['status'],
            'order_id' => $attempt['order_id'],
        ]);
    }

    private function fromLifecycle(PaymentLifecycleException $exception): Response
    {
        [$status, $errorCode] = match ($exception->getKind()) {
            PaymentLifecycleException::KIND_CONFLICT => [HttpStatus::CONFLICT, ApiErrorCode::CONFLICT],
            PaymentLifecycleException::KIND_NOT_FOUND => [HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND],
            default => [HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST],
        };
        $message = $this->modx->lexicon($exception->getLexiconKey(), $exception->getPlaceholders());
        if (!is_string($message) || $message === '') {
            $message = $exception->getMessage();
        }

        return Response::error($message, $status, null, $errorCode);
    }

    private function fail(string $message, int $status, string $errorCode): Response
    {
        $translated = $this->modx->lexicon($message);
        if (is_string($translated) && $translated !== '') {
            $message = $translated;
        }

        return Response::error($message, $status, null, $errorCode);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function readRawRequestBody(): string
    {
        $raw = file_get_contents('php://input');

        return is_string($raw) ? $raw : '';
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $normalized = [];
            foreach ($headers as $name => $value) {
                $normalized[strtolower((string) $name)] = (string) $value;
            }

            return $normalized;
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = is_scalar($value) ? (string) $value : '';
        }

        return $headers;
    }
}
