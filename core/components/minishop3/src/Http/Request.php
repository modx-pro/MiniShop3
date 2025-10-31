<?php

namespace MiniShop3\Http;

/**
 * HTTP Request wrapper для Web API
 *
 * Инкапсулирует данные запроса и предоставляет удобный API для доступа к ним.
 * Не является полноценной PSR-7 реализацией, но следует тем же принципам.
 */
class Request
{
    /** @var array Данные запроса (POST/GET параметры) */
    private array $data;

    /** @var array HTTP заголовки */
    private array $headers;

    /** @var array Параметры маршрута из FastRoute */
    private array $routeParams;

    /** @var string HTTP метод */
    private string $method;

    /** @var string URI запроса */
    private string $uri;

    /**
     * @param array $data POST/GET данные
     * @param array $headers HTTP заголовки
     * @param array $routeParams Параметры из маршрута
     * @param string $method HTTP метод
     * @param string $uri URI запроса
     */
    public function __construct(
        array $data = [],
        array $headers = [],
        array $routeParams = [],
        string $method = 'GET',
        string $uri = ''
    ) {
        $this->data = $data;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->routeParams = $routeParams;
        $this->method = strtoupper($method);
        $this->uri = $uri;
    }

    /**
     * Создать Request из глобальных переменных PHP
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $data = $_REQUEST;

        // Получаем заголовки
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        // Добавляем Content-Type если есть
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return new self($data, $headers, [], $method, $uri);
    }

    /**
     * Получить значение из данных запроса
     *
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Получить все данные запроса
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Получить только указанные ключи из данных
     *
     * @param array $keys Массив ключей
     * @return array
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Получить все данные кроме указанных ключей
     *
     * @param array $keys Массив ключей для исключения
     * @return array
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    /**
     * Проверить наличие ключа в данных
     *
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Получить значение заголовка
     *
     * @param string $key Имя заголовка (регистронезависимо)
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Получить все заголовки
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Получить параметр маршрута из FastRoute
     *
     * @param string $key Имя параметра
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function route(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Установить параметры маршрута (вызывается из роутера)
     *
     * @param array $params Параметры маршрута
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Получить HTTP метод
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Получить URI запроса
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Проверить, является ли запрос AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Проверить, является ли метод POST
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Проверить, является ли метод GET
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Проверить, является ли метод PUT
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Проверить, является ли метод DELETE
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }
}
