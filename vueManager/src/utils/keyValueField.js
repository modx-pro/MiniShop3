export const KEY_VALUE_XTYPE = 'ms3-key-value'

export function defaultKeyValueConfig() {
  return {
    mode: 'free', // 'free' or 'fixed'
    keys: [], // Array of { key: string, label: string, valueType: 'string'|'number', required: boolean }
  }
}

/**
 * Example fixed keys config (optional):
 * {
 *   mode: 'fixed',
 *   keys: [
 *     { key: 'width', label: 'Width', valueType: 'number', required: true },
 *     { key: 'color', label: 'Color', valueType: 'string', required: false }
 *   ]
 * }
 */

export function parseKeyValueConfig(raw) {
  if (!raw) {
    return defaultKeyValueConfig()
  }

  let parsed = raw
  if (typeof raw === 'string') {
    try {
      parsed = JSON.parse(raw)
    } catch {
      return defaultKeyValueConfig()
    }
  }

  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
    return defaultKeyValueConfig()
  }

  const defaults = defaultKeyValueConfig()

  return {
    ...defaults,
    ...parsed,
    keys: Array.isArray(parsed.keys) ? parsed.keys : defaults.keys,
  }
}

export function parseKeyValueModelValue(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    return value
  }
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed = JSON.parse(value)
      return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {}
    } catch {
      return {}
    }
  }
  return {}
}

export function normalizeKeyValueMap(map, config) {
  const schema = parseKeyValueConfig(config)
  const normalized = {}

  if (schema.mode === 'fixed') {
    for (const keyDef of schema.keys) {
      if (!keyDef.key) continue
      const value = map?.[keyDef.key]
      if (keyDef.valueType === 'number') {
        normalized[keyDef.key] = (value === '' || value === null || value === undefined)
          ? null
          : Number(value)
      } else {
        normalized[keyDef.key] = value ?? ''
      }
    }
  } else {
    // Free mode: just ensure it's a flat object of scalars
    if (map && typeof map === 'object' && !Array.isArray(map)) {
      for (const [k, v] of Object.entries(map)) {
        normalized[k] = v
      }
    }
  }

  return normalized
}

export function serializeKeyValueForPost(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return '{}'
  }
  try {
    return JSON.stringify(value)
  } catch {
    return '{}'
  }
}

export function getKeyValueConfigFromField(fieldConfig) {
  if (!fieldConfig) {
    return defaultKeyValueConfig()
  }
  return parseKeyValueConfig(
    fieldConfig.config?.key_value_config
      ?? fieldConfig.key_value_config
      ?? fieldConfig.config
  )
}
