/**
 * Менеджер токенов покупателя
 *
 * Управляет токеном авторизации покупателя:
 * - Хранение в localStorage
 * - Проверка срока действия (expiry)
 * - Автоматическое получение нового токена при отсутствии/истечении
 *
 * @example
 * const tokenManager = new TokenManager({ tokenName: 'ms3_token' })
 * await tokenManager.ensureToken()
 * const token = tokenManager.getToken()
 */
class TokenManager {
  /**
   * @param {Object} config - Конфигурация
   * @param {string} config.tokenName - Ключ для хранения токена в localStorage
   */
  constructor (config) {
    this.tokenName = config.tokenName || 'ms3_token'
    this.apiClient = null // Будет установлен позже через setApiClient()
  }

  /**
   * Установка ApiClient (для получения нового токена с сервера)
   *
   * @param {ApiClient} apiClient
   */
  setApiClient (apiClient) {
    this.apiClient = apiClient
  }

  /**
   * Получить токен из localStorage
   *
   * @returns {string|null} - Токен или null если токен отсутствует/истёк
   */
  getToken () {
    const tokenData = this.getTokenData()
    return tokenData ? tokenData.token : null
  }

  /**
   * Получить полные данные токена (token + expiry)
   *
   * @returns {Object|null} - { token: string, expiry: number } или null
   */
  getTokenData () {
    const stored = localStorage.getItem(this.tokenName)
    if (!stored) {
      return null
    }

    try {
      const data = JSON.parse(stored)
      const now = Date.now()

      // Проверяем срок действия
      if (now > data.expiry) {
        this.removeToken()
        return null
      }

      return data
    } catch (e) {
      // Если JSON невалидный - удаляем
      this.removeToken()
      return null
    }
  }

  /**
   * Сохранить токен в localStorage
   *
   * @param {string} token - Токен
   * @param {number} lifetime - Время жизни токена в секундах
   */
  setToken (token, lifetime) {
    const data = {
      token,
      expiry: Date.now() + (lifetime * 1000)
    }
    localStorage.setItem(this.tokenName, JSON.stringify(data))
  }

  /**
   * Удалить токен из localStorage
   */
  removeToken () {
    localStorage.removeItem(this.tokenName)
  }

  /**
   * Проверить наличие валидного токена, получить новый если нужно
   *
   * @returns {Promise<void>}
   */
  async ensureToken () {
    // Если токен есть и валиден - ничего не делаем
    if (this.getToken()) {
      return
    }

    // Получаем новый токен с сервера
    await this.fetchNewToken()
  }

  /**
   * Получить новый токен с сервера
   *
   * @returns {Promise<void>}
   */
  async fetchNewToken () {
    if (!this.apiClient) {
      console.error('TokenManager: ApiClient не установлен. Используйте setApiClient()')
      return
    }

    try {
      // Создаём URL для получения токена через api.php (фронтенд API)
      const url = new URL(this.apiClient.baseUrl, window.location.origin)
      url.searchParams.set('route', '/api/v1/customer/token/get')

      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      const result = await response.json()

      if (result.success && result.data) {
        // api.php возвращает данные напрямую в result.data
        this.setToken(result.data.token, result.data.lifetime)
      } else {
        console.error('TokenManager: Не удалось получить токен', result)
      }
    } catch (error) {
      console.error('TokenManager: Ошибка при получении токена', error)
    }
  }

  /**
   * Обновить существующий токен (продлить время жизни)
   *
   * @returns {Promise<void>}
   */
  async refreshToken () {
    if (!this.apiClient) {
      console.error('TokenManager: ApiClient не установлен')
      return
    }

    try {
      const response = await this.apiClient.post('/customer/token/update')

      if (response.success && response.data) {
        this.setToken(response.data.token, response.data.lifetime)
      }
    } catch (error) {
      console.error('TokenManager: Ошибка при обновлении токена', error)
    }
  }
}
