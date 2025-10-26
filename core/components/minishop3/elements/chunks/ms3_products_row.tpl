<div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
    <div class="card h-100 border-0 shadow-sm product-card" itemtype="http://schema.org/Product" itemscope>
        <meta itemprop="description" content="{$description ?: $pagetitle}">
        <meta itemprop="name" content="{$pagetitle}">

        {* Изображение товара с hover эффектом *}
        <div class="position-relative overflow-hidden d-flex align-items-center justify-content-center" style="background: #f8f9fa; aspect-ratio: 1/1;">
            <a href="/{$id | url}" class="d-block product-image-link w-100 h-100 d-flex align-items-center justify-content-center">
                {if $thumb?}
                    <img src="{$thumb}" class="product-image" alt="{$pagetitle}" title="{$pagetitle}" itemprop="image"/>
                {else}
                    <img src="{'assets_url' | option}components/minishop3/img/web/ms3_small.png"
                        srcset="{'assets_url' | option}components/minishop3/img/web/ms3_small@2x.png 2x"
                        class="product-image" alt="{$pagetitle}" title="{$pagetitle}"/>
                {/if}

                {* Оверлей при hover *}
                <div class="product-overlay">
                    <span class="text-white fw-semibold">
                        <svg width="20" height="20" fill="currentColor" class="me-1">
                            <use href="#icon-eye"/>
                        </svg>
                        Быстрый просмотр
                    </span>
                </div>
            </a>

            {* Статус наличия *}
            <div class="position-absolute top-0 start-0 m-2">
                {if $weight > 0?}
                    <span class="badge bg-success bg-opacity-90 text-white px-2 py-1 rounded-1">
                        <svg width="12" height="12" fill="currentColor" class="me-1">
                            <use href="#icon-check"/>
                        </svg>
                        В наличии
                    </span>
                {else}
                    <span class="badge bg-secondary bg-opacity-75 text-white px-2 py-1 rounded-1">Под заказ</span>
                {/if}
            </div>

            {* Скидка и бейджи *}
            <div class="position-absolute top-0 end-0 m-2 d-flex flex-column gap-1 align-items-end">
                {if $discount > 0}
                    <span class="badge bg-danger text-white px-2 py-1 rounded-1 fs-6 fw-bold">
                        -{$discount}%
                    </span>
                {/if}
                {if $new?}
                    <span class="badge bg-primary bg-opacity-90 text-white px-2 py-1 rounded-1">NEW</span>
                {/if}
                {if $popular?}
                    <span class="badge bg-warning bg-opacity-90 text-dark px-2 py-1 rounded-1">
                        <svg width="12" height="12" fill="currentColor">
                            <use href="#icon-fire"/>
                        </svg>
                        ХИТ
                    </span>
                {/if}
                {if $favorite?}
                    <span class="badge bg-danger bg-opacity-90 text-white px-2 py-1 rounded-1">
                        FAV
                    </span>
                {/if}
            </div>
        </div>

        {* Содержимое карточки *}
        <div class="card-body d-flex flex-column p-3" itemtype="http://schema.org/Offer" itemprop="offers" itemscope>
            <meta itemprop="price" content="{$price}">
            <meta itemprop="priceCurrency" content="RUB">
            <link itemprop="availability" href="http://schema.org/InStock"/>
            <link itemprop="url" href="{$id | url : ['scheme' => 'full']}"/>

            {* Производитель и артикул в одной строке *}
            <div class="d-flex justify-content-between align-items-center mb-2">
                {if $vendor_name?}
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                        {$vendor_name}
                    </small>
                {/if}
                {if $article?}
                    <small class="text-muted" style="font-size: 0.7rem;">
                        арт. {$article}
                    </small>
                {/if}
            </div>

            {* Название товара *}
            <h6 class="card-title mb-2 flex-grow-1" style="min-height: 2.8rem; line-height: 1.4;">
                <a href="/{$id | url}" class="text-decoration-none text-dark stretched-link product-title">
                    {$pagetitle}
                </a>
            </h6>

            {* Краткие характеристики (если есть) *}
            {if $color || $size}
                <div class="mb-2 pb-2 border-bottom">
                    <div class="d-flex flex-wrap gap-1" style="font-size: 0.75rem;">
                        {if $color}
                            {foreach $color as $opt}
                                {if $opt@index < 3}
                                    <span class="badge bg-light text-dark border">{$opt}</span>
                                {/if}
                            {/foreach}
                            {if ($color | length) > 3}
                                <span class="badge bg-light text-muted border">+{($color | length) - 3}</span>
                            {/if}
                        {/if}
                        {if $size}
                            {foreach $size as $opt}
                                {if $opt@index < 3}
                                    <span class="badge bg-light text-dark border">{$opt}</span>
                                {/if}
                            {/foreach}
                            {if ($size | length) > 3}
                                <span class="badge bg-light text-muted border">+{($size | length) - 3}</span>
                            {/if}
                        {/if}
                    </div>
                </div>
            {/if}

            {* Дополнительная информация *}
            <div class="mb-3">
                <div class="d-flex flex-wrap gap-2" style="font-size: 0.75rem; color: #6c757d;">
                    {if $weight > 0?}
                        <span>
                            <svg width="14" height="14" fill="currentColor" class="me-1" style="vertical-align: -2px;">
                                <use href="#icon-box"/>
                            </svg>
                            {$weight} кг
                        </span>
                    {/if}
                    <span>
                        <svg width="14" height="14" fill="currentColor" class="me-1" style="vertical-align: -2px;">
                            <use href="#icon-truck"/>
                        </svg>
                        1-3 дня
                    </span>
                </div>
            </div>

            {* Цена и кнопка *}
            <div class="mt-auto">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        {if $old_price > 0?}
                            <div class="text-decoration-line-through text-muted small mb-1" style="font-size: 0.8rem;">
                                {$old_price}
                            </div>
                        {/if}
                        <div class="fw-bold text-primary" style="font-size: 1.25rem;">
                            {$price}
                        </div>
                    </div>
                </div>

                {* Форма добавления в корзину *}
                <form method="post" class="ms3_form position-relative" style="z-index: 10;">
                    <input type="hidden" name="id" value="{$id}">
                    <input type="hidden" name="count" value="1">
                    <input type="hidden" name="options" value="[]">
                    <input type="hidden" name="ms3_action" value="cart/add">

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-sm fw-semibold" type="submit" style="padding: 0.5rem;">
                            <svg width="16" height="16" fill="currentColor" class="me-1">
                                <use href="#icon-cart"/>
                            </svg>
                            В корзину
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.product-card {
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}

.product-image {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.product-image-link:hover .product-image {
    transform: scale(1.05);
}

.product-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-image-link:hover .product-overlay {
    opacity: 1;
}

.product-title {
    transition: color 0.2s ease;
}

.product-title:hover {
    color: var(--bs-primary) !important;
}

.badge {
    backdrop-filter: blur(4px);
}
</style>
