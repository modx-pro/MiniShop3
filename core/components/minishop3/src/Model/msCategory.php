<?php

namespace MiniShop3\Model;

use MiniShop3\Services\Category\CategoryService;
use MiniShop3\Services\Category\CategoryOptionService;
use MODX\Revolution\modAccessibleObject;
use MODX\Revolution\modCategory;
use MODX\Revolution\modResource;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Class msCategory
 *
 * @property string $class_key
 *
 * @property msProduct[] $OwnProducts
 * @property msCategoryMember[] $AlienProducts
 * @property msCategoryOption[] $CategoryOptions
 *
 * @package MiniShop3\Model
 */
class msCategory extends modResource
{
    public $showInContextMenu = true;

    /** @var CategoryService|null */
    protected $categoryService;

    /** @var CategoryOptionService|null */
    protected $categoryOptionService;
    /**
     * msCategory constructor.
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO &$xpdo)
    {
        parent::__construct($xpdo);
        $this->set('class_key', msCategory::class);
    }

    /**
     * @param xPDO $xpdo
     * @param string $className
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return array
     */
    public static function loadCollection(xPDO &$xpdo, $className, $criteria = null, $cacheFlag = true)
    {
        if (!is_object($criteria)) {
            $criteria = $xpdo->getCriteria($className, $criteria, $cacheFlag);
        }
        $xpdo->addDerivativeCriteria($className, $criteria);

        return parent::loadCollection($xpdo, $className, $criteria, $cacheFlag);
    }

    /**
     * @param xPDO $modx
     *
     * @return string
     */
    public static function getControllerPath(xPDO &$modx)
    {
        $corePath = $modx->getOption(
            'ms3.core_path',
            null,
            $modx->getOption('core_path') . 'components/minishop3/'
        );

        return $corePath . 'controllers/category/';
    }

    /**
     * @return array
     */
    public function getContextMenuText()
    {
        $this->xpdo->lexicon->load('minishop3:default');

        return [
            'text_create' => $this->xpdo->lexicon('ms3_category'),
            'text_create_here' => $this->xpdo->lexicon('ms3_category_create_here'),
        ];
    }

    /**
     * @param xPDO $xpdo
     * @param string $className
     * @param null $criteria
     * @param bool $cacheFlag
     *
     * @return modAccessibleObject|null|object
     */
    public static function load(xPDO &$xpdo, $className, $criteria = null, $cacheFlag = true)
    {
//        if (!is_object($criteria)) {
//            $criteria = $xpdo->getCriteria($className, $criteria, $cacheFlag);
//        }
//        $xpdo->addDerivativeCriteria($className, $criteria);

        return parent::load($xpdo, $className, $criteria, $cacheFlag);
    }

    /**
     * @return null|string
     */
    public function getResourceTypeName()
    {
        $this->xpdo->lexicon->load('minishop3:default');

        return $this->xpdo->lexicon('ms3_category_type');
    }

    /**
     * @param array $node
     *
     * @return array
     */
    public function prepareTreeNode(array $node = [])
    {
        $classes = array_map('trim', explode(' ', $node['cls']));
        $remove = ['pnew_modStaticResource', 'pnew_modSymLink', 'pnew_modWebLink', 'pnew_modDocument'];
        $node['cls'] = implode(' ', array_diff($classes, $remove));
        $node['hasChildren'] = true;
        $node['expanded'] = false;
        $node['type'] = "MiniShop3\\Model\\msCategory";

        return $node;
    }

    /**
     * @param array $options
     *
     * @return mixed
     */
    public function duplicate(array $options = [])
    {
        $newCategory = parent::duplicate($options);

        // Дублируем связанные данные через сервис
        $this->getCategoryService()->duplicateCategory($this, $newCategory);

        return $newCategory;
    }

    /**
     * @param null $cacheFlag
     *
     * @return bool
     */
    public function save($cacheFlag = null)
    {
        $oldClassKey = parent::get('class_key');

        // Обрабатываем изменение типа категории через сервис
        if (!$this->isNew() && $oldClassKey != 'msCategory') {
            $this->getCategoryService()->handleCategorySave($this, $oldClassKey);
        }

        return parent::save($cacheFlag);
    }

    /**
     * Returns array with all neighborhood products
     *
     * @return array $arr Array with neighborhood from left and right
     */
    public function getNeighborhood()
    {
        return $this->getCategoryService()->getNeighborCategories($this);
    }

    /**
     * @param bool $force
     *
     * @return array
     */
    public function getOptionKeys($force = false)
    {
        return $this->getCategoryOptionService()->getOptionKeys($this, $force);
    }

    /**
     * @return xPDOQuery
     */
    public function prepareOptionListCriteria()
    {
        return $this->getCategoryOptionService()->buildOptionQuery($this);
    }

    /**
     * @return array
     */
    public function getOptionFields(array $keys = [])
    {
        return $this->getCategoryOptionService()->getOptionFields($this, $keys);
    }

    /**
     * Получить сервис категорий (lazy loading)
     *
     * @return CategoryService
     */
    protected function getCategoryService(): CategoryService
    {
        if ($this->categoryService === null) {
            // Пытаемся получить из контейнера, если зарегистрирован
            if ($this->xpdo->services->has('ms3_category_service')) {
                $this->categoryService = $this->xpdo->services->get('ms3_category_service');
            } else {
                // Создаем новый экземпляр
                $this->categoryService = new CategoryService($this->xpdo);
            }
        }

        return $this->categoryService;
    }

    /**
     * Получить сервис опций категорий (lazy loading)
     *
     * @return CategoryOptionService
     */
    protected function getCategoryOptionService(): CategoryOptionService
    {
        if ($this->categoryOptionService === null) {
            // Пытаемся получить из контейнера, если зарегистрирован
            if ($this->xpdo->services->has('ms3_category_option_service')) {
                $this->categoryOptionService = $this->xpdo->services->get('ms3_category_option_service');
            } else {
                // Создаем новый экземпляр
                $this->categoryOptionService = new CategoryOptionService($this->xpdo);
            }
        }

        return $this->categoryOptionService;
    }
}
