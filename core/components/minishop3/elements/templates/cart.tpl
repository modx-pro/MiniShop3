{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container py-4">
        {* Хлебные крошки *}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Главная</a></li>
                <li class="breadcrumb-item active" aria-current="page">{$_modx->resource.pagetitle}</li>
            </ol>
        </nav>

        <main>
            {* Заголовок корзины *}
            <div class="page-header mb-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="mb-2">
                            <i class="bi bi-cart3 me-2 text-primary"></i>
                            {$_modx->resource.pagetitle}
                        </h1>
                        {if $_modx->resource.introtext}
                            <p class="lead text-muted mb-0">{$_modx->resource.introtext}</p>
                        {/if}
                    </div>
                </div>
            </div>

            {* Содержимое корзины *}
            <div class="cart-wrapper">
                <div class="msCart">
                    {'!msCart'|snippet:[
                        'tpl' => 'tpl.msCart',
                        'selector' => '.msCart'
                    ]}
                </div>

                {* Дополнительные действия - показываем только если корзина НЕ пуста *}
                {set $order_page_id = 'ms3_order_page_id' | option}
                <div class="cart-actions mt-4 d-none" id="cart-actions">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="/" class="btn btn-outline-primary btn-lg w-100">
                                <i class="bi bi-arrow-left me-2"></i>
                                {'ms3_frontend_continue_shopping' | lexicon}
                            </a>
                        </div>
                        {if $order_page_id > 0}
                        <div class="col-md-6">
                            <a href="/{$order_page_id | url}" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-check-lg me-2"></i>
                                {'ms3_frontend_checkout' | lexicon}
                            </a>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>

            {* Преимущества покупки *}
            <div class="cart-benefits mt-5 pt-5 border-top">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-truck text-primary benefit-icon"></i>
                            </div>
                            <h5 class="mb-2">Быстрая доставка</h5>
                            <p class="text-muted mb-0 small">Доставим заказ в течение 1-3 рабочих дней</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-shield-check text-primary benefit-icon"></i>
                            </div>
                            <h5 class="mb-2">Гарантия качества</h5>
                            <p class="text-muted mb-0 small">Все товары сертифицированы и имеют гарантию</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-credit-card text-primary benefit-icon"></i>
                            </div>
                            <h5 class="mb-2">Удобная оплата</h5>
                            <p class="text-muted mb-0 small">Наличными, картой или онлайн-переводом</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    {* JavaScript для отображения кнопок действий *}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Показываем кнопки действий только если корзина не пуста
            const checkCartEmpty = function() {
                const cartAlert = document.querySelector('.msCart .alert-warning');
                const cartActions = document.getElementById('cart-actions');

                if (cartActions) {
                    if (cartAlert && cartAlert.textContent.trim()) {
                        // Корзина пуста
                        cartActions.classList.add('d-none');
                    } else {
                        // Корзина содержит товары
                        cartActions.classList.remove('d-none');
                    }
                }
            };

            // Проверяем при загрузке
            checkCartEmpty();

            // Проверяем при обновлении корзины
            document.addEventListener('ms3:cart:updated', function() {
                setTimeout(checkCartEmpty, 100);
            });
        });
    </script>
{/block}
