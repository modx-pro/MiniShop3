<?php

namespace MiniShop3\Controllers\Options;

use MiniShop3\Controllers\Options\Types\msOptionType;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOption;
use MODX\Revolution\modX;

class Options
{
    private $modx;
    private $ms3;
    private $config = [];
    /** @var array $optionTypes */
    private $optionTypes = [];

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;
        $this->config = [
            'types_dir' => $this->ms3->config['corePath'] . 'src/Controllers/Options/Types'
        ];
    }

    /**
     * @return array
     */
    public function loadOptionTypeList()
    {
        $files = scandir($this->config['types_dir']);
        $list = [];

        foreach ($files as $file) {
            if ($file === 'msOptionType.php') {
                continue;
            }
            if (preg_match('/.*?\.php$/i', $file)) {
                $list[] = str_replace('.php', '', $file);
            }
        }

        return $list;
    }

    /**
     * @param msOption $option
     *
     * @return null|msOptionType
     */
    public function getOptionType($option)
    {
        $className = $this->loadOptionType($option->get('type'));

        if (class_exists($className)) {
            return new $className($option);
        } else {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Could not initialize miniShop3 option type class: "' . $className . '"'
            );

            return null;
        }
    }

    /**
     * @param string $type
     *
     * @return mixed
     */
    public function loadOptionType($type)
    {
        $o = [];
        $s = explode(' ', str_replace(['_', '-'], ' ', $type));
        foreach ($s as $k) {
            $o[] = ucfirst($k);
        }
        $fileName = implode('', $o);

        $typePath = $this->config['types_dir'] . '/' . $fileName . '.php';

        if (array_key_exists($typePath, $this->optionTypes)) {
            $className = $this->optionTypes[$typePath];
        } else {
            $className = include_once $typePath;
            $this->optionTypes[$typePath] = $className;
        }

        return $className;
    }

    /**
     * @param array $options
     * @param array|string $sorting
     *
     * @return array
     */
    public function sortOptionValues(array $options, $sorting)
    {
        if (!empty($sorting)) {
            $sorting = array_map('trim', is_array($sorting) ? $sorting : explode(',', $sorting));
            foreach ($sorting as $sort) {
                @list($key, $order, $type, $first) = explode(':', $sort);
                if (array_key_exists($key, $options)) {
                    $order = empty($order) ? SORT_ASC : constant($order);
                    $type = empty($type) ? SORT_STRING : constant($type);

                    $values = &$options[$key];
                    if (isset($options[$key]['value'])) {
                        $values = &$options[$key]['value'];
                    }

                    array_multisort($values, $order, $type);

                    if (!is_null($first) && ($index = array_search($first, $values)) !== false) {
                        unset($values[$index]);
                        array_unshift($values, $first);
                    }
                }
            }
        }

        return $options;
    }
}
