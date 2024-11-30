{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container">
        <main>
            <h1>{$_modx->resource.pagetitle}</h1>

            {'!msProducts'|snippet:[
                'tpl' => 'tpl.msProducts.row',
            ]}
        </main>
    </div>
{/block}
