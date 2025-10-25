/**
 * API Request класс для работы с MiniShop3 API через MODX connector
 *
 * Особенности:
 * - Использует MODX connector.php для всех запросов
 * - Автоматически добавляет HTTP_MODAUTH токен для безопасности
 * - Поддерживает все HTTP методы (GET, POST, PUT, DELETE, PATCH)
 * - Обработка ошибок с детальной информацией
 */
class Request {
  constructor() {
    this.headers = {};
    this.init();
  }

  /**
   * Инициализация: получение конфигурации из MODX
   */
  init() {
    // Не инициализируем здесь - connector URL и MODAUTH токен
    // получаем динамически при каждом запросе через геттеры
    this.setHeaders();
  }

  /**
   * Получить connector URL (динамически)
   */
  getConnectorUrl() {
    if (typeof ms3 !== 'undefined' && ms3?.config?.connector_url) {
      return ms3.config.connector_url;
    }
    return '/assets/components/minishop3/connector.php';
  }

  /**
   * Получить MODAUTH токен (динамически)
   */
  getModAuthToken() {
    if (typeof MODx !== 'undefined' && MODx?.siteId) {
      return MODx.siteId;
    }
    return null;
  }

  /**
   * Установка заголовков по умолчанию
   */
  setHeaders() {
    this.headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    };
  }

  /**
   * Построение URL для connector запроса
   *
   * @param {string} route - API роут (например: /api/mgr/products)
   * @param {Object} params - Дополнительные GET параметры
   * @returns {string} - Полный URL
   */
  buildUrl(route, params = {}) {
    const url = new URL(this.getConnectorUrl(), window.location.origin);

    // Базовые параметры для connector
    url.searchParams.set('action', 'MiniShop3\\Processors\\Api\\Index');
    url.searchParams.set('route', route);

    // Добавляем HTTP_MODAUTH токен
    const modAuthToken = this.getModAuthToken();
    if (modAuthToken) {
      url.searchParams.set('HTTP_MODAUTH', modAuthToken);
    }

    // Добавляем дополнительные параметры
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        url.searchParams.set(key, value);
      }
    });

    return url.toString();
  }

  /**
   * Основной метод для выполнения запросов
   *
   * @param {string} method - HTTP метод
   * @param {string} route - API роут
   * @param {Object} data - Данные для отправки
   * @param {Object} options - Дополнительные опции
   * @returns {Promise<Object>} - Ответ от API
   */
  async request(method, route, data = null, options = {}) {
    try {
      const fetchOptions = {
        method,
        headers: { ...this.headers, ...options.headers },
        credentials: 'same-origin' // Важно для MODX сессий
      };

      let url;

      // Для GET запросов данные передаем через URL параметры
      if (method === 'GET' && data) {
        url = this.buildUrl(route, data);
      } else {
        url = this.buildUrl(route);

        // Для остальных методов - в body
        if (data) {
          fetchOptions.body = JSON.stringify(data);
        }
      }

      const response = await fetch(url, fetchOptions);

      // Получаем тело ответа
      const responseData = await response.json();

      // Проверяем успешность через MODX processor формат
      if (responseData.success === false) {
        throw new RequestError(
          responseData.message || 'Request failed',
          response.status,
          responseData
        );
      }

      // Если статус HTTP не успешный
      if (!response.ok) {
        throw new RequestError(
          responseData.message || `HTTP error! status: ${response.status}`,
          response.status,
          responseData
        );
      }

      // MODX процессор возвращает данные в object или data
      // Извлекаем данные из правильного места
      if (responseData.object && Object.keys(responseData.object).length > 0) {
        return responseData.object;
      } else if (responseData.data && Array.isArray(responseData.data) && responseData.data.length > 0) {
        return responseData.data;
      } else if (responseData.data && !Array.isArray(responseData.data)) {
        return responseData.data;
      }

      // Если ни object, ни data не содержат данных, возвращаем весь ответ
      return responseData;

    } catch (error) {
      // Если это уже RequestError, пробрасываем дальше
      if (error instanceof RequestError) {
        throw error;
      }

      // Иначе оборачиваем в RequestError
      throw new RequestError(
        error.message || 'Network error',
        0,
        { originalError: error }
      );
    }
  }

  /**
   * GET запрос
   */
  async get(route, params = null, options = {}) {
    return this.request('GET', route, params, options);
  }

  /**
   * POST запрос
   */
  async post(route, data = null, options = {}) {
    return this.request('POST', route, data, options);
  }

  /**
   * PUT запрос
   */
  async put(route, data = null, options = {}) {
    return this.request('PUT', route, data, options);
  }

  /**
   * DELETE запрос
   */
  async delete(route, data = null, options = {}) {
    return this.request('DELETE', route, data, options);
  }

  /**
   * PATCH запрос
   */
  async patch(route, data = null, options = {}) {
    return this.request('PATCH', route, data, options);
  }
}

/**
 * Кастомный класс ошибки для API запросов
 */
class RequestError extends Error {
  constructor(message, statusCode, data = {}) {
    super(message);
    this.name = 'RequestError';
    this.statusCode = statusCode;
    this.data = data;
  }

  /**
   * Проверка является ли ошибка ошибкой авторизации
   */
  isUnauthorized() {
    return this.statusCode === 401;
  }

  /**
   * Проверка является ли ошибка ошибкой доступа
   */
  isForbidden() {
    return this.statusCode === 403;
  }

  /**
   * Проверка является ли ошибка ошибкой валидации
   */
  isValidationError() {
    return this.statusCode === 422;
  }
}

// Экспортируем singleton instance
const request = new Request();

export default request;
export { Request, RequestError };
