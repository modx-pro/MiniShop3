const ms3 = {
    config: {},
    init () {
        this.config = window.ms3Config
        this.checkToken()
        ms3.form.init();
        ms3.cart.init();
    },
    checkToken () {
        let ms3Token = localStorage.getItem(ms3.config.tokenName)
        if (ms3Token === null) {
            this.setToken()
        }
    },
    async setToken () {
        this.request.setHeaders()
        const formData = new FormData()
        formData.append('ms3_action', 'customer/token/get')
        const response = await this.request.get(formData)
        if (response.success === true) {
            localStorage.setItem(ms3.config.tokenName, response.data.token)
        }
    },
}

document.addEventListener('DOMContentLoaded', () => {
    ms3.init()
})

document.addEventListener('ms3_send_success', () => {
    //Время на перерисовку DOM
    setTimeout(() => {
        ms3.cart.init();
    }, 300)
})