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
                                    <i class="bi bi-shield-lock text-success benefit-icon"></i>
                                </div>
                                <h6 class="mb-2">Безопасная оплата</h6>
                                <p class="text-muted mb-0 small">Защита персональных данных и платежей</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-headset text-info benefit-icon"></i>
                                </div>
                                <h6 class="mb-2">Поддержка 24/7</h6>
                                <p class="text-muted mb-0 small">Всегда на связи для помощи с заказом</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-arrow-repeat text-warning benefit-icon"></i>
                                </div>
                                <h6 class="mb-2">Легкий возврат</h6>
                                <p class="text-muted mb-0 small">14 дней на возврат товара без объяснения причин</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    {/block}
