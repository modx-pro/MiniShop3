<div class="ms3-customer-auth-container">
    <div class="ms3-auth-wrapper">
        <div class="ms3-auth-header">
            <h3>{'ms3_customer_account' | lexicon}</h3>
        </div>

        {* Табы переключения между формами *}
        <ul class="nav nav-tabs ms3-auth-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-form"
                        type="button" role="tab" aria-controls="login-form" aria-selected="true">
                    {'ms3_customer_login' | lexicon}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-form"
                        type="button" role="tab" aria-controls="register-form" aria-selected="false">
                    {'ms3_customer_register' | lexicon}
                </button>
            </li>
        </ul>

        <div class="tab-content ms3-auth-content">
            {* ============================================ *}
            {* ФОРМА ВХОДА *}
            {* ============================================ *}
            <div class="tab-pane fade show active" id="login-form" role="tabpanel" aria-labelledby="login-tab">
                <form id="ms3-login-form" class="ms3-auth-form">

                    {* Сообщения об ошибках/успехе *}
                    <div id="login-messages" class="ms3-messages"></div>

                    <div class="mb-3">
                        <label for="login-email" class="form-label">
                            {'ms3_customer_email' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="login-email" name="email"
                               autocomplete="username"
                               placeholder="{'ms3_customer_email_placeholder' | lexicon}" required>
                    </div>

                    <div class="mb-3">
                        <label for="login-password" class="form-label">
                            {'ms3_customer_password' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="login-password" name="password"
                               autocomplete="current-password"
                               placeholder="{'ms3_customer_password_placeholder' | lexicon}" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="login-submit-btn">
                        {'ms3_customer_login' | lexicon}
                    </button>

                    <div class="mt-3 text-center">
                        <a href="#" class="text-muted small" id="forgot-password-link">
                            {'ms3_customer_forgot_password' | lexicon}
                        </a>
                    </div>
                </form>
            </div>

            {* ============================================ *}
            {* ФОРМА РЕГИСТРАЦИИ *}
            {* ============================================ *}
            <div class="tab-pane fade" id="register-form" role="tabpanel" aria-labelledby="register-tab">
                <form id="ms3-register-form" class="ms3-auth-form">

                    {* Сообщения об ошибках/успехе *}
                    <div id="register-messages" class="ms3-messages"></div>

                    <div class="mb-3">
                        <label for="register-email" class="form-label">
                            {'ms3_customer_email' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="register-email" name="email"
                               autocomplete="email"
                               placeholder="{'ms3_customer_email_placeholder' | lexicon}" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="register-first-name" class="form-label">
                                {'ms3_customer_first_name' | lexicon}
                            </label>
                            <input type="text" class="form-control" id="register-first-name" name="first_name"
                                   placeholder="{'ms3_customer_first_name_placeholder' | lexicon}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="register-last-name" class="form-label">
                                {'ms3_customer_last_name' | lexicon}
                            </label>
                            <input type="text" class="form-control" id="register-last-name" name="last_name"
                                   placeholder="{'ms3_customer_last_name_placeholder' | lexicon}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="register-phone" class="form-label">
                            {'ms3_customer_phone' | lexicon}
                        </label>
                        <input type="tel" class="form-control" id="register-phone" name="phone"
                               placeholder="{'ms3_customer_phone_placeholder' | lexicon}">
                    </div>

                    <div class="mb-3">
                        <label for="register-password" class="form-label">
                            {'ms3_customer_password' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="register-password" name="password"
                               autocomplete="new-password"
                               placeholder="{'ms3_customer_password_placeholder' | lexicon}" required>
                        <small class="form-text text-muted">
                            {'ms3_customer_password_hint' | lexicon}
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="register-password-confirm" class="form-label">
                            {'ms3_customer_password_confirm' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="register-password-confirm" name="password_confirm"
                               autocomplete="new-password"
                               placeholder="{'ms3_customer_password_confirm_placeholder' | lexicon}" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="register-privacy" name="privacy_accepted" required>
                        <label class="form-check-label" for="register-privacy">
                            {'ms3_customer_privacy_accept' | lexicon} <span class="text-danger">*</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="register-submit-btn">
                        {'ms3_customer_register' | lexicon}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.ms3Lexicon = window.ms3Lexicon || {};
window.ms3Lexicon.ms3_customer_err_login_required = '{'ms3_customer_err_login_required' | lexicon}';
window.ms3Lexicon.ms3_customer_login_success = '{'ms3_customer_login_success' | lexicon}';
window.ms3Lexicon.ms3_customer_err_register_required = '{'ms3_customer_err_register_required' | lexicon}';
window.ms3Lexicon.ms3_customer_err_password_mismatch = '{'ms3_customer_err_password_mismatch' | lexicon}';
window.ms3Lexicon.ms3_customer_err_privacy_required = '{'ms3_customer_err_privacy_required' | lexicon}';
window.ms3Lexicon.ms3_customer_register_success = '{'ms3_customer_register_success' | lexicon}';
window.ms3Lexicon.ms3_err_unknown = '{'ms3_err_unknown' | lexicon}';
window.ms3Lexicon.ms3_customer_password_recovery_not_available = '{'ms3_customer_password_recovery_not_available' | lexicon}';
</script>
