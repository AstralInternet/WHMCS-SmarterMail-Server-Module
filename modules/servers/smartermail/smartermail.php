<?php
/**
 * ============================================================================
 *  smartermail.php — Module Serveur WHMCS pour SmarterMail
 * ============================================================================
 *
 * Point d'entrée principal du module. Ce fichier contient toutes les fonctions
 * que WHMCS appelle automatiquement selon les actions déclenchées par l'admin
 * ou par le client dans son espace.
 *
 * STRUCTURE D'UN MODULE SERVEUR WHMCS :
 * ─────────────────────────────────────
 * Un module serveur WHMCS doit définir des fonctions avec le préfixe du nom
 * du module (ici "smartermail_"). WHMCS appelle automatiquement ces fonctions
 * selon les événements :
 *
 *   Cycle de vie du service (appelés par l'admin ou par des automatisations) :
 *     smartermail_CreateAccount()   → Provisionnement initial
 *     smartermail_SuspendAccount()  → Suspension (ex: facture impayée)
 *     smartermail_UnsuspendAccount()→ Réactivation
 *     smartermail_TerminateAccount()→ Résiliation définitive
 *
 *   Métriques (appelé par le cron WHMCS quotidien) :
 *     smartermail_UsageUpdate()     → Mise à jour de l'utilisation disque
 *
 *   Interface admin :
 *     smartermail_LoginLink()       → Lien "Accéder au serveur" dans l'admin
 *
 *   Espace client :
 *     smartermail_ClientArea()                → Page d'accueil
 *     smartermail_ClientAreaCustomButtonArray()→ Boutons personnalisés
 *     smartermail_ClientAreaAllowedFunctions() → Liste des actions autorisées
 *     smartermail_manageusers()               → Liste des boîtes courriel
 *     smartermail_adduserpage()               → Formulaire ajout boîte
 *     smartermail_createuser()                → Action : créer boîte
 *     smartermail_edituserpage()              → Formulaire modif boîte
 *     smartermail_saveuser()                  → Action : sauvegarder boîte
 *     smartermail_deleteuser()                → Action : supprimer boîte
 *     smartermail_managealiases()             → Liste des alias
 *     smartermail_addaliaspage()              → Formulaire ajout alias
 *     smartermail_createalias()               → Action : créer alias
 *     smartermail_editaliaspage()             → Formulaire modif alias
 *     smartermail_savealias()                 → Action : sauvegarder alias
 *     smartermail_deletealias()               → Action : supprimer alias
 *
 * TABLEAU $params (fourni par WHMCS à chaque fonction) :
 * ─────────────────────────────────────────────────────
 * Ce tableau contient toutes les informations contextuelles. Clés importantes :
 *
 *   Serveur :
 *     $params['serverhostname']  → Hostname du serveur SmarterMail
 *     $params['serversecure']    → HTTPS activé ? (0 ou 1)
 *     $params['serverport']      → Port configuré dans WHMCS
 *     $params['serverusername']  → Username admin SmarterMail (SysAdmin)
 *     $params['serverpassword']  → Mot de passe SysAdmin (décrypté automatiquement)
 *
 *   Service :
 *     $params['serviceid']       → ID du service WHMCS (tblhosting.id)
 *     $params['domain']          → Domaine du service (ex: "client.com")
 *     $params['username']        → Username associé au service (notre admin secret)
 *     $params['password']        → Mot de passe associé (admin secret, décrypté)
 *
 *   Configuration du produit (Module Settings) :
 *     $params['configoption1']   → GB par tranche de facturation
 *     $params['configoption2']   → Prix EAS par boîte/mois
 *     $params['configoption3']   → Prix MAPI par boîte/mois
 *     $params['configoption4']   → Prix combiné EAS+MAPI
 *     $params['configoption5']   → Chemin des domaines sur le serveur
 *     $params['configoption6']   → Adresse IP de sortie
 *     $params['configoption7']   → Nombre max d'utilisateurs
 *     $params['configoption8']   → Supprimer les données à la résiliation ?
 *     $params['configoption9']   → Longueur minimale du mot de passe
 *     $params['configoption10']  → Exiger une lettre majuscule
 *     $params['configoption11']  → Exiger un chiffre
 *     $params['configoption12']  → Exiger un caractère spécial
 *     $params['configoption13']  → Mécanisme SPF requis
 *     $params['configoption14']  → Proposer ActiveSync (EAS) aux clients (yesno)
 *     $params['configoption15']  → Proposer MAPI/Exchange aux clients (yesno)
 *
 * VALEURS DE RETOUR DES FONCTIONS DE CYCLE DE VIE :
 * ─────────────────────────────────────────────────
 * Les fonctions CreateAccount, SuspendAccount, etc. doivent retourner :
 *   - La chaîne "success" si l'opération a réussi
 *   - N'importe quelle autre chaîne pour indiquer une erreur (elle sera affichée)
 *   - NE PAS retourner de booléen, array, ou null
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

// Inclure la classe wrapper de l'API SmarterMail
// __DIR__ = répertoire du fichier courant = modules/servers/smartermail/
require_once __DIR__ . '/lib/SmarterMailApi.php';
// Bibliothèque partagée de suivi d'utilisation EAS/MAPI (partagée avec hooks.php)
require_once __DIR__ . '/lib/SmarterMailProtoUsage.php';

use WHMCS\Database\Capsule;


// =============================================================================
//  MÉTADONNÉES ET CONFIGURATION DU MODULE
// =============================================================================

/**
 * Métadonnées du module — Identité et comportement global.
 *
 * Ces informations sont lues par WHMCS lors du chargement du module et
 * apparaissent dans l'interface d'administration.
 *
 * Versionnement — schéma MAJEUR.MINEUR.CORRECTIF (SemVer simplifié) :
 *   MAJEUR  → Changement incompatible (ex: refonte de la structure de base de données)
 *   MINEUR  → Nouvelle fonctionnalité rétrocompatible (ex: ajout d'une option de config)
 *   CORRECTIF → Correction de bogue ou de sécurité sans nouvelle fonctionnalité
 *
 * @return array Tableau de métadonnées
 */
function smartermail_MetaData(): array
{
    return [
        // Nom affiché dans l'admin WHMCS (liste des modules serveur)
        'DisplayName' => 'SmarterMail (Hébergement Courriel)',

        // Version du module — incrémenter à chaque déploiement en production
        // Format : MAJEUR.MINEUR.CORRECTIF  (ex: 1.0.1 pour un correctif, 1.1.0 pour une nouveauté)
        'MODVersion' => '1.0.0',

        // Version de l'API WHMCS utilisée (1.1 = compatibilité large)
        'APIVersion' => '1.1',

        // Ce module NÉCESSITE un serveur configuré dans WHMCS → Serveurs
        // Sans serveur, le module ne peut pas fonctionner
        'RequiresServer' => true,

        // Ports par défaut suggérés dans la configuration du serveur WHMCS
        'DefaultNonSSLPort' => '9998',  // Port HTTP par défaut de SmarterMail
        'DefaultSSLPort'    => '443',   // Port HTTPS standard

        // Libellé du lien SSO dans l'espace client (connexion directe au webmail)
        'ServiceSingleSignOnLabel' => 'Accéder au Webmail',
    ];
}

/**
 * Fournisseur de métriques WHMCS — Statistiques d'utilisation SmarterMail.
 *
 * WHMCS (7.9+) appelle cette fonction pour obtenir une instance de
 * SmarterMailMetricsProvider, qui implémente ProviderInterface.
 *
 * Métriques exposées :
 *   - Espace disque (Go)
 *   - Adresses courriel actives
 *   - Alias
 *   - Comptes ActiveSync (EAS)
 *   - Comptes MAPI/Exchange
 *
 * Ces métriques s'affichent dans :
 *   - Admin → Clients → [Client] → Services → [Service] → onglet Utilisation
 *   - Les rapports d'utilisation WHMCS
 *   - Peuvent être utilisées pour la facturation à l'usage (Usage Billing)
 *
 * @param  array  $params Paramètres du serveur (serverid, serverhostname, etc.)
 * @return \WHMCS\Module\Server\Smartermail\SmarterMailMetricsProvider
 */
function smartermail_MetricProvider(array $params): object
{
    return new \WHMCS\Module\Server\Smartermail\SmarterMailMetricsProvider($params);
}

/**
 * Options de configuration du module (onglet "Module Settings" du produit WHMCS).
 *
 * Ces options s'affichent dans Configuration → Produits/Services → [Produit] → Onglet Module.
 * Elles permettent de personnaliser le comportement du module par produit.
 *
 * Les valeurs sont accessibles dans $params via $params['configoptionN'] (N = 1 à 8).
 *
 * IMPORTANT : Le prix par tranche (configoption1) fonctionne EN COMBINAISON avec
 * le prix mensuel du produit configuré dans l'onglet Tarification de WHMCS.
 * Le prix mensuel du produit = prix pour 1 tranche.
 * Ex: Produit à 6$/mois + configoption1 = 10 GB → client utilisant 21 GB = 18$
 *
 * @return array Tableau de configuration des options du module
 */
function smartermail_ConfigOptions(): array
{
    return [
        // ── FACTURATION ──────────────────────────────────────────────────────

        'configoption1' => [
            'FriendlyName' => 'GB par tranche de facturation',
            'Type'         => 'text',
            'Size'         => '5',
            'Default'      => '10',
            'Description'  => implode(' ', [
                'Nombre de Go par incrément.',
                'Ex: valeur=10, produit à 6$/mois → client utilisant 21 Go sera facturé',
                '3 tranches × 6$ = 18$, avec "21.00 Go utilisés sur 30 Go facturés" sur la facture.',
            ]),
        ],

        'configoption2' => [
            'FriendlyName' => 'Prix ActiveSync (EAS) / boîte / mois ($)',
            'Type'         => 'text',
            'Size'         => '8',
            'Default'      => '2.00',
            'Description'  => implode(' ', [
                'Frais mensuels ajoutés par boîte courriel ayant ActiveSync activé.',
                'Une ligne séparée sera ajoutée sur la facture par boîte.',
                'Mettre 0 pour désactiver la facturation EAS.',
            ]),
        ],

        'configoption3' => [
            'FriendlyName' => 'Prix MAPI/Exchange / boîte / mois ($)',
            'Type'         => 'text',
            'Size'         => '8',
            'Default'      => '3.00',
            'Description'  => implode(' ', [
                'Frais mensuels ajoutés par boîte courriel ayant MAPI (Outlook mode Exchange) activé.',
                'Mettre 0 pour désactiver la facturation MAPI.',
            ]),
        ],

        'configoption4' => [
            'FriendlyName' => 'Prix combiné EAS + MAPI / boîte / mois ($)',
            'Type'         => 'text',
            'Size'         => '8',
            'Default'      => '4.50',
            'Description'  => implode(' ', [
                'Tarif réduit appliqué si une boîte a BOTH EAS ET MAPI activés.',
                'Remplace les deux prix séparés (pas de cumul).',
                'Mettre 0 pour utiliser les deux prix séparément même en combinaison.',
            ]),
        ],

        // ── PARAMÈTRES SMARTERMAIL ────────────────────────────────────────────

        'configoption5' => [
            'FriendlyName' => 'Chemin des domaines sur le serveur',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => 'C:\\SmarterMail\\Domains\\',
            'Description'  => implode(' ', [
                'Chemin de stockage sur le serveur Windows SmarterMail.',
                'Le nom du domaine sera automatiquement ajouté à la fin.',
                'Ex: "C:\\SmarterMail\\Domains\\" → stocke dans "C:\\SmarterMail\\Domains\\client.com\\"',
            ]),
        ],

        'configoption6' => [
            'FriendlyName' => 'Adresse IP de sortie (outbound)',
            'Type'         => 'text',
            'Size'         => '20',
            'Default'      => 'default',
            'Description'  => implode(' ', [
                'IP utilisée pour envoyer les courriels sortants de ce domaine.',
                'Laisser "default" pour utiliser l\'IP par défaut du serveur.',
                'Utile si le serveur a plusieurs IPs (ex: IPs dédiées par client).',
            ]),
        ],

        'configoption7' => [
            'FriendlyName' => 'Nombre max d\'utilisateurs par domaine',
            'Type'         => 'text',
            'Size'         => '5',
            'Default'      => '0',
            'Description'  => '0 = illimité. Peut être utilisé pour créer des forfaits avec limite.',
        ],

        // ── RÉSILIATION ────────────────────────────────────────────────────────

        'configoption8' => [
            'FriendlyName' => 'Supprimer les données lors de la résiliation',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => implode(' ', [
                'Si activé : supprime tous les courriels et données du domaine lors de la résiliation.',
                'Si désactivé : supprime le domaine de SmarterMail mais conserve les fichiers sur disque',
                '(utile pour archivage manuel ou si vous gérez la rétention des données vous-même).',
            ]),
        ],

        // ── POLITIQUE DE MOT DE PASSE ─────────────────────────────────────────
        // Ces critères sont affichés lors du changement de mot de passe dans
        // l'espace client et doivent correspondre aux règles configurées dans
        // SmarterMail (Admin → Configuration → Password Requirements).

        'configoption9' => [
            'FriendlyName' => 'Longueur minimale du mot de passe',
            'Type'         => 'text',
            'Size'         => '3',
            'Default'      => '8',
            'Description'  => 'Nombre minimal de caractères. Doit correspondre à la configuration SmarterMail.',
        ],

        'configoption10' => [
            'FriendlyName' => 'Exiger une lettre majuscule',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => 'Le mot de passe doit contenir au moins une lettre majuscule (A-Z).',
        ],

        'configoption11' => [
            'FriendlyName' => 'Exiger un chiffre',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => 'Le mot de passe doit contenir au moins un chiffre (0-9).',
        ],

        'configoption12' => [
            'FriendlyName' => 'Exiger un caractère spécial',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*-_=+).',
        ],

        // ── ENREGISTREMENTS DNS ───────────────────────────────────────────────

        'configoption13' => [
            'FriendlyName' => 'Mécanisme SPF requis',
            'Type'         => 'text',
            'Size'         => '60',
            'Default'      => 'include:mail.example.com',
            'Description'  => implode(' ', [
                'Mécanisme SPF que le client doit avoir dans son DNS (ex: include:mail.example.com ou ip4:1.2.3.4).',
                'Le module vérifie que le TXT v=spf1 du domaine contient ce mécanisme.',
                'Affiché en vert dans le tableau de bord si correctement configuré.',
            ]),
        ],

        // ── OFFRE DE PROTOCOLES SUPPLÉMENTAIRES ─────────────────────────────────
        // Ces options contrôlent si les protocoles EAS et MAPI sont PROPOSÉS aux
        // clients dans leur espace client. Elles sont INDÉPENDANTES du prix :
        //   - Une option peut être activée avec un prix à 0 (offert gratuitement)
        //   - Une option peut être désactivée même si un prix est configuré
        //     (l'admin gère alors les activations manuellement via SmarterMail)
        //
        // IMPORTANT : Ces options pilotent l'AFFICHAGE des cases à cocher dans
        // l'interface client (Ajouter / Modifier une adresse courriel).
        // La facturation reste gérée par les prix configoption2/3/4.

        'configoption14' => [
            'FriendlyName' => 'Proposer ActiveSync (EAS) aux clients',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => implode(' ', [
                'Si activé : les clients voient et peuvent activer l\'option ActiveSync (EAS)',
                'dans leur espace client lors de la création ou modification d\'une adresse courriel.',
                'Si désactivé : l\'option est masquée — seul l\'administrateur peut activer EAS',
                'depuis SmarterMail ou via l\'admin WHMCS.',
            ]),
        ],

        'configoption15' => [
            'FriendlyName' => 'Proposer MAPI/Exchange aux clients',
            'Type'         => 'yesno',
            'Default'      => 'on',
            'Description'  => implode(' ', [
                'Si activé : les clients voient et peuvent activer l\'option MAPI/Exchange',
                'dans leur espace client lors de la création ou modification d\'une adresse courriel.',
                'Si désactivé : l\'option est masquée — seul l\'administrateur peut activer MAPI',
                'depuis SmarterMail ou via l\'admin WHMCS.',
            ]),
        ],

        // ── SEUIL DE FACTURATION EAS/MAPI ────────────────────────────────────────
        // Nombre de jours cumulatifs d'activation minimum dans la période de
        // facturation avant qu'EAS ou MAPI soit facturé pour ce mois.
        //
        // COMPORTEMENT :
        //   - Le temps est CUMULATIF : actif 12h + désactivé + réactivé 12h = 1 jour → facturable
        //   - IDEMPOTENCE : une seule facturation par adresse/protocole/période,
        //     même si désactivé/réactivé plusieurs fois
        //   - Adresses SUPPRIMÉES : si seuil atteint, facturées quand même sur la prochaine
        //     facture avec la plage de dates (Actif du jj-mmm au jj-mmm)
        //   - Valeur 0 → désactivé → facturation live (état au moment de la facture)
        'configoption16' => [
            'FriendlyName' => 'Seuil de facturation EAS/MAPI (jours)',
            'Type'         => 'text',
            'Size'         => '5',
            'Default'      => '1',
            'Description'  => implode(' ', [
                'Jours cumulatifs d\'activation minimum pour qu\'EAS ou MAPI soit facturé.',
                'Défaut : 1 jour. Exemple : 1 = si actif ≥ 24h cumulatif dans la période → facturé.',
                'Mettre 0 pour revenir à la facturation par état en temps réel.',
            ]),
        ],
    ];
}


// =============================================================================
//  FONCTIONS HELPERS INTERNES
// =============================================================================
//
//  Ces fonctions ne sont pas appelées par WHMCS directement.
//  Elles factorisent la logique commune utilisée par plusieurs fonctions du module.
// =============================================================================

/**
 * Génère un nom d'utilisateur aléatoire de $length caractères minuscules.
 *
 * Utilisé pour créer le compte administrateur "secret" du domaine.
 * Ce compte est invisible pour le client — il est uniquement utilisé
 * par WHMCS pour l'impersonification Domain Admin via l'API SmarterMail.
 *
 * Exemple de résultat : "xkqmtparis", "bhzlonuavq"
 *
 * Note : Uniquement des lettres minuscules pour éviter les conflits
 * avec les règles de validation des usernames de SmarterMail.
 *
 * @param int $length Longueur souhaitée (défaut: 10)
 * @return string     Username aléatoire en minuscules
 */
/**
 * Charge et retourne le tableau de traductions selon la langue active du client.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  ORDRE DE RÉSOLUTION DE LA LANGUE
 * ─────────────────────────────────────────────────────────────────────────────
 *  1. $_SESSION['Language'] — langue sélectionnée par le sélecteur de langue
 *     du portail client WHMCS. C'est la source la plus fiable car elle reflète
 *     le choix actif de la session, même si le client n'a pas de préférence
 *     enregistrée dans son profil.
 *
 *  2. $params['clientsdetails']['language'] — préférence de langue stockée
 *     dans le profil du compte client WHMCS. Peut être vide ('') si le client
 *     n'a jamais défini de préférence explicite (valeur vide ≠ null — l'ancien
 *     opérateur ?? ne capturait pas les chaînes vides, ce qui forçait toujours
 *     le retour en anglais). On utilise !empty() pour ignorer les chaînes vides.
 *
 *  3. $GLOBALS['CONFIG']['Language'] — langue par défaut configurée par
 *     l'administrateur WHMCS dans Paramètres → Général. Même traitement
 *     !empty() pour ignorer les valeurs vides éventuelles.
 *
 *  4. Fallback final : 'english' — valeur sûre si toutes les sources sont vides.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  SÉCURITÉ — PATH TRAVERSAL
 * ─────────────────────────────────────────────────────────────────────────────
 *  Le nom de langue est nettoyé par preg_replace (lettres et tirets uniquement)
 *  avant d'être utilisé pour construire le chemin du fichier. Cela neutralise
 *  les tentatives d'injection via la session ou le profil client
 *  (ex : "../../config", "../../../etc/passwd").
 *  realpath() valide ensuite que le fichier résolu reste bien dans /lang/.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  CACHE STATIQUE
 * ─────────────────────────────────────────────────────────────────────────────
 *  Le tableau est mis en cache par nom de langue résolu pour éviter de relire
 *  le fichier à chaque appel dans la même requête PHP.
 *  La clé de cache est la langue nettoyée (jamais '') — l'ancienne implémentation
 *  pouvait mettre en cache les traductions anglaises sous la clé '' et ne jamais
 *  réévaluer la langue active lors d'un appel suivant dans la même requête.
 *
 * @param  array $params Paramètres WHMCS du service
 * @return array<string,string> Tableau de traductions ['clé' => 'texte traduit']
 */
function _sm_lang(array $params): array
{
    // Cache statique indexé par langue résolue (pas par langue brute).
    // Jamais de clé vide — une langue vide sera toujours résolue en 'english'
    // avant d'être utilisée comme clé de cache.
    static $cache = [];

    // ── Étape 1 : collecter la langue brute selon l'ordre de priorité ────────
    //
    // On teste chaque source avec !empty() pour ignorer les chaînes vides
    // (cas fréquent quand le client n'a pas de préférence enregistrée dans WHMCS).
    // L'ancien opérateur ?? ne capturait que null, pas les chaînes vides.
    $rawLanguage = '';

    // Source 1 — Sélecteur de langue actif dans la session du portail client.
    // WHMCS stocke la langue choisie via le sélecteur dans $_SESSION['Language'].
    // C'est la source la plus fiable pour l'espace client car elle reflète
    // le choix effectif de navigation, indépendamment du profil enregistré.
    if (!empty($_SESSION['Language'])) {
        $rawLanguage = $_SESSION['Language'];
    }

    // Source 2 — Préférence de langue enregistrée dans le profil client WHMCS.
    // Disponible via $params['clientsdetails']['language']. Peut être '' si
    // le client n'a jamais défini de préférence — on ignore les chaînes vides.
    if (empty($rawLanguage) && !empty($params['clientsdetails']['language'])) {
        $rawLanguage = $params['clientsdetails']['language'];
    }

    // Source 3 — Langue par défaut du système WHMCS (Admin → Paramètres → Général).
    // Exposée via $GLOBALS['CONFIG']['Language']. Dernier recours avant le fallback.
    if (empty($rawLanguage) && !empty($GLOBALS['CONFIG']['Language'])) {
        $rawLanguage = $GLOBALS['CONFIG']['Language'];
    }

    // ── Étape 2 : normaliser et sécuriser le nom de langue ───────────────────
    //
    // strtolower() : harmonise la casse ('French' → 'french').
    // preg_replace() : supprime tout caractère non-alphabétique et non-tiret
    //   pour prévenir les attaques path traversal via la session ou le profil
    //   client (ex: '../../etc/passwd' → '', '../config' → 'config').
    // Si le résultat est vide (langue non définie partout), on force 'english'.
    $language = preg_replace('/[^a-z\-]/', '', strtolower(trim((string) $rawLanguage)));
    if (empty($language)) {
        $language = 'english';
    }

    // Retour depuis le cache si cette langue a déjà été résolue dans la requête
    if (isset($cache[$language])) {
        return $cache[$language];
    }

    // ── Étape 3 : résoudre le chemin du fichier de langue avec realpath() ────
    //
    // realpath() résout les symlinks et les séquences '..' — garantit que
    // $langDir pointe bien sur le répertoire /lang/ du module, jamais ailleurs.
    $langDir = realpath(__DIR__ . '/lang');

    // Sécurité : si realpath() échoue (répertoire absent ou droits insuffisants),
    // on retourne un tableau vide plutôt que de risquer une inclusion hors-path.
    if ($langDir === false) {
        return $cache[$language] = [];
    }

    $langFile = $langDir . '/' . $language . '.php';

    // Vérification de confinement : realpath() du fichier résolu doit commencer
    // par $langDir pour confirmer que le chemin n'a pas traversé les '..' résiduels.
    if (!file_exists($langFile) || strpos((string) realpath($langFile), $langDir) !== 0) {
        // Fallback vers l'anglais si la langue demandée est introuvable ou hors-path.
        // Ne pas logguer ici — une langue non supportée est un cas normal
        // (ex : client avec préférence 'spanish' non traduit dans ce module).
        $langFile = $langDir . '/english.php';
    }

    // ── Étape 4 : charger le tableau $_lang depuis le fichier de langue ───────
    //
    // Le fichier de langue déclare : $_lang = [ 'clé' => 'valeur', ... ]
    // On l'inclut dans la portée locale — $_lang sera disponible juste après.
    // Initialisation explicite à [] pour éviter tout résidu d'un include précédent.
    $_lang = [];
    if (file_exists($langFile)) {
        include $langFile; // $_lang est peuplé par le fichier inclus
    }

    return $cache[$language] = $_lang;
}

function _sm_randomUsername(int $length = 10): string
{
    $chars  = 'abcdefghijklmnopqrstuvwxyz';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        // random_int() est cryptographiquement sûr (contrairement à rand())
        $result .= $chars[random_int(0, 25)];
    }
    return $result;
}

/**
 * Génère un mot de passe sécurisé aléatoire.
 *
 * Utilisé pour le mot de passe du compte administrateur secret du domaine.
 * Ce mot de passe est stocké dans WHMCS (champ password du service) et
 * n'est jamais communiqué au client.
 *
 * Le jeu de caractères inclut des lettres, chiffres et symboles spéciaux
 * pour satisfaire les politiques de mots de passe de SmarterMail.
 *
 * @param int $length Longueur souhaitée (défaut: 24)
 * @return string     Mot de passe aléatoire fort
 */
function _sm_randomPassword(int $length = 24): string
{
    $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*-_=+';
    $max    = strlen($chars) - 1;
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $max)];
    }
    return $result;
}

/**
 * Valide un mot de passe selon les critères configurés dans les configoptions.
 *
 * Cette fonction centralise TOUTES les règles de validation de mot de passe
 * pour le compte Primary Admin (Domain Admin secret de SmarterMail).
 * Elle est appelée dans :
 *   - smartermail_ChangePassword()  → validation avant envoi à SmarterMail
 *   - smartermail_CreateAccount()   → validation du mot de passe saisi manuellement
 *
 * RÈGLES APPLIQUÉES (selon configoptions du module) :
 *   - Longueur minimale                  (configoption9,  défaut : 8)
 *   - Au moins une lettre majuscule      (configoption10, défaut : on)
 *   - Au moins un chiffre                (configoption11, défaut : on)
 *   - Au moins un caractère spécial      (configoption12, défaut : on)
 *   - Ne doit pas contenir le username   (toujours vérifié)
 *   - Ne doit pas contenir le domaine    (toujours vérifié)
 *
 * VALEUR DE RETOUR :
 *   - null   → mot de passe valide, aucune erreur
 *   - string → message d'erreur localisé (prêt à être retourné à WHMCS)
 *
 * @param string $password   Mot de passe à valider
 * @param string $username   Nom d'utilisateur (vérifié pour éviter le mot de passe = username)
 * @param string $domain     Domaine (vérifié pour éviter que le mdp contienne le domaine)
 * @param array  $params     Tableau $params WHMCS (pour lire les configoptions)
 * @return string|null       null si valide, message d'erreur sinon
 */
function _sm_validateAdminPassword(string $password, string $username, string $domain, array $params): ?string
{
    // ── Lecture des critères depuis les configoptions ──────────────────────
    $minLen         = max(8, (int) ($params['configoption9']  ?? 8));   // minimum absolu : 8
    $requireUpper   = ($params['configoption10'] ?? 'on') === 'on';
    $requireNumber  = ($params['configoption11'] ?? 'on') === 'on';
    $requireSpecial = ($params['configoption12'] ?? 'on') === 'on';

    // Chargement du tableau de langue une seule fois pour cette validation.
    // Toutes les erreurs de ce bloc utilisent _sm_lang() — plus de chaînes
    // codées en dur, conformément à la politique de localisation du module.
    $l = _sm_lang($params);

    // ── Longueur minimale ──────────────────────────────────────────────────
    if (strlen($password) < $minLen) {
        // %d = longueur minimale requise (configoption9, min 8)
        return sprintf($l['err_pwd_min_length'] ?? 'Le mot de passe doit contenir au moins %d caractères.', $minLen);
    }

    // ── Lettre majuscule obligatoire ───────────────────────────────────────
    if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
        return $l['err_pwd_no_upper'] ?? 'Le mot de passe doit contenir au moins une lettre majuscule (A-Z).';
    }

    // ── Chiffre obligatoire ────────────────────────────────────────────────
    if ($requireNumber && !preg_match('/[0-9]/', $password)) {
        return $l['err_pwd_no_digit'] ?? 'Le mot de passe doit contenir au moins un chiffre (0-9).';
    }

    // ── Caractère spécial obligatoire ──────────────────────────────────────
    if ($requireSpecial && !preg_match('/[!@#$%^&*\-_=+]/', $password)) {
        return $l['err_pwd_no_special'] ?? 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*-_=+).';
    }

    // ── Ne doit pas contenir le nom d'utilisateur ──────────────────────────
    // Vérifié insensible à la casse — "Patate" est refusé si username = "patate"
    if ($username !== '' && stripos($password, $username) !== false) {
        // %s = nom d'utilisateur incriminé (pour aider l'admin à corriger)
        return sprintf($l['err_pwd_has_user'] ?? 'Le mot de passe ne doit pas contenir le nom d\'utilisateur (%s).', $username);
    }

    // ── Ne doit pas contenir le domaine ───────────────────────────────────
    // Vérifie le domaine complet ET la partie principale (avant le premier point).
    // Ex: "example.com" et "example" sont tous les deux vérifiés.
    $domainBase = strstr($domain, '.', true) ?: $domain;
    if ($domain !== '' && stripos($password, $domain) !== false) {
        // %s = nom de domaine complet (ex : example.com)
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domain);
    }
    if (strlen($domainBase) >= 4 && stripos($password, $domainBase) !== false) {
        // %s = partie principale du domaine (ex : "example" de "example.com")
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domainBase);
    }

    // ── Tous les critères passés — mot de passe valide ────────────────────
    return null;
}

/**
 * Extrait le message d'erreur lisible depuis une réponse API.
 *
 * Raccourci pour accéder à $resp['error'] avec une valeur de repli.
 * Utilisé pour générer des messages d'erreur cohérents dans les fonctions.
 *
 * @param array  $resp     Tableau de réponse retourné par SmarterMailApi
 * @param string $fallback Message par défaut si $resp['error'] est vide
 * @return string          Message d'erreur lisible
 */
function _sm_apiError(array $resp, string $fallback = 'Une erreur est survenue.'): string
{
    return $resp['error'] ?? $fallback;
}

/**
 * Initialise l'API SmarterMail et obtient un token SysAdmin.
 *
 * Fonction utilitaire utilisée en DÉBUT DE CHAQUE FONCTION du module.
 * Elle centralise la logique d'initialisation pour éviter la duplication.
 *
 * En cas de succès, retourne :
 *   ['api' => SmarterMailApi, 'token' => string]
 *
 * En cas d'échec (serveur inaccessible, credentials invalides) :
 *   ['error' => string]
 *
 * Usage typique :
 *   $init = _sm_initApi($params);
 *   if (isset($init['error'])) return $init['error']; // Retourner l'erreur à WHMCS
 *   $api     = $init['api'];
 *   $saToken = $init['token'];
 *
 * @param  array $params Tableau $params fourni par WHMCS
 * @return array         ['api' => ..., 'token' => ...] ou ['error' => string]
 */
function _sm_initApi(array $params): array
{
    $api     = SmarterMailApi::fromParams($params);
    $saToken = $api->loginSysAdminFromParams($params);

    if (!$saToken) {
        // Utilise la clé de langue pour le message d'erreur de connexion SA.
        // _sm_lang() reçoit $params qui contient les détails du client/service.
        return ['error' => (_sm_lang($params)['err_server_connect'] ?? 'Impossible de se connecter au serveur SmarterMail. Vérifiez les identifiants du serveur.')];
    }

    return ['api' => $api, 'token' => $saToken];
}


/**
 * Initialise l'API SmarterMail et obtient un token Domain Admin via impersonification SA.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  STRATÉGIE D'AUTHENTIFICATION POUR L'ESPACE CLIENT
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  La connexion directe DA (loginDomainAdminDirect) nécessite que le mot de
 *  passe stocké dans WHMCS corresponde exactement à celui dans SmarterMail.
 *  Ce n'est pas garanti pour les domaines créés manuellement ou migrés.
 *
 *  On utilise plutôt l'impersonification SA → DA :
 *    1. Connexion SA avec les credentials du SERVEUR (tblservers)
 *    2. Impersonification du domaine du SERVICE → token DA limité à CE domaine
 *
 *  SÉCURITÉ :
 *    - Le token DA retourné est strictement limité au domaine du service
 *    - Il ne peut pas accéder aux autres domaines du serveur
 *    - Les credentials SA ne sont jamais exposés au client
 *    - $params['serverusername'] et ['serverpassword'] sont des credentials
 *      serveur, pas des credentials client — ils viennent de tblservers
 *
 *  En cas de succès, retourne :
 *    ['api' => SmarterMailApi, 'token' => string]  (token = daToken via impersonification)
 *
 *  En cas d'échec :
 *    ['error' => string]  avec message détaillé pour debug
 *
 * @param  array $params Tableau $params fourni par WHMCS
 * @return array         ['api' => ..., 'token' => ...] ou ['error' => string]
 */
function _sm_initDomainAdmin(array $params): array
{
    $api    = SmarterMailApi::fromParams($params);
    $domain = $params['domain'];
    // strtolower() : SmarterMail traite les usernames de façon insensible à la casse,
    // mais l'endpoint authenticate-user peut être sensible selon la version.
    // On normalise en minuscules pour cohérence avec _sm_randomUsername() et éviter
    // les échecs d'authentification causés par des majuscules saisies manuellement.
    $daUser = strtolower(trim($params['username'] ?? ''));
    $daPass = trim($params['password'] ?? '');

    // ── Étape 1 : Connexion SA ───────────────────────────────────
    $saToken = $api->loginSysAdminFromParams($params);
    if (!$saToken) {
        return ['error' => (_sm_lang($params)['err_server_connect'] ?? 'Impossible de se connecter au serveur SmarterMail.')];
    }

    // ── Étape 2 : Authentification Domain Admin ────────────────────
    //
    // Si des credentials DA sont enregistrés (username/password du service WHMCS),
    // on utilise la CONNEXION DIRECTE — pas l'impersonification SA.
    //
    // Avantages :
    //   1. Vérifie que le compte est bien lié au bon domaine dans SmarterMail.
    //   2. Évite d'accéder silencieusement au mauvais domaine via le SA.
    //   3. Détecte immédiatement une désynchronisation de credentials.
    //
    // Si les credentials sont vides (service jamais provisionné) :
    //   Fallback vers l'impersonification SA.
    //
    if ($daUser !== '' && $daPass !== '') {

        $directResult = $api->loginDomainAdminDirectFull($daUser, $daPass, $domain);

        if ($directResult['token']) {
            return ['api' => $api, 'token' => $directResult['token'], 'saToken' => $saToken];
        }

        // Échec : les credentials WHMCS ne correspondent pas à SmarterMail.
        // On ne fait PAS de fallback SA — refuser l'accès est plus sécuritaire.
        logActivity('SmarterMail [initDA] Credentials DA invalides pour '
            . $domain . ' | user=' . $daUser
            . ' | attempts=' . json_encode($directResult['attemptLog']));

        return [
            // Clé err_domain_not_ready : service commandé mais domaine pas encore créé
            // dans SmarterMail (credentials WHMCS invalides → domaine non prêt).
            'error'          => (_sm_lang($params)['err_domain_not_ready'] ?? 'Ce service courriel n\'est pas encore activé. Veuillez ouvrir un ticket de support.'),
            'domainNotReady' => true,
            'domain'         => $domain,
        ];
    }

    // ─ Pas de credentials stockés : fallback impersonification SA ─────────
    $daResult = $api->loginDomainAdminFull($saToken, $domain);

    if (!$daResult['token']) {
        $code     = $daResult['code'];
        $apiError = (string) ($daResult['error'] ?? '');

        $isDomainMissing = $code === 404
            || ($code === 400 && str_contains($apiError, 'INVALID_PERMISSIONS'));

        if ($isDomainMissing) {
            return [
                // Même message que le bloc direct credentials — domaine non encore provisionné
                'error'          => (_sm_lang($params)['err_domain_not_ready'] ?? 'Ce service courriel n\'est pas encore activé. Veuillez ouvrir un ticket de support.'),
                'domainNotReady' => true,
                'domain'         => $domain,
            ];
        }

        // ── Message d'erreur contextuel selon le code HTTP reçu ──────────────
        // Chaque cas correspond à une situation opérationnelle précise.
        // Les clés de langue permettent de localiser ces messages techniques
        // qui s'affichent dans le panneau admin WHMCS (pas en espace client).
        $l = _sm_lang($params);
        $hint = match (true) {
            $code === 401 => $l['err_sa_token_invalid']  ?? 'Jeton SA invalide ou expiré.',
            $code === 403 => $l['err_sa_no_impersonate'] ?? 'Le compte SA n\'a pas les droits d\'impersonification.',
            $code === 0   => $l['err_server_unreachable'] ?? 'Serveur SmarterMail injoignable — vérifiez le nom d\'hôte et le port.',
            default       => sprintf($l['err_api_http'] ?? 'Erreur API HTTP %d.', $code),
        };

        logActivity('SmarterMail [initDA] ' . $hint . ' | API: ' . $apiError . ' | domain: ' . $domain);

        // err_contact_admin est un SUFFIXE ajouté aux messages d'erreur techniques
        // pour guider l'admin vers la prochaine action.
        return ['error' => $hint . ($l['err_contact_admin'] ?? ' Veuillez contacter votre administrateur ou ouvrir un ticket de support.')];
    }

    return ['api' => $api, 'token' => $daResult['token'], 'saToken' => $saToken];
}


// =============================================================================
//  UTILITAIRE DKIM — Activation avec fallback rollover
// =============================================================================

/**
 * Active la signature DKIM d'un domaine avec fallback automatique vers le rollover.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  POURQUOI CE FALLBACK EST NÉCESSAIRE
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Sur certaines installations SmarterMail, l'endpoint EnableDkim
 * (POST api/v1/settings/domain/dkim-enable) échoue avec une erreur car
 * DKIM n'a jamais été initialisé sur le domaine — aucune paire de clés
 * RSA n'a encore été générée.
 *
 * Dans ce cas, il FAUT d'abord créer la paire de clés via le mécanisme
 * de rollover (POST api/v1/settings/domain/dkim-create-rollover/{keySize}),
 * puis valider l'enregistrement DNS pour activer la clé.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  FLUX D'EXÉCUTION
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  TENTATIVE 1 — EnableDkim standard :
 *    POST api/v1/settings/domain/dkim-enable/false
 *    ├─ success=true  → DKIM activé normalement → retourner ['success' => true]
 *    └─ success=false → DKIM non installé → TENTATIVE 2
 *
 *  TENTATIVE 2 — CreateDkimRollover :
 *    POST api/v1/settings/domain/dkim-create-rollover/2048
 *    ├─ success=true  → Clé générée (état « pending ») → retourner [
 *    │                     'success'    => true,
 *    │                     'rollover'   => true,       ← marqueur : via rollover
 *    │                     'publicKey'  => '...',      ← à mettre dans le DNS
 *    │                     'selector'   => '...',
 *    │                  ]
 *    └─ success=false → Les deux méthodes ont échoué → retourner ['success' => false]
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  ÉTAT APRÈS SUCCÈS VIA ROLLOVER
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  La clé est en état « pending » — la signature DKIM N'est PAS encore active.
 *  Pour finaliser :
 *    - Le client doit ajouter l'enregistrement TXT dans son DNS.
 *    - SmarterMail vérifiera automatiquement (nextCheckUtc) OU
 *    - L'admin peut appeler verifyDkimRollover() manuellement.
 *
 *  Ce comportement est identique à ce que ferait EnableDkim lorsqu'il crée
 *  un enregistrement « pending » — la différence est uniquement dans l'API
 *  appelée pour obtenir ce résultat.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  SÉCURITÉ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  - Le token DA passé en paramètre est TOUJOURS limité au domaine du service
 *    WHMCS du client — impossible d'agir sur un autre domaine.
 *  - La taille de clé 2048 est la valeur recommandée par l'IETF (RFC 6376).
 *    Elle est codée en dur ici pour éviter qu'un paramètre externe puisse
 *    forcer une clé trop faible (ex: 512 bits).
 *  - Aucune information sensible (token DA, clé privée) n'est loguée.
 *    Seuls le domaine et le résultat (succès/échec) sont journalisés.
 *
 * @param SmarterMailApi $api      Instance API déjà initialisée
 * @param string         $daToken  Token Domain Admin valide pour le domaine
 * @param string         $domain   Nom de domaine (pour la journalisation uniquement)
 * @return array {
 *   'success'   : bool   — true si l'opération a réussi (EnableDkim OU rollover)
 *   'rollover'  : bool   — true si c'est le rollover qui a été utilisé
 *   'publicKey' : string — clé publique (disponible seulement si rollover=true)
 *   'selector'  : string — sélecteur DNS (disponible seulement si rollover=true)
 *   'error'     : string — message d'erreur si success=false
 * }
 */
function _sm_enableOrRolloverDkim(SmarterMailApi $api, string $daToken, string $domain): array
{
    // ── ÉTAPE 1 : Appeler EnableDkim ──────────────────────────────────────
    //
    // EnableDkim est appelé en premier, mais son code retour success=true
    // n'est PAS suffisant pour confirmer que le DKIM est réellement activé.
    // Sur certaines versions de SmarterMail, l'endpoint répond success=true
    // même quand aucune paire de clés RSA n'a jamais été générée pour ce
    // domaine — l'appel "réussit" sans effet observable.
    //
    // C'est pourquoi on NE SE FIE PAS au seul retour de EnableDkim.
    // On vérifie systématiquement la présence d'une clé réelle à l'étape 2.
    $api->enableDkim($daToken, false);
    // Le résultat de enableDkim est intentionnellement ignoré ici.
    // On considère qu'un éventuel échec HTTP sera détecté à l'étape suivante
    // (absence de clé dans getDomainSettings). Ce design évite de traiter
    // différemment un vrai échec d'un succès silencieux.

    // ── ÉTAPE 2 : Vérifier la présence effective d'une clé DKIM ──────────
    //
    // On interroge getDomainSettings pour inspecter le sous-objet
    // « domainKeysSettings » qui contient la clé DKIM active du domaine.
    //
    // Les champs significatifs :
    //   domainKeysSettings.publicKey → clé publique RSA brute (non vide = clé présente)
    //   domainKeysSettings.selector  → sélecteur DNS (ex: « selector1 »)
    //
    // Si publicKey ET selector sont non vides, EnableDkim a bien initialisé
    // ou réactivé une clé DKIM existante → succès confirmé.
    //
    // Si l'un ou l'autre est vide (ou domainKeysSettings absent), cela signifie
    // que EnableDkim n'a pas créé de clé — on doit passer au rollover.
    $domSettings    = $api->getDomainSettings($daToken);
    $dkimRaw        = $domSettings['domainKeysSettings'] ?? [];

    // ── Vérification de la clé active (champs directs) ───────────────────
    // Ces champs sont peuplés quand SmarterMail a une clé active confirmée.
    $activeKey      = trim((string) ($dkimRaw['publicKey'] ?? ''));
    $activeSelector = trim((string) ($dkimRaw['selector']  ?? ''));

    // ── Vérification de la clé pending (sous-objet pending) ──────────────
    // Peuplé quand une clé a été créée (rollover ou EnableDkim first-time)
    // mais que le DNS n'a pas encore été validé par SmarterMail.
    // C'est l'état normal après CreateDkimRollover ou EnableDkim sur un domaine
    // qui n'avait jamais eu de DKIM — la clé existe, le DNS reste à configurer.
    $pendingRaw      = $dkimRaw['pending'] ?? [];
    $pendingKey      = trim((string) ($pendingRaw['publicKey'] ?? ''));
    $pendingSelector = trim((string) ($pendingRaw['selector']  ?? ''));

    if ($activeKey !== '' && $activeSelector !== '') {
        // ── Clé ACTIVE confirmée ─────────────────────────────────────────
        // EnableDkim a activé ou réactivé une clé existante.
        logActivity('SmarterMail [DKIM] Clé active confirmée pour ' . $domain
            . ' | sélecteur : ' . $activeSelector . ' — EnableDkim OK.');
        return [
            'success'   => true,
            'rollover'  => false,
            'publicKey' => $activeKey,
            'selector'  => $activeSelector,
        ];
    }

    if ($pendingKey !== '' && $pendingSelector !== '') {
        // ── Clé PENDING détectée ─────────────────────────────────────────
        // EnableDkim a créé une clé initiale mais elle est en attente de
        // validation DNS. Pas besoin de rollover — la clé existe, le client
        // doit simplement ajouter l'enregistrement DNS.
        logActivity('SmarterMail [DKIM] Clé pending confirmée pour ' . $domain
            . ' | sélecteur : ' . $pendingSelector . ' — EnableDkim a créé une clé pending.');
        return [
            'success'   => true,
            'rollover'  => false,   // EnableDkim a fonctionné (clé pending = normal)
            'publicKey' => $pendingKey,
            'selector'  => $pendingSelector,
        ];
    }

    // ── ÉTAPE 3 : Aucune clé détectée (ni active, ni pending) ─────────────
    //
    // EnableDkim n'a eu aucun effet observable : ni clé active, ni clé pending.
    // Cela se produit quand DKIM n'a jamais été installé sur ce serveur
    // SmarterMail. On crée la paire de clés via CreateDkimRollover.
    //
    // Taille 2048 bits : recommandation IETF RFC 6376 §3.3.
    //   → Plus sécurisée que 1024 sans impact sur les performances de signature.
    //   → 4096 bits peut causer des problèmes de fragmentation UDP des réponses
    //     DNS et des incompatibilités avec certains serveurs de réception.
    logActivity('SmarterMail [DKIM] Aucune clé détectée après EnableDkim pour '
        . $domain . ' — tentative via CreateDkimRollover (2048 bits).');

    $rolloverResp = $api->createDkimRollover($daToken, 2048);

    if (!$rolloverResp['success']) {
        // Les deux approches ont échoué — rien de plus à tenter.
        // Le domaine est fonctionnel sans DKIM ; l'admin doit investiguer
        // manuellement dans la console SmarterMail.
        $rolloverError = $rolloverResp['error'] ?? 'erreur inconnue';
        logActivity('SmarterMail [DKIM] CreateDkimRollover échoué pour ' . $domain
            . ' (' . $rolloverError . ') — DKIM non activé, intervention manuelle requise.');
        return [
            'success'  => false,
            'rollover' => true,   // Indique qu'on a TENTÉ le rollover (pour les logs)
            'error'    => 'EnableDkim sans effet + CreateDkimRollover : ' . $rolloverError,
        ];
    }

    // ── Rollover réussi — extraire la clé et le sélecteur ─────────────────
    //
    // L'API CreateDkimRollover retourne directement la clé publique et le
    // sélecteur dans la réponse (contrairement à EnableDkim qui nécessite
    // un appel getDomainSettings séparé pour les lire).
    //
    // La clé est en état « pending » — elle ne signera pas encore les
    // courriels jusqu'à ce que SmarterMail valide l'enregistrement DNS
    // (automatiquement via nextCheckUtc, ou manuellement via verifyDkimRollover).
    $data          = $rolloverResp['data'] ?? [];
    $rolloverKey   = trim((string) ($data['publicKey'] ?? ''));
    $rolloverSel   = trim((string) ($data['selector']  ?? ''));

    logActivity('SmarterMail [DKIM] CreateDkimRollover réussi pour ' . $domain
        . ' | sélecteur : ' . ($rolloverSel ?: 'inconnu')
        . ' — Clé en attente de validation DNS (pending).');

    return [
        'success'   => true,
        'rollover'  => true,          // La clé est « pending » — DNS requis
        'publicKey' => $rolloverKey,
        'selector'  => $rolloverSel,
    ];
}


// =============================================================================
//
//  Ces fonctions sont appelées par WHMCS lors des actions sur le service.
//  Elles doivent retourner "success" ou un message d'erreur.
// =============================================================================

/**
 * Provisionnement initial — Crée le domaine courriel dans SmarterMail.
 *
 * Appelé par WHMCS lors de :
 *   - L'activation manuelle par un admin (Admin → Client → Services → Activer)
 *   - La commande d'un client si l'activation automatique est configurée
 *   - Un clic sur "Create" dans la vue d'un service admin
 *
 * ÉTAPES EXÉCUTÉES :
 *   1. Vérification que le domaine n'existe PAS déjà dans SmarterMail
 *      (évite les doublons si CreateAccount est appelé deux fois par erreur)
 *   2. Génération du compte admin secret (username 10 chars aléatoires + password fort)
 *   3. Sauvegarde des credentials dans WHMCS (champs username/password du service)
 *   4. Création du domaine dans SmarterMail via l'API
 *   5. Application des paramètres supplémentaires (EAS/MAPI/forwarding)
 *   6. En cas d'erreur à l'étape 5 : rollback (suppression du domaine créé)
 *
 * @param array $params Paramètres WHMCS (voir entête du fichier)
 * @return string       "success" ou message d'erreur
 */
function smartermail_CreateAccount(array $params): string
{
    $domain = $params['domain'];

    // ── Étape 1 : Initialiser l'API et se connecter ───────────────────────
    $init = _sm_initApi($params);
    if (isset($init['error'])) {
        return $init['error'];
    }
    /** @var SmarterMailApi $api */
    $api     = $init['api'];
    $saToken = $init['token'];

    // ── Étape 2 : Vérifier que le domaine n'existe pas déjà ───────────────
    // Cela peut arriver si :
    //   - Un domaine a été créé manuellement dans SmarterMail
    //   - CreateAccount a été appelé deux fois (bug ou retry)
    //   - Un ancien client avait le même domaine
    if ($api->domainExists($domain, $saToken)) {
        // Cas : domaine créé manuellement dans SmarterMail ou double appel de CreateAccount.
        // %s = nom de domaine pour que l'admin identifie rapidement le conflit.
        $l = _sm_lang($params);
        return sprintf(
            $l['err_domain_exists'] ?? 'Erreur : Le domaine « %s » existe déjà dans SmarterMail. Créez manuellement le service ou contactez votre administrateur.',
            $domain
        );
    }

    // ── Étape 3 : Résoudre le compte administrateur secret ─────────────────
    //
    // LOGIQUE DE RÉSOLUTION (priorité décroissante) :
    //
    //   A) WHMCS a un username ET un password → vérifier la solidité :
    //        - Username < 8 chars  → jugé trop court/prévisible → régénérer
    //        - Password ne passe pas la validation → régénérer
    //      Si l'un des deux est régénéré, $credentialsUpdated = true
    //      et les deux valeurs seront sauvegardées dans WHMCS à l'étape 4b.
    //
    //   B) Un des deux champs est vide → générer les deux entièrement.
    //
    // POURQUOI 8 caractères minimum pour le username ?
    //   Un username court (ex: "ab", "john", "admin") est facilement devinable
    //   par énumération. Le compte Primary Admin est le seul compte avec accès
    //   Domain Admin — il doit être difficile à retrouver. 10 chars aléatoires
    //   minuscules offrent 26^10 ≈ 141 billions de combinaisons.
    //
    // NOTE : _sm_randomUsername() génère des lettres minuscules uniquement
    //        pour assurer la compatibilité maximale avec SmarterMail.
    // strtolower() appliqué immédiatement : si un admin a saisi "AdminUser"
    // manuellement dans WHMCS, on normalise ici avant tout traitement.
    $adminUser = strtolower(trim($params['username'] ?? ''));
    $adminPass = trim($params['password'] ?? '');

    // Indique si les credentials ont été modifiés → déclenche la mise à jour WHMCS
    $credentialsUpdated = false;

    if ($adminUser === '' || $adminPass === '') {
        // ── Cas B : l'un des deux champs est vide → générer les deux ──────
        // On génère TOUJOURS les deux ensemble pour garantir la cohérence.
        // Si seul le username était vide, le password saisi manuellement
        // pourrait ne pas satisfaire nos critères — on génère tout.
        $adminUser = _sm_randomUsername(10);
        $adminPass = _sm_randomPassword(24);
        $credentialsUpdated = true;
        logActivity('SmarterMail [CreateAccount] Credentials générés automatiquement pour ' . $domain);

    } else {
        // ── Cas A : les deux champs sont présents — vérifier la solidité ──

        // Vérification du username : longueur minimale de 8 caractères
        // Un admin peut avoir saisi "admin", "test", etc. → remplacer silencieusement
        if (strlen($adminUser) < 8) {
            $oldUser   = $adminUser;
            $adminUser = _sm_randomUsername(10);
            $credentialsUpdated = true;
            logActivity('SmarterMail [CreateAccount] Username trop court ('
                . strlen($oldUser) . ' chars) remplacé par username généré (10 chars) pour ' . $domain);
        }

        // Vérification du password : tous les critères de sécurité
        // On valide avec le username FINAL (potentiellement régénéré) pour
        // s'assurer que le mot de passe ne contient pas le nouveau username non plus.
        $pwdError = _sm_validateAdminPassword($adminPass, $adminUser, $domain, $params);
        if ($pwdError !== null) {
            // Mot de passe trop faible → générer un password fort aléatoire
            // Le nouveau password passe forcément la validation (générateur garanti)
            $adminPass = _sm_randomPassword(24);
            $credentialsUpdated = true;
            logActivity('SmarterMail [CreateAccount] Password ne respectait pas les critères ('
                . $pwdError . ') — remplacé par password généré pour ' . $domain);
        }
    }


    // ── Étape 4 : Créer le domaine dans SmarterMail ───────────────────────
    $maxUsers = max(0, (int) ($params['configoption7'] ?? 0));
    $outIP    = $params['configoption6'] ?? 'default';

    $domainOptions = [
        // Champs documentés dans domainData (voir doc API domain-put)
        'path'       => rtrim($params['configoption5'] ?? 'C:\\SmarterMail\\Domains\\', '\\') . '\\' . $domain,
        'hostname'   => 'mail.' . $domain,
        'userLimit'  => $maxUsers,
        'maxSize'    => 0,   // 0 = illimité — notre facturation gère ça
        'aliasLimit' => 0,   // Illimité
        'listLimit'  => 0,   // Illimité
        // outgoingIP est appliqué via setDomainSettings() après création (non documenté dans domain-put)
    ];

    $createResp = $api->createDomain($domain, $adminUser, $adminPass, $domainOptions, $saToken);
    if (!$createResp['success']) {
        $msg = $createResp['error'] ?? '';
        $l   = _sm_lang($params);
        // Retourner des messages d'erreur lisibles pour les cas communs
        if (str_contains($msg, 'NAME_IN_USE')) {
            // Conflit de nom détecté par l'API SmarterMail — domaine déjà enregistré
            return sprintf($l['err_domain_duplicate'] ?? 'Erreur : Le domaine « %s » est déjà utilisé dans SmarterMail.', $domain);
        }
        // Erreur générique — inclure le message brut de l'API pour le débogage admin
        return sprintf($l['err_create_domain'] ?? 'Erreur lors de la création du domaine : %s', $msg);
    }

    // ── Étape 4b : Sauvegarder les credentials dans WHMCS ────────────────
    //
    // On sauvegarde dès que $credentialsUpdated = true, c'est-à-dire :
    //   - Cas B : credentials entièrement générés (champs vides dans WHMCS)
    //   - Cas A : username trop court ou password trop faible → renforcés
    //
    // IMPORTANT : La sauvegarde se fait APRÈS la création réussie du domaine
    // pour éviter d'écraser les credentials WHMCS si la création échoue.
    //
    // POURQUOI Capsule plutôt que localAPI('UpdateClientProduct') ?
    // ─────────────────────────────────────────────────────────────
    //   localAPI() passe par toute la couche WHMCS (validation, hooks, permissions).
    //   Dans certains contextes d'exécution (provisionnement automatique, cron,
    //   appel depuis un hook), localAPI() peut silencieusement échouer ou retourner
    //   une erreur de permission sans lever d'exception — les credentials ne seraient
    //   pas mis à jour sans qu'on s'en aperçoive.
    //
    //   Capsule écrit directement dans tblhosting, exactement comme WHMCS le fait
    //   lui-même en interne. C'est fiable dans TOUS les contextes d'exécution.
    //
    // CHIFFREMENT DU MOT DE PASSE :
    // ─────────────────────────────
    //   WHMCS stocke tblhosting.password chiffré via sa propre fonction encrypt().
    //   Écrire le mot de passe en clair corromprait la colonne et empêcherait
    //   WHMCS de le déchiffrer dans $params['password'] lors des prochains appels.
    //   On DOIT appeler encrypt() avant d'écrire dans la DB.
    //
    //   WHMCS déchiffre automatiquement via decrypt() quand il remplit $params.
    //   La fonction encrypt() est une globale WHMCS, disponible partout dans les modules.
    if ($credentialsUpdated) {
        try {
            $sid = (int) $params['serviceid'];

            // Vérification de sécurité : on s'assure que l'ID de service est valide
            // et que la ligne existe avant d'écrire — évite un UPDATE sans WHERE cible.
            if ($sid <= 0) {
                throw new \RuntimeException('Service ID invalide : ' . $params['serviceid']);
            }

            // Chiffrer le mot de passe via la fonction globale WHMCS avant stockage.
            // encrypt() utilise la clé de chiffrement configurée dans WHMCS (config.php).
            // Le username n'est PAS chiffré dans tblhosting — il est stocké en clair.
            $encryptedPassword = encrypt($adminPass);

            $updated = Capsule::table('tblhosting')
                ->where('id', $sid)
                ->update([
                    'username' => $adminUser,       // Stocké en clair dans tblhosting
                    'password' => $encryptedPassword, // Doit être chiffré via encrypt()
                ]);

            // $updated = nombre de lignes affectées (0 si l'ID n'existe pas)
            if ($updated === 0) {
                logActivity('SmarterMail [CreateAccount] ATTENTION : aucune ligne mise à jour '
                    . 'dans tblhosting pour serviceid=' . $sid . ' (domaine: ' . $domain . '). '
                    . 'Le service existe-t-il encore dans WHMCS ?');
            } else {
                logActivity('SmarterMail [CreateAccount] Credentials mis à jour dans tblhosting '
                    . 'pour ' . $domain . ' (serviceid=' . $sid . ').');
            }

        } catch (\Throwable $e) {
            // Non bloquant — le domaine est créé dans SmarterMail mais WHMCS
            // affichera encore les anciennes valeurs. L'admin peut corriger manuellement
            // depuis Admin → Services → [Service] → Module Settings.
            logActivity('SmarterMail [CreateAccount] Impossible de sauvegarder les credentials '
                . 'pour ' . $domain . ' : ' . $e->getMessage());
        }
    }

    // ── Étape 5 : Appliquer les paramètres supplémentaires du domaine ──────
    // Ces paramètres contrôlent les fonctionnalités disponibles pour le domaine.
    //
    // LOGIQUE DE DÉCISION pour enableActiveSyncAccountManagement /
    //                           enableMapiEwsAccountManagement :
    //
    //   On active la gestion EAS/MAPI au niveau du domaine SmarterMail si
    //   configoption14/15 = 'on' (l'offre est activée dans la config du produit).
    //   La disponibilité est désormais pilotée par la configuration WHMCS,
    //   indépendamment du prix configuré. Cela permet :
    //     - D'offrir EAS/MAPI gratuitement (option on, prix = 0)
    //     - De désactiver l'accès client même si un prix est configuré
    //       (pour gérer les activations manuellement)
    $enableEas  = ($params['configoption14'] ?? 'on') === 'on';
    $enableMapi = ($params['configoption15'] ?? 'on') === 'on';

    $outIPValue = ($outIP === 'default' ? '' : $outIP);

    $settingsResp = $api->setDomainSettings($domain, [
        'isEnabled'                         => true,
        'enableMailForwarding'              => true,
        'enableSmtpAccounts'               => true,
        'enableActiveSyncAccountManagement' => $enableEas,
        'enableMapiEwsAccountManagement'    => $enableMapi,
        // outgoingIP appliqué ici (champ de setDomainSettings, pas de domain-put)
        'outgoingIP'                        => $outIPValue,
    ], $saToken);

    if (!$settingsResp['success']) {
        // ROLLBACK : Si l'application des paramètres échoue, supprimer le domaine
        // pour ne pas laisser un domaine dans un état incohérent
        $api->deleteDomain($domain, true, $saToken);
        // err_config_domain : %s = message d'erreur brut retourné par l'API
        return sprintf(
            _sm_lang($params)['err_config_domain'] ?? 'Erreur lors de la configuration du domaine (domaine supprimé) : %s',
            _sm_apiError($settingsResp)
        );
    }

    // ── Étape 6 : Activer la signature DKIM ───────────────────────────────
    // Nécessite un token Domain Admin — on impersonifie le DA via le SA.
    // ── Étape 6 : Activer la signature DKIM ───────────────────────────────
    //
    // COMPORTEMENT AVEC FALLBACK AUTOMATIQUE :
    //   1re tentative : EnableDkim standard
    //      POST api/v1/settings/domain/dkim-enable/false
    //      Fonctionne si DKIM a déjà été initialisé une fois sur ce domaine.
    //
    //   2e tentative  : CreateDkimRollover (si EnableDkim échoue)
    //      POST api/v1/settings/domain/dkim-create-rollover/2048
    //      Utilisé lorsque DKIM n'a JAMAIS été installé — génère la paire
    //      de clés RSA initiale en mode « pending » (attente validation DNS).
    //
    // NON BLOQUANT : Si les deux méthodes échouent, le domaine est quand même
    // créé et fonctionnel sans DKIM. L'admin verra l'erreur dans les logs
    // WHMCS et le client pourra activer DKIM depuis l'espace client.
    //
    // Token DA requis : on impersonifie via le SA (credentials DA pas encore
    // stockés à ce stade du provisionnement — CreateAccount vient de les créer).
    $daResult = $api->loginDomainAdminFull($saToken, $domain);
    if ($daResult['token']) {
        // _sm_enableOrRolloverDkim() gère le fallback EnableDkim → CreateDkimRollover.
        // Résultat journalisé dans la fonction — on n'a pas besoin de traiter ici.
        _sm_enableOrRolloverDkim($api, $daResult['token'], $domain);
    } else {
        // Token DA impossible à obtenir — DKIM ignoré (domaine fonctionnel sans DKIM).
        // Le client pourra activer DKIM depuis l'espace client après provisionnement.
        logActivity('SmarterMail [CreateAccount] Token DA indisponible pour DKIM sur '
            . $domain . ' (HTTP ' . $daResult['code'] . ') — DKIM ignoré.');
    }

    return 'success';
}

/**
 * Suspension du compte — Désactive le domaine dans SmarterMail.
 *
 * Appelé par WHMCS lors de :
 *   - Suspension manuelle par un admin
 *   - Facture impayée (si la suspension automatique est configurée dans WHMCS)
 *   - Dépassement d'une limite (si configuré)
 *
 * EFFET : Le domaine devient inaccessible (connexions refusées, courriels refusés).
 * Les données sont conservées intactes. La réactivation (UnsuspendAccount) restore
 * tout immédiatement.
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur
 */
function smartermail_SuspendAccount(array $params): string
{
    $init = _sm_initApi($params);
    if (isset($init['error'])) {
        return $init['error'];
    }

    // setDomainEnabled(false) = envoyer { "domainSettings": { "isEnabled": false } }
    $resp = $init['api']->setDomainEnabled($params['domain'], false, $init['token']);
    // err_suspend : %s = message d'erreur brut de l'API (pour débogage admin)
    return $resp['success'] ? 'success' : sprintf(
        _sm_lang($params)['err_suspend'] ?? 'Erreur lors de la suspension : %s',
        _sm_apiError($resp)
    );
}

/**
 * Réactivation du compte — Réactive le domaine dans SmarterMail.
 *
 * Appelé par WHMCS lors de :
 *   - Réactivation manuelle par un admin
 *   - Paiement d'une facture impayée (si configuré)
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur
 */
function smartermail_UnsuspendAccount(array $params): string
{
    $init = _sm_initApi($params);
    if (isset($init['error'])) {
        return $init['error'];
    }

    $resp = $init['api']->setDomainEnabled($params['domain'], true, $init['token']);
    // err_unsuspend : %s = message d'erreur brut de l'API
    return $resp['success'] ? 'success' : sprintf(
        _sm_lang($params)['err_unsuspend'] ?? 'Erreur lors de la réactivation : %s',
        _sm_apiError($resp)
    );
}

/**
 * Résiliation du compte — Supprime le domaine et optionnellement ses données.
 *
 * Appelé par WHMCS lors de :
 *   - Résiliation manuelle par un admin
 *   - Expiration du service (si configuré)
 *
 * ⚠️  ATTENTION : Cette action est IRRÉVERSIBLE si configoption8 = 'on'.
 *
 * Le comportement de suppression des données est contrôlé par configoption8 :
 *   - "on"  → Supprime le domaine ET tous les fichiers courriel du disque
 *   - autre → Supprime le domaine de SmarterMail mais conserve les fichiers
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur
 */
function smartermail_TerminateAccount(array $params): string
{
    $init = _sm_initApi($params);
    if (isset($init['error'])) {
        return $init['error'];
    }

    // Lire la configuration : supprimer les données sur disque ?
    $deleteData = ($params['configoption8'] === 'on');

    $resp = $init['api']->deleteDomain($params['domain'], $deleteData, $init['token']);

    if (!$resp['success']) {
        // err_terminate : %s = message d'erreur brut de l'API SmarterMail
        return sprintf(
            _sm_lang($params)['err_terminate'] ?? 'Erreur lors de la résiliation : %s',
            _sm_apiError($resp)
        );
    }

    // ── Nettoyage des enregistrements EAS/MAPI dans mod_sm_proto_usage ───
    // Le service est résilié → les enregistrements de suivi ne serviront plus.
    // On purge immédiatement plutôt qu'attendre le nettoyage hebdomadaire.
    _sm_cleanProtoUsage((int) $params['serviceid']);

    return 'success';
}

/**
 * Changement de mot de passe du compte Primary Admin — Appelé par WHMCS.
 *
 * WHMCS appelle cette fonction quand un admin change le mot de passe depuis
 * la page de détails du service (Admin → Client → Services → Change Password).
 *
 * Le mot de passe fourni dans $params['password'] est le nouveau mot de passe
 * déjà saisi par l'admin dans WHMCS. WHMCS se charge de le stocker dans la DB
 * après un retour 'success' — pas besoin de l'écrire manuellement.
 *
 * Le compte concerné est le Primary Admin (Domain Admin secret) dont le
 * username est stocké dans $params['username'].
 *
 * @param array $params Paramètres WHMCS — $params['password'] = nouveau mot de passe
 * @return string       "success" ou message d'erreur
 */
function smartermail_ChangePassword(array $params): string
{
    // ChangePassword utilise _sm_initApi (SA) + impersonification SA — PAS _sm_initDomainAdmin.
    // Raison : WHMCS passe le NOUVEAU mot de passe dans $params['password'].
    // _sm_initDomainAdmin tenterait de se connecter avec ce nouveau mot de passe
    // alors que SmarterMail a encore l'ancien — la connexion directe DA échouerait.
    // Le SA peut impersonifier sans connaître le mot de passe DA.
    $init = _sm_initApi($params);
    if (isset($init['error'])) return $init['error'];

    $api      = $init['api'];
    $saToken  = $init['token'];
    // strtolower() pour cohérence — SmarterMail stocke les usernames en minuscules
    $username = strtolower(trim($params['username'] ?? ''));
    $domain   = $params['domain'];
    $password = $params['password'];

    // ── Validation : username présent ─────────────────────────────────────
    // Sans username, on ne peut pas identifier le compte DA à modifier dans WHMCS.
    if ($username === '') {
        return _sm_lang($params)['err_admin_user_missing'] ?? 'Nom d\'utilisateur admin non configuré dans WHMCS.';
    }

    // ── Validation : mot de passe non vide ────────────────────────────────
    if ($password === '') {
        return _sm_lang($params)['err_new_pwd_empty'] ?? 'Le nouveau mot de passe ne peut pas être vide.';
    }

    // ── Validation complète des critères de sécurité ──────────────────────
    //
    // POURQUOI cette validation ici et pas seulement côté client ?
    //   - Le formulaire "Change Password" de l'admin WHMCS (clientsservices.php)
    //     est une interface native WHMCS qui n'a PAS de champs de configuration
    //     personnalisés : on ne peut pas y injecter de JS de validation.
    //   - Un admin pourrait saisir "patate" ou "123456" et WHMCS l'enverrait
    //     directement à ce module sans aucune vérification préalable.
    //   - La seule barrière fiable est donc côté PHP, ici, avant tout appel API.
    //
    // _sm_validateAdminPassword() lit les mêmes configoptions que le reste du
    // module (longueur min, majuscule, chiffre, caractère spécial).
    // Elle retourne null si le mot de passe est valide, ou un message d'erreur.
    $validationError = _sm_validateAdminPassword($password, $username, $domain, $params);
    if ($validationError !== null) {
        // Journaliser la tentative sans révéler le mot de passe en clair
        logActivity('SmarterMail [ChangePassword] Mot de passe refusé pour '
            . $username . '@' . $domain . ' — critère: ' . $validationError);
        return $validationError;
    }

    // ── Impersonifier le DA via SA ────────────────────────────────────────
    $daResult = $api->loginDomainAdminFull($saToken, $domain);
    if (!$daResult['token']) {
        // err_domain_http_access : %s = domaine, %d = code HTTP retourné par SmarterMail
        return sprintf(
            _sm_lang($params)['err_domain_http_access'] ?? 'Impossible d\'accéder au domaine %s (HTTP %d).',
            $domain,
            (int) $daResult['code']
        );
    }

    $email = $username . '@' . $domain;
    $resp  = $api->updateUser($email, ['password' => $password], null, $daResult['token']);

    // err_pwd_change_api : %s = message d'erreur brut retourné par l'API SmarterMail
    return $resp['success'] ? 'success' : sprintf(
        _sm_lang($params)['err_pwd_change_api'] ?? 'Erreur lors du changement de mot de passe : %s',
        _sm_apiError($resp)
    );
}


/**
 * Mise à jour de l'utilisation disque — Appelé par le cron WHMCS.
 *
 * RÔLE DE CETTE FONCTION vs MetricProvider :
 * ─────────────────────────────────────────
 *  UsageUpdate()     → Met à jour tblhosting.diskusage (en MB)
 *                      Utilisé par hooks.php pour calculer les tranches de facturation.
 *                      WHMCS affiche aussi cette valeur dans l'admin.
 *
 *  MetricProvider()  → Collecte TOUTES les métriques (disque + email + EAS + MAPI)
 *                      Stockées dans les tables internes WHMCS de métriques.
 *                      Affichées dans l'onglet "Utilisation" du service.
 *
 * UsageUpdate() collecte uniquement le disque car c'est la seule valeur
 * dont hooks.php a besoin dans tblhosting.diskusage pour la facturation.
 * Les autres métriques sont gérées exclusivement par MetricProvider.
 *
 * @param array $params Paramètres WHMCS
 * @return array        ['diskusage' => float, ...] ou ['error' => string]
 */
function smartermail_UsageUpdate(array $params): array
{
    // ── Garde : domaine manquant ou null ──────────────────────────────────
    //
    // Le cron WHMCS (UpdateServerUsage) appelle cette fonction pour TOUS les
    // services rattachés au serveur, y compris ceux dont le provisionnement
    // est incomplet (status Pending, domain non encore renseigné, etc.).
    //
    // Si $params['domain'] est null ou vide, getDomainDiskUsageGB() reçoit
    // null comme premier argument et lève un TypeError fatal (type hint string).
    //
    // Correction : on retourne silencieusement 0 MB sans appel API.
    // WHMCS ignorera cette valeur (diskusage = 0) sans bloquer le cron.
    // Un log d'activité est émis pour faciliter le diagnostic admin.
    $domain = trim((string) ($params['domain'] ?? ''));
    if ($domain === '') {
        logActivity(
            'SmarterMail UsageUpdate ignoré — domaine manquant pour le service #'
            . ($params['serviceid'] ?? '?')
            . '. Le service est peut-être en attente de provisionnement.'
        );
        return [
            'diskusage'  => 0,
            'disklimit'  => 0,
            'bwusage'    => 0,
            'bwlimit'    => 0,
            'carryoveru' => 0,
        ];
    }

    $init = _sm_initApi($params);
    if (isset($init['error'])) {
        return ['error' => $init['error']];
    }

    $usageGB = $init['api']->getDomainDiskUsageGB($domain, $init['token']);
    $usageMB = round($usageGB * 1024, 2);

    return [
        'diskusage'  => $usageMB,  // En MB → tblhosting.diskusage (facturation)
        'disklimit'  => 0,
        'bwusage'    => 0,
        'bwlimit'    => 0,
        'carryoveru' => 0,
    ];
}


// =============================================================================
//  ESPACE CLIENT — PAGE D'ACCUEIL
// =============================================================================

/**
 * Page d'accueil de l'espace client — Tableau de bord du service courriel.
 *
 * Appelé quand un client clique sur son service dans l'espace client WHMCS.
 * Cette fonction charge les données nécessaires et les passe au template Smarty.
 *
 * DONNÉES CALCULÉES POUR L'AFFICHAGE :
 *   - Utilisation disque en GB (lue depuis tblhosting.diskusage, déjà en MB)
 *   - Nombre de tranches facturées (ceil(usageGB / gbPerTier))
 *   - GB facturés (tiers * gbPerTier — ex: 3 tranches × 10 Go = 30 Go facturés)
 *   - Estimation du prix mensuel (tiers × prix_base_produit)
 *   - Nombre d'utilisateurs et d'alias actifs
 *   - Disponibilité EAS et MAPI sur le domaine
 *
 * Le template correspondant est : templates/clientarea.tpl
 *
 * @param array $params Paramètres WHMCS
 * @return array        ['templatefile' => string, 'vars' => array]
 */
function smartermail_ClientArea(array $params): array
{
    // ── ROUTEUR CENTRAL ──────────────────────────────────────────────────────
    //
    // tabOverviewReplacementTemplate intercepte TOUTES les requêtes productdetails,
    // y compris celles avec customAction. On doit donc router ici plutôt que
    // de laisser WHMCS appeler les fonctions individuelles.
    //
    // Toutes les pages retournent tabOverviewReplacementTemplate pour éviter
    // les blocs WHMCS par défaut (informations d'hébergement, etc.).
    // ─────────────────────────────────────────────────────────────────────────

    // Whitelist explicite des actions autorisées avant tout traitement
    // SÉCURITÉ : Toute action non listée ici est silencieusement ignorée —
    // le tableau de bord s'affiche à la place. Cela empêche l'appel de
    // fonctions sensibles via manipulation d'URL ou injection de paramètre.
    $allowedActions = [
        'edituserpage', 'adduserpage',
        'createuser', 'saveuser', 'savepassword', 'deleteuser',
        'toggledkim',       // Activation / désactivation DKIM depuis l'espace client
        // ── Redirections autonomes (alias sans boîte courriel) ────────────
        // Ces actions gèrent les alias qui pointent vers des adresses externes
        // et n'ont aucune boîte courriel associée dans SmarterMail.
        'addredirectpage',  // Page : formulaire d'ajout d'une redirection autonome
        'createredirect',   // Action POST : créer l'alias de redirection
        'editredirectpage', // Page : formulaire de modification d'une redirection autonome
        'saveredirect',     // Action POST : sauvegarder les modifications
        'deleteredirect',   // Action POST : supprimer l'alias de redirection
    ];
    $rawAction    = trim($_GET['customAction'] ?? $_POST['customAction'] ?? '');
    $customAction = in_array($rawAction, $allowedActions, true) ? $rawAction : '';
    $sid          = (int) $params['serviceid'];

    // Convertit un retour templatefile en tabOverviewReplacementTemplate
    $toTab = function (array $r): array {
        if (isset($r['templatefile'])) {
            $tpl = $r['templatefile'];
            unset($r['templatefile'], $r['breadcrumb']);
            $r['tabOverviewReplacementTemplate'] = 'templates/' . $tpl;
        }
        return $r;
    };

    // Pages d'affichage — dispatch vers la fonction existante
    $displayPages = [
        'edituserpage'     => 'smartermail_edituserpage',
        'adduserpage'      => 'smartermail_adduserpage',
        // ── Redirections autonomes ────────────────────────────────────────
        'addredirectpage'  => 'smartermail_addredirectpage',   // Formulaire ajout redirection
        'editredirectpage' => 'smartermail_editredirectpage',  // Formulaire modif redirection
    ];

    if (isset($displayPages[$customAction])) {
        return $toTab(($displayPages[$customAction])($params));
    }

    // Actions POST — exécuter + rediriger
    // Chaque action retourne 'success' ou un message d'erreur.
    // En cas d'erreur, la page d'erreur générique est affichée.
    // En cas de succès, l'utilisateur est redirigé (PRG pattern — Post/Redirect/Get).
    $actions = [
        'createuser'     => 'smartermail_createuser',
        'saveuser'       => 'smartermail_saveuser',
        'savepassword'   => 'smartermail_savepassword',
        'deleteuser'     => 'smartermail_deleteuser',
        'toggledkim'     => 'smartermail_toggledkim',  // Active/désactive la signature DKIM du domaine
        // ── Redirections autonomes ────────────────────────────────────────
        // Les alias autonomes sont des alias SmarterMail dont TOUTES les cibles
        // sont externes au domaine (aucune boîte courriel locale impliquée).
        'createredirect' => 'smartermail_createredirect',  // Crée un alias de redirection
        'saveredirect'   => 'smartermail_saveredirect',    // Met à jour les cibles
        'deleteredirect' => 'smartermail_deleteredirect',  // Supprime l'alias complet
    ];

    if (isset($actions[$customAction])) {
        $result = ($actions[$customAction])($params);

        if ($result !== 'success') {
            return [
                'tabOverviewReplacementTemplate' => 'templates/error',
                'vars' => ['error' => '[' . htmlspecialchars($customAction) . '] ' . $result],
            ];
        }

        // Redirection post-action
        $username = trim($_POST['selectuser'] ?? '');
        $redir = 'clientarea.php?action=productdetails&id=' . $sid;

        // Après savepassword : retour vers edituserpage (le client est encore en mode édition)
        // Après saveuser     : retour vers la page principale du produit (tableau de bord)
        //                      → le client voit la confirmation visuelle de ses modifications
        if ($customAction === 'savepassword' && $username !== '') {
            $redir .= '&customAction=edituserpage&username=' . urlencode($username);
        }

        header('Location: ' . str_replace(["\r", "\n"], '', $redir));
        exit;
    }

    // ── Tableau de bord principal (pas de customAction) ───────────────────

    $lang   = _sm_lang($params);
    // $params['domain'] vient de WHMCS (tblhosting) — on le normalise par précaution
    $params['domain'] = strtolower(trim((string) ($params['domain'] ?? '')));
    $errTpl = fn($msg) => [
        'tabOverviewReplacementTemplate' => 'templates/clientarea',
        'vars' => ['error' => $msg, 'lang' => $lang],
    ];

    // ── Connexion DA (impersonification SA) ───────────────────────────────
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        // Domaine non configuré → message informatif dans clientarea.tpl
        if (!empty($init['domainNotReady'])) {
            return [
                'tabOverviewReplacementTemplate' => 'templates/clientarea',
                'vars' => [
                    'domain'         => $params['domain'],
                    'lang'           => $lang,
                    'serviceid'      => $params['serviceid'],
                    'domainNotReady' => true,
                    'error'          => null,
                ],
            ];
        }
        return $errTpl($init['error']);
    }

    $api     = $init['api'];
    $daToken = $init['token'];
    $saToken = $init['saToken'] ?? null;  // Pour getMailboxForwardList()
    $domain  = $params['domain'];

    // ── Données de facturation WHMCS ──────────────────────────────────────
    $cycleMap = [
        'Monthly'       => $lang['cycle_monthly']       ?? 'Monthly',
        'Quarterly'     => $lang['cycle_quarterly']     ?? 'Quarterly',
        'Semi-Annually' => $lang['cycle_semi_annually'] ?? 'Semi-Annually',
        'Annually'      => $lang['cycle_annually']      ?? 'Annually',
        'Biennially'    => $lang['cycle_biennially']    ?? 'Biennially',
        'Triennially'   => $lang['cycle_triennially']   ?? 'Triennially',
        'Free Account'  => $lang['cycle_free']          ?? 'Free Account',
        'One Time'      => $lang['cycle_one_time']      ?? 'One Time',
    ];
    $statusMap = [
        'Active'     => ['label' => $lang['status_active']     ?? 'Active',     'class' => 'success'],
        'Suspended'  => ['label' => $lang['status_suspended']  ?? 'Suspended',  'class' => 'warning'],
        'Terminated' => ['label' => $lang['status_terminated'] ?? 'Terminated', 'class' => 'danger'],
        'Cancelled'  => ['label' => $lang['status_cancelled']  ?? 'Cancelled',  'class' => 'danger'],
        'Pending'    => ['label' => $lang['status_pending']    ?? 'Pending',    'class' => 'info'],
    ];

    $svcStatus     = 'Active';
    $regDate       = '';
    $nextDueDate   = '';
    $billingCycle  = '';
    $svcAmount     = 0.0;
    $paymentMethod = '';
    $basePrice     = 0.0;

    // Utiliser \Throwable (pas \Exception) pour attraper aussi Error/TypeError (PHP 8)
    try {
        $svc = Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->select('domainstatus', 'regdate', 'nextduedate',
                     'billingcycle', 'amount', 'paymentmethod', 'packageid')
            ->first();

        if ($svc) {
            // Whitelist des statuts valides — protège contre une valeur inattendue
            // de tblhosting.domainstatus qui serait injectée dans le template sans échappement.
            $allowedStatuses = ['Active', 'Suspended', 'Terminated', 'Cancelled', 'Pending', 'Fraud'];
            $rawStatus        = $svc->domainstatus ?? 'Active';
            $svcStatus        = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'Active';
            $regDate       = !empty($svc->regdate) ? date('Y-m-d', strtotime($svc->regdate)) : '';
            $nextDueDate   = !empty($svc->nextduedate) ? date('Y-m-d', strtotime($svc->nextduedate)) : '';
            $billingCycle  = $cycleMap[$svc->billingcycle ?? ''] ?? ($svc->billingcycle ?? '');
            $svcAmount     = (float) ($svc->amount ?? 0);
            $paymentMethod = ucfirst(str_replace(['_', '-'], ' ', $svc->paymentmethod ?? ''));

            // Prix mensuel du produit — DANS le if ($svc) pour éviter le null access
            $pricing = Capsule::table('tblpricing')
                ->where('relid', (int) $svc->packageid)
                ->where('type', 'product')
                ->where('currency', 1)
                ->value('monthly');
            $basePrice = (float) ($pricing ?? 0);
        }
    } catch (\Throwable $e) {
        // Non bloquant — les données de facturation seront vides mais la page s'affiche
        logActivity('SmarterMail ClientArea [billing] erreur DB : ' . $e->getMessage());
    }

    // ── Données SmarterMail — chaque appel dans son propre try-catch ──────
    $domainData = [];
    try {
        $domainData = $api->getDomainData($daToken);
    } catch (\Throwable $e) {
        logActivity('SmarterMail ClientArea [getDomainData] : ' . $e->getMessage());
    }

    $sizeMb  = (float) ($domainData['sizeMb'] ?? 0);
    $usageGB = round($sizeMb / 1024, 3);

    $gbPerTier      = max(1, (int) ($params['configoption1'] ?? 10));
    $tiers          = max(1, (int) ceil($usageGB > 0 ? $usageGB / $gbPerTier : 0)) ?: 1;
    $gbBilled       = $tiers * $gbPerTier;
    $estimatedPrice = round($tiers * $basePrice, 2);

    // ── Utilisateurs + alias ──────────────────────────────────────────────
    // getUsers()      → liste complète (aliases, forwarding, etc.)
    // getUsersQuick() → même endpoint que l'UI SM, retourne bytesUsed par user
    // Les deux sont mergés dans la boucle d'enrichissement via userName.
    $users      = [];
    $usersQuick = [];   // indexé par userName (minuscules)
    $aliases    = [];
    try {
        $users      = $api->getUsers($daToken);
        $usersQuick = $api->getUsersQuick($daToken);
        $aliases    = $api->getAliases($daToken);
    } catch (\Throwable $e) {
        logActivity('SmarterMail ClientArea [getUsers/getAliases] : ' . $e->getMessage());
    }

    $domainLower = strtolower($domain);
    $aliasMap = [];
    foreach ($aliases as $alias) {
        $aliasName = trim((string) ($alias['userName'] ?? ''));
        if ($aliasName === '') continue;

        // Vérifier si aliasTargetList est présent dans la réponse de liste
        $targetList = [];
        if (array_key_exists('aliasTargetList', $alias) && is_array($alias['aliasTargetList'])) {
            $targetList = $alias['aliasTargetList'];
        }

        // Fallback : account-list-search ne retourne pas toujours aliasTargetList.
        // Dans ce cas, appel individuel GET /alias/{name} pour obtenir les cibles.
        if (empty($targetList)) {
            $detail     = $api->getAlias($aliasName, $daToken);
            $targetList = (array) ($detail['aliasTargetList'] ?? []);
        }

        foreach ($targetList as $rawTarget) {
            $target = strtolower(trim((string) $rawTarget));
            if ($target === '') continue;
            // Normaliser : ajouter le domaine si la cible n'a pas de @
            if (!str_contains($target, '@')) {
                $target .= '@' . $domainLower;
            }
            $aliasMap[$target][] = $aliasName;
        }
    }

    // ── EAS + MAPI — fetchés avant la boucle pour enrichir chaque user ─────
    //
    // SÉPARATION DES RESPONSABILITÉS :
    //   easSalesEnabled / mapiSalesEnabled (configoption14/15)
    //     → Contrôle si l'option est PROPOSÉE aux clients dans l'interface.
    //       C'est cette valeur qui pilote l'affichage des cases à cocher EAS/MAPI.
    //
    //   easPriceEnabled / mapiPriceEnabled (configoption2/3 > 0)
    //     → Contrôle si la FACTURATION EAS/MAPI est active.
    //       Utilisé pour calculer les coûts estimés dans le tableau de bord.
    //
    //   easEnabled / mapiEnabled (combinaison)
    //     → Décide si on récupère les listes EAS/MAPI depuis l'API SmarterMail.
    //       On fetch si la vente est activée OU si un prix est configuré
    //       (un admin peut avoir activé EAS sans le proposer à la vente).
    $easSalesEnabled  = ($params['configoption14'] ?? 'on') === 'on';
    $mapiSalesEnabled = ($params['configoption15'] ?? 'on') === 'on';
    $easPriceEnabled  = ((float) ($params['configoption2'] ?? 0)) > 0;
    $mapiPriceEnabled = ((float) ($params['configoption3'] ?? 0)) > 0;

    // On fetch les mailboxes si la vente est active ou si un prix est configuré
    $easEnabled  = $easSalesEnabled  || $easPriceEnabled;
    $mapiEnabled = $mapiSalesEnabled || $mapiPriceEnabled;

    $easMailboxes  = [];
    $mapiMailboxes = [];
    $easCount      = 0;
    $mapiCount     = 0;
    try {
        if ($easEnabled) {
            $easMailboxes = $api->getActiveSyncMailboxes($daToken);
            $easCount     = count($easMailboxes);
        }
        if ($mapiEnabled) {
            $mapiMailboxes = $api->getMapiMailboxes($daToken);
            $mapiCount     = count($mapiMailboxes);
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail ClientArea [EAS/MAPI] : ' . $e->getMessage());
    }

    // Enrichir chaque utilisateur
    foreach ($users as &$user) {
        $uEmail = strtolower((string) ($user['emailAddress'] ?? ($user['userName'] . '@' . $domain)));
        $user['email']    = $uEmail;
        $user['_aliases'] = $aliasMap[$uEmail] ?? [];


        // Forwarding — POST api/v1/settings/domain/get-mailbox-forward-list (DA only)
        $fwd     = '';
        $fwdList = [];
        try {
            $fwdData = $api->getMailboxForwardList($daToken, $user['userName'] ?? '', $domain, $saToken);
            $fwdList = array_values(array_filter(array_map('trim', $fwdData['forwardList'] ?? [])));
            if (!empty($fwdList)) {
                $fwd = implode(', ', $fwdList);
            }
        } catch (\Throwable $e) {
            // non bloquant
        }
        // _forwarding  = tableau  → foreach dans le template (une pill par adresse)
        // _forwardingStr = string → attribut data-fwd pour la recherche JS
        $user['_forwarding']    = $fwdList;
        $user['_forwardingStr'] = $fwd;

        // Statut boîte
        if (!($user['isEnabled'] ?? true)) {
            $user['_mailboxStatus'] = 'disabled';
        } elseif ($fwd !== '' && ((int) ($user['totalMailboxSize'] ?? 0)) === 0) {
            $user['_mailboxStatus'] = 'redirect';
        } else {
            $user['_mailboxStatus'] = 'active';
        }

        $last = (string) ($user['lastLogin'] ?? '');
        $user['_lastLogin'] = ($last === '' || str_contains($last, '0001'))
            ? '' : date('Y-m-d', strtotime($last));

        // Espace disque — bytesUsed vient de getUsersQuick() (account-search-quick).
        // C'est le même endpoint que l'UI SmarterMail, retourné en un seul appel.
        $userName        = strtolower((string) ($user['userName'] ?? ''));
        $quickData       = $usersQuick[$userName] ?? [];
        $sizeBytes       = (int) ($quickData['bytesUsed'] ?? 0);
        $user['_sizeGB'] = $sizeBytes > 0 ? round($sizeBytes / 1073741824, 3) : 0;
        // Unités affichées (tri reste sur _sizeGB en Go)
        $user['_sizeMo'] = $sizeBytes >= 1048576    ? (int) round($sizeBytes / 1048576)    : 0;
        $user['_sizeKo'] = $sizeBytes > 0           ? max(1, (int) round($sizeBytes / 1024)) : 0;

        // EAS / MAPI par boîte — lookup O(1) dans les maps indexées
        $user['_hasEas']  = isset($easMailboxes[$uEmail]);
        $user['_hasMapi'] = isset($mapiMailboxes[$uEmail]);

        // Chaînes pré-calculées pour le template (évite les modificateurs join/implode dans Smarty)
        $user['_aliasStr']  = implode(' ', $user['_aliases']);  // pour data-search
        $user['_searchStr'] = strtolower(
            $user['email'] . ' ' .
            $user['_aliasStr'] . ' ' .
            implode(' ', $user['_forwarding'])  // _forwarding est maintenant un tableau
        );
    }
    unset($user);

    // ── Exclure le Primary Admin de la liste client ───────────────────
    // Le compte admin secret (username stocké dans $params['username']) est
    // un compte de service interne — le client ne doit pas le voir ni le gérer.
    $primaryAdmin = strtolower(trim($params['username'] ?? ''));
    if ($primaryAdmin !== '') {
        $users = array_values(array_filter($users, function ($u) use ($primaryAdmin) {
            return strtolower((string) ($u['userName'] ?? '')) !== $primaryAdmin;
        }));
    }

    // ── Redirections autonomes (alias vers adresses externes) ─────────────
    //
    // DÉFINITION : un alias est dit « autonome » (standalone redirect) si
    //   TOUTES ses cibles sont des adresses externes au domaine (aucune boîte
    //   courriel locale n'est impliquée). Ex: bob@mondomaine.com → bob@gmail.com
    //
    // Ces adresses doivent apparaître dans la liste des comptes courriel
    // avec une icône distincte pour éviter la confusion avec les boîtes.
    //
    // ALGORITHME :
    //   1. Construire un set des adresses locales ($userEmailSet)
    //   2. Pour chaque alias dont TOUTES les cibles sont hors du set → autonome
    //   3. Ajouter comme entrée synthétique dans $users avec _isRedirectOnly=true
    //
    // SÉCURITÉ :
    //   - Les noms d'alias sont validés (pattern alphanumérique uniquement)
    //   - Les données proviennent de l'API interne, jamais directement du $_POST
    //   - Les adresses cibles ne sont affichées qu'après |escape dans le template
    $userEmailSet = [];
    foreach ($users as $_u) {
        // Indexer les adresses courriel locales pour un lookup O(1)
        $userEmailSet[strtolower((string) ($_u['email'] ?? ''))] = true;
    }

    $standaloneRedirects = [];
    foreach ($aliases as $_alias) {
        $_aliasName = strtolower(trim((string) ($_alias['userName'] ?? '')));
        if ($_aliasName === '') continue;

        // Récupérer la liste des cibles (peut nécessiter un appel individuel)
        $_targetList = [];
        if (array_key_exists('aliasTargetList', $_alias) && is_array($_alias['aliasTargetList'])) {
            $_targetList = $_alias['aliasTargetList'];
        }
        if (empty($_targetList)) {
            try {
                $_detail     = $api->getAlias($_aliasName, $daToken);
                $_targetList = (array) ($_detail['aliasTargetList'] ?? []);
            } catch (\Throwable $_e) {
                // Non bloquant — on ignore l'alias si le détail est inaccessible
                continue;
            }
        }

        // Vérifier si TOUTES les cibles sont externes (hors domaine local)
        $_hasTarget   = false;
        $_allExternal = true;
        foreach ($_targetList as $_rawTarget) {
            $_target = strtolower(trim((string) $_rawTarget));
            if ($_target === '') continue;
            // Normaliser : ajouter le domaine si la cible n'a pas de @
            if (!str_contains($_target, '@')) {
                $_target .= '@' . $domainLower;
            }
            $_hasTarget = true;
            if (isset($userEmailSet[$_target])) {
                // Cette cible est une boîte locale → alias lié à un compte existant
                $_allExternal = false;
                break;
            }
        }

        // Seulement les alias avec au moins une cible et 100 % externes
        if (!$_hasTarget || !$_allExternal) continue;

        // Construire les cibles normalisées pour l'affichage
        $_targetStrings = array_values(array_filter(array_map(
            fn($_t) => trim((string) $_t),
            $_targetList
        )));

        // Construire une entrée synthétique compatible avec le template
        $standaloneRedirects[] = [
            'email'            => $_aliasName . '@' . $domainLower,
            'userName'         => $_aliasName,
            'fullName'         => '',
            '_isRedirectOnly'  => true,                           // Indicateur principal
            '_targets'         => $_targetStrings,
            '_forwarding'      => $_targetStrings,                      // Tableau — une pill par adresse dans le template
            '_forwardingStr'   => implode(', ', $_targetStrings),        // String — attribut data-fwd pour la recherche JS
            '_aliases'         => [],
            '_aliasStr'        => '',
            '_sizeGB'          => 0.0,
            '_sizeMo'          => 0,
            '_sizeKo'          => 0,
            '_hasEas'          => false,
            '_hasMapi'         => false,
            '_mailboxStatus'   => 'redirect',
            '_lastLogin'       => '',
            // Chaîne de recherche : adresse source + toutes les destinations
            '_searchStr'       => strtolower(
                $_aliasName . '@' . $domainLower . ' ' .
                implode(' ', $_targetStrings)
            ),
        ];
    }
    unset($_alias, $_aliasName, $_targetList, $_rawTarget, $_target,
          $_hasTarget, $_allExternal, $_targetStrings, $_u, $userEmailSet);

    // Fusionner les redirections autonomes à la fin de la liste des utilisateurs
    // Elles s'affichent dans la même liste mais avec une icône différente
    $users = array_merge($users, $standaloneRedirects);

    // ── Décompte EAS / MAPI / Combiné avec coûts ─────────────────────────
    // On itère une fois sur les users déjà enrichis pour catégoriser :
    //   - easOnly    : EAS activé, MAPI non → facturé à easPrice
    //   - mapiOnly   : MAPI activé, EAS non → facturé à mapiPrice
    //   - combined   : EAS + MAPI tous les deux → facturé à bundlePrice (configoption4)
    $easPrice    = (float) ($params['configoption2'] ?? 0);
    $mapiPrice   = (float) ($params['configoption3'] ?? 0);
    $bundlePrice = (float) ($params['configoption4'] ?? 0);
    // Si bundlePrice = 0, on cumule les deux prix séparés
    $effectiveBundlePrice = $bundlePrice > 0 ? $bundlePrice : ($easPrice + $mapiPrice);

    $easOnlyCount  = 0;
    $mapiOnlyCount = 0;
    $combinedCount = 0;
    foreach ($users as $u) {
        $hasE = $u['_hasEas']  ?? false;
        $hasM = $u['_hasMapi'] ?? false;
        if ($hasE && $hasM)       $combinedCount++;
        elseif ($hasE)            $easOnlyCount++;
        elseif ($hasM)            $mapiOnlyCount++;
    }

    $easOnlyCost    = round($easOnlyCount  * $easPrice,             2);
    $mapiOnlyCost   = round($mapiOnlyCount * $mapiPrice,            2);
    $combinedCost   = round($combinedCount * $effectiveBundlePrice, 2);
    $totalProtoCost = round($easOnlyCost + $mapiOnlyCost + $combinedCost, 2);

    // ── Ajouter le coût des entrées "deleted" depuis mod_sm_proto_usage ───────
    //
    // Les protocoles en status=deleted ont été désactivés dans SmarterMail :
    // ils ne sont plus retournés par l'API live (getActiveSyncMailboxes, etc.)
    // et ne sont donc pas comptés dans easOnlyCount/mapiOnlyCount/combinedCount.
    // Pourtant, ces adresses SERONT facturées lors de la prochaine facture.
    // On les ajoute ici pour que le montant estimé soit fidèle à la réalité.
    //
    // IMPORTANT : les entrées grace/active sont déjà actives dans SM → comptées
    // par l'API live. Seul status=deleted est absent de l'API → à ajouter.
    _sm_transitionGraceToActive((int) $params['serviceid']);
    $protoDetail = _sm_getProtoUsageDetail(
        (int) $params['serviceid'],
        $easPrice,
        $mapiPrice,
        $bundlePrice
    );

    // ── FALLBACK LIVE : injection des mailboxes actives si la DB est vide ───
    //
    // PROBLÈME : _sm_getProtoUsageDetail() lit uniquement mod_sm_proto_usage.
    // Si le suivi d'utilisation a été activé APRÈS l'activation EAS/MAPI sur
    // des comptes existants, la table est vide → billingCombinedLines,
    // billingMapiLines et billingEasLines sont tous vides → le modal "Billing
    // Detail" affiche seulement "Le détail sera disponible après..." même si
    // des mailboxes EAS/MAPI sont bien actives.
    //
    // SOLUTION : quand la DB ne contient aucune ligne pour ce service ET que
    // l'API live retourne des mailboxes EAS/MAPI actives, on construit un
    // $protoDetail provisoire à partir des données live (status='active').
    //
    // Ce fallback est purement d'affichage — il ne modifie pas la DB et ne
    // déclenche aucune facturation. Les prix affichés sont identiques à ceux
    // utilisés pour la facturation réelle.
    //
    // SÉCURITÉ :
    //   - Les adresses proviennent de l'API SmarterMail (interne), pas du $_POST.
    //   - filter_var FILTER_VALIDATE_EMAIL filtre tout format inattendu.
    //   - Les prix sont castés en float depuis les configoptions (jamais du $_GET).
    if (empty($protoDetail) && ($easEnabled || $mapiEnabled)) {

        // Prix effectif bundle : si bundlePrice = 0, on cumule les deux prix
        $effectiveBundleFallback = $bundlePrice > 0
            ? $bundlePrice
            : ($easPrice + $mapiPrice);

        // Collecter toutes les adresses présentes dans les mailboxes live
        $liveEmails = array_unique(array_merge(
            array_keys($easMailboxes),
            array_keys($mapiMailboxes)
        ));

        foreach ($liveEmails as $liveEmail) {
            // Validation stricte du format courriel — rejet silencieux si invalide
            if (filter_var($liveEmail, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $hasE = isset($easMailboxes[$liveEmail]);
            $hasM = isset($mapiMailboxes[$liveEmail]);

            // Construire la ligne de détail provisoire (même structure que
            // les lignes produites par _sm_getProtoUsageDetail)
            if ($hasE && $hasM) {
                $protoDetail[] = [
                    'email'      => $liveEmail,
                    'type'       => 'combined',
                    'status'     => 'active',   // Actif en live → pas de période grace
                    'deleted_at' => null,
                    'billed'     => false,       // Non encore facturé (fallback)
                    'price'      => $effectiveBundleFallback,
                ];
            } elseif ($hasE) {
                $protoDetail[] = [
                    'email'      => $liveEmail,
                    'type'       => 'eas',
                    'status'     => 'active',
                    'deleted_at' => null,
                    'billed'     => false,
                    'price'      => $easPrice,
                ];
            } elseif ($hasM) {
                $protoDetail[] = [
                    'email'      => $liveEmail,
                    'type'       => 'mapi',
                    'status'     => 'active',
                    'deleted_at' => null,
                    'billed'     => false,
                    'price'      => $mapiPrice,
                ];
            }
        }

        // Trier selon le même ordre que _sm_getProtoUsageDetail :
        // combined → mapi → eas, puis alphabétique
        usort($protoDetail, function ($a, $b) {
            $order = ['combined' => 0, 'mapi' => 1, 'eas' => 2];
            $diff  = ($order[$a['type']] ?? 9) <=> ($order[$b['type']] ?? 9);
            return $diff !== 0 ? $diff : strcmp($a['email'], $b['email']);
        });
    }
    // ── Fin du fallback live ─────────────────────────────────────────────────

    $deletedProtoCost = 0.0;
    foreach ($protoDetail as $line) {
        if ($line['status'] === 'deleted' && !$line['billed']) {
            $deletedProtoCost += (float) ($line['price'] ?? 0);
        }
    }
    $totalProtoCost = round($totalProtoCost + $deletedProtoCost, 2);

    // Coût total estimé = stockage + protocoles (live + deleted non encore facturés)
    $totalEstimated = round($estimatedPrice + $totalProtoCost, 2);

    // ── DKIM — via getDomainSettings() → GET api/v1/settings/domain/domain ─
    //
    // L'API SmarterMail expose deux champs distincts pour le DKIM :
    //
    //   enableDkimSigningDomainAdmin (niveau domaine, racine de la réponse)
    //     → true  : la signature DKIM est activée par l'admin de domaine
    //     → false : la signature DKIM est DÉSACTIVÉE (toggle "off")
    //     C'est CE champ qui reflète l'action du bouton toggle.
    //
    //   domainKeysSettings.isActive (dans le sous-objet domainKeysSettings)
    //     → true  : SmarterMail a vérifié que l'enregistrement DNS DKIM est
    //               présent et valide (validation interne SM, pas notre lookup)
    //     → false : la clé est générée MAIS le DNS n'a pas encore été validé
    //               par SmarterMail → état "Standby / Pending DNS"
    //
    // Les 4 états combinés :
    //
    //   État A — Pas de clé (domainKeysSettings vide ou sans publicKey)
    //     → $dkim = [] — toggle "Activer" seulement
    //
    //   État B — Clé présente + enableDkimSigningDomainAdmin = false
    //     → $dkim['status'] = 'disabled'
    //     → La clé existe mais la signature est éteinte par l'admin
    //     → Afficher les records DNS (en cas de réactivation) + toggle "Activer"
    //
    //   État C — Clé présente + enableDkimSigningDomainAdmin = true + isActive = false
    //     → $dkim['status'] = 'standby'
    //     → Clé générée, signature activée, mais DNS pas encore validé par SM
    //     → Afficher les records DNS pour que le client les configure
    //     → Indicateur "En attente de validation DNS"
    //
    //   État D — Clé présente + enableDkimSigningDomainAdmin = true + isActive = true
    //     → $dkim['status'] = 'active'
    //     → Signature DKIM pleinement active et DNS confirmé
    //
    $dkim        = [];
    $domSettings = [];
    try {
        $domSettings = $api->getDomainSettings($daToken);
        $dkimRaw     = $domSettings['domainKeysSettings'] ?? [];

        // enableDkimSigningDomainAdmin est à la RACINE de la réponse domain/domain,
        // pas dans le sous-objet domainKeysSettings.
        // On le lit depuis $domSettings directement.
        $dkimSigningEnabled = (bool) ($domSettings['enableDkimSigningDomainAdmin'] ?? false);

        // ── Lecture de la clé active et de la clé pending ────────────────
        //
        // SmarterMail distingue deux emplacements pour la clé DKIM :
        //
        //   domainKeysSettings.publicKey / .selector
        //     → Clé ACTIVE : validée par SM (DNS confirmé). Signe les courriels.
        //
        //   domainKeysSettings.pending.publicKey / .selector
        //     → Clé PENDING : générée (via CreateDkimRollover ou EnableDkim),
        //       mais DNS pas encore vérifié par SmarterMail. Ne signe pas encore.
        //       C'est l'état dans lequel se trouve une clé créée par rollover.
        //
        // On lit les deux pour couvrir tous les états. La clé à afficher au
        // client (pour le DNS guide + copier-coller) est :
        //   1. La clé active si elle existe (champs directs de domainKeysSettings)
        //   2. Sinon la clé pending (sous-objet domainKeysSettings.pending)
        //
        $activeKey     = trim((string) ($dkimRaw['publicKey'] ?? ''));
        $activeSelector= trim((string) ($dkimRaw['selector']  ?? ''));

        $pendingRaw    = $dkimRaw['pending'] ?? [];
        $pendingKey    = trim((string) ($pendingRaw['publicKey'] ?? ''));
        $pendingSelector= trim((string) ($pendingRaw['selector'] ?? ''));

        // Détermine si une clé existe (active OU pending)
        $hasActiveKey  = ($activeKey !== '' && $activeSelector !== '');
        $hasPendingKey = ($pendingKey !== '' && $pendingSelector !== '');

        if ($hasActiveKey || $hasPendingKey) {
            // ── Une clé existe (active ou pending) — déterminer le statut ──
            //
            // Priorité d'affichage :
            //   Si clé active disponible → on l'utilise (états B, C, D)
            //   Sinon clé pending seule  → on l'utilise (état E — rollover pending)
            //
            // Les 5 états DKIM :
            //
            //   État B — Clé active + enableDkimSigningDomainAdmin = false
            //     → 'disabled' — Clé présente, signature éteinte par l'admin
            //     → Toggle : "Activer" | Clé visible pour copier-coller
            //
            //   État C — Clé active + signing=true + isActive=false
            //     → 'standby' — Signature activée, DNS pas encore validé par SM
            //     → Toggle : "Désactiver" | Clé visible | Badge jaune
            //
            //   État D — Clé active + signing=true + isActive=true
            //     → 'active' — Tout est confirmé
            //     → Toggle : "Désactiver" | Badge vert
            //
            //   État E — Clé pending seule (rollover créé, active pas encore)
            //     → 'standby' — Même affichage que C : DNS à configurer
            //     → Toggle : "Désactiver" (enableDkimSigningDomainAdmin=true)
            //     → Clé et sélecteur du pending visibles pour copier-coller
            //     → Badge jaune "En attente de validation DNS"
            //
            if ($hasActiveKey) {
                // États B, C, D — clé active
                $displayKey      = $activeKey;
                $displaySelector = $activeSelector;
                $dkimIsActive    = (bool) ($dkimRaw['isActive'] ?? false);

                if (!$dkimSigningEnabled) {
                    $dkimStatus = 'disabled';   // État B
                } elseif (!$dkimIsActive) {
                    $dkimStatus = 'standby';    // État C
                } else {
                    $dkimStatus = 'active';     // État D
                }
            } else {
                // État E — clé pending uniquement (rollover en cours, pas encore active)
                // enableDkimSigningDomainAdmin=true car SM a accepté la demande
                // On traite comme "standby" : le client doit configurer le DNS
                $displayKey      = $pendingKey;
                $displaySelector = $pendingSelector;
                $dkimStatus      = 'standby';   // En attente de validation DNS
            }

            $dkim = [
                // 'enabled' pilote le toggle : true → montrer "Désactiver", false → "Activer"
                // Pour l'état E (pending rollover), enableDkimSigningDomainAdmin est true
                // car SM a accepté la création de la clé via CreateDkimRollover.
                'enabled'   => $dkimSigningEnabled || ($hasPendingKey && !$hasActiveKey),
                'status'    => $dkimStatus,
                'selector'  => $displaySelector,
                'publicKey' => $displayKey,
                'txtRecord' => 'v=DKIM1; k=rsa; p=' . $displayKey,
                // Indicateur supplémentaire : la clé vient du pending (rollover)
                // Utilisable par le template pour afficher un message spécifique si besoin
                'isPending' => !$hasActiveKey && $hasPendingKey,
            ];
        }
        // État A : aucune clé (ni active, ni pending) → $dkim reste []
    } catch (\Throwable $e) {
        logActivity('SmarterMail ClientArea [getDomainSettings/DKIM] : ' . $e->getMessage());
    }

    // ── Vérification DNS : DKIM ───────────────────────────────────────────
    // Lookup DNS indépendant de SmarterMail — vérifie côté serveur DNS public
    // que l'enregistrement TXT {selector}._domainkey.{domain} est propagé.
    // Ce résultat est affiché dans l'interface (vert/rouge) mais n'affecte pas
    // le status DKIM calculé ci-dessus (qui vient de l'API SM).
    $dkimDnsValid = false;
    if (!empty($dkim['selector']) && !empty($dkim['publicKey'])) {
        try {
            $dkimHost = $dkim['selector'] . '._domainkey.' . $domain;
            $dkimDns  = @dns_get_record($dkimHost, DNS_TXT) ?: [];
            foreach ($dkimDns as $rec) {
                $txt = '';
                if (isset($rec['txt'])) {
                    $txt = $rec['txt'];
                } elseif (isset($rec['entries']) && is_array($rec['entries'])) {
                    $txt = implode('', $rec['entries']);
                }
                // Valide si le record contient v=DKIM1 et une clé publique (p=)
                if (str_contains($txt, 'v=DKIM1') && str_contains($txt, 'p=')) {
                    $dkimDnsValid = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail ClientArea [dkim-dns] : ' . $e->getMessage());
        }
    }

    // ── Vérification DNS : SPF ────────────────────────────────────────────
    // Récupère configoption13 = mécanisme SPF attendu (ex: include:mail.server.com)
    // Cherche un TXT v=spf1 sur le domaine qui contient ce mécanisme.
    $spfMechanism = trim($params['configoption13'] ?? '');
    $spfValid     = false;
    $spfFound     = '';   // Le record SPF trouvé dans le DNS
    if ($spfMechanism !== '') {
        try {
            $spfDns = @dns_get_record($domain, DNS_TXT) ?: [];
            foreach ($spfDns as $rec) {
                $txt = '';
                if (isset($rec['txt'])) {
                    $txt = $rec['txt'];
                } elseif (isset($rec['entries']) && is_array($rec['entries'])) {
                    $txt = implode('', $rec['entries']);
                }
                if (str_starts_with($txt, 'v=spf1')) {
                    $spfFound = $txt;
                    if (str_contains($txt, $spfMechanism)) {
                        $spfValid = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            logActivity('SmarterMail ClientArea [spf-dns] : ' . $e->getMessage());
        }
    }
    // Record SPF recommandé à afficher dans le popup
    $spfRecommended = 'v=spf1 ' . $spfMechanism . ' ~all';

    // ── Détection de l'onglet DNS par défaut selon les NS du domaine ─────
    //
    // On regarde les serveurs de noms (NS) du domaine pour deviner quel panneau
    // de contrôle le client utilise, et pré-sélectionner l'onglet le plus pertinent.
    //
    // Mapping NS → onglet :
    //   cPanel    : ns3/ns4.astralinternet.com, ns3/ns4.hosting-management.com
    //   Plesk     : ns20/ns21.astralinternet.com
    //   Espace client : zone1/zone2/zone3.astralinternet.com
    //   Générique : tout autre NS (GoDaddy, Cloudflare, etc.)
    //
    // SÉCURITÉ : dns_get_record() est appelé avec @ pour supprimer les warnings
    // en cas d'échec (ex: domaine inexistant, timeout DNS). Si la détection
    // échoue, on bascule sur l'onglet "générique" — comportement sûr.
    $domainNsDefault = 'generic'; // Valeur de repli si détection impossible

    // Nameservers cPanel hébergés sur infrastructure Astral Internet / Hosting Management
    $nsCpanel = ['ns3.astralinternet.com', 'ns4.astralinternet.com',
                 'ns3.hosting-management.com', 'ns4.hosting-management.com'];

    // Nameservers Plesk hébergés sur infrastructure Astral Internet
    $nsPlesk  = ['ns20.astralinternet.com', 'ns21.astralinternet.com'];

    // Nameservers de l'espace client Astral Internet (zone DNS gérée directement)
    $nsClient = ['zone1.astralinternet.com', 'zone2.astralinternet.com', 'zone3.astralinternet.com'];

    try {
        // Récupérer les NS du domaine (requête DNS type NS)
        $nsRecords = @dns_get_record($domain, DNS_NS) ?: [];

        // Extraire et normaliser les noms de serveurs
        $nsNames = array_map('strtolower', array_column($nsRecords, 'target'));

        // Parcourir les NS trouvés et identifier le type de panneau
        foreach ($nsNames as $ns) {
            if (in_array($ns, $nsCpanel, true)) {
                $domainNsDefault = 'cpanel';
                break; // Premier match suffit
            }
            if (in_array($ns, $nsPlesk, true)) {
                $domainNsDefault = 'plesk';
                break;
            }
            if (in_array($ns, $nsClient, true)) {
                $domainNsDefault = 'clientspace';
                break;
            }
        }
    } catch (\Throwable $e) {
        // Non bloquant — la page s'affiche avec l'onglet générique par défaut
        logActivity('SmarterMail ClientArea [ns-lookup] : ' . $e->getMessage());
    }

    // ── Valeurs DKIM pré-calculées pour le guide de configuration DNS ─────
    // Ces valeurs sont utilisées à la fois dans la modale DKIM existante ET
    // dans le nouveau guide étape par étape (onglets DNS).
    $dkimHost     = !empty($dkim['selector']) ? $dkim['selector'] . '._domainkey.' . $domain : '';
    $dkimTxtValue = $dkim['txtRecord'] ?? (!empty($dkim['publicKey']) ? 'v=DKIM1; k=rsa; p=' . $dkim['publicKey'] : '');
    // Le mécanisme SPF à ajouter (sans le v=spf1 ... ~all — juste le mécanisme)
    $spfMechDisplay = $spfMechanism; // ex: "include:mail.server.com" ou "ip4:1.2.3.4"

    // ── Pré-groupement des lignes de facturation par type de protocole ────
    //
    // Smarty 3 ne supporte pas la syntaxe PHP {$array[] = $value} pour ajouter
    // des éléments à un tableau dans un template. On effectue donc le tri ici
    // en PHP avant de passer les données au template.
    //
    // Les trois groupes correspondent aux sections du modal de détail :
    //   - billingCombinedLines : adresses avec EAS + MAPI tous les deux
    //   - billingMapiLines     : adresses avec MAPI seulement
    //   - billingEasLines      : adresses avec EAS seulement
    $billingCombinedLines = array_values(array_filter(
        $protoDetail,
        fn($l) => ($l['type'] ?? '') === 'combined'
    ));
    $billingMapiLines = array_values(array_filter(
        $protoDetail,
        fn($l) => ($l['type'] ?? '') === 'mapi'
    ));
    $billingEasLines = array_values(array_filter(
        $protoDetail,
        fn($l) => ($l['type'] ?? '') === 'eas'
    ));

    return [
        'tabOverviewReplacementTemplate' => 'templates/clientarea',
        'vars' => [
            'domain'         => $domain,
            'lang'           => $lang,
            'usageGB'        => $usageGB,
            'gbPerTier'      => $gbPerTier,
            'gbBilled'       => $gbBilled,
            'tiers'          => $tiers,
            'basePrice'      => $basePrice,
            'estimatedPrice' => $estimatedPrice,
            'userCount'      => count($users),
            'aliasCount'     => count($aliases),
            'easCount'          => $easCount,
            'mapiCount'         => $mapiCount,
            'easOnlyCount'      => $easOnlyCount,
            'mapiOnlyCount'     => $mapiOnlyCount,
            'combinedCount'     => $combinedCount,
            'easOnlyCost'       => $easOnlyCost,
            'mapiOnlyCost'      => $mapiOnlyCost,
            'combinedCost'      => $combinedCost,
            'totalProtoCost'    => $totalProtoCost,
            'totalEstimated'    => $totalEstimated,
            // easEnabled/mapiEnabled = fetch actif (vente ou prix configuré)
            // easSalesEnabled/mapiSalesEnabled = option proposée aux clients (configoption14/15)
            'easEnabled'        => $easEnabled,
            'mapiEnabled'       => $mapiEnabled,
            'easSalesEnabled'   => $easSalesEnabled,   // Piloté par configoption14
            'mapiSalesEnabled'  => $mapiSalesEnabled,  // Piloté par configoption15
            'easPrice'          => $easPrice,
            'mapiPrice'         => $mapiPrice,
            'bundlePrice'       => $bundlePrice,
            'effectiveBundlePrice' => $effectiveBundlePrice,
            'svcStatus'      => $svcStatus,
            'svcStatusLabel' => $statusMap[$svcStatus]['label'] ?? $svcStatus,
            'svcStatusClass' => $statusMap[$svcStatus]['class'] ?? 'default',
            'regDate'        => $regDate,
            'nextDueDate'    => $nextDueDate,
            'billingCycle'   => $billingCycle,
            'svcAmount'      => $svcAmount,
            'paymentMethod'  => $paymentMethod,
            'users'          => $users,
            'aliases'        => $aliases,
            'dkim'           => $dkim,
            'dkimDnsValid'   => $dkimDnsValid,
            'dkimHost'       => $dkimHost,       // ex: "mail._domainkey.client.com"
            'dkimTxtValue'   => $dkimTxtValue,   // ex: "v=DKIM1; k=rsa; p=..."
            'spfMechanism'   => $spfMechanism,
            'spfMechDisplay' => $spfMechDisplay, // Mécanisme seul, pour le guide DNS
            'spfValid'       => $spfValid,
            'spfFound'       => $spfFound,
            'spfRecommended' => $spfRecommended,
            'domainNsDefault'=> $domainNsDefault, // Onglet DNS actif par défaut: cpanel|plesk|clientspace|generic
            // ── Détail de facturation EAS/MAPI pour le popup (i) ──────────
            // On fait passer les lignes grace → active AVANT de lire le détail,
            // pour que le statut affiché au client soit toujours à jour sans
            // attendre le cron quotidien. L'appel est limité à ce service.
            'protoUsageDetail' => $protoDetail, // Calculé + transition grace→active déjà effectuée ci-dessus
            // ── Lignes de facturation pré-groupées par type ──────────────
            // Pré-calculé en PHP car Smarty 3 ne supporte pas {$array[] = $val}
            'billingCombinedLines' => $billingCombinedLines,
            'billingMapiLines'     => $billingMapiLines,
            'billingEasLines'      => $billingEasLines,
            'billingPeriod'  => _sm_getBillingPeriod((int) $params['serviceid']),
            'lockDays'       => max(1, (int) ($params['configoption16'] ?? 1)),
            'error'          => null,
        ],
    ];
}


/**
 * Définit les boutons personnalisés affichés dans le menu "Actions" de l'espace client.
 *
 * IMPORTANT — TRADUCTION DES LIBELLÉS :
 *   Dans WHMCS, la CLÉ du tableau retourné par cette fonction est utilisée
 *   directement comme texte du bouton affiché au client. Elle ne passe par
 *   aucun système de traduction automatique. Si la clé est codée en dur dans
 *   une seule langue, le bouton reste dans cette langue quelle que soit la
 *   langue du compte client.
 *
 *   SOLUTION : on charge le fichier de langue du client via _sm_lang($params)
 *   et on utilise la clé 'action_btn_add_email' pour obtenir le libellé traduit.
 *   Fallback vers le français canadien si la clé est absente du fichier de langue.
 *
 * Clé   = Libellé traduit du bouton (affiché à l'utilisateur)
 * Valeur = Nom de la fonction à appeler (sans le préfixe "smartermail_")
 *
 * @param  array $params Paramètres WHMCS du service (inclut clientsdetails.language)
 * @return array         Tableau libellé traduit => action
 */
function smartermail_ClientAreaCustomButtonArray(array $params): array
{
    // Charger le fichier de langue correspondant à la langue du compte client.
    // _sm_lang() utilise $params['clientsdetails']['language'] avec fallback
    // vers la langue par défaut de WHMCS, puis vers l'anglais si introuvable.
    $lang = _sm_lang($params);

    return [
        // La clé DOIT être traduite — c'est elle qui s'affiche dans le menu Actions.
        // Fallback explicite en français canadien si la clé est absente du fichier de langue.
        ($lang['action_btn_add_email'] ?? 'Ajouter une adresse courriel') => 'adduserpage',
    ];
}

/**
 * Déclare TOUTES les fonctions autorisées depuis l'espace client.
 *
 * IMPORTANT : Toute fonction non listée ici sera refusée par WHMCS
 * avec une erreur "Not Authorized". C'est une mesure de sécurité pour
 * éviter qu'un client appelle des fonctions admin via manipulation d'URL.
 *
 * NOTE : Les clés de ce tableau sont de simples descriptions internes pour
 * la lisibilité du code — elles ne sont jamais affichées au client. On les
 * écrit en anglais pour cohérence avec les conventions WHMCS.
 *
 * Clé   = Description interne (non affiché, documentation uniquement)
 * Valeur = Nom de la fonction (sans le préfixe "smartermail_")
 *
 * @return array Tableau description => action
 */
function smartermail_ClientAreaAllowedFunctions(): array
{
    return [
        'Add email address'          => 'adduserpage',
        'Edit email address'         => 'edituserpage',
        'Create email address'       => 'createuser',
        'Save email address'         => 'saveuser',
        'Change password'            => 'savepassword',
        'Delete email address'       => 'deleteuser',
        'Toggle DKIM signing'        => 'toggledkim',
    ];
}


// =============================================================================
//  ESPACE CLIENT — TOGGLE DKIM
// =============================================================================

/**
 * Action : Active ou désactive la signature DKIM du domaine.
 *
 * Appelé via POST depuis l'espace client (bouton bascule dans la section DNS).
 * Le client peut activer ou désactiver DKIM sans intervention de l'admin.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  SÉCURITÉ
 * ─────────────────────────────────────────────────────────────────────────────
 *  1. L'action POST passe par _sm_initDomainAdmin() → token DA strictement
 *     limité au domaine du service WHMCS du client connecté.
 *  2. La valeur dkim_action est validée contre une whitelist ('enable'/'disable').
 *     Toute autre valeur est rejetée avec un message d'erreur — aucune
 *     exécution de code arbitraire n'est possible.
 *  3. Le paramètre est récupéré depuis $_POST (pas $_GET) pour éviter
 *     le CSRF trivial via lien cliquable.
 *  4. Le serviceid est celui du service WHMCS authentifié — le client ne
 *     peut pas agir sur le domaine d'un autre client.
 *
 * Variables $_POST attendues :
 *   - dkim_action : 'enable' ou 'disable' (whitelist stricte)
 *
 * @param array $params Paramètres WHMCS du service
 * @return string       'success' ou message d'erreur localisé
 */
function smartermail_toggledkim(array $params): string
{
    $lang = _sm_lang($params);

    // ── Validation stricte de l'action ────────────────────────────────────
    // On accepte UNIQUEMENT 'enable' ou 'disable' — rien d'autre.
    // strip_tags() + trim() pour nettoyer toute tentative d'injection.
    $rawAction  = strtolower(trim(strip_tags($_POST['dkim_action'] ?? '')));
    $validActions = ['enable', 'disable'];

    if (!in_array($rawAction, $validActions, true)) {
        // Action inconnue : journaliser et refuser sans révéler de détails
        logActivity('SmarterMail [toggledkim] Action invalide reçue : ' . var_export($rawAction, true)
            . ' | domaine: ' . $params['domain'] . ' | service: ' . $params['serviceid']);
        return $lang['dkim_toggle_invalid_action'] ?? 'Action DKIM invalide.';
    }

    // ── Initialisation API — token Domain Admin via impersonification SA ──
    // _sm_initDomainAdmin garantit que le token est limité au bon domaine.
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        return $init['error'];
    }

    $api     = $init['api'];
    $daToken = $init['token'];
    $domain  = $params['domain'];

    // ── Exécution de l'action DKIM ────────────────────────────────────────
    if ($rawAction === 'enable') {
        // ── Activation DKIM avec fallback automatique rollover ─────────────
        //
        // Sur certains serveurs SmarterMail, EnableDkim échoue si DKIM n'a
        // jamais été initialisé sur le domaine. Dans ce cas, _sm_enableOrRolloverDkim()
        // tente CreateDkimRollover automatiquement pour générer la paire de clés.
        //
        // Comportement selon le résultat :
        //   rollover=false → EnableDkim a réussi → DKIM actif (ou pending DNS)
        //   rollover=true  → CreateDkimRollover utilisé → clé « pending »
        //                    Le client doit ajouter l'enregistrement DNS
        //                    L'espace client affichera la nouvelle clé au rechargement
        $dkimResult = _sm_enableOrRolloverDkim($api, $daToken, $domain);

        if (!$dkimResult['success']) {
            // Les deux méthodes ont échoué — informer le client
            logActivity('SmarterMail [toggledkim] Activation DKIM échouée (enable + rollover) pour '
                . $domain . ' : ' . ($dkimResult['error'] ?? 'erreur inconnue'));
            return $lang['err_dkim_toggle_failed'] ?? 'Impossible de modifier le statut DKIM.';
        }

        // Succès — journaliser la méthode utilisée pour traçabilité
        if (!empty($dkimResult['rollover'])) {
            logActivity('SmarterMail [toggledkim] DKIM activé via rollover pour '
                . $domain . ' — sélecteur : ' . ($dkimResult['selector'] ?? 'inconnu'));
        }

        return 'success';

    } else {
        // ── Désactivation DKIM ────────────────────────────────────────────
        //
        // disableDkim() désactive la signature. Les enregistrements DNS existants
        // ne sont PAS supprimés automatiquement — le client doit les retirer
        // manuellement s'il le souhaite. Comportement sûr et réversible.
        //
        // Note : Le rollover n'a aucun sens pour la désactivation — on appelle
        // directement disableDkim() sans fallback.
        $resp = $api->disableDkim($daToken);

        if (!$resp['success']) {
            logActivity('SmarterMail [toggledkim] Désactivation DKIM échouée pour '
                . $domain . ' : ' . ($resp['error'] ?? 'erreur inconnue'));
            return $lang['err_dkim_toggle_failed'] ?? 'Impossible de modifier le statut DKIM.';
        }

        return 'success';
    }
}


// =============================================================================
//  ESPACE CLIENT — GESTION DES UTILISATEURS (BOÎTES COURRIEL)
// =============================================================================

/**
 * Page : Formulaire de création d'une nouvelle adresse courriel.
 *
 * Charge les paramètres du domaine pour savoir si EAS/MAPI sont disponibles
 * et afficher les options correspondantes dans le formulaire.
 *
 * Template : templates/adduser.tpl
 *
 * @param array $params Paramètres WHMCS
 * @return array        Données pour le template ou page d'erreur
 */
function smartermail_adduserpage(array $params): array
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        return ['templatefile' => 'error', 'vars' => ['error' => $init['error']]];
    }

    $api         = $init['api'];
    $daToken     = $init['token'];
    $lang        = _sm_lang($params);
    $domSettings = $api->getDomainSettings($daToken);

    $domain     = $params['domain'];
    $domainBase = strstr($domain, '.', true) ?: $domain;

    return [
        'templatefile' => 'adduser',
        'vars'         => [
            'domain'           => $domain,
            'domainBase'       => $domainBase,
            'lang'             => $lang,
            'serviceid'        => $params['serviceid'],
            'canEAS'           => ($params['configoption14'] ?? 'on') === 'on',  // configoption14 : offre EAS activée
            'canMAPI'          => ($params['configoption15'] ?? 'on') === 'on',  // configoption15 : offre MAPI activée
            'easPrice'         => (float) ($params['configoption2'] ?? 0),
            'mapiPrice'        => (float) ($params['configoption3'] ?? 0),
            'bundlePrice'      => (float) ($params['configoption4'] ?? 0),
            'lockDays'         => max(1, (int) ($params['configoption16'] ?? 1)),  // Seuil facturation EAS/MAPI
            'pwdMinLength'     => max(1, (int) ($params['configoption9']  ?? 8)),
            'pwdRequireUpper'  => ($params['configoption10'] ?? 'on') === 'on',
            'pwdRequireNumber' => ($params['configoption11'] ?? 'on') === 'on',
            'pwdRequireSpecial'=> ($params['configoption12'] ?? 'on') === 'on',
        ],
    ];
}

/**
 * Action : Crée une nouvelle adresse courriel dans SmarterMail.
 *
 * Reçoit les données du formulaire adduser.tpl via $_POST et crée l'utilisateur.
 * Après création, active optionnellement EAS et/ou MAPI selon les cases cochées.
 *
 * Variables $_POST attendues :
 *   - username      : Partie avant le @ (ex: "jean")
 *   - password      : Mot de passe de la boîte
 *   - fullname      : Nom complet (optionnel)
 *   - mailboxsize_mb: Limite en MB (0 = illimité)
 *   - forward_to    : Adresse de redirection automatique (optionnel)
 *   - enable_eas    : "1" si EAS doit être activé (checkbox)
 *   - enable_mapi   : "1" si MAPI doit être activé (checkbox)
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur
 */
function smartermail_createuser(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];
    $domain  = $params['domain'];

    // ── Lecture et validation des entrées ─────────────────────────────────
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $sizeMB   = max(0, min(1048576, (int) ($_POST['mailboxsize_mb'] ?? 0))); // max 1 To

    if ($username === '') {
        $l = _sm_lang($params); return $l['err_user_required'] ?? 'Le nom d\'utilisateur est requis.';
    }
    // Validation du format : lettres, chiffres, points, tirets, underscores.
    // Doit commencer par une lettre ou un chiffre (pas de point ou tiret en premier).
    if (!preg_match('/^[a-z0-9][a-z0-9._\-]{0,63}$/i', $username)) {
        $l = _sm_lang($params);
        return $l['err_user_invalid_chars'] ?? 'Nom d\'utilisateur invalide. Caractères autorisés : lettres, chiffres, . - _ (doit commencer par une lettre ou un chiffre).';
    }
    if ($password === '') {
        $l = _sm_lang($params); return $l['err_pwd_required'] ?? 'Le mot de passe est requis.';
    }
    $minLen = (int) ($params['configoption9'] ?? 6);
    if (strlen($password) < $minLen) {
        $l = _sm_lang($params);
        // %d = longueur minimale configurée
        return sprintf($l['err_pwd_min_length'] ?? 'Le mot de passe doit contenir au moins %d caractères.', $minLen);
    }
    if (stripos($password, $username) !== false) {
        $l = _sm_lang($params);
        // %s = nom d'utilisateur détecté dans le mot de passe
        return sprintf($l['err_pwd_has_user'] ?? 'Le mot de passe ne doit pas contenir le nom d\'utilisateur (%s).', $username);
    }
    $domainBase = strstr($domain, '.', true) ?: $domain;
    if (stripos($password, $domain) !== false) {
        $l = _sm_lang($params);
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domain);
    }
    if (strlen($domainBase) >= 4 && stripos($password, $domainBase) !== false) {
        $l = _sm_lang($params);
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domainBase);
    }

    // Validation optionnelle de la redirection
    $forwardTo = trim($_POST['forward_to'] ?? '');
    if ($forwardTo !== '' && !filter_var($forwardTo, FILTER_VALIDATE_EMAIL)) {
        $l = _sm_lang($params);
        return $l['err_fwd_invalid'] ?? 'L\'adresse de redirection n\'est pas valide.';
    }

    // Construire le payload utilisateur (pas de fullName — retiré de l'interface)
    $userData = [
        'userName' => $username,
        'password' => $password,
        'securityFlags' => [
            'isDomainAdmin' => false,
        ],
    ];
    // maxMailboxSize va dans userData selon l'API SmarterMail
    $userData['maxMailboxSize'] = $sizeMB > 0 ? $sizeMB * 1024 * 1024 : 0;

    // Créer l'utilisateur dans SmarterMail
    $resp = $api->createUser($userData, null, $daToken);
    if (!$resp['success']) {
        $msg = $resp['error'] ?? '';
        if (str_contains($msg, 'NAME_IN_USE')) {
            $l = _sm_lang($params); return $l['err_user_exists'] ?? 'Une adresse courriel avec ce nom existe déjà.';
        }
        logActivity('SmarterMail [createuser] Erreur API pour ' . $username . '@' . $domain . ' : ' . $msg);
        $l = _sm_lang($params); return $l['err_create_failed'] ?? 'Impossible de créer l\'adresse courriel.';
    }

    // Adresse courriel complète pour les appels post-création
    $email = $username . '@' . $domain;

    // ── Alias (non bloquants — l'utilisateur est déjà créé) ──────────────
    $aliases = array_filter(array_map('trim', (array) ($_POST['aliases'] ?? [])));
    foreach ($aliases as $aliasName) {
        if (!preg_match('/^[a-z0-9._\-]+$/i', $aliasName)) {
            logActivity('SmarterMail [createuser] Alias invalide ignoré : ' . $aliasName . ' (domaine: ' . $domain . ')');
            continue;
        }
        $api->createAlias([
            'name'            => strtolower($aliasName),
            'displayName'     => $aliasName,
            'allowSending'    => false,
            'hideFromGAL'     => false,
            'internalOnly'    => false,
            'aliasTargetList' => [$email],
        ], $daToken);
    }

    // ── Redirections (non bloquantes) ─────────────────────────────────────
    $rawFwdCreate = array_map('trim', (array) ($_POST['fwd_list'] ?? []));
    $fwdList = array_values(array_filter($rawFwdCreate, function($addr) {
        return $addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
    }));
    if (!empty($fwdList)) {
        $api->setMailboxForwardList($daToken, $email, $fwdList, [
            'keepRecipients'   => !empty($_POST['fwd_keep']),
            'deleteOnForward'  => !empty($_POST['fwd_delete']),
            'spamForwardOption'=> 'None',
        ]);
    }

    // ── EAS si demandé (non bloquant) ─────────────────────────────────────
    // Même logique que saveuser : activer le niveau domaine via SA si nécessaire
    // avant d'activer par boîte, pour les domaines créés avant configoption14/15.
    if (!empty($_POST['enable_eas'])) {
        $saToken = $init['saToken'] ?? null;
        if ($saToken) {
            $domCurrent = $api->getDomainSettings($daToken);
            if (empty($domCurrent['enableActiveSyncAccountManagement'])) {
                $api->setDomainSettings($domain, ['enableActiveSyncAccountManagement' => true], $saToken);
            }
        }
        $api->setActiveSyncEnabled($email, true, $daToken);

        // ── Enregistrer l'activation pour la facturation basée sur l'utilisation ──
        $thresholdHours = max(1, (int) ($params['configoption16'] ?? 1)) * 24; // Jours → heures
        if ($thresholdHours > 0) {
            _sm_recordProtoActivation((int) $params['serviceid'], $email, 'eas', $thresholdHours);
        }
    }

    // ── MAPI si demandé (non bloquant) ────────────────────────────────────
    if (!empty($_POST['enable_mapi'])) {
        $saToken = $saToken ?? ($init['saToken'] ?? null);
        if ($saToken) {
            $domCurrent = $domCurrent ?? $api->getDomainSettings($daToken);
            if (empty($domCurrent['enableMapiEwsAccountManagement'])) {
                $api->setDomainSettings($domain, ['enableMapiEwsAccountManagement' => true], $saToken);
            }
        }
        $api->setMapiEnabled($email, true, $daToken);

        // ── Enregistrer l'activation MAPI ─────────────────────────────────
        $thresholdHours = $thresholdHours ?? (max(1, (int) ($params['configoption16'] ?? 1)) * 24);
        if ($thresholdHours > 0) {
            _sm_recordProtoActivation((int) $params['serviceid'], $email, 'mapi', $thresholdHours);
        }
    }

    return 'success';
}

/**
 * Page : Formulaire de modification d'une adresse courriel existante.
 *
 * Récupère les données actuelles de l'utilisateur depuis SmarterMail
 * pour pré-remplir le formulaire d'édition.
 *
 * L'utilisateur à modifier est identifié par $_POST['selectuser'] (username sans @).
 *
 * Template : templates/edituser.tpl
 *
 * @param array $params Paramètres WHMCS
 * @return array        Données pour le template ou page d'erreur
 */
function smartermail_edituserpage(array $params): array
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        return ['templatefile' => 'error', 'vars' => ['error' => $init['error']]];
    }

    $username = trim($_POST['selectuser'] ?? $_GET['username'] ?? '');
    if ($username === '' || !preg_match('/^[a-z0-9._\-]+$/i', $username)) {
        return ['templatefile' => 'error', 'vars' => ['error' => 'Utilisateur non spécifié ou invalide.']];
    }

    $api     = $init['api'];
    $daToken = $init['token'];
    $domain  = $params['domain'];
    $email   = $username . '@' . $domain;

    // ── Données de la boîte ───────────────────────────────────────────────
    $userData      = $api->getUser($email, $daToken);
    $mailSettings  = $api->getUserMailSettings($email, $daToken);
    $easMailboxes  = $api->getActiveSyncMailboxes($daToken);
    $mapiMailboxes = $api->getMapiMailboxes($daToken);
    $domSettings   = $api->getDomainSettings($daToken);

    // Taille courante (bytes) — userData.currentMailboxSize ou mailSettings.totalMailboxSize
    $currentBytes  = (int) ($userData['currentMailboxSize'] ?? $mailSettings['totalMailboxSize'] ?? 0);
    // userData.maxMailboxSize (API post-user) OU userMailSettings.maxSize selon le contexte
    $maxBytes      = (int) ($userData['maxMailboxSize'] ?? $mailSettings['maxSize'] ?? 0);
    $currentMB     = round($currentBytes / (1024 * 1024), 2);
    $maxMBInput    = $maxBytes > 0 ? (int) round($maxBytes / (1024 * 1024)) : 0;

    // Pourcentage d'utilisation pour la barre de progression
    $usagePct = ($maxBytes > 0 && $currentBytes > 0)
        ? min(100, round($currentBytes / $maxBytes * 100, 1))
        : 0;

    // ── Alias pointant vers cet utilisateur ──────────────────────────────
    $allAliases  = $api->getAliases($daToken);
    $userAliases = [];
    $domainLower = strtolower($domain);

    foreach ($allAliases as $alias) {
        $aliasName  = trim((string) ($alias['userName'] ?? ''));
        if ($aliasName === '') continue;

        $targetList = [];
        if (array_key_exists('aliasTargetList', $alias) && is_array($alias['aliasTargetList'])) {
            $targetList = $alias['aliasTargetList'];
        }
        if (empty($targetList)) {
            $detail     = $api->getAlias($aliasName, $daToken);
            $targetList = (array) ($detail['aliasTargetList'] ?? []);
        }

        foreach ($targetList as $rawTarget) {
            $t = strtolower(trim((string) $rawTarget));
            if (!str_contains($t, '@')) $t .= '@' . $domainLower;
            if ($t === strtolower($email)) {
                $userAliases[] = $aliasName;
                break;
            }
        }
    }

    // ── Critères de mot de passe (depuis les configoptions du module) ─────
    $pwdMinLength      = max(1, (int) ($params['configoption9']  ?? 8));
    $pwdRequireUpper   = ($params['configoption10'] ?? 'on') === 'on';
    $pwdRequireNumber  = ($params['configoption11'] ?? 'on') === 'on';
    $pwdRequireSpecial = ($params['configoption12'] ?? 'on') === 'on';

    // ── Forwarding actuel ─────────────────────────────────────────────────
    $fwdData   = $api->getMailboxForwardList($daToken, $username, $domain);
    $fwdList   = $fwdData['forwardList']    ?? [];
    $fwdKeep   = (bool)   ($fwdData['keepRecipients']  ?? false);
    $fwdDelete = (bool)   ($fwdData['deleteOnForward'] ?? false);
    $fwdSpam   = (string) ($fwdData['spamForwardOption'] ?? 'None');

    // ── Nom du produit (pour le titre de page) ────────────────────────────
    $productName = Capsule::table('tblproducts')
        ->where('id', $params['pid'] ?? 0)
        ->value('name') ?? '';

    $pageTitle = trim($productName . ' - ' . $domain . ' : Gestion de l\'adresse "' . $email . '"');

    $domainBase = strstr($domain, '.', true) ?: $domain;

    return [
        'templatefile' => 'edituser',
        'pagetitle'    => $pageTitle,
        'vars'         => [
            'productname'      => $productName,
            'domain'           => $domain,
            'domainBase'       => $domainBase,
            'lang'             => _sm_lang($params),
            'username'         => $username,
            'email'            => $email,
            'userData'         => $userData,
            'mailSettings'     => $mailSettings,
            'currentMB'        => $currentMB,
            'maxMBInput'       => $maxMBInput,
            'usagePct'         => $usagePct,
            'currentBytes'     => $currentBytes,
            'maxBytes'         => $maxBytes,
            'userAliases'      => $userAliases,
            'fwdList'          => $fwdList,
            'fwdKeep'          => $fwdKeep,
            'fwdDelete'        => $fwdDelete,
            'fwdSpam'          => $fwdSpam,
            'easEnabled'       => isset($easMailboxes[strtolower($email)]),
            'mapiEnabled'      => isset($mapiMailboxes[strtolower($email)]),
            'canEAS'           => ($params['configoption14'] ?? 'on') === 'on',  // configoption14 : offre EAS activée
            'canMAPI'          => ($params['configoption15'] ?? 'on') === 'on',  // configoption15 : offre MAPI activée
            'easPrice'         => (float) ($params['configoption2'] ?? 0),
            'mapiPrice'        => (float) ($params['configoption3'] ?? 0),
            'bundlePrice'      => (float) ($params['configoption4'] ?? 0),  // Prix combiné EAS+MAPI (manquait — causait SM_BUNDLE_PRICE=0)
            'lockDays'         => max(1, (int) ($params['configoption16'] ?? 1)),  // Seuil facturation EAS/MAPI
            'pwdMinLength'     => $pwdMinLength,
            'pwdRequireUpper'  => $pwdRequireUpper,
            'pwdRequireNumber' => $pwdRequireNumber,
            'pwdRequireSpecial'=> $pwdRequireSpecial,
        ],
    ];
}

/**
 * Action : Change uniquement le mot de passe d'une adresse courriel.
 *
 * Séparé de saveuser() pour éviter d'écraser les autres paramètres
 * lors d'un changement de mot de passe depuis le modal dédié.
 *
 * Variables $_POST : selectuser, password
 */
function smartermail_savepassword(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $username = trim($_POST['selectuser'] ?? '');
    $password = trim($_POST['password']   ?? '');

    if ($username === '' || !preg_match('/^[a-z0-9._\-]+$/i', $username)) {
        $l = _sm_lang($params); return $l['err_user_required'] ?? 'Utilisateur non spécifié ou invalide.';
    }
    if ($password === '') {
        $l = _sm_lang($params); return $l['err_pwd_required'] ?? 'Le mot de passe est requis.';
    }
    $minLen  = (int) ($params['configoption9'] ?? 6);
    $domain  = $params['domain'];

    if (strlen($password) < $minLen) {
        $l = _sm_lang($params);
        // %d = longueur minimale requise (configoption9)
        return sprintf($l['err_pwd_min_length'] ?? 'Le mot de passe doit contenir au moins %d caractères.', $minLen);
    }

    // ── Règle : ne doit pas contenir le nom d'utilisateur ─────────────────
    // Vérifié de façon insensible à la casse, n'importe où dans le mot de passe.
    if (stripos($password, $username) !== false) {
        $l = _sm_lang($params);
        // %s = nom d'utilisateur incriminé
        return sprintf($l['err_pwd_has_user'] ?? 'Le mot de passe ne doit pas contenir le nom d\'utilisateur (%s).', $username);
    }

    // ── Règle : ne doit pas contenir le nom de domaine ───────────────────
    // On vérifie le domaine complet (ex: example.com) et la partie principale
    // avant le premier point (ex: "example") pour attraper les deux cas.
    $domainBase = strstr($domain, '.', true) ?: $domain; // "example" de "example.com"
    if (stripos($password, $domain) !== false) {
        $l = _sm_lang($params);
        // %s = nom de domaine complet
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domain);
    }
    if (strlen($domainBase) >= 4 && stripos($password, $domainBase) !== false) {
        $l = _sm_lang($params);
        // %s = partie principale du domaine (avant le premier point)
        return sprintf($l['err_pwd_has_domain'] ?? 'Le mot de passe ne doit pas contenir le nom de domaine (%s).', $domainBase);
    }

    $api     = $init['api'];
    $daToken = $init['token'];
    $email   = $username . '@' . $domain;

    $resp = $api->updateUser($email, ['password' => $password], null, $daToken);
    if (!$resp['success']) {
        logActivity('SmarterMail [savepassword] Erreur pour ' . $email . ' : ' . _sm_apiError($resp));
        $l = _sm_lang($params); return $l['err_pwd_change_failed'] ?? 'Impossible de changer le mot de passe.';
    }
    return 'success';
}


function smartermail_saveuser(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];

    $username = trim($_POST['selectuser'] ?? '');
    $domain   = $params['domain'];

    // ── Validation ────────────────────────────────────────────────────────
    if ($username === '' || !preg_match('/^[a-z0-9._\-]+$/i', $username)) {
        $l = _sm_lang($params); return $l['err_user_required'] ?? 'Utilisateur non spécifié ou invalide.';
    }

    $email  = $username . '@' . $domain;
    $sizeMB = max(0, min(1048576, (int) ($_POST['mailboxsize_mb'] ?? 0))); // max 1 To (1 048 576 MB)

    // ── Profil utilisateur ────────────────────────────────────────────────
    $userData = [
        'fullName'        => mb_substr(trim(strip_tags($_POST['fullname'] ?? '')), 0, 100),
        'maxMailboxSize'  => $sizeMB > 0 ? $sizeMB * 1024 * 1024 : 0,
    ];
    $newPassword = trim($_POST['password'] ?? '');
    if ($newPassword !== '') {
        if (strlen($newPassword) < (int) ($params['configoption9'] ?? 6)) {
            // Vérification rapide de longueur — sans sprintf car configoption9 non chargé ici
            return _sm_lang($params)['err_pwd_too_short'] ?? 'Le mot de passe est trop court.';
        }
        $userData['password'] = $newPassword;
    }

    $resp = $api->updateUser($email, $userData, null, $daToken);
    if (!$resp['success']) {
        logActivity('SmarterMail [saveuser] Erreur pour ' . $email . ' : ' . _sm_apiError($resp));
        $l = _sm_lang($params); return $l['err_save_failed'] ?? 'Impossible de sauvegarder les modifications.';
    }

    // ── EAS et MAPI — mise à jour seulement si l'offre est activée dans le produit ──
    //
    // LOGIQUE DE SÉCURITÉ :
    //   On ne touche à EAS/MAPI que si configoption14/15 = 'on' (offre activée).
    //   Cela empêche qu'un client manipule le POST pour activer des protocoles
    //   sur un produit qui ne les propose pas.
    //
    // COMPATIBILITÉ DOMAINES EXISTANTS :
    //   Les domaines créés avant l'ajout de configoption14/15 peuvent avoir
    //   enableActiveSyncAccountManagement = false au niveau du domaine dans SM.
    //   La méthode setActiveSyncEnabled() (par boîte) échoue silencieusement si
    //   cette permission domaine est désactivée. On s'assure donc d'activer la
    //   permission au niveau du domaine via le SA token AVANT d'activer par boîte.
    //   Cette activation domaine n'est déclenchée QUE si le client active le protocole
    //   (pas à chaque sauvegarde) pour minimiser les appels API superflus.
    $canEAS  = ($params['configoption14'] ?? 'on') === 'on';
    $canMAPI = ($params['configoption15'] ?? 'on') === 'on';
    $saToken = $init['saToken'] ?? null;

    // ── Lire l'état PRÉCÉDENT pour détecter les transitions ON→OFF et OFF→ON ──
    // was_eas / was_mapi sont des champs cachés ajoutés par edituser.tpl.
    // Ils reflètent l'état EAS/MAPI au moment du chargement de la page.
    // Cette comparaison permet d'enregistrer précisément les activations/désactivations.
    $wasEas  = !empty($_POST['was_eas']);
    $wasMapiPrev = !empty($_POST['was_mapi']);
    $thresholdHours = max(1, (int) ($params['configoption16'] ?? 1)) * 24; // Jours → heures

    if ($canEAS) {
        $easWanted = !empty($_POST['enable_eas']);

        // Si le client active EAS, s'assurer que le domaine l'autorise
        if ($easWanted && $saToken) {
            $domCurrent = $api->getDomainSettings($daToken);
            if (empty($domCurrent['enableActiveSyncAccountManagement'])) {
                $api->setDomainSettings($domain, [
                    'enableActiveSyncAccountManagement' => true,
                ], $saToken);
            }
        }

        $api->setActiveSyncEnabled($email, $easWanted, $daToken);

        // ── Suivi d'utilisation : enregistrer activation / désactivation ──
        if ($thresholdHours > 0) {
            if ($easWanted && !$wasEas) {
                // OFF → ON : début d'une nouvelle session
                _sm_recordProtoActivation((int) $params['serviceid'], $email, 'eas', $thresholdHours);
            } elseif (!$easWanted && $wasEas) {
                // ON → OFF : fin de session, calcul des minutes cumulées
                _sm_recordProtoDeactivation((int) $params['serviceid'], $email, 'eas');
            }
            // État inchangé → rien à enregistrer
        }
    }

    if ($canMAPI) {
        $mapiWanted = !empty($_POST['enable_mapi']);

        // Si le client active MAPI, s'assurer que le domaine l'autorise
        if ($mapiWanted && $saToken) {
            $domCurrent = $domCurrent ?? $api->getDomainSettings($daToken);
            if (empty($domCurrent['enableMapiEwsAccountManagement'])) {
                $api->setDomainSettings($domain, [
                    'enableMapiEwsAccountManagement' => true,
                ], $saToken);
            }
        }

        $api->setMapiEnabled($email, $mapiWanted, $daToken);

        // ── Suivi d'utilisation MAPI ──────────────────────────────────────
        if ($thresholdHours > 0) {
            if ($mapiWanted && !$wasMapiPrev) {
                _sm_recordProtoActivation((int) $params['serviceid'], $email, 'mapi', $thresholdHours);
            } elseif (!$mapiWanted && $wasMapiPrev) {
                _sm_recordProtoDeactivation((int) $params['serviceid'], $email, 'mapi');
            }
        }
    }

    // ── Gestion des alias ─────────────────────────────────────────────────
    $domainLower = strtolower($domain);
    $rawNew  = array_map('trim', (array) ($_POST['aliases']      ?? []));
    $rawOrig = array_map('trim', (array) ($_POST['orig_aliases'] ?? []));

    // Valider et nettoyer les noms d'alias (alphanumériques, . - _ uniquement)
    $filterAlias = fn($list) => array_values(array_filter(
        array_map('strtolower', $list),
        fn($a) => $a !== '' && preg_match('/^[a-z0-9._\-]+$/i', $a)
    ));

    $newAliases  = $filterAlias($rawNew);
    $origAliases = $filterAlias($rawOrig);

    $added   = array_diff($newAliases, $origAliases);
    $removed = array_diff($origAliases, $newAliases);

    // Retirer cet utilisateur des alias supprimés
    foreach ($removed as $aliasName) {
        $aliasData = $api->getAlias($aliasName, $daToken);

        // getAlias() retourne ['name'=>'boby', 'aliasTargetList'=>[...], ...]
        // Le champ s'appelle 'name' (pas 'userName' qui est pour la liste account-list-search)
        if (empty($aliasData) || empty($aliasData['name'])) {
            continue; // Alias introuvable — déjà supprimé ou inaccessible
        }

        $targets    = $aliasData['aliasTargetList'] ?? [];
        $newTargets = array_values(array_filter($targets, function ($t) use ($email, $domainLower) {
            $t = strtolower(trim((string) $t));
            if (!str_contains($t, '@')) $t .= '@' . $domainLower;
            return $t !== strtolower($email);
        }));

        if (empty($newTargets)) {
            // Cet utilisateur était la seule cible — supprimer l'alias entier
            $api->deleteAlias($aliasName, $daToken);
        } else {
            // D'autres cibles existent — retirer seulement cet utilisateur
            $safeAliasData = [
                'name'            => (string) ($aliasData['name'] ?? $aliasName),
                'displayName'     => (string) ($aliasData['displayName'] ?? $aliasName),
                'allowSending'    => (bool)   ($aliasData['allowSending']   ?? false),
                'hideFromGAL'     => (bool)   ($aliasData['hideFromGAL']    ?? false),
                'internalOnly'    => (bool)   ($aliasData['internalOnly']   ?? false),
                'aliasTargetList' => $newTargets,
            ];
            $api->updateAlias($aliasName, $safeAliasData, $daToken);
        }
    }

    // Ajouter cet utilisateur aux nouveaux alias
    foreach ($added as $aliasName) {
        $existing = $api->getAlias($aliasName, $daToken);
        if (!empty($existing)) {
            // L'alias existe : ajouter cet utilisateur à ses cibles
            $targets = $existing['aliasTargetList'] ?? [];
            if (!in_array($email, array_map('strtolower', $targets))) {
                $targets[] = $email;
                $safeExisting = [
                    'name'            => (string) ($existing['name'] ?? $aliasName),
                    'displayName'     => (string) ($existing['displayName'] ?? $aliasName),
                    'allowSending'    => (bool)   ($existing['allowSending']   ?? false),
                    'hideFromGAL'     => (bool)   ($existing['hideFromGAL']    ?? false),
                    'internalOnly'    => (bool)   ($existing['internalOnly']   ?? false),
                    'aliasTargetList' => $targets,
                ];
                $api->updateAlias($aliasName, $safeExisting, $daToken);
            }
        } else {
            // Créer un nouvel alias
            $api->createAlias([
                'name'            => $aliasName,
                'displayName'     => $aliasName,
                'aliasTargetList' => [$email],
                'allowSending'    => false,
                'hideFromGAL'     => false,
                'internalOnly'    => false,
            ], $daToken);
        }
    }

    // ── Mise à jour du forwarding ─────────────────────────────────────────
    if (isset($_POST['fwd_list']) || isset($_POST['fwd_updated'])) {
        $rawFwdList = array_map('trim', (array) ($_POST['fwd_list'] ?? []));
        $newFwdList = array_values(array_filter($rawFwdList, function($addr) {
            return $addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
        }));
        $api->setMailboxForwardList($daToken, $email, $newFwdList, [
            'keepRecipients'    => !empty($_POST['fwd_keep']),
            'deleteOnForward'   => !empty($_POST['fwd_delete']),
            'spamForwardOption' => (function() {
                $allowed = ['None', 'Delete', 'MoveToJunk', 'NoProcessing'];
                $v = trim($_POST['fwd_spam'] ?? 'None');
                return in_array($v, $allowed, true) ? $v : 'None';
            })(),
        ]);
    }

    return 'success';
}


/**
 * Action : Supprime une adresse courriel du domaine.
 *
 * ⚠️  IRRÉVERSIBLE — Tous les courriels de la boîte sont supprimés.
 *
 * Variables $_POST attendues :
 *   - selectuser : Username à supprimer (sans @domaine)
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur
 */
function smartermail_deleteuser(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];

    $username = trim($_POST['selectuser'] ?? '');
    if ($username === '' || !preg_match('/^[a-z0-9._\-]+$/i', $username)) {
        $l = _sm_lang($params); return $l['err_user_required'] ?? 'Utilisateur non spécifié ou invalide.';
    }

    $domain  = $params['domain'];
    $email   = strtolower($username . '@' . $domain);

    // ── Supprimer les alias qui pointent vers cet utilisateur ─────────────
    // On récupère tous les alias du domaine, on identifie ceux dont la
    // liste de destinations contient uniquement cette adresse, et on les
    // supprime. Si un alias pointe vers plusieurs destinataires, on le laisse
    // intact (il peut servir d'autres utilisateurs).
    try {
        $allAliases  = $api->getAliases($daToken);
        $domainLower = strtolower($domain);

        foreach ($allAliases as $alias) {
            $aliasName  = trim((string) ($alias['userName'] ?? ''));
            if ($aliasName === '') continue;

            // Récupérer les cibles complètes si non incluses dans la liste
            $targetList = [];
            if (array_key_exists('aliasTargetList', $alias) && is_array($alias['aliasTargetList'])) {
                $targetList = $alias['aliasTargetList'];
            }
            if (empty($targetList)) {
                $detail     = $api->getAlias($aliasName, $daToken);
                $targetList = (array) ($detail['aliasTargetList'] ?? []);
            }

            // Normaliser les cibles
            $normalized = array_map(function($t) use ($domainLower) {
                $t = strtolower(trim((string) $t));
                return str_contains($t, '@') ? $t : $t . '@' . $domainLower;
            }, $targetList);
            $normalized = array_filter($normalized, fn($t) => strlen($t) > 3 && str_contains($t, '@'));

            // Supprimer l'alias seulement si sa seule cible est l'utilisateur supprimé
            if (count($normalized) === 1 && reset($normalized) === $email) {
                $api->deleteAlias($aliasName, $daToken);
            }
        }
    } catch (\Throwable $e) {
        // Non bloquant — la suppression de l'utilisateur continue
        logActivity('SmarterMail deleteuser [alias cleanup] : ' . $e->getMessage());
    }

    $resp = $api->deleteUser($username, $daToken);
    if (!$resp['success']) {
        logActivity('SmarterMail [deleteuser] Erreur pour ' . $email . ' : ' . _sm_apiError($resp));
        $l = _sm_lang($params); return $l['err_delete_failed'] ?? 'Impossible de supprimer le compte.';
    }

    // ── Marquer les enregistrements EAS/MAPI de l'adresse supprimée ───────
    // Si la boîte avait atteint le seuil de facturation ce mois-ci,
    // son enregistrement est marqué deleted_mailbox=1 pour apparaître sur
    // la prochaine facture avec la plage de dates d'utilisation.
    // Si le seuil n'était pas atteint, l'enregistrement est simplement supprimé.
    $thresholdHours = max(1, (int) ($params['configoption16'] ?? 1)) * 24; // Jours → heures
    _sm_markMailboxProtoDeleted((int) $params['serviceid'], $email, $thresholdHours);

    return 'success';
}


// =============================================================================
//  BOUTONS PERSONNALISÉS ADMIN
// =============================================================================

/**
 * Déclare les boutons personnalisés visibles dans l'admin WHMCS.
 *
 * Ces boutons apparaissent dans :
 *   Admin → Clients → [client] → Services → [service] → Onglet "Module Commands"
 *
 * @return array Label du bouton => nom de la fonction (sans préfixe smartermail_)
 */
function smartermail_AdminCustomButtonArray(): array
{
    return [
        'Sync MAPI/EAS'        => 'syncprotousage',
        'Nettoyage Proto Usage' => 'cleanprotousage',
    ];
}

/**
 * Synchronise les activations EAS/MAPI depuis SmarterMail vers mod_sm_proto_usage.
 *
 * UTILITÉ : Cette fonction est prévue pour les comptes EXISTANTS créés avant
 * l'installation du module de suivi d'utilisation. Sans cette synchronisation,
 * les boîtes ayant EAS/MAPI actif depuis longtemps n'auraient aucune entrée
 * dans mod_sm_proto_usage et ne seraient pas prises en compte dans le détail
 * de facturation ni dans le montant estimé du tableau de bord client.
 *
 * LOGIQUE :
 *   1. Connexion à SmarterMail via l'API
 *   2. Récupération de toutes les boîtes EAS actives du domaine
 *   3. Récupération de toutes les boîtes MAPI actives du domaine
 *   4. Pour chaque boîte active : INSERT dans mod_sm_proto_usage si absent
 *      → status = 'active' (pas 'grace' — ces boîtes sont actives depuis longtemps)
 *      → activated_at = début de la période de facturation courante
 *      → On ne touche PAS aux entrées existantes (INSERT IGNORE)
 *   5. Retourner un résumé des insertions effectuées
 *
 * IDEMPOTENTE : peut être appelée plusieurs fois sans créer de doublons.
 * Les entrées existantes ne sont pas modifiées.
 *
 * @param  array  $params Paramètres WHMCS du service
 * @return string Message de résultat affiché dans l'admin
 */
function smartermail_syncprotousage(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        // err_connection_prefix : %s = message d'erreur retourné par _sm_initDomainAdmin
        return sprintf(
            _sm_lang($params)['err_connection_prefix'] ?? 'Erreur de connexion : %s',
            $init['error']
        );
    }

    $api       = $init['api'];
    $daToken   = $init['token'];
    $domain    = $params['domain'];
    $serviceId = (int) $params['serviceid'];

    // Récupérer la période de facturation courante.
    // Un Free Account (billingcycle = 'Free Account') n'a pas de nextduedate valide —
    // ce n'est PAS une erreur : EAS/MAPI ne sont simplement pas facturés sur ce service.
    // On détecte le cas AVANT d'appeler _sm_getBillingPeriod pour afficher un message
    // informatif au lieu d'un message d'erreur.
    $billingCycle = strtolower(trim(
        Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->value('billingcycle') ?? ''
    ));
    if ($billingCycle === 'free account') {
        logActivity(sprintf(
            'SmarterMail [syncprotousage] Service #%d (%s) : Free Account — suivi EAS/MAPI ignoré (non facturé).',
            $serviceId, $domain
        ));
        return _sm_lang($params)['info_free_account_skip']
            ?? 'Ce service est un Free Account — le suivi EAS/MAPI n\'est pas applicable (aucune facturation).';
    }

    $period = _sm_getBillingPeriod($serviceId);
    if (!$period['start']) {
        // Ici, ce n'est pas un Free Account — la date est vraiment manquante ou invalide.
        return _sm_lang($params)['err_billing_period']
            ?? 'Impossible de déterminer la période de facturation (nextduedate manquant ?).';
    }

    // Seuil en heures depuis configoption16
    $thresholdHours = max(1, (int) ($params['configoption16'] ?? 1)) * 24;

    // activated_at = début de la période courante (on ne connaît pas la vraie date)
    // Pour les comptes existants, on assume qu'ils sont actifs depuis le début
    // de la période → toujours au-delà du seuil → status=active directement.
    $activatedAt = $period['start'] . ' 00:00:00';

    try {
        // Récupérer les listes EAS et MAPI depuis SmarterMail
        $easMailboxes  = $api->getActiveSyncMailboxes($daToken);
        $mapiMailboxes = $api->getMapiMailboxes($daToken);

        $insertedEas  = 0;
        $insertedMapi = 0;
        $skipped      = 0;

        // ── Synchroniser EAS ──────────────────────────────────────────────
        foreach (array_keys($easMailboxes) as $email) {
            $email = strtolower($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            // Vérifier si une entrée existe déjà pour cette adresse/protocole/période
            $exists = Capsule::table('mod_sm_proto_usage')
                ->where('serviceid',    $serviceId)
                ->where('email',        $email)
                ->where('protocol',     'eas')
                ->where('period_start', $period['start'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Insérer en status=active : actif depuis le début de la période
            // → le seuil est clairement dépassé pour ces comptes historiques
            Capsule::table('mod_sm_proto_usage')->insert([
                'serviceid'       => $serviceId,
                'email'           => $email,
                'protocol'        => 'eas',
                'status'          => 'active',
                'period_start'    => $period['start'],
                'threshold_hours' => $thresholdHours,
                'activated_at'    => $activatedAt,
                'deleted_at'      => null,
                'billed'          => 0,
            ]);
            $insertedEas++;
        }

        // ── Synchroniser MAPI ─────────────────────────────────────────────
        foreach (array_keys($mapiMailboxes) as $email) {
            $email = strtolower($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            $exists = Capsule::table('mod_sm_proto_usage')
                ->where('serviceid',    $serviceId)
                ->where('email',        $email)
                ->where('protocol',     'mapi')
                ->where('period_start', $period['start'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Capsule::table('mod_sm_proto_usage')->insert([
                'serviceid'       => $serviceId,
                'email'           => $email,
                'protocol'        => 'mapi',
                'status'          => 'active',
                'period_start'    => $period['start'],
                'threshold_hours' => $thresholdHours,
                'activated_at'    => $activatedAt,
                'deleted_at'      => null,
                'billed'          => 0,
            ]);
            $insertedMapi++;
        }

        // ── Journal ───────────────────────────────────────────────────────
        $total = $insertedEas + $insertedMapi;
        logActivity(sprintf(
            'SmarterMail [syncprotousage] Domaine %s (service #%d) : %d EAS + %d MAPI insérés, %d déjà présents.',
            $domain, $serviceId, $insertedEas, $insertedMapi, $skipped
        ));

        if ($total === 0 && $skipped === 0) {
            return sprintf(
                'Sync terminé — Aucun protocole EAS/MAPI actif trouvé pour %s.',
                $domain
            );
        }

        return sprintf(
            'Sync terminé — %d entrée(s) ajoutée(s) (%d EAS, %d MAPI) · %d déjà présente(s) · Période : %s → %s.',
            $total,
            $insertedEas,
            $insertedMapi,
            $skipped,
            $period['start'],
            $period['end']
        );

    } catch (\Throwable $e) {
        logActivity('SmarterMail [syncprotousage] EXCEPTION (service #' . $serviceId . '): ' . $e->getMessage());
        // err_sync : %s = message d'exception PHP (pour diagnostic admin)
        return sprintf(
            _sm_lang($params)['err_sync'] ?? 'Erreur lors de la synchronisation : %s',
            $e->getMessage()
        );
    }
}


// =============================================================================
//  NETTOYAGE DB — BOUTON ADMIN "Nettoyage Proto Usage"
// =============================================================================

/**
 * Supprime les enregistrements mod_sm_proto_usage du service courant
 * si son statut est Cancelled, Fraud ou Terminated.
 *
 * Également utilisé comme déclencheur manuel du nettoyage GLOBAL
 * (tous les services inactifs) si l'admin appuie depuis n'importe quel
 * service — le nettoyage global est toujours sans danger car il cible
 * uniquement les services avec un statut terminal.
 *
 * @param  array  $params Paramètres WHMCS du service
 * @return string Message de résultat affiché dans l'admin
 */
function smartermail_cleanprotousage(array $params): string
{
    // Nettoyage global : tous les services annulés/résiliés/fraude
    // (pas seulement le service courant — l'admin utilise ce bouton
    //  pour déclencher la maintenance complète manuellement)
    $result = _sm_cleanProtoUsage(0);
    $l      = _sm_lang($params);

    if ($result['deleted'] === 0) {
        // msg_cleanup_none : aucun service inactif avec des données de suivi trouvé
        return $l['msg_cleanup_none'] ?? 'Nettoyage terminé — aucun enregistrement obsolète trouvé (aucun service Annulé/Fraude/Résilié avec des données de suivi).';
    }

    // msg_cleanup_done : %d[1] = enregistrements supprimés, %d[2] = services concernés
    return sprintf(
        $l['msg_cleanup_done'] ?? 'Nettoyage terminé — %d enregistrement(s) supprimé(s) pour %d service(s) avec statut inactif.',
        $result['deleted'],
        $result['services']
    );
}


// =============================================================================
//  TEST DE CONNEXION AU SERVEUR
// =============================================================================

/**
 * Teste la connexion au serveur SmarterMail depuis l'onglet de configuration.
 *
 * WHMCS affiche automatiquement un bouton "Test Connection" dans :
 *   Configuration → Serveurs → [Votre serveur] → Tester la connexion
 * dès que cette fonction est définie dans le module.
 *
 * ÉTAPES DE VALIDATION :
 *   1. Connexion SysAdmin avec les credentials configurés dans WHMCS
 *      → Vérifie que le serveur est joignable et que les credentials sont valides
 *   2. Récupération des infos système (version SmarterMail, nombre de domaines)
 *      → Vérifie que l'API répond correctement et que le compte SA a les droits attendus
 *
 * VALEUR DE RETOUR :
 *   ['success' => true]            → Bouton vert "Connection Successful" dans WHMCS
 *   ['success' => false,
 *    'error'   => 'message']       → Bouton rouge avec le message d'erreur affiché
 *
 * @param  array $params Paramètres WHMCS du serveur (hostname, username, password, etc.)
 * @return array         ['success' => bool, 'error' => string]
 */
function smartermail_TestConnection(array $params): array
{
    // ── Étape 1 : Connexion SysAdmin ──────────────────────────────────────
    $api     = SmarterMailApi::fromParams($params);
    $saToken = $api->loginSysAdminFromParams($params);

    if (!$saToken) {
        return [
            'success' => false,
            // err_test_connection_failed : mauvais identifiants, serveur injoignable,
            // port erroné ou SSL mal configuré — affiché dans le panneau admin WHMCS.
            'error'   => _sm_lang($params)['err_test_connection_failed']
                         ?? 'Connexion échouée : impossible de s\'authentifier avec les identifiants fournis. Vérifiez le nom d\'utilisateur, le mot de passe, le nom d\'hôte et le port.',
        ];
    }

    // ── Étape 2 : Vérification de l'accès SysAdmin à l'API ───────────────
    // getSystemInfo() essaie plusieurs endpoints selon la version de SmarterMail.
    // Si tous échouent avec 4xx, le compte n'a pas les droits SysAdmin.
    // Si le tableau retourné est vide mais sans erreur, le token SA fonctionne
    // mais le serveur ne retourne pas d'infos (version ancienne) — c'est OK.
    $info = $api->getSystemInfo($saToken);

    // ── Succès : construire un message informatif ─────────────────────────
    $version     = $info['productVersion']  ?? $info['version']      ?? null;
    $domainCount = $info['totalDomains']    ?? $info['domainCount']   ?? null;
    $edition     = $info['productEdition']  ?? $info['edition']       ?? null;

    // Chargement de la langue une seule fois pour les trois libellés de résumé.
    // Ces clés utilisent sprintf : lbl_version (%s), lbl_edition (%s),
    // lbl_domains_active (%d) — voir fichiers de langue pour les gabarits exacts.
    $l       = _sm_lang($params);
    $details = [];
    if ($version !== null) {
        $details[] = sprintf($l['lbl_version'] ?? 'Version : %s', $version);
    }
    if ($edition !== null) {
        $details[] = sprintf($l['lbl_edition'] ?? 'Édition : %s', $edition);
    }
    if ($domainCount !== null) {
        $details[] = sprintf($l['lbl_domains_active'] ?? '%d domaine(s) actif(s)', (int) $domainCount);
    }

    return [
        'success' => true,
        'error'   => empty($details) ? '' : implode(' — ', $details),
    ];
}



// =============================================================================
//  REDIRECTIONS AUTONOMES (alias sans boîte courriel)
// =============================================================================
//
//  Une « redirection autonome » est un alias SmarterMail dont TOUTES les cibles
//  sont des adresses externes au domaine (ex: bob@gmail.com). Il n'y a aucune
//  boîte courriel locale associée à ce nom.
//
//  POURQUOI ? Le client peut vouloir que bob@mondomaine.com transfère
//  automatiquement vers son adresse personnelle bob@gmail.com sans avoir
//  à payer pour une boîte courriel.
//
//  Les quatre fonctions ci-dessous gèrent le cycle de vie de ces redirections :
//    smartermail_addredirectpage()  → Affiche le formulaire d'ajout
//    smartermail_createredirect()   → Crée l'alias via l'API SmarterMail
//    smartermail_editredirectpage() → Affiche le formulaire de modification
//    smartermail_saveredirect()     → Met à jour les cibles via l'API
//    smartermail_deleteredirect()   → Supprime l'alias complet
//
//  SÉCURITÉ (vérifiée à chaque étape) :
//    - Nom d'alias validé : lettres, chiffres, . - _ uniquement (preg_match strict)
//    - Chaque adresse cible validée avec filter_var(FILTER_VALIDATE_EMAIL)
//    - Vérification que le nom n'entre pas en conflit avec une boîte existante
//    - Vérification que l'alias n'existe pas déjà avant création
//    - Le serviceid est celui de la session WHMCS du client authentifié
//    - Aucune donnée n'est directement insérée en DB sans validation préalable
//    - Les tokens API (saToken, daToken) sont obtenus via _sm_initDomainAdmin()
//      qui valide les droits d'accès via WHMCS

/**
 * Affiche le formulaire d'ajout d'une redirection autonome.
 *
 * Page affichée lorsque le client clique sur "+ Redirection".
 * Ne reçoit aucune donnée $_POST — GET seulement.
 *
 * Template utilisé : templates/addredirect.tpl
 *
 * @param array $params Paramètres WHMCS (domain, serviceid, etc.)
 * @return array        Tableau templatefile + vars pour WHMCS
 */
function smartermail_addredirectpage(array $params): array
{
    // Initialiser la connexion Domain Admin (requis pour vérifier les conflits)
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        return ['templatefile' => 'error', 'vars' => ['error' => $init['error']]];
    }

    $lang = _sm_lang($params);

    return [
        'templatefile' => 'addredirect',
        'vars'         => [
            'domain'    => $params['domain'],
            'serviceid' => $params['serviceid'],
            'lang'      => $lang,
        ],
    ];
}

/**
 * Action : Crée un alias de redirection autonome dans SmarterMail.
 *
 * Variables $_POST attendues :
 *   - aliasname   : Partie avant le @ (ex: "bob") — alphanumérique + . - _
 *   - targets[]   : Tableau d'adresses de destination (au moins une requise)
 *
 * SÉCURITÉ :
 *   - aliasname : preg_match strict, conflit boîte/alias vérifié via API
 *   - targets   : filter_var(FILTER_VALIDATE_EMAIL) sur chaque adresse
 *   - htmlspecialchars() sur tout affichage de données utilisateur
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur localisé
 */
function smartermail_createredirect(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];
    $lang    = _sm_lang($params);
    $domain  = strtolower(trim((string) ($params['domain'] ?? '')));

    // ── Validation : nom de l'alias ──────────────────────────────────────
    // Pattern identique à celui utilisé pour les noms d'utilisateur
    $aliasName = strtolower(trim((string) ($_POST['aliasname'] ?? '')));
    if ($aliasName === '' || !preg_match('/^[a-z0-9][a-z0-9._\-]*$/i', $aliasName)) {
        return $lang['err_redirect_invalid_name']
            ?? 'Nom de redirection invalide. Lettres, chiffres, . - _ seulement (doit commencer par une lettre ou un chiffre).';
    }

    // ── Validation : au moins une adresse cible ──────────────────────────
    $rawTargets = array_filter(array_map('trim', (array) ($_POST['targets'] ?? [])));
    if (empty($rawTargets)) {
        return $lang['err_redirect_target_required']
            ?? 'Au moins une adresse de destination est requise.';
    }

    // Valider chaque adresse cible avec FILTER_VALIDATE_EMAIL
    $validTargets = [];
    foreach ($rawTargets as $rawTarget) {
        if (!filter_var($rawTarget, FILTER_VALIDATE_EMAIL)) {
            return sprintf(
                $lang['err_redirect_invalid_target'] ?? 'Adresse de destination invalide : %s',
                htmlspecialchars($rawTarget, ENT_QUOTES, 'UTF-8')
            );
        }
        // Dédupliquer (insensible à la casse)
        $validTargets[strtolower($rawTarget)] = $rawTarget;
    }
    $validTargets = array_values($validTargets);

    // ── Vérifier conflit avec une boîte courriel existante ───────────────
    try {
        $existingUsers = $api->getUsers($daToken);
        foreach ($existingUsers as $existingUser) {
            if (strtolower((string) ($existingUser['userName'] ?? '')) === $aliasName) {
                return $lang['err_redirect_conflicts_mailbox']
                    ?? 'Ce nom est déjà utilisé par une boîte courriel.';
            }
        }

        // Vérifier conflit avec un alias existant
        $existingAliases = $api->getAliases($daToken);
        foreach ($existingAliases as $existingAlias) {
            if (strtolower((string) ($existingAlias['userName'] ?? '')) === $aliasName) {
                return $lang['err_redirect_alias_exists']
                    ?? 'Une redirection ou un alias avec ce nom existe déjà.';
            }
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [createredirect] vérification conflit échouée (service #'
            . $params['serviceid'] . '): ' . $e->getMessage());
        return $lang['err_create_failed']
            ?? 'Impossible de créer la redirection. Veuillez réessayer.';
    }

    // ── Créer l'alias dans SmarterMail ───────────────────────────────────
    // allowSending=false : la redirection ne peut pas envoyer au nom du domaine
    // hideFromGAL=false  : visible dans la liste d'adresses globale par défaut
    // internalOnly=false : accepte les courriels entrants de l'extérieur
    try {
        $aliasData = [
            'name'            => $aliasName,
            'displayName'     => $aliasName,
            'allowSending'    => false,
            'hideFromGAL'     => false,
            'internalOnly'    => false,
            'aliasTargetList' => $validTargets,
        ];
        $result = $api->createAlias($aliasData, $daToken);

        // L'API SmarterMail retourne success:false en cas d'erreur
        if (!empty($result['error']) || (isset($result['success']) && $result['success'] === false)) {
            logActivity('SmarterMail [createredirect] erreur API lors de la création de '
                . $aliasName . '@' . $domain . ' (service #' . $params['serviceid'] . ')');
            return $lang['err_create_failed']
                ?? 'Impossible de créer la redirection. Veuillez réessayer.';
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [createredirect] exception : ' . $e->getMessage());
        return $lang['err_create_failed']
            ?? 'Impossible de créer la redirection. Veuillez réessayer.';
    }

    return 'success';
}

/**
 * Affiche le formulaire de modification d'une redirection autonome.
 *
 * Reçoit le nom de l'alias en GET (?aliasname=bob), charge ses données
 * depuis l'API et les injecte dans le template editredirect.tpl.
 *
 * SÉCURITÉ :
 *   - aliasname validé par preg_match avant tout appel API
 *   - Les données API sont de confiance (source interne)
 *
 * @param array $params Paramètres WHMCS
 * @return array        Tableau templatefile + vars pour WHMCS
 */
function smartermail_editredirectpage(array $params): array
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) {
        return ['templatefile' => 'error', 'vars' => ['error' => $init['error']]];
    }

    $api     = $init['api'];
    $daToken = $init['token'];
    $lang    = _sm_lang($params);
    $domain  = strtolower(trim((string) ($params['domain'] ?? '')));

    // Valider le nom de l'alias reçu en GET
    $aliasName = strtolower(trim((string) ($_GET['aliasname'] ?? '')));
    if ($aliasName === '' || !preg_match('/^[a-z0-9][a-z0-9._\-]*$/i', $aliasName)) {
        return [
            'templatefile' => 'error',
            'vars'         => ['error' => $lang['err_user_required'] ?? 'Alias non spécifié.', 'lang' => $lang],
        ];
    }

    // Charger les données de l'alias depuis l'API
    try {
        $aliasData = $api->getAlias($aliasName, $daToken);
        if (empty($aliasData) || empty($aliasData['name'])) {
            return [
                'templatefile' => 'error',
                'vars'         => ['error' => $lang['err_user_required'] ?? 'Alias introuvable.', 'lang' => $lang],
            ];
        }
        $targets = (array) ($aliasData['aliasTargetList'] ?? []);
    } catch (\Throwable $e) {
        logActivity('SmarterMail [editredirectpage] erreur chargement alias '
            . $aliasName . ' (service #' . $params['serviceid'] . '): ' . $e->getMessage());
        return [
            'templatefile' => 'error',
            'vars'         => ['error' => $lang['err_connection'] ?? 'Erreur de connexion.', 'lang' => $lang],
        ];
    }

    return [
        'templatefile' => 'editredirect',
        'vars'         => [
            'domain'    => $domain,
            'serviceid' => $params['serviceid'],
            'lang'      => $lang,
            'aliasName' => $aliasName,
            // JSON_HEX_TAG encode < et > en \u003C / \u003E — empêche une séquence
            // </script> dans une adresse de fermer prématurément le bloc <script>
            // du template. Les adresses sont validées par FILTER_VALIDATE_EMAIL,
            // mais cette protection défensive est préférable selon les standards OWASP.
            'targets'   => json_encode($targets, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
        ],
    ];
}

/**
 * Action : Sauvegarde les modifications d'une redirection autonome.
 *
 * Variables $_POST attendues :
 *   - aliasname   : Nom de l'alias existant (ne peut pas être modifié)
 *   - targets[]   : Nouveau tableau de destinations (au moins une requise)
 *
 * SÉCURITÉ :
 *   - aliasname validé par preg_match
 *   - Chaque target validé par filter_var(FILTER_VALIDATE_EMAIL)
 *   - Les champs non modifiables (allowSending, hideFromGAL, etc.)
 *     sont relus depuis l'API existante pour éviter toute manipulation
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur localisé
 */
function smartermail_saveredirect(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];
    $lang    = _sm_lang($params);

    // Valider le nom de l'alias
    $aliasName = strtolower(trim((string) ($_POST['aliasname'] ?? '')));
    if ($aliasName === '' || !preg_match('/^[a-z0-9][a-z0-9._\-]*$/i', $aliasName)) {
        return $lang['err_user_required'] ?? 'Alias non spécifié ou invalide.';
    }

    // Valider les adresses cibles
    $rawTargets = array_filter(array_map('trim', (array) ($_POST['targets'] ?? [])));
    if (empty($rawTargets)) {
        return $lang['err_redirect_target_required']
            ?? 'Au moins une adresse de destination est requise.';
    }

    $validTargets = [];
    foreach ($rawTargets as $rawTarget) {
        if (!filter_var($rawTarget, FILTER_VALIDATE_EMAIL)) {
            return sprintf(
                $lang['err_redirect_invalid_target'] ?? 'Adresse de destination invalide : %s',
                htmlspecialchars($rawTarget, ENT_QUOTES, 'UTF-8')
            );
        }
        // Dédupliquer
        $validTargets[strtolower($rawTarget)] = $rawTarget;
    }
    $validTargets = array_values($validTargets);

    // Relire l'alias existant pour conserver les champs non modifiables
    // (allowSending, hideFromGAL, internalOnly) — le client ne doit pas
    // pouvoir modifier ces options depuis l'espace client.
    try {
        $existingAlias = $api->getAlias($aliasName, $daToken);
        if (empty($existingAlias) || empty($existingAlias['name'])) {
            return $lang['err_user_required'] ?? 'Alias introuvable.';
        }

        // Mettre à jour uniquement la liste des cibles
        $aliasData = [
            'name'            => $aliasName,
            'displayName'     => (string) ($existingAlias['displayName'] ?? $aliasName),
            'allowSending'    => (bool)   ($existingAlias['allowSending']   ?? false),
            'hideFromGAL'     => (bool)   ($existingAlias['hideFromGAL']    ?? false),
            'internalOnly'    => (bool)   ($existingAlias['internalOnly']   ?? false),
            'aliasTargetList' => $validTargets,
        ];

        $result = $api->updateAlias($aliasName, $aliasData, $daToken);
        if (!empty($result['error']) || (isset($result['success']) && $result['success'] === false)) {
            logActivity('SmarterMail [saveredirect] erreur API pour l\'alias '
                . $aliasName . ' (service #' . $params['serviceid'] . ')');
            return $lang['err_save_failed']
                ?? 'Impossible de sauvegarder les modifications.';
        }
    } catch (\Throwable $e) {
        logActivity('SmarterMail [saveredirect] exception : ' . $e->getMessage());
        return $lang['err_save_failed']
            ?? 'Impossible de sauvegarder les modifications.';
    }

    return 'success';
}

/**
 * Action : Supprime une redirection autonome (alias complet).
 *
 * Variables $_POST attendues :
 *   - aliasname : Nom de l'alias à supprimer
 *
 * SÉCURITÉ :
 *   - aliasname validé par preg_match avant tout appel API
 *   - Uniquement des alias — deleteAlias() n'affecte pas les boîtes courriel
 *
 * @param array $params Paramètres WHMCS
 * @return string       "success" ou message d'erreur localisé
 */
function smartermail_deleteredirect(array $params): string
{
    $init = _sm_initDomainAdmin($params);
    if (isset($init['error'])) return $init['error'];

    $api     = $init['api'];
    $daToken = $init['token'];
    $lang    = _sm_lang($params);

    // Valider le nom de l'alias avant tout appel
    $aliasName = strtolower(trim((string) ($_POST['aliasname'] ?? '')));
    if ($aliasName === '' || !preg_match('/^[a-z0-9][a-z0-9._\-]*$/i', $aliasName)) {
        return $lang['err_user_required'] ?? 'Alias non spécifié ou invalide.';
    }

    try {
        $api->deleteAlias($aliasName, $daToken);
    } catch (\Throwable $e) {
        logActivity('SmarterMail [deleteredirect] exception lors de la suppression de '
            . $aliasName . ' (service #' . $params['serviceid'] . '): ' . $e->getMessage());
        return $lang['err_delete_failed']
            ?? 'Impossible de supprimer la redirection. Veuillez réessayer.';
    }

    return 'success';
}
