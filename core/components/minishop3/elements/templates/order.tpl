{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container">
        <main class="mt-4">
            <h1 class="mb-4">{$_modx->resource.pagetitle}</h1>
            {'!msOrder'|snippet:[
                'tpl' => 'tpl.msOrder',
            ]}
        </main>
    </div>
{/block}
