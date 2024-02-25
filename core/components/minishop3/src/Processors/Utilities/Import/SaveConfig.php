<?php

namespace MiniShop3\Processors\Utilities\Import;

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\Processors\Processor;

class SaveConfig extends Processor
{

    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'mssetting_save';
    public $fields = '';
    public $delimiter = '';

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        return parent::initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function checkPermissions()
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }


    /**
     * {@inheritDoc}
     */
    public function getLanguageTopics()
    {
        return $this->languageTopics;
    }


    /**
     * {@inheritDoc}
     */
    public function process()
    {
        $this->fields = $this->getProperty('fields');
        $this->delimiter = $this->getProperty('delimiter', ';');

        // save fields to system settings
        if ($settingFields = $this->modx->getObject(modSystemSetting::class, 'ms3_utility_import_fields')) {
            $settingFields->set('value', $this->fields);
            $settingFields->save();
        }

        // save delimiter to system settings
        if ($settingDelimiter = $this->modx->getObject(modSystemSetting::class, 'ms3_utility_import_fields_delimiter')) {
            $settingDelimiter->set('value', $this->delimiter);
            $settingDelimiter->save();
        }

        return $this->success();
    }
}
