{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_paid_user' | lexicon : $order}
{/block}
