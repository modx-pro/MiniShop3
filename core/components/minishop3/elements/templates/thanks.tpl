{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container my-5">
        <main>
            {* Success Header *}
            <div class="text-center mb-5">
                <div class="mb-4">
                    <svg class="text-success thanks-checkmark" width="80" height="80" fill="currentColor">
                        <use xlink:href="#icon-check"/>
                    </svg>
                </div>
                <h1 class="display-5 fw-bold text-success mb-3">Спасибо за заказ!</h1>
                <p class="lead text-muted">Ваш заказ успешно оформлен и принят в обработку</p>
            </div>

            {* Order Details *}
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    {'!msGetOrder'|snippet:[
                        'tpl' => 'tpl.msGetOrder',
                    ]}
                </div>
            </div>

            {* Additional Actions *}
            <div class="row justify-content-center mt-5">
                <div class="col-lg-10">
                    <div class="card border-0 bg-light thanks-card">
                        <div class="card-body text-center py-4">
                            <h5 class="card-title mb-3">Что дальше?</h5>
                            <p class="card-text text-muted mb-4">
                                Мы отправили подтверждение заказа на вашу электронную почту.<br>
                                Наш менеджер свяжется с вами в ближайшее время для уточнения деталей.
                            </p>
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="[[~[[++site_start]]]]" class="btn btn-outline-primary">
                                    <svg class="me-2" width="16" height="16" fill="currentColor">
                                        <use xlink:href="#icon-globe"/>
                                    </svg>
                                    На главную
                                </a>
                                {* ms3.page_id.catalog does not exist; use the package setting with a site_start fallback (#817) *}
                                {set $catalog_page_id = 'ms3_catalog_page_id' | option}
                                {set $site_start_id = 'site_start' | option}
                                {set $continue_page_id = $catalog_page_id > 0 ? $catalog_page_id : $site_start_id}
                                <a href="{$continue_page_id | url}" class="btn btn-primary">
                                    <svg class="me-2" width="16" height="16" fill="currentColor">
                                        <use xlink:href="#icon-cart"/>
                                    </svg>
                                    Продолжить покупки
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
{/block}
