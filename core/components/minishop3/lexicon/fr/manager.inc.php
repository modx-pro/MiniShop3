<?php

/**
 * Manager French Lexicon Entries for MiniShop3
 *
 * @package MiniShop3
 * @subpackage lexicon
 */

$_lang['ms_menu_create'] = 'Créer';
$_lang['ms_menu_update'] = 'Mettre à jour';
$_lang['ms_menu_remove'] = 'Supprimer';
$_lang['ms_menu_remove_multiple'] = 'Supprimer la sélection';
$_lang['ms_menu_remove_confirm'] = 'Êtes vous sûr de vouloir supprimer la sélection ?';
$_lang['ms_menu_remove_multiple_confirm'] = 'Êtes vous sûr de vouloir supprimer toutes les entrées sélectionnées ?';

$_lang['ms_combo_select'] = 'Cliquer pour sélectionner';
$_lang['ms_combo_select_status'] = 'Filtrer par l\'état';

$_lang['ms_id'] = 'Id';
$_lang['ms_name'] = 'Nom';
$_lang['ms_color'] = 'Couleur';
$_lang['ms_country'] = 'Pays';
$_lang['ms_logo'] = 'Logo';
$_lang['ms_address'] = 'Adresse';
$_lang['ms_phone'] = 'Téléphone';
$_lang['ms_fax'] = 'Fax';
$_lang['ms_email'] = 'Courriel';
$_lang['ms_active'] = 'Actif';
$_lang['ms_class'] = 'Gestionnaire de classe';
$_lang['ms_description'] = 'Description';
$_lang['ms_num'] = 'Nombre';
$_lang['ms_status'] = 'États';
$_lang['ms_count'] = 'Compter';
$_lang['ms_cost'] = 'Coût';
$_lang['ms_order_cost'] = 'Montant de la commande';
$_lang['ms_cart_cost'] = 'Coût des produits';
$_lang['ms_delivery_cost'] = 'Coût de la livraison';
$_lang['ms_weight'] = 'Poids';
$_lang['ms_createdon'] = 'Créé le';
$_lang['ms_updatedon'] = 'Mis a jour le';
$_lang['ms_user'] = 'Utilisateur';
$_lang['ms_timestamp'] = 'Horodatage';
$_lang['ms_order_log'] = 'Journal de commande';
$_lang['ms_order_products'] = 'Articles';
$_lang['ms_action'] = 'Action';
$_lang['ms_entry'] = 'Écriture';
$_lang['ms_username'] = 'Nom de l\'utilisateur';
$_lang['ms_fullname'] = 'Nom complet';
$_lang['ms_resource'] = 'Ressource';

$_lang['ms_receiver'] = 'Destinnataire';
$_lang['ms_index'] = 'Code postal';
$_lang['ms_region'] = 'Région';
$_lang['ms_city'] = 'Ville';
$_lang['ms_metro'] = 'Métro';
$_lang['ms_street'] = 'Rue';
$_lang['ms_building'] = 'Immeuble';
$_lang['ms_room'] = 'Appartement';
$_lang['ms_comment'] = 'Commentaire';

$_lang['ms_email_user'] = 'Message à l\'utilisateur';
$_lang['ms_email_manager'] = 'Message au responsable';
$_lang['ms_subject_user'] = 'Sujet du message pour l\'utilisateur';
$_lang['ms_subject_manager'] = 'Sujet du message pour le responsable';
$_lang['ms_body_user'] = 'Partie du message pour l\'utilisateur';
$_lang['ms_body_manager'] = 'Partie du message au responsable';
$_lang['ms_status_final'] = 'Finale';
$_lang['ms_status_final_help'] = '';
$_lang['ms_status_fixed'] = 'Correction d\'';
$_lang['ms_status_fixed_help'] = '';
$_lang['ms_options'] = 'Options';
$_lang['ms_price'] = 'Prix';
$_lang['ms_price_help'] = 'Coût minimal de la livraison';
$_lang['ms_weight_price'] = 'Prix par unité de poids';
$_lang['ms_weight_price_help'] = 'Coût supplémentaire par unité de poids.<br/>Peut être utilisé dans des classes personnalisées.';
$_lang['ms_distance_price'] = 'Prix par unité de distance';
$_lang['ms_distance_price_help'] = 'Coût supplémentaire par unité de distance.<br/>Peut être utilisé dans des classes personnalisées.';
$_lang['ms_order_requires'] = 'Champs requis';
$_lang['ms_order_requires_help'] = 'Lors de la commande, une classe personnalisée peut exiger le remplissage d\'un de ses champs';
$_lang['ms_orders_selected_status'] = 'Changer l\'état de la commande sélectionnée';
$_lang['ms_free_delivery_amount'] = 'Livraison gratuite sur le montant de la commande';
$_lang['ms_free_delivery_amount_help'] = 'Lorsque le montant de la commande atteint cette valeur, la livraison sera gratuite. Si la classe d\'expédition a été modifiée et / ou si vous avez installé des composants susceptibles d\'affecter la valeur de la commande, ce champ peut ne pas être pris en compte';

$_lang['ms_link_name'] = 'Nom du lien';
$_lang['ms_link_one_to_one'] = 'Un pour un';
$_lang['ms_link_one_to_one_desc'] = 'Liaison d\égalité de 2 articles. Si vous voulez lier plus de 2 articles, vous devez utiliser le type de relation "Plusieurs à plusieurs".';
$_lang['ms_link_one_to_many'] = 'Un à plusieurs';
$_lang['ms_link_one_to_many_desc'] = 'Liaison d\'un article maitre avec ses esclaves. Par exemple, un article est un ensemble d\'autres articles. Bien adapté aussi pour préciser des articles recommandés.';
$_lang['ms_link_many_to_one'] = 'Plusieurs à un';
$_lang['ms_link_many_to_one_desc'] = 'Liaison des articles esclaves vers l\'article maitre, les exclaves non aucun lien entre eux. Par exemple, les articles inclus dans un "kit".';
$_lang['ms_link_many_to_many'] = 'Plusieurs à plusieurs';
$_lang['ms_link_many_to_many_desc'] = 'Liaison d\'égalité entre plusieurs articles. Tous les articles sont liés entre eux. L\'ajout d\'une nouvelle liaison a un article, implique l\'ajout de cette liaison à tous les autres. Par exemple un paramètre de couleur, de taille, de langue, de version, etc.';
$_lang['ms_link_master'] = 'Article maitre';
$_lang['ms_link_slave'] = 'Article esclave';
