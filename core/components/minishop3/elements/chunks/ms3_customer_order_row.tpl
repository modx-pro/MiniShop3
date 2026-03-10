<tr>
    <td>
        <a href="{'ms3_customer_orders_page_id' | option | url}?order={$uuid}" class="fw-semibold text-decoration-none">
            №{$num}
        </a>
    </td>
    <td class="text-nowrap">
        {$createdon_formatted}
    </td>
    <td>
        <span class="badge"{if $status_color} style="background-color: #{$status_color};"{/if}>
            {$status_name}
        </span>
    </td>
    <td class="text-end text-nowrap fw-bold">
        {$cost_formatted} {'ms3_frontend_currency' | lexicon}
    </td>
    <td class="text-end">
        <div class="d-flex gap-2 justify-content-end" role="group">
            <a href="{'ms3_customer_orders_page_id' | option | url}?order={$uuid}"
               class="btn btn-sm btn-outline-primary"
               title="{'ms3_customer_order_view' | lexicon}"
               aria-label="{'ms3_customer_order_view' | lexicon}">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="8" cy="8" r="3"/>
                    <path d="M1 8 C3 4 6 2 8 2 C10 2 13 4 15 8 C13 12 10 14 8 14 C6 14 3 12 1 8 Z"/>
                </svg>
            </a>
            {if $can_cancel}
            <button type="button"
                    class="btn btn-sm btn-outline-danger ms3-order-cancel"
                    data-order-id="{$id}"
                    data-confirm="{'ms3_customer_order_cancel_confirm' | lexicon}"
                    title="{'ms3_customer_order_cancel' | lexicon}"
                    aria-label="{'ms3_customer_order_cancel' | lexicon}">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="8" cy="8" r="6"/>
                    <path d="M5.5 5.5 L10.5 10.5 M10.5 5.5 L5.5 10.5"/>
                </svg>
            </button>
            {/if}
        </div>
    </td>
</tr>
