ms3.Message = {
    initialize: function () {
        ms3.Message.close = function () {
        };
        ms3.Message.show = function (message) {
            if (message != '') {
                alert(message);
            }
        };

        if (typeof($.fn.jGrowl) === 'function') {
            $.jGrowl.defaults.closerTemplate = '<div>[ ' + miniShop2Config.close_all_message + ' ]</div>';
            ms3.Message.close = function () {
                $.jGrowl('close');
            };
            ms3.Message.show = function (message, options) {
                if (message != '') {
                    $.jGrowl(message, options);
                }
            }
        }
    },
    success: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            ms3.Message.show(message, {
                theme: 'ms2-message-success',
                sticky: false
            });
        }
    },
    error: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            ms3.Message.show(message, {
                theme: 'ms2-message-error',
                sticky: false
            });
        }
    },
    info: function (message) {
        if (typeof($.fn.jGrowl) === 'function') {
            ms3.Message.show(message, {
                theme: 'ms2-message-info',
                sticky: false
            });
        }
    }
};
