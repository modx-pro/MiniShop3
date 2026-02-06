{extends 'tpl.msEmail'}

{block 'title'}
    {'ms3_email_subject_sent_user' | lexicon : $order}
{/block}
