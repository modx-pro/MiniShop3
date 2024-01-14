ms3.customer = {
    init () {
        const customerForms = document.querySelectorAll('.ms3_customer_form')
        if (customerForms.length > 0) {
            customerForms.forEach(customerForm => {
                ms3.customer.formListener(customerForm)
            })
        }
    },
    formListener (customerForm) {
        const inputs = customerForm.querySelectorAll('input, textarea')
        if (inputs.length > 0) {
            inputs.forEach(input => {
                ms3.customer.changeInputListener(input)
            })
        }
    },
    changeInputListener (input) {
        input.addEventListener('change', async () => {
            const formData = new FormData()
            formData.append('key', input.name)
            formData.append('value', input.value)
            const response = await ms3.customer.add(formData)
            console.log(response)
            if (response.success === true) {
                input.value = response.data[input.name]
            } else {
                const form = input.closest('.ms3_customer_form')
                form.classList.add('was-validated')
                input.closest('div').querySelector('.invalid-feedback').textContent = response.message
            }

        })
    },
    async add (formData) {
        formData.append('ms3_action', 'customer/add')
        return await ms3.request.send(formData)
    },
}