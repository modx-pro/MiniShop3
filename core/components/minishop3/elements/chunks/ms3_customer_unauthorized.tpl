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
                               placeholder="{'ms3_customer_email_placeholder' | lexicon}" required>
                    </div>

                    <div class="mb-3">
                        <label for="login-password" class="form-label">
                            {'ms3_customer_password' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="login-password" name="password"
                               placeholder="{'ms3_customer_password_placeholder' | lexicon}" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="login-remember" name="remember">
                        <label class="form-check-label" for="login-remember">
                            {'ms3_customer_remember_me' | lexicon}
                        </label>
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

<style>
.ms3-customer-auth-container {
    max-width: 500px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.ms3-auth-wrapper {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}

.ms3-auth-header {
    padding: 1.5rem 1.5rem 1rem;
    text-align: center;
    border-bottom: 1px solid #dee2e6;
}

.ms3-auth-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #212529;
}

.ms3-auth-tabs {
    border-bottom: 1px solid #dee2e6;
    padding: 0 1.5rem;
}

.ms3-auth-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
    padding: 1rem 1.5rem;
    cursor: pointer;
    background: none;
}

.ms3-auth-tabs .nav-link:hover {
    border-color: #dee2e6;
    color: #212529;
}

.ms3-auth-tabs .nav-link.active {
    border-color: #0d6efd;
    color: #0d6efd;
}

.ms3-auth-content {
    padding: 1.5rem;
}

.ms3-auth-form {
    max-width: 400px;
    margin: 0 auto;
}

.ms3-messages {
    margin-bottom: 1rem;
}

.ms3-messages .alert {
    margin-bottom: 0.5rem;
}

.ms3-messages .alert:last-child {
    margin-bottom: 0;
}

/* Спиннер для кнопок при отправке */
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.65;
}

.btn-loading::after {
    content: "";
    position: absolute;
    width: 1rem;
    height: 1rem;
    top: 50%;
    left: 50%;
    margin-left: -0.5rem;
    margin-top: -0.5rem;
    border: 2px solid currentColor;
    border-radius: 50%;
    border-right-color: transparent;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}
</style>

{* Подключение и инициализация AuthForms *}
<script src="{'assets_url' | option}components/minishop3/js/web/modules/auth-forms.js"></script>
<script>
(function() {
    'use strict';

    // Инициализация AuthForms
    const authForms = new AuthForms({
        apiUrl: '{'assets_url' | option}components/minishop3/api.php',
        loginRoute: '/api/v1/customer/login',
        registerRoute: '/api/v1/customer/register'
    });

    authForms.init();

    // Передача лексиконов в глобальный объект (для использования в auth-forms.js)
    window.ms3Lexicon = window.ms3Lexicon || {};
    window.ms3Lexicon.ms3_customer_err_login_required = '{'ms3_customer_err_login_required' | lexicon}';
    window.ms3Lexicon.ms3_customer_login_success = '{'ms3_customer_login_success' | lexicon}';
    window.ms3Lexicon.ms3_customer_err_register_required = '{'ms3_customer_err_register_required' | lexicon}';
    window.ms3Lexicon.ms3_customer_err_password_mismatch = '{'ms3_customer_err_password_mismatch' | lexicon}';
    window.ms3Lexicon.ms3_customer_err_privacy_required = '{'ms3_customer_err_privacy_required' | lexicon}';
    window.ms3Lexicon.ms3_customer_register_success = '{'ms3_customer_register_success' | lexicon}';
    window.ms3Lexicon.ms3_err_unknown = '{'ms3_err_unknown' | lexicon}';
})();
</script>
