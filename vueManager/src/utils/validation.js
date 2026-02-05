/**
 * Validation utilities for MiniShop3 forms
 *
 * Contains validation rules compatible with MODX and PrimeVue
 */

/**
 * Check if value is empty
 *
 * @param {any} value - Value
 * @returns {boolean} - Check result
 */
export function isEmpty(value) {
  if (value === null || value === undefined) return true;
  if (typeof value === 'string') return value.trim().length === 0;
  if (Array.isArray(value)) return value.length === 0;
  if (typeof value === 'object') return Object.keys(value).length === 0;
  return false;
}

/**
 * Check required field
 *
 * @param {any} value - Value
 * @returns {boolean|string} - true if valid, error string if not
 */
export function required(value) {
  return !isEmpty(value) || 'This field is required';
}

/**
 * Check minimum length
 *
 * @param {number} min - Minimum length
 * @returns {Function} - Validation function
 */
export function minLength(min) {
  return (value) => {
    if (isEmpty(value)) return true;
    return value.length >= min || `Minimum length ${min} characters`;
  };
}

/**
 * Check maximum length
 *
 * @param {number} max - Maximum length
 * @returns {Function} - Validation function
 */
export function maxLength(max) {
  return (value) => {
    if (isEmpty(value)) return true;
    return value.length <= max || `Maximum length ${max} characters`;
  };
}

/**
 * Check email
 *
 * @param {string} value - Email
 * @returns {boolean|string} - Validation result
 */
export function email(value) {
  if (isEmpty(value)) return true;

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(value) || 'Invalid email address';
}

/**
 * Check number
 *
 * @param {any} value - Value
 * @returns {boolean|string} - Validation result
 */
export function numeric(value) {
  if (isEmpty(value)) return true;
  return !isNaN(Number(value)) || 'Value must be a number';
}

/**
 * Check integer
 *
 * @param {any} value - Value
 * @returns {boolean|string} - Validation result
 */
export function integer(value) {
  if (isEmpty(value)) return true;
  return Number.isInteger(Number(value)) || 'Value must be an integer';
}

/**
 * Check minimum value
 *
 * @param {number} min - Minimum value
 * @returns {Function} - Validation function
 */
export function minValue(min) {
  return (value) => {
    if (isEmpty(value)) return true;
    return Number(value) >= min || `Minimum value ${min}`;
  };
}

/**
 * Check maximum value
 *
 * @param {number} max - Maximum value
 * @returns {Function} - Validation function
 */
export function maxValue(max) {
  return (value) => {
    if (isEmpty(value)) return true;
    return Number(value) <= max || `Maximum value ${max}`;
  };
}

/**
 * Check positive number
 *
 * @param {any} value - Value
 * @returns {boolean|string} - Validation result
 */
export function positive(value) {
  if (isEmpty(value)) return true;
  return Number(value) > 0 || 'Value must be positive';
}

/**
 * Check URL
 *
 * @param {string} value - URL
 * @returns {boolean|string} - Validation result
 */
export function url(value) {
  if (isEmpty(value)) return true;

  try {
    new URL(value);
    return true;
  } catch {
    return 'Invalid URL';
  }
}

/**
 * Check alias (only latin, digits, hyphen)
 *
 * @param {string} value - Alias
 * @returns {boolean|string} - Validation result
 */
export function alias(value) {
  if (isEmpty(value)) return true;

  const aliasRegex = /^[a-z0-9-]+$/;
  return aliasRegex.test(value) || 'Alias can only contain latin letters, digits and hyphen';
}

/**
 * Check article (letters, digits, hyphen, underscore)
 *
 * @param {string} value - Article
 * @returns {boolean|string} - Validation result
 */
export function article(value) {
  if (isEmpty(value)) return true;

  const articleRegex = /^[a-zA-Z0-9_-]+$/;
  return articleRegex.test(value) || 'Article can only contain letters, digits, hyphen and underscore';
}

/**
 * Check uniqueness (async)
 *
 * @param {Function} checkFunction - Check function (must return Promise<boolean>)
 * @param {string} errorMessage - Error message
 * @returns {Function} - Validation function
 */
export function unique(checkFunction, errorMessage = 'Value already in use') {
  return async (value) => {
    if (isEmpty(value)) return true;

    try {
      const isUnique = await checkFunction(value);
      return isUnique || errorMessage;
    } catch (error) {
      console.error('[Validation] Unique check failed:', error);
      return 'Uniqueness check error';
    }
  };
}

/**
 * Check regex match
 *
 * @param {RegExp} regex - Regular expression
 * @param {string} errorMessage - Error message
 * @returns {Function} - Validation function
 */
export function matches(regex, errorMessage = 'Invalid format') {
  return (value) => {
    if (isEmpty(value)) return true;
    return regex.test(value) || errorMessage;
  };
}

/**
 * Check match with another field (e.g., password confirmation)
 *
 * @param {any} otherValue - Other field value
 * @param {string} fieldName - Other field name
 * @returns {Function} - Validation function
 */
export function sameAs(otherValue, fieldName = 'other field') {
  return (value) => {
    return value === otherValue || `Value must match ${fieldName}`;
  };
}

/**
 * Composite validator (multiple rules)
 *
 * @param {Array<Function>} rules - Array of validation functions
 * @returns {Function} - Validation function
 */
export function validate(...rules) {
  return async (value) => {
    for (const rule of rules) {
      const result = await rule(value);
      if (result !== true) {
        return result;
      }
    }
    return true;
  };
}

/**
 * Create validation rules for PrimeVue form
 *
 * @param {Object} rulesConfig - Rules configuration { fieldName: [rule1, rule2, ...] }
 * @returns {Object} - Rules for PrimeVue
 */
export function createValidationRules(rulesConfig) {
  const rules = {};

  for (const [field, fieldRules] of Object.entries(rulesConfig)) {
    rules[field] = validate(...fieldRules);
  }

  return rules;
}
export default {
  isEmpty,
  required,
  minLength,
  maxLength,
  email,
  numeric,
  integer,
  minValue,
  maxValue,
  positive,
  url,
  alias,
  article,
  unique,
  matches,
  sameAs,
  validate,
  createValidationRules,
};
