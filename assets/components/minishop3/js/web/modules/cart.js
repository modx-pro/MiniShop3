ms3.cart = {
    init () {
        const countBtns = document.querySelectorAll('.qty-btn')
        const countInputs = document.querySelectorAll('.qty-input')
        const changeOptionSelects = document.querySelectorAll('.ms3_cart_options')
        if (countBtns.length > 0) {
            ms3.cart.countBtnsListener(countBtns)
            ms3.cart.countInputsListener(countInputs)
        }
        if (changeOptionSelects.length > 0) {
            ms3.cart.changeOptionSelectListener(changeOptionSelects)
        }
    },
    async add (formData) {
        const response = await ms3.request.send(formData)
        if (response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
    async change (formData) {
        const response = await ms3.request.send(formData)
        if (response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
    async remove (formData) {
        const response = await ms3.request.send(formData)
        if (response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
    async clean (formData) {
        const response = await ms3.request.send(formData)
        if (response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
    async changeOption (formData) {
        const response =  await ms3.request.send(formData)
        if (response.data.render.cart !== undefined) {
            ms3.callback.cart.render(response)
        }
    },
    countBtnsListener(countBtns) {
        countBtns.forEach($btn => {
            $btn.addEventListener('click', ms3.cart.countBtnClickListener)
        })
    },
    countInputsListener(countInputs) {
        countInputs.forEach($input => {
            $input.addEventListener('change', ms3.cart.countInputChangeListener)
        })
    },
    countBtnClickListener(event) {
        const $btn = event.target
        const form = $btn.closest('.ms3_form')
        const input = form.querySelector('.qty-input')
        let quantity = parseInt(input.value)
        if ($btn.classList.contains('inc-qty')) {
            quantity = quantity + 1
            input.value = quantity
        }
        if ($btn.classList.contains('dec-qty')) {
            if (quantity > 0) {
                quantity = quantity - 1
                input.value = quantity
            }
        }

        const formData = new FormData(form)
        if (ms3Config.render !== undefined) {
            formData.append('render', JSON.stringify(ms3Config.render))
        }
        ms3.cart.change(formData)
    },
    countInputChangeListener(event) {
        const $input = event.target
        const form = $input.closest('.ms3_form')
        let quantity = parseInt(input.value)
        if (quantity > 0) {
            const formData = new FormData(form)
            if (ms3Config.render !== undefined) {
                formData.append('render', JSON.stringify(ms3Config.render))
            }
            ms3.cart.change(formData)
        }
    },
    changeOptionSelectListener(changeOptionSelects) {
        changeOptionSelects.forEach($select => {
            $select.addEventListener('change', ms3.cart.changeOptionSelectChangeListener)
        })
    },
    changeOptionSelectChangeListener(event) {
        const $input = event.target
        const form = $input.closest('.ms3_form')
        const formData = new FormData(form)
        if (ms3Config.render !== undefined) {
            formData.append('render', JSON.stringify(ms3Config.render))
        }
        ms3.cart.changeOption(formData)
    },

}