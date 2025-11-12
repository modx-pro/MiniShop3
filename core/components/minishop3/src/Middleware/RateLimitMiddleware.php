<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;

/**
 * Middleware для ограничения частоты запросов (Rate Limiting)
 *
 * Защита от DDoS атак и злоупотреблений API.
 * Использует простой механизм на основе файлового кеша.
 *
 * TODO: В продакшене рекомендуется использовать Redis/Memcached для rate limiting
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var int Максимальное количество запросов */
    private int $maxAttempts;

    /** @var int Период времени в секундах */
    private int $decaySeconds;

    /** @var string Путь к директории для хранения данных rate limit */
    private string $storagePath;

    /**
     * @param int $maxAttempts Максимальное количество запросов (по умолчанию 60)
     * @param int $decaySeconds Период времени в секундах (по умолчанию 60 - 1 минута)
     * @param string $storagePath Путь к директории хранения (по умолчанию sys_get_temp_dir())
     */
    public function __construct(
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $storagePath = ''
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->storagePath = !empty($storagePath) ? $storagePath : sys_get_temp_dir();
    }

    /**
     * Обработать запрос
     *
     * @param array $params URL параметры из роутера
     * @return Response|null Вернуть Response для прерывания, или null для продолжения
     */
    public function handle(array $params)
    {
        $key = $this->resolveRequestKey();

        $attempts = $this->getAttempts($key);
        $resetTime = $this->getResetTime($key);

        // Если время истекло, сбрасываем счётчик
        if (time() >= $resetTime) {
            $this->resetAttempts($key);
            $attempts = 0;
        }

        // Проверяем лимит
        if ($attempts >= $this->maxAttempts) {
            $retryAfter = $resetTime - time();
            header("Retry-After: $retryAfter");
            return Response::error('ms3_err_rate_limit', 429);
        }

        // Увеличиваем счётчик
        $this->incrementAttempts($key);

        // Устанавливаем заголовки rate limit
        $this->setRateLimitHeaders($attempts + 1, $resetTime);

        return null; // Продолжить выполнение
    }

    /**
     * Получить ключ для идентификации клиента
     *
     * @return string
     */
    private function resolveRequestKey(): string
    {
        // Используем комбинацию IP и токена (если есть)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $token = $_SERVER['HTTP_MS3TOKEN'] ?? '';

        return 'rate_limit:' . md5($ip . ':' . $token);
    }

    /**
     * Получить количество попыток
     *
     * @param string $key Ключ
     * @return int
     */
    private function getAttempts(string $key): int
    {
        $file = $this->getFilePath($key, 'attempts');

        if (!file_exists($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        return (int)$content;
    }

    /**
     * Получить время сброса счётчика
     *
     * @param string $key Ключ
     * @return int Unix timestamp
     */
    private function getResetTime(string $key): int
    {
        $file = $this->getFilePath($key, 'reset');

        if (!file_exists($file)) {
            return time() + $this->decaySeconds;
        }

        $content = file_get_contents($file);
        return (int)$content;
    }

    /**
     * Увеличить счётчик попыток
     *
     * @param string $key Ключ
     * @return void
     */
    private function incrementAttempts(string $key): void
    {
        $attempts = $this->getAttempts($key) + 1;
        $resetTime = $this->getResetTime($key);

        // Если это первая попытка в периоде, устанавливаем время сброса
        if ($attempts === 1) {
            $resetTime = time() + $this->decaySeconds;
        }

        file_put_contents($this->getFilePath($key, 'attempts'), $attempts);
        file_put_contents($this->getFilePath($key, 'reset'), $resetTime);
    }

    /**
     * Сбросить счётчик попыток
     *
     * @param string $key Ключ
     * @return void
     */
    private function resetAttempts(string $key): void
    {
        @unlink($this->getFilePath($key, 'attempts'));
        @unlink($this->getFilePath($key, 'reset'));
    }

    /**
     * Получить путь к файлу для хранения данных
     *
     * @param string $key Ключ
     * @param string $type Тип данных (attempts или reset)
     * @return string
     */
    private function getFilePath(string $key, string $type): string
    {
        return $this->storagePath . '/' . $key . '_' . $type . '.tmp';
    }

    /**
     * Установить заголовки rate limit
     *
     * @param int $attempts Текущее количество попыток
     * @param int $resetTime Время сброса счётчика
     * @return void
     */
    private function setRateLimitHeaders(int $attempts, int $resetTime): void
    {
        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $this->maxAttempts - $attempts));
        header('X-RateLimit-Reset: ' . $resetTime);
    }
}
