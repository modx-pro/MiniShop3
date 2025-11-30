{extends 'file:templates/base.tpl'}

{block 'pagecontent'}
<style>
    .customer-account {
        min-height: 100vh;
        background-color: #f8f9fa;
        padding: 3rem 0;
    }

    .account-sidebar {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 0;
        position: sticky;
        top: 20px;
    }

    .account-sidebar .customer-info {
        padding: 1.5rem;
        border-bottom: 1px solid #dee2e6;
    }

    .account-sidebar .nav-link {
        padding: 1rem 1.5rem;
        color: #495057;
        border-bottom: 1px solid #f1f3f5;
        border-radius: 0;
        transition: all 0.2s;
    }

    .account-sidebar .nav-link:hover {
        background-color: #f8f9fa;
        color: #007bff;
    }

    .account-sidebar .nav-link.active {
        background-color: #007bff;
        color: white;
        font-weight: 500;
    }

    .account-sidebar .nav-link svg {
        width: 18px;
        height: 18px;
        margin-right: 0.75rem;
        vertical-align: middle;
    }

    .account-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 2rem;
        min-height: 500px;
    }

    .customer-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
    }

    .verification-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
    }

    .verification-badge.verified {
        background-color: #d4edda;
        color: #155724;
    }

    .verification-badge.unverified {
        background-color: #fff3cd;
        color: #856404;
    }

    @media (max-width: 768px) {
        .account-sidebar {
            position: static;
            margin-bottom: 1.5rem;
        }

        .account-sidebar .customer-info {
            text-align: center;
        }

        .customer-avatar {
            margin: 0 auto 1rem;
        }
    }
</style>

<div class="customer-account">
    <div class="container">
        {* Вызываем сниппет который отрендерит весь контент с sidebar *}
        [[!msCustomer?
            &service=`[[+service:default=`profile`]]`
        ]]
    </div>
</div>

{* Дополнительные SVG иконки для личного кабинета *}
<svg style="display: none;">
    <symbol id="icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
    </symbol>
    <symbol id="icon-map-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
        <circle cx="12" cy="10" r="3"></circle>
    </symbol>
    <symbol id="icon-shopping-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <path d="M16 10a4 4 0 0 1-8 0"></path>
    </symbol>
    <symbol id="icon-log-out" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
    </symbol>
    <symbol id="icon-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </symbol>
    <symbol id="icon-alert-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </symbol>
</svg>
{/block}
