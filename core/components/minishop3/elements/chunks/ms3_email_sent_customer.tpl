{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_sent_customer' | lexicon : $order}
{/block}
