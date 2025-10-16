<?php

namespace MiniShop3\Router;

/**
 * JSON Response класс
 */
class Response
{
    protected $data;
    protected $statusCode;
    protected $headers = [];

    public function __construct($data, int $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Создать успешный ответ
     */
    public static function success($data = null, string $message = null, int $statusCode = 200): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Создать ответ с ошибкой
     */
    public static function error(string $message, int $statusCode = 400, $errors = null): self
    {
        return new self([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Установить заголовок
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Отправить ответ клиенту
     */
    public function send(): void
    {
        // Установить HTTP статус
        http_response_code($this->statusCode);

        // Установить заголовки
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Вывести JSON
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Получить данные ответа
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Получить статус код
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
