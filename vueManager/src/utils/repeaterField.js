export const REPEATER_XTYPE = 'ms3-repeater'

export function defaultRepeaterConfig() {
  return {
    columns: [
      { key: 'name', label: 'Name', xtype: 'textfield', required: true },
      { key: 'value', label: 'Value', xtype: 'textfield', required: false },
    ],
    minRows: 0,
    maxRows: null,
    sortable: true,
    rankField: 'rank',
  }
}

export function parseRepeaterConfig(raw) {
  if (!raw) {
    return defaultRepeaterConfig()
  }

  let parsed = raw
  if (typeof raw === 'string') {
    try {
      parsed = JSON.parse(raw)
    } catch {
      return defaultRepeaterConfig()
    }
  }

  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
    return defaultRepeaterConfig()
  }

  const defaults = defaultRepeaterConfig()

  return {
    ...defaults,
    ...parsed,
    columns: Array.isArray(parsed.columns) && parsed.columns.length
      ? parsed.columns
      : defaults.columns,
  }
}

export function normalizeRepeaterRows(rows, config) {
  const schema = parseRepeaterConfig(config)
  const columns = schema.columns || []

  return (Array.isArray(rows) ? rows : []).map((row, index) => {
    const normalized = {}
    for (const column of columns) {
      if (!column.key) {
        continue
      }
      const value = row?.[column.key]
      if (column.xtype === 'numberfield') {
        normalized[column.key] = value === '' || value === null || value === undefined
          ? null
          : Number(value)
      } else {
        normalized[column.key] = value ?? ''
      }
    }
    normalized._ms3RowId = row?._ms3RowId ?? `row-${index}-${Date.now()}`
    return normalized
  })
}

export function stripRepeaterRowMeta(rows, config) {
  const schema = parseRepeaterConfig(config)
  const rankField = schema.rankField || 'rank'

  return (Array.isArray(rows) ? rows : []).map(row => {
    const clean = { ...row }
    delete clean._ms3RowId
    delete clean[rankField]
    return clean
  })
}

export function parseRepeaterModelValue(value) {
  if (Array.isArray(value)) {
    return value
  }
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed = JSON.parse(value)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  return []
}

export function getRepeaterConfigFromField(fieldConfig) {
  if (!fieldConfig) {
    return defaultRepeaterConfig()
  }
  return parseRepeaterConfig(
    fieldConfig.config?.repeater_config
      ?? fieldConfig.repeater_config
      ?? fieldConfig.config
  )
}
