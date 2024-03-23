<?php

namespace MiniShop3\Processors\Utilities\ExtraField;

use MiniShop3\Model\msExtraField;
use MiniShop3\Utils\DBManager;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    /** @var msExtraField $object */
    public $object;
    public $classKey = msExtraField::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'mssetting_view';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function beforeOutput()
    {
        $dbManager = new DBManager($this->modx);

        $className = $this->object->get('class');
        $columnName = $this->object->get('key');
        $table = $this->modx->getTableName($className);
        $exists = $dbManager->hasField($className, $columnName);

        $this->object->set('exists',  $exists);
        $existsMessage = '';
        if ($exists) {
            $existsMessageTpl = 'Столбец <strong>%s</strong> существует в таблице <strong>%s</strong>, редактирование большинства полей недоступно.';
            $existsMessage = sprintf($existsMessageTpl, $columnName, $table);
        }
        $this->object->set('exists_message', $existsMessage);

        parent::beforeOutput();
    }
}
