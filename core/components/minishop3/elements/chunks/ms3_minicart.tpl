<div class="card">
    <div class="card-body">
        {if $products | length == 0}
            <div class="alert alert-warning">
                {'ms3_cart_is_empty' | lexicon}
            </div>
        {else}
            {foreach $products as $product}
                <div class="col-12 col-md-8 mt-2 mt-md-0 flex-grow-1 mb-4">
                    {var $image}
                    {if $product.thumb?}
                        <img src="{$product.thumb}" alt="{$product.pagetitle}" title="{$product.pagetitle}" class="mw-100"/>
                    {else}
                        <img src="{'assets_url' | option}components/minishop3/img/web/ms3_small.png"
                             srcset="{'assets_url' | option}components/minishop3/img/web/ms3_small@2x.png 2x"
                             alt="{$product.pagetitle}" title="{$product.pagetitle}" class="mw-100"/>
                    {/if}
                    {/var}
                    <div class="d-flex">
                        <div class="col-12 col-md-3">
                            <a href="/{$product.product_id | url}">{$image}</a>
                        </div>
                        <div class="col-12 col-md-9">
                            <div class="d-flex">
                                <div class="col-12 col-md-6 d-flex flex-column justify-content-around justify-content-md-start">
                                    <a href="/{$product.product_id | url}" class="font-weight-bold">{$product.pagetitle}</a>
                                    <span class="price ml-md-3">{$product.price} x {$product.count} = {$product.cost} {'ms3_frontend_currency' | lexicon}</span>
                                    {if $old_price > 0?}
                                        <span class="old_price ml-md-3 text-decoration-line-through">{$old_price} x {$product.count} = {$product.old_cost} {'ms3_frontend_currency' | lexicon}</span>
                                    {/if}
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="mt-2 d-flex justify-content-around justify-content-md-start gap-4">
                                        <div class="d-flex flex-column justify-content-start align-items-start">
                                            <form method="post" class="ms3_form">
                                                <input type="hidden" name="product_key" value="{$product.product_key}"/>
                                                <input type="hidden" name="ms3_action" value="cart/change"/>

                                                <div class="quantity d-flex align-items-center justify-content-between">
                                                    <button class="btn btn-primary qty-btn dec-qty" type="button">
                                                        -
                                                    </button>
                                                    <input class="form-control qty-input" type="number" name="count" value="{$product.count}" min="0" style="max-width: 50px;">
                                                    <button class="btn btn-primary qty-btn inc-qty" type="button">
                                                        +
                                                    </button>
                                                </div>
                                            </form>
                                            {if $product.options?}
                                                {if $product.color && $product.options.color}
                                                    <form class="ms3_form mt-2">
                                                        <input type="hidden" name="product_key" value="{$product.product_key}">
                                                        <input type="hidden" name="ms3_action" value="cart/changeOption"/>
                                                        <select name="options[color]" class="form-select ms3_cart_options" style="width:200px;">
                                                            <option value="">Выбери цвет</option>
                                                            {foreach $product.color as $option}
                                                                <option value="{$option}"
                                                                        {if $product.options.color == $option}selected{/if}>{$option}</option>
                                                            {/foreach}
                                                        </select>
                                                    </form>
                                                {/if}

                                                {if $product.size && $product.options.size}
                                                    <form class="ms3_form  mt-2">
                                                        <input type="hidden" name="product_key" value="{$product.product_key}">
                                                        <input type="hidden" name="ms3_action" value="cart/changeOption"/>
                                                        <select name="options[size]" class="form-select mt-2 ms3_cart_options"
                                                                style="width:200px;">
                                                            <option value="">Выбери размер</option>
                                                            {foreach $product.size as $option}
                                                                <option value="{$option}"
                                                                        {if $product.options.size == $option}selected{/if}>{$option}</option>
                                                            {/foreach}
                                                        </select>
                                                    </form>
                                                {/if}
                                            {/if}
                                        </div>

                                        <div class="product-remove-area d-flex flex-column align-items-end">

                                            <form method="post" class="ms3_form text-md-right">
                                                <input type="hidden" name="product_key" value="{$product.product_key}">
                                                <input type="hidden" name="ms3_action" value="cart/remove"/>
                                                <button class="btn  btn-danger" type="submit">&times;</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            {/foreach}
        {/if}
    </div>

</div>
