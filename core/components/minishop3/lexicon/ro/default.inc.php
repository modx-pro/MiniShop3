<?php

/**
 * Romanian Lexicon Entries for MiniShop3
 * translated by Anna
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
$_lang['ms_menu_desc'] = 'Magazin online avansat';
$_lang['ms_order'] = 'Comandă';
$_lang['ms_orders'] = 'Comenzi';
$_lang['ms_orders_intro'] = 'Panou de gestionare a comenzilor. Puteți selecta mai multe comenzi simultan prin Shift sau Ctrl (Cmd).';
$_lang['ms_orders_desc'] = 'Gestionarea proiectelor';
$_lang['ms_settings'] = 'Setări';
$_lang['ms_settings_intro'] = 'Panou de gestionare a setărilor magazinului. Aici puteți specifica metodele de achitare, livrare și starea comenzilor.';
$_lang['ms_settings_desc'] = 'Starea comenzilor, opțiunile de plată și de livrare';
$_lang['ms_payment'] = 'Achitare';
$_lang['ms_payments'] = 'Modalități de achitare';
$_lang['ms_payments_intro'] = 'Puteți crea orice metode de achitare a comenzilor. Logica achitării (direcționarea cumpărătorului către serviciu la distanță, primirea plății etc. ) este implementată în clasa pe care o specificați. <br/> Pentru metodele de plată, parametrul "clasă" este obligatoriu. ';
$_lang['ms_delivery'] = 'Livrare';
$_lang['ms_deliveries'] = 'Opțiuni de livrare';
$_lang['ms_deliveries_intro'] = 'Posibile opțiuni de livrare. Logica calculării costului livrării în funcție de distanță și greutate este implementată de clasa pe care o specificați în setări. <br/> Dacă nu precizați clasa, calculele vor fi efectuate prin algoritmul implicit.';
$_lang['ms_statuses'] = 'Starea comenzii';
$_lang['ms_statuses_intro'] = 'Există mai multe stări de comandă obligatorii: "nou", "achitat", "expediat" și "anulat". Ele pot fi editate, dar nu pot fi șterse, deoarece sunt necesare pentru funcționarea magazinului. Puteți specifica personal stările pentru logica avansată de lucru cu comenzi. <br/> Starea poate fi finală, ceea ce înseamnă că nu poate fi schimbată cu alta, de exemplu "expediat" și "anulat". Starea poate fi fixată, adică nu poate fi trecută la starea anterioară, de exemplu, "achitat" nu poate fi trecut la "nou" ".';
$_lang['ms_vendors'] = 'Producătorii produselor';
$_lang['ms_vendors_intro'] = 'Lista posibililor producători de produse. Ceea ce adăugați aici, puteți alege în cîmpul "vendor" al produsului.';
$_lang['ms_link'] = 'Legătura produselor';
$_lang['ms_links'] = 'Legăturile produselor';
$_lang['ms_links_intro'] = 'Lista posibilelor legături ale mărfurilor între ele. Tipul de legătură caracterizează modul în care va funcționa, el nu poate fi creat, numai ales din listă.';
$_lang['ms_option'] = 'Proprietatea produsului';
$_lang['ms_options'] = 'Proprietățile produselor';
$_lang['ms_options_intro'] = 'Lista posibilelor proprietăți ale produselor. Arborele de categorii este utilizat pentru a filtra proprietățile categoriilor selectate. <br/> Pentru a atribui categoriilor mai multe opțiuni in același timp, selectați-le cu Ctrl (Cmd) sau Shift.';
$_lang['ms_options_category_intro'] = 'Lista posibilelor proprietăți ale produselor din aceasta categorie.';
$_lang['ms_default_value'] = 'Valoare implicită';
$_lang['ms_customer'] = 'Cumpărătorul';
$_lang['ms_all'] = 'Toți';
$_lang['ms_type'] = 'Tip';

$_lang['ms_btn_create'] = 'A crea';
$_lang['ms_btn_copy'] = 'A copia';
$_lang['ms_btn_save'] = 'A salva';
$_lang['ms_btn_edit'] = 'A edita';
$_lang['ms_btn_view'] = 'A vizualiza';
$_lang['ms_btn_delete'] = 'A șterge';
$_lang['ms_btn_undelete'] = 'A restabili';
$_lang['ms_btn_publish'] = 'A activa';
$_lang['ms_btn_unpublish'] = 'A dezactiva';
$_lang['ms_btn_cancel'] = 'A anula';
$_lang['ms_btn_back'] = '{Înapoi} (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Produsul precedent (alt + &larr;)';
$_lang['ms_btn_next'] = 'Produsul următor (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Ajutor';
$_lang['ms_btn_duplicate'] = 'A duplica produsul';
$_lang['ms_btn_addoption'] = 'A adăuga';
$_lang['ms_btn_assign'] = 'A atribui';

$_lang['ms_actions'] = 'Acțiuni';
$_lang['ms_search'] = 'Căutare';
$_lang['ms_search_clear'] = 'A șterge';

$_lang['ms_category'] = 'Categoria produselor';
$_lang['ms_category_tree'] = 'Arborele categoriilor';
$_lang['ms_category_type'] = 'Categoria produselor';
$_lang['ms_category_create'] = 'A adăuga categoria';
$_lang['ms_category_create_here'] = 'Categoria cu produse';
$_lang['ms_category_manage'] = 'Gestionarea produselor';
$_lang['ms_category_duplicate'] = 'A copia categoria';
$_lang['ms_category_publish'] = 'A publica categoria';
$_lang['ms_category_unpublish'] = 'A șterge categoria din publicații';
$_lang['ms_category_delete'] = 'A șterge categoria';
$_lang['ms_category_undelete'] = 'A restabili categoria';
$_lang['ms_category_view'] = 'A privi pe site';
$_lang['ms_category_new'] = 'Categoria nouă';
$_lang['ms_category_option_add'] = 'A adăuga categoria';
$_lang['ms_category_option_rank'] = 'Ordinea sortării';
$_lang['ms_category_show_nested'] = 'A afișa produsele incluse';

$_lang['ms_product'] = 'Produs';
$_lang['ms_product_type'] = 'Produs';
$_lang['ms_product_create_here'] = 'Produs';
$_lang['ms_product_create'] = 'A adăuga produs';

$_lang['ms_option_type'] = 'Tipul caracteristicilor';

$_lang['ms_frontend_currency'] = 'RUB';
$_lang['ms_frontend_weight_unit'] = 'kg.';
$_lang['ms_frontend_count_unit'] = 'bucăți';
$_lang['ms_frontend_add_to_cart'] = 'A adăuga în coș';
$_lang['ms_frontend_tags'] = 'Tagg-uri';
$_lang['ms_frontend_colors'] = 'Culori';
$_lang['ms_frontend_color'] = 'Culoare';
$_lang['ms_frontend_sizes'] = 'Dimensiuni';
$_lang['ms_frontend_size'] = 'Dimensiune';
$_lang['ms_frontend_popular'] = 'Produs popular';
$_lang['ms_frontend_favorite'] = 'Recomandăm';
$_lang['ms_frontend_new'] = 'Nou';
$_lang['ms_frontend_deliveries'] = 'Opțiuni de livrare';
$_lang['ms_frontend_delivery'] = 'Livrare';
$_lang['ms_frontend_payments'] = 'Metode de achitare';
$_lang['ms_frontend_payment'] = 'Achitare';
$_lang['ms_frontend_delivery_select'] = 'Selectați livrarea';
$_lang['ms_frontend_payment_select'] = 'Selectați achitarea';
$_lang['ms_frontend_credentials'] = 'Datele destinatarului';
$_lang['ms_frontend_address'] = 'Adresa livrării';

$_lang['ms_frontend_comment'] = 'Comentariu';
$_lang['ms_frontend_receiver'] = 'Destinatar';
$_lang['ms_frontend_email'] = 'Email';
$_lang['ms_frontend_phone'] = 'Telefon';
$_lang['ms_frontend_index'] = 'Codul poștal';
$_lang['ms_frontend_country'] = 'Țară';
$_lang['ms_frontend_region'] = 'Regiune';
$_lang['ms_frontend_city'] = 'Oraș';
$_lang['ms_frontend_street'] = 'Stradă';
$_lang['ms_frontend_building'] = 'Casa';
$_lang['ms_frontend_room'] = 'Apartament';

$_lang['ms_frontend_order_cost'] = 'Total, cu livrare';
$_lang['ms_frontend_order_submit'] = 'A face comanda!';
$_lang['ms_frontend_order_cancel'] = 'A șterge formularul';
$_lang['ms_frontend_order_success'] = 'Mulțumim pentru efectuarea comenzii <b>#[[+num]]</b> pe site-ul nostru <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'a închide totul';
$_lang['ms_err_unknown'] = 'Eroare necunoscută';
$_lang['ms_err_ns'] = 'Acest câmp este obligatoriu';
$_lang['ms_err_ae'] = 'Acest câmp trebuie să fie unic';
$_lang['ms_err_json'] = 'Acest câmp necesită șir JSON';

$_lang['ms_err_user_nf'] = 'Utilizatorul nu a fost găsit.';
$_lang['ms_err_order_nf'] = 'Comanda cu acest identificator nu a fost găsită.';
$_lang['ms_err_status_nf'] = 'Statut cu acest identificator nu a fost găsit.';
$_lang['ms_err_delivery_nf'] = 'Metoda de livrare cu acest identificator nu a fost găsită.';
$_lang['ms_err_payment_nf'] = 'Metoda de achitare cu acest identificator nu a fost găsită.';
$_lang['ms_err_status_final'] = 'A fost instalat statutul final. Nu poate fi schimbat.';
$_lang['ms_err_status_fixed'] = 'A fost instalat statutul de fixare. Nu poate fi schimbat la varianta anterioară.';
$_lang['ms_err_status_wrong'] = 'Statutul comenzii este incorect.';
$_lang['ms_err_status_same'] = 'Acest statut este deja instalat.';
$_lang['ms_err_register_globals'] = 'Eroare: parametru php <b>register_globals</b> trebuie să fie dezactivat.';
$_lang['ms_err_link_equal'] = 'Incercați să adăugați același link al produsului';
$_lang['ms_err_value_duplicate'] = 'Nu ați introdus o valoare sau ați introdus o repetare.';

$_lang['ms_err_gallery_save'] = 'File-ul nu a fost salvat (vezi sistem log).';
$_lang['ms_err_gallery_ns'] = 'Fișierul expediat este gol';
$_lang['ms_err_gallery_ext'] = 'Format de fișier nevalid';
$_lang['ms_err_gallery_exists'] = 'Această imagine deja este în galeria produsului.';
$_lang['ms_err_gallery_thumb'] = 'Nu s-au putut genera miniaturi. Priviți view log.';
$_lang['ms_err_wrong_image'] = 'Fișierul nu este o imagine validă.';

$_lang['ms_email_subject_new_user'] = 'Ați făcut comanda #[[+num]] pe site-ul [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'Aveți comanda nouă #[[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Ați achitat comanda #[[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'Comanda #[[+num]] a fost achitată';
$_lang['ms_email_subject_sent_user'] = 'Comanda Dvs #[[+num]] a fost expediată';
$_lang['ms_email_subject_cancelled_user'] = 'Comanda Dvs #[[+num]] a fost anulată';

$_lang['ms_payment_link'] = 'Dacă ați întrerupt accidental procedura de plată, puteți să o continuați oricînd <a href="[[+link]]" style="color:#348eda;"> urmînd acest link</a>.';

$_lang['ms_category_err_ns'] = 'Categoria nu a fost selectată';
$_lang['ms_option_err_ns'] = 'Opțiunea nu a fost selectată';
$_lang['ms_option_err_nf'] = 'Opțiunea nu a fost găsită';
$_lang['ms_option_err_ae'] = 'Opțiunea deja există';
$_lang['ms_option_err_save'] = 'Eroare la salvarea proprietății';
$_lang['ms_option_err_reserved_key'] = 'Această cheie de opțiune nu poate fi utilizată';
$_lang['ms_option_err_invalid_key'] = 'Cheia de opțiune incorectă';
