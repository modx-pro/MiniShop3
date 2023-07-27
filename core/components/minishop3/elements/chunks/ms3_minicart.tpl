<div class="msMiniCart{$total_count > 0 ? ' full' : ''}">
    <div class="empty">
        <h5>{'ms3_minicart' | lexicon}</h5>
        {'ms3_minicart_is_empty' | lexicon}
    </div>
    <div class="not_empty">
        <h5>{'ms3_minicart' | lexicon}</h5>
        {'ms3_minicart_goods' | lexicon} <strong class="ms3_total_count">{$total_count}</strong> {'ms3_frontend_count_unit' | lexicon},
        {'ms3_minicart_cost' | lexicon} <strong class="ms3_total_cost">{$total_cost}</strong> {'ms3_frontend_currency' | lexicon}
    </div>
</div>
