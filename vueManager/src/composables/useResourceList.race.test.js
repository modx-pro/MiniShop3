import { describe, expect, it } from 'vitest'

import { createResourceList } from './resourceListCore.js'

function deferred() {
  let resolve
  let reject
  const promise = new Promise((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

describe('createResourceList race (#385)', () => {
  it('keeps the later response when an earlier fetch resolves last', async () => {
    const first = deferred()
    const second = deferred()
    let call = 0

    const list = createResourceList({
      fetchPage: () => {
        call += 1
        return call === 1 ? first.promise : second.promise
      },
    })

    const loadA = list.load()
    const loadB = list.load()

    second.resolve({ results: [{ id: 2 }], total: 1 })
    await loadB

    first.resolve({ results: [{ id: 1 }], total: 1 })
    await loadA

    expect(list.items.value).toEqual([{ id: 2 }])
    expect(list.total.value).toBe(1)
    expect(list.loading.value).toBe(false)
  })

  it('aborts the previous in-flight request when load is called again', async () => {
    const signals = []
    const first = deferred()
    const second = deferred()
    let call = 0

    const list = createResourceList({
      fetchPage: ({ signal }) => {
        call += 1
        signals.push(signal)
        return call === 1 ? first.promise : second.promise
      },
    })

    const loadA = list.load()
    expect(signals[0]?.aborted).toBe(false)

    const loadB = list.load()
    expect(signals[0]?.aborted).toBe(true)

    second.resolve({ results: [{ id: 9 }], total: 1 })
    await loadB

    first.reject(Object.assign(new Error('aborted'), { name: 'AbortError' }))
    await loadA

    expect(list.items.value).toEqual([{ id: 9 }])
    expect(list.loading.value).toBe(false)
  })

  it('does not call onLoadError on AbortError for a stale load', async () => {
    const errors = []
    const first = deferred()
    const second = deferred()
    let call = 0

    const list = createResourceList({
      fetchPage: () => {
        call += 1
        return call === 1 ? first.promise : second.promise
      },
      onLoadError: error => {
        errors.push(error)
      },
    })

    const loadA = list.load()
    const loadB = list.load()

    second.resolve({ results: [{ id: 3 }], total: 1 })
    await loadB

    first.reject(Object.assign(new Error('aborted'), { name: 'AbortError' }))
    await loadA

    expect(errors).toHaveLength(0)
    expect(list.items.value).toEqual([{ id: 3 }])
  })

  it('logs malformed responses and clears the list', async () => {
    const logs = []
    const originalError = console.error
    console.error = (...args) => {
      logs.push(args.join(' '))
    }

    try {
      const list = createResourceList({
        fetchPage: async () => ({ ok: true }),
      })
      await list.load()
      expect(list.items.value).toEqual([])
      expect(list.total.value).toBe(0)
      expect(logs.join('\n')).toMatch(/Unexpected response shape/)
    } finally {
      console.error = originalError
    }
  })
})
