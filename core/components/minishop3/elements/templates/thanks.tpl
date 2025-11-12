{extends 'file:templates/base.tpl'}
{block 'pagecontent'}
    <div class="container my-5">
        <main>
            {* Success Header *}
            <div class="text-center mb-5">
                <div class="mb-4">
                    <svg class="text-success" width="80" height="80" fill="currentColor">
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
                    <div class="card border-0 bg-light">
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
                                <a href="[[~[[++ms3.page_id.catalog:default=`0`]]]]" class="btn btn-primary">
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

    {* Custom Styles *}
    <style>
        /* Success checkmark animation */
        @keyframes checkmark-appear {
            0% {
                transform: scale(0) rotate(0deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        svg use[xlink\:href="#icon-check"] {
            animation: checkmark-appear 0.5s ease-out;
        }

        /* Card hover effect */
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
{/block}
