<form class="ms3_customer_form" method="post">
    <div class="row">
        <h4>{'ms3_frontend_customer' | lexicon}:</h4>
        {foreach ['first_name','last_name','email', 'phone'] as $field}
            <div class="col-12 col-md-6">
                <div class="form-group row input-parent">
                    <label class="col-12 col-form-label" for="{$field}">
                        {('ms3_frontend_' ~ $field) | lexicon} <span class="required-star">*</span>
                    </label>
                    <div class="col-md-12">
                        <input type="text" id="{$field}" placeholder="{('ms3_frontend_' ~ $field) | lexicon}"
                               name="{$field}" value="{$form[$field]}"
                               class="form-control{($field in list $errors) ? ' error' : ''}">
                    </div>
                </div>

            </div>
        {/foreach}
    </div>

    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-end mt-4">
        <button type="submit" name="ms3_action" value="customer/set" class="btn btn-lg btn-primary ml-md-2 ms3_link">
            {'ms3_frontend_save' | lexicon}
        </button>
    </div>
</form>
