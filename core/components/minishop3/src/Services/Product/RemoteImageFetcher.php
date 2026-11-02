<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Utils\PublicHostPolicy;
use MODX\Revolution\modX;

/**
 * Загрузка изображения по ссылке для галереи товара.
 *
 * Раньше это делал сам процессор одним `fopen($url)` — то есть сервер ходил
 * по любому адресу, который прислал пользователь, без ограничений по размеру
 * и времени (#923). Здесь та же возможность, но как отдельная фича с защитой:
 *
 * - только http и https;
 * - имя разрешается заранее, и каждый полученный адрес проверяется
 *   {@see PublicHostPolicy};
 * - адрес закрепляется через CURLOPT_RESOLVE: без этого проверка бесполезна,
 *   потому что между проверкой и подключением имя переразрешается на
 *   внутренний адрес (DNS rebinding);
 * - редиректы не следуются автоматически, каждый прыжок проверяется заново;
 * - объём обрывается в обработчике записи, а не по Content-Length: заголовка
 *   может не быть, и он может врать;
 * - таймауты на подключение и на всю операцию.
 */
final class RemoteImageFetcher
{
    /** Сколько прыжков редиректа согласны пройти. */
    private const MAX_REDIRECTS = 3;

    private const CONNECT_TIMEOUT = 5;

    private const TOTAL_TIMEOUT = 30;

    public function __construct(private modX $modx)
    {
    }

    /**
     * Скачать изображение в указанный файл.
     *
     * @param string $url ссылка от пользователя
     * @param string $target куда писать (существующий временный файл)
     * @param int $maxBytes предел объёма
     * @return array{ok: true, name: string}|array{ok: false, reason: string}
     *         reason — ключ лексикона
     */
    public function fetchTo(string $url, string $target, int $maxBytes): array
    {
        if (!function_exists('curl_init')) {
            return $this->fail('ms3_err_gallery_url_no_curl', 'curl extension is missing');
        }

        $current = trim($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $checked = $this->validate($current);
            if (!$checked['ok']) {
                return $checked;
            }

            $result = $this->request($checked['url'], $checked['host'], $checked['port'], $checked['ip'], $target, $maxBytes);

            if (!$result['ok']) {
                return $result;
            }

            if ($result['redirect'] === null) {
                return ['ok' => true, 'name' => $this->fileName($checked['url'], $result['contentType'])];
            }

            // Относительный Location тоже бывает.
            $current = $this->absoluteUrl($result['redirect'], $checked['url']);
        }

        return $this->fail('ms3_err_gallery_url_too_many_redirects', "too many redirects for {$url}");
    }

    /**
     * Разбор и проверка ссылки вместе с адресами имени.
     *
     * @return array{ok: true, url: string, host: string, port: int, ip: string}|array{ok: false, reason: string}
     */
    private function validate(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $this->fail('ms3_err_gallery_url_invalid', "cannot parse {$url}");
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $this->fail('ms3_err_gallery_url_scheme', "scheme {$scheme} rejected for {$url}");
        }

        // `user:pass@host` в ссылке — приём для подмены разбора и утечки
        // учётных данных в чужой запрос.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->fail('ms3_err_gallery_url_invalid', "credentials in url rejected: {$url}");
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        $ips = PublicHostPolicy::resolveHost($host);
        if ($ips === []) {
            return $this->fail('ms3_err_gallery_url_unresolved', "cannot resolve {$host}");
        }

        // Если хотя бы один адрес внутренний — отказ целиком. Выбирать
        // «хороший» из набора нельзя: это и есть лазейка.
        foreach ($ips as $ip) {
            if (!PublicHostPolicy::isPublicIp($ip)) {
                return $this->fail('ms3_err_gallery_url_private', "private address {$ip} for {$host}");
            }
        }

        return ['ok' => true, 'url' => $url, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /**
     * Один запрос без следования редиректам.
     *
     * @return array{ok: true, redirect: string|null, contentType: string}|array{ok: false, reason: string}
     */
    private function request(
        string $url,
        string $host,
        int $port,
        string $ip,
        string $target,
        int $maxBytes
    ): array {
        $handle = fopen($target, 'wb');
        if (!$handle) {
            return $this->fail('ms3_err_gallery_url_failed', "cannot open {$target}");
        }

        $written = 0;
        $tooBig = false;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            // Закрепляем проверенный адрес: имя больше не переразрешается.
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'MiniShop3 gallery image fetch',
            CURLOPT_WRITEFUNCTION => function ($_curl, string $chunk) use (
                $handle,
                $maxBytes,
                &$written,
                &$tooBig
            ): int {
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    $tooBig = true;

                    return 0; // обрыв передачи
                }

                fwrite($handle, $chunk);

                return strlen($chunk);
            },
        ]);

        curl_exec($curl);

        $errno = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $redirect = (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        $error = curl_error($curl);

        curl_close($curl);
        fclose($handle);

        if ($tooBig) {
            return $this->fail('ms3_err_gallery_too_large', "remote file over {$maxBytes} bytes: {$url}");
        }

        if ($errno !== 0) {
            return $this->fail('ms3_err_gallery_url_failed', "curl error {$errno}: {$error}");
        }

        if ($status >= 300 && $status < 400) {
            if ($redirect === '') {
                return $this->fail('ms3_err_gallery_url_failed', "redirect without location: {$url}");
            }

            return ['ok' => true, 'redirect' => $redirect, 'contentType' => $contentType];
        }

        if ($status !== 200) {
            return $this->fail('ms3_err_gallery_url_failed', "http {$status} for {$url}");
        }

        return ['ok' => true, 'redirect' => null, 'contentType' => $contentType];
    }

    /**
     * Имя файла из ссылки, при необходимости с расширением из Content-Type.
     */
    private function fileName(string $url, string $contentType): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $name = basename($path);

        // Служебные символы в имени не нужны: дальше его чистит сам процессор,
        // но пустое или бессмысленное имя лучше заменить здесь.
        $name = preg_replace('/[^\w.\-]+/u', '', $name) ?? '';

        if ($name === '' || !str_contains($name, '.')) {
            $extension = self::extensionFromContentType($contentType);
            $base = $name !== '' ? $name : 'image';
            $name = $extension === null ? $base : $base . '.' . $extension;
        }

        return $name;
    }

    private static function extensionFromContentType(string $contentType): ?string
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        return match ($type) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }

    /**
     * Приведение относительного Location к абсолютному адресу.
     */
    private function absoluteUrl(string $location, string $base): string
    {
        if (parse_url($location, PHP_URL_HOST) !== null) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $path = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_contains($path, '/') ? substr($path, 0, strrpos($path, '/') + 1) : '/', '');

        return "{$scheme}://{$host}{$port}{$dir}{$location}";
    }

    /**
     * @return array{ok: false, reason: string}
     */
    private function fail(string $lexiconKey, string $logMessage): array
    {
        $this->modx->log(modX::LOG_LEVEL_ERROR, "[RemoteImageFetcher] {$logMessage}");

        return ['ok' => false, 'reason' => $lexiconKey];
    }
}
