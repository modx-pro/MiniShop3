<div class="ms3_product mb-5 mb-md-4" itemtype="http://schema.org/Product" itemscope>
    <meta itemprop="description" content="{$description = $description ?: $pagetitle}">
    <meta itemprop="name" content="{$pagetitle}">

    <form method="post" class="ms3_form d-flex flex-column flex-md-row align-items-center no-gutters">
        <input type="hidden" name="id" value="{$id}">
        <input type="hidden" name="count" value="1">
        <input type="hidden" name="options" value="[]">
        <input type="hidden" name="ms3_action" value="cart/add">
        <div class="col-md-2 text-center text-md-left">
            <a href="/{$id | url}">
                {if $thumb?}
                    <img src="{$thumb}" class="mw-100" alt="{$pagetitle}" title="{$pagetitle}" itemprop="image"/>
                {else}
                    <img src="{'assets_url' | option}components/minishop3/img/web/ms3_small.png"
                        srcset="{'assets_url' | option}components/minishop3/img/web/ms3_small@2x.png 2x"
                        class="mw-100" alt="{$pagetitle}" title="{$pagetitle}"/>
                {/if}
            </a>
        </div>
        <div class="col-md-10 d-flex flex-column flex-md-row align-items-center no-gutters" itemtype="http://schema.org/AggregateOffer" itemprop="offers" itemscope>
            <meta itemprop="category" content="{$parent | resource: "pagetitle"}">
            <meta itemprop="name" content="{$pagetitle}">
            <meta itemprop="offerCount" content="1">
            <meta itemprop="price" content="{$price}">
            <meta itemprop="lowPrice" content="{$price}">
            <meta itemprop="priceCurrency" content="RUR">

            <div class="col-12 col-md-8 mt-2 mt-md-0 flex-grow-1">
                <div class="d-flex flex-column justify-content-around justify-content-md-start">
                    <a href="/{$id | url}" class="font-weight-bold">{$pagetitle}</a>
                    <span class="price ml-md-3">{$price} {'ms3_frontend_currency' | lexicon}</span>
                    {if $old_price > 0?}
                        <span class="old_price ml-md-3 text-decoration-line-through text-danger">{$old_price} {'ms3_frontend_currency' | lexicon}</span>
                    {/if}
                </div>
                <div class="flags mt-2 d-flex justify-content-around justify-content-md-start">
                    {if $new?}
                        <span class="badge badge-secondary badge-pill mr-md-1">{'ms3_frontend_new' | lexicon}</span>
                    {/if}
                    {if $popular?}
                        <span class="badge badge-secondary badge-pill mr-md-1">{'ms3_frontend_popular' | lexicon}</span>
                    {/if}
                    {if $favorite?}
                        <span class="badge badge-secondary badge-pill mr-md-1">{'ms3_frontend_favorite' | lexicon}</span>
                    {/if}
                </div>

                <div class="d-flex flex-column gap-2">
                    {if $color}
                        <select name="options[color]" class="form-select" style="width:200px;">
                            <option value="">Выбери цвет</option>
                            {foreach $color as $option}
                                <option value="{$option}">{$option}</option>
                            {/foreach}
                        </select>
                    {/if}

                    {if $size}
                        <select name="options[size]" class="form-select" style="width:200px;">
                            <option value="">Выбери размер</option>
                            {foreach $size as $option}
                                <option value="{$option}">{$option}</option>
                            {/foreach}
                        </select>
                    {/if}
                </div>

                {if $options?}
                    {if $product.size && $product.options.size}
                        <form class="ms3_form  mt-2">
                            <input type="hidden" name="product_key" value="{$product.product_key}">
                            <input type="hidden" name="ms3_action" value="cart/changeOption"/>
                            <select name="options[size]" class="form-select mt-2 ms3_cart_options" style="width:200px;">
                                <option value="">Выбери размер</option>
                                {foreach $product.size as $option}
                                    <option value="{$option}"  {if $product.options.size == $option}selected{/if}>{$option}</option>
                                {/foreach}
                            </select>
                        </form>
                    {/if}
                {/if}


                {if $introtext}
                    <div class="mt-2 text-center text-md-left">
                        <small>{$introtext | truncate : 200}</small>
                    </div>
                {/if}
            </div>
            <div class="col-12 col-md-4 mt-2 mt-md-0 text-center text-md-right">
                <button class="btn btn-primary" type="submit">
                    {'ms3_frontend_add_to_cart' | lexicon}
                </button>
            </div>
        </div>
    </form>
</div>
