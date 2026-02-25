/**
 * Order cancel button handler for customer account (list and order details pages).
 * Expects container with data-api-url and buttons with class .ms3-order-cancel and data-order-id.
 */
(function () {
  const container = document.querySelector('.ms3-customer-orders, .ms3-customer-order-details')
  const apiBaseUrl = (container && container.getAttribute('data-api-url')) || '/assets/components/minishop3/api.php'

  document.querySelectorAll('.ms3-order-cancel').forEach(function (cancelButton) {
    cancelButton.addEventListener('click', function () {
      const orderId = this.getAttribute('data-order-id')
      const confirmMessage = this.getAttribute('data-confirm') || 'Cancel this order?'
      if (!confirm(confirmMessage)) return

      cancelButton.disabled = true
      fetch(apiBaseUrl + '?route=/api/v1/customer/orders/' + orderId + '/cancel', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json() })
        .then(function (responseData) {
          if (responseData.success) {
            location.reload()
          } else {
            alert(responseData.message || 'Error')
            cancelButton.disabled = false
          }
        })
        .catch(function () {
          alert('Request failed')
          cancelButton.disabled = false
        })
    })
  })
})()
