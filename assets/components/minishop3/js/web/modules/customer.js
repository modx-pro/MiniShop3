ms3.customer = {
  init () {
    const forms = document.querySelectorAll('.ms3_customer_form')
    forms.forEach(form => ms3.customer.formListener(form))
  },

  formListener (form) {
    const formInputs = form.querySelectorAll('input, textarea')
    formInputs.forEach(input => ms3.customer.changeInputListener(input))
  },

  changeInputListener (input) {
    input.addEventListener('change', async () => {
      const form = input.closest('.ms3_customer_form')
      form.classList.remove('was-validated')
      input.classList.remove('is-invalid')
      input.closest('div').querySelector('.invalid-feedback').textContent = ''

      const formData = new FormData()
      formData.append('key', input.name)
      formData.append('value', input.value)

      try {
        const { success, data, message } = await ms3.customer.add(formData)
        if (success === true) {
          input.value = data[input.name]

          if (message !== '') {
            ms3.message.success(message)
          }
        } else {
          form.classList.add('was-validated')
          input.classList.add('is-invalid')
          input.closest('div').querySelector('.invalid-feedback').textContent = message
          if (message !== '') {
            ms3.message.error(message)
          }
        }
      } catch (error) {
        console.error('Error when executing method customer/changeInputListener:', error)
      }
    })
  },

  async add (formData) {
    if (!formData.get('ms3_action')) {
      formData.append('ms3_action', 'customer/add')
    }
    const hooksData = { formData }
    await ms3.hooks.runHooks('beforeAddCustomer', hooksData)
    if (hooksData.cancel) {
      return false
    }
    try {
      const response = await ms3.request.send(formData)
      await ms3.hooks.runHooks('afterAddCustomer', { formData, response })
      if (response.success && response.message !== '') {
        ms3.message.success(response.message)
      }
      if (!response.success && response.message !== '') {
        ms3.message.error(response.message)
      }
      return response
    } catch (error) {
      console.error('Error when executing method customer/add:', error)
    }
  },

  async changeAddress (formData) {
    if (!formData.get('ms3_action')) {
      formData.append('ms3_action', 'customer/changeAddress')
    }
    const hooksData = { formData }
    await ms3.hooks.runHooks('beforeChangeAddressCustomer', hooksData)
    if (hooksData.cancel) {
      return false
    }
    try {
      const response = await ms3.request.send(formData)
      await ms3.hooks.runHooks('afterChangeAddressCustomer', { formData, response })
      if (response.success && response.message !== '') {
        ms3.message.success(response.message)
      }
      if (!response.success && response.message !== '') {
        ms3.message.error(response.message)
      }
      return response
    } catch (error) {
      console.error('Error when executing method customer/hangeAddress:', error)
    }
  },

  checkToken () {
    const ms3Token = localStorage.getItem(ms3.config.tokenName)
    if (ms3Token === null) {
      ms3.setToken()
      return false
    }

    if (!ms3.isJSON(ms3Token)) {
      localStorage.removeItem(ms3.config.tokenName)
      ms3.setToken()
      return false
    }

    const ms3TokenData = JSON.parse(ms3Token)
    const now = new Date()
    if (now.getTime() > parseInt(ms3TokenData.expiry)) {
      localStorage.removeItem(ms3.config.tokenName)
      ms3.setToken()
    } else {
      ms3.updateToken()
    }
  },
  async setToken () {
    this.request.setHeaders()
    const formData = new FormData()
    formData.append('ms3_action', 'customer/token/get')
    const hooksData = { formData }
    await ms3.hooks.runHooks('beforeGetTokenCustomer', hooksData)
    if (hooksData.cancel) {
      return false
    }
    try {
      const response = await this.request.get(formData)
      if (response.success === true) {
        const now = new Date()
        const tokenData = {
          token: response.data.token,
          expiry: now.getTime() + parseInt(response.data.lifetime)
        }
        localStorage.setItem(ms3.config.tokenName, JSON.stringify(tokenData))
      }
      await ms3.hooks.runHooks('afterGetTokenCustomer', { formData, response })
    } catch (error) {
      console.error('Error when executing method customer/setToken:', error)
    }
  },
  async updateToken () {
    this.request.setHeaders()
    const formData = new FormData()
    formData.append('ms3_action', 'customer/token/update')
    const hooksData = { formData }
    await ms3.hooks.runHooks('beforeUpdateTokenCustomer', hooksData)
    if (hooksData.cancel) {
      return false
    }
    try {
      const response = await this.request.post(formData)
      if (response.success === true) {
        const now = new Date()
        const tokenData = {
          token: response.data.token,
          expiry: now.getTime() + parseInt(response.data.lifetime)
        }
        localStorage.setItem(ms3.config.tokenName, JSON.stringify(tokenData))
      }
      await ms3.hooks.runHooks('afterUpdateTokenCustomer', { formData, response })
    } catch (error) {
      console.error('Error when executing method customer/updateToken:', error)
    }
  }
}
