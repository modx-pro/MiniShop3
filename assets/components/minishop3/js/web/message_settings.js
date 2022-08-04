minishop.Message = {
    initialize: function () {
        minishop.Message.close = function () {
        };
        minishop.Message.show = function (message) {
            if (message != '') {
                alert(message);
            }
        };

        if (typeof($.fn.jGrowl) === 'function') {
            $.jGrowl.defaults.closerTemplate = '<div>[ ' + miniShop2Config.close_all_message + ' ]</div>';
            minishop.Message.close = function () {
                $.jGrowl('close');
            };
            minishop.Message.show = function (message, options) {
                if (message != '') {
                    $.jGrowl(message, options);
                }
            }
        }
    },
    success: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            minishop.Message.show(message, {
                theme: 'ms2-message-success',
                sticky: false
            });
        }
    },
    error: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            minishop.Message.show(message, {
                theme: 'ms2-message-error',
                sticky: false
            });
        }
    },
    info: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            minishop.Message.show(message, {
                theme: 'ms2-message-info',
                sticky: false
            });
        }
    }
};
