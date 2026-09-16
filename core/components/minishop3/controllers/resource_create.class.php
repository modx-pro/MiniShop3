<?php

use \xPDO\Om\xPDOQuery;
class msResourceCreateController extends ResourceCreateManagerController
{
    /** @var MiniShop3\MiniShop3 $ms3 */
    public $ms3;

    /**
     * Check if VueTools check script is already registered
     * @var bool
     */
    protected static $vueCoreCheckRegistered = false;

    /**
     *
     */
    public function initialize()
    {
        $this->ms3 = $this->modx->services->get('ms3');
        $this->setContext();
        $this->modx->getUser($this->ctx, true);

        parent::initialize();
    }

    /**
     * @param string $script
     */
    public function addCss($script)
    {
        require_once __DIR__ . '/vue_module_cache_bust.inc.php';
        $script = ms3_vue_module_cache_bust_url($this->modx, $script, (string) $this->ms3->version);
        parent::addCss($script);
    }

    /**
     * @param string $script
     */
    public function addJavascript($script)
    {
        $script = $script . '?v=' . $this->ms3->version;
        parent::addJavascript($script);
    }

    /**
     * @param string $script
     */
    public function addLastJavascript($script)
    {
        $script = $script . '?v=' . $this->ms3->version;
        parent::addLastJavascript($script);
    }

    /**
     * Register Vue ES module with VueTools dependency check
     *
     * @param string $src Module script URL
     * @return void
     */
    public function addVueModule($src)
    {
        // Register the check script only once per page
        if (!self::$vueCoreCheckRegistered) {
            $this->registerVueCoreCheck();
            self::$vueCoreCheckRegistered = true;
        }

        require_once __DIR__ . '/vue_module_cache_bust.inc.php';
        $src = ms3_vue_module_cache_bust_url($this->modx, $src, (string) $this->ms3->version);

        // Register the module (will be blocked by check if VueCore not installed)
        $this->modx->regClientStartupHTMLBlock(
            '<script type="module" data-vue-module src="' . $src . '"></script>'
        );
    }

    /**
     * Register inline script that checks for VueTools Import Map
     * If not found, shows MODX alert and prevents Vue module loading
     */
    protected function registerVueCoreCheck()
    {
        require_once __DIR__ . '/vue_core_check.inc.php';
        $alertTitle = $this->modx->lexicon('ms3_error') ?: 'Error';
        $alertMessage = $this->modx->lexicon('ms3_vuetools_required')
            ?: 'VueTools package (>= 1.2.0) is required. Please install or update it via Package Manager.';
        ms3_register_vue_core_check($this->modx, $alertTitle, $alertMessage);
    }

    /**
     * Check if content field is hidden
     * @return bool
     */
    public function isHideContent()
    {
        $userGroups = $this->modx->user->getUserGroups();
        $c = $this->modx->newQuery('modActionDom');
        $c->innerJoin('modFormCustomizationSet', 'FCSet');
        $c->innerJoin('modFormCustomizationProfile', 'Profile', 'FCSet.profile = Profile.id');
        $c->leftJoin('modFormCustomizationProfileUserGroup', 'ProfileUserGroup', 'Profile.id = ProfileUserGroup.profile');
        $c->leftJoin('modFormCustomizationProfile', 'UGProfile', 'UGProfile.id = ProfileUserGroup.profile');
        $c->where([
            'modActionDom.action:IN' => ['resource/*', 'resource/create'],
            'modActionDom.name' => 'modx-resource-content',
            'modActionDom.container' => 'modx-panel-resource',
            'modActionDom.rule' => 'fieldVisible',
            'modActionDom.active' => true,
            'FCSet.template:IN' => [0, $this->resource->template],
            'FCSet.active' => true,
            'Profile.active' => true,
        ]);
        $c->where([
            [
                'ProfileUserGroup.usergroup:IN' => $userGroups,
                [
                    'OR:ProfileUserGroup.usergroup:IS' => null,
                    'AND:UGProfile.active:=' => true,
                ],
            ],
            'OR:ProfileUserGroup.usergroup:=' => null,
        ], xPDOQuery::SQL_AND, null, 2);

        return (bool)$this->modx->getCount('modActionDom', $c);
    }

    /**
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return mixed
     */
    public function getOption($key, $options = null, $default = null, $skipEmpty = false)
    {
        $option = $default;
        if (!empty($key) and is_string($key)) {
            if (is_array($options) && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif ($options = $this->modx->_userConfig and array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif ($options = $this->context->config and array_key_exists($key, $options)) {
                $option = $options[$key];
            } else {
                $option = $this->modx->getOption($key);
            }
        }
        if ($skipEmpty and empty($option)) {
            $option = $default;
        }

        return $option;
    }
}
