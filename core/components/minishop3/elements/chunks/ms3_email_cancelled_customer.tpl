{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_cancelled_customer' | lexicon : $order}
{/block}
