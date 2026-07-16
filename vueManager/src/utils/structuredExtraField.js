import { KEY_VALUE_XTYPE, parseKeyValueModelValue } from './keyValueField.js'
import { parseRepeaterModelValue, REPEATER_XTYPE } from './repeaterField.js'

const STRUCTURED_EXTRA_FIELD_PARSERS = {
  [REPEATER_XTYPE]: parseRepeaterModelValue,
  [KEY_VALUE_XTYPE]: parseKeyValueModelValue,
}

export function isFullWidthExtraFieldXtype(xtype) {
  return Object.prototype.hasOwnProperty.call(STRUCTURED_EXTRA_FIELD_PARSERS, xtype)
}

export function parseStructuredExtraFieldValue(xtype, value) {
  const parser = STRUCTURED_EXTRA_FIELD_PARSERS[xtype]
  return parser ? parser(value) : value
}
