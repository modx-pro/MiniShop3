{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container py-4">
        {* Хлебные крошки *}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Главная</a></li>
                {if $_modx->resource.parent > 0}
                    <li class="breadcrumb-item"><a href="/{$_modx->resource.parent | resource : 'uri'}">{$_modx->resource.parent | resource : 'pagetitle'}</a></li>
                {/if}
                <li class="breadcrumb-item active" aria-current="page">{$_modx->resource.pagetitle}</li>
            </ol>
        </nav>

        <main>
            {* Основная информация о товаре *}
            <div class="row mb-5">
                {* Галерея товара *}
                <div class="col-lg-6 mb-4">
                    {'!msGallery'|snippet: [
                        'tpl' => 'tpl.msGallery'
                    ]}
                </div>

                {* Информация о товаре *}
                <div class="col-lg-6">
                    <div class="product-info">
                        {* Производитель *}
                        {if $vendor_name?}
                            <div class="text-muted text-uppercase mb-2 product-vendor-name">
                                {$vendor_name}
                            </div>
                        {/if}

                        {* Название товара *}
                        <h1 class="mb-3">{$_modx->resource.pagetitle}</h1>

                        {* Артикул и статус *}
                        <div class="d-flex align-items-center gap-3 mb-3">
                            {if $article?}
                                <span class="text-muted">Артикул: <strong>{$article}</strong></span>
                            {/if}

                            {if $stock? && $stock > 0}
                                <span class="badge bg-success">
                                    <svg width="14" height="14" fill="currentColor" class="me-1 product-badge-icon">
                                        <use href="#icon-check"/>
                                    </svg>
                                    В наличии
                                </span>
                            {else}
                                <span class="badge bg-secondary">Под заказ</span>
                            {/if}
                        </div>

                        {* Бейджи *}
                        <div class="d-flex gap-2 mb-4">
                            {if $new?}
                                <span class="badge bg-primary">NEW</span>
                            {/if}
                            {if $popular?}
                                <span class="badge bg-warning text-dark">
                                    <svg width="12" height="12" fill="currentColor" class="me-1">
                                        <use href="#icon-fire"/>
                                    </svg>
                                    ХИТ ПРОДАЖ
                                </span>
                            {/if}
                            {if $favorite?}
                                <span class="badge bg-danger">
                                    <svg width="12" height="12" fill="currentColor" class="me-1">
                                        <use href="#icon-heart"/>
                                    </svg>
                                    РЕКОМЕНДУЕМ
                                </span>
                            {/if}
                        </div>

                        {* Краткое описание *}
                        {if $_modx->resource.introtext}
                            <div class="product-intro mb-4 text-muted">
                                {$_modx->resource.introtext}
                            </div>
                        {/if}

                        {* Цена *}
                        <div class="product-price mb-4 p-4 bg-light rounded">
                            {if $old_price? && $old_price > 0}
                                <div class="old-price text-muted text-decoration-line-through mb-2 product-old-price-lg">
                                    {$old_price} ₽
                                </div>

                                {if $discount?}
                                    <div class="badge bg-danger mb-2">
                                        Скидка {$discount}%
                                    </div>
                                {/if}
                            {/if}
                            <div class="current-price display-4 fw-bold text-primary">
                                {$price ?: 0} ₽
                            </div>
                        </div>

                        {* Опции товара - через поля ресурса *}
                        {if $_modx->resource.color? || $_modx->resource.size?}
                            <div class="product-options mb-4">
                                {* Цвета *}
                                {if $_modx->resource.color?}
                                    <div class="option-group mb-3">
                                        <label class="form-label fw-semibold">Цвет:</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            {foreach $_modx->resource.color as $colorOption}
                                                <button type="button" class="btn btn-outline-secondary btn-sm option-btn">
                                                    {$colorOption}
                                                </button>
                                            {/foreach}
                                        </div>
                                    </div>
                                {/if}

                                {* Размеры *}
                                {if $_modx->resource.size?}
                                    <div class="option-group mb-3">
                                        <label class="form-label fw-semibold">Размер:</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            {foreach $_modx->resource.size as $sizeOption}
                                                <button type="button" class="btn btn-outline-secondary btn-sm option-btn">
                                                    {$sizeOption}
                                                </button>
                                            {/foreach}
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {/if}

                        {* Количество и кнопка добавления в корзину *}
                        <div class="ms3-product-card mb-4" data-product-id="{$_modx->resource.id}" data-ms3-product-card>
                            {* Форма добавления (когда товара НЕТ в корзине) *}
                            <form method="post" class="ms3_form" data-cart-state="add" data-ms3-form>
                                <input type="hidden" name="id" value="{$_modx->resource.id}">
                                <input type="hidden" name="options" value="[]">
                                <input type="hidden" name="ms3_action" value="cart/add">

                                <div class="row g-3 align-items-end">
                                    <div class="col-auto">
                                        <label class="form-label">{'ms3_cart_count' | lexicon}:</label>
                                        <input type="number" name="count" value="1" min="1"
                                               class="form-control product-count-input">
                                    </div>
                                    <div class="col">
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            {'ms3_cart_add' | lexicon}
                                        </button>
                                    </div>
                                </div>
                            </form>

                            {* Форма изменения количества (когда товар ЕСТЬ в корзине) *}
                            <form method="post" class="ms3_form product-cart-controls-hidden" data-cart-state="change" data-ms3-form>
                                <input type="hidden" name="product_key" value="">
                                <input type="hidden" name="ms3_action" value="cart/change">

                                <div class="row g-3 align-items-end">
                                    <div class="col-auto">
                                        <label class="form-label">{'ms3_cart_count' | lexicon}:</label>
                                        <div class="input-group product-qty-group">
                                            <button class="btn btn-outline-primary qty-btn dec-qty" type="button" data-ms3-qty="dec">−</button>
                                            <input type="number" name="count" value="1" min="0"
                                                   class="form-control text-center qty-input" data-ms3-qty="input">
                                            <button class="btn btn-outline-primary qty-btn inc-qty" type="button" data-ms3-qty="inc">+</button>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <button type="button" class="btn btn-success btn-lg w-100" disabled>
                                            ✓ {'ms3_cart_in_cart' | lexicon}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        {* Дополнительная информация *}
                        <div class="product-meta">
                            <ul class="list-unstyled mb-0">
                                {if $weight? && $weight > 0}
                                    <li class="mb-2">
                                        <svg width="16" height="16" fill="currentColor" class="me-2 text-muted">
                                            <use href="#icon-box"/>
                                        </svg>
                                        <span class="text-muted">Вес:</span> <strong>{$weight} кг</strong>
                                    </li>
                                {/if}
                                {if $made_in?}
                                    <li class="mb-2">
                                        <svg width="16" height="16" fill="currentColor" class="me-2 text-muted">
                                            <use href="#icon-globe"/>
                                        </svg>
                                        <span class="text-muted">Страна производства:</span> <strong>{$made_in}</strong>
                                    </li>
                                {/if}
                                <li>
                                    <svg width="16" height="16" fill="currentColor" class="me-2 text-muted">
                                        <use href="#icon-truck"/>
                                    </svg>
                                    <span class="text-muted">Доставка:</span> <strong>1-3 рабочих дня</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {* Табы с подробной информацией *}
            <div class="product-tabs">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#description" type="button">
                            Описание
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#specs" type="button">
                            Характеристики
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#delivery" type="button">
                            Доставка
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    {* Описание *}
                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                        <div class="content-section">
                            {if $_modx->resource.description}
                                {$_modx->resource.description}
                            {else}
                                <p class="text-muted">Подробное описание товара отсутствует.</p>
                            {/if}
                        </div>
                    </div>

                    {* Характеристики *}
                    <div class="tab-pane fade" id="specs" role="tabpanel">
                        <div class="content-section">
                            <table class="table table-striped">
                                <tbody>
                                    {if $article?}
                                        <tr>
                                            <td class="text-muted product-specs-label">Артикул</td>
                                            <td><strong>{$article}</strong></td>
                                        </tr>
                                    {/if}
                                    {if $vendor_name?}
                                        <tr>
                                            <td class="text-muted">Производитель</td>
                                            <td><strong>{$vendor_name}</strong></td>
                                        </tr>
                                    {/if}
                                    {if $made_in?}
                                        <tr>
                                            <td class="text-muted">Страна производства</td>
                                            <td><strong>{$made_in}</strong></td>
                                        </tr>
                                    {/if}
                                    {if $weight? && $weight > 0}
                                        <tr>
                                            <td class="text-muted">Вес</td>
                                            <td><strong>{$weight} кг</strong></td>
                                        </tr>
                                    {/if}
                                    {if $_modx->resource.color?}
                                        <tr>
                                            <td class="text-muted">Доступные цвета</td>
                                            <td>
                                                {foreach $_modx->resource.color as $c}
                                                    <span class="badge bg-light text-dark border me-1">{$c}</span>
                                                {/foreach}
                                            </td>
                                        </tr>
                                    {/if}
                                    {if $_modx->resource.size?}
                                        <tr>
                                            <td class="text-muted">Доступные размеры</td>
                                            <td>
                                                {foreach $_modx->resource.size as $s}
                                                    <span class="badge bg-light text-dark border me-1">{$s}</span>
                                                {/foreach}
                                            </td>
                                        </tr>
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {* Доставка *}
                    <div class="tab-pane fade" id="delivery" role="tabpanel">
                        <div class="content-section">
                            <h5 class="mb-3">Условия доставки</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="delivery-option p-3 border rounded">
                                        <div class="d-flex align-items-start mb-2">
                                            <svg width="24" height="24" fill="currentColor" class="me-3 text-primary">
                                                <use href="#icon-truck"/>
                                            </svg>
                                            <div>
                                                <h6 class="mb-1">Курьерская доставка</h6>
                                                <p class="text-muted mb-0 small">Доставка по городу 1-2 дня</p>
                                                <strong class="text-primary">от 300 ₽</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="delivery-option p-3 border rounded">
                                        <div class="d-flex align-items-start mb-2">
                                            <svg width="24" height="24" fill="currentColor" class="me-3 text-primary">
                                                <use href="#icon-box"/>
                                            </svg>
                                            <div>
                                                <h6 class="mb-1">Самовывоз</h6>
                                                <p class="text-muted mb-0 small">Из пункта выдачи</p>
                                                <strong class="text-success">Бесплатно</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Похожие товары *}
            <div class="related-products mt-5">
                <h3 class="mb-4">Похожие товары</h3>
                <div class="row">
                    {'!msProducts' | snippet : [
                        'tpl' => 'tpl.msProducts.row',
                        'parents' => $_modx->resource.parent,
                        'resources' => '-' ~ $_modx->resource.id,
                        'limit' => 4,
                        'formatPrices' => 1,
                        'withCurrency' => 0
                    ]}
                </div>
            </div>
        </main>
    </div>

    {* JavaScript для выбора опций *}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Обработка кликов по опциям
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Снимаем active с других кнопок в этой группе
                    this.closest('.option-group').querySelectorAll('.option-btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    // Активируем текущую
                    this.classList.add('active');
                });
            });

            // Активируем первую опцию по умолчанию
            document.querySelectorAll('.option-group').forEach(group => {
                const firstBtn = group.querySelector('.option-btn');
                if (firstBtn) {
                    firstBtn.classList.add('active');
                }
            });
        });
    </script>
{/block}
