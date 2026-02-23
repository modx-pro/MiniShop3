<tr>
    <td>
        <a href="?order_id={$id}" class="fw-semibold text-decoration-none">
            №{$num}
        </a>
    </td>
    <td class="text-nowrap">
        {$createdon_formatted}
    </td>
    <td>
        <span class="badge" style="background-color: {$status_color};">
            {$status_name}
        </span>
    </td>
    <td class="text-end text-nowrap fw-bold">
        {$cost_formatted} {'ms3_frontend_currency' | lexicon}
    </td>
    <td class="text-end">
        <a href="?order_id={$id}" class="btn btn-sm btn-outline-primary">
            {'ms3_customer_order_view' | lexicon}
        </a>
        {if $can_cancel}
        <button type="button" class="btn btn-sm btn-outline-danger ms3-order-cancel" data-order-id="{$id}" data-confirm="{'ms3_customer_order_cancel_confirm' | lexicon}">
            {'ms3_customer_order_cancel' | lexicon}
        </button>
        {/if}
    </td>
</tr>
