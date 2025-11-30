{*
 * Страница истории заказов клиента
 *
 * Расширяет базовый layout tpl.msCustomer.base
 * Отображает список заказов клиента с фильтрацией и пагинацией
 *}

{extends 'tpl.msCustomer.base'}

{block 'content'}
<div class="ms3-customer-orders">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">{'ms3_customer_orders_title' | lexicon}</h5>
        </div>
        <div class="card-body">
            {* Фильтр по статусу *}
            {if $statuses}
            <form class="mb-4" method="get" action="">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label for="status-filter" class="form-label">
                            {'ms3_customer_orders_filter_by_status' | lexicon}
                        </label>
                        <select class="form-select" id="status-filter" name="status" onchange="this.form.submit()">
                            <option value="">{'ms3_customer_orders_all_statuses' | lexicon}</option>
                            {foreach $statuses as $status}
                            <option value="{$status.id}" {if $status.selected}selected{/if}>
                                {$status.name}
                            </option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-secondary" onclick="location.href='?'">
                            {'ms3_customer_orders_reset_filter' | lexicon}
                        </button>
                    </div>
                </div>
            </form>
            {/if}

            {* Список заказов *}
            {if $orders_count > 0}
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{'ms3_customer_order_num' | lexicon}</th>
                            <th>{'ms3_customer_order_date' | lexicon}</th>
                            <th>{'ms3_customer_order_status' | lexicon}</th>
                            <th class="text-end">{'ms3_customer_order_total' | lexicon}</th>
                            <th style="width: 120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {$orders}
                    </tbody>
                </table>
            </div>

            {* Пагинация *}
            {if $pagination.total_pages > 1}
            <nav aria-label="{'ms3_customer_orders_pagination' | lexicon}">
                <ul class="pagination justify-content-center">
                    {if $pagination.has_prev}
                    <li class="page-item">
                        <a class="page-link" href="?offset={$pagination.prev_offset}">
                            {'ms3_customer_orders_prev' | lexicon}
                        </a>
                    </li>
                    {/if}

                    {foreach $pagination.pages as $page}
                    <li class="page-item {if $page.active}active{/if}">
                        <a class="page-link" href="?offset={$page.offset}">
                            {$page.num}
                        </a>
                    </li>
                    {/foreach}

                    {if $pagination.has_next}
                    <li class="page-item">
                        <a class="page-link" href="?offset={$pagination.next_offset}">
                            {'ms3_customer_orders_next' | lexicon}
                        </a>
                    </li>
                    {/if}
                </ul>
            </nav>
            {/if}

            <div class="text-muted small mt-3">
                Всего заказов: {$total}
            </div>
            {else}
            <div class="alert alert-info" role="alert">
                {'ms3_customer_orders_empty' | lexicon}
            </div>
            {/if}
        </div>
    </div>
</div>
{/block}
