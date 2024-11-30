<div class="table-responsive">
    <table class="table table-striped">
        <tr class="ms-header">
            <th class="ms-title">{'ms3_cart_title' | lexicon}</th>
            <th class="ms-count">{'ms3_cart_count' | lexicon}</th>
            <th class="ms-weight">{'ms3_cart_weight' | lexicon}</th>
            <th class="ms-price">{'ms3_cart_cost' | lexicon}</th>
        </tr>
        {foreach $products as $product}
            {var $image}
            {if $product.thumb?}
                <img src="{$product.thumb}" alt="{$product.pagetitle}" title="{$product.pagetitle}"/>
            {else}
                <img src="{'assets_url' | option}components/minishop3/img/web/ms3_small.png"
                     srcset="{'assets_url' | option}components/minishop3/img/web/ms3_small@2x.png 2x"
                     alt="{$product.pagetitle}" title="{$product.pagetitle}"/>
            {/if}
            {/var}
            <tr>
                <td class="ms-title">
                    <div class="d-flex gap-4">
                        <div class="image mw-100 pr-3">
                            {if $product.id?}
                                <a href="{$product.id | url}">{$image}</a>
                            {else}
                                {$image}
                            {/if}
                        </div>
                        <div class="title">
                            {if $product.id?}
                                <a href="{$product.id | url}">{$product.pagetitle}</a>
                            {else}
                                {$product.name}
                            {/if}
                            {if $product.options?}
                                <div class="small">
                                    {$product.options | join : '; '}
                                </div>
                            {/if}
                        </div>
                    </div>
                </td>
                <td class="ms-count text-nowrap">{$product.count} {'ms3_frontend_count_unit' | lexicon}</td>
                <td class="ms-weight text-nowrap">{$product.weight} {'ms3_frontend_weight_unit' | lexicon}</td>
                <td class="ms-price text-nowrap">{$product.price} {'ms3_frontend_currency' | lexicon}</td>
            </tr>
        {/foreach}
        <tr class="ms-footer">
            <th class="total">{'ms3_cart_total' | lexicon}:</th>
            <th class="total_count text-nowrap">
                <span class="ms3_total_count">{$total.cart_count}</span> {'ms3_frontend_count_unit' | lexicon}
            </th>
            <th class="total_weight text-nowrap">
                <span class="ms3_total_weight">{$total.cart_weight}</span> {'ms3_frontend_weight_unit' | lexicon}
            </th>
            <th class="total_cost text-nowrap">
                <span class="ms3_total_cost">{$total.cart_cost}</span> {'ms3_frontend_currency' | lexicon}
            </th>
        </tr>
    </table>
</div>

<h4>
    {'ms3_frontend_order_cost' | lexicon}:
    {if $total.delivery_cost}
        {$total.cart_cost} {'ms3_frontend_currency' | lexicon} + {$total.delivery_cost}
        {'ms3_frontend_currency' | lexicon} =
    {/if}
    <strong>{$total.cost}</strong> {'ms3_frontend_currency' | lexicon}
</h4>

{if $payment_link?}
    <p>{'ms3_payment_link' | lexicon : ['link' => $payment_link]}</p>
{/if}
