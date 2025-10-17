/**
 * Утилиты валидации для форм MiniShop3
 *
 * Содержит правила валидации совместимые с MODX и PrimeVue
 */

/**
 * Проверка на пустое значение
 *
 * @param {any} value - Значение
 * @returns {boolean} - Результат проверки
 */
export function isEmpty(value) {
  if (value === null || value === undefined) return true;
  if (typeof value === 'string') return value.trim().length === 0;
  if (Array.isArray(value)) return value.length === 0;
  if (typeof value === 'object') return Object.keys(value).length === 0;
  return false;
}

/**
 * Проверка обязательного поля
 *
 * @param {any} value - Значение
 * @returns {boolean|string} - true если валидно, строка с ошибкой если нет
 */
export function required(value) {
  return !isEmpty(value) || 'Это поле обязательно для заполнения';
}

/**
 * Проверка минимальной длины
 *
 * @param {number} min - Минимальная длина
 * @returns {Function} - Функция валидации
 */
export function minLength(min) {
  return (value) => {
    if (isEmpty(value)) return true; // Используем required для проверки обязательности
    return value.length >= min || `Минимальная длина ${min} символов`;
  };
}

/**
 * Проверка максимальной длины
 *
 * @param {number} max - Максимальная длина
 * @returns {Function} - Функция валидации
 */
export function maxLength(max) {
  return (value) => {
    if (isEmpty(value)) return true;
    return value.length <= max || `Максимальная длина ${max} символов`;
  };
}

/**
 * Проверка email
 *
 * @param {string} value - Email
 * @returns {boolean|string} - Результат валидации
 */
export function email(value) {
  if (isEmpty(value)) return true;

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(value) || 'Некорректный email адрес';
}

/**
 * Проверка числа
 *
 * @param {any} value - Значение
 * @returns {boolean|string} - Результат валидации
 */
export function numeric(value) {
  if (isEmpty(value)) return true;
  return !isNaN(Number(value)) || 'Значение должно быть числом';
}

/**
 * Проверка целого числа
 *
 * @param {any} value - Значение
 * @returns {boolean|string} - Результат валидации
 */
export function integer(value) {
  if (isEmpty(value)) return true;
  return Number.isInteger(Number(value)) || 'Значение должно быть целым числом';
}

/**
 * Проверка минимального значения
 *
 * @param {number} min - Минимальное значение
 * @returns {Function} - Функция валидации
 */
export function minValue(min) {
  return (value) => {
    if (isEmpty(value)) return true;
    return Number(value) >= min || `Минимальное значение ${min}`;
  };
}

/**
 * Проверка максимального значения
 *
 * @param {number} max - Максимальное значение
 * @returns {Function} - Функция валидации
 */
export function maxValue(max) {
  return (value) => {
    if (isEmpty(value)) return true;
    return Number(value) <= max || `Максимальное значение ${max}`;
  };
}

/**
 * Проверка на положительное число
 *
 * @param {any} value - Значение
 * @returns {boolean|string} - Результат валидации
 */
export function positive(value) {
  if (isEmpty(value)) return true;
  return Number(value) > 0 || 'Значение должно быть положительным';
}

/**
 * Проверка URL
 *
 * @param {string} value - URL
 * @returns {boolean|string} - Результат валидации
 */
export function url(value) {
  if (isEmpty(value)) return true;

  try {
    new URL(value);
    return true;
  } catch {
    return 'Некорректный URL';
  }
}

/**
 * Проверка alias (только латиница, цифры, дефис)
 *
 * @param {string} value - Alias
 * @returns {boolean|string} - Результат валидации
 */
export function alias(value) {
  if (isEmpty(value)) return true;

  const aliasRegex = /^[a-z0-9-]+$/;
  return aliasRegex.test(value) || 'Alias может содержать только латинские буквы, цифры и дефис';
}

/**
 * Проверка артикула (буквы, цифры, дефис, подчеркивание)
 *
 * @param {string} value - Артикул
 * @returns {boolean|string} - Результат валидации
 */
export function article(value) {
  if (isEmpty(value)) return true;

  const articleRegex = /^[a-zA-Z0-9_-]+$/;
  return articleRegex.test(value) || 'Артикул может содержать только буквы, цифры, дефис и подчеркивание';
}

/**
 * Проверка на уникальность (асинхронная)
 *
 * @param {Function} checkFunction - Функция проверки (должна возвращать Promise<boolean>)
 * @param {string} errorMessage - Сообщение об ошибке
 * @returns {Function} - Функция валидации
 */
export function unique(checkFunction, errorMessage = 'Значение уже используется') {
  return async (value) => {
    if (isEmpty(value)) return true;

    try {
      const isUnique = await checkFunction(value);
      return isUnique || errorMessage;
    } catch (error) {
      console.error('[Validation] Unique check failed:', error);
      return 'Ошибка проверки уникальности';
    }
  };
}

/**
 * Проверка соответствия регулярному выражению
 *
 * @param {RegExp} regex - Регулярное выражение
 * @param {string} errorMessage - Сообщение об ошибке
 * @returns {Function} - Функция валидации
 */
export function matches(regex, errorMessage = 'Некорректный формат') {
  return (value) => {
    if (isEmpty(value)) return true;
    return regex.test(value) || errorMessage;
  };
}

/**
 * Проверка совпадения с другим полем (например, подтверждение пароля)
 *
 * @param {any} otherValue - Значение другого поля
 * @param {string} fieldName - Название другого поля
 * @returns {Function} - Функция валидации
 */
export function sameAs(otherValue, fieldName = 'другое поле') {
  return (value) => {
    return value === otherValue || `Значение должно совпадать с ${fieldName}`;
  };
}

/**
 * Составной валидатор (несколько правил)
 *
 * @param {Array<Function>} rules - Массив функций валидации
 * @returns {Function} - Функция валидации
 */
export function validate(...rules) {
  return async (value) => {
    for (const rule of rules) {
      const result = await rule(value);
      if (result !== true) {
        return result; // Возвращаем первую ошибку
      }
    }
    return true;
  };
}

/**
 * Создание правил валидации для PrimeVue формы
 *
 * @param {Object} rulesConfig - Конфигурация правил { fieldName: [rule1, rule2, ...] }
 * @returns {Object} - Правила для PrimeVue
 */
export function createValidationRules(rulesConfig) {
  const rules = {};

  for (const [field, fieldRules] of Object.entries(rulesConfig)) {
    rules[field] = validate(...fieldRules);
  }

  return rules;
}

// Экспорт всех валидаторов как объект
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
  createValidationRules
};
