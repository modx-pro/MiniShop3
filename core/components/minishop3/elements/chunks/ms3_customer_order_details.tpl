<div class="ms3-customer-order-details">
    {* Навигация назад *}
    <div class="mb-3">
        <a href="?" class="btn btn-sm btn-outline-secondary">
            <svg width="16" height="16" fill="currentColor" class="me-1">
                <use xlink:href="#icon-arrow-left"/>
            </svg>
            {'ms3_customer_orders_back' | lexicon}
        </a>
    </div>

    {* Информация о заказе *}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    {'ms3_customer_order_title' | lexicon} №{$order.num}
                </h5>
                <span class="badge bg-light text-dark" style="color: {$order.status_color} !important;">
                    {$order.status_name}
                </span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">
                <small>{'ms3_customer_order_created' | lexicon}: {$order.createdon_formatted}</small>
            </p>

            {* Таблица товаров *}
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{'ms3_cart_title' | lexicon}</th>
                            <th class="text-center" style="width: 100px;">{'ms3_cart_count' | lexicon}</th>
                            <th class="text-end" style="width: 120px;">{'ms3_cart_price' | lexicon}</th>
                            <th class="text-end" style="width: 120px;">{'ms3_cart_cost' | lexicon}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $products as $product}
                        <tr>
                            <td>
                                <div class="fw-semibold">{$product.pagetitle}</div>
                                {if $product.article}
                                <div class="small text-muted">{'ms3_frontend_article' | lexicon}: {$product.article}</div>
                                {/if}
                                {if $product.options && count($product.options) > 0}
                                <div class="small text-muted mt-1">
                                    {foreach $product.options as $option => $value}
                                    {$option}: {$value}{if !$value@last}; {/if}
                                    {/foreach}
                                </div>
                                {/if}
                            </td>
                            <td class="text-center text-nowrap">
                                <span class="badge bg-secondary">{$product.count} {'ms3_frontend_count_unit' | lexicon}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                {if $product.old_price && $product.old_price > $product.price}
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
                            <td colspan="3" class="text-end fw-bold">{'ms3_frontend_cart_total' | lexicon}:</td>
                            <td class="text-end fw-bold">{$total.cart_cost} {'ms3_frontend_currency' | lexicon}</td>
                        </tr>
                        {if $total.delivery_cost}
                        <tr>
                            <td colspan="3" class="text-end">
                                <svg class="me-1" width="16" height="16" fill="currentColor">
                                    <use xlink:href="#icon-truck"/>
                                </svg>
                                {'ms3_frontend_delivery' | lexicon}:
                            </td>
                            <td class="text-end">{$total.delivery_cost} {'ms3_frontend_currency' | lexicon}</td>
                        </tr>
                        {/if}
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end fs-5">{'ms3_frontend_total' | lexicon}:</td>
                            <td class="text-end fs-5">{$total.cost} {'ms3_frontend_currency' | lexicon}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {* Адрес доставки *}
    {if $address && count($address) > 0}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <svg class="me-1" width="16" height="16" fill="currentColor">
                    <use xlink:href="#icon-map-pin"/>
                </svg>
                {'ms3_frontend_delivery_address' | lexicon}
            </h6>
        </div>
        <div class="card-body">
            <p class="mb-0">
                {if $address.index}{$address.index}, {/if}
                {$address.city}{if $address.region}, {$address.region}{/if}{if $address.country}, {$address.country}{/if}
                <br>
                {$address.street}{if $address.building}, д. {$address.building}{/if}{if $address.entrance}, п. {$address.entrance}{/if}{if $address.floor}, эт. {$address.floor}{/if}{if $address.room}, кв. {$address.room}{/if}
                {if $address.metro}
                <br>
                <small class="text-muted">м. {$address.metro}</small>
                {/if}
                {if $address.text_address}
                <br>
                <small class="fst-italic text-muted">{$address.text_address}</small>
                {/if}
            </p>
        </div>
    </div>
    {/if}

    {* Доставка и оплата *}
    <div class="row">
        {if $delivery && count($delivery) > 0}
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <svg class="me-1" width="16" height="16" fill="currentColor">
                            <use xlink:href="#icon-truck"/>
                        </svg>
                        {'ms3_frontend_delivery_method' | lexicon}
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">{$delivery.name}</p>
                    {if $delivery.description}
                    <small class="text-muted">{$delivery.description}</small>
                    {/if}
                </div>
            </div>
        </div>
        {/if}

        {if $payment && count($payment) > 0}
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <svg class="me-1" width="16" height="16" fill="currentColor">
                            <use xlink:href="#icon-credit-card"/>
                        </svg>
                        {'ms3_frontend_payment_method' | lexicon}
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">{$payment.name}</p>
                    {if $payment.description}
                    <small class="text-muted">{$payment.description}</small>
                    {/if}
                </div>
            </div>
        </div>
        {/if}
    </div>

    {* Комментарий *}
    {if $order.comment}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <svg class="me-1" width="16" height="16" fill="currentColor">
                    <use xlink:href="#icon-message"/>
                </svg>
                {'ms3_frontend_comment' | lexicon}
            </h6>
        </div>
        <div class="card-body">
            <p class="mb-0">{$order.comment}</p>
        </div>
    </div>
    {/if}
</div>
