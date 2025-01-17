async function handleCartAction (action, formData, beforeHook, afterHook) {
  if (!formData.get('ms3_action')) {
    formData.append('ms3_action', action)
  }
  const hooksData = { formData }
  await ms3.hooks.runHooks(beforeHook, hooksData)
  if (hooksData.cancel) {
    return false
  }
  try {
    const response = await ms3.request.send(formData)
    await ms3.hooks.runHooks(afterHook, { formData, response })
    if (response.shouldRender) {
      ms3.cart.render(response)
    }
    if (response.message !== '') {
      ms3.message[response.success ? 'success' : 'error'](response.message)
    }
  } catch (error) {
    console.error(`Error when executing method ${action}:`, error)
  }
}

ms3.cart = {
  init () {
    const countBtns = document.querySelectorAll('.qty-btn')
    const countInputs = document.querySelectorAll('.qty-input')
    const changeOptionSelects = document.querySelectorAll('.ms3_cart_options')

    ms3.cart.countBtnsListener(countBtns)
    ms3.cart.countInputsListener(countInputs)
    ms3.cart.changeOptionSelectListener(changeOptionSelects)
  },

  async add (formData) {
    return handleCartAction('cart/add', formData, 'beforeAddCart', 'afterAddCart')
  },

  async change (formData) {
    return handleCartAction('cart/change', formData, 'beforeChangeCart', 'afterChangeCart')
  },

  async remove (formData) {
    return handleCartAction('cart/remove', formData, 'beforeRemoveCart', 'afterRemoveCart')
  },

  async clean (formData) {
    return handleCartAction('cart/clean', formData, 'beforeCleanCart', 'afterCleanCart')
  },

  async changeOption (formData) {
    return handleCartAction('cart/changeOption', formData, 'beforeChangeOptionCart', 'afterChangeOptionCart')
  },

  render: function (response) {
    const cartRender = response.data.render.cart

    for (const key in cartRender) {
      const { selector, render } = cartRender[key]
      const $element = document.querySelector(selector)

      if ($element) {
        $element.innerHTML = render
      }
    }
  },

  countBtnsListener (countBtns = []) {
    countBtns.forEach($btn => $btn.addEventListener('click', ms3.cart.countBtnClickListener))
  },

  countInputsListener (countInputs = []) {
    countInputs.forEach($input => $input.addEventListener('change', ms3.cart.countInputChangeListener))
  },

  countBtnClickListener (event) {
    const $btn = event.target
    const form = $btn.closest('.ms3_form')
    const input = form.querySelector('.qty-input')

    let quantity = parseInt(input.value, 10)
    if ($btn.classList.contains('inc-qty')) {
      quantity++
    }

    if ($btn.classList.contains('dec-qty') && quantity > 0) {
      quantity--
    }

    input.value = quantity

    const formData = new FormData(form)
    if (ms3Config.render) {
      formData.append('render', JSON.stringify(ms3Config.render))
    }
    ms3.cart.change(formData)
  },

  countInputChangeListener (event) {
    const $input = event.target
    const form = $input.closest('.ms3_form')
    const quantity = parseInt($input.value, 10)

    if (!quantity) {
      return
    }

    const formData = new FormData(form)
    if (ms3Config.render) {
      formData.append('render', JSON.stringify(ms3Config.render))
    }
    ms3.cart.change(formData)
  },

  changeOptionSelectListener (changeOptionSelects = []) {
    changeOptionSelects.forEach($select => $select.addEventListener('change', ms3.cart.changeOptionSelectChangeListener))
  },

  changeOptionSelectChangeListener (event) {
    const $input = event.target
    const form = $input.closest('.ms3_form')
    const formData = new FormData(form)

    if (ms3Config.render) {
      formData.append('render', JSON.stringify(ms3Config.render))
    }
    ms3.cart.changeOption(formData)
  }

}
