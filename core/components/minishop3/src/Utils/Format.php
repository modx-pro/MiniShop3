<?php

namespace MiniShop3\Utils;

use MiniShop3\MiniShop3;

class Format
{
    private $modx;
    private $ms3;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;
    }

    /**
     * Function for formatting dates
     *
     * @param string $date Source date
     *
     * @return string $date Formatted date
     */
    public function date($date = '')
    {
        $df = $this->modx->getOption('ms3_date_format', null, 'd.m.Y H:M');

        if (!empty($date) && $date !== '0000-00-00 00:00:00') {
            $date = date_create($date);
            return date_format($date, $df);
        }

        return '&nbsp;';
    }

    /**
     * Function for price format
     *
     * @param $price
     *
     * @return int|mixed|string
     */
    public function price($price = 0)
    {
        $format = json_decode($this->modx->getOption('ms3_price_format', null, '[2, ".", " "]'), true);
        if (!$format) {
            $format = [2, '.', ' '];
        }
        $price = number_format($price, $format[0], $format[1], $format[2]);

        if ($this->modx->getOption('ms3_price_format_no_zeros', null, true)) {
            $tmp = explode($format[1], $price);
            $tmp[1] = rtrim(rtrim(@$tmp[1], '0'), '.');
            $price = !empty($tmp[1])
                ? $tmp[0] . $format[1] . $tmp[1]
                : $tmp[0];
        }

        return $price;
    }

    /**
     * Function for weight format
     *
     * @param $weight
     *
     * @return int|mixed|string
     */
    public function weight($weight = 0)
    {
        $format = json_decode($this->modx->getOption('ms3_weight_format', null, '[3, ".", " "]'), true);
        if (!$format) {
            $format = [3, '.', ' '];
        }
        $weight = number_format($weight, $format[0], $format[1], $format[2]);

        if ($this->modx->getOption('ms3_weight_format_no_zeros', null, true)) {
            $tmp = explode($format[1], $weight);
            $tmp[1] = rtrim(rtrim(@$tmp[1], '0'), '.');
            $weight = !empty($tmp[1])
                ? $tmp[0] . $format[1] . $tmp[1]
                : $tmp[0];
        }

        return $weight;
    }
}
