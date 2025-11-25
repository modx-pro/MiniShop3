{*
 * Боковая панель личного кабинета клиента
 *
 * Включает:
 * - Информацию о клиенте (аватар, имя, email, статус верификации)
 * - Навигацию по разделам ЛК
 * - Кнопку выхода
 *
 * Переменные (передаются из родительского чанка):
 * @var array $customer - Данные клиента (first_name, last_name, email, email_verified_at)
 *
 * Определяет активный раздел по текущему ID ресурса самостоятельно.
 *}

{* Определить текущий раздел по ID страницы *}
{set $profile_id = 'ms3_customer_profile_page_id' | option}
{set $addresses_id = 'ms3_customer_addresses_page_id' | option}
{set $orders_id = 'ms3_customer_orders_page_id' | option}
{set $current_id = $_modx->resource.id}

{if $current_id == $profile_id}
    {set $current_section = 'profile'}
{elseif $current_id == $addresses_id}
    {set $current_section = 'addresses'}
{elseif $current_id == $orders_id}
    {set $current_section = 'orders'}
{else}
    {set $current_section = ''}
{/if}

<nav class="ms3-customer-sidebar">
    {* ============================================ *}
    {* ИНФОРМАЦИЯ О КЛИЕНТЕ *}
    {* ============================================ *}
    <div class="customer-info text-center">
        {* Аватар с инициалами *}
        <div class="customer-avatar mx-auto">
            {set $first_name = $customer.first_name ?: ''}
            {set $last_name = $customer.last_name ?: ''}
            {set $email = $customer.email ?: ''}
            {if $first_name && $last_name}
                {($first_name|truncate:1:'')|upper}{($last_name|truncate:1:'')|upper}
            {elseif $first_name}
                {($first_name|truncate:2:'')|upper}
            {elseif $email}
                {($email|truncate:2:'')|upper}
            {else}
                ?
            {/if}
        </div>

        {* Имя клиента *}
        <h5 class="customer-name mb-1">
            {if $first_name || $last_name}
                {$first_name} {$last_name}
            {else}
                {'ms3_customer_guest' | lexicon}
            {/if}
        </h5>

        {* Email *}
        <p class="customer-email text-muted small mb-2">{$email}</p>

        {* Статус верификации email *}
        {if $customer.email_verified_at}
        <span class="verification-badge verified">
            <svg width="12" height="12" fill="currentColor" class="me-1">
                <circle cx="6" cy="6" r="5" fill="#28a745"/>
                <path d="M3 6 L5 8 L9 4" stroke="white" stroke-width="1.5" fill="none"/>
            </svg>
            {'ms3_customer_email_verified' | lexicon}
        </span>
        {else}
        <span class="verification-badge unverified">
            <svg width="12" height="12" fill="currentColor" class="me-1">
                <circle cx="6" cy="6" r="5" fill="#ffc107"/>
                <text x="6" y="9" text-anchor="middle" fill="white" font-size="10" font-weight="bold">!</text>
            </svg>
            {'ms3_customer_email_not_verified' | lexicon}
        </span>
        {/if}
    </div>

    {* ============================================ *}
    {* НАВИГАЦИЯ *}
    {* ============================================ *}
    <ul class="nav flex-column mt-4">
        {* Профиль *}
        <li class="nav-item">
            <a class="nav-link {if $current_section == 'profile'}active{/if}"
               href="{'ms3_customer_profile_page_id' | option | url}">
                <svg width="18" height="18" fill="currentColor" class="me-2">
                    <circle cx="9" cy="5" r="3"/>
                    <path d="M3 18 C3 13 6 11 9 11 C12 11 15 13 15 18"/>
                </svg>
                {'ms3_customer_profile_title' | lexicon}
            </a>
        </li>

        {* Адреса доставки *}
        <li class="nav-item">
            <a class="nav-link {if $current_section == 'addresses'}active{/if}"
               href="{'ms3_customer_addresses_page_id' | option | url}">
                <svg width="18" height="18" fill="currentColor" class="me-2">
                    <path d="M9 2 L9 9 M9 2 L5 6 M9 2 L13 6"/>
                    <rect x="4" y="9" width="10" height="8" rx="1"/>
                </svg>
                {'ms3_customer_addresses_title' | lexicon}
            </a>
        </li>

        {* История заказов *}
        <li class="nav-item">
            <a class="nav-link {if $current_section == 'orders'}active{/if}"
               href="{'ms3_customer_orders_page_id' | option | url}">
                <svg width="18" height="18" fill="currentColor" class="me-2">
                    <rect x="3" y="3" width="12" height="14" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="6" y1="7" x2="12" y2="7" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="6" y1="10" x2="12" y2="10" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="6" y1="13" x2="10" y2="13" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                {'ms3_customer_orders_title' | lexicon}
            </a>
        </li>

        {* Разделитель *}
        <li class="nav-item">
            <hr class="my-2">
        </li>

        {* Выход *}
        <li class="nav-item">
            <a class="nav-link text-danger" href="[[~[[*id]]]]?action=logout" onclick="return confirm('{'ms3_customer_logout_confirm' | lexicon}')">
                <svg width="18" height="18" fill="currentColor" class="me-2">
                    <path d="M7 16 L3 16 C2 16 2 15 2 14 L2 2 C2 1 2 1 3 1 L7 1"/>
                    <path d="M7 8 L15 8 M12 5 L15 8 L12 11" stroke="currentColor" stroke-width="1.5" fill="none"/>
                </svg>
                {'ms3_customer_logout' | lexicon}
            </a>
        </li>
    </ul>
</nav>
