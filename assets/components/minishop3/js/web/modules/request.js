ms3.request = {
  headers: {},

  baseUrl: '',

  async send (formData) {
    const hooksData = { formData, success: true }
    try {
      await ms3.hooks.runHooks('beforeSendRequest', hooksData)
    } catch (error) {
      console.error('Error when executing a hook beforeSendRequest:', error)
    }
    if (!hooksData.success) {
      return false
    }
    const response = await this.post(formData)
    const event = new Event('ms3_send_success')
    document.dispatchEvent(event)
    try {
      await ms3.hooks.runHooks('afterSendRequest', { formData, response })
    } catch (error) {
      console.error('Error when executing a hook afterSendRequest:', error)
    }

    return {
      ...response,
      shouldRender: !!response.data?.render?.cart
    }
  },

  async get (formData = new FormData()) {
    try {
      this.setBaseUrl()
      this.setHeaders()

      const url = new URL(this.baseUrl)
      url.search = new URLSearchParams(formData).toString()

      const response = await fetch(url, {
        method: 'GET',
        headers: this.headers
      })
      return await response.json()
    } catch (e) {
      console.warn('Error', e.message)
    }
  },

  async post (formData = new FormData()) {
    this.setBaseUrl()
    this.setHeaders()

    try {
      const response = await fetch(this.baseUrl, {
        method: 'POST',
        headers: this.headers,
        body: formData
      }
      )
      return await response.json()
    } catch (e) {
      console.warn('Error', e.message)
    }
  },

  setBaseUrl () {
    this.baseUrl = ms3.config.actionUrl
  },

  setHeaders (additionalHeaders = {}) {
    this.headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...additionalHeaders
    }

    const token = localStorage.getItem(ms3.config.tokenName)
    if (token) {
      this.headers.ms3Token = JSON.parse(token).token
    }
  }
}
