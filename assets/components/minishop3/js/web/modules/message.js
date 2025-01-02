ms3.message = {
  show (type, message) {
    if (message !== '') {
      if (typeof iziToast !== 'undefined') {
        // eslint-disable-next-line no-undef
        iziToast[type]({
          message,
          position: 'topRight'
        })
      } else {
        alert(message)
      }
    }
  },
  success (message) {
    this.show('success', message)
  },

  error (message) {
    this.show('error', message)
  }
}
