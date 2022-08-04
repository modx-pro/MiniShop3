<?php

/**
 * Default French Lexicon Entries for MiniShop3
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
$_lang['ms_menu_desc'] = 'Impressionnante extension de e-commerce';
$_lang['ms_order'] = 'Commande';
$_lang['ms_orders'] = 'Commandes';
$_lang['ms_orders_intro'] = 'Gestion de vos commandes';
$_lang['ms_orders_desc'] = 'Gestion de vos commandes';
$_lang['ms_settings'] = 'Paramètres';
$_lang['ms_settings_intro'] = 'Paramètres principaux du magasin. Ici vous pouvez définir les moyens de paiements, les méthodes de livraisons et l\'état des commandes';
$_lang['ms_settings_desc'] = 'États des commandes, options de paiements et de livraisons';
$_lang['ms_payment'] = 'Paiement';
$_lang['ms_payments'] = 'Paiements';
$_lang['ms_payments_intro'] = 'Vous pouvez créer n\'importe quel type de paiements. La logique de paiement (redirection de l\'acheteur sur un service distant, la réception du paiement, etc.) est mis en oeuvre dans la "classe" que vous indiquez.<br/>Pour les méthodes de paiements le paramètre "classe" est nécessaire.';
$_lang['ms_delivery'] = 'Livraison';
$_lang['ms_deliveries'] = 'Options de livraisons';
$_lang['ms_deliveries_intro'] = 'Options possibles pour la livraison. Définit la logique du calcul des coûts d\'expédition en fonction de la distance et de la catégorie de poids.<br/> Si vous ne spécifiez pas de classe, les calculs seront effectués par l\'algorithme par défaut.';
$_lang['ms_statuses'] = 'États';
$_lang['ms_statuses_intro'] = 'Il y a plusieurs états obligatoires dans une commande: "nouveau", "payée", "envoyée" et "annulée". Ils peuvent être modifiés mais pas enlevés car ils sont indispensables au fonctionnement du magasin. Vous pouvez définir vos propre états pour étendre la logique de travail avec les commandes.<br/>Un état peut être "final", cela signifie que vous ne pourrez plus le modifier pour un autre, par exemple, "envoyé" et "annulé". Un état peut être fixé, c\'est à dire qu\'une fois positionné vous ne pourrez pas revenir a un état précédent, un état "payé" ne peut pas être remis sur "nouveau".';
$_lang['ms_vendors'] = 'Fournisseurs des articles';
$_lang['ms_vendors_intro'] = '';
$_lang['ms_link'] = 'Lien de produits';
$_lang['ms_links'] = 'Lien de produits';
$_lang['ms_links_intro'] = 'Liste des liens possible de produits entre eux. Le type de connexion décrit exactement comment il va fonctionner, il n\'est pas possible d\'en créer de nouveau, vous pouvez seulement le sélectionner dans la liste.';
$_lang['ms_customer'] = 'Clients';
$_lang['ms_all'] = 'Tout';
$_lang['ms_type'] = 'Type';

$_lang['ms_btn_create'] = 'Création';
$_lang['ms_btn_save'] = 'Enregistrer';
$_lang['ms_btn_edit'] = 'Modifier';
$_lang['ms_btn_view'] = 'Voir';
$_lang['ms_btn_delete'] = 'Supprimer';
$_lang['ms_btn_undelete'] = 'Récupérer';
$_lang['ms_btn_publish'] = 'Publier';
$_lang['ms_btn_unpublish'] = 'dépublier';
$_lang['ms_btn_cancel'] = 'Annuler';
$_lang['ms_btn_back'] = 'Retour (alt + &uarr;)';
$_lang['ms_btn_prev'] = 'Précédent (alt + &larr;)';
$_lang['ms_btn_next'] = 'Suivant (alt + &rarr;)';
$_lang['ms_btn_help'] = 'Aide';
$_lang['ms_btn_duplicate'] = 'Dupliquer un article';

$_lang['ms_actions'] = 'Actions';
$_lang['ms_search'] = 'Chercher';
$_lang['ms_search_clear'] = 'Éffacer';

$_lang['ms_category'] = 'Catégorie des articles';
$_lang['ms_category_tree'] = 'Arbre des catégories';
$_lang['ms_category_type'] = 'Catégorie des articles';
$_lang['ms_category_create'] = 'Ajout de catégorie';
$_lang['ms_category_create_here'] = 'Catégorie ayant des articles';
$_lang['ms_category_manage'] = 'Gestion des catégories';
$_lang['ms_category_duplicate'] = 'Copie la catégorie';
$_lang['ms_category_publish'] = 'Publie la catégorie';
$_lang['ms_category_unpublish'] = 'Dépublie la catégorie';
$_lang['ms_category_delete'] = 'Supprime la catégorie';
$_lang['ms_category_undelete'] = 'Restaure la catégorie';
$_lang['ms_category_view'] = 'Mettre en ligne';
$_lang['ms_category_new'] = 'Nouvelle catégorie';

$_lang['ms_product'] = 'Article du magasin';
$_lang['ms_product_type'] = 'Article du magasin';
$_lang['ms_product_create_here'] = 'Article de la catégorie';
$_lang['ms_product_create'] = 'Ajout d\'article';

$_lang['ms_frontend_currency'] = 'EUR';
$_lang['ms_frontend_weight_unit'] = 'kg';
$_lang['ms_frontend_count_unit'] = 'pcs';
$_lang['ms_frontend_add_to_cart'] = 'Ajout au panier';
$_lang['ms_frontend_tags'] = 'Étiquettes';
$_lang['ms_frontend_colors'] = 'Couleurs';
$_lang['ms_frontend_color'] = 'Couleur';
$_lang['ms_frontend_sizes'] = 'Tailles';
$_lang['ms_frontend_size'] = 'Taille';
$_lang['ms_frontend_popular'] = 'Populaire';
$_lang['ms_frontend_favorite'] = 'Favori';
$_lang['ms_frontend_new'] = 'Nouveau';
$_lang['ms_frontend_deliveries'] = 'Livraisons';
$_lang['ms_frontend_payments'] = 'Paiements';
$_lang['ms_frontend_delivery_select'] = 'Choisissez une livraison';
$_lang['ms_frontend_payment_select'] = 'Choisisez un moyen de paiement';
$_lang['ms_frontend_credentials'] = 'Identités';
$_lang['ms_frontend_address'] = 'Adresse';

$_lang['ms_frontend_comment'] = 'Commentaire';
$_lang['ms_frontend_receiver'] = 'Destinataire';
$_lang['ms_frontend_email'] = 'Courriel';
$_lang['ms_frontend_phone'] = 'Téléphone';
$_lang['ms_frontend_index'] = 'Code postal';
$_lang['ms_frontend_country'] = 'Pays';
$_lang['ms_frontend_region'] = 'Département';
$_lang['ms_frontend_city'] = 'Ville';
$_lang['ms_frontend_street'] = 'Rue';
$_lang['ms_frontend_building'] = 'Immeuble';
$_lang['ms_frontend_room'] = 'Porte';

$_lang['ms_frontend_order_cost'] = 'Coût total';
$_lang['ms_frontend_order_submit'] = 'Paiement!';
$_lang['ms_frontend_order_cancel'] = 'RaZ du formulaire';
$_lang['ms_frontend_order_success'] = 'Merci pour votre commande <b>#[[+num]]</b> sur notre site <b>[[++site_name]]</b>!';

$_lang['ms_message_close_all'] = 'fermer tout';
$_lang['ms_err_unknown'] = 'Erreur non référencée';
$_lang['ms_err_ns'] = 'Ce champs est requis';
$_lang['ms_err_ae'] = 'Ce champs doit être unique';
$_lang['ms_err_user_nf'] = 'Utilisateur introuvable.';
$_lang['ms_err_order_nf'] = 'Aucune commande avec cet ID n\'a été trouvée.';
$_lang['ms_err_status_nf'] = 'Aucun état avec cetID n\'a été trouvé.';
$_lang['ms_err_delivery_nf'] = 'Aucune livraison avec cet ID n\'a été trouvée.';
$_lang['ms_err_payment_nf'] = 'Aucun paiement avec cet ID n\' été trouvé.';
$_lang['ms_err_status_final'] = 'L\'état Terminé est posistionné, vous ne pouvez pas le modifier.';
$_lang['ms_err_status_fixed'] = 'L\'état Vérouillé est positionné, vous ne pouvez pas revenir à un état précedant.';
$_lang['ms_err_status_same'] = 'Cet état est déjà positionné.';
$_lang['ms_err_register_globals'] = 'Erreur : le paramètre PHP <b>register_globals</b> doit être off.';
$_lang['ms_err_link_equal'] = 'Vous essayez d\'ajouter un lien de produit à lui-même';

$_lang['ms_email_subject_new_user'] = 'Vous avez passé la commande n°[[+num]] sur le site [[++site_name]]';
$_lang['ms_email_subject_new_manager'] = 'Vous avez une nouvelle commande n°[[+num]]';
$_lang['ms_email_subject_paid_user'] = 'Vous avez payé la commande n°[[+num]]';
$_lang['ms_email_subject_paid_manager'] = 'La commande n°[[+num]] a été payée';
$_lang['ms_email_subject_sent_user'] = 'Votre commande n°[[+num]] a été expédiée';
$_lang['ms_email_subject_cancelled_user'] = 'Votre commande n°[[+num]] a été annulée';
