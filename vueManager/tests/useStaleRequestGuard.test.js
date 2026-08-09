import assert from 'node:assert/strict'
import test from 'node:test'

import { effectScope, ref } from 'vue'

import { useStaleRequestGuard } from '../src/composables/useStaleRequestGuard.js'

test('beginLoad aborts the previous request signal', () => {
  const scope = effectScope()
  scope.run(() => {
    const { beginLoad } = useStaleRequestGuard()
    const first = beginLoad()
    const second = beginLoad()

    assert.equal(first.signal.aborted, true)
    assert.equal(second.signal.aborted, false)
  })
  scope.stop()
})

test('isCurrent is false after a newer beginLoad', () => {
  const scope = effectScope()
  scope.run(() => {
    const { beginLoad } = useStaleRequestGuard()
    const first = beginLoad()
    beginLoad()

    assert.equal(first.isCurrent(), false)
  })
  scope.stop()
})

test('scope dispose aborts in-flight signal', () => {
  const scope = effectScope()
  let load

  scope.run(() => {
    const { beginLoad } = useStaleRequestGuard()
    load = beginLoad()
  })

  scope.stop()
  assert.equal(load.signal.aborted, true)
})

test('runGuarded clears loading only for the latest load', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const { runGuarded } = useStaleRequestGuard()
    const loading = ref(false)
    let resolveFirst
    const firstBlocked = new Promise(resolve => {
      resolveFirst = resolve
    })

    const firstRun = runGuarded(loading, async signal => {
      await firstBlocked
      assert.equal(signal.aborted, true)
    })

    const secondRun = runGuarded(loading, async () => {
      await Promise.resolve()
    })

    resolveFirst()
    await firstRun
    await secondRun

    assert.equal(loading.value, false)
  })

  scope.stop()
})

test('runGuarded rethrows non-abort errors', async () => {
  const scope = effectScope()

  await scope.run(async () => {
    const { runGuarded } = useStaleRequestGuard()
    const loading = ref(false)

    await assert.rejects(
      () =>
        runGuarded(loading, async () => {
          throw new Error('boom')
        }),
      /boom/
    )

    assert.equal(loading.value, false)
  })

  scope.stop()
})

test('isAbortError detects AbortError', () => {
  const scope = effectScope()
  scope.run(() => {
    const { isAbortError } = useStaleRequestGuard()

    assert.equal(isAbortError({ name: 'AbortError' }), true)
    assert.equal(isAbortError(new Error('fail')), false)
  })
  scope.stop()
})
