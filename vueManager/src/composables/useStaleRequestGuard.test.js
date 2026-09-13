import { describe, expect, it } from 'vitest'
import { effectScope, ref } from 'vue'

import { useStaleRequestGuard } from './useStaleRequestGuard.js'

function withScope(run) {
  const scope = effectScope()
  scope.run(run)
  scope.stop()
}

async function withScopeAsync(run) {
  const scope = effectScope()
  await scope.run(run)
  scope.stop()
}

describe('useStaleRequestGuard', () => {
  it('beginLoad aborts the previous request signal', () => {
    withScope(() => {
      const { beginLoad } = useStaleRequestGuard()
      const first = beginLoad()
      const second = beginLoad()

      expect(first.signal.aborted).toBe(true)
      expect(second.signal.aborted).toBe(false)
    })
  })

  it('isCurrent is false after a newer beginLoad', () => {
    withScope(() => {
      const { beginLoad } = useStaleRequestGuard()
      const first = beginLoad()
      beginLoad()

      expect(first.isCurrent()).toBe(false)
    })
  })

  it('scope dispose aborts in-flight signal', () => {
    let load

    withScope(() => {
      const { beginLoad } = useStaleRequestGuard()
      load = beginLoad()
    })

    expect(load.signal.aborted).toBe(true)
  })

  it('runGuarded clears loading only for the latest load', async () => {
    await withScopeAsync(async () => {
      const { runGuarded } = useStaleRequestGuard()
      const loading = ref(false)
      let resolveFirst
      const firstBlocked = new Promise(resolve => {
        resolveFirst = resolve
      })

      const firstRun = runGuarded(loading, async signal => {
        await firstBlocked
        expect(signal.aborted).toBe(true)
      })

      const secondRun = runGuarded(loading, async () => {
        await Promise.resolve()
      })

      resolveFirst()
      await firstRun
      await secondRun

      expect(loading.value).toBe(false)
    })
  })

  it('runGuarded rethrows non-abort errors', async () => {
    await withScopeAsync(async () => {
      const { runGuarded } = useStaleRequestGuard()
      const loading = ref(false)

      await expect(
        runGuarded(loading, async () => {
          throw new Error('boom')
        })
      ).rejects.toThrow(/boom/)

      expect(loading.value).toBe(false)
    })
  })

  it('isAbortError detects AbortError', () => {
    withScope(() => {
      const { isAbortError } = useStaleRequestGuard()

      expect(isAbortError({ name: 'AbortError' })).toBe(true)
      expect(isAbortError(new Error('fail'))).toBe(false)
    })
  })
})
