<form class="ms3_form ms3_order_form" method="post">
    {* Секция 1: Контактные данные и способы оплаты *}
    <div class="row g-4 mb-4">
        {* Контактные данные *}
        <div class="col-12 col-lg-6">
            <div class="order-section">
                <h4>
                    <svg width="20" height="20" fill="currentColor" class="me-2 text-primary">
                        <use href="#icon-person"/>
                    </svg>
                    {'ms3_frontend_credentials' | lexicon}
                </h4>

                {foreach ['first_name','last_name','email','phone'] as $field}
                    <div class="mb-3">
                        <label class="form-label" for="{$field}">
                            {('ms3_frontend_' ~ $field) | lexicon} <span class="required-star">*</span>
                        </label>
                        <input type="text" id="{$field}" placeholder="{('ms3_frontend_' ~ $field) | lexicon}"
                            name="{$field}" value="{$form[$field]}"
                            class="form-control{($field in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                {/foreach}

                <div class="mb-3">
                    <label class="form-label" for="comment">
                        {'ms3_frontend_comment' | lexicon}
                    </label>
                    <textarea name="comment" id="comment" placeholder="{'ms3_frontend_comment' | lexicon}"
                        rows="3"
                        class="form-control{('comment' in list $errors) ? ' error' : ''}">{$form.comment}</textarea>
                    <div class="invalid-feedback"></div>
                </div>
            </div>
        </div>

        {* Способы оплаты *}
        <div class="col-12 col-lg-6">
            <div class="order-section">
                <h4>
                    <svg width="20" height="20" fill="currentColor" class="me-2 text-primary">
                        <use href="#icon-credit-card"/>
                    </svg>
                    {'ms3_frontend_payments' | lexicon}
                </h4>

                <div class="payment-methods">
                    {foreach $payments as $payment}
                        <div class="form-check payment-option">
                            <input class="form-check-input" type="radio" name="payment_id"
                                   id="payment_{$payment.id}" value="{$payment.id}"
                                   {if $order.payment_id == $payment.id}checked{/if}>
                            <label class="form-check-label w-100" for="payment_{$payment.id}">
                                <div class="d-flex align-items-center">
                                    {if $payment.logo?}
                                        <img src="{$payment.logo}" alt="{$payment.name}" title="{$payment.name}" class="payment-logo me-3"/>
                                    {/if}
                                    <div class="flex-grow-1">
                                        <div class="payment-name fw-semibold">{$payment.name}</div>
                                        {if $payment.description?}
                                            <div class="payment-desc small text-muted mt-1">{$payment.description}</div>
                                        {/if}
                                    </div>
                                </div>
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>

    {* Секция 2: Доставка и адрес *}
    <div class="row g-4 mb-4">
        {* Способы доставки *}
        <div class="col-12 col-lg-6">
            <div class="order-section" id="deliveries">
                <h4>
                    <svg width="20" height="20" fill="currentColor" class="me-2 text-primary">
                        <use href="#icon-truck"/>
                    </svg>
                    {'ms3_frontend_deliveries' | lexicon}
                </h4>

                <div class="delivery-methods">
                    {foreach $deliveries as $delivery}
                        <div class="form-check delivery-option">
                            <input class="form-check-input" type="radio" name="delivery_id"
                                   id="delivery_{$delivery.id}" value="{$delivery.id}"
                                   {if $order.delivery_id == $delivery.id}checked{/if}>
                            <label class="form-check-label w-100" for="delivery_{$delivery.id}">
                                <div class="d-flex align-items-center">
                                    {if $delivery.logo?}
                                        <img src="{$delivery.logo}" alt="{$delivery.name}" title="{$delivery.name}" class="delivery-logo me-3"/>
                                    {/if}
                                    <div class="flex-grow-1">
                                        <div class="delivery-name fw-semibold">{$delivery.name}</div>
                                        {if $delivery.description?}
                                            <div class="delivery-desc small text-muted mt-1">{$delivery.description}</div>
                                        {/if}
                                    </div>
                                </div>
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>

        {* Адрес доставки *}
        <div class="col-12 col-lg-6">
            <div class="order-section">
                <h4>
                    <svg width="20" height="20" fill="currentColor" class="me-2 text-primary">
                        <use href="#icon-geo-alt"/>
                    </svg>
                    {'ms3_frontend_address' | lexicon}
                </h4>

                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label" for="index">
                            {('ms3_frontend_index') | lexicon} <span class="required-star">*</span>
                        </label>
                        <input type="text" id="index" placeholder="{('ms3_frontend_index') | lexicon}"
                            name="index" value="{$form.index}"
                            class="form-control{('index' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-8">
                        <label class="form-label" for="region">
                            {('ms3_frontend_region') | lexicon} <span class="required-star">*</span>
                        </label>
                        <input type="text" id="region" placeholder="{('ms3_frontend_region') | lexicon}"
                            name="region" value="{$form.region}"
                            class="form-control{('region' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="city">
                        {('ms3_frontend_city') | lexicon} <span class="required-star">*</span>
                    </label>
                    <input type="text" id="city" placeholder="{('ms3_frontend_city') | lexicon}"
                        name="city" value="{$form.city}"
                        class="form-control{('city' in list $errors) ? ' error' : ''}">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="street">
                        {('ms3_frontend_street') | lexicon} <span class="required-star">*</span>
                    </label>
                    <input type="text" id="street" placeholder="{('ms3_frontend_street') | lexicon}"
                        name="street" value="{$form.street}"
                        class="form-control{('street' in list $errors) ? ' error' : ''}">
                    <div class="invalid-feedback"></div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="building">
                            {('ms3_frontend_building') | lexicon} <span class="required-star">*</span>
                        </label>
                        <input type="text" id="building" placeholder="{('ms3_frontend_building') | lexicon}"
                            name="building" value="{$form.building}"
                            class="form-control{('building' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-2">
                        <label class="form-label" for="entrance">
                            {('ms3_frontend_entrance') | lexicon}
                        </label>
                        <input type="text" id="entrance" placeholder="№"
                            name="entrance" value="{$form.entrance}"
                            class="form-control{('entrance' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-2">
                        <label class="form-label" for="floor">
                            {('ms3_frontend_floor') | lexicon}
                        </label>
                        <input type="text" id="floor" placeholder="№"
                            name="floor" value="{$form.floor}"
                            class="form-control{('floor' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-2">
                        <label class="form-label" for="room">
                            {('ms3_frontend_room') | lexicon}
                        </label>
                        <input type="text" id="room" placeholder="№"
                            name="room" value="{$form.room}"
                            class="form-control{('room' in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="text_address">
                        {('ms3_frontend_text_address') | lexicon}
                    </label>
                    <textarea name="text_address" id="text_address" placeholder="{'ms3_frontend_text_address' | lexicon}"
                        rows="2"
                        class="form-control{('text_address' in list $errors) ? ' error' : ''}">{$form.text_address}</textarea>
                    <div class="invalid-feedback"></div>
                    <div class="form-text">Укажите дополнительную информацию для доставки</div>
                </div>
            </div>
        </div>
    </div>
</form>

{* Итоговая панель с ценой и кнопками *}
<div class="order-summary">
    <div class="row align-items-center g-3">
        <div class="col-12 col-md-6">
            <div class="cost-breakdown">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Товары:</span>
                    <span class="fw-semibold">
                        <span id="ms3_order_cart_cost">{$order.cart_cost ?: 0}</span> {'ms3_frontend_currency' | lexicon}
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Доставка:</span>
                    <span class="fw-semibold">
                        <span id="ms3_order_delivery_cost">{$order.delivery_cost ?: 0}</span> {'ms3_frontend_currency' | lexicon}
                    </span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="h5 mb-0">{'ms3_frontend_order_cost' | lexicon}:</span>
                    <span class="h4 mb-0 text-primary">
                        <span id="ms3_order_cost">{$order.cost ?: 0}</span> {'ms3_frontend_currency' | lexicon}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="d-flex gap-2 justify-content-md-end">
                <form class="ms3_form">
                    <input type="hidden" name="ms3_action" value="order/clean">
                    <button type="button" class="btn btn-outline-danger ms3_link">
                        <svg width="16" height="16" fill="currentColor" class="me-1">
                            <use href="#icon-x-circle"/>
                        </svg>
                        {'ms3_frontend_order_cancel' | lexicon}
                    </button>
                </form>
                <form class="ms3_form">
                    <input type="hidden" name="ms3_action" value="order/submit">
                    <button type="submit" class="btn btn-lg btn-primary">
                        <svg width="20" height="20" fill="currentColor" class="me-2">
                            <use href="#icon-check-circle"/>
                        </svg>
                        {'ms3_frontend_order_submit' | lexicon}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .order-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        height: 100%;
    }

    .payment-option,
    .delivery-option {
        cursor: pointer;
    }

    .payment-logo,
    .delivery-logo {
        max-height: 32px;
        max-width: 80px;
        object-fit: contain;
    }

    .order-summary {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 8px;
        padding: 2rem;
        margin-top: 2rem;
        border: 2px solid #dee2e6;
    }

    .cost-breakdown {
        font-size: 1.1rem;
    }
</style>
