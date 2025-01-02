const ms3 = {
  config: {},
  init () {
    this.config = window.ms3Config
    ms3.customer.checkToken()
    ms3.form.init()
    ms3.cart.init()
    ms3.customer.init()
    ms3.order.init()
  },
  isJSON (str) {
    try {
      JSON.parse(str)
    } catch (e) {
      return false
    }
    return true
  }
}

document.addEventListener('DOMContentLoaded', () => {
  ms3.init()
})
