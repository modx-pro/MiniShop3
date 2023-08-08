{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_new_customer' | lexicon : $order}
{/block}

{block 'products'}
    {parent}
    {if $payment_link?}
        <p style="margin-left:20px;{$style.p}">
            {'ms3_payment_link' | lexicon : ['link' => $payment_link]}
        </p>
    {/if}
{/block}
