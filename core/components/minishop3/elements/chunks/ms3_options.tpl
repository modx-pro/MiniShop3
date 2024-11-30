{foreach $options as $name => $values}
    <div class="form-group row align-items-center mb-4">
        <label class="col-6 col-md-3 text-right text-md-left col-form-label" for="option_{$name}">{('ms3_product_' ~ $name) | lexicon}:</label>
        <div class="col-6 col-md-9">
            <select name="options[{$name}]" class="form-select col-md-6" id="option_{$name}">
                {foreach $values as $value}
                    <option value="{$value}">{$value}</option>
                {/foreach}
            </select>
        </div>
    </div>
{/foreach}
