    <div class="list-group-item"
         data-confirm-set-default="{'ms3_customer_address_set_default_confirm' | lexicon}"
         data-confirm-delete="{'ms3_customer_address_delete_confirm' | lexicon}"
         data-error-unknown="{'ms3_err_unknown' | lexicon}">
        <div class="d-flex w-100 justify-content-between align-items-start">
            <div class="flex-grow-1">
                <h6 class="mb-1">
                    {if $name}
                        {$name}
                    {else}
                        {$display_name}
                    {/if}
                    {if $is_default}
                    <span class="badge bg-success ms-2">{'ms3_customer_address_default' | lexicon}</span>
                    {/if}
                </h6>
                <p class="mb-1 text-muted small">
                    {if $index}{$index}, {/if}
                    {$city}{if $region}, {$region}{/if}{if $country}, {$country}{/if}
                </p>
                <p class="mb-1 small">
                    {$street}{if $building}, д. {$building}{/if}{if $entrance}, п. {$entrance}{/if}{if $floor}, эт. {$floor}{/if}{if $room}, кв. {$room}{/if}
                </p>
                {if $metro}
                <p class="mb-1 small text-muted">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" class="me-1">
                        <circle cx="7" cy="7" r="5"/>
                        <path d="M3 9 L7 4 L11 9"/>
                    </svg>
                    м. {$metro}
                </p>
                {/if}
                {if $text_address}
                <p class="mb-1 small text-muted fst-italic">
                    {$text_address}
                </p>
                {/if}
            </div>
            <div class="btn-group btn-group-sm ms-3" role="group">
                {if !$is_default}
                <button type="button"
                        class="btn btn-outline-secondary set-default-address"
                        data-address-id="{$id}"
                        title="{'ms3_customer_address_set_default' | lexicon}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M8 2 L9.5 6 L14 6.5 L10.5 9.5 L11.5 14 L8 11.5 L4.5 14 L5.5 9.5 L2 6.5 L6.5 6 Z"/>
                    </svg>
                </button>
                {/if}
                <a href="{$page_url}?mode=edit&id={$id}" class="btn btn-outline-primary" title="{'ms3_customer_address_edit' | lexicon}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11 2 L14 5 L5 14 L2 14 L2 11 Z"/>
                        <path d="M9 4 L12 7"/>
                    </svg>
                </a>
                <button type="button"
                        class="btn btn-outline-danger delete-address"
                        data-address-id="{$id}"
                        title="{'ms3_customer_address_delete' | lexicon}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 4 L13 4 M6 4 L6 2 L10 2 L10 4 M4 4 L4 14 L12 14 L12 4"/>
                        <path d="M6 7 L6 11 M10 7 L10 11"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
