{*
 * Страница управления адресами клиента
 *
 * Расширяет базовый layout tpl.msCustomer.base
 * Отображает список адресов доставки клиента
 *}

{extends 'tpl.msCustomer.base'}

{block 'content'}
<div class="ms3-customer-addresses">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{'ms3_customer_addresses_title' | lexicon}</h5>
            <a href="{$create_url}" class="btn btn-sm btn-light">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
                    <path d="M8 3 L8 13 M3 8 L13 8"/>
                </svg>
                {'ms3_customer_address_add' | lexicon}
            </a>
        </div>
        <div class="card-body">
            {if $success?}
            <div class="alert alert-success" role="alert">
                {$success}
            </div>
            {/if}

            {if $error?}
            <div class="alert alert-danger" role="alert">
                {$error}
            </div>
            {/if}

            {if $addresses_count > 0}
            <div class="list-group list-group-flush">
                {$addresses}
            </div>
            {else}
            <div class="alert alert-info" role="alert">
                {'ms3_customer_addresses_empty' | lexicon}
            </div>
            {/if}
        </div>
    </div>
</div>
{/block}
