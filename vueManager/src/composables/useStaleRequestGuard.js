import { onScopeDispose } from 'vue'

/**
 * Guards async list loads against stale responses when pagination/filters change quickly (#385).
 *
 * Each beginLoad() aborts the previous in-flight fetch and bumps a sequence id.
 * Prefer runGuarded() in grid loaders to centralize loading/abort/stale handling.
 */
export function useStaleRequestGuard() {
  let loadSeq = 0
  /** @type {AbortController | null} */
  let abortController = null

  onScopeDispose(() => {
    abortController?.abort()
    abortController = null
  })

  /**
   * @returns {{ seq: number, signal: AbortSignal, isCurrent: () => boolean }}
   */
  function beginLoad() {
    abortController?.abort()
    abortController = new AbortController()
    const seq = ++loadSeq

    return {
      seq,
      signal: abortController.signal,
      isCurrent: () => seq === loadSeq,
    }
  }

  function isAbortError(error) {
    return error?.name === 'AbortError'
  }

  /**
   * @param {import('vue').Ref<boolean>} loadingRef
   * @param {(signal: AbortSignal, isCurrent: () => boolean) => Promise<void>} task
   */
  async function runGuarded(loadingRef, task) {
    const load = beginLoad()
    loadingRef.value = true

    try {
      await task(load.signal, load.isCurrent)
    } catch (error) {
      if (isAbortError(error) || !load.isCurrent()) {
        return
      }
      throw error
    } finally {
      if (load.isCurrent()) {
        loadingRef.value = false
      }
    }
  }

  return { beginLoad, isAbortError, runGuarded }
}
