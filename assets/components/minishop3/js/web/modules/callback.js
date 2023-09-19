ms3.callback = {
    cart: {
        render: function (response) {
            for (let key in response.data.render.cart) {
                const renderItem = response.data.render.cart[key]
                const selector = renderItem.selector
                const htmlRender = renderItem.render

                const $el = document.querySelector(selector)
                if ($el) {
                    $el.innerHTML = htmlRender
                }
            }
        }
    }
}