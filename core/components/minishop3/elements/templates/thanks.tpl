{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container">
        <main class="mt-4">
            <h1 class="mb-4">{$_modx->resource.pagetitle}</h1>
            <div class="msCart">
                {'!msGetOrder'|snippet:[
                    'tpl' => 'tpl.msGetOrder',
                ]}
            </div>
        </main>
    </div>
{/block}
