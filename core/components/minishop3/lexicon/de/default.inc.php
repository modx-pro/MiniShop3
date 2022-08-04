<?php

/**
 * Default German Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

include_once('setting.inc.php');

$_lang['miniShop3'] = 'MiniShop3';
$_lang['ms_menu_desc'] = 'Geniale e-commerce Erweiterung';
$_lang['ms_order'] = 'Bestellung';
$_lang['ms_orders'] = 'Bestellungen';
$_lang['ms_orders_intro'] = 'Verwalten Sie Ihre Bestellungen';
$_lang['ms_orders_desc'] = 'Verwalten Sie Ihre Bestellungen';
$_lang['ms_settings'] = 'Einstellungen';
$_lang['ms_settings_intro'] = 'Einstellungen für den MiniShop3. Hier können Sie Zahlungs- und Lieferungsoptionen festlegen sowie den Bestellstatus abwickeln.';
$_lang['ms_settings_desc'] = 'Status der Bestellung, Optionen zu Zahlungen und Lieferungen';
$_lang['ms_payment'] = 'Zahlung';
$_lang['ms_payments'] = 'Zahlungen';
$_lang['ms_payments_intro'] = 'Sie können jegliche Art von Zahlungen anlegen. Die Logik der Zahlung (das Senden des Käufers auf dem Remote-Service, Empfang der Zahlung, etc.) wird in der Klasse, die Sie angeben, implementiert.<br/>Für Zahlungsmethoden ist der Parameter "class" erforderlich.';
$_lang['ms_delivery'] = 'Lieferung';
$_lang['ms_deliveries'] = 'Lieferungen';
$_lang['ms_deliveries_intro'] = 'Verschiedene Varianten der Lieferung. Die Logik der Preiskalkulation der Lieferung (abhängig von der Distanz und dem Gewicht der Ware) ist in Form einer Klasse implementiert, die in den Einstellungen festgelegt werden kann.<br/>Wenn Sie keine Klasse angeben, wird der Standard-Algorythmus die Berechnung vornehmen.';
$_lang['ms_statuses'] = 'Status';
$_lang['ms_statuses_intro'] = 'Es existieren verschiedene Bestellstatus: "Neu", "Bezahlt", "Versandt" und "Storniert". Diese Status können bearbeitet, aber nicht entfernt werden, da sie für die Funktionalität des Shops von Nöten sind. Weitere Status können hinzugefügt werden.<br/>Vorsicht bei der Statusvergabe, denn einige Status können nicht getauscht werden (z.B. "Versandt" und "Storniert"). Ein "Zurückgehen" zu einem vorherigen Status (z.B. von "Bezahlt" auf "Neu") ist ebenfalls nicht möglich.';
$_lang['ms_vendors'] = 'Hersteller';
$_lang['ms_vendors_intro'] = 'Eine Liste möglicher Hersteller Ihrer Produkte. Was Sie hier hinzufügen, können Sie in der Produktbeschreibung im Feld "Hersteller" auswählen.';
$_lang['ms_link'] = 'Produktlink';
$_lang['ms_links'] = 'Produktlinks';
$_lang['ms_links_intro'] = 'The list of possible links of goods with each other. Connection type describes exactly how it will work, it is impossible to create, you can only select from the list.';
$_lang['ms_customer'] = 'Kunde';
$_lang['ms_all'] = 'Alle';
$_lang['ms_type'] = 'Typ';

$_lang['ms_btn_create'] = 'Erstellen';
$_lang['ms_btn_save'] = 'Speichern';
$_lang['ms_btn_edit'] = 'Bearbeiten';
$_lang['ms_btn_view'] = 'Ansehen';
$_lang['ms_btn_delete'] = 'Löschen';
$_lang['ms_btn_undelete'] = 'Wiederherstellen';
$_lang['ms_btn_publish'] = 'Veröffentlichen';
$_lang['ms_btn_unpublish'] = 'Veröffentlichung zurückziehen';
$_lang['ms_btn_cancel'] = 'Abbrechen';
$_lang['ms_btn_back'] = 'Zurück (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Zurück btn (alt + &larr;)';
$_lang['ms_btn_next'] = 'Vor btn (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Hilfe';
$_lang['ms_btn_duplicate'] = 'Produkt duplizieren';

$_lang['ms_actions'] = 'Aktionen';
$_lang['ms_search'] = 'Suchen';
$_lang['ms_search_clear'] = 'Verwerfen';

$_lang['ms_category'] = 'Produktkategorie';
$_lang['ms_category_tree'] = 'Kategoriebaum';
$_lang['ms_category_type'] = 'Produktkategorie-Typ';
$_lang['ms_category_create'] = 'Kategorie hinzufügen';
$_lang['ms_category_create_here'] = 'Produktkategorie hier erstellen';
$_lang['ms_category_manage'] = 'Kategorie verwalten';
$_lang['ms_category_duplicate'] = 'Kategorie duplizieren';
$_lang['ms_category_publish'] = 'Kategorie veröffentlichen';
$_lang['ms_category_unpublish'] = 'Kategorie zurückziehen';
$_lang['ms_category_delete'] = 'Kategorie löschen';
$_lang['ms_category_undelete'] = 'Kategorie wiederherstellen';
$_lang['ms_category_view'] = 'Auf Seite ansehen';
$_lang['ms_category_new'] = 'Neue Kategorie';

$_lang['ms_product'] = 'Produkt';
$_lang['ms_product_type'] = 'Produkt-Typ';
$_lang['ms_product_create_here'] = 'Produkt hier erstellen';
$_lang['ms_product_create'] = 'Produkt hinzufügen';

$_lang['ms_frontend_currency'] = '€';
$_lang['ms_frontend_weight_unit'] = 'kg';
$_lang['ms_frontend_count_unit'] = 'St.';
$_lang['ms_frontend_add_to_cart'] = 'Zum Warenkorb hinzufügen';
$_lang['ms_frontend_tags'] = 'Tags';
$_lang['ms_frontend_colors'] = 'Farben';
$_lang['ms_frontend_color'] = 'Farbe';
$_lang['ms_frontend_sizes'] = 'Größen';
$_lang['ms_frontend_size'] = 'Größe';
$_lang['ms_frontend_popular'] = 'Beliebt';
$_lang['ms_frontend_favorite'] = 'Favorit';
$_lang['ms_frontend_new'] = 'Neu';
$_lang['ms_frontend_deliveries'] = 'Zustellungen';
$_lang['ms_frontend_payments'] = 'Zahlungen';
$_lang['ms_frontend_delivery_select'] = 'Zustellungsart wählen';
$_lang['ms_frontend_payment_select'] = 'Bezahlungsart wählen';
$_lang['ms_frontend_credentials'] = 'Voraussetzungen';
$_lang['ms_frontend_address'] = 'Adresse';

$_lang['ms_frontend_comment'] = 'Nachricht / Kommentar';
$_lang['ms_frontend_receiver'] = 'Empfänger';
$_lang['ms_frontend_email'] = 'Email';
$_lang['ms_frontend_phone'] = 'Telefon';
$_lang['ms_frontend_index'] = 'PLZ';
$_lang['ms_frontend_country'] = 'Land';
$_lang['ms_frontend_region'] = 'Bundesland';
$_lang['ms_frontend_city'] = 'Stadt';
$_lang['ms_frontend_street'] = 'Straße';
$_lang['ms_frontend_building'] = 'Gebäude';
$_lang['ms_frontend_room'] = 'Raum';

$_lang['ms_frontend_order_cost'] = 'Gesamtsumme';
$_lang['ms_frontend_order_submit'] = 'Bestellunge senden!';
$_lang['ms_frontend_order_cancel'] = 'Formular zurücksetzen';
$_lang['ms_frontend_order_success'] = 'Vielen Dank für Ihre Bestellung <b>#[[+num]]</b> auf unserer Webseite: <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'Alle schließen';
$_lang['ms_err_unknown'] = 'Unbekannter Fehler';
$_lang['ms_err_ns'] = 'Dieses Feld ist ein Pflichtfeld';
$_lang['ms_err_ae'] = 'This field must be unique';
$_lang['ms_err_user_nf'] = 'Benutzer nicht gefunden.';
$_lang['ms_err_order_nf'] = 'Eine Bestellung mit dieser ID wurde nicht gefunden.';
$_lang['ms_err_status_nf'] = 'Ein Status mit dieser ID wurde nicht gefunden.';
$_lang['ms_err_delivery_nf'] = 'Eine Zustellung mit dieser ID wurde nicht gefunden.';
$_lang['ms_err_payment_nf'] = 'Eine Zahlung mit dieser ID wurde nicht gefunden.';
$_lang['ms_err_status_final'] = 'Der finale Status wurde bereits gesetzt und kann nicht geändert werden.';
$_lang['ms_err_status_fixed'] = 'Status ist fix und kann nicht zurück auf einen vorherigen Status gändert werden.';
$_lang['ms_err_status_same'] = 'Dieser Status ist bereits vergeben.';
$_lang['ms_err_register_globals'] = 'Error: PHP Parameter <b>register_globals</b> muss "off" sein.';

$_lang['ms_email_subject_new_user'] = 'Ihre Bestellung #[[+num]] auf [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'Neue Bestellung #[[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Ihre Bezahlung der Bestellung #[[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'Bestellung #[[+num]] bezahlt';
$_lang['ms_email_subject_sent_user'] = 'Ihre Bestellung #[[+num]] wurde verschickt';
$_lang['ms_email_subject_cancelled_user'] = 'Ihre Bestellung #[[+num]] wurde gestrichen';
