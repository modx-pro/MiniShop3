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
    </td>
</tr>
