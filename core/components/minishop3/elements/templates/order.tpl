    {extends 'file:templates/base.tpl'}
    {block 'pagecontent'}
        <div class="container py-4">
            {set $cart_page_id = 'ms3_cart_page_id' | option}

            {* Хлебные крошки *}
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Главная</a></li>
                    {if $cart_page_id > 0}
                        <li class="breadcrumb-item">
                            <a href="/{$cart_page_id | url}"> {'ms3_frontend_go_to_cart' | lexicon}</a>
                        </li>
                    {/if}
                    <li class="breadcrumb-item active" aria-current="page">{$_modx->resource.pagetitle}</li>
                </ol>
            </nav>

            <main>
                {* Заголовок страницы *}
                <div class="page-header mb-4">
                    <h1 class="mb-2">
                        <i class="bi bi-clipboard-check me-2 text-primary"></i>
                        {$_modx->resource.pagetitle}
                    </h1>
                    {if $_modx->resource.introtext}
                        <p class="lead text-muted mb-0">{$_modx->resource.introtext}</p>
                    {else}
                        <p class="lead text-muted mb-0">Заполните форму для оформления заказа</p>
                    {/if}
                </div>

                {* Основная форма заказа *}
                <div class="order-wrapper">
                    {'!msOrder'|snippet:[
                        'tpl' => 'tpl.msOrder',
                    ]}
                </div>

                {* Безопасность и гарантии *}
                <div class="order-benefits mt-5 pt-5 border-top">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-shield-lock text-success" style="font-size: 3rem;"></i>
                                </div>
                                <h6 class="mb-2">Безопасная оплата</h6>
                                <p class="text-muted mb-0 small">Защита персональных данных и платежей</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-headset text-info" style="font-size: 3rem;"></i>
                                </div>
                                <h6 class="mb-2">Поддержка 24/7</h6>
                                <p class="text-muted mb-0 small">Всегда на связи для помощи с заказом</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-arrow-repeat text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <h6 class="mb-2">Легкий возврат</h6>
                                <p class="text-muted mb-0 small">14 дней на возврат товара без объяснения причин</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        {* Дополнительные стили для страницы заказа *}
        <style>
            .order-wrapper {
                background: #fff;
                border-radius: 8px;
                padding: 2rem;
                box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            }

            .order-wrapper h4 {
                color: #495057;
                font-weight: 600;
                margin-bottom: 1.5rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid #e9ecef;
            }

            .order-wrapper .form-group {
                margin-bottom: 1.25rem;
            }

            .order-wrapper .required-star {
                color: #dc3545;
            }

            .order-wrapper .form-control,
            .order-wrapper .form-select {
                border-radius: 6px;
                border: 1px solid #dee2e6;
                transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }

            .order-wrapper .form-control:focus,
            .order-wrapper .form-select:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }

            .order-wrapper .form-control.error {
                border-color: #dc3545;
            }

            .order-wrapper .form-check {
                padding: 1rem;
                margin-bottom: 0.75rem;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                transition: all 0.2s ease;
            }

            .order-wrapper .form-check:hover {
                border-color: #0d6efd;
                background-color: #f8f9fa;
            }

            .order-wrapper .form-check-input:checked + .form-check-label {
                color: #0d6efd;
                font-weight: 500;
            }

            .order-wrapper .form-check img {
                max-height: 40px;
                margin-right: 0.5rem;
            }

            .order-benefits {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 2rem;
            }

            #ms3_order_cost {
                color: #0d6efd;
                font-weight: 700;
            }

            .order-summary {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 1.5rem;
                margin-top: 2rem;
            }

            .order-summary h4 {
                border: none !important;
                margin-bottom: 1rem;
            }

            @media (max-width: 768px) {
                .order-wrapper {
                    padding: 1rem;
                }

                .order-benefits {
                    padding: 1rem;
                }

                .order-wrapper .col-md-4 {
                    margin-bottom: 0.5rem;
                }
            }
        </style>
    {/block}
