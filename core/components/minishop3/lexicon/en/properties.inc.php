<?php

/**
 * Properties English Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms3_prop_limit'] = 'Limit of results selection';
$_lang['ms3_prop_offset'] = 'Skip results from beginning of selection';
$_lang['ms3_prop_depth'] = 'Depth of products search from each parent.';
$_lang['ms3_prop_sortby'] = 'Selection sorting. To sort by product fields you need to add "Data." prefix, for example: "&sortby=`Data.price`"';
$_lang['ms3_prop_sortdir'] = 'Sort direction';
$_lang['ms3_prop_where'] = 'Additional selection parameters encoded in JSON.';
$_lang['ms3_prop_tpl'] = 'Chunk for formatting each result';
$_lang['ms3_prop_toPlaceholder'] = 'If not empty, snippet will save all data in placeholder with this name instead of output to screen.';
$_lang['ms3_prop_toSeparatePlaceholders'] = 'If you specify a word in this parameter, ALL results will be set to different placeholders starting with this word and ending with row number from zero. For example, specifying "myPl" you will get placeholders [[+myPl0]], [[+myPl1]], etc.';
$_lang['ms3_prop_showLog'] = 'Show additional information about snippet work. Only for users authorized in "mgr" context.';
$_lang['ms3_prop_parents'] = 'Comma-separated list of categories to search results. By default selection is limited to current parent. If set to 0 - selection is not limited.';
$_lang['ms3_prop_resources'] = 'Comma-separated list of products to display in results. If product id starts with minus, this product is excluded from selection.';
$_lang['ms3_prop_fastMode'] = 'If enabled - only values from DB will be substituted in result chunk. All unprocessed MODX tags such as filters, snippet calls and others - will be cut out.';
$_lang['ms3_prop_where'] = 'JSON-encoded string with additional selection conditions.';
$_lang['ms3_prop_includeContent'] = 'Select "content" field of products.';
$_lang['ms3_prop_includeTVs'] = 'Comma-separated list of TV parameters to select. For example: "action,time" will give placeholders [[+action]] and [[+time]].';
$_lang['ms3_prop_includeThumbs'] = 'Comma-separated list of thumbnail sizes to select. For example: "small,medium" will give placeholders [[+small]] and [[+medium]]. Images must be pre-generated in product gallery.';
$_lang['ms3_prop_includeVendorFields'] = 'Comma-separated list of vendor table fields to select. For example: "name,logo". By default all fields are selected via *.';
$_lang['ms3_prop_link'] = 'Products link id that is assigned automatically when creating new link in settings.';
$_lang['ms3_prop_master'] = 'Master product id. If both "master" and "slave" are specified - selection will be by master.';
$_lang['ms3_prop_slave'] = 'Slave product id. If "master" is specified - this option is ignored.';
$_lang['ms3_prop_class'] = 'Class name for selection. Default is "msProduct".';
$_lang['ms3_prop_tvPrefix'] = 'Prefix for TV placeholders, for example "tv.". By default parameter is empty.';
$_lang['ms3_prop_outputSeparator'] = 'Optional string to separate work results.';
$_lang['ms3_prop_returnIds'] = 'Return string with product ids instead of formatted chunks.';
$_lang['ms3_prop_return'] = 'Results output method';

$_lang['ms3_prop_showUnpublished'] = 'Show unpublished products.';
$_lang['ms3_prop_showDeleted'] = 'Show deleted products.';
$_lang['ms3_prop_showHidden'] = 'Show products hidden in menu.';
$_lang['ms3_prop_showZeroPrice'] = 'Show products with zero price.';

$_lang['ms3_prop_tplRow'] = 'Chunk for formatting one selection item.';
$_lang['ms3_prop_tplSingle'] = 'Chunk for formatting single selection result.';
$_lang['ms3_prop_tplOuter'] = 'Wrapper for snippet work results output.';
$_lang['ms3_prop_tplEmpty'] = 'Chunk that is displayed when there are no results.';
$_lang['ms3_prop_tplSuccess'] = 'Chunk with message about successful snippet work.';
$_lang['ms3_prop_tplPaymentsOuter'] = 'Chunk for formatting block of possible payment methods.';
$_lang['ms3_prop_tplPaymentsRow'] = 'Chunk for formatting one payment method.';
$_lang['ms3_prop_tplDeliveriesOuter'] = 'Chunk for formatting block of possible delivery methods.';
$_lang['ms3_prop_tplDeliveriesRow'] = 'Chunk for formatting one delivery method.';

$_lang['ms3_prop_options'] = 'Comma-separated list of options to display.';
$_lang['ms3_prop_product'] = 'Product identifier. If not specified, current document id is used.';
$_lang['ms3_prop_optionSelected'] = 'Active option name to set "selected" attribute';
$_lang['ms3_prop_optionName'] = 'Option name to display.';
$_lang['ms3_prop_filetype'] = 'File type for selection. You can use "image" for images and extensions for other files. For example: "image,pdf,xls,doc".';
$_lang['ms3_prop_optionFilters'] = 'Filters by product options. Passed as JSON string, for example, {"optionkey:>":10}';
$_lang['ms3_prop_sortbyOptions'] = 'Specifies by which options and how to sort among those listed in &sortby. Passed as string, for example, "optionkey:integer,optionkey2:datetime"';
$_lang['ms3_prop_sortGroups'] = 'Specifies sort order of option groups. Accepts both ids and text group names. Passed as string, for example: "22,23,24" or "Sizes,Electronics,Other".';
$_lang['ms3_prop_sortOptions'] = 'Specifies sort order of options. Passed as string, for example: "size,color".';
$_lang['ms3_prop_sortOptionValues'] = 'Specifies sort order of option values. Passed as string, for example: "size:SORT_DESC:SORT_NUMERIC:100,color:SORT_ASC:SORT_STRING"';
$_lang['ms3_prop_valuesSeparator'] = 'Separator for multiple option values';
$_lang['ms3_prop_ignoreGroups'] = 'Comma-separated groups whose options should not be displayed in list.';
$_lang['ms3_prop_ignoreOptions'] = 'Comma-separated options that should not be displayed in list.';
$_lang['ms3_prop_onlyOptions'] = 'Display only this comma-separated list of options';
$_lang['ms3_prop_hideEmpty'] = 'Do not show options with empty values.';
$_lang['ms3_prop_groups'] = 'Display options only of specified groups (name or category identifier comma-separated, "0" means without groups)';
$_lang['ms3_prop_tplValue'] = 'Template for one value (only for multiple options)';

$_lang['ms3_prop_userFields'] = 'Associative array of correspondence between order fields and user profile fields in format "order field" => "profile field".';
$_lang['ms3_prop_wrapIfEmpty'] = 'Enables output of wrapper chunk (tplWrapper) even if there are no results.';
$_lang['ms3_prop_includeDeliveryFields'] = 'Comma-separated list of msDelivery table fields to select. For example: "name,price,free_delivery_amount". By default all fields are selected via *.';
$_lang['ms3_prop_includePaymentFields'] = 'Comma-separated list of msPayment table fields to select. For example: "name,description,price,logo". By default all fields are selected via *.';
