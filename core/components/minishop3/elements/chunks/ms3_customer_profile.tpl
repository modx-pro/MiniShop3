{*
 * Страница профиля клиента
 *
 * Расширяет базовый layout tpl.msCustomer.base
 * Отображает форму редактирования личных данных клиента
 *}

{extends 'tpl.msCustomer.base'}

{block 'content'}
<div class="ms3-customer-profile">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">{'ms3_customer_profile_title' | lexicon}</h5>
        </div>
        <div class="card-body">
            {if $success?}
            <div class="alert alert-success" role="alert">
                {'ms3_customer_profile_updated' | lexicon}
            </div>
            {/if}

            <form class="ms3_form ms3-customer-profile-form" method="post" action="" data-ms3-form="customer-profile">
                <input type="hidden" name="ms3_action" value="customer/update-profile">

                <div class="row">
                    {* Имя *}
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">
                            {'ms3_customer_first_name' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control {if $errors.first_name?}is-invalid{/if}"
                               id="first_name"
                               name="first_name"
                               value="{$customer.first_name}"
                               required>
                        {if $errors.first_name?}
                        <div class="invalid-feedback">{$errors.first_name}</div>
                        {/if}
                    </div>

                    {* Фамилия *}
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">
                            {'ms3_customer_last_name' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control {if $errors.last_name?}is-invalid{/if}"
                               id="last_name"
                               name="last_name"
                               value="{$customer.last_name}"
                               required>
                        {if $errors.last_name?}
                        <div class="invalid-feedback">{$errors.last_name}</div>
                        {/if}
                    </div>
                </div>

                {* Email с проверкой *}
                <div class="mb-3">
                    <label for="email" class="form-label">
                        {'ms3_customer_email' | lexicon} <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="email"
                               class="form-control {if $errors.email?}is-invalid{/if}"
                               id="email"
                               name="email"
                               value="{$customer.email}"
                               required>
                        {if $email_verified}
                        <span class="input-group-text bg-success text-white"
                              title="{'ms3_customer_email_verified' | lexicon}">
                            <svg width="16" height="16" fill="currentColor">
                                <use xlink:href="#icon-check-circle"/>
                            </svg>
                            {'ms3_customer_email_verified' | lexicon}
                        </span>
                        {else}
                        <button class="btn btn-outline-warning"
                                type="button"
                                id="resend-verification-email"
                                data-ms3-resend-verification
                                data-customer-id="{$customer.id}">
                            {'ms3_customer_email_send_verification' | lexicon}
                        </button>
                        {/if}
                    </div>
                    {if $errors.email?}
                    <div class="invalid-feedback d-block">{$errors.email}</div>
                    {/if}
                    {if !$email_verified}
                    <div class="form-text text-warning">
                        {'ms3_customer_email_not_verified' | lexicon}
                    </div>
                    {/if}
                </div>

                {* Телефон *}
                <div class="mb-3">
                    <label for="phone" class="form-label">
                        {'ms3_customer_phone' | lexicon} <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="tel"
                               class="form-control {if $errors.phone?}is-invalid{/if}"
                               id="phone"
                               name="phone"
                               value="{$customer.phone}"
                               required>
                        {if $phone_verified}
                        <span class="input-group-text bg-success text-white"
                              title="{'ms3_customer_phone_verified' | lexicon}">
                            <svg width="16" height="16" fill="currentColor">
                                <use xlink:href="#icon-check-circle"/>
                            </svg>
                            {'ms3_customer_phone_verified' | lexicon}
                        </span>
                        {else}
                        <span class="input-group-text bg-light text-muted">
                            {'ms3_customer_phone_not_verified' | lexicon}
                        </span>
                        {/if}
                    </div>
                    {if $errors.phone?}
                    <div class="invalid-feedback d-block">{$errors.phone}</div>
                    {/if}
                    <div class="form-text">
                        {'ms3_customer_phone_verification_soon' | lexicon}
                    </div>
                </div>

                {* Кнопка сохранения *}
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="submit" class="btn btn-primary ms3_link">
                        {'ms3_customer_profile_save' | lexicon}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
{/block}
