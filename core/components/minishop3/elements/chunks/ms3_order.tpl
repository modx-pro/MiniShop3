<form class="ms3_form ms3_order_form" method="post">
    <div class="row">
        <div class="col-12 col-md-6">
            <h4>{'ms3_frontend_credentials' | lexicon}:</h4>
            {foreach ['first_name','last_name','email','phone'] as $field}
                <div class="form-group row input-parent">
                    <label class="col-md-4 col-form-label" for="{$field}">
                        {('ms3_frontend_' ~ $field) | lexicon} <span class="required-star">*</span>
                    </label>
                    <div class="col-md-8">
                        <input type="text" id="{$field}" placeholder="{('ms3_frontend_' ~ $field) | lexicon}"
                            name="{$field}" value="{$form[$field]}"
                            class="form-control{($field in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>

                </div>
            {/foreach}

            <div class="form-group row input-parent">
                <label class="col-md-4 col-form-label" for="comment">
                    {'ms3_frontend_comment' | lexicon} <span class="required-star">*</span>
                </label>
                <div class="col-md-8">
                    <textarea name="comment" id="comment" placeholder="{'ms3_frontend_comment' | lexicon}"
                        class="form-control{('comment' in list $errors) ? ' error' : ''}">{$form.comment}</textarea>
                    <div class="invalid-feedback"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <h4>{'ms3_frontend_payments' | lexicon}:</h4>
            <div class="form-group row">
                <div class="col-12">
                    {foreach $payments as $payment}
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_id" id="payment_{$payment.id}" value="{$payment.id}"
                                   {if $order.payment_id == $payment.id}checked{/if}
                            >
                            <label class="form-check-label" for="payment_{$payment.id}">
                                {if $payment.logo?}
                                    <img src="{$payment.logo}" alt="{$payment.name}" title="{$payment.name}" class="mw-100"/>
                                {else}
                                    {$payment.name}
                                {/if}
                                {if $payment.description?}
                                    <p class="small">{$payment.description}</p>
                                {/if}
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-md-6" id="deliveries">
            <h4>{'ms3_frontend_deliveries' | lexicon}:</h4>
            <div class="form-group row">
                <div class="col-12">
                    {foreach $deliveries as $delivery}
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_id" id="delivery_{$delivery.id}" value="{$delivery.id}"
                                   {if $order.delivery_id == $delivery.id}checked{/if}
                            >
                            <label class="form-check-label" for="delivery_{$delivery.id}">
                                {if $delivery.logo?}
                                    <img src="{$delivery.logo}" alt="{$delivery.name}" title="{$delivery.name}"/>
                                {else}
                                    {$delivery.name}
                                {/if}
                                {if $delivery.description?}
                                    <p class="small">
                                        {$delivery.description}
                                    </p>
                                {/if}
                            </label>
                            <div class="invalid-feedback"></div>
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <h4>{'ms3_frontend_address' | lexicon}:</h4>
            {foreach ['index','region','city', 'street', 'building', 'entrance','floor', 'room'] as $field}
                <div class="form-group row input-parent">
                    <label class="col-md-4 col-form-label" for="{$field}">
                        {('ms3_frontend_' ~ $field) | lexicon} <span class="required-star">*</span>
                    </label>
                    <div class="col-md-8">
                        <input type="text" id="{$field}" placeholder="{('ms3_frontend_' ~ $field) | lexicon}"
                            name="{$field}" value="{$form[$field]}"
                            class="form-control{($field in list $errors) ? ' error' : ''}">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            {/foreach}

            <div class="form-group row input-parent">
                <label class="col-md-4 col-form-label" for="text_address">
                    {'ms3_frontend_text_address' | lexicon} <span class="required-star">*</span>
                </label>
                <div class="col-md-8">
                    <textarea name="text_address" id="text_address" placeholder="{'ms3_frontend_text_address' | lexicon}"
                        class="form-control{('text_address' in list $errors) ? ' error' : ''}">{$form.text_address}</textarea>
                    <div class="invalid-feedback"></div>
                </div>
            </div>

        </div>

    </div>

</form>
<form class="ms3_form">
    <input type="hidden" name="ms3_action" value="order/clean">
    <button type="button"class="btn btn-danger ms3_link">
        {'ms3_frontend_order_cancel' | lexicon}
    </button>
</form>

<hr class="mt-4 mb-4"/>


<div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-end mb-5">
    <h4 class="mb-md-0">{'ms3_frontend_order_cost' | lexicon}:</h4>
    <h3 class="mb-md-0 ml-md-2">
        <span id="ms3_order_cart_cost">{$order.cart_cost ?: 0}</span> {'ms3_frontend_currency' | lexicon} +
        <span id="ms3_order_delivery_cost">{$order.delivery_cost ?: 0}</span> {'ms3_frontend_currency' | lexicon} =
        <span id="ms3_order_cost">{$order.cost ?: 0}</span> {'ms3_frontend_currency' | lexicon}
    </h3>
    <form class="ms3_form">
        <input type="hidden" name="ms3_action" value="order/submit">
        <button type="submit" class="btn btn-lg btn-primary ml-md-2">
            {'ms3_frontend_order_submit' | lexicon}
        </button>
    </form>
</div>
