<?php

namespace MiniShop3\Processors\Utilities\Import;

use MiniShop3\Utils\ImportCSV;
use MODX\Revolution\Processors\ModelProcessor;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msProduct;

class Import extends ModelProcessor
{

    public $classKey = msProduct::class;
    public $objectType = 'msProduct';
    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'msproduct_save';
    public $properties = [];

    /** @var MiniShop3 $ms3 */
    protected $ms3;

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        $this->properties = $this->getProperties();

        return parent::initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function process()
    {
        $required = ['importfile', 'fields', 'delimiter'];

        foreach ($required as $field) {
            if (!trim($this->getProperty($field))) {
                return $this->addFieldError($field, $this->modx->lexicon('field_required'));
            }
        }

        $importParams = [
            'file' => $this->properties['importfile'],
            'fields' => $this->properties['fields'],
            'update' => $this->properties['update'],
            'key' => $this->properties['key'],
            'debug' => $this->properties['debug'],
            'delimiter' => $this->properties['delimiter'],
            'skip_header' => $this->properties['skip_header'],
        ];

        $scheduler = $this->getProperty('scheduler', 0);
        if (empty($scheduler)) {
            $importCSV = new ImportCSV($this->modx);
            return $importCSV->process($importParams);
        }
        
        // TODO: Поддержка Scheduler в альфа версии еще не реализована.
        return $this->failure('Scheduler support is not yet implemented!');

        /** @var Scheduler $scheduler */
        /*$path = $this->modx->getOption(
            'scheduler.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/scheduler/'
        );
        $scheduler = $this->modx->getService('scheduler', 'Scheduler', $path . 'model/scheduler/');
        if (!$scheduler) {
            $this->modx->log(1, 'not found Scheduler extra');
            return $this->failure($this->modx->lexicon('ms3_utilities_scheduler_nf'));
        }
        $task = $scheduler->getTask('MiniShop3', 'ms3_csv_import');
        if (!$task) {
            $task = $this->createImportTask();
        }
        if (empty($task)) {
            return $this->failure($this->modx->lexicon('ms3_utilities_scheduler_task_ce'));
        }

        $task->schedule('+1 second', $importParams);

        return $this->success($this->modx->lexicon('ms3_utilities_scheduler_success'));*/
    }

    /**
     * Creating Sheduler's task for start import
     * @return false|object|null
     */
    private function createImportTask()
    {
        $task = $this->modx->newObject('sFileTask');
        $task->fromArray([
            'class_key' => 'sFileTask',
            'content' => '/tasks/csvImport.php',
            'namespace' => 'MiniShop3',
            'reference' => 'ms3_csv_import',
            'description' => 'MiniShop3 CSV import'
        ]);
        if (!$task->save()) {
            return false;
        }
        return $task;
    }
}
