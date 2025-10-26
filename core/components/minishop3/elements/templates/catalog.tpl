{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container py-4">
        <main>
            {* Заголовок категории *}
            <div class="page-header mb-4">
                <h1>{$_modx->resource.pagetitle}</h1>
                {if $_modx->resource.introtext}
                    <p class="lead text-muted">{$_modx->resource.introtext}</p>
                {/if}
            </div>

            {* Сетка товаров Bootstrap Grid *}
            <div class="row">
                {* Вызов сниппета msProducts с параметрами *}
                {'!msProducts'|snippet:[
                    'tpl' => 'tpl.msProducts.row',
                    'includeThumbs' => 'small,medium',
                    'includeVendorFields' => 'name,logo',
                    'formatPrices' => 1,
                    'withCurrency' => 1,
                    'limit' => 12,
                    'showLog' => 0,
                    'sortby' => 'menuindex',
                    'sortdir' => 'ASC',
                    'includeTVs' => '',
                    'showZeroPrice' => 0,
                ]}
            </div>

            {* Пагинация (если нужна) *}
            {*
            <nav aria-label="Пагинация" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled"><a class="page-link" href="#">Предыдущая</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">Следующая</a></li>
                </ul>
            </nav>
            *}
        </main>
    </div>
{/block}
