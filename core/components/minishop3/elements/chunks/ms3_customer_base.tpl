{*
 * Базовый layout для всех страниц личного кабинета клиента
 *
 * Используется как обёртка для всех сервисов msCustomer:
 * - profile (профиль клиента)
 * - addresses (управление адресами)
 * - orders (история заказов)
 *
 * Включает:
 * - Боковую панель с информацией о клиенте и навигацией
 * - Область контента (через {block 'content'})
 *
 * Переменные:
 * @var array $customer - Данные клиента (id, email, first_name, last_name и т.д.)
 *}

<div class="ms3-customer-account">
    <div class="container">
        <div class="row">
            {* ============================================ *}
            {* БОКОВАЯ ПАНЕЛЬ (Sidebar) *}
            {* ============================================ *}
            <div class="col-lg-3 col-md-4 mb-4">
                {include 'tpl.msCustomer.sidebar'}
            </div>

            {* ============================================ *}
            {* ОСНОВНОЙ КОНТЕНТ *}
            {* ============================================ *}
            <div class="col-lg-9 col-md-8">
                <div class="ms3-customer-content">
                    {block 'content'}{/block}
                </div>
            </div>
        </div>
    </div>
</div>
