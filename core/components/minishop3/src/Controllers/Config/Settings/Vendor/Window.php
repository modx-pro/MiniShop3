<?php

namespace MiniShop3\Controllers\Config\Settings\Vendor;

use MODX\Revolution\modX;

class Window
{
    private $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:manager');
    }

    public function getCreate(): array
    {
        $fileJSON = $this->getConfigFromJSON();
        return $this->getCreateLayout($fileJSON);
    }

    public function getUpdate(): array
    {
        $fileJSON = $this->getConfigFromJSON();
        return $this->getUpdateLayout($fileJSON);
    }

    protected function getConfigFromJSON(): array
    {
        $file = MODX_CORE_PATH . 'components/minishop3/config/mgr/settings/vendor/window.json';
        if (!file_exists($file)) {
            $this->modx->log(1, 'file not found: ' . $file);
            return [];
        }

        $fileData = file_get_contents($file);
        if (empty($fileData)) {
            return [];
        }
        $fileJSON = json_decode($fileData, true);
        if (!is_array($fileJSON)) {
            $this->modx->log(1, 'not array');
            return [];
        }

        return $fileJSON;
    }

    private function getCreateLayout($fileJSON): array
    {
        $idPrefix = 'ms3-window-vendor-create';
        $layout = [];

        foreach ($fileJSON as $row) {
            if (isset($row['name'])) {
                if (empty($row['id'])) {
                    $row['id'] = $idPrefix . '-' . $row['name'];
                }

                if (empty($row['fieldLabel'])) {
                    $row['fieldLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                }

                if (empty($row['anchor'])) {
                    $row['anchor'] = '99%';
                }
            }

            if (isset($row['layout']) && !empty($row['items'])) {
                foreach ($row['items'] as $key => $item) {
                    if (empty($item['items'])) {
                        continue;
                    }

                    foreach ($item['items'] as $k => $item_2) {
                        if (empty($item_2['id'])) {
                            $row['items'][$key]['items'][$k]['id'] = $idPrefix . '-' . $item_2['name'];
                        }

                        if (empty($item_2['fieldLabel'])) {
                            $row['items'][$key]['items'][$k]['fieldLabel'] = $this->modx->lexicon(
                                'ms3_' . $item_2['name']
                            );
                        }

                        if (empty($item_2['anchor'])) {
                            $row['items'][$key]['items'][$k]['anchor'] = '99%';
                        }
                    }
                }
            }

            $layout[] = $row;
        }

        return $layout;
    }

    private function getUpdateLayout($fileJSON): array
    {
        $idPrefix = 'ms3-window-vendor-update';
        $layout = [];

        $layout[] = [
            'name' => 'id',
            'xtype' => 'hidden'
        ];

        foreach ($fileJSON as $row) {
            if (isset($row['name'])) {
                if (empty($row['id'])) {
                    $row['id'] = $idPrefix . '-' . $row['name'];
                }

                if (empty($row['fieldLabel'])) {
                    $row['fieldLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                }

                if (empty($row['anchor'])) {
                    $row['anchor'] = '99%';
                }
            }

            if (isset($row['layout']) && !empty($row['items'])) {
                foreach ($row['items'] as $key => $item) {
                    if (empty($item['items'])) {
                        continue;
                    }

                    foreach ($item['items'] as $k => $item_2) {
                        if (empty($item_2['id'])) {
                            $row['items'][$key]['items'][$k]['id'] = $idPrefix . '-' . $item_2['name'];
                        }

                        if (empty($item_2['fieldLabel'])) {
                            $row['items'][$key]['items'][$k]['fieldLabel'] = $this->modx->lexicon(
                                'ms3_' . $item_2['name']
                            );
                        }

                        if (empty($item_2['anchor'])) {
                            $row['items'][$key]['items'][$k]['anchor'] = '99%';
                        }
                    }
                }
            }

            $layout[] = $row;
        }

        return $layout;
    }
}
