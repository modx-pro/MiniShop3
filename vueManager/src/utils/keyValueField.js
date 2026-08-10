export const KEY_VALUE_XTYPE = 'ms3-key-value'

export function defaultKeyValueConfig() {
  return {
    mode: 'fixed',
    keys: [],
  }
}

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
    mode: parsed.mode === 'free' ? 'free' : 'fixed',
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
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
    } catch {
      return {}
    }
  }
  return {}
}

export function normalizeKeyValueMap(map, config) {
  const schema = parseKeyValueConfig(config)

  if (schema.mode === 'fixed') {
    const normalized = {}
    for (const keyDef of schema.keys) {
      if (!keyDef.key) {
        continue
      }
      const value = map?.[keyDef.key]
      normalized[keyDef.key] = keyDef.valueType === 'number'
        ? (value === '' || value == null ? null : Number(value))
        : (value ?? '')
    }
    return normalized
  }

  if (!map || typeof map !== 'object' || Array.isArray(map)) {
    return {}
  }

  const normalized = {}
  for (const [rawKey, value] of Object.entries(map)) {
    const key = rawKey.trim()
    if (key === '') {
      continue
    }
    normalized[key] = value
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

export function encodeKeyValueConfig(config) {
  return JSON.stringify(parseKeyValueConfig(config))
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
