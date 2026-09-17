<?php

/**
 * Register startup HTML that requires VueTools Import Map with theme support (>= 1.2.0).
 *
 * Signal key `vuetools/theme` is present only in VueTools builds that ship
 * `@vuetools/useTheme` / getActiveTheme(). Without it, remove data-vue-module
 * scripts and show a MODX alert instead of a silent ES module link error.
 *
 * @param \MODX\Revolution\modX $modx
 * @param string $alertTitle
 * @param string $alertMessage
 */
function ms3_register_vue_core_check($modx, string $alertTitle, string $alertMessage): void
{
    $script = <<<JS
<script>
(function() {
    var importMap = document.querySelector('script[type="importmap"]');
    var hasVueToolsTheme = false;

    if (importMap) {
        try {
            var mapContent = JSON.parse(importMap.textContent);
            hasVueToolsTheme = mapContent.imports
                && mapContent.imports.vue
                && mapContent.imports['vuetools/theme'];
        } catch (e) {
            hasVueToolsTheme = false;
        }
    }

    if (!hasVueToolsTheme) {
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

    $modx->regClientStartupHTMLBlock($script);
}
