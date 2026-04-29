<?php
if (!defined('WHMCS')) { die('Accès direct interdit.'); }
/**
 * SmarterMail WHMCS Module — Fichier de langue : Français
 *
 * Chargé automatiquement par _sm_lang() selon la langue du client ou
 * la langue par défaut du système WHMCS.
 *
 * Convention de nommage des clés :
 *   section_description   ex: btn_save, err_invalid_email, lbl_domain
 */

$_lang = [

    // ── Général ───────────────────────────────────────────────────────────
    'back_dashboard'       => 'Retour au tableau de bord',
    'back'                 => 'Retour',
    'btn_save'             => 'Sauvegarder',
    'btn_cancel'           => 'Annuler',
    'btn_add'              => 'Ajouter',
    'btn_generate'         => 'Générer',
    'btn_manage'           => 'Gérer',
    'btn_delete'           => 'Supprimer',
    'btn_edit'             => 'Modifier',
    'btn_create'           => 'Créer',
    'btn_close'            => 'Fermer',
    'btn_copy'             => 'Copier',
    'required'             => 'Requis',
    'na'                   => '—',
    'unlimited'            => 'illimité',
    'never'                => 'Jamais',
    'per_month'            => '/mois',
    'zero_unlimited'       => '0 = illimité',

    // ── Statuts de service ────────────────────────────────────────────────
    'status_active'        => 'Actif',
    'status_suspended'     => 'Suspendu',
    'status_terminated'    => 'Résilié',
    'status_cancelled'     => 'Annulé',
    'status_pending'       => 'En attente',

    // ── Cycles de facturation ─────────────────────────────────────────────
    'cycle_monthly'        => 'Mensuel',
    'cycle_quarterly'      => 'Trimestriel',
    'cycle_semi_annually'  => 'Semi-annuel',
    'cycle_annually'       => 'Annuel',
    'cycle_biennially'     => 'Biennal',
    'cycle_triennially'    => 'Triennal',
    'cycle_free'           => 'Gratuit',
    'cycle_one_time'       => 'Unique',

    // ── Tableau de bord client (clientarea.tpl) ───────────────────────────
    'dash_storage_title'   => 'Utilisation de l\'espace courriel',
    'dash_storage_used'    => 'Utilisé',
    'dash_storage_billed'  => 'Facturé',
    'dash_storage_tiers'   => 'Tranche',
    'dash_storage_of'      => 'de',
    'dash_storage_go'      => 'Go',
    'dash_stats_title'     => 'Statistiques',
    'dash_service_title'   => 'Information du service',
    'dash_dkim_title'      => 'Clé DKIM',
    'dash_dkim_active'     => 'Active',
    'dash_dkim_inactive'   => 'Inactive',
    'dash_dkim_view'       => 'Voir l\'enregistrement à ajouter',

    'stat_email_accounts'  => 'Adresses courriel',
    'stat_aliases'         => 'Alias',
    'stat_eas'             => 'ActiveSync (EAS)',
    'stat_mapi'            => 'MAPI/Exchange',
    'stat_disk'            => 'Espace utilisé',

    'svc_status'           => 'État',
    'svc_reg_date'         => 'Depuis le',
    'svc_amount'           => 'Montant',
    'svc_cycle'            => 'Cycle',
    'svc_due_date'         => 'Prochaine échéance',
    'svc_payment'          => 'Mode de paiement',
    'svc_estimated'        => 'Estimation mensuelle',

    // ── Modes de paiement (libellés clients) ─────────────────────────────
    // Mappés depuis les slugs WHMCS de tblhosting.paymentmethod par
    // _sm_collectClientAreaData(). Ajouter/modifier ici pour personnaliser
    // l'affichage côté client sans toucher au code PHP.
    'pm_credit_card'       => 'Carte de crédit (Visa/Mastercard)',

    // Liste des comptes courriel
    'list_title'           => 'Comptes courriel',
    'list_add_btn'         => 'Ajouter',
    'list_col_email'       => 'Adresse courriel',
    'list_col_alias'       => 'Alias',
    'list_col_fwd'         => 'Redirection',
    'list_col_status'      => 'Boîte',
    'list_search_ph'       => 'Rechercher adresse, alias, redirection…',
    'list_per_page'        => '/ page',
    'list_results'         => 'résultat',
    'list_results_pl'      => 'résultats',
    'list_accounts'        => 'compte',
    'list_accounts_pl'     => 'comptes',
    'list_no_results'      => 'Aucun compte ne correspond à votre recherche.',
    'list_empty'           => 'Aucune adresse courriel pour ce domaine.',
    'list_create_first'    => 'Créer la première adresse',
    'list_sort_asc'        => 'Trier A→Z',
    'list_sort_desc'       => 'Trier Z→A',

    'mbox_active'          => 'Actif',
    'mbox_redirect'        => 'Non',
    'mbox_disabled'        => 'Désactivé',

    // ── Lien webmail (icône boîte courriel dans la grille des comptes) ──
    // Tooltip affiché au survol de l'icône fa-inbox qui sert de lien
    // vers l'interface webmail SmarterMail (nouvel onglet).
    'list_webmail_link'    => 'Ouvrir le webmail',

    // ── Bouton webmail (sous le bloc Statistiques) ──────────────────────
    // Libellé du bouton pleine largeur qui ouvre le webmail dans un
    // nouvel onglet. Affiché avec une icône fa-external-link.
    'btn_open_webmail'     => 'Ouvrir le webmail',

    // ── DKIM Modal ────────────────────────────────────────────────────────
    'dkim_modal_title'     => 'Clé DKIM',
    'dkim_desc'            => 'Ajoutez cet enregistrement DNS TXT chez votre registraire pour authentifier les courriels sortants.',
    'dkim_lbl_host'        => 'Nom de l\'enregistrement (Host / Name)',
    'dkim_lbl_value'       => 'Valeur de l\'enregistrement (TXT Value)',
    'dkim_info'            => 'Type&nbsp;: <strong>TXT</strong>&nbsp;|&nbsp;TTL&nbsp;: <strong>3600</strong>&nbsp;|&nbsp;La propagation DNS peut prendre jusqu\'à 48&nbsp;h.',

    // ── Gérer une adresse (edituser.tpl) ──────────────────────────────────
    'eu_usage_title'       => 'Utilisation de la boîte',
    'eu_protocols'         => 'Protocoles',
    'eu_last_login'        => 'Dernière connexion',
    'eu_used_mb'           => 'Mo utilisés',
    'eu_allocated_mb'      => 'Mo alloués',

    'eu_alias_title'       => 'Alias de cette adresse',
    'eu_alias_none'        => 'Aucun alias pour cette adresse.',
    'eu_alias_ph'          => 'nomalias',
    'eu_alias_err_chars'   => 'Caractères invalides. Utilisez lettres, chiffres, . - _',
    'eu_alias_err_dup'     => 'Cet alias est déjà dans la liste.',

    'eu_fwd_title'         => 'Redirection (Forwarding)',
    'eu_fwd_ph'            => 'destination@exemple.com',
    'eu_fwd_keep'          => 'Conserver l\'expéditeur et les destinataires d\'origine lors du transfert',
    'eu_fwd_delete'        => 'Supprimer après transfert',
    'eu_fwd_err_invalid'   => 'Adresse courriel invalide.',
    'eu_fwd_err_dup'       => 'Cette adresse est déjà dans la liste.',

    'eu_settings_title'    => 'Paramètres',
    'eu_display_name'      => 'Nom affiché',
    'eu_disk_limit'        => 'Limite d\'espace (Mo)',
    'eu_eas_label'         => 'ActiveSync (EAS)',
    'eu_mapi_label'        => 'MAPI/Exchange',
    'eu_per_box_month'     => '/boîte/mois',

    'eu_btn_pwd'           => 'Mot de passe',
    'eu_btn_save'          => 'Sauvegarder',
    'eu_btn_delete'        => 'Supprimer',

    // ── Modal : Mot de passe ──────────────────────────────────────────────
    'pwd_modal_title'      => 'Changer le mot de passe',
    'pwd_new_label'        => 'Nouveau mot de passe',
    'pwd_confirm_label'    => 'Confirmer',
    'pwd_show_hide'        => 'Afficher/masquer',
    'pwd_generate'         => 'Générer',
    'pwd_change_btn'       => 'Changer',
    'pwd_crit_min'         => 'Minimum %d caractères',
    'pwd_crit_upper'       => 'Au moins une lettre majuscule (A-Z)',
    'pwd_crit_number'      => 'Au moins un chiffre (0-9)',
    'pwd_crit_special'     => 'Au moins un caractère spécial (!@#$...)',
    'pwd_crit_match'       => 'Les mots de passe correspondent',

    // ── Modal : Suppression adresse ───────────────────────────────────────
    'del_modal_title'      => 'Supprimer l\'adresse courriel',
    'del_irreversible'     => 'Cette action est irréversible.',
    'del_all_deleted'      => 'Tous les courriels de %s seront définitivement supprimés du serveur.',
    'del_no_recovery'      => 'Il n\'existe aucune façon de récupérer ces données après la suppression.',
    'del_keep_btn'         => 'Annuler — Conserver la boîte',
    'del_confirm_btn'      => 'Supprimer définitivement',

    // ── Ajouter une adresse (adduser.tpl) ─────────────────────────────────
    'add_user_title'       => 'Nouvelle adresse courriel',
    'add_user_lbl_email'   => 'Adresse courriel',
    'add_user_lbl_name'    => 'Nom affiché',
    'add_user_lbl_pwd'     => 'Mot de passe',
    'add_user_lbl_size'    => 'Limite d\'espace disque (Mo)',
    'add_user_lbl_fwd'     => 'Redirection automatique (optionnel)',
    'add_user_lbl_opts'    => 'Options supplémentaires',
    'add_user_lbl_eas'     => 'Activer ActiveSync (EAS)',
    'add_user_lbl_mapi'    => 'Activer MAPI/Exchange',
    'add_user_btn'         => 'Créer l\'adresse courriel',
    'add_user_help_email'  => 'Lettres, chiffres, points, tirets et underscores seulement.',
    'add_user_help_fwd'    => 'Si renseigné, les courriels reçus seront également transférés à cette adresse.',
    'add_user_help_eas'    => 'Synchronisation des courriels, calendriers et contacts avec appareils mobiles.',
    'add_user_help_mapi'   => 'Connexion via Outlook (mode Exchange) avec synchronisation complète.',

    // ── Gérer les alias (managealiases.tpl) ───────────────────────────────
    'al_manage_title'      => 'Alias courriel',
    'al_add_btn'           => 'Ajouter un alias',
    'al_col_alias'         => 'Alias',
    'al_col_dest'          => 'Destinations',
    'al_col_actions'       => 'Actions',
    'al_btn_edit'          => 'Modifier',
    'al_btn_delete'        => 'Supprimer',
    'al_empty'             => 'Aucun alias pour ce domaine.',
    'al_empty_desc'        => 'Un alias permet de rediriger des courriels vers une ou plusieurs adresses.',
    'al_create_first'      => 'Créer le premier alias',
    'al_confirm_delete'    => 'Supprimer cet alias ?',

    // ── Ajouter/Modifier un alias (addalias.tpl / editalias.tpl) ─────────
    'al_add_title'         => 'Nouvel alias',
    'al_edit_title'        => 'Modifier l\'alias',
    'al_desc'              => 'Un alias permet de rediriger les courriels reçus vers une ou plusieurs adresses de destination.',
    'al_lbl_name'          => 'Nom de l\'alias',
    'al_lbl_display'       => 'Nom affiché',
    'al_lbl_targets'       => 'Adresses de destination',
    'al_lbl_options'       => 'Options',
    'al_opt_send'          => 'Autoriser l\'envoi depuis cet alias',
    'al_opt_hide_gal'      => 'Masquer de la liste d\'adresses globale (GAL)',
    'al_opt_internal'      => 'Accepter les courriels internes seulement',
    'al_help_targets'      => 'Une adresse par ligne. Peut inclure des adresses externes.',
    'al_btn_create'        => 'Créer l\'alias',
    'al_btn_save'          => 'Sauvegarder',

    // ── Erreur ────────────────────────────────────────────────────────────
    'err_title'            => 'Erreur',
    'err_default'          => 'Une erreur inattendue est survenue.',
    'err_connection'       => 'Le service courriel est temporairement indisponible. Veuillez réessayer plus tard ou contacter le soutien technique.',
    // Affiché lorsqu'une action mutative est reçue sans jeton CSRF valide
    // (formulaire posté depuis un site tiers ou session expirée).
    'err_csrf'             => 'Jeton de sécurité invalide ou expiré. Veuillez recharger la page et réessayer.',

    // ── Critères mot de passe (ajouts) ───────────────────────────────────
    'pwd_crit_no_user'     => 'Ne doit pas contenir le nom d\'utilisateur',
    'pwd_crit_no_domain'   => 'Ne doit pas contenir le nom de domaine',
    'pwd_not_set'          => 'Non défini',
    'pwd_btn_define'       => 'Définir',
    'pwd_btn_apply'        => 'Appliquer',
    'btn_show_pwd'         => 'Afficher',

    // ── Alias / Redirection ───────────────────────────────────────────────
    'eu_alias_empty'       => 'Aucun alias',
    'eu_fwd_empty'         => 'Aucune redirection',
    'eu_btn_add'           => 'Ajouter',

    // ── Tableau de bord — statistiques et DNS ─────────────────────────────
    'stat_combined'        => 'EAS + MAPI combiné',
    'stat_total_proto'     => 'Total protocoles',
    'dash_dns_title'       => 'Enregistrements DNS',
    'dash_spf_ok'          => 'Configuré',
    'dash_spf_err'         => 'Non configuré',
    'dash_spf_view'        => 'Voir le record',
    'dash_spf_add'         => 'Que configurer ?',
    'spf_mechanism_label'  => 'Mécanisme requis',
    'dkim_selector_label'  => 'Sélecteur',
    'list_col_size'        => 'Espace',
    'list_col_proto'       => 'Protocoles',

    // ── Service non activé ────────────────────────────────────────────────
    'domain_not_ready_title' => 'Service courriel non encore activé',
    'domain_not_ready_msg'   => 'Le domaine <strong>{$domain}</strong> n\'est pas encore configuré sur notre serveur de messagerie.<br>Cela peut survenir si votre service vient d\'être commandé ou si une configuration manuelle est requise de notre part.',
    'domain_not_ready_cta'   => 'Ouvrez un ticket de support',
    'domain_not_ready_sub'   => 'Notre équipe activera votre service dans les plus brefs délais.',

    // ── Modales info protocoles ───────────────────────────────────────────
    'info_eas_title'       => 'ActiveSync',
    'info_eas_desc'        => '<strong>ActiveSync (EAS)</strong> synchronise en temps réel vos <strong>courriels</strong>, <strong>contacts</strong>, <strong>calendriers</strong> et <strong>tâches</strong> vers vos appareils mobiles. Idéal pour iPhone et Android.',
    'info_mapi_title'      => 'MAPI / Exchange',
    'info_mapi_desc'       => '<strong>MAPI (Exchange)</strong> est le protocole natif de Microsoft Outlook. Il active le partage de <strong>calendriers</strong>, les <strong>invitations de réunion</strong>, les <strong>dossiers publics</strong> et la synchronisation complète des règles.',
    'btn_close_modal'      => 'Fermer',

    // ── Chaînes JS dynamiques (injectées via Smarty dans les scripts) ─────
    'js_price_bundle'      => 'Prix groupé EAS+MAPI',
    'js_price_saving'      => 'Économie',
    'js_price_included'    => 'Inclus',
    'js_pwd_defined'       => 'Défini',
    'js_alias_empty'       => 'Aucun alias',
    'js_fwd_empty'         => 'Aucune redirection',


    // ── DKIM modal ────────────────────────────────────────────────────────────
    'dkim_modal_desc_valid'    => 'Enregistrement DKIM détecté et valide dans le DNS.',
    'dkim_modal_desc_invalid'  => 'Enregistrement DKIM non trouvé dans le DNS. Ajoutez les records ci-dessous.',
    'dkim_modal_desc_add'      => 'Ajoutez cet enregistrement TXT dans la zone DNS de votre domaine pour authentifier vos courriels sortants.',
    'dkim_lbl_host_short'      => 'Hôte (Name)',
    'dkim_lbl_value_short'     => 'Valeur (TXT)',
    'dkim_propagation'         => 'Après modification du DNS, la propagation peut prendre jusqu\'à 24 h.',

    // ── SPF modal ─────────────────────────────────────────────────────────────
    'spf_modal_title'          => 'Enregistrement SPF',
    'spf_valid_desc'           => 'Enregistrement SPF valide détecté dans le DNS.',
    'spf_current_label'        => 'Enregistrement SPF actuel',
    'spf_missing_desc'         => 'Enregistrement SPF manquant ou incomplet.',
    'spf_add_desc'             => 'Ajoutez ou modifiez le record TXT SPF de votre domaine pour autoriser notre serveur à envoyer en votre nom.',
    'spf_txt_label'            => 'Record TXT à ajouter sur votre domaine',
    'spf_existing_warn'        => 'Un record SPF existe déjà sur ce domaine. Modifiez-le pour y ajouter le mécanisme requis.',
    'spf_propagation'          => 'La propagation DNS peut prendre jusqu\'à 24h.',

    // ── Boutons communs (ajouts) ──────────────────────────────────────────────
    'btn_remove_alias'         => 'Retirer cet alias',
    'btn_remove'               => 'Retirer',
    'btn_more_info'            => 'En savoir plus',
    'btn_generate_pwd'         => 'Générer un mot de passe',

    // ── Placeholders ─────────────────────────────────────────────────────────
    'eu_alias_ph_ex'           => 'ex : info',
    'eu_fwd_ph_ex'             => 'ex : autre@exemple.com',
    'add_user_ph_username'     => 'utilisateur',

    // ── Libellé erreur générique ──────────────────────────────────────────────
    'err_label'                => 'Erreur :',

    // ── Messages d'erreur PHP visibles par le client ──────────────────────────
    //
    // Convention : les clés err_* sont retournées par les fonctions PHP et
    // affichées soit dans l'espace client (AJAX), soit dans le panneau admin
    // WHMCS (fonctions de module). Toutes doivent être claires et actionnables.
    //
    // Codes de substitution sprintf utilisés dans certaines clés :
    //   %s → chaîne (domaine, message d'erreur API, nom d'utilisateur, etc.)
    //   %d → entier (nombre de caractères, code HTTP, nombre d'enregistrements)

    // ── Connexion / serveur ───────────────────────────────────────────────────
    'err_server_connect'       => 'Le service courriel est temporairement indisponible. Veuillez réessayer plus tard ou contacter le soutien technique si le problème persiste.',
    'err_server_unreachable'   => 'Serveur SmarterMail injoignable — vérifiez le nom d\'hôte et le port.',
    'err_sa_token_invalid'     => 'Jeton SA invalide ou expiré.',
    'err_sa_no_impersonate'    => 'Le compte SA n\'a pas les droits d\'impersonification.',
    'err_api_http'             => 'Erreur API HTTP %d.',
    // Suffixe ajouté aux erreurs de connexion qui nécessitent une intervention admin.
    // Utilisé en conjonction avec une erreur précise : ex. err_api_http . err_contact_admin
    'err_contact_admin'        => ' Veuillez contacter votre administrateur ou ouvrir un ticket de support.',
    'err_connection_prefix'    => 'Erreur de connexion : %s',

    // ── Domaines / provisionnement ────────────────────────────────────────────
    'err_domain_not_ready'     => 'Ce service courriel n\'est pas encore activé. Veuillez ouvrir un ticket de support.',
    'err_domain_access'        => 'Ce domaine n\'est pas encore configuré. Veuillez ouvrir un ticket de support.',
    'err_domain_not_found'     => 'Impossible d\'accéder au domaine — veuillez ouvrir un ticket de support.',
    // %s = nom de domaine — affiché dans le panneau admin lors du provisionnement
    'err_domain_exists'        => 'Erreur : Le domaine « %s » existe déjà dans SmarterMail. Créez manuellement le service ou contactez votre administrateur.',
    'err_domain_duplicate'     => 'Erreur : Le domaine « %s » est déjà utilisé dans SmarterMail.',
    // %s = message d'erreur retourné par l'API SmarterMail
    'err_create_domain'        => 'Erreur lors de la création du domaine : %s',
    'err_config_domain'        => 'Erreur lors de la configuration du domaine (domaine supprimé) : %s',
    'err_suspend'              => 'Erreur lors de la suspension : %s',
    'err_unsuspend'            => 'Erreur lors de la réactivation : %s',
    'err_terminate'            => 'Erreur lors de la résiliation : %s',
    // %s = domaine, %d = code HTTP retourné par SmarterMail
    'err_domain_http_access'   => 'Impossible d\'accéder au domaine %s (HTTP %d).',

    // ── Utilisateurs / comptes courriel ───────────────────────────────────────
    'err_user_required'        => 'Utilisateur non spécifié ou invalide.',
    'err_user_exists'          => 'Une adresse courriel avec ce nom existe déjà.',
    // Validation format du nom d'utilisateur (lettres, chiffres, . - _)
    'err_user_invalid_chars'   => 'Nom d\'utilisateur invalide. Caractères autorisés : lettres, chiffres, . - _ (doit commencer par une lettre ou un chiffre).',
    'err_admin_user_missing'   => 'Nom d\'utilisateur admin non configuré dans WHMCS.',
    'err_create_failed'        => 'Impossible de créer l\'adresse courriel. Veuillez réessayer ou ouvrir un ticket de support.',
    'err_save_failed'          => 'Impossible de sauvegarder les modifications. Veuillez réessayer ou ouvrir un ticket de support.',
    'err_delete_failed'        => 'Impossible de supprimer le compte. Veuillez réessayer ou ouvrir un ticket de support.',

    // ── Redirection (forwarding) ──────────────────────────────────────────────
    'err_fwd_invalid'          => 'L\'adresse de redirection n\'est pas valide.',

    // ── Mots de passe ─────────────────────────────────────────────────────────
    'err_pwd_required'         => 'Le mot de passe est requis.',
    'err_new_pwd_empty'        => 'Le nouveau mot de passe ne peut pas être vide.',
    'err_pwd_too_short'        => 'Le mot de passe est trop court.',
    // %d = longueur minimale configurée dans configoption9
    'err_pwd_min_length'       => 'Le mot de passe doit contenir au moins %d caractères.',
    'err_pwd_no_upper'         => 'Le mot de passe doit contenir au moins une lettre majuscule (A-Z).',
    'err_pwd_no_digit'         => 'Le mot de passe doit contenir au moins un chiffre (0-9).',
    'err_pwd_no_special'       => 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*-_=+).',
    // %s = nom d'utilisateur ou de domaine qui ne doit pas apparaître dans le mot de passe
    'err_pwd_has_user'         => 'Le mot de passe ne doit pas contenir le nom d\'utilisateur (%s).',
    'err_pwd_has_domain'       => 'Le mot de passe ne doit pas contenir le nom de domaine (%s).',
    'err_pwd_change_failed'    => 'Impossible de changer le mot de passe. Veuillez réessayer ou ouvrir un ticket de support.',
    // Erreur API lors du changement de mot de passe admin (panneau admin WHMCS) — %s = message API
    'err_pwd_change_api'       => 'Erreur lors du changement de mot de passe : %s',

    // ── Facturation / synchronisation (fonctions admin module) ───────────────
    // Ces messages sont retournés par les commandes de module admin et affichés
    // dans le panneau WHMCS Admin → Services → Module Commands.
    'err_billing_period'       => 'Impossible de déterminer la période de facturation (nextduedate manquant ?).',
    'info_free_account_skip'   => 'Ce service est un compte gratuit (Free Account) — le suivi EAS/MAPI n\'est pas applicable (aucune facturation).',
    // %s = message d'exception PHP
    'err_sync'                 => 'Erreur lors de la synchronisation : %s',
    'msg_cleanup_none'         => 'Nettoyage terminé — aucun enregistrement obsolète trouvé (aucun service Annulé/Fraude/Résilié avec des données de suivi).',
    // %d[1] = nb d'enregistrements supprimés, %d[2] = nb de services concernés
    'msg_cleanup_done'         => 'Nettoyage terminé — %d enregistrement(s) supprimé(s) pour %d service(s) avec statut inactif.',

    // ── Test de connexion serveur (bouton "Test Connection" dans WHMCS Admin) ──
    //
    // Ces chaînes sont retournées par smartermail_TestConnection() et affichées
    // dans le panneau Administration → Serveurs → Tester la connexion.
    // Elles ciblent l'administrateur technique, pas le client.
    //
    // err_test_connection_failed : authentification SA impossible — mauvais
    // identifiants, serveur injoignable, port erroné, etc.
    'err_test_connection_failed' => 'Connexion échouée : impossible de s\'authentifier avec les identifiants fournis. Vérifiez le nom d\'utilisateur, le mot de passe, le nom d\'hôte et le port.',
    // Libellés du résumé affiché en cas de succès.
    // Concaténés en une ligne : "Version : X.Y — Édition : Pro — 42 domaine(s) actif(s)"
    // %s = valeur lue depuis l'API (version, édition)
    'lbl_version'                => 'Version : %s',
    'lbl_edition'                => 'Édition : %s',
    // %d = nombre de domaines actifs recensés par SmarterMail
    'lbl_domains_active'         => '%d domaine(s) actif(s)',

    // ── Bouton d'activation/désactivation DKIM (espace client) ───────────────
    // Ces libellés sont utilisés par le bouton bascule (toggle) DKIM dans la
    // section DNS du tableau de bord client. Le client peut activer ou désactiver
    // la signature DKIM sans quitter la page.
    'dkim_toggle_enable'          => 'Activer le DKIM',
    'dkim_toggle_disable'         => 'Désactiver le DKIM',
    'dkim_toggle_confirm_disable' => 'Désactiver la signature DKIM de ce domaine ? Les courriels sortants ne seront plus signés DKIM.',
    'dkim_toggle_confirm_enable'  => 'Activer la signature DKIM pour ce domaine ?',
    'dkim_toggle_enabled_badge'   => 'Signature active',
    'dkim_toggle_disabled_badge'  => 'Signature inactive',
    'err_dkim_toggle_failed'      => 'Impossible de modifier le statut DKIM. Veuillez réessayer ou ouvrir un ticket de support.',
    'dkim_status_active'          => 'Actif',
    'dkim_status_disabled'        => 'Désactivé',
    'dkim_status_standby'         => 'En attente de validation DNS',
    'dkim_status_standby_desc'    => 'La clé DKIM a été générée et la signature est activée. Ajoutez l\'enregistrement DNS ci-dessous pour que SmarterMail puisse le valider.',
    'dkim_toggle_invalid_action'  => 'Action DKIM invalide.',

    // ── DKIM Rollover — Repli lorsque EnableDkim est indisponible ─────────
    // Ces chaînes sont utilisées lorsque SmarterMail n'a jamais eu DKIM
    // installé sur le domaine et que CreateDkimRollover sert de repli.
    // Elles apparaissent dans les logs admin et optionnellement dans l'espace client.
    'dkim_rollover_pending'        => 'Clé DKIM générée (en attente de validation DNS). Ajoutez l\'enregistrement ci-dessous pour activer la signature.',
    'dkim_rollover_activated'      => 'Clé DKIM activée via roulement de clé (rollover).',
    'err_dkim_rollover_failed'     => 'Impossible de générer une clé DKIM. Veuillez réessayer ou contacter le support.',
    'dkim_rollover_notice'         => 'Une nouvelle clé DKIM a été créée. Rechargez cette page pour voir l\'enregistrement DNS à ajouter.',

    // ── Guide de configuration DNS — Onglets ──────────────────────────────────
    // Ces chaînes alimentent le guide étape par étape affiché sous la carte de
    // statut DNS. Le guide explique comment ajouter les enregistrements DKIM et
    // SPF selon le panneau de contrôle ou le gestionnaire DNS utilisé par le
    // client. L'onglet par défaut est détecté automatiquement selon les NS du domaine.
    'dns_guide_card_title'     => 'Comment configurer vos enregistrements DNS',
    'dns_guide_tab_cpanel'     => 'cPanel',
    'dns_guide_tab_plesk'      => 'Plesk',
    'dns_guide_tab_client'     => 'Espace client',
    'dns_guide_tab_generic'    => 'Autre / Générique',
    'dns_guide_main_title'     => 'Configuration DNS – DKIM & SPF',
    'dns_guide_dkim_section'   => 'Enregistrement DKIM',
    'dns_guide_spf_section'    => 'Enregistrement SPF',
    'dns_guide_spf_exists'     => 'Il en existe déjà un :',
    'dns_guide_spf_missing'    => 'Aucun enregistrement SPF existant :',
    'dns_guide_spf_no_dup'     => '⚠️ Ne créez pas un second enregistrement SPF – un seul est autorisé par domaine.',
    'dns_guide_spf_help'       => '💡 En cas de doute, consultez la documentation de votre fournisseur DNS ou contactez leur support.',
    'dns_guide_footer'         => '⚠️ Les modifications DNS peuvent prendre jusqu\'à <strong>24–48 h</strong> pour se propager.',
    'dns_guide_type_txt'       => 'TXT',
    'dns_guide_field_type'     => 'Type',
    'dns_guide_field_name'     => 'Nom',
    'dns_guide_field_record'   => 'Enregistrement',
    'dns_guide_field_value'    => 'Valeur',
    'dns_guide_field_host'     => 'Hôte / Nom',
    'dns_guide_field_content'  => 'Contenu',
    'dns_guide_field_ttl'      => 'TTL',
    'dns_guide_field_ttl_default' => 'Laissez la valeur par défaut',
    'dns_guide_save'           => 'Enregistrer',
    'dns_guide_ok_save'        => 'Cliquez <strong>« Enregistrer »</strong>.',
    'dns_guide_with_dot'       => '(avec le point final)',
    'dns_guide_leave_empty'    => 'laissez vide (représente la racine du domaine)',
    'dns_guide_or_leave_empty' => 'ou laissez le champ <strong>vide</strong> (selon votre fournisseur)',
    'dns_guide_spf_add_before' => 'Ajoutez la valeur ci-dessous <strong>avant</strong> le <code>~all</code> ou <code>-all</code> en fin de ligne.',
    'dns_guide_spf_full_rec'   => 'Utilisez cet enregistrement complet comme valeur TXT :',
    'dns_guide_copy_below'     => 'Copiez la valeur ci-dessous :',
    'dns_guide_copy_host'      => 'Copiez ce nom d\'hôte :',
    'dns_guide_copy_value'     => 'Copiez cette valeur TXT :',
    'dns_guide_copy_spf_mech'  => 'Ajoutez ce mécanisme avant <code>~all</code> :',

    // ── Guide DNS — Étapes spécifiques cPanel ────────────────────────────────
    'dns_cpanel_step1'   => 'Ouvrez votre <strong>cPanel</strong> et accédez à <strong>« Éditeur de zone DNS »</strong>.',
    'dns_cpanel_step2'   => 'Cliquez sur <strong>« Gérer »</strong> en face de votre domaine <strong>{$domain}</strong>.',
    'dns_cpanel_dkim3'   => 'Cliquez sur <strong>« Ajouter un enregistrement »</strong>.',
    'dns_cpanel_dkim4'   => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_cpanel_dkim5'   => 'Dans <strong>Nom</strong>, collez :',
    'dns_cpanel_dkim6'   => 'Dans <strong>Enregistrement</strong>, collez :',
    'dns_cpanel_dkim7'   => 'Cliquez <strong>« Enregistrer »</strong>.',
    'dns_cpanel_spf8'    => 'Dans la liste, cherchez un enregistrement <strong>TXT</strong> dont la valeur commence par <code>v=spf1</code>.',
    'dns_cpanel_spf_e1'  => 'Cliquez sur <strong>« Éditer »</strong> sur cette ligne.',
    'dns_cpanel_spf_e3'  => 'Cliquez <strong>« Enregistrer »</strong>.',
    'dns_cpanel_spf_n1'  => 'Cliquez sur <strong>« Ajouter un enregistrement »</strong>.',
    'dns_cpanel_spf_n2'  => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_cpanel_spf_n3'  => 'Dans <strong>Nom</strong>, collez : <code>{$domain}.</code> <em>(avec le point final)</em>.',
    'dns_cpanel_spf_n4'  => 'Dans <strong>Enregistrement</strong>, collez :',
    'dns_cpanel_spf_n5'  => 'Cliquez <strong>« Enregistrer »</strong>.',

    // ── Guide DNS — Étapes spécifiques Plesk ─────────────────────────────────
    'dns_plesk_step1'    => 'Connectez-vous à votre panneau <strong>Plesk</strong> et allez dans <strong>« Sites Web & Domaines »</strong>.',
    'dns_plesk_step2'    => 'Sélectionnez le domaine <strong>{$domain}</strong>.',
    'dns_plesk_step3'    => 'Cliquez sur <strong>« Paramètres DNS »</strong>.',
    'dns_plesk_dkim4'    => 'Cliquez sur <strong>« Ajouter un enregistrement »</strong>.',
    'dns_plesk_dkim5'    => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_plesk_dkim6'    => 'Dans <strong>Nom de domaine</strong>, collez :',
    'dns_plesk_dkim7'    => 'Dans <strong>Valeur TXT</strong>, collez :',
    'dns_plesk_dkim8'    => 'Cliquez <strong>« OK »</strong>, puis <strong>« Mettre à jour »</strong>.',
    'dns_plesk_spf9'     => 'Dans la liste, cherchez un enregistrement <strong>TXT</strong> dont la valeur commence par <code>v=spf1</code>.',
    'dns_plesk_spf_e1'   => 'Cliquez sur la ligne correspondante pour l\'<strong>éditer</strong>.',
    'dns_plesk_spf_e3'   => 'Cliquez <strong>« OK »</strong>, puis <strong>« Mettre à jour »</strong>.',
    'dns_plesk_spf_n1'   => 'Cliquez sur <strong>« Ajouter un enregistrement »</strong>.',
    'dns_plesk_spf_n2'   => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_plesk_spf_n3'   => 'Dans <strong>Nom de domaine</strong>, laissez le champ <strong>vide</strong> <em>(représente la racine du domaine)</em>.',
    'dns_plesk_spf_n4'   => 'Dans <strong>Valeur TXT</strong>, collez :',
    'dns_plesk_spf_n5'   => 'Cliquez <strong>« OK »</strong>, puis <strong>« Mettre à jour »</strong>.',

    // ── Guide DNS — Étapes spécifiques Espace client (Astral Internet) ────────
    'dns_client_step1'   => 'Dans le menu déroulant <strong>« Mes Domaines »</strong>, sélectionnez <strong>« Mes noms de domaines »</strong>.',
    'dns_client_step2'   => 'Cliquez sur <strong>« … »</strong> au bout de la ligne de <strong>{$domain}</strong> et sélectionnez <strong>« Gérer le domaine »</strong>.',
    'dns_client_step3'   => 'Dans le menu de gauche, cliquez sur <strong>« Gestion de la Zone DNS »</strong>.',
    'dns_client_dkim4'   => 'Cliquez sur <strong>« + Add record »</strong>.',
    'dns_client_dkim5'   => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_client_dkim6'   => 'Dans <strong>Name</strong>, collez :',
    'dns_client_dkim7'   => 'Dans <strong>Content</strong>, collez :',
    'dns_client_dkim8'   => 'Cliquez <strong>« Save »</strong>.',
    'dns_client_spf9'    => 'Dans la liste, cherchez un enregistrement <strong>TXT</strong> dont la valeur commence par <code>v=spf1</code>.',
    'dns_client_spf_e1'  => 'Cliquez directement sur la <strong>case de texte</strong> de l\'enregistrement SPF pour l\'éditer.',
    'dns_client_spf_e3'  => 'Cliquez en dehors de la case, puis cliquez sur <strong>« Save Changes »</strong> dans le bas de la page.',
    'dns_client_spf_n1'  => 'Cliquez sur <strong>« + Add record »</strong>.',
    'dns_client_spf_n2'  => 'Dans <strong>Type</strong>, sélectionnez <strong>TXT</strong>.',
    'dns_client_spf_n3'  => 'Dans <strong>Name</strong>, collez : <code>{$domain}.</code> <em>(avec le point final)</em>.',
    'dns_client_spf_n4'  => 'Dans <strong>Content</strong>, collez :',
    'dns_client_spf_n5'  => 'Cliquez <strong>« Save »</strong>.',

    // ── Guide DNS — Étapes génériques (autres fournisseurs) ──────────────────
    'dns_generic_step1'      => 'Connectez-vous au panneau de gestion de votre <strong>fournisseur DNS</strong> <em>(ex : GoDaddy, Cloudflare, Google Domains, Namecheap, etc.)</em>.',
    'dns_generic_step2'      => 'Accédez à la section de <strong>gestion de zone DNS</strong> de votre domaine <strong>{$domain}</strong>.',
    'dns_generic_dkim3'      => 'Ajoutez un nouvel enregistrement <strong>TXT</strong> avec les valeurs suivantes :',
    'dns_generic_dkim_host'  => '<strong>Nom / Hôte / Host :</strong>',
    'dns_generic_dkim_val'   => '<strong>Valeur / Contenu / Value :</strong>',
    'dns_generic_dkim_ttl'   => '<strong>TTL :</strong> Laissez la valeur par défaut.',
    'dns_generic_dkim4'      => 'Sauvegardez l\'enregistrement.',
    'dns_generic_spf5'       => 'Dans la liste, cherchez un enregistrement <strong>TXT</strong> dont la valeur commence par <code>v=spf1</code>.',
    'dns_generic_spf_e1'     => 'Modifiez cet enregistrement existant.',
    'dns_generic_spf_e3'     => 'Sauvegardez l\'enregistrement.',
    'dns_generic_spf_n1'     => 'Ajoutez un nouvel enregistrement <strong>TXT</strong> avec les valeurs suivantes :',
    'dns_generic_spf_n_name' => '<strong>Nom / Hôte / Host :</strong> <code>{$domain}.</code> ou laissez le champ <strong>vide</strong> <em>(selon votre fournisseur)</em>.',
    'dns_generic_spf_n_val'  => '<strong>Valeur / Contenu / Value :</strong>',
    'dns_generic_spf_n_ttl'  => '<strong>TTL :</strong> Laissez la valeur par défaut.',
    'dns_generic_spf_n4'     => 'Sauvegardez l\'enregistrement.',
    // ── Facturation EAS/MAPI basée sur l'utilisation — Espace client ─────────
    // Avis affiché sous les cases EAS/MAPI dans l'ajout et la modification d'adresse.
    // {days} est remplacé dynamiquement par la valeur de configoption16.
    'proto_billing_notice'         => 'Si EAS ou MAPI est actif, il sera facturé pour le mois en cours. Si désactivé en moins de {days} jour(s), aucun frais ne s\'applique.',

    // Popup de détail de facturation (bouton i à côté du montant)
    'proto_billing_detail_title'   => 'Détail de facturation',
    'proto_billing_period'         => 'Période : ',
    'proto_billing_storage'        => 'Stockage',
    'proto_billing_active_from'    => 'du ',
    'proto_billing_active_to'      => ' au ',
    'proto_billing_total'          => 'Total estimé',
    'proto_billing_live_note'      => 'Les montants affichés sont des estimations en temps réel. Le détail historique complet sera disponible après le premier cycle de facturation avec le suivi d\'utilisation EAS/MAPI activé.',
    'proto_billing_threshold_note' => 'EAS et MAPI → Si désactivé après {days} jour(s) → il sera facturé avec date de désactivation sur la prochaine facture.',
    // ── États des protocoles dans le popup de facturation ────────────────────
    'proto_status_grace'           => 'Période de grâce en cours',
    'proto_billing_deleted_on'     => 'Désactivé le ',

    // ── Descriptions des lignes de facture (hooks.php — hook InvoiceCreation) ─
    //
    // Ces chaînes apparaissent directement sur les factures client. Elles sont
    // générées par le hook InvoiceCreation et stockées en texte brut dans
    // tblinvoiceitems.description.
    //
    // Paramètres de substitution (sprintf) :
    //   inv_usage_label   : %1$s = utilisation en Go (formaté), %2$d = nb tranches,
    //                       %3$d = Go par tranche, %4$s = prix unitaire
    //   inv_usage_prefix  : préfixe affiché AVANT le détail d'utilisation sur la
    //                       deuxième ligne de la facture. Le "\n" (retour de ligne)
    //                       est ajouté côté PHP — ce préfixe suit immédiatement.
    //                       Caractère utilisé : guillemet double fermant « » » (U+00BB)
    //   inv_disabled_on   : %s = date au format d-M-y (ex : 15-jan-26)
    //   inv_entry_prefix  : préfixe de puce avant chaque adresse courriel
    //   inv_combined_hdr  : en-tête pour les lignes EAS + MAPI combinés
    //   inv_eas_hdr       : en-tête pour les lignes ActiveSync (EAS) uniquement
    //   inv_mapi_hdr      : en-tête pour les lignes MAPI/Exchange uniquement
    'inv_usage_label'  => '%1$s Go utilisé · %2$d Tranche(s) de %3$d Go × $%4$s',
    'inv_usage_prefix' => '» ',
    'inv_disabled_on'  => '(Désactivé le %s)',
    'inv_entry_prefix' => '- ',
    'inv_combined_hdr' => 'EAS + MAPI/Exchange :',
    'inv_eas_hdr'      => 'ActiveSync (EAS) :',
    'inv_mapi_hdr'     => 'MAPI/Exchange :',


    // ── Redirections autonomes (alias sans boîte courriel) ───────────────────
    //
    // Ces chaînes alimentent les pages addredirect.tpl et editredirect.tpl,
    // ainsi que la liste des comptes dans clientarea.tpl.
    //
    // CONVENTION :
    //   add_redirect_*  → formulaire de création
    //   edit_redirect_* → formulaire de modification / suppression
    //   list_*          → tableau de bord (liste des comptes)
    //   err_redirect_*  → messages d'erreur retournés par PHP

    // ── Boutons de la liste ───────────────────────────────────────────────────
    'list_add_email_btn'    => 'Courriel',       // Remplace l'ancien '+ Ajouter'
    'list_add_redirect_btn' => 'Redirection',    // Nouveau bouton

    // ── Colonne type dans la liste ────────────────────────────────────────────
    'list_col_type'     => 'Type',
    'list_type_mailbox' => 'Boîte courriel',       // Icône fa-inbox (bleu)
    'list_type_redirect'=> 'Redirection autonome', // Icône fa-share (vert)

    // ── Formulaire d'ajout ────────────────────────────────────────────────────
    'add_redirect_title'         => 'Nouvelle redirection',
    // Sous-titre dans l'en-tête vert
    'add_redirect_subtitle'      => 'Une redirection transfère automatiquement les courriels reçus vers une autre adresse, sans créer de boîte courriel.',
    // Section adresse source
    'add_redirect_source_title'  => 'Adresse source',
    'add_redirect_source_label'  => 'Nom de la redirection',
    'add_redirect_source_ph'     => 'ex : bob',
    'add_redirect_source_help'   => 'Lettres, chiffres, points, tirets et underscores seulement.',
    // Bandeau informatif
    'add_redirect_info'          => 'Les courriels envoyés à cette adresse seront automatiquement transférés vers les destinataires ci-dessous. Aucune boîte courriel ne sera créée.',
    // Section destinations
    'add_redirect_dest_title'    => 'Adresses de destination',
    'add_redirect_dest_empty'    => 'Aucune destination — au moins une est requise.',
    'add_redirect_dest_add'      => 'Ajouter une destination',
    'add_redirect_btn_create'    => 'Créer la redirection',
    // Modale ajout destination
    'add_redirect_modal_title'   => 'Ajouter une destination',
    'add_redirect_modal_label'   => 'Adresse de destination',
    'add_redirect_modal_ph'      => 'ex : bob@gmail.com',

    // ── Formulaire de modification ────────────────────────────────────────────
    'edit_redirect_title'        => 'Modifier la redirection',
    'edit_redirect_subtitle'     => 'Modifiez les adresses vers lesquelles cette redirection transfère les courriels.',
    // Indication que l'adresse source ne peut pas être modifiée
    'edit_redirect_source_hint'  => 'L\'adresse source ne peut pas être modifiée. Pour la changer, supprimez cette redirection et créez-en une nouvelle.',
    'edit_redirect_btn_delete'   => 'Supprimer la redirection',
    // Modale de confirmation de suppression
    'edit_redirect_del_title'    => 'Supprimer la redirection',
    'edit_redirect_del_confirm'  => 'Supprimer définitivement la redirection suivante ?',
    'edit_redirect_del_irreversible' => 'Cette action est irréversible.',
    'edit_redirect_del_confirm_btn'  => 'Supprimer définitivement',

    // ── Messages d'erreur ─────────────────────────────────────────────────────
    'err_redirect_invalid_name'       => 'Nom de redirection invalide. Lettres, chiffres, . - _ seulement (doit commencer par une lettre ou un chiffre).',
    'err_redirect_target_required'    => 'Au moins une adresse de destination est requise.',
    // %s = adresse invalide (htmlspecialchars appliqué côté PHP)
    'err_redirect_invalid_target'     => 'Adresse de destination invalide : %s',
    // Version JS (sans %s — affichée par le JS avant soumission)
    'err_redirect_invalid_target_js'  => 'Adresse courriel invalide.',
    'err_redirect_dup_target'         => 'Cette adresse est déjà dans la liste.',
    'err_redirect_conflicts_mailbox'  => 'Ce nom est déjà utilisé par une boîte courriel existante.',
    'err_redirect_alias_exists'       => 'Une redirection ou un alias avec ce nom existe déjà.',

    // ── Menu Actions de l'espace client ──────────────────────────────────────
    // Libellé du bouton affiché dans le menu déroulant "Actions" de WHMCS.
    // La clé du tableau ClientAreaCustomButtonArray DOIT être traduite ici
    // car WHMCS utilise directement la clé comme texte du bouton.
    'action_btn_add_email' => 'Ajouter une adresse courriel',

    // ═══════════════════════════════════════════════════════════════════════════
    //  ALIAS DE DOMAINE
    // ═══════════════════════════════════════════════════════════════════════════
    //
    //  Un alias de domaine permet au domaine principal de recevoir du courrier
    //  adressé à un autre nom de domaine. Par exemple, si « client.com » est
    //  le domaine principal et « client.ca » est un alias, alors un courriel
    //  envoyé à « info@client.ca » sera livré dans la boîte « info@client.com ».
    //
    //  Ces chaînes sont utilisées dans le panneau de l'espace client affiché
    //  sous la section des enregistrements DNS. Le panneau montre les alias
    //  existants sous forme de pills, permet d'en ajouter (jusqu'à la limite
    //  configoption17) et de les supprimer après confirmation.
    // ═══════════════════════════════════════════════════════════════════════════

    // ── Titre de section et description ───────────────────────────────────────
    // Le titre apparaît dans l'en-tête de la carte, l'infobulle explique la fonctionnalité.
    'domain_alias_title'            => 'Alias de domaine',
    'domain_alias_tooltip'          => 'Un alias de domaine permet à toutes les boîtes courriel de votre domaine principal de recevoir les courriels adressés à un autre nom de domaine. Par exemple, si votre domaine est « exemple.com » et que vous ajoutez « exemple.ca » comme alias, un courriel envoyé à « info@exemple.ca » sera livré dans « info@exemple.com ».',
    // Compteur affiché dans l'en-tête : « 2 / 5 »
    'domain_alias_counter'          => '%d / %d',

    // ── État vide ────────────────────────────────────────────────────────────
    // Affiché quand aucun alias de domaine n'existe.
    'domain_alias_empty'            => 'Aucun alias de domaine configuré.',

    // ── Bouton d'ajout et modale ─────────────────────────────────────────────
    'domain_alias_add_btn'          => '+ Alias de domaine',
    'domain_alias_add_title'        => 'Ajouter un alias de domaine',
    'domain_alias_add_label'        => 'Nom de domaine',
    'domain_alias_add_placeholder'  => 'exemple.ca',
    'domain_alias_add_help'         => 'Entrez le nom de domaine que vous souhaitez utiliser comme alias. Les courriels envoyés à ce domaine seront livrés dans les boîtes de votre domaine principal.',
    'domain_alias_add_submit'       => 'Ajouter l\'alias',

    // ── Modale de confirmation de suppression ────────────────────────────────
    'domain_alias_del_title'        => 'Retirer un alias de domaine',
    'domain_alias_del_confirm'      => 'Voulez-vous vraiment retirer l\'alias de domaine suivant?',
    'domain_alias_del_warning'      => 'Les courriels envoyés aux adresses de ce domaine ne seront plus livrés dans vos boîtes courriel.',
    'domain_alias_del_submit'       => 'Retirer définitivement',

    // ── Message de limite atteinte ───────────────────────────────────────────
    // Affiché en infobulle sur le bouton désactivé quand la limite est atteinte.
    'domain_alias_limit_reached'    => 'Nombre maximal d\'alias de domaine atteint.',

    // ── Messages d'erreur (PHP côté serveur) ─────────────────────────────────
    // Retournés par les fonctions d'action PHP et affichés sur la page
    // d'erreur générique si quelque chose tourne mal.
    'err_domain_alias_disabled'         => 'La fonctionnalité d\'alias de domaine n\'est pas activée pour ce service.',
    'err_domain_alias_invalid_name'     => 'Nom d\'alias de domaine invalide. Entrez un nom de domaine valide (ex : exemple.ca).',
    'err_domain_alias_same_as_primary'  => 'L\'alias ne peut pas être identique au domaine principal.',
    'err_domain_alias_limit_reached'    => 'Limite atteinte (%d alias de domaine maximum).',
    'err_domain_alias_already_exists'   => 'Cet alias de domaine existe déjà.',
    'err_domain_alias_add_failed'       => 'Impossible d\'ajouter l\'alias de domaine. Veuillez réessayer ou contacter le soutien technique.',
    'err_domain_alias_not_found'        => 'Alias de domaine introuvable.',
    'err_domain_alias_delete_failed'    => 'Impossible de retirer l\'alias de domaine. Veuillez réessayer ou contacter le soutien technique.',

];
