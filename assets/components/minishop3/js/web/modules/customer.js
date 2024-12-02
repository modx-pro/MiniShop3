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

      const { success, data, message } = await ms3.customer.add(formData)

      if (success === true) {
        input.value = data[input.name]
      } else {
        form.classList.add('was-validated')
        input.classList.add('is-invalid')
        input.closest('div').querySelector('.invalid-feedback').textContent = message
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
    const response = await ms3.request.send(formData)
    await ms3.hooks.runHooks('afterAddCustomer', { formData, response })
    return response
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
    const response = await ms3.request.send(formData)
    await ms3.hooks.runHooks('afterChangeAddressCustomer', { formData, response })
    return response
  }
}
