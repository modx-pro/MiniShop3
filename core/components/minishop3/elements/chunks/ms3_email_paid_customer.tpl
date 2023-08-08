{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_paid_customer' | lexicon : $order}
{/block}
