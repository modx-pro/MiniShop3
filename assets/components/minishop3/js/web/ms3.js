const ms3 = {
    config: {},
    init () {
        this.config = window.ms3Config
        this.checkToken()
        this.request.init()
        this.cart.init()
    },
    checkToken () {
        let ms3Token = localStorage.getItem(ms3.config.tokenName)
        if (ms3Token === null) {
            this.setToken()
        }
    },
    async setToken () {
        this.request.setHeaders()
        const response = await this.request.get('customer/token/get')
        if (response.success === true) {
            localStorage.setItem(ms3.config.tokenName, response.data.token)
        }
    },
}

document.addEventListener('DOMContentLoaded', () => {
    ms3.init()
})