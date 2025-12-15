<?php
/**
 * Combo field configurations for msOrderAddress model
 *
 * Defines data sources for dropdown/select fields on order address forms.
 *
 * Source types:
 * - model: Load options from xPDO model class
 * - static: Static options array
 *
 * To customize: copy this file to custom/combos/msOrderAddress.php and modify.
 * Custom config will be merged with default (custom overrides default).
 *
 * Database config (ms3_model_fields.properties) has highest priority.
 *
 * @see \MiniShop3\Services\ComboConfigManager
 */

return [
    // Country dropdown example (static options)
    // 'country' => [
    //     'source' => [
    //         'type' => 'static',
    //         'options' => [
    //             ['value' => 'RU', 'label' => 'Россия'],
    //             ['value' => 'BY', 'label' => 'Беларусь'],
    //             ['value' => 'KZ', 'label' => 'Казахстан'],
    //             ['value' => 'UA', 'label' => 'Украина'],
    //         ],
    //     ],
    // ],

    // Region dropdown example (from model)
    // 'region' => [
    //     'source' => [
    //         'type' => 'model',
    //         'class' => 'YourNamespace\\Model\\Region',
    //         'valueField' => 'id',
    //         'labelField' => 'name',
    //         'sort' => ['name' => 'ASC'],
    //     ],
    // ],
];
