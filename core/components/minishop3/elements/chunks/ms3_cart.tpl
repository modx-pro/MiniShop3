<div id="msCart">
    {if $products | length == 0}
        <div class="alert alert-warning">
            {'ms3_cart_is_empty' | lexicon}
        </div>
    {else}

        <div class="table-responsive">
            <table class="table table-striped">
                <tr class="ms-header">
                    <th class="ms-title">{'ms3_cart_title' | lexicon}</th>
                    <th class="ms-count">{'ms3_cart_count' | lexicon}</th>
                    <th class="ms-weight">{'ms3_cart_weight' | lexicon}</th>
                    <th class="ms-price">{'ms3_cart_price' | lexicon}</th>
                    <th class="ms-cost">{'ms3_cart_cost' | lexicon}</th>
                    <th class="ms-remove"></th>
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
                    <tr id="{$product.product_key}">
                        <td class="ms-title">
                            <div class="d-flex">
                                <div class="ms-image mw-100 pr-3">
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
                        <td class="ms-count">
                            <form method="post" class="ms3_form" role="form">
                                <input type="hidden" name="product_key" value="{$product.product_key}"/>
                                <input type="hidden" name="ms3_action" value="cart/change">
                                <div class="form-group">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="count" value="{$product.count}" class="form-control"/>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{'ms3_frontend_count_unit' | lexicon}</span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm" type="submit">&#8635;</button>
                                </div>
                            </form>
                        </td>
                        <td class="ms-weight">
                            <span class="text-nowrap">{$product.weight} {'ms3_frontend_weight_unit' | lexicon}</span>
                        </td>
                        <td class="ms-price">
                            <span class="mr-2 text-nowrap">{$product.price} {'ms3_frontend_currency' | lexicon}</span>
                            {if $product.old_price?}
                                <span class="old_price text-nowrap">{$product.old_price} {'ms3_frontend_currency' | lexicon}</span>
                            {/if}
                        </td>
                        <td class="ms-cost">
                            <span class="mr-2 text-nowrap"><span class="ms3_cost">{$product.cost}</span> {'ms3_frontend_currency' | lexicon}</span>
                        </td>
                        <td class="ms-remove">
                            <form method="post" class="ms3_form text-md-right">
                                <input type="hidden" name="product_key" value="{$product.product_key}">
                                <input type="hidden" name="ms3_action" value="cart/remove">
                                <button class="btn btn-sm btn-danger" type="submit">&times;</button>
                            </form>
                        </td>
                    </tr>
                {/foreach}

                <tr class="ms-footer">
                    <th class="total">{'ms3_cart_total' | lexicon}:</th>
                    <th class="total_count">
                        <span class="ms3_total_count">{$total.count}</span>
                        {'ms3_frontend_count_unit' | lexicon}
                    </th>
                    <th class="total_weight text-nowrap" colspan="2">
                        <span class="ms3_total_weight">{$total.weight}</span>
                        {'ms3_frontend_weight_unit' | lexicon}
                    </th>
                    <th class="total_cost text-nowrap" colspan="2">
                        <span class="ms3_total_cost">{$total.cost}</span>
                        {'ms3_frontend_currency' | lexicon}
                    </th>
                </tr>
            </table>
        </div>

        <form method="post" class="ms3_form">
            <input type="hidden" name="ms3_action" value="cart/clean">
            <button type="submit"  class="btn btn-danger">
                {'ms3_cart_clean' | lexicon}
            </button>
        </form>
    {/if}
</div>