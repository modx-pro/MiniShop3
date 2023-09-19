ms3.form = {
    init () {
        document.addEventListener('submit', event => {
                if (event.target.classList.contains('ms3_form')) {
                event.preventDefault()
                const form = event.target
                const formData = new FormData(form)
                if (ms3Config.render !== undefined) {
                    formData.append('render', JSON.stringify(ms3Config.render))
                }
                this.send(formData)
            }
        })
    },
    async send (formData) {
        const response = await ms3.request.send(formData)
        if (response.data.render !== undefined && response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
}