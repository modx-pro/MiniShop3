<div class="ms3-customer-address-form">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                {if $mode == 'edit'}
                    {'ms3_customer_address_edit' | lexicon}
                {else}
                    {'ms3_customer_address_add' | lexicon}
                {/if}
            </h5>
        </div>
        <div class="card-body">
            <form class="ms3_form ms3-address-form" method="post" action="" data-ms3-form="customer-address">
                <input type="hidden" name="ms3_action" value="customer/{if $mode == 'edit'}address-update{else}address-create{/if}">
                {if $mode == 'edit'}
                <input type="hidden" name="id" value="{$address.id}">
                {/if}

                {* Название адреса *}
                <div class="mb-3">
                    <label for="name" class="form-label">
                        {'ms3_customer_address_name' | lexicon}
                    </label>
                    <input type="text"
                           class="form-control {if $errors.name?}is-invalid{/if}"
                           id="name"
                           name="name"
                           value="{$address.name}"
                           placeholder="{'ms3_customer_address_name_placeholder' | lexicon}">
                    {if $errors.name?}
                    <div class="invalid-feedback">{$errors.name}</div>
                    {/if}
                    <div class="form-text">
                        {'ms3_customer_address_name_help' | lexicon}
                    </div>
                </div>

                <div class="row">
                    {* Индекс *}
                    <div class="col-md-4 mb-3">
                        <label for="index" class="form-label">
                            {'ms3_customer_index' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.index?}is-invalid{/if}"
                               id="index"
                               name="index"
                               value="{$address.index}">
                        {if $errors.index?}
                        <div class="invalid-feedback">{$errors.index}</div>
                        {/if}
                    </div>

                    {* Страна *}
                    <div class="col-md-8 mb-3">
                        <label for="country" class="form-label">
                            {'ms3_customer_country' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.country?}is-invalid{/if}"
                               id="country"
                               name="country"
                               value="{$address.country}">
                        {if $errors.country?}
                        <div class="invalid-feedback">{$errors.country}</div>
                        {/if}
                    </div>
                </div>

                <div class="row">
                    {* Регион *}
                    <div class="col-md-6 mb-3">
                        <label for="region" class="form-label">
                            {'ms3_customer_region' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.region?}is-invalid{/if}"
                               id="region"
                               name="region"
                               value="{$address.region}">
                        {if $errors.region?}
                        <div class="invalid-feedback">{$errors.region}</div>
                        {/if}
                    </div>

                    {* Город *}
                    <div class="col-md-6 mb-3">
                        <label for="city" class="form-label">
                            {'ms3_customer_city' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control {if $errors.city?}is-invalid{/if}"
                               id="city"
                               name="city"
                               value="{$address.city}"
                               required>
                        {if $errors.city?}
                        <div class="invalid-feedback">{$errors.city}</div>
                        {/if}
                    </div>
                </div>

                {* Улица *}
                <div class="mb-3">
                    <label for="street" class="form-label">
                        {'ms3_customer_street' | lexicon} <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           class="form-control {if $errors.street?}is-invalid{/if}"
                           id="street"
                           name="street"
                           value="{$address.street}"
                           required>
                    {if $errors.street?}
                    <div class="invalid-feedback">{$errors.street}</div>
                    {/if}
                </div>

                <div class="row">
                    {* Дом *}
                    <div class="col-md-3 mb-3">
                        <label for="building" class="form-label">
                            {'ms3_customer_building' | lexicon} <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control {if $errors.building?}is-invalid{/if}"
                               id="building"
                               name="building"
                               value="{$address.building}"
                               required>
                        {if $errors.building?}
                        <div class="invalid-feedback">{$errors.building}</div>
                        {/if}
                    </div>

                    {* Подъезд *}
                    <div class="col-md-3 mb-3">
                        <label for="entrance" class="form-label">
                            {'ms3_customer_entrance' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.entrance?}is-invalid{/if}"
                               id="entrance"
                               name="entrance"
                               value="{$address.entrance}">
                        {if $errors.entrance?}
                        <div class="invalid-feedback">{$errors.entrance}</div>
                        {/if}
                    </div>

                    {* Этаж *}
                    <div class="col-md-3 mb-3">
                        <label for="floor" class="form-label">
                            {'ms3_customer_floor' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.floor?}is-invalid{/if}"
                               id="floor"
                               name="floor"
                               value="{$address.floor}">
                        {if $errors.floor?}
                        <div class="invalid-feedback">{$errors.floor}</div>
                        {/if}
                    </div>

                    {* Квартира *}
                    <div class="col-md-3 mb-3">
                        <label for="room" class="form-label">
                            {'ms3_customer_room' | lexicon}
                        </label>
                        <input type="text"
                               class="form-control {if $errors.room?}is-invalid{/if}"
                               id="room"
                               name="room"
                               value="{$address.room}">
                        {if $errors.room?}
                        <div class="invalid-feedback">{$errors.room}</div>
                        {/if}
                    </div>
                </div>

                {* Метро *}
                <div class="mb-3">
                    <label for="metro" class="form-label">
                        {'ms3_customer_metro' | lexicon}
                    </label>
                    <input type="text"
                           class="form-control {if $errors.metro?}is-invalid{/if}"
                           id="metro"
                           name="metro"
                           value="{$address.metro}">
                    {if $errors.metro?}
                    <div class="invalid-feedback">{$errors.metro}</div>
                    {/if}
                </div>

                {* Комментарий к адресу *}
                <div class="mb-3">
                    <label for="text_address" class="form-label">
                        {'ms3_customer_comment' | lexicon}
                    </label>
                    <textarea class="form-control {if $errors.text_address?}is-invalid{/if}"
                              id="text_address"
                              name="text_address"
                              rows="2">{$address.text_address}</textarea>
                    {if $errors.text_address?}
                    <div class="invalid-feedback">{$errors.text_address}</div>
                    {/if}
                    <div class="form-text">
                        {'ms3_customer_address_comment_help' | lexicon}
                    </div>
                </div>

                {* Кнопки *}
                <div class="d-flex justify-content-between gap-2 mt-4">
                    <a href="?" class="btn btn-secondary">
                        {'ms3_customer_cancel' | lexicon}
                    </a>
                    <button type="submit" class="btn btn-primary ms3_link">
                        {if $mode == 'edit'}
                            {'ms3_customer_save' | lexicon}
                        {else}
                            {'ms3_customer_address_add' | lexicon}
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
