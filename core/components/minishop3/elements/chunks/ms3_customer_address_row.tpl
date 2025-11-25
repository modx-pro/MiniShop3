<div class="list-group-item"
     data-confirm-set-default="{'ms3_customer_address_set_default_confirm' | lexicon}"
     data-confirm-delete="{'ms3_customer_address_delete_confirm' | lexicon}"
     data-error-unknown="{'ms3_err_unknown' | lexicon}">
    <div class="d-flex w-100 justify-content-between align-items-start">
        <div class="flex-grow-1">
            <h6 class="mb-1">
                {if $name}
                    {$name}
                {else}
                    {$display_name}
                {/if}
                {if $is_default}
                <span class="badge bg-success ms-2">{'ms3_customer_address_default' | lexicon}</span>
                {/if}
            </h6>
            <p class="mb-1 text-muted small">
                {if $index}{$index}, {/if}
                {$city}{if $region}, {$region}{/if}{if $country}, {$country}{/if}
            </p>
            <p class="mb-1 small">
                {$street}{if $building}, д. {$building}{/if}{if $entrance}, п. {$entrance}{/if}{if $floor}, эт. {$floor}{/if}{if $room}, кв. {$room}{/if}
            </p>
            {if $metro}
            <p class="mb-1 small text-muted">
                <svg width="14" height="14" fill="currentColor" class="me-1">
                    <use xlink:href="#icon-metro"/>
                </svg>
                м. {$metro}
            </p>
            {/if}
            {if $text_address}
            <p class="mb-1 small text-muted fst-italic">
                {$text_address}
            </p>
            {/if}
        </div>
        <div class="btn-group btn-group-sm ms-3" role="group">
            {if !$is_default}
            <button type="button"
                    class="btn btn-outline-secondary set-default-address"
                    data-address-id="{$id}"
                    title="{'ms3_customer_address_set_default' | lexicon}">
                <svg width="16" height="16" fill="currentColor">
                    <use xlink:href="#icon-star"/>
                </svg>
            </button>
            {/if}
            <a href="?mode=edit&id={$id}" class="btn btn-outline-primary" title="{'ms3_customer_address_edit' | lexicon}">
                <svg width="16" height="16" fill="currentColor">
                    <use xlink:href="#icon-edit"/>
                </svg>
            </a>
            <button type="button"
                    class="btn btn-outline-danger delete-address"
                    data-address-id="{$id}"
                    title="{'ms3_customer_address_delete' | lexicon}">
                <svg width="16" height="16" fill="currentColor">
                    <use xlink:href="#icon-trash"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
// Установка адреса по умолчанию
document.querySelectorAll('.set-default-address').forEach(btn => {
    btn.addEventListener('click', async function() {
        const container = this.closest('.list-group-item');
        const confirmText = container.dataset.confirmSetDefault || 'Сделать этот адрес основным?';
        const errorText = container.dataset.errorUnknown || 'Произошла ошибка';

        if (!confirm(confirmText)) return;

        const addressId = this.dataset.addressId;
        try {
            const response = await fetch(`/assets/components/minishop3/api.php`, {
                method: 'PUT',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ms3_action=customer/address/set-default&id=${addressId}`
            });

            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || errorText);
            }
        } catch (error) {
            alert(errorText);
        }
    });
});

// Удаление адреса
document.querySelectorAll('.delete-address').forEach(btn => {
    btn.addEventListener('click', async function() {
        const container = this.closest('.list-group-item');
        const confirmText = container.dataset.confirmDelete || 'Вы уверены, что хотите удалить этот адрес?';
        const errorText = container.dataset.errorUnknown || 'Произошла ошибка';

        if (!confirm(confirmText)) return;

        const addressId = this.dataset.addressId;
        try {
            const response = await fetch(`/assets/components/minishop3/api.php`, {
                method: 'DELETE',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ms3_action=customer/address/delete&id=${addressId}`
            });

            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || errorText);
            }
        } catch (error) {
            alert(errorText);
        }
    });
});
</script>
