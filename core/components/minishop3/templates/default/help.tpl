<div class="x-panel-body">
    <div id="ms3-help-page" class="container">
        <h2>{$_lang.ms3_help}</h2>

        <div id="ms3-help-text">
            {if $logo}
                <img src="{$logo}">
            {/if}

            <p>
                {$_lang.ms3_help_text}
            </p>

            <ul>
                <li>
                    <i class="icon icon-shopping-cart"></i>

                    <a href="{$_lang.ms3_orders_href}">
                        {$_lang.ms3_orders_text}
                    </a>
                </li>

                <li>
                    <i class="icon icon-wrench"></i>

                    <a href="{$_lang.ms3_settings_href}">
                        {$_lang.ms3_settings_text}
                    </a>
                </li>

                <li>
                    <i class="icon icon-gear"></i>

                    <a href="{$_lang.ms3_sys_settings_href}">
                        {$_lang.ms3_sys_settings_text}
                    </a>
                </li>

                <li>
                    <i class="icon icon-language"></i>

                    <a href="{$_lang.ms3_lexicons_href}">
                        {$_lang.ms3_lexicons_text}
                    </a>
                </li>
            </ul>

            <hr>

            <p>
                {$_lang.ms3_help_text_support}
            </p>
        </div>

        <div id="ms3-help-links">
            <ul>
                <li>
                    <a href="{$_lang.ms3_demo_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-television icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_demo_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_demo_text}
                        </span>
                    </a>
                </li>

                <li>
                    <a href="{$_lang.ms3_docs_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-book icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_docs_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_docs_text}
                        </span>
                    </a>
                </li>

                <li>
                    <a href="{$_lang.ms3_components_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-archive icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_components_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_components_text}
                        </span>
                    </a>
                </li>

                <li>
                    <a href="{$_lang.ms3_forum_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-comments icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_forum_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_forum_text}
                        </span>
                    </a>
                </li>

                <li>
                    <a href="{$_lang.ms3_github_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-bug icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_github_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_github_text}
                        </span>
                    </a>
                </li>

                <li>
                    <a href="{$_lang.ms3_localization_href}" target="_blank">
                        <span class="icon">
                            <i class="icon icon-language icon-3x"></i>
                        </span>

                        <span class="title">
                            {$_lang.ms3_localization_title}
                        </span>

                        <span class="text">
                            {$_lang.ms3_localization_text}
                        </span>
                    </a>
                </li>
            </ul>
        </div>

        {if $changelog}
            <div id="ms3-help-text">
                <textarea name="changelog" class="x-form-textarea" autocomplete="off" readonly="">{$changelog}</textarea>
            </div>
        {/if}
    </div>
</div>
