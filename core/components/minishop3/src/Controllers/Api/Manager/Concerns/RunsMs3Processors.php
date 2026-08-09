<?php

namespace MiniShop3\Controllers\Api\Manager\Concerns;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

/**
 * Run a MiniShop3 processor and map the result to the HTTP Response envelope (#341).
 *
 * Host class must expose `protected modX $modx`.
 */
trait RunsMs3Processors
{
    /**
     * @param class-string $action Fully-qualified processor class
     */
    protected function runMs3Processor(string $action, array $scriptProperties = []): Response
    {
        $processorResponse = $this->modx->runProcessor(
            $action,
            $scriptProperties,
            [
                'processors_path' => MODX_CORE_PATH . 'components/minishop3/src/Processors/',
            ]
        );

        if (!is_object($processorResponse)) {
            return Response::error(
                'Processor failed',
                HttpStatus::INTERNAL_SERVER_ERROR
            );
        }

        return Response::fromProcessor($processorResponse);
    }

    protected function jsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);

        return is_array($data) ? $data : [];
    }
}
