ms3.form = {
    init () {
        document.addEventListener('submit', event => {
            if (event.target.classList.contains('ms3_form')) {
                event.preventDefault()
                const form = event.target
                const formData = new FormData(form)
                const callbacks = {}
                if (ms3Config.render !== undefined) {
                    formData.append('render', JSON.stringify(ms3Config.render))
                    callbacks.render = function (response) {
                        if (response.data.render.cart !== undefined) {
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
                this.send(formData, callbacks)
            }
        })
    },
    async send (formData, callbacks = {}) {
        const response = await ms3.request.post(formData)
        if (callbacks !== undefined && Object.keys(callbacks).length > 0) {
            for (let key in callbacks) {
                callbacks[key](response)
            }
        }

        let event = new Event('ms3_send_success')
        dispatchEvent(event)
    },
}