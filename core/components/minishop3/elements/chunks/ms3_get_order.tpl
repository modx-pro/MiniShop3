{* Order Info Card *}
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <svg class="me-2" width="20" height="20" fill="currentColor">
                    <use xlink:href="#icon-box"/>
                </svg>
                Заказ №{$order.num}
            </h5>
            <span class="badge bg-light text-dark">{$order.status_name ?: 'Новый'}</span>
        </div>
    </div>
    <div class="card-body">
        {if $order.createdon}
            <p class="text-muted mb-2">
                <small>Дата оформления: {$order.createdon | date_format:'%d.%m.%Y %H:%M'}</small>
            </p>
        {/if}

        {* Products Table *}
        <div class="table-responsive">
            <table class="table table-hover align-middle ms3-order-table">
                <thead class="table-light">
                    <tr>
                        <th>{'ms3_cart_title' | lexicon}</th>
                        <th class="text-center col-count">{'ms3_cart_count' | lexicon}</th>
                        <th class="text-end col-price">{'ms3_cart_price' | lexicon}</th>
                        <th class="text-end col-cost">{'ms3_cart_cost' | lexicon}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $products as $product}
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0 ms3-order-product-thumb-wrapper">
                                        {if $product.thumb?}
                                            <img src="{$product.thumb}" alt="{$product.pagetitle}"
                                                 class="img-thumbnail ms3-order-product-thumb"/>
                                        {else}
                                            <img src="{'assets_url' | option}components/minishop3/img/web/ms3_small.png"
                                                 srcset="{'assets_url' | option}components/minishop3/img/web/ms3_small@2x.png 2x"
                                                 alt="{$product.pagetitle}" class="img-thumbnail ms3-order-product-thumb"/>
                                        {/if}
                                    </div>
                                    <div class="flex-grow-1">
                                        {if $product.id?}
                                            <a href="{$product.id | url}" class="text-decoration-none fw-semibold">
                                                {$product.pagetitle}
                                            </a>
                                        {else}
                                            <span class="fw-semibold">{$product.name}</span>
                                        {/if}
                                        {if $product.options?}
                                            <div class="small text-muted mt-1">
                                                {$product.options | join : '; '}
                                            </div>
                                        {/if}
                                        {if $product.article?}
                                            <div class="small text-muted">
                                                Артикул: {$product.article}
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            </td>
                            <td class="text-center text-nowrap">
                                <span class="badge bg-secondary">{$product.count} {'ms3_frontend_count_unit' | lexicon}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                {if $product.old_price > $product.price}
                                    <div class="text-decoration-line-through text-muted small">{$product.old_price}</div>
                                {/if}
                                <div class="fw-semibold">{$product.price}</div>
                                <small class="text-muted">{'ms3_frontend_currency' | lexicon}</small>
                            </td>
                            <td class="text-end text-nowrap fw-bold">
                                {$product.cost} {'ms3_frontend_currency' | lexicon}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Товары:</td>
                        <td class="text-end fw-bold">{$total.cart_cost} {'ms3_frontend_currency' | lexicon}</td>
                    </tr>
                    {if $total.delivery_cost}
                        <tr>
                            <td colspan="3" class="text-end">
                                <svg class="me-1" width="16" height="16" fill="currentColor">
                                    <use xlink:href="#icon-truck"/>
                                </svg>
                                Доставка:
                            </td>
                            <td class="text-end">{$total.delivery_cost} {'ms3_frontend_currency' | lexicon}</td>
                        </tr>
                    {/if}
                    <tr class="table-primary">
                        <td colspan="3" class="text-end fw-bold fs-5">Итого:</td>
                        <td class="text-end fw-bold fs-5 text-primary">{$total.cost} {'ms3_frontend_currency' | lexicon}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{* Delivery and Payment Info *}
<div class="row g-4 mb-4">
    {* Delivery *}
    {if $delivery.name?}
        <div class="col-md-6">
            <div class="card h-100 border-0 bg-light">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <svg class="me-2 text-primary" width="20" height="20" fill="currentColor">
                            <use xlink:href="#icon-truck"/>
                        </svg>
                        Способ доставки
                    </h6>
                    <p class="mb-1 fw-semibold">{$delivery.name | lexicon}</p>
                    {if $delivery.description?}
                        <p class="small text-muted mb-0">{$delivery.description}</p>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    {* Payment *}
    {if $payment.name?}
        <div class="col-md-6">
            <div class="card h-100 border-0 bg-light">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <svg class="me-2 text-success" width="20" height="20" fill="currentColor">
                            <use xlink:href="#icon-cart"/>
                        </svg>
                        Способ оплаты
                    </h6>
                    <p class="mb-1 fw-semibold">{$payment.name | lexicon}</p>
                    {if $payment.description?}
                        <p class="small text-muted mb-0">{$payment.description}</p>
                    {/if}
                    {if $payment_link?}
                        <a href="{$payment_link}" class="btn btn-success btn-sm mt-3">
                            Оплатить заказ
                        </a>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</div>

{* Customer and Address Info *}
{if $address.receiver || $address.phone || $address.email || $address.street}
    <div class="card border-0 bg-light">
        <div class="card-body">
            <h6 class="card-title mb-3">
                <svg class="me-2 text-info" width="20" height="20" fill="currentColor">
                    <use xlink:href="#icon-globe"/>
                </svg>
                Контактные данные
            </h6>
            <div class="row g-3">
                {if $address.receiver?}
                    <div class="col-md-6">
                        <div class="small text-muted">Получатель</div>
                        <div class="fw-semibold">{$address.receiver}</div>
                    </div>
                {/if}
                {if $address.phone?}
                    <div class="col-md-6">
                        <div class="small text-muted">Телефон</div>
                        <div class="fw-semibold">{$address.phone}</div>
                    </div>
                {/if}
                {if $address.email?}
                    <div class="col-md-6">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold">{$address.email}</div>
                    </div>
                {/if}
                {if $address.street?}
                    <div class="col-12">
                        <div class="small text-muted">Адрес доставки</div>
                        <div class="fw-semibold">
                            {if $address.index?}{$address.index}, {/if}
                            {if $address.country?}{$address.country}, {/if}
                            {if $address.region?}{$address.region}, {/if}
                            {if $address.city?}{$address.city}, {/if}
                            {$address.street}
                            {if $address.building?}, д. {$address.building}{/if}
                            {if $address.room?}, кв. {$address.room}{/if}
                        </div>
                    </div>
                {/if}
                {if $order.comment?}
                    <div class="col-12">
                        <div class="small text-muted">Комментарий к заказу</div>
                        <div class="fw-semibold">{$order.comment}</div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}
