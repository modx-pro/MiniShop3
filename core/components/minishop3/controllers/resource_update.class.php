<?php

use MODX\Revolution\modActionDom;
use MODX\Revolution\modFormCustomizationProfile;
use MODX\Revolution\modFormCustomizationSet;
use \xPDO\Om\xPDOQuery;

class msResourceUpdateController extends ResourceUpdateManagerController
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
        $script = $script . '?v=' . $this->ms3->version;
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
        $alertTitle = $this->modx->lexicon('ms3_error') ?: 'Error';
        $alertMessage = $this->modx->lexicon('ms3_vuetools_required')
            ?: 'VueTools package is required. Please install it from Package Manager.';

        $script = <<<JS
<script>
(function() {
    var importMap = document.querySelector('script[type="importmap"]');
    var hasVueCore = false;

    if (importMap) {
        try {
            var mapContent = JSON.parse(importMap.textContent);
            hasVueCore = mapContent.imports && mapContent.imports.vue;
        } catch (e) {
            hasVueCore = false;
        }
    }

    if (!hasVueCore) {
        document.querySelectorAll('script[type="module"][data-vue-module]').forEach(function(el) {
            el.remove();
        });

        if (typeof Ext !== 'undefined') {
            Ext.onReady(function() {
                if (typeof MODx !== 'undefined' && MODx.msg) {
                    MODx.msg.alert('{$alertTitle}', '{$alertMessage}');
                } else {
                    alert('{$alertMessage}');
                }
            });
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (typeof MODx !== 'undefined' && MODx.msg) {
                        MODx.msg.alert('{$alertTitle}', '{$alertMessage}');
                    } else {
                        alert('{$alertMessage}');
                    }
                }, 500);
            });
        }

        window.MS3_VUE_CORE_MISSING = true;
    }
})();
</script>
JS;

        $this->modx->regClientStartupHTMLBlock($script);
    }

    /**
     * Check if content field is hidden
     * @return bool
     */
    public function isHideContent()
    {
        $userGroups = $this->modx->user->getUserGroups();
        $c = $this->modx->newQuery(modActionDom::class);
        $c->innerJoin(modFormCustomizationSet::class, 'FCSet');
        $c->innerJoin(modFormCustomizationProfile::class, 'Profile', 'FCSet.profile = Profile.id');
        $c->leftJoin(modFormCustomizationProfileUserGroup::class, 'ProfileUserGroup', 'Profile.id = ProfileUserGroup.profile');
        $c->leftJoin(modFormCustomizationProfile::class, 'UGProfile', 'UGProfile.id = ProfileUserGroup.profile');
        $c->where([
            'modActionDom.action:IN' => ['resource/*', 'resource/update'],
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

        return (bool)$this->modx->getCount(modActionDom::class, $c);
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
