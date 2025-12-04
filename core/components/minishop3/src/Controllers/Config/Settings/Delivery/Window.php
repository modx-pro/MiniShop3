<?php

namespace MiniShop3\Controllers\Config\Settings\Delivery;

use MODX\Revolution\modX;

class Window
{
    private $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function getCreate(): array
    {
        return $this->getCreateLayout();
    }

    public function getUpdate(): array
    {
        $output = [];
        $output['info'] = $this->getInfoLayout();
        $output['settings'] = $this->getSettingsLayout();
        return $output;
    }

    protected function getConfigFromJSON($file): array
    {
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

    private function getCreateLayout(): array
    {
        $file = MODX_CORE_PATH . 'components/minishop3/config/mgr/settings/delivery/window-info.json';
        $fileJSON = $this->getConfigFromJSON($file);

        $idPrefix = 'ms3-window-delivery-create';
        $layout = [];

        foreach ($fileJSON as $row) {
            if (isset($row['name'])) {

                if (empty($row['id'])) {
                    $row['id'] = $idPrefix . '-' . $row['name'];
                }

                if (empty($row['fieldLabel'])) {
                    $row['fieldLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                } else {
                    $row['fieldLabel'] = $this->modx->lexicon($row['fieldLabel']);
                }

                if ($row['xtype'] == 'xcheckbox') {
                    if (empty($row['boxLabel'])) {
                        $row['boxLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                    } else {
                        $row['boxLabel'] = $this->modx->lexicon("ms3_active");
                    }
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

    private function getInfoLayout(): array
    {
        $file = MODX_CORE_PATH . 'components/minishop3/config/mgr/settings/delivery/window-info.json';
        $fileJSON = $this->getConfigFromJSON($file);
        $idPrefix = 'ms3-window-delivery-update';
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
                } else {
                    $row['fieldLabel'] = $this->modx->lexicon($row['fieldLabel']);
                }
                if ($row['xtype'] == 'xcheckbox') {
                    if (empty($row['boxLabel'])) {
                        $row['boxLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                    } else {
                        $row['boxLabel'] = $this->modx->lexicon($row['boxLabel']);
                    }
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

    private function getSettingsLayout(): array
    {
        $file = MODX_CORE_PATH . 'components/minishop3/config/mgr/settings/delivery/window-settings.json';
        $fileJSON = $this->getConfigFromJSON($file);

        $idPrefix = 'ms3-window-delivery-update';
        $layout = [];

        foreach ($fileJSON as $row) {
            if (isset($row['name'])) {
                if (empty($row['id'])) {
                    $row['id'] = $idPrefix . '-' . $row['name'];
                }

                if (empty($row['fieldLabel'])) {
                    $row['fieldLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                } else {
                    $row['fieldLabel'] = $this->modx->lexicon($row['fieldLabel']);
                }
                if ($row['xtype'] == 'xcheckbox') {
                    if (empty($row['boxLabel'])) {
                        $row['boxLabel'] = $this->modx->lexicon('ms3_' . $row['name']);
                    } else {
                        $row['boxLabel'] = $this->modx->lexicon($row['boxLabel']);
                    }
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
