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
                                <i class="bi bi-truck text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="mb-2">Быстрая доставка</h5>
                            <p class="text-muted mb-0 small">Доставим заказ в течение 1-3 рабочих дней</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="mb-2">Гарантия качества</h5>
                            <p class="text-muted mb-0 small">Все товары сертифицированы и имеют гарантию</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="bi bi-credit-card text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="mb-2">Удобная оплата</h5>
                            <p class="text-muted mb-0 small">Наличными, картой или онлайн-переводом</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    {* Дополнительные стили для корзины *}
    <style>
        .cart-wrapper {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }

        .msCart .ms-image img {
            max-width: 80px;
            height: auto;
            border-radius: 4px;
            object-fit: cover;
        }

        .msCart .quantity {
            gap: 0.5rem;
        }

        .msCart .qty-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-weight: bold;
        }

        .msCart .qty-input {
            text-align: center;
            width: 60px;
            padding: 0.375rem 0.5rem;
        }

        .msCart .old_price {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .msCart .ms-footer {
            background: #f8f9fa;
            font-weight: 600;
        }

        .msCart .ms-footer th {
            padding: 1rem;
            vertical-align: middle;
        }

        .cart-benefits {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
        }

        .cart-actions .btn {
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .cart-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        @media (max-width: 768px) {
            .cart-wrapper {
                padding: 1rem;
            }

            .msCart .ms-image img {
                max-width: 60px;
            }

            .cart-benefits {
                padding: 1rem;
            }
        }
    </style>

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
