<?php


class msManagerController extends \MODX\Revolution\modExtraManagerController
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
        $this->modx->getUser('', true);

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
     * Emit the inline `var ms3 = { config }` block for a Vue page and inject the manager
     * auth token synchronously.
     *
     * Ext-less Vue pages fire their first API requests on DOMContentLoaded, before the
     * manager JS has populated the `MODx.siteId` global that request.js uses as
     * HTTP_MODAUTH. Without the token the connector rejects those early requests with
     * "Access denied" (code 401), even though the session is valid (#544). Shipping the
     * token inside ms3.config — available before the Vue module runs — removes that race.
     *
     * @param array $config
     * @return void
     */
    public function addVueConfig(array $config)
    {
        $contextKey = $this->modx->context ? (string) $this->modx->context->get('key') : 'mgr';
        $config['token'] = $this->modx->user
            ? (string) $this->modx->user->getUserToken($contextKey)
            : '';

        $this->addHtml(
            '<script>var ms3 = { config: ' . json_encode($config) . ' };</script>'
        );
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

        // Add version to URL
        $src = $src . '?v=' . $this->ms3->version;

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
