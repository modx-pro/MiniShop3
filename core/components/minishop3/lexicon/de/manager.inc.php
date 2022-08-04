<?php

/**
 * Manager German Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms_menu_create'] = 'Anlegen';
$_lang['ms_menu_update'] = 'Bearbeiten';
$_lang['ms_menu_remove'] = 'Entfernen';
$_lang['ms_menu_remove_multiple'] = 'Ausgewählte entfernen';
$_lang['ms_menu_remove_confirm'] = 'Diesen Eintrag entfernen?';
$_lang['ms_menu_remove_multiple_confirm'] = 'Alle ausgewählten Einträge entfernen?';

$_lang['ms_combo_select'] = 'Für Auswahl klicken';
$_lang['ms_combo_select_status'] = 'Nach Status sortieren';

$_lang['ms_id'] = 'ID';
$_lang['ms_name'] = 'Name';
$_lang['ms_color'] = 'Farbe';
$_lang['ms_country'] = 'Land';
$_lang['ms_logo'] = 'Logo';
$_lang['ms_address'] = 'Adresse';
$_lang['ms_phone'] = 'Telefon';
$_lang['ms_fax'] = 'Fax';
$_lang['ms_email'] = 'e-Mail';
$_lang['ms_active'] = 'Aktiv';
$_lang['ms_class'] = 'Handler class';
$_lang['ms_description'] = 'Beschreibung';
$_lang['ms_num'] = 'Nummer';
$_lang['ms_status'] = 'Status';
$_lang['ms_count'] = 'Anzahl';
$_lang['ms_cost'] = 'Preis';
$_lang['ms_order_cost'] = 'Bestellpreis';
$_lang['ms_cart_cost'] = 'Gesamtpreis der Produkte';
$_lang['ms_delivery_cost'] = 'Lieferungspreis';
$_lang['ms_weight'] = 'Gewicht';
$_lang['ms_createdon'] = 'Erstellt am';
$_lang['ms_updatedon'] = 'Bearbeitet am';
$_lang['ms_user'] = 'Benutzer';
$_lang['ms_timestamp'] = 'Zeitpunkt';
$_lang['ms_order_log'] = 'Bestellprotokoll';
$_lang['ms_order_products'] = 'Produkte';
$_lang['ms_action'] = 'Aktion';
$_lang['ms_entry'] = 'Eintrag';
$_lang['ms_username'] = 'Benutzername';
$_lang['ms_fullname'] = 'Vollständiger Name';
$_lang['ms_resource'] = 'Resource';

$_lang['ms_receiver'] = 'Empfänger';
$_lang['ms_index'] = 'PLZ';
$_lang['ms_region'] = 'Bundesland';
$_lang['ms_city'] = 'Stadt';
$_lang['ms_metro'] = 'Metro';
$_lang['ms_street'] = 'Straße';
$_lang['ms_building'] = 'Gebäude';
$_lang['ms_room'] = 'Raum';
$_lang['ms_comment'] = 'Kommentar';

$_lang['ms_email_user'] = 'e-Mail Kunde';
$_lang['ms_email_manager'] = 'e-Mail Manager';
$_lang['ms_subject_user'] = 'Betreff der e-Mail an Kunde';
$_lang['ms_subject_manager'] = 'Betreff der e-Mail an Manager';
$_lang['ms_body_user'] = 'Chunk für e-Mail an Kunde';
$_lang['ms_body_manager'] = 'Chunk für e-Mail an Manager';
$_lang['ms_status_final'] = 'Final';
$_lang['ms_status_final_help'] = '';
$_lang['ms_status_fixed'] = 'Fixed';
$_lang['ms_status_fixed_help'] = '';
$_lang['ms_options'] = 'Optionen';
$_lang['ms_price'] = 'Preis';
$_lang['ms_price_help'] = 'Basislieferpreis';
$_lang['ms_weight_price'] = 'Preis für 1 St./kg';
$_lang['ms_weight_price_help'] = 'Zusätzliche Kosten pro Gewichtseinheit.<br/>Kann in benutzerdefinierten Klassen verwendet werden.';
$_lang['ms_distance_price'] = 'Preis für 1 St./Entfernung';
$_lang['ms_distance_price_help'] = 'Zusätzliche Kosten pro Entfernungseinheit<br/>Kann in benutzerdefinierten Klassen verwendet werden.';
$_lang['ms_order_requires'] = 'Pflichtfelder';
$_lang['ms_order_requires_help'] = 'Bei der Bestellung ist das Ausfüllen dieser Felder Pflicht';
$_lang['ms_free_delivery_amount'] = 'Kostenloser Versand auf Bestellmenge';
$_lang['ms_free_delivery_amount_help'] = 'Wenn der Bestellbetrag diesen Wert erreicht, ist die Lieferung kostenlos. Wenn die Versandklasse geändert wurde und / oder Sie Komponenten installiert haben, die sich auf den Bestellwert auswirken können, wird dieses Feld möglicherweise nicht berücksichtigt';

$_lang['ms_orders_selected_status'] = 'Status geändert';

$_lang['ms_link_name'] = 'Linkname';
$_lang['ms_link_one_to_one'] = 'Eins zu Eins';
$_lang['ms_link_one_to_one_desc'] = 'Equal union of two goods. If you want to connect more than 2 product, you need to use the "many-to-many".';
$_lang['ms_link_one_to_many'] = 'One to many';
$_lang['ms_link_one_to_many_desc'] = 'The connection of the master of the goods with slaves. For example, the product is a set of other goods. Well suited for the specifying recommended goods.';
$_lang['ms_link_many_to_one'] = 'Many to one';
$_lang['ms_link_many_to_one_desc'] = 'Link slaves with the master and slaves has no connection with each other. For example, goods are included in a set.';
$_lang['ms_link_many_to_many'] = 'Many to many';
$_lang['ms_link_many_to_many_desc'] = 'Equal union of many goods. All the goods of the group are connected with each other and with the addition of a new connection to one product, all other will have the same. Typical applications: link by one parameter, such as color, size, language, version, etc.';
$_lang['ms_link_master'] = 'Eltern-Produkt';
$_lang['ms_link_slave'] = 'Kind-Produkt';
