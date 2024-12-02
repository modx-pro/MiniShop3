ms3.hooks = {
  items: {},
  addHook (name, fn) {
    if (!ms3.hooks.items[name]) ms3.hooks.items[name] = []
    ms3.hooks.items[name].push(fn)
  },
  async runHooks (name, context) {
    if (!ms3.hooks.items[name]) return
    for (const fn of ms3.hooks.items[name]) {
      if (context.cancel) {
        return false
      }
      await fn(context)
    }
  }
}

ms3.hooks.addHook('afterSendRequest', () => {
  // Время на перерисовку DOM
  setTimeout(() => {
    ms3.cart.init()
  }, 300)
})
