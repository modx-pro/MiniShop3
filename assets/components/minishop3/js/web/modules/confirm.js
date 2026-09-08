/**
 * ConfirmDialog - Promise-based confirmation dialog
 *
 * Uses Bootstrap Modal when available, falls back to native confirm().
 * Auto-binds to elements with [data-ms3-confirm] attribute.
 *
 * @example
 * const confirmed = await ms3Confirm('Delete this item?')
 *
 * @example
 * await ms3Confirm('Cancel order?', {
 *   confirmText: 'Yes, cancel',
 *   confirmClass: 'btn-danger'
 * })
 *
 * @example HTML auto-bind
 * <a href="/logout" data-ms3-confirm="Are you sure?">Logout</a>
 */
const ms3Confirm = (function () {
  let modalElement = null
  let pendingResolve = null

  function getOrCreateModal () {
    if (modalElement) return modalElement

    modalElement = document.createElement('div')
    modalElement.className = 'modal fade'
    modalElement.setAttribute('tabindex', '-1')
    modalElement.innerHTML = `
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-body text-center py-4">
            <p class="mb-0 ms3-confirm-message"></p>
          </div>
          <div class="modal-footer justify-content-center border-top-0 pt-0">
            <button type="button" class="btn btn-secondary ms3-confirm-cancel" data-bs-dismiss="modal"></button>
            <button type="button" class="btn ms3-confirm-ok"></button>
          </div>
        </div>
      </div>
    `
    document.body.appendChild(modalElement)
    return modalElement
  }

  /**
   * Show confirmation dialog
   *
   * @param {string} message - Confirmation message
   * @param {Object} [options] - Options
   * @param {string} [options.confirmText] - Confirm button text
   * @param {string} [options.cancelText] - Cancel button text
   * @param {string} [options.confirmClass] - Confirm button CSS class
   * @returns {Promise<boolean>} - true if confirmed, false if cancelled
   */
  async function confirm (message, options = {}) {
    // Fallback to native confirm if Bootstrap is not available
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      return window.confirm(message)
    }

    // Dismiss previous dialog if still open
    if (pendingResolve) {
      pendingResolve(false)
      pendingResolve = null
    }

    const lexicon = (typeof window !== 'undefined' && window.ms3Lexicon) || {}
    const lang = (document.documentElement.lang || 'en').slice(0, 2)
    const i18n = { ru: { ok: 'Подтвердить', cancel: 'Отмена' }, en: { ok: 'Confirm', cancel: 'Cancel' } }
    const t = i18n[lang] || i18n.en
    const opts = {
      confirmText: options.confirmText || lexicon.ms3_confirm_ok || t.ok,
      cancelText: options.cancelText || lexicon.ms3_confirm_cancel || t.cancel,
      confirmClass: options.confirmClass || 'btn-primary',
    }

    const el = getOrCreateModal()
    const modalInstance = bootstrap.Modal.getOrCreateInstance(el)

    el.querySelector('.ms3-confirm-message').textContent = message

    const okBtn = el.querySelector('.ms3-confirm-ok')
    okBtn.textContent = opts.confirmText
    okBtn.className = `btn ${opts.confirmClass} ms3-confirm-ok`

    el.querySelector('.ms3-confirm-cancel').textContent = opts.cancelText

    return new Promise((resolve) => {
      let resolved = false
      pendingResolve = resolve

      function cleanup () {
        okBtn.removeEventListener('click', handleConfirm)
        el.removeEventListener('hidden.bs.modal', handleHidden)
        if (pendingResolve === resolve) {
          pendingResolve = null
        }
      }

      function handleConfirm () {
        if (resolved) return
        resolved = true
        cleanup()
        modalInstance.hide()
        resolve(true)
      }

      function handleHidden () {
        if (resolved) return
        resolved = true
        cleanup()
        resolve(false)
      }

      okBtn.addEventListener('click', handleConfirm)
      el.addEventListener('hidden.bs.modal', handleHidden)

      modalInstance.show()
    })
  }

  // Auto-bind: elements with [data-ms3-confirm] get a confirm dialog on click
  document.addEventListener('click', async (e) => {
    const el = e.target.closest('[data-ms3-confirm]')
    if (!el) return

    e.preventDefault()

    const message = el.dataset.ms3Confirm
    const confirmed = await confirm(message)

    if (confirmed) {
      if (el.tagName === 'A' && el.href) {
        window.location.href = el.href
      }
    }
  })

  return confirm
})()

window.ms3Confirm = ms3Confirm
