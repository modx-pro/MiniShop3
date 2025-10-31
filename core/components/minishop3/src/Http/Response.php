<?php

namespace MiniShop3\Http;

use MODX\Revolution\modX;

/**
 * HTTP Response helper для Web API
 *
 * Формирует JSON ответы с правильными HTTP статус-кодами.
 * Заменяет старые методы Utils::success() и Utils::error().
 */
class Response
{
    /** @var modX */
    private modX $modx;

    /**
     * HTTP статус-коды
     */
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * @param modX $modx Экземпляр MODX для доступа к lexicon
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Отправить JSON ответ с указанным статус-кодом
     *
     * @param array $data Данные для JSON
     * @param int $status HTTP статус-код
     * @return void
     */
    public function json(array $data, int $status = self::HTTP_OK): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Успешный ответ
     *
     * @param string $message Ключ лексикона или текст сообщения
     * @param array $data Дополнительные данные
     * @param array $placeholders Плейсхолдеры для лексикона
     * @param int $status HTTP статус-код (по умолчанию 200)
     * @return void
     */
    public function success(
        string $message = '',
        array $data = [],
        array $placeholders = [],
        int $status = self::HTTP_OK
    ): void {
        $this->json([
            'success' => true,
            'message' => $this->translate($message, $placeholders),
            'data' => $data,
        ], $status);
    }

    /**
     * Ответ об успешном создании ресурса
     *
     * @param string $message Ключ лексикона или текст сообщения
     * @param array $data Данные созданного ресурса
     * @param array $placeholders Плейсхолдеры для лексикона
     * @return void
     */
    public function created(
        string $message = '',
        array $data = [],
        array $placeholders = []
    ): void {
        $this->success($message, $data, $placeholders, self::HTTP_CREATED);
    }

    /**
     * Ответ без содержимого (например, при успешном удалении)
     *
     * @return void
     */
    public function noContent(): void
    {
        http_response_code(self::HTTP_NO_CONTENT);
        exit;
    }

    /**
     * Ответ с ошибкой
     *
     * @param string $message Ключ лексикона или текст сообщения
     * @param array $errors Детали ошибок (для валидации)
     * @param array $placeholders Плейсхолдеры для лексикона
     * @param int $status HTTP статус-код (по умолчанию 400)
     * @return void
     */
    public function error(
        string $message = '',
        array $errors = [],
        array $placeholders = [],
        int $status = self::HTTP_BAD_REQUEST
    ): void {
        $response = [
            'success' => false,
            'message' => $this->translate($message, $placeholders),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    /**
     * Ответ 400 Bad Request
     *
     * @param string $message Сообщение об ошибке
     * @param array $errors Детали ошибок
     * @param array $placeholders Плейсхолдеры для лексикона
     * @return void
     */
    public function badRequest(
        string $message,
        array $errors = [],
        array $placeholders = []
    ): void {
        $this->error($message, $errors, $placeholders, self::HTTP_BAD_REQUEST);
    }

    /**
     * Ответ 401 Unauthorized
     *
     * @param string $message Сообщение об ошибке (по умолчанию "ms3_err_token")
     * @return void
     */
    public function unauthorized(string $message = 'ms3_err_token'): void
    {
        $this->error($message, [], [], self::HTTP_UNAUTHORIZED);
    }

    /**
     * Ответ 403 Forbidden
     *
     * @param string $message Сообщение об ошибке
     * @return void
     */
    public function forbidden(string $message = 'ms3_err_permission_denied'): void
    {
        $this->error($message, [], [], self::HTTP_FORBIDDEN);
    }

    /**
     * Ответ 404 Not Found
     *
     * @param string $message Сообщение об ошибке
     * @param array $placeholders Плейсхолдеры для лексикона
     * @return void
     */
    public function notFound(string $message, array $placeholders = []): void
    {
        $this->error($message, [], $placeholders, self::HTTP_NOT_FOUND);
    }

    /**
     * Ответ 422 Unprocessable Entity (ошибки валидации)
     *
     * @param string $message Общее сообщение об ошибке
     * @param array $errors Детали ошибок валидации
     * @return void
     */
    public function validationError(string $message, array $errors = []): void
    {
        $this->error($message, $errors, [], self::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Ответ 429 Too Many Requests
     *
     * @param string $message Сообщение об ошибке
     * @param int $retryAfter Через сколько секунд можно повторить запрос
     * @return void
     */
    public function tooManyRequests(string $message = 'ms3_err_rate_limit', int $retryAfter = 60): void
    {
        header("Retry-After: $retryAfter");
        $this->error($message, [], [], self::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Ответ 500 Internal Server Error
     *
     * @param string $message Сообщение об ошибке
     * @param array $details Детали ошибки (только в dev режиме)
     * @return void
     */
    public function serverError(string $message = 'ms3_err_unknown', array $details = []): void
    {
        $response = [
            'success' => false,
            'message' => $this->translate($message),
        ];

        // В dev режиме добавляем детали ошибки
        if (!empty($details) && $this->modx->getOption('debug', null, false)) {
            $response['details'] = $details;
        }

        $this->json($response, self::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Перевести ключ лексикона или вернуть текст как есть
     *
     * @param string $message Ключ лексикона или текст
     * @param array $placeholders Плейсхолдеры для лексикона
     * @return string
     */
    private function translate(string $message, array $placeholders = []): string
    {
        if (empty($message)) {
            return '';
        }

        // Попытаться перевести как ключ лексикона
        $translated = $this->modx->lexicon($message, $placeholders);

        // Если ключ не найден, lexicon() вернёт сам ключ
        // В этом случае вернём оригинальное сообщение
        return $translated;
    }
}
