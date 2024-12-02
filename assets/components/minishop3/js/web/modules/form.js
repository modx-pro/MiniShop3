ms3.form = {
  init () {
    document.addEventListener('submit', async event => {
      if (!event.target.classList.contains('ms3_form')) {
        return
      }

      event.preventDefault()
      const form = event.target
      const formData = new FormData(form)
      const action = formData.get('ms3_action')
      const parts = action.split('/')
      const entity = parts[0]
      const method = parts[1]

      if (entity === 'cart' && ms3Config.render) {
        formData.append('render', JSON.stringify(ms3Config.render))
      }

      if (ms3[entity][method] !== undefined) {
        await ms3[entity][method](formData)
      } else {
        const hooksData = { formData }
        await ms3.hooks.runHooks('beforeSend', hooksData)
        if (hooksData.cancel) {
          return false
        }
        await this.send(formData)
        await ms3.hooks.runHooks('afterSend', { formData })
      }
    })
  },

  async send (formData) {
    const response = await ms3.request.send(formData)
    if (response.shouldRender) {
      ms3.cart.render(response)
    }
    if (response.data.redirect) {
      location.href = response.data.redirect
    }
    return response
  }
}
