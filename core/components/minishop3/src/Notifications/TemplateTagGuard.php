<?php

namespace MiniShop3\Notifications;

/**
 * Маскировка открывающих последовательностей тегов в данных покупателя (#888, #929).
 *
 * Fenom подставляет значения плейсхолдеров в вывод как есть, а идущий следом
 * парсер читает готовое тело уже как шаблон. С установленным pdoTools
 * `$modx->getParser()` — это Fenom, а не разбор MODX-тегов, поэтому из
 * комментария покупателя исполнялись и `[[++setting]]`, и
 * `{$_modx->runSnippet(...)}`.
 *
 * Данные уходят в рендер с подменёнными открывающими последовательностями
 * и возвращаются к исходному виду уже после парсера: шаблон по-прежнему
 * может пользоваться и MODX-тегами, и Fenom, а текст покупателя остаётся
 * текстом.
 *
 * Метки — цифры, чтобы модификатор вроде `|upper` не изменил маркер и
 * разворачивание не прошло мимо.
 */
class TemplateTagGuard
{
    private const TAG_OPENERS = [
        '[[' => '1',
        '{' => '2',
    ];

    /**
     * Разовая метка для подмены открывающих последовательностей тегов.
     *
     * Случайная на каждое сообщение: иначе покупатель мог бы вписать саму метку
     * в комментарий и получить обратно `[[` или `{` после разворачивания.
     *
     * Только цифры — модификатор шаблона вроде `|upper` не должен её изменить,
     * иначе разворачивание пройдёт мимо и читатель увидит маркер.
     *
     * От модификатора, который режет строку (`|truncate`), это не спасает:
     * обрезанный маркер развернуть не выйдет, и в тексте останутся цифры
     * вместо `[[` или `{`. Косметика, и только если покупатель вписал в поле
     * открывающую последовательность.
     */
    public function nonce(): string
    {
        return '9' . random_int(10 ** 15, 10 ** 16 - 1) . '9';
    }

    /**
     * Подменить открывающие последовательности тегов во всех строках данных.
     *
     * Рекурсивно, потому что плейсхолдеры — вложенные массивы (order, address,
     * customer, products). Объекты не обходим: в плейсхолдеры они не попадают,
     * всё приводится к массивам через toArray().
     */
    public function neutralize(mixed $value, string $nonce): mixed
    {
        if (is_array($value)) {
            return array_map(fn($item): mixed => $this->neutralize($item, $nonce), $value);
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $search = [];
        $replace = [];
        foreach (self::TAG_OPENERS as $opener => $label) {
            $search[] = $opener;
            $replace[] = $nonce . $label;
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * Вернуть подменённые последовательности на место — после всех парсеров.
     */
    public function restore(string $text, string $nonce): string
    {
        $search = [];
        $replace = [];
        foreach (self::TAG_OPENERS as $opener => $label) {
            $search[] = $nonce . $label;
            $replace[] = $opener;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Экранировать строки для Telegram parse_mode=HTML.
     *
     * Шаблон с разметкой не трогаем: экранируются только значения плейсхолдеров,
     * до neutralize(). Нестроковые скаляры проходят как есть.
     */
    public function escapeHtml(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn($item): mixed => $this->escapeHtml($item), $value);
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
