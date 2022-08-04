<?php

/**
 * Default English Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

include_once('setting.inc.php');
$files = scandir(dirname(__FILE__));
foreach ($files as $file) {
    if (strpos($file, 'msp.') === 0) {
        @include_once($file);
    }
}

$_lang['miniShop3'] = 'MiniShop3';
$_lang['ms_menu_desc'] = 'Puikus priedas el. komercijai';
$_lang['ms_order'] = 'Užsakymas';
$_lang['ms_orders'] = 'Užsakymai';
$_lang['ms_orders_intro'] = 'Užsakymų tvarkymas';
$_lang['ms_orders_desc'] = 'Tvarkyti užsakymus';
$_lang['ms_settings'] = 'Nuostatos';
$_lang['ms_settings_intro'] = 'Pagrindinės parduotuvės nuostatos. Čia aprašomi apmokėjimo ir pristatymo būdai bei galimos užsakymų būsenos';
$_lang['ms_settings_desc'] = 'Užsakymų būsenos, apmokėjimo ir pristatymo būdai';
$_lang['ms_payment'] = 'Apmokėjimas';
$_lang['ms_payments'] = 'Apmokėjimo būdai';
$_lang['ms_payments_intro'] = 'Galite kurti bet kokius apmokėjimo būdus. Apmokėjimo logiką (pirkėjo nukreipimą į reikiamą tarnybą, mokesčio priėmimą ir pan.) turi įgyvendinti nurodyta klasė.<br/>Apmokėjimo būdams parametras „klasė“ privalomas.';
$_lang['ms_delivery'] = 'Pristatymas';
$_lang['ms_deliveries'] = 'Pristatymo būdai';
$_lang['ms_deliveries_intro'] = 'Galimi pristatymo būdai. Kainos paskaičiavimo priklausomai nuo svorio ir atstumo logiką turi įgyvendinti nurodyta klasė.<br/>Nenuordžius klasės, bus naudojamas numatytasis kainos skaičiavimo algoritmas.';
$_lang['ms_statuses'] = 'Būsenos';
$_lang['ms_statuses_intro'] = 'Keturios numatytosios užsakymo būsenos – „naujas“, „apmokėtas“, „išsiųstas“ ir „atšauktas“ – yra privalomos. Jas galima konfigūruoti, tačiau ne šalinti, nes jos būtinos korektiškam parduotuvės veikimui. Sudėtingesnei užsakymų apdorojimo logikai įgyvendinti galite aprašyti papildomas užsakymo būsenas.<br/>Jeigu būsena pažymėta kaip galutinė, jos nebus galima perjungti į kitą (pavyzdžiui, galutinės būsenos yra „išsiųstas“ ir „atšauktas“). Jei būsena pažymėta kaip fiksuojanti, jos nebus galima perjungti į ankstesnę būseną (pavyzdžiui, būsenos „apmokėtas“ negalima keisti į „naujas“).';
$_lang['ms_vendors'] = 'Prekių gamintojai';
$_lang['ms_vendors_intro'] = 'Galimi prekių gamintojai arba tiekėjai. Kuriant prekę, jos „gamintojo“ lauke galima pasirinkti vieną čia įvestų variantų.';
$_lang['ms_link'] = 'Prekių sąryšis';
$_lang['ms_links'] = 'Prekių sąryšiai';
$_lang['ms_links_intro'] = 'Galimų prekių tarpusavio susiejimo būdų sąrašas. Sąryšio tipas nurodo, kaip būtent sąryšis veiks. Naujų sąryšio tipų kurti negalima, privaloma rinktis vieną iš esamų.';
$_lang['ms_customer'] = 'Pirkėjas';
$_lang['ms_all'] = 'Visi';
$_lang['ms_type'] = 'Tipas';

$_lang['ms_btn_create'] = 'Kurti';
$_lang['ms_btn_save'] = 'Įrašyti';
$_lang['ms_btn_edit'] = 'Taisyti';
$_lang['ms_btn_view'] = 'Žiūrėti';
$_lang['ms_btn_delete'] = 'Šalinti';
$_lang['ms_btn_undelete'] = 'Atstatyti pašalintą';
$_lang['ms_btn_publish'] = 'Publikuoti';
$_lang['ms_btn_unpublish'] = 'Nebepublikuoti';
$_lang['ms_btn_cancel'] = 'Atsisakyti';
$_lang['ms_btn_back'] = 'Aukštyn (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Ankstesnis (alt + &larr;)';
$_lang['ms_btn_next'] = 'Kitas (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Pagalba';
$_lang['ms_btn_duplicate'] = 'Kurti dublikatą';

$_lang['ms_actions'] = 'Veiksmai';
$_lang['ms_search'] = 'Ieškoti';
$_lang['ms_search_clear'] = 'Valyti';

$_lang['ms_category'] = 'Prekių kategorija';
$_lang['ms_category_tree'] = 'Kategorijų medis';
$_lang['ms_category_type'] = 'Prekių kategorija';
$_lang['ms_category_create'] = 'Pridėti kategoriją';
$_lang['ms_category_create_here'] = 'Parduotuvės prekių kategorija';
$_lang['ms_category_manage'] = 'Tvarkyti kategoriją';
$_lang['ms_category_duplicate'] = 'Kopijuoti kategoriją';
$_lang['ms_category_publish'] = 'Publikuoti kategoriją';
$_lang['ms_category_unpublish'] = 'Nebepublikuoti kategorijos';
$_lang['ms_category_delete'] = 'Šalinti kategoriją';
$_lang['ms_category_undelete'] = 'Atstatyti pašalintą kategoriją';
$_lang['ms_category_view'] = 'Rodyti svetainėje';
$_lang['ms_category_new'] = 'Nauja kategorija';

$_lang['ms_product'] = 'Parduotuvės prekė';
$_lang['ms_product_type'] = 'Parduotuvės prekė';
$_lang['ms_product_create_here'] = 'Šios kategorijos prekė';
$_lang['ms_product_create'] = 'Pridėti prekę';

$_lang['ms_frontend_currency'] = 'Lt';
$_lang['ms_frontend_weight_unit'] = 'kg';
$_lang['ms_frontend_count_unit'] = 'vnt.';
$_lang['ms_frontend_add_to_cart'] = 'Į krepšelį';
$_lang['ms_frontend_tags'] = 'Gairės';
$_lang['ms_frontend_colors'] = 'Spalvos';
$_lang['ms_frontend_color'] = 'Spalva';
$_lang['ms_frontend_sizes'] = 'Dydžiai';
$_lang['ms_frontend_size'] = 'Dydis';
$_lang['ms_frontend_popular'] = 'Populiari';
$_lang['ms_frontend_favorite'] = 'Rekomenduojama';
$_lang['ms_frontend_new'] = 'Naujiena';
$_lang['ms_frontend_deliveries'] = 'Pristatymo būdai';
$_lang['ms_frontend_payments'] = 'Apmokėjimo būdai';
$_lang['ms_frontend_delivery_select'] = 'Pasirinkite pristatymo būdą';
$_lang['ms_frontend_payment_select'] = 'Pasirinkite apmokėjimo būdą';
$_lang['ms_frontend_credentials'] = 'Pristatymo informacija';
$_lang['ms_frontend_address'] = 'Addresas';

$_lang['ms_frontend_comment'] = 'Komentaras';
$_lang['ms_frontend_receiver'] = 'Gavėjas';
$_lang['ms_frontend_email'] = 'El. paštas';
$_lang['ms_frontend_phone'] = 'Telefonas';
$_lang['ms_frontend_index'] = 'Pašto kodas';
$_lang['ms_frontend_country'] = 'Šalis';
$_lang['ms_frontend_region'] = 'Valstija / rajonas';
$_lang['ms_frontend_city'] = 'Miestas';
$_lang['ms_frontend_street'] = 'Gatvė';
$_lang['ms_frontend_building'] = 'Namas';
$_lang['ms_frontend_room'] = 'Butas';

$_lang['ms_frontend_order_cost'] = 'Galutinė kaina';
$_lang['ms_frontend_order_submit'] = 'Užsakyti!';
$_lang['ms_frontend_order_cancel'] = 'Valyti formą';
$_lang['ms_frontend_order_success'] = 'Dėkojame už užsakymą <b>Nr. [[+num]]</b>, atliktą mūsų svetainėje <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'užverti viską';
$_lang['ms_err_unknown'] = 'Nežinoma klaida';
$_lang['ms_err_ns'] = 'Šis laukas privalomas';
$_lang['ms_err_ae'] = 'Šio lauko reikšmė turi būti unikali';
$_lang['ms_err_json'] = 'Šio lauko reikšmė turi būti JSON eilutė';
$_lang['ms_err_user_nf'] = 'Utente non trovato.';
$_lang['ms_err_order_nf'] = 'Nurodytas užsakymas nerastas.';
$_lang['ms_err_status_nf'] = 'Nurodyta būsena nerasta.';
$_lang['ms_err_delivery_nf'] = 'Nurodytas pristatymo būdas nerastas.';
$_lang['ms_err_payment_nf'] = 'Nurodytas apmokėjimo būdas nerastas.';
$_lang['ms_err_status_final'] = 'Užsakymui nustatyta galutinė būsena, jos keisti negalima.';
$_lang['ms_err_status_fixed'] = 'Užsakymui nustatyta fiksuojanti būsena. Jos negalima keisti ankstesne.';
$_lang['ms_err_status_wrong'] = 'Netinkama užsakymo būsena.';
$_lang['ms_err_status_same'] = 'Ši būsena jau nustatyta.';
$_lang['ms_err_register_globals'] = 'Klaida: php parametras <b>register_globals</b> turi būti išjungtas.';
$_lang['ms_err_link_equal'] = 'Klaida: bandoma produktą susieti su juo pačiu';

$_lang['ms_err_gallery_save'] = 'Failo įrašyti nepavyko';
$_lang['ms_err_gallery_ns'] = 'Failo perskaityti nepavyko';
$_lang['ms_err_gallery_ext'] = 'Netinkamas failo prievardis';
$_lang['ms_err_gallery_thumb'] = 'Nepavyko sugeneruoti miniatiūrų. Žr. sistemos žurnalą.';
$_lang['ms_err_gallery_exists'] = 'Toks paveikslas jau yra prekių galerijoje.';

$_lang['ms_email_subject_new_user'] = 'Jūs atlikote užsakymą Nr. [[+num]] svetainėje [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'Atliktas naujas užsakymas Nr. [[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Jūs apmokėjote užsakymą Nr. [[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'Užsakymas Nr. [[+num]] apmokėtas';
$_lang['ms_email_subject_sent_user'] = 'Jūsų užsakymas Nr. [[+num]] išsiųstas';
$_lang['ms_email_subject_cancelled_user'] = 'Jūsų užsakymas Nr. [[+num]] atšauktas';

$_lang['ms_payment_link'] = 'Jeigu apmokėjimą atšaukėte netyčia, visuomet galite <a href="[[+link]]">pratęsti, spustelėdami šį saitą</a>.';
