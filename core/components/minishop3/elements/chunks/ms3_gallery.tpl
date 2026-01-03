{* Splide Gallery + GLightbox for MiniShop3 *}
{* Подключаем через CDN (можно заменить на локальные файлы) *}
{if $files?}
    {* Splide Slider *}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js"></script>

    {* GLightbox *}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css">
    <script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>

    <div class="ms3-gallery">
        {* Основной слайдер *}
        <div class="splide ms3-gallery-main" id="ms3-gallery-main">
            <div class="splide__track">
                <ul class="splide__list">
                    {foreach $files as $file}
                        <li class="splide__slide">
                            <a href="{$file['url']}"
                               class="glightbox"
                               data-gallery="ms3-product-gallery"
                               data-title="{$file['name']}"
                               data-description="{$file['description']}">
                                <img src="{$file['medium'] ?: $file['url']}"
                                     alt="{$file['description'] ?: $file['name']}"
                                     loading="{$file@first ? 'eager' : 'lazy'}">
                            </a>
                        </li>
                    {/foreach}
                </ul>
            </div>
        </div>

        {* Слайдер миниатюр (показываем если больше 1 изображения) *}
        {if ($files | length) > 1}
            <div class="splide ms3-gallery-thumbs" id="ms3-gallery-thumbs">
                <div class="splide__track">
                    <ul class="splide__list">
                        {foreach $files as $file}
                            <li class="splide__slide">
                                <img src="{$file['small'] ?: $file['medium'] ?: $file['url']}"
                                     alt="{$file['description'] ?: $file['name']}">
                            </li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        {/if}
    </div>

    <style>
        .ms3-gallery {
            max-width: 100%;
        }

        .ms3-gallery-main {
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .ms3-gallery-main .splide__slide {
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1 / 1;
        }

        .ms3-gallery-main .splide__slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .ms3-gallery-main .splide__slide a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .ms3-gallery-thumbs .splide__slide {
            opacity: 0.6;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .ms3-gallery-thumbs .splide__slide:hover {
            opacity: 0.8;
        }

        .ms3-gallery-thumbs .splide__slide.is-active {
            opacity: 1;
            border-color: var(--bs-primary, #0d6efd);
        }

        .ms3-gallery-thumbs .splide__slide img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        /* Стрелки навигации */
        .ms3-gallery-main .splide__arrow {
            background: rgba(255, 255, 255, 0.9);
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .ms3-gallery-main:hover .splide__arrow {
            opacity: 1;
        }

        .ms3-gallery-main .splide__arrow:hover {
            background: #fff;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var mainSlider = document.getElementById('ms3-gallery-main');
            var thumbsSlider = document.getElementById('ms3-gallery-thumbs');

            if (!mainSlider) return;

            // Инициализация Splide
            var main = new Splide('#ms3-gallery-main', {
                type: 'fade',
                rewind: true,
                pagination: false,
                arrows: true,
                cover: false,
            });

            if (thumbsSlider) {
                var thumbs = new Splide('#ms3-gallery-thumbs', {
                    fixedWidth: 100,
                    fixedHeight: 80,
                    gap: 10,
                    rewind: true,
                    pagination: false,
                    arrows: false,
                    isNavigation: true,
                    focus: 'center',
                    breakpoints: {
                        576: {
                            fixedWidth: 70,
                            fixedHeight: 56,
                            gap: 6,
                        },
                    },
                });

                main.sync(thumbs);
                main.mount();
                thumbs.mount();
            } else {
                main.mount();
            }

            // Инициализация GLightbox
            GLightbox({
                selector: '.glightbox',
                touchNavigation: true,
                loop: true,
                closeButton: true,
                zoomable: true,
                draggable: true,
            });
        });
    </script>
{else}
    <div class="ms3-gallery ms3-gallery-empty">
        <div class="ms3-gallery-placeholder">
            <img src="{'assets_url' | option}components/minishop3/img/web/ms3_medium.png"
                 srcset="{'assets_url' | option}components/minishop3/img/web/ms3_medium@2x.png 2x"
                 alt="" title=""/>
        </div>
    </div>

    <style>
        .ms3-gallery-empty {
            background: #f8f9fa;
            border-radius: 8px;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ms3-gallery-placeholder img {
            max-width: 60%;
            opacity: 0.5;
        }
    </style>
{/if}
