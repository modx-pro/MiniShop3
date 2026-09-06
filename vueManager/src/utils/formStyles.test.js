import { describe, expect, it } from 'vitest'

import { MS3_CONTROL_HEIGHT } from './formStyles.js'

describe('formStyles control height', () => {
  it('exports a single rem token for inputs and buttons', () => {
    expect(MS3_CONTROL_HEIGHT).toBe('2.25rem')
  })
})
