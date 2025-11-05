/**
 * HTTP клиент для работы с MiniShop3 REST API
 *
 * Простая обёртка над fetch() для взаимодействия с backend API.
 * Автоматически добавляет токен авторизации и обрабатывает JSON.
 *
 * @example
 * const client = new ApiClient({
 *   baseUrl: '/assets/components/minishop3/connector.php',
 *   tokenManager: tokenManager
 * })
 *
 * const response = await client.get('/cart/get')
 */
class ApiClient {
  /**
   * @param {Object} config - Конфигурация клиента
   * @param {string} config.baseUrl - Базовый URL API (например: '/assets/components/minishop3/action.php')
   * @param {TokenManager} config.tokenManager - Менеджер токенов
   */
  constructor (config) {
    this.baseUrl = config.baseUrl || '/assets/components/minishop3/action.php'
    this.tokenManager = config.tokenManager
  }

  /**
   * Базовый метод для выполнения HTTP запросов
   *
   * @param {string} method - HTTP метод (GET, POST и т.д.)
   * @param {string} endpoint - Endpoint API (например: '/cart/get')
   * @param {Object|null} data - Данные для отправки (для POST/PATCH)
   * @returns {Promise<Object>} - Ответ от сервера
   */
  async request (method, endpoint, data = null) {
    // Формируем URL с параметрами
    const url = new URL(this.baseUrl, window.location.origin)

    // Добавляем route как параметр
    url.searchParams.set('route', endpoint)

    // Получаем токен из TokenManager
    const token = this.tokenManager.getToken()
    if (token) {
      url.searchParams.set('ms3_token', token)
    }

    // Формируем заголовки
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    // Опции запроса
    const options = {
      method,
      headers
    }

    // Если есть данные для отправки - добавляем body
    if (data && (method === 'POST' || method === 'PATCH' || method === 'PUT')) {
      // Проверяем тип данных
      if (data instanceof FormData) {
        // FormData отправляем как есть (без Content-Type, браузер сам установит)
        options.body = data
      } else {
        // Обычный объект - отправляем как JSON
        headers['Content-Type'] = 'application/json'
        options.body = JSON.stringify(data)
      }
    }

    try {
      const response = await fetch(url.toString(), options)

      // Проверяем успешность HTTP запроса
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const result = await response.json()
      return result
    } catch (error) {
      console.error('ApiClient request error:', error)
      throw error
    }
  }

  /**
   * GET запрос
   *
   * @param {string} endpoint - Endpoint API
   * @returns {Promise<Object>}
   */
  get (endpoint) {
    return this.request('GET', endpoint)
  }

  /**
   * POST запрос
   *
   * @param {string} endpoint - Endpoint API
   * @param {Object|FormData} data - Данные для отправки
   * @returns {Promise<Object>}
   */
  post (endpoint, data) {
    return this.request('POST', endpoint, data)
  }

  /**
   * PATCH запрос (частичное обновление)
   *
   * @param {string} endpoint - Endpoint API
   * @param {Object} data - Данные для отправки
   * @returns {Promise<Object>}
   */
  patch (endpoint, data) {
    return this.request('PATCH', endpoint, data)
  }

  /**
   * DELETE запрос
   *
   * @param {string} endpoint - Endpoint API
   * @returns {Promise<Object>}
   */
  delete (endpoint) {
    return this.request('DELETE', endpoint)
  }
}
