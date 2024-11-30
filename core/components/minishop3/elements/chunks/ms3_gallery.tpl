<div class="msGallery">
    {if $files?}
        <div class="any_gallery_library">
            {foreach $files as $file}
                <a href="{$file['url']}" target="_blank">
                    <img src="{$file['small']}" alt="{$file['description']}" title="{$file['name']}">
                </a>
            {/foreach}
        </div>
    {else}
        <img src="{('assets_url' | option) ~ 'components/minishop3/img/web/ms3_medium.png'}"
            srcset="{('assets_url' | option) ~ 'components/minishop3/img/web/ms3_medium@2x.png'} 2x"
            alt="" title=""/>
    {/if}
</div>
