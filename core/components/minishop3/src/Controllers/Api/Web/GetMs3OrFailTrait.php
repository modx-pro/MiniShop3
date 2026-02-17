<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\Response;

/**
 * Трейт для проверки наличия сервиса ms3 перед вызовом get() (Issue #68).
 * Избегает необработанного Exception при удалённом/битом компоненте.
 */
trait GetMs3OrFailTrait
{
    /**
     * Получить сервис MiniShop3 или null при отсутствии.
     *
     * @return \MiniShop3\MiniShop3|null
     */
    protected function getMs3OrFail(): ?\MiniShop3\MiniShop3
    {
        if (!$this->modx->services->has('ms3')) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
            return null;
        }
        return $this->modx->services->get('ms3');
    }

    /**
     * Ответ 503 «Service unavailable» в формате array для возврата из action-методов.
     *
     * @return array
     */
    protected function serviceUnavailableResponse(): array
    {
        return Response::error('Service unavailable', 503)->getData();
    }
}
