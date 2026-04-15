<?php
/**
 * ============================================================================
 *  SmarterMailApi.php — Classe wrapper pour l'API REST de SmarterMail
 * ============================================================================
 *
 * 
 * Smartermail API doc : https://mail.smartertools.com/Documentation/api#/topics/overview
 *
 * Cette classe encapsule TOUS les appels à l'API REST de SmarterMail.
 * Elle est le seul point de contact avec le serveur SmarterMail dans ce module.
 * Aucun appel cURL ne doit se faire ailleurs que dans cette classe.
 *
 * COMPATIBILITÉ :
 *   - SmarterMail v16.x et supérieur (API REST v1)
 *   - PHP 8.0+
 *
 * DOCUMENTATION API OFFICIELLE :
 *   https://mail.smartertools.com/Documentation/api
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  MODÈLE D'AUTHENTIFICATION DE SMARTERMAIL
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  SmarterMail utilise un système de tokens JWT Bearer.
 *  Il existe deux niveaux d'accès distincts :
 *
 *  1. SYSADMIN (SA) — Administrateur système global
 *     - Accès total au serveur SmarterMail
 *     - Peut créer, modifier, supprimer des domaines
 *     - Peut "impersonifier" n'importe quel administrateur de domaine
 *     - Credentials : ceux configurés dans les paramètres du serveur WHMCS
 *     - Endpoint de login : POST api/v1/auth/authenticate-user
 *     - Retourne : { "accessToken": "eyJ..." }
 *
 *  2. DOMAIN ADMIN (DA) — Administrateur d'un domaine spécifique
 *     - Accès limité à SON domaine uniquement
 *     - Peut gérer les utilisateurs, alias, paramètres du domaine
 *     - Obtenu via impersonification par le SA (pas de credentials séparés)
 *     - Endpoint d'impersonification : POST api/v1/settings/sysadmin/manage-domain/{domain}
 *     - Retourne : { "impersonateAccessToken": "eyJ..." }
 *
 *  FLUX TYPIQUE D'UTILISATION :
 *    $saToken = $api->loginSysAdmin(...)        // Connexion SA
 *    $daToken = $api->loginDomainAdmin(saToken) // Impersonification DA
 *    $api->getUsers($daToken)                   // Actions sur le domaine
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  STRUCTURE DE RETOUR DE TOUTES LES MÉTHODES PUBLIQUES
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Toutes les méthodes qui appellent l'API retournent un tableau uniforme :
 *  [
 *    'success' => bool,       // true si code HTTP 2xx
 *    'code'    => int,        // Code HTTP (200, 400, 401, 404, 500...)
 *    'data'    => array|null, // Corps JSON décodé de la réponse
 *    'error'   => string|null // Message d'erreur lisible (si success = false)
 *  ]
 *
 *  Les méthodes utilitaires (getUsers, getDomainInfo, etc.) retournent
 *  directement un array PHP simplifié pour faciliter l'utilisation.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  TYPES DE COMPTES DANS SMARTERMAIL (accountType)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  L'API retourne souvent une liste mixte de comptes. Le champ 'accountType'
 *  permet de les distinguer :
 *    0 = Utilisateur standard (boîte courriel normale)
 *    1 = Administrateur de domaine
 *    2 = Administrateur système
 *    4 = Alias (redirection)
 *    5 = Liste de diffusion (mailing list)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  NOTES IMPORTANTES SUR L'API
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  - Les tailles (disque, mailbox) sont TOUJOURS en BYTES dans l'API.
 *    Ex: 1 GB = 1 073 741 824 bytes. On convertit dans cette classe.
 *
 *  - Les tokens n'ont pas de mécanisme de révocation côté serveur dans
 *    les versions actuelles (sm_LogoutToken du module original était vide).
 *    Il suffit de les laisser expirer naturellement (courte durée de vie).
 *
 *  - Les booléens JSON sont mappés en true/false PHP automatiquement
 *    par json_decode($result, true).
 *
 *  - L'API peut retourner HTTP 200 même pour certaines erreurs métier.
 *    Toujours vérifier le champ 'message' dans la réponse si 'success' = true
 *    mais le comportement attendu n'a pas eu lieu.
 */

// Sécurité : ce fichier ne doit jamais être appelé directement,
// seulement inclus via le module WHMCS.
if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}


class SmarterMailApi
{
    // ─────────────────────────────────────────────────────────────────────────
    // Propriétés de la classe
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @var string URL de base complète du serveur SmarterMail.
     *             Construite dans le constructeur à partir du hostname, protocole et port.
     *             Exemple : "https://mail.votreserveur.com:443" ou "http://192.168.1.10:9998"
     *             Toutes les requêtes API seront préfixées par cette URL.
     */
    private string $baseUrl;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @var bool Indique si cURL doit vérifier le certificat SSL du serveur.
     *
     *           FALSE (défaut) : Recommandé pour la plupart des déploiements,
     *           car SmarterMail utilise souvent des certificats auto-signés
     *           ou des certificats internes non reconnus par l'autorité du serveur WHMCS.
     *
     *           TRUE : Active la vérification stricte du certificat.
     *           Utiliser seulement si le certificat du serveur SmarterMail est
     *           signé par une autorité de certification reconnue (Let's Encrypt, etc.)
     *           ET que le hostname correspond exactement au CN du certificat.
     *
     *           ⚠️  SÉCURITÉ : Toujours true en production. Passer false
     *           explicitement uniquement pour le développement local avec
     *           des certificats auto-signés ou des connexions HTTP internes.
     */
    private bool $verifySsl;


    // ─────────────────────────────────────────────────────────────────────────
    // Constructeur et factory
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Constructeur — Initialise la connexion vers un serveur SmarterMail.
     *
     * @param string $hostname  Hostname ou adresse IP du serveur SmarterMail.
     *                          Sans protocole ni port. Ex: "mail.exemple.com" ou "192.168.1.10"
     *
     * @param bool   $secure    true = utiliser HTTPS, false = HTTP.
     *                          Utiliser HTTPS en production pour protéger
     *                          les tokens d'authentification en transit.
     *
     * @param int    $port      Port d'écoute de l'interface web SmarterMail.
     *                          Défauts courants :
     *                            - HTTP standard  : 80
     *                            - HTTPS standard : 443
     *                            - SmarterMail HTTP par défaut  : 9998
     *                            - SmarterMail HTTPS par défaut : 443
     *                          Si le port est le port standard (80 pour HTTP,
     *                          443 pour HTTPS), il n'est PAS ajouté à l'URL.
     *
     * @param bool   $verifySsl Voir documentation de la propriété $verifySsl.
     *                          Défaut : true — la vérification SSL est ACTIVÉE par défaut
     *                          pour éviter les attaques MITM. Passer false explicitement
     *                          uniquement pour les environnements de développement local
     *                          (ex: certificat auto-signé sur HTTP interne).
     */
    public function __construct(string $hostname, bool $secure, int $port, bool $verifySsl = true)
    {
        // Choisir le protocole selon le paramètre $secure
        $scheme = $secure ? 'https' : 'http';

        // Construire l'URL de base sans port pour l'instant
        // rtrim pour s'assurer qu'il n'y a pas de slash final sur le hostname
        $this->baseUrl = $scheme . '://' . rtrim($hostname, '/');

        // Ajouter le port seulement s'il est non-standard
        // (évite des URLs comme "https://exemple.com:443" qui sont valides mais inutiles)
        $isStandardPort = ($secure && $port === 443) || (!$secure && $port === 80);
        if (!$isStandardPort) {
            $this->baseUrl .= ':' . $port;
        }

        $this->verifySsl = $verifySsl;
    }

    /**
     * Factory statique — Crée une instance à partir du tableau $params de WHMCS.
     *
     * WHMCS fournit automatiquement les informations du serveur dans le tableau
     * $params passé à chaque fonction du module. Cette méthode simplifie
     * l'instanciation en lisant directement ces clés standardisées.
     *
     * Clés utilisées dans $params :
     *   - $params['serverhostname'] : Hostname configuré dans WHMCS → Serveurs
     *   - $params['serversecure']   : Checkbox "SSL" cochée dans WHMCS (0 ou 1)
     *   - $params['serverport']     : Port configuré (peut être vide/0)
     *
     * Si serverport est vide ou 0, on utilise le port standard selon le protocole.
     *
     * Usage typique au début de chaque fonction du module :
     *   $api = SmarterMailApi::fromParams($params);
     *
     * @param  array $params Tableau de paramètres fourni par WHMCS
     * @return self          Nouvelle instance configurée
     */
    public static function fromParams(array $params): self
    {
        $secure      = (bool) $params['serversecure'];
        $defaultPort = $secure ? 443 : 80;
        $port        = (int) ($params['serverport'] ?: $defaultPort);

        // Activer la vérification SSL seulement en HTTPS.
        // Les serveurs SmarterMail utilisent souvent des certificats auto-signés,
        // donc on ne vérifie pas en HTTP. En HTTPS, on vérifie le certificat.
        $verifySsl = $secure;

        return new self(
            $params['serverhostname'],
            $secure,
            $port,
            $verifySsl
        );
    }


    // =========================================================================
    //  COUCHE HTTP — Méthodes privées de transport
    // =========================================================================
    //
    //  Ces méthodes gèrent la communication bas-niveau avec l'API SmarterMail.
    //  Elles ne sont jamais appelées directement depuis l'extérieur de la classe.
    //  Toute la logique cURL est concentrée ici pour faciliter la maintenance
    //  (ex: ajouter du logging, changer la lib HTTP, etc.).
    // =========================================================================

    /**
     * Exécute une requête HTTP vers l'API SmarterMail.
     *
     * C'est le point d'entrée unique pour TOUS les appels API.
     * Les méthodes publiques post() et get() sont des raccourcis vers cette méthode.
     *
     * GESTION DES ERREURS :
     *   - Erreur cURL réseau (serveur injoignable, timeout) → success: false, code: 0
     *   - Réponse HTTP 4xx/5xx → success: false, code: <httpCode>
     *   - Réponse HTTP 2xx → success: true, data: <contenu JSON décodé>
     *
     * NOTE SUR LE SSL :
     *   CURLOPT_SSL_VERIFYHOST = 2 signifie "vérifier que le CN du certificat
     *   correspond au hostname". La valeur 1 est dépréciée par cURL.
     *
     * NOTE SUR LES TIMEOUTS :
     *   - CURLOPT_CONNECTTIMEOUT = 10s : délai max pour établir la connexion TCP
     *   - CURLOPT_TIMEOUT = 30s : délai max pour l'opération complète
     *   Ces valeurs peuvent nécessiter un ajustement si le serveur SmarterMail
     *   est lent à répondre (ex: grosse opération de suppression de domaine).
     *
     * @param string $method   Méthode HTTP : 'GET', 'POST', ou 'DELETE'
     * @param string $endpoint Chemin de l'endpoint API, sans slash initial.
     *                         Ex: "api/v1/auth/authenticate-user"
     * @param array  $data     Corps de la requête (pour POST). Sera encodé en JSON.
     *                         Ignoré pour GET et DELETE.
     * @param string $token    Token JWT Bearer pour l'authentification.
     *                         Chaîne vide pour les endpoints publics (ex: login).
     *
     * @return array Tableau standardisé :
     *               [
     *                 'success' => bool,        // true si HTTP 200-299
     *                 'code'    => int,          // Code HTTP reçu (0 si erreur réseau)
     *                 'data'    => array|null,   // JSON décodé ou null
     *                 'error'   => string|null   // Message d'erreur ou null si succès
     *               ]
     */
    private function request(string $method, string $endpoint, array $data = [], string $token = ''): array
    {
        // Construire l'URL complète en combinant l'URL de base et l'endpoint
        // ltrim retire un éventuel slash en début d'endpoint pour éviter les doubles slashes
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        // Initialiser la session cURL
        $ch = curl_init($url);

        // ── En-têtes HTTP ────────────────────────────────────────────────────
        // Content-Type et Accept : l'API SmarterMail communique exclusivement en JSON
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Ajouter le token d'authentification si fourni
        // Format Bearer Token selon RFC 6750 (standard OAuth2/JWT)
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        // ── Options cURL communes ────────────────────────────────────────────
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Retourner la réponse comme string (pas echo)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl); // Vérifier le certificat SSL du serveur
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0); // Vérifier que le CN correspond au hostname
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);           // Timeout global de 30 secondes
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    // Timeout connexion TCP de 10 secondes

        // ── Options spécifiques à la méthode HTTP ────────────────────────────
        if ($method === 'POST') {
            // Encoder le corps en JSON
            $body = json_encode($data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            // Content-Length est techniquement optionnel en HTTP/1.1 avec chunked encoding,
            // mais SmarterMail semble l'exiger pour certains endpoints
            $headers[] = 'Content-Length: ' . strlen($body);

        } elseif ($method === 'DELETE') {
            // DELETE sans corps (les infos sont dans l'URL pour SmarterMail)
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        }
        // GET est la méthode par défaut de cURL, pas besoin de l'expliciter

        // Appliquer les en-têtes (après avoir construit la liste complète)
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // ── Exécution de la requête ──────────────────────────────────────────
        $result    = curl_exec($ch);          // Corps de la réponse HTTP (string)
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); // Code de statut HTTP
        $curlError = curl_error($ch);         // Message d'erreur cURL (vide si succès)
        curl_close($ch);

        // ── Gestion des erreurs réseau (pas de réponse du serveur) ───────────
        if ($curlError) {
            // Erreurs possibles : connexion refusée, timeout, DNS non résolu,
            // problème de certificat SSL, etc.
            return [
                'success' => false,
                'code'    => 0,   // 0 indique une erreur réseau, pas HTTP
                'data'    => null,
                'error'   => 'Erreur réseau cURL : ' . $curlError,
            ];
        }

        // ── Décodage de la réponse JSON ──────────────────────────────────────
        // json_decode avec true = tableau associatif (pas objet stdClass)
        // $result peut être vide si l'API retourne un 204 No Content par exemple
        $decoded = !empty($result) ? json_decode($result, true) : null;

        // Succès = tout code HTTP entre 200 et 299 inclus
        $success = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $success,
            'code'    => $httpCode,
            'data'    => $decoded,
            // Message d'erreur : priorité au message de l'API, sinon message générique
            'error'   => $success ? null : ($decoded['message'] ?? 'Erreur HTTP ' . $httpCode),
        ];
    }

    /**
     * Raccourci pour les requêtes POST vers l'API SmarterMail.
     *
     * La majorité des endpoints SmarterMail utilisent POST, même pour
     * des opérations de lecture (ex: la recherche de comptes se fait
     * par POST avec des critères dans le corps, pas par GET avec query params).
     *
     * @param string $endpoint Chemin de l'endpoint (sans slash initial)
     * @param array  $data     Corps JSON à envoyer
     * @param string $token    Token d'authentification Bearer
     * @return array           Voir la documentation de request()
     */
    public function post(string $endpoint, array $data, string $token): array
    {
        return $this->request('POST', $endpoint, $data, $token);
    }

    /**
     * Raccourci pour les requêtes GET vers l'API SmarterMail.
     *
     * Les GET sont utilisés pour récupérer des ressources par leur identifiant
     * quand celui-ci est dans l'URL (ex: récupérer les infos d'un domaine,
     * les mailboxes EAS/MAPI, les paramètres du domaine).
     *
     * @param string $endpoint Chemin de l'endpoint (sans slash initial)
     * @param string $token    Token d'authentification Bearer
     * @return array           Voir la documentation de request()
     */
    public function get(string $endpoint, string $token): array
    {
        return $this->request('GET', $endpoint, [], $token);
    }


    // =========================================================================
    //  AUTHENTIFICATION
    // =========================================================================
    //
    //  SmarterMail utilise des tokens JWT à courte durée de vie.
    //  Un nouveau token doit être obtenu à chaque requête ou groupe de requêtes
    //  dans le même contexte d'exécution PHP (pas de persistance entre requêtes).
    //
    //  ⚠️  IMPORTANT : Les tokens expirent rapidement (quelques minutes).
    //  Ne pas les stocker en base de données ou en cache pour une réutilisation
    //  ultérieure. Obtenir un nouveau token à chaque appel au module.
    // =========================================================================

    /**
     * Authentifie un administrateur système (SysAdmin) sur SmarterMail.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.AuthenticationController/AuthenticateUser/post
     * 
     * Le SysAdmin est le compte le plus privilégié. Il a accès à tous les
     * domaines et peut effectuer toutes les opérations d'administration.
     *
     * Les credentials sont ceux configurés dans WHMCS :
     * Configuration → Serveurs → [Votre serveur] → Nom d'utilisateur / Mot de passe
     *
     * Endpoint API : POST api/v1/auth/authenticate-user
     * Corps : { "username": "...", "password": "..." }
     * Réponse succès (200) : { "accessToken": "eyJhbGci...", ... }
     *
     * @param string $username Nom d'utilisateur de l'administrateur système SmarterMail
     * @param string $password Mot de passe correspondant
     *
     * @return string|null Token JWT Bearer à utiliser dans les requêtes suivantes,
     *                     ou null si l'authentification échoue (mauvais credentials,
     *                     serveur inaccessible, etc.)
     */
    public function loginSysAdmin(string $username, string $password): ?string
    {
        $resp = $this->request('POST', 'api/v1/auth/authenticate-user', [
            'username' => $username,
            'password' => $password,
        ]);
        // Retourner null pour signaler l'échec proprement à l'appelant
        return $resp['success'] ? ($resp['data']['accessToken'] ?? null) : null;
    }

    /**
     * Obtient un token de Domain Admin par impersonification via le SysAdmin.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/ManageDomain/post
     * 
     * CONCEPT D'IMPERSONIFICATION :
     * Plutôt que d'avoir un mot de passe pour chaque administrateur de domaine,
     * le SysAdmin peut "devenir" n'importe quel Domain Admin en utilisant son
     * propre token. Cela permet d'effectuer des opérations dans le contexte
     * d'un domaine spécifique sans connaître le mot de passe de l'admin du domaine.
     *
     * Le token retourné (impersonateAccessToken) a les mêmes permissions qu'un
     * Domain Admin : il peut gérer les utilisateurs, alias, et paramètres du
     * domaine, mais ne peut PAS accéder aux autres domaines ni aux settings système.
     *
     * Endpoint API : POST api/v1/settings/sysadmin/manage-domain/{domain}
     * Corps : (vide)
     * Réponse succès (200) : { "impersonateAccessToken": "eyJhbGci...", ... }
     *
     * @param string $saToken Token SysAdmin obtenu via loginSysAdmin()
     * @param string $domain  Nom de domaine à gérer (ex: "exemple.com")
     *
     * @return string|null Token de Domain Admin ou null si le domaine n'existe pas
     *                     ou si le token SA est invalide
     */
    public function loginDomainAdmin(string $saToken, string $domain): ?string
    {
        $resp = $this->request(
            'POST',
            'api/v1/settings/sysadmin/manage-domain/' . urlencode($domain),
            [],       // Corps vide — l'identité du domaine est dans l'URL
            $saToken
        );
        return $resp['success'] ? ($resp['data']['impersonateAccessToken'] ?? null) : null;
    }

    /**
     * Raccourci : authentifie le SysAdmin directement depuis les params WHMCS.
     *
     * WHMCS fournit automatiquement les credentials du serveur dans $params :
     *   - $params['serverusername'] : Nom d'utilisateur admin SmarterMail
     *   - $params['serverpassword'] : Mot de passe (décrypté automatiquement par WHMCS)
     *
     * Usage typique dans une fonction du module :
     *   $saToken = $api->loginSysAdminFromParams($params);
     *   if (!$saToken) return 'Erreur de connexion au serveur.';
     *
     * @param  array       $params Tableau $params fourni par WHMCS
     * @return string|null         Token SysAdmin ou null si échec
     */
    public function loginSysAdminFromParams(array $params): ?string
    {
        return $this->loginSysAdmin($params['serverusername'], $params['serverpassword']);
    }


    /**
     * Authentifie un Domain Admin DIRECTEMENT avec ses propres credentials.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.AuthenticationController/AuthenticateUser/post
     * 
     * DIFFÉRENCE AVEC loginDomainAdmin() (impersonification) :
     *   - loginDomainAdmin() : le SysAdmin "devient" le DA via son propre token
     *   - loginDomainAdminDirect() : le DA s'authentifie avec ses credentials réels
     *
     * UTILISATION DANS L'ESPACE CLIENT :
     *   Quand un client gère son compte, on utilise CETTE méthode plutôt que
     *   l'impersonification SA, afin que le token obtenu soit strictement limité
     *   au domaine du client. Même si l'appel est détourné, il ne peut pas
     *   affecter un autre domaine car le token DA n'a pas accès aux autres domaines.
     *
     * Le username doit être au format complet : "admin@domaine.com"
     * (la partie @domaine est requise pour que SmarterMail identifie le domaine)
     *
     * Endpoint API : POST api/v1/auth/authenticate-user
     * Corps : { "username": "admin@domaine.com", "password": "..." }
     * Réponse succès (200) : { "accessToken": "eyJhbGci..." }
     *
     * @param string $username Username complet du DA (ex: "xkqmtparis@client.com")
     * @param string $password Mot de passe du DA (stocké dans $params['password'])
     * @return string|null     Token d'accès DA ou null si échec
     */
    public function loginDomainAdminDirect(string $username, string $password): ?string
    {
        $resp = $this->request('POST', 'api/v1/auth/authenticate-user', [
            'username' => $username,
            'password' => $password,
        ]);
        return $resp['success'] ? ($resp['data']['accessToken'] ?? null) : null;
    }

    /**
     * Version détaillée de loginDomainAdmin() — expose le code HTTP et l'erreur API.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/ManageDomain/post
     * 
     * Utilisée en phase de développement pour diagnostiquer les échecs d'impersonification.
     * Retourne un tableau structuré au lieu d'un simple ?string.
     *
     * @param string $saToken Token SysAdmin
     * @param string $domain  Nom de domaine
     * @return array ['token' => ?string, 'code' => int, 'error' => ?string, 'rawData' => array]
     */
    public function loginDomainAdminFull(string $saToken, string $domain): array
    {
        $resp = $this->request(
            'POST',
            'api/v1/settings/sysadmin/manage-domain/' . urlencode($domain),
            [],
            $saToken
        );

        $token = null;
        if ($resp['success']) {
            $token = $resp['data']['impersonateAccessToken'] ?? null;
        }

        return [
            'token'   => $token,
            'code'    => $resp['code'],
            'error'   => $resp['error'],
            'rawData' => $resp['data'] ?? [],
        ];
    }

    /**
     * Version détaillée de loginDomainAdminDirect() — expose le code HTTP et l'erreur API.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.AuthenticationController/AuthenticateUser/post
     * 
     * Teste aussi les deux formats de username possibles :
     *   - Format A : "user@domain.com"  (domain admins dans la plupart des versions SM)
     *   - Format B : "user"             (sans domaine — certaines configurations)
     *
     * Retourne le résultat du premier format qui réussit, ou le détail du dernier échec.
     *
     * @param string $username Username seul (ex: "xkqmtparis")
     * @param string $password Mot de passe
     * @param string $domain   Domaine (ex: "client.com")
     * @return array [
     *   'token'        => ?string,  // Token si succès, null sinon
     *   'formatUsed'   => string,   // Format de username qui a fonctionné
     *   'attemptLog'   => array,    // Détail de chaque tentative [format => [code, error]]
     * ]
     */
    public function loginDomainAdminDirectFull(string $username, string $password, string $domain): array
    {
        $attempts = [
            'user@domain' => $username . '@' . $domain,  // Format A (standard)
            'user seul'   => $username,                   // Format B (fallback)
        ];

        $attemptLog = [];

        foreach ($attempts as $formatLabel => $usernameToTry) {
            $resp = $this->request('POST', 'api/v1/auth/authenticate-user', [
                'username' => $usernameToTry,
                'password' => $password,
            ]);

            $attemptLog[$formatLabel] = [
                'username' => $usernameToTry,
                'code'     => $resp['code'],
                'error'    => $resp['error'],
            ];

            if ($resp['success'] && !empty($resp['data']['accessToken'])) {
                return [
                    'token'      => $resp['data']['accessToken'],
                    'formatUsed' => $formatLabel . ' (' . $usernameToTry . ')',
                    'attemptLog' => $attemptLog,
                ];
            }
        }

        return [
            'token'      => null,
            'formatUsed' => '',
            'attemptLog' => $attemptLog,
        ];
    }


    // =========================================================================
    //  GESTION DES DOMAINES
    // =========================================================================
    //
    //  Ces méthodes gèrent le cycle de vie complet d'un domaine courriel dans
    //  SmarterMail. Elles requièrent toutes un token SYSADMIN (pas Domain Admin)
    //  car les opérations de création/suppression sont des opérations système.
    //
    //  Un "domaine" dans SmarterMail correspond à une organisation cliente :
    //  tous les utilisateurs d'un domaine partagent le même @nom-de-domaine.com
    // =========================================================================

    /**
     * Vérifie si un domaine existe déjà dans SmarterMail.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/GetDomain/get
     * 
     * Utilisé avant la création pour éviter l'erreur "DOMAIN_ADD_ERROR_NAME_IN_USE".
     * Si le domaine existe, le module peut retourner une erreur explicite à l'admin.
     *
     * Méthode : on tente de GET les infos du domaine.
     *   - HTTP 200 → le domaine existe
     *   - HTTP 404 → le domaine n'existe pas
     *   - Autre    → erreur (serveur, auth...) — on retourne false par prudence
     *
     * Endpoint API : GET api/v1/settings/sysadmin/domain/{domain}
     * Token requis : SysAdmin
     *
     * @param string $domain  Nom de domaine à vérifier (ex: "client.com")
     * @param string $saToken Token SysAdmin
     * @return bool           true si le domaine existe, false sinon
     */
    public function domainExists(string $domain, string $saToken): bool
    {
        $resp = $this->get('api/v1/settings/sysadmin/domain/' . urlencode($domain), $saToken);
        // On considère que seul un HTTP 200 exact confirme l'existence du domaine
        return $resp['code'] === 200;
    }

    /**
     * Crée un nouveau domaine courriel dans SmarterMail.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/AddDomain/post
     * 
     * PROCESSUS DE CRÉATION :
     * Cette méthode crée le domaine ET son premier compte administrateur
     * en une seule requête API. L'administrateur créé est le "Domain Admin"
     * qui sera utilisé pour l'impersonification future.
     *
     * Dans notre module, cet admin est le compte "secret" (username aléatoire)
     * que le client ne connaît pas. C'est notre compte de service interne.
     *
     * VALEURS DES LIMITES (domainData) :
     *   0 = illimité pour la plupart des champs numériques (userLimit, maxSize, etc.)
     *  -1 = désactivé (ex: -1 pour aliasLimit = les alias sont désactivés)
     *
     * TAILLES : Fournir en BYTES dans l'API.
     *   Ex: maxSize = 10 * 1024 * 1024 * 1024 pour 10 GB
     *       0 = pas de limite de taille
     *
     * Le tableau $options est fusionné (array_merge) sur les valeurs par défaut,
     * ce qui permet d'override seulement les valeurs nécessaires.
     *
     * Endpoint API : POST api/v1/settings/sysadmin/domain-put
     * Token requis : SysAdmin
     *
     * Erreurs possibles dans $resp['error'] :
     *   - "DOMAIN_ADD_ERROR_NAME_IN_USE"   → domaine déjà existant
     *   - "DOMAIN_ADD_ERROR_INVALID_NAME"  → nom invalide
     *
     * @param string $domain        Nom de domaine à créer (ex: "client.com")
     * @param string $adminUsername Username de l'administrateur du domaine (compte secret)
     * @param string $adminPassword Mot de passe de l'administrateur
     * @param array  $options       Options à fusionner dans domainData :
     *                              - 'path'       : Chemin de stockage sur le serveur
     *                              - 'outgoingIP' : IP de sortie des courriels
     *                              - 'userLimit'  : Nombre max d'utilisateurs (0 = illimité)
     *                              - 'maxSize'    : Taille max du domaine en bytes
     *                              - 'hostname'   : Hostname de messagerie
     * @param string $saToken       Token SysAdmin
     * @return array                Tableau standardisé
     */
    public function createDomain(
        string $domain,
        string $adminUsername,
        string $adminPassword,
        array  $options,
        string $saToken
    ): array {
        // Champs documentés dans domainData (POST api/v1/settings/sysadmin/domain-put) :
        //   name, path, hostname, isSplitDomain, secondaryStoragePath,
        //   secondaryStorageAgeDays, userLimit, aliasLimit, listLimit, maxSize.
        //
        // Champs NON documentés retirés :
        //   - mainDomainAdmin : redondant avec adminUsername au niveau racine
        //   - outgoingIP      : doit être appliqué via setDomainSettings() après création
        //
        // $options peut contenir : path, hostname, userLimit, aliasLimit, listLimit, maxSize
        $domainData = array_merge([
            'name'       => $domain,
            'hostname'   => 'mail.' . $domain,
            'userLimit'  => 0,   // 0 = illimité
            'maxSize'    => 0,   // 0 = illimité (bytes)
            'aliasLimit' => 0,   // 0 = illimité
            'listLimit'  => 0,   // 0 = illimité
        ], array_intersect_key($options, array_flip([
            'name', 'path', 'hostname', 'userLimit', 'aliasLimit', 'listLimit', 'maxSize',
            'isSplitDomain', 'secondaryStoragePath', 'secondaryStorageAgeDays',
        ])));

        return $this->post('api/v1/settings/sysadmin/domain-put', [
            'adminUsername'                   => $adminUsername,
            'adminPassword'                   => $adminPassword,
            'deliverLocallyForExternalDomain' => false,
            'domainLocation'                  => 0,   // 0 = local path
            'domainLocationAddress'           => '',
            'domainData'                      => $domainData,
        ], $saToken);
    }

    /**
     * Applique ou met à jour les paramètres d'un domaine existant.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/SetDomainSettings/post
     * 
     * Cette méthode est plus flexible que createDomain() car elle peut modifier
     * n'importe quel sous-ensemble des paramètres sans affecter les autres.
     * SmarterMail fait un merge partiel côté serveur.
     *
     * PARAMÈTRES COURANTS dans $settings :
     *   'isEnabled'                         => bool   — Activer/désactiver le domaine
     *   'enableMailForwarding'              => bool   — Autoriser la redirection de courriels
     *   'enableSmtpAccounts'                => bool   — Autoriser les comptes SMTP externes
     *   'enableXmpp'                        => bool   — Activer le chat XMPP (Enterprise)
     *   'enableDisposableAddresses'         => bool   — Adresses jetables
     *   'enableFileStorage'                 => bool   — Stockage de fichiers
     *   'sharedGlobalAddressList'           => bool   — Liste d'adresses globale partagée
     *   'enableActiveSyncAccountManagement' => bool   — Interface gestion EAS dans le DA
     *   'enableMapiEwsAccountManagement'    => bool   — Interface gestion MAPI dans le DA
     *   'maxActiveSyncAccounts'             => int    — Max comptes EAS (0 = illimité)
     *   'maxMapiEwsAccounts'                => int    — Max comptes MAPI (0 = illimité)
     *   'maxDomainAliases'                  => int    — Max alias de domaine
     *   'userLimit'                         => int    — Max utilisateurs
     *   'maxSize'                           => int    — Taille max en bytes
     *   'hostname'                          => string — Hostname MX
     *   'outgoingIP'                        => string — IP d'envoi
     *
     * Endpoint API : POST api/v1/settings/sysadmin/domain-settings/{domain}
     * Token requis : SysAdmin
     *
     * @param string $domain   Nom du domaine à modifier
     * @param array  $settings Tableau de paramètres à appliquer (merge partiel)
     * @param string $saToken  Token SysAdmin
     * @return array           Tableau standardisé
     */
    public function setDomainSettings(string $domain, array $settings, string $saToken): array
    {
        return $this->post(
            'api/v1/settings/sysadmin/domain-settings/' . urlencode($domain),
            ['domainSettings' => $settings],
            $saToken
        );
    }

    /**
     * Active ou désactive un domaine (suspension / réactivation WHMCS).
     *
     * Quand un domaine est désactivé (isEnabled = false) :
     *   - Les utilisateurs ne peuvent plus se connecter au webmail
     *   - Les courriels entrants sont refusés (ou mis en attente selon config serveur)
     *   - Les courriels sortants sont bloqués
     *   - Les données sont CONSERVÉES intactes sur le serveur
     *
     * Quand il est réactivé (isEnabled = true) :
     *   - Tout reprend immédiatement comme avant la suspension
     *   - Aucune action manuelle requise côté SmarterMail
     *
     * C'est un wrapper simplifié de setDomainSettings() pour ce cas d'usage fréquent.
     *
     * Endpoint API : POST api/v1/settings/sysadmin/domain-settings/{domain}
     * Corps : { "domainSettings": { "isEnabled": true|false } }
     * Token requis : SysAdmin
     *
     * @param string $domain  Nom du domaine
     * @param bool   $enabled true = activer, false = désactiver (suspendre)
     * @param string $saToken Token SysAdmin
     * @return array          Tableau standardisé
     */
    public function setDomainEnabled(string $domain, bool $enabled, string $saToken): array
    {
        return $this->setDomainSettings($domain, ['isEnabled' => $enabled], $saToken);
    }

    /**
     * Supprime définitivement un domaine de SmarterMail.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/DeleteDomain/post
     * 
     * ⚠️  OPÉRATION IRRÉVERSIBLE — À utiliser seulement lors de la résiliation
     *      d'un service dans WHMCS. Il n'existe pas de corbeille ou de restauration.
     *
     * Si $deleteData = true :
     *   - Le domaine est supprimé de SmarterMail
     *   - Tous les dossiers de courriels sont effacés du disque
     *   - Tous les utilisateurs, alias et listes de diffusion sont supprimés
     *
     * Si $deleteData = false :
     *   - Le domaine est supprimé de SmarterMail (ne reçoit plus de courriels)
     *   - Les fichiers sur le disque sont CONSERVÉS (pour archivage manuel éventuel)
     *   - Les données peuvent être réimportées manuellement si besoin
     *
     * Le choix est configurable via configoption8 dans les paramètres du module WHMCS.
     *
     * Endpoint API : POST api/v1/settings/sysadmin/domain-delete/{domain}/{deleteData}
     * Corps : (vide)
     * Token requis : SysAdmin
     *
     * @param string $domain     Nom du domaine à supprimer
     * @param bool   $deleteData true = supprimer aussi les fichiers sur disque
     * @param string $saToken    Token SysAdmin
     * @return array             Tableau standardisé
     */
    public function deleteDomain(string $domain, bool $deleteData, string $saToken): array
    {
        return $this->post(
            // L'endpoint attend true/false comme segment d'URL (pas comme paramètre GET)
            'api/v1/settings/sysadmin/domain-delete/' . urlencode($domain) . '/' . ($deleteData ? 'true' : 'false'),
            [],  // Corps vide — toutes les infos sont dans l'URL
            $saToken
        );
    }

    /**
     * Récupère les informations complètes d'un domaine depuis le SysAdmin.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SystemAdminSettingsController/GetDomainSettings/get
     * 
     * La réponse inclut notamment :
     *   - domainData.name         : Nom du domaine
     *   - domainData.hostname     : Hostname MX
     *   - domainData.diskUsage    : Utilisation disque actuelle EN BYTES ← clé pour facturation
     *   - domainData.userCount    : Nombre actuel d'utilisateurs
     *   - domainData.isEnabled    : Statut actif/suspendu
     *   - domainData.userLimit    : Limite max d'utilisateurs
     *   - domainData.maxSize      : Limite de taille en bytes (0 = illimité)
     *   - domainData.mainDomainAdmin : Username de l'admin du domaine
     *
     * Cette méthode est utilisée principalement par getDomainDiskUsageGB() pour
     * la mise à jour de l'utilisation disque lors du cron WHMCS.
     *
     * Endpoint API : GET api/v1/settings/sysadmin/domain/{domain}
     * Token requis : SysAdmin
     *
     * @param string $domain  Nom du domaine
     * @param string $saToken Token SysAdmin
     * @return array          Données du domaine ou tableau vide si erreur/introuvable
     */
    public function getDomainInfo(string $domain, string $saToken): array
    {
        $resp = $this->get('api/v1/settings/sysadmin/domain/' . urlencode($domain), $saToken);
        return $resp['success'] ? ($resp['data'] ?? []) : [];
    }

    /**
     * Retourne l'utilisation disque réelle d'un domaine en Gigaoctets (GB).
     *
     * Cette valeur est utilisée par le système de facturation :
     *   1. Appelé lors du cron WHMCS (fonction UsageUpdate du module)
     *   2. Stocké en MB dans tblhosting.diskusage
     *   3. Lu lors de la génération de facture par le hook InvoiceCreated
     *   4. Converti en tranches de facturation (ex: 21 GB → 3 tranches de 10 GB)
     *
     * L'API retourne la valeur en BYTES dans domainData.diskUsage.
     * Conversion : bytes → GB = bytes / (1024^3)
     *
     * NOTE : Cette valeur reflète l'espace utilisé RÉELLEMENT sur le disque
     * par tous les utilisateurs du domaine (courriels, pièces jointes, etc.).
     * Elle est mise à jour par SmarterMail en temps quasi-réel.
     *
     * @param string $domain  Nom du domaine
     * @param string $saToken Token SysAdmin
     * @return float          Utilisation en GB (arrondi à 4 décimales), 0.0 si erreur
     */
    public function getDomainDiskUsageGB(string $domain, string $saToken): float
    {
        $info  = $this->getDomainInfo($domain, $saToken);
        $bytes = $info['domainData']['diskUsage'] ?? 0;
        // 1024^3 = 1 073 741 824 bytes par gigaoctet (gibibyte)
        return round($bytes / (1024 ** 3), 4);
    }

    /**
     * Récupère les paramètres du domaine depuis le contexte Domain Admin.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/GetDomainSettings/get
     * 
     * Contrairement à getDomainInfo() qui utilise le token SysAdmin,
     * cette méthode utilise le token Domain Admin (impersonification).
     * Les informations retournées sont légèrement différentes et incluent
     * les capacités/fonctionnalités activées pour CE domaine spécifiquement.
     *
     * Champs importants retournés dans domainSettings :
     *   - mainDomainAdmin                    : Username de l'admin (pour auto-login)
     *   - enableActiveSyncAccountManagement  : EAS disponible pour ce domaine ?
     *   - enableMapiEwsAccountManagement     : MAPI disponible ?
     *   - allowUserSizeChanging              : Les admins peuvent changer la taille des boîtes ?
     *   - showDomainAliasMenu                : Menu alias de domaine visible ?
     *   - showListMenu                       : Menu listes de diffusion visible ?
     *   - maxSize                            : Taille max domaine en bytes
     *   - isEnabled                          : Domaine actif ?
     *
     * Endpoint API : GET api/v1/settings/domain/domain
     * Token requis : Domain Admin (impersonification)
     *
     * @param string $daToken Token Domain Admin (impersonification)
     * @return array          Paramètres du domaine ou tableau vide si erreur
     */
    public function getDomainSettings(string $daToken): array
    {
        $resp = $this->get('api/v1/settings/domain/domain', $daToken);
        return $resp['success'] ? ($resp['data']['domainSettings'] ?? []) : [];
    }

    /**
     * Récupère les données du domaine en une seule requête DA.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/GetDomain/get
     * 
     * Endpoint optimisé qui retourne en UN SEUL appel :
     *   - userCount  : Nombre de boîtes courriel actives
     *   - aliasCount : Nombre d'alias
     *   - sizeMb     : Utilisation disque en MB (virgule flottante)
     *   - size       : Utilisation disque en bytes
     *   - maxSize    : Limite disque en bytes (0 = illimité)
     *   - userLimit  : Limite de boîtes (0 = illimité)
     *   - aliasLimit : Limite d'alias (0 = illimité)
     *   - isEnabled  : Domaine actif ?
     *   - name       : Nom du domaine
     *   - hostname   : Hostname MX
     *
     * AVANTAGE vs approche précédente :
     *   Avant : getDomainDiskUsageGB (SA) + getUsers (DA) + getAliases (DA) = 3 appels
     *   Après : getDomainData (DA) = 1 appel → disk + users + aliases en une fois
     *
     * Utilisé par SmarterMailMetricsProvider::collectStats() pour que "Refresh Now"
     * dans WHMCS obtienne des données fraîches avec un minimum d'appels API.
     *
     * Endpoint API : GET api/v1/settings/domain/data
     * Token requis : Domain Admin (impersonification ou direct)
     *
     * @param string $daToken Token Domain Admin
     * @return array          domainData ou tableau vide si erreur
     */
    public function getDomainData(string $daToken): array
    {
        $resp = $this->get('api/v1/settings/domain/data', $daToken);
        return $resp['success'] ? ($resp['data']['domainData'] ?? []) : [];
    }

    /**
     * Récupère les paramètres DKIM du domaine.
     *
     * Essaie deux endpoints selon la version de SmarterMail.
     * Le DKIM permet aux clients de configurer leur DNS pour authentifier
     * les courriels sortants.
     *
     * Champs retournés (selon version) :
     *   - enabled      : bool — DKIM actif sur ce domaine
     *   - selector     : string — sélecteur DNS (ex: "mail", "default")
     *   - publicKey    : string — clé publique RSA (brute, sans en-tête)
     *   - txtRecord    : string — valeur DNS complète (v=DKIM1; k=rsa; p=...)
     *   - dnsHostname  : string — nom d'enregistrement DNS complet
     *                            (ex: "mail._domainkey.client.com")
     *
     * Endpoint API : GET api/v1/settings/domain/dkim-settings
     *           ou : GET api/v1/settings/domain/dkim
     * Token requis : Domain Admin
     *
     * @param string $daToken Token Domain Admin
     * @return array          Paramètres DKIM ou tableau vide si non supporté
     */
    /**
     * Impersonifie un utilisateur individuel pour obtenir un token user.
     *
     * Essaie d'abord via le SA (plus de chances), puis via le DA.
     * Si on obtient un token user, on peut appeler les endpoints "User only"
     * comme GET api/v1/settings/mailbox-forward-list.
     *
     * @param string      $daToken  Token Domain Admin
     * @param string      $username Username seul (sans @domaine)
     * @param string|null $domain   Domaine (ex: "client.com") — requis pour SA
     * @param string|null $saToken  Token SysAdmin (optionnel mais recommandé)
     * @return string|null          Token utilisateur ou null si non supporté
     */
    /**
     * Active la signature DKIM sur le domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/EnableDkim/post
     * 
     * Si aucun enregistrement DKIM n'existe, un enregistrement "pending"
     * est créé (en attente de validation DNS).
     *
     * Endpoint API : POST api/v1/settings/domain/dkim-enable/{forceActivation?}
     * Token requis : Domain Admin
     *
     * @param string $daToken          Token Domain Admin
     * @param bool   $forceActivation  Si true, force le DKIM actif même sans validation DNS
     * @return array                   Tableau standardisé
     */
    public function enableDkim(string $daToken, bool $forceActivation = false): array
    {
        // Endpoint documenté : POST api/v1/settings/domain/dkim-enable/{forceActivation}
        // forceActivation=false → enregistrement "pending" créé si clé absente
        // forceActivation=true  → DKIM activé même sans validation DNS préalable
        $endpoint = 'api/v1/settings/domain/dkim-enable/' . ($forceActivation ? 'true' : 'false');
        return $this->post($endpoint, [], $daToken);
    }

    /**
     * Désactive la signature DKIM sur le domaine.
     *
     * Endpoint API : POST api/v1/settings/domain/dkim-disable
     * Token requis : Domain Admin
     *
     * Après désactivation, les courriels sortants ne seront plus signés DKIM.
     * Les enregistrements DNS existants peuvent être conservés sans impact négatif
     * (ils seront simplement ignorés). L'enregistrement peut être réactivé à tout moment.
     *
     * SÉCURITÉ : Seul un token Domain Admin valide pour le domaine courant
     * peut effectuer cette opération. Le SA ne peut pas désactiver le DKIM
     * d'un domaine directement — il doit d'abord impersonifier le DA.
     *
     * @param string $daToken Token Domain Admin du domaine concerné
     * @return array          ['success' => bool, 'error' => string|null]
     */
    public function disableDkim(string $daToken): array
    {
        // Endpoint symétrique à dkim-enable, pas de paramètre additionnel requis
        return $this->post('api/v1/settings/domain/dkim-disable', [], $daToken);
    }

    // =========================================================================
    //  DKIM ROLLOVER — Rotation de clé DKIM (CreateDkimRollover / Verify / Delete)
    // =========================================================================
    //
    //  Contexte d'utilisation :
    //    Sur certaines installations SmarterMail, l'endpoint EnableDkim échoue
    //    parce que le DKIM n'a jamais été initialisé sur le domaine — aucune
    //    paire de clés n'existe encore. Dans ce cas, il faut CRÉER la paire
    //    initiale via le mécanisme de rollover plutôt que d'activer un DKIM
    //    inexistant.
    //
    //  Flux recommandé lorsque EnableDkim échoue :
    //    1. createDkimRollover()    → Génère la paire de clés (état : pending)
    //    2. Le client ajoute l'enregistrement DNS fourni
    //    3. verifyDkimRollover()    → Valide l'enregistrement DNS et active la clé
    //       (ou forceActivation=true pour forcer sans validation DNS)
    //
    //  DeleteDkimRollover est disponible pour annuler une rotation en cours
    //  sans activer la nouvelle clé (ex: erreur de génération).
    //
    //  SÉCURITÉ : Ces trois endpoints exigent un token Domain Admin valide.
    //    Le SA ne peut pas les appeler directement — il doit d'abord
    //    impersonifier le DA via loginDomainAdminFull().
    // =========================================================================

    /**
     * Génère une nouvelle paire de clés DKIM pour le domaine.
     *
     * À utiliser lorsque EnableDkim échoue parce qu'aucune clé DKIM n'existe
     * encore sur le domaine (DKIM jamais initialisé). Cette méthode crée la
     * paire de clés initiale en tant que clé « pending » (rollover).
     *
     * La clé reste en état « pending » jusqu'à ce que :
     *   - verifyDkimRollover() confirme la présence du bon enregistrement DNS, OU
     *   - verifyDkimRollover(forceActivation=true) force l'activation sans DNS.
     *
     * Endpoint API : POST api/v1/settings/domain/dkim-create-rollover/{keySize}
     * Token requis : Domain Admin uniquement (pas SysAdmin)
     *
     * Champs retournés dans $resp['data'] :
     *   - publicKey     : string — clé publique RSA brute (sans en-tête)
     *   - selector      : string — sélecteur DNS (ex: « selector1 »)
     *   - keySize       : int    — taille de la clé en bits (ex: 2048)
     *   - nextCheckUtc  : date   — prochain contrôle automatique de validation DNS
     *   - success       : bool
     *   - message       : string — message d'erreur si success = false
     *
     * @param string $daToken  Token Domain Admin du domaine concerné
     * @param int    $keySize  Taille de la clé RSA en bits (2048 recommandé)
     * @return array           Tableau standardisé ['success', 'data', 'error']
     */
    public function createDkimRollover(string $daToken, int $keySize = 2048): array
    {
        // Seules les tailles standard sont acceptées par SmarterMail.
        // On valide ici pour éviter un appel API voué à l'échec.
        // Les tailles supportées : 1024, 2048, 4096 (2048 recommandé IETF).
        $allowedSizes = [1024, 2048, 4096];
        if (!in_array($keySize, $allowedSizes, true)) {
            // Taille invalide → forcer 2048 pour éviter une erreur 400 côté API
            $keySize = 2048;
        }

        // L'endpoint attend la taille directement dans l'URL (paramètre de route).
        // Aucun corps JSON n'est envoyé — la taille est le seul paramètre.
        return $this->post(
            'api/v1/settings/domain/dkim-create-rollover/' . (int) $keySize,
            [],  // Corps vide — la taille est dans l'URL
            $daToken
        );
    }

    /**
     * Valide la clé DKIM en attente (rollover) contre les enregistrements DNS.
     *
     * Appelé APRÈS createDkimRollover() une fois que le client a ajouté
     * l'enregistrement TXT dans son DNS.
     *
     * Comportement selon $forceActivation :
     *   - false (défaut) : Active la clé rollover UNIQUEMENT si le DNS est valide.
     *                      Si DNS pas encore propagé → retourne success=false.
     *   - true           : Force l'activation même si le DNS n'est pas encore valide.
     *                      Utile pour les cas où la propagation DNS est lente.
     *
     * Endpoint API : POST api/v1/settings/domain/dkim-verify-rollover/{forceActivation?}
     * Token requis : Domain Admin uniquement
     *
     * @param string $daToken          Token Domain Admin
     * @param bool   $forceActivation  Si true, active la clé sans attendre la validation DNS
     * @return array                   ['success' => bool, 'error' => string|null]
     */
    public function verifyDkimRollover(string $daToken, bool $forceActivation = false): array
    {
        // Le paramètre forceActivation est optionnel dans l'API SmarterMail.
        // On l'inclut toujours pour être explicite et éviter le comportement par défaut
        // qui pourrait varier selon la version de SmarterMail installée.
        $endpoint = 'api/v1/settings/domain/dkim-verify-rollover/'
            . ($forceActivation ? 'true' : 'false');

        return $this->post($endpoint, [], $daToken);
    }

    /**
     * Supprime la clé DKIM rollover en attente sans l'activer.
     *
     * Permet d'annuler une rotation de clé initiée par createDkimRollover()
     * si elle n'a pas encore été vérifiée/activée.
     *
     * Cas d'usage typiques :
     *   - Erreur lors de la génération → recommencer avec createDkimRollover()
     *   - Annulation d'une rotation planifiée
     *   - Nettoyage d'une clé orpheline (ex: la précédente tentative a échoué)
     *
     * ATTENTION : Si aucune clé rollover n'est en attente, l'API retourne
     * success=false. Ce comportement est normal et ne doit pas être traité
     * comme une erreur critique.
     *
     * Endpoint API : POST api/v1/settings/domain/dkim-delete-rollover
     * Token requis : Domain Admin uniquement
     *
     * @param string $daToken Token Domain Admin
     * @return array          ['success' => bool, 'error' => string|null]
     */
    public function deleteDkimRollover(string $daToken): array
    {
        // Pas de paramètre — l'endpoint supprime la clé rollover en attente
        // pour le domaine associé au token DA fourni.
        return $this->post('api/v1/settings/domain/dkim-delete-rollover', [], $daToken);
    }

    public function loginUser(string $daToken, string $username, ?string $domain = null, ?string $saToken = null): ?string
    {
        $extractToken = fn($data) => $data['accessToken']
            ?? $data['impersonateAccessToken']
            ?? $data['userToken']
            ?? null;

        // Tentatives avec le SA token (niveau le plus élevé)
        if ($saToken && $domain) {
            $email = $username . '@' . $domain;
            $saEps = [
                ['POST', 'api/v1/settings/sysadmin/manage-user/' . urlencode($email)],
                ['POST', 'api/v1/settings/sysadmin/impersonate-user/' . urlencode($email)],
                ['POST', 'api/v1/settings/sysadmin/manage-user/' . urlencode($username) . '/' . urlencode($domain)],
            ];
            foreach ($saEps as [$method, $ep]) {
                $resp = $this->request($method, $ep, [], $saToken);
                $tk = $extractToken($resp['data'] ?? []);
                if ($tk) return $tk;
            }
        }

        // Tentatives avec le DA token
        $daEps = [
            ['POST', 'api/v1/settings/domain/impersonate-user/' . urlencode($username)],
            ['POST', 'api/v1/settings/domain/manage-user/' . urlencode($username)],
        ];
        foreach ($daEps as [$method, $ep]) {
            $resp = $this->request($method, $ep, [], $daToken);
            $tk = $extractToken($resp['data'] ?? []);
            if ($tk) return $tk;
        }

        return null;
    }

    /**
     * Met à jour la liste de forwarding d'un utilisateur.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/SetMailboxForwardList/post
     * 
     * Endpoint : POST api/v1/settings/domain/post-mailbox-forward-list
     * Token requis : Domain Admin
     *
     * @param string $daToken     Token Domain Admin
     * @param string $email       Adresse courriel complète (user@domain)
     * @param array  $forwardList Tableau des adresses de destination
     * @param array  $options     Options : keepRecipients, deleteOnForward, spamForwardOption
     * @return array              Tableau standardisé
     */
    public function setMailboxForwardList(string $daToken, string $email, array $forwardList, array $options = []): array
    {
        return $this->post(
            'api/v1/settings/domain/post-mailbox-forward-list',
            [
                'email' => $email,
                'mailboxForwardList' => [
                    'forwardList'       => array_values($forwardList),
                    'keepRecipients'    => (bool) ($options['keepRecipients']  ?? false),
                    'deleteOnForward'   => (bool) ($options['deleteOnForward'] ?? false),
                    'spamForwardOption' => (string) ($options['spamForwardOption'] ?? 'None'),
                ],
            ],
            $daToken
        );
    }

        /**
     * Récupère la liste des adresses de forwarding d'un utilisateur.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/GetMailboxForwardList/post
     * 
     * Endpoint "User only" : GET api/v1/settings/mailbox-forward-list
     * Nécessite un token UTILISATEUR (pas DA).
     *
     * Stratégie :
     *   1. Essayer avec le token DA (certaines versions SM l'acceptent)
     *   2. Impersonifier l'utilisateur depuis le DA et réessayer
     *   3. Essayer des endpoints DA alternatifs si disponibles
     *
     * Retourne un tableau avec :
     *   - 'forwardList'      : array de string (adresses de destination)
     *   - 'deleteOnForward'  : bool (supprimer après transfert)
     *   - 'keepRecipients'   : bool (conserver les destinataires originaux)
     *
     * @param string $daToken  Token Domain Admin
     * @param string $username Username seul (sans @domaine)
     * @return array           ['forwardList' => [...], ...] ou tableau vide
     */
    public function getMailboxForwardList(string $daToken, string $username, ?string $domain = null, ?string $saToken = null): array
    {
        // ── Endpoint principal : POST domain/get-mailbox-forward-list ─────
        // Domain Admin only — passe l'email dans le corps de la requête.
        // Retourne forwardList[], keepRecipients, deleteOnForward, spamForwardOption.
        $email = $domain ? ($username . '@' . $domain) : $username;

        $resp = $this->post(
            'api/v1/settings/domain/get-mailbox-forward-list',
            ['email' => $email],
            $daToken
        );

        if ($resp['success'] && isset($resp['data']['mailboxForwardList'])) {
            return $resp['data']['mailboxForwardList'];
        }

        // ── Fallback : impersonification utilisateur → GET mailbox-forward-list ─
        // Utilisé si l'endpoint DA n'est pas disponible sur cette version SM.
        $userToken = $this->loginUser($daToken, $username, $domain, $saToken);
        if ($userToken) {
            $resp = $this->get('api/v1/settings/mailbox-forward-list', $userToken);
            if ($resp['success'] && isset($resp['data']['mailboxForwardList'])) {
                return $resp['data']['mailboxForwardList'];
            }
        }

        return [];
    }

    /**
     * 
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/DkimSettings/post
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/SetDkimSettings/post
     * 
     * 
     * @param string $daToken
     * @return array
     */
    public function getDkimSettings(string $daToken): array
    {
        $endpoints = [
            'api/v1/settings/domain/dkim-settings' => 'dkimSettings',
            'api/v1/settings/domain/dkim'          => 'dkim',
        ];

        foreach ($endpoints as $endpoint => $key) {
            $resp = $this->get($endpoint, $daToken);
            if ($resp['success'] && !empty($resp['data'])) {
                return $resp['data'][$key] ?? $resp['data'];
            }
        }

        return [];
    }


    // =========================================================================
    //  GESTION DES UTILISATEURS (BOÎTES COURRIEL)
    // =========================================================================
    //
    //  Ces méthodes gèrent les comptes utilisateurs (boîtes courriel) d'un domaine.
    //  Elles requièrent toutes un token DOMAIN ADMIN (obtenu par impersonification).
    //
    //  Dans SmarterMail, un "utilisateur" = une boîte courriel avec connexion.
    //  Chaque utilisateur a :
    //    - Un username (partie avant le @)
    //    - Un mot de passe pour se connecter au webmail
    //    - Des paramètres de boîte (taille max, services activés, etc.)
    //    - Des options de sécurité (admin de domaine, authentification AD, etc.)
    //
    //  Ne pas confondre avec les "alias" (accountType = 4) qui sont des
    //  redirections sans boîte de stockage propre.
    // =========================================================================

    /**
     * Récupère la liste de tous les utilisateurs (boîtes courriel) du domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AccountListSearch/post
     * 
     * L'API SmarterMail retourne une liste mixte de tous les comptes du domaine
     * (utilisateurs + alias + listes de diffusion). Cette méthode filtre
     * automatiquement pour ne retourner que les vrais utilisateurs.
     *
     * FILTRAGE DES TYPES :
     *   accountType = 4 → Alias              → EXCLU (voir getAliases())
     *   accountType = 5 → Liste de diffusion → EXCLU
     *   Autres (0,1,2)  → Utilisateurs       → INCLUS
     *
     * Champs retournés par utilisateur :
     *   - userName         : Username (sans @domaine)
     *   - displayName      : Nom affiché
     *   - fullName         : Prénom Nom complet
     *   - emailAddress     : Adresse complète (username@domaine)
     *   - lastLogin        : Date/heure de dernière connexion (ISO 8601)
     *   - accountType      : Type de compte (0, 1, ou 2)
     *   - isEnabled        : Compte actif ?
     *   - totalMailboxSize : Taille actuelle de la boîte en bytes
     *
     * Note: "take: 99999" est une limite pratique. Si un domaine dépasse
     * ce nombre d'utilisateurs (très improbable), implémenter la pagination.
     *
     * Endpoint API : POST api/v1/settings/domain/account-list-search
     * Token requis : Domain Admin
     *
     * @param string $daToken Token Domain Admin
     * @return array          Liste des utilisateurs (tableaux associatifs)
     *                        Tableau vide si erreur ou aucun utilisateur
     */
    public function getUsers(string $daToken): array
    {
        $resp = $this->post('api/v1/settings/domain/account-list-search', [
            'search'      => null,         // null = pas de filtre de recherche (tous)
            'searchFlags' => ['users'],    // Demander seulement les utilisateurs
            'skip'        => 0,            // Pagination : commencer au début
            'take'        => 99999,        // Prendre jusqu'à 99 999 résultats
            'sortField'   => 'userName',   // Trier alphabétiquement
        ], $daToken);

        if (!$resp['success']) {
            return [];
        }

        // Filtrage défensif : même si on demande searchFlags=['users'],
        // l'API peut parfois retourner des entrées mixtes selon la version.
        // On exclut explicitement les alias (4) et listes de diffusion (5).
        return array_values(array_filter(
            $resp['data']['results'] ?? [],
            fn($u) => ($u['accountType'] ?? 0) !== 4   // Exclure les alias
                   && ($u['accountType'] ?? 0) !== 5    // Exclure les mailing lists
        ));
    }

    /**
     * Récupère la liste allégée des utilisateurs avec espace disque inclus.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AccountListSearchQuick/post
     * 
     * Utilise l'endpoint account-search-quick (même que l'UI SmarterMail) qui
     * retourne bytesUsed pour chaque compte en UN SEUL appel API.
     *
     * Champs clés retournés :
     *   - userName      : partie avant le @
     *   - displayName   : nom affiché
     *   - bytesUsed     : espace utilisé en bytes
     *   - bytesAllowed  : quota en bytes (null = illimité)
     *   - status        : "Enabled" | "Disabled" | "CriticallyErrored"
     *   - lastLoginTime : date ISO 8601
     *
     * Retourné indexé par userName (minuscules) pour merge O(1) avec getUsers().
     *
     * Endpoint : POST api/v1/settings/domain/account-search-quick
     * Token    : Domain Admin
     */
    public function getUsersQuick(string $daToken): array
    {
        $resp = $this->post('api/v1/settings/domain/account-search-quick', [
            'search'         => null,
            'searchFlags'    => ['users'],
            'skip'           => 0,
            'take'           => 99999,
            'sortField'      => 'userName',
            'sortDescending' => false,
        ], $daToken);

        if (!$resp['success']) {
            return [];
        }

        $indexed = [];
        foreach ($resp['data']['users'] ?? [] as $u) {
            $name = strtolower(trim((string) ($u['userName'] ?? '')));
            if ($name !== '') {
                $indexed[$name] = $u;
            }
        }
        return $indexed;
    }


    /**
     * Récupère les données complètes d'un utilisateur spécifique.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/GetUser/post
     * 
     * Retourne les informations de profil et paramètres de sécurité de l'utilisateur.
     * Ne pas confondre avec getUserMailSettings() qui retourne les paramètres
     * de la boîte courriel (taille, activation, etc.).
     *
     * Champs importants retournés dans userData :
     *   - userName              : Username
     *   - emailAddress          : Adresse complète
     *   - fullName              : Nom complet
     *   - securityFlags
     *     - isDomainAdmin       : Est admin du domaine ?
     *     - authType            : 0=normal, 1=Active Directory
     *   - autoResponder
     *     - forwardingAddress   : Adresse de redirection active
     *
     * Endpoint API : POST api/v1/settings/domain/get-user
     * Corps : { "email": "user@domaine.com" }
     * Token requis : Domain Admin
     *
     * @param string $email   Adresse courriel complète (ex: "jean@client.com")
     * @param string $daToken Token Domain Admin
     * @return array          Données utilisateur ou tableau vide si introuvable
     */
    public function getUser(string $email, string $daToken): array
    {
        $resp = $this->post('api/v1/settings/domain/get-user', ['email' => $email], $daToken);
        return $resp['success'] ? ($resp['data']['userData'] ?? []) : [];
    }

    /**
     * Récupère les paramètres de la boîte courriel d'un utilisateur.
     *
     * Ces paramètres sont distincts des données de profil (getUser()).
     * Ils concernent spécifiquement le comportement de la boîte courriel.
     *
     * Champs importants retournés dans userMailSettings :
     *   - isEnabled        : Boîte activée ? (peut être désactivée individuellement)
     *   - canReceiveMail   : Peut recevoir des courriels ? (false = bounce les entrants)
     *   - maxSize          : Taille maximale de la boîte en BYTES (0 = illimité)
     *   - totalMailboxSize : Utilisation actuelle en bytes
     *   - hideFromGAL      : Masqué de la liste d'adresses globale ?
     *   - isCatchAll       : Compte fourre-tout du domaine ?
     *   - enablePop        : POP3 activé ?
     *   - enableImap       : IMAP activé ?
     *   - enableSmtp       : SMTP activé ?
     *
     * Endpoint API : POST api/v1/settings/domain/get-user-mail
     * Corps : { "email": "user@domaine.com" }
     * Token requis : Domain Admin
     *
     * @param string $email   Adresse courriel complète
     * @param string $daToken Token Domain Admin
     * @return array          Paramètres de boîte ou tableau vide si erreur
     */
    public function getUserMailSettings(string $email, string $daToken): array
    {
        $resp = $this->post('api/v1/settings/domain/get-user-mail', ['email' => $email], $daToken);
        return $resp['success'] ? ($resp['data']['userMailSettings'] ?? []) : [];
    }

    /**
     * Crée un nouvel utilisateur (boîte courriel) dans le domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AddUser/post
     * 
     * STRUCTURE DE $userData (champs importants) :
     * [
     *   'userName'     => 'jean',           // Requis. Partie avant le @. Minuscules recommandées.
     *   'password'     => 'MotDePasse123!', // Requis si authType = 0 (pas AD)
     *   'fullName'     => 'Jean Tremblay',  // Optionnel. Nom affiché dans le webmail.
     *   'securityFlags' => [
     *     'isDomainAdmin'  => false,  // true = cet utilisateur peut gérer le domaine
     *     'authType'       => 0,      // 0 = mot de passe local, 1 = Active Directory
     *   ],
     *   'isPasswordExpired' => false,       // true = forcer changement au prochain login
     *   'autoResponder' => [
     *     'forwardingAddress' => 'autre@exemple.com'  // Redirection automatique
     *   ]
     * ]
     *
     * STRUCTURE DE $mailSettings (optionnel) :
     * [
     *   'maxSize' => 1073741824,  // Taille max en bytes (1 GB = 1024^3). 0 = illimité.
     * ]
     *
     * Si $mailSettings est null, la taille par défaut du domaine s'applique.
     *
     * Endpoint API : POST api/v1/settings/domain/user-put
     * Token requis : Domain Admin
     *
     * Erreurs possibles dans $resp['error'] :
     *   - "USER_ADD_ERROR_NAME_IN_USE"       → username déjà pris dans ce domaine
     *   - "USER_ADD_ERROR_INVALID_NAME"      → caractères invalides dans le username
     *   - "USER_ADD_ERROR_PASSWORD_TOO_SHORT" → mot de passe trop court
     *
     * @param array      $userData     Données de l'utilisateur (voir ci-dessus)
     * @param array|null $mailSettings Paramètres de boîte, ou null pour défauts du domaine
     * @param string     $daToken      Token Domain Admin
     * @return array                   Tableau standardisé
     */
    public function createUser(array $userData, ?array $mailSettings, string $daToken): array
    {
        // Whitelist des champs acceptés par l'API domain/user-put
        $allowedUserFields = [
            'userName', 'password', 'fullName', 'maxMailboxSize',
            'securityFlags', 'autoResponder',
        ];
        $safeUserData = array_intersect_key($userData, array_flip($allowedUserFields));

        // securityFlags : forcer isDomainAdmin à false — un client ne peut jamais créer un DA
        if (isset($safeUserData['securityFlags'])) {
            $safeUserData['securityFlags']['isDomainAdmin'] = false;
        }

        // maxMailboxSize : plafonner à 1 To en bytes
        if (isset($safeUserData['maxMailboxSize'])) {
            $safeUserData['maxMailboxSize'] = max(0, min(1099511627776, (int) $safeUserData['maxMailboxSize']));
        }

        $payload = ['userData' => $safeUserData];
        if ($mailSettings !== null) {
            $payload['userMailSettings'] = $mailSettings;
        }

        return $this->post('api/v1/settings/domain/user-put', $payload, $daToken);
    }

    /**
     * Met à jour un utilisateur existant.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/UpdateUser/post
     * 
     * Fonctionne comme createUser() mais pour une modification.
     * Le champ $email identifie l'utilisateur à modifier.
     *
     * CHANGEMENT DE MOT DE PASSE :
     * Inclure 'password' dans $userData pour changer le mot de passe.
     * Si 'password' est absent ou null, le mot de passe actuel est conservé.
     *
     * Endpoint API : POST api/v1/settings/domain/post-user
     * Token requis : Domain Admin
     *
     * @param string     $email        Adresse courriel complète de l'utilisateur à modifier
     * @param array      $userData     Nouvelles valeurs (merge partiel côté serveur)
     * @param array|null $mailSettings Nouveaux paramètres de boîte, ou null = pas de changement
     * @param string     $daToken      Token Domain Admin
     * @return array                   Tableau standardisé
     */
    public function updateUser(string $email, array $userData, ?array $mailSettings, string $daToken): array
    {
        // Whitelist des champs modifiables par domain/post-user
        $allowedUserFields = [
            'password', 'fullName', 'maxMailboxSize',
            'securityFlags', 'autoResponder',
        ];
        $safeUserData = array_intersect_key($userData, array_flip($allowedUserFields));

        // securityFlags : interdire toute élévation de privilège
        if (isset($safeUserData['securityFlags'])) {
            $safeUserData['securityFlags']['isDomainAdmin'] = false;
            $safeUserData['securityFlags']['isSysAdmin']    = false;
        }

        // maxMailboxSize depuis mailSettings legacy
        if ($mailSettings !== null) {
            if (isset($mailSettings['maxSize'])) {
                $safeUserData['maxMailboxSize'] = (int) $mailSettings['maxSize'];
            }
            if (isset($mailSettings['maxMailboxSize'])) {
                $safeUserData['maxMailboxSize'] = (int) $mailSettings['maxMailboxSize'];
            }
        }

        // Plafonner la taille à 1 To
        if (isset($safeUserData['maxMailboxSize'])) {
            $safeUserData['maxMailboxSize'] = max(0, min(1099511627776, (int) $safeUserData['maxMailboxSize']));
        }

        return $this->post('api/v1/settings/domain/post-user', [
            'email'    => $email,
            'userData' => $safeUserData,
        ], $daToken);
    }

    /**
     * Supprime définitivement un utilisateur du domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AccountListDelete/post
     * 
     * ⚠️  IRRÉVERSIBLE — Tous les courriels de l'utilisateur sont supprimés.
     *
     * IMPORTANT : Fournir le USERNAME seul (ex: "jean"),
     * PAS l'adresse courriel complète (pas "jean@client.com").
     *
     * L'API accepte un tableau pour permettre la suppression en lot.
     * Cette implémentation n'en traite qu'un à la fois pour simplifier l'usage.
     *
     * Endpoint API : POST api/v1/settings/domain/account-list-delete
     * Corps : { "input": ["username"] }
     * Token requis : Domain Admin
     *
     * @param string $username Username seul (sans @domaine)
     * @param string $daToken  Token Domain Admin
     * @return array           Tableau standardisé
     */
    public function deleteUser(string $username, string $daToken): array
    {

        return $this->post('api/v1/settings/domain/account-list-delete', [
            'input' => [$username],  // Tableau même pour un seul utilisateur
        ], $daToken);
    }


    // =========================================================================
    //  GESTION DES ALIAS
    // =========================================================================
    //
    //  Un alias dans SmarterMail est une adresse courriel "virtuelle" qui
    //  redirige les courriels reçus vers une ou plusieurs autres adresses.
    //
    //  DIFFÉRENCES ALIAS vs UTILISATEUR :
    //    - Un alias n'a PAS de boîte de stockage propre
    //    - Un alias peut pointer vers des adresses EXTERNES (hors du domaine)
    //    - Un alias peut avoir PLUSIEURS destinations simultanées
    //    - Un alias peut optionnellement être autorisé à ENVOYER des courriels
    //    - accountType = 4 dans les listes de comptes
    //
    //  CAS D'USAGE TYPIQUES :
    //    - info@client.com → jean@client.com + marie@client.com
    //    - contact@client.com → jean@client.com + responsable@externe.com
    // =========================================================================

    /**
     * Récupère la liste de tous les alias du domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AccountListSearch/post
     * 
     * Utilise le même endpoint que getUsers() (account-list-search) mais
     * avec searchFlags=['aliases'] pour filtrer uniquement les alias.
     * Un filtre défensif supplémentaire vérifie que accountType === 4.
     *
     * Champs retournés par alias :
     *   - userName              : Nom de l'alias (partie avant le @)
     *   - displayName           : Nom affiché
     *   - aliasTargetList       : Tableau des adresses de destination
     *   - allowSending          : Peut envoyer depuis cet alias ?
     *   - hideFromGAL           : Masqué de la liste globale ?
     *   - internalOnly          : Accepte seulement les courriels internes ?
     *   - includeAllDomainUsers : Redirige vers TOUS les utilisateurs du domaine ?
     *
     * Endpoint API : POST api/v1/settings/domain/account-list-search
     * Token requis : Domain Admin
     *
     * @param string $daToken Token Domain Admin
     * @return array          Liste des alias, tableau vide si aucun ou erreur
     */
    public function getAliases(string $daToken): array
    {
        $resp = $this->post('api/v1/settings/domain/account-list-search', [
            'search'      => null,
            'searchFlags' => ['aliases'],  // Filtrer seulement les alias
            'skip'        => 0,
            'take'        => 99999,
            'sortField'   => 'userName',
        ], $daToken);

        if (!$resp['success']) {
            return [];
        }

        // Filtre défensif : s'assurer qu'on n'a que des alias (accountType = 4)
        return array_values(array_filter(
            $resp['data']['results'] ?? [],
            fn($a) => ($a['accountType'] ?? 0) === 4
        ));
    }

    /**
     * Récupère les données complètes d'un alias spécifique.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/GetAliasByName/get
     * 
     * Contrairement à getAliases() qui retourne des données sommaires,
     * cette méthode retourne TOUTES les informations de l'alias,
     * notamment la liste complète des destinations (aliasTargetList).
     *
     * Structure de retour (champ 'alias') :
     * [
     *   'name'            => 'info',
     *   'displayName'     => 'Informations générales',
     *   'allowSending'    => false,
     *   'hideFromGAL'     => false,
     *   'internalOnly'    => false,
     *   'aliasTargetList' => ['jean@client.com', 'marie@client.com'],
     * ]
     *
     * Endpoint API : GET api/v1/settings/domain/alias/{aliasName}
     * Token requis : Domain Admin
     *
     * @param string $aliasName Nom de l'alias (sans @domaine, ex: "info")
     * @param string $daToken   Token Domain Admin
     * @return array            Données de l'alias ou tableau vide si introuvable
     */
    public function getAlias(string $aliasName, string $daToken): array
    {
        $resp = $this->get('api/v1/settings/domain/alias/' . urlencode($aliasName), $daToken);
        return $resp['success'] ? ($resp['data']['alias'] ?? []) : [];
    }

    /**
     * Crée un nouvel alias dans le domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/AddAlias/post
     * 
     * STRUCTURE DE $aliasData :
     * [
     *   'name'            => 'info',              // Requis. Partie avant le @.
     *   'displayName'     => 'Informations',       // Optionnel. Nom lisible.
     *   'allowSending'    => false,               // Peut envoyer depuis cette adresse ?
     *   'hideFromGAL'     => false,               // Masquer de la liste globale ?
     *   'internalOnly'    => false,               // Accepter seulement les courriels internes ?
     *   'aliasTargetList' => [                    // Requis. Destinations de la redirection.
     *     'jean@client.com',
     *     'marie@client.com',
     *     'responsable@externe.com'               // Adresses externes autorisées
     *   ],
     *   'includeAllDomainUsers' => false,         // true = envoyer à tous les utilisateurs
     *   'enableForXmpp'         => false,         // Activer pour le chat XMPP
     * ]
     *
     * Endpoint API : POST api/v1/settings/domain/alias-put/
     * Corps : { "alias": { ... } }
     * Token requis : Domain Admin
     *
     * Erreurs possibles :
     *   - "USER_ADD_ERROR_NAME_IN_USE" → un alias (ou utilisateur) avec ce nom existe déjà
     *
     * @param array  $aliasData Données de l'alias à créer
     * @param string $daToken   Token Domain Admin
     * @return array            Tableau standardisé
     */
    public function createAlias(array $aliasData, string $daToken): array
    {
        // Whitelist des champs alias valides
        $allowed = ['name', 'displayName', 'allowSending', 'hideFromGAL',
                    'internalOnly', 'aliasTargetList', 'includeAllDomainUsers', 'enableForXmpp'];
        $safe = array_intersect_key($aliasData, array_flip($allowed));

        // Forcer les booleans
        foreach (['allowSending', 'hideFromGAL', 'internalOnly', 'includeAllDomainUsers', 'enableForXmpp'] as $boolField) {
            if (isset($safe[$boolField])) $safe[$boolField] = (bool) $safe[$boolField];
        }

        return $this->post('api/v1/settings/domain/alias-put/', ['alias' => $safe], $daToken);
    }

    /**
     * Met à jour un alias existant.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/UpdateAlias/post
     * 
     * Le paramètre $oldName est nécessaire car l'alias peut être renommé lors
     * de la mise à jour (le nouveau nom est dans $aliasData['name']).
     * Si le nom ne change pas, $oldName === $aliasData['name'].
     *
     * Endpoint API : POST api/v1/settings/domain/alias/
     * Corps : { "oldName": "anciennom", "alias": { ... } }
     * Token requis : Domain Admin
     *
     * @param string $oldName   Nom actuel de l'alias (avant modification)
     * @param array  $aliasData Nouvelles données de l'alias
     * @param string $daToken   Token Domain Admin
     * @return array            Tableau standardisé
     */
    public function updateAlias(string $oldName, array $aliasData, string $daToken): array
    {
        $allowed = ['name', 'displayName', 'allowSending', 'hideFromGAL',
                    'internalOnly', 'aliasTargetList', 'includeAllDomainUsers', 'enableForXmpp'];
        $safe = array_intersect_key($aliasData, array_flip($allowed));

        foreach (['allowSending', 'hideFromGAL', 'internalOnly', 'includeAllDomainUsers', 'enableForXmpp'] as $boolField) {
            if (isset($safe[$boolField])) $safe[$boolField] = (bool) $safe[$boolField];
        }

        return $this->post('api/v1/settings/domain/alias/', [
            'oldName' => $oldName,
            'alias'   => $safe,
        ], $daToken);
    }

    /**
     * Supprime un alias du domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/DeleteAlias/post
     * 
     * Utilise le même endpoint que deleteUser() (account-list-delete).
     * L'API accepte n'importe quel type de compte dans ce tableau.
     *
     * IMPORTANT : Fournir le NOM DE L'ALIAS seul (ex: "info"),
     * PAS l'adresse courriel complète (pas "info@client.com").
     *
     * Endpoint API : POST api/v1/settings/domain/account-list-delete
     * Corps : { "input": ["aliasname"] }
     * Token requis : Domain Admin
     *
     * @param string $aliasName Nom de l'alias à supprimer (sans @domaine)
     * @param string $daToken   Token Domain Admin
     * @return array            Tableau standardisé
     */
    public function deleteAlias(string $aliasName, string $daToken): array
    {

        // Endpoint dédié à la suppression d'alias (Domain Admin uniquement)
        // POST api/v1/settings/domain/alias-delete/{name}
        return $this->post(
            'api/v1/settings/domain/alias-delete/' . urlencode($aliasName),
            [],
            $daToken
        );
    }


    // =========================================================================
    //  ACTIVESYNC (EAS) ET MAPI/EXCHANGE
    // =========================================================================
    //
    //  Ces protocoles permettent une synchronisation avancée des courriels,
    //  calendriers et contacts avec des clients de messagerie tiers.
    //
    //  ACTIVESYNC (EAS — Exchange ActiveSync) :
    //    - Protocole de synchronisation Microsoft pour appareils mobiles
    //    - Utilisé par : iOS (Mail natif), Android, Windows Phone, Outlook Mobile
    //    - Synchronise : courriels, calendriers, contacts, tâches en temps réel
    //    - Nécessite une licence SmarterMail Enterprise ou une licence EAS séparée
    //    - Dans notre module : coût supplémentaire par boîte (configoption2)
    //
    //  MAPI/EWS (Messaging API / Exchange Web Services) :
    //    - Protocole Microsoft pour Outlook desktop (mode Exchange)
    //    - Utilisé par : Microsoft Outlook 2013+ (mode Exchange complet)
    //    - Synchronise : courriels, calendriers, contacts, tâches, notes
    //    - Offre plus de fonctionnalités qu'IMAP (dossiers publics, délégation, etc.)
    //    - Nécessite une licence SmarterMail Enterprise
    //    - Dans notre module : coût supplémentaire par boîte (configoption3)
    //
    //  TARIF COMBINÉ :
    //    Si une boîte a LES DEUX (EAS + MAPI), un tarif réduit s'applique (configoption4)
    //    au lieu de facturer les deux séparément.
    //
    //  PRÉREQUIS POUR CES MÉTHODES :
    //    Le domaine doit avoir les flags suivants activés (via setDomainSettings) :
    //      - enableActiveSyncAccountManagement = true (pour EAS)
    //      - enableMapiEwsAccountManagement    = true (pour MAPI)
    //    Sinon, l'API retournera une erreur 403 ou ignorera la requête.
    //
    //  RETOUR INDEXÉ :
    //    Les méthodes getActiveSyncMailboxes() et getMapiMailboxes() retournent
    //    un tableau INDEXÉ par adresse courriel (strtolower) pour des lookups O(1).
    //    Ex: isset($eas['jean@client.com']) → true si EAS activé pour Jean
    // =========================================================================

    /**
     * Récupère la liste de toutes les boîtes avec ActiveSync (EAS) activé.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/ActiveSyncGetMailboxesEx/get
     * 
     * L'API retourne uniquement les comptes AYANT EAS actif (pas tous les utilisateurs).
     * Cette méthode convertit ce tableau en un tableau INDEXÉ par adresse courriel
     * (en minuscules) pour un accès rapide lors de la génération de factures.
     *
     * Retour indexé :
     * [
     *   'jean@client.com'  => ['emailAddress' => ..., 'isActive' => true, ...],
     *   'marie@client.com' => ['emailAddress' => ..., 'isActive' => true, ...],
     * ]
     *
     * Endpoint API : GET api/v1/settings/domain/active-sync-mailboxes
     * Token requis : Domain Admin
     *
     * @param string $daToken Token Domain Admin
     * @return array          Tableau indexé par emailAddress (strtolower), vide si erreur
     */
    public function getActiveSyncMailboxes(string $daToken): array
    {
        $resp     = $this->get('api/v1/settings/domain/active-sync-mailboxes', $daToken);
        $accounts = $resp['success'] ? ($resp['data']['activeSyncAccounts'] ?? []) : [];

        // Indexer par adresse courriel en minuscules pour lookups O(1) rapides
        $indexed = [];
        foreach ($accounts as $acc) {
            $indexed[strtolower($acc['emailAddress'])] = $acc;
        }
        return $indexed;
    }

    /**
     * Récupère la liste de toutes les boîtes avec MAPI/EWS activé.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/MapiEwsGetMailboxesEx/get
     * 
     * Identique à getActiveSyncMailboxes() mais pour le protocole MAPI.
     * Retourne également un tableau indexé par emailAddress.
     *
     * Endpoint API : GET api/v1/settings/domain/mapi-ews-mailboxes
     * Token requis : Domain Admin
     *
     * @param string $daToken Token Domain Admin
     * @return array          Tableau indexé par emailAddress (strtolower), vide si erreur
     */
    public function getMapiMailboxes(string $daToken): array
    {
        $resp     = $this->get('api/v1/settings/domain/mapi-ews-mailboxes', $daToken);
        $accounts = $resp['success'] ? ($resp['data']['mapiEwsAccounts'] ?? []) : [];

        $indexed = [];
        foreach ($accounts as $acc) {
            $indexed[strtolower($acc['emailAddress'])] = $acc;
        }
        return $indexed;
    }

    /**
     * Active ou désactive ActiveSync (EAS) pour une boîte courriel spécifique.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/ActiveSyncEnableUser/post
     * 
     * L'API "patch" permet de modifier plusieurs boîtes en une seule requête.
     * Cette méthode n'en traite qu'une à la fois pour simplifier l'usage
     * depuis le formulaire de modification d'utilisateur.
     *
     * Endpoint API : POST api/v1/settings/domain/active-sync-mailboxes-patch
     * Corps : [ { "emailAddress": "...", "isActive": true|false }, ... ]
     * Token requis : Domain Admin
     *
     * @param string $email   Adresse courriel complète (ex: "jean@client.com")
     * @param bool   $enabled true = activer EAS, false = désactiver
     * @param string $daToken Token Domain Admin
     * @return array          Tableau standardisé
     */
    public function setActiveSyncEnabled(string $email, bool $enabled, string $daToken): array
    {
        return $this->post('api/v1/settings/domain/active-sync-mailboxes-patch', [
            // L'API attend un tableau d'objets, même pour une seule boîte
            ['emailAddress' => $email, 'isActive' => $enabled],
        ], $daToken);
    }

    /**
     * Active ou désactive MAPI/EWS pour une boîte courriel spécifique.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.DomainSettingsController/MapiEwsEnableUser/post
     * 
     * Identique à setActiveSyncEnabled() mais pour le protocole MAPI.
     *
     * Endpoint API : POST api/v1/settings/domain/mapi-ews-mailboxes-patch
     * Corps : [ { "emailAddress": "...", "isActive": true|false }, ... ]
     * Token requis : Domain Admin
     *
     * @param string $email   Adresse courriel complète
     * @param bool   $enabled true = activer MAPI, false = désactiver
     * @param string $daToken Token Domain Admin
     * @return array          Tableau standardisé
     */
    public function setMapiEnabled(string $email, bool $enabled, string $daToken): array
    {
        return $this->post('api/v1/settings/domain/mapi-ews-mailboxes-patch', [
            ['emailAddress' => $email, 'isActive' => $enabled],
        ], $daToken);
    }


    /**
     * Récupère les informations système du serveur SmarterMail.
     *
     * Utilisée principalement par TestConnection pour afficher la version
     * et valider que le token SA a bien accès à l'API d'administration.
     *
     * ROBUSTESSE MULTI-VERSION :
     *   Le nom de l'endpoint varie selon la version de SmarterMail.
     *   Cette méthode essaie plusieurs endpoints dans l'ordre jusqu'à en trouver
     *   un qui répond avec un HTTP 200. Retourne le premier succès trouvé.
     *
     *   Endpoints testés (ordre de priorité) :
     *     - api/v1/settings/sysadmin/system-info     (SM 16.x+)
     *     - api/v1/settings/sysadmin/sysadmin-info   (versions intermédiaires)
     *     - api/v1/settings/sysadmin/server-settings (fallback — toujours présent)
     *
     * Champs potentiellement retournés (varient selon la version) :
     *   - productVersion / version  : Ex: "100.0.8665"
     *   - productEdition / edition  : "Enterprise", "Professional", "Free"
     *   - totalDomains / domainCount: Nombre de domaines hébergés
     *   - hostName                  : Hostname du serveur
     *
     * Token requis : SysAdmin
     *
     * @param string $saToken Token SysAdmin
     * @return array          Données système (tableau vide si tous les endpoints échouent)
     */
    public function getSystemInfo(string $saToken): array
    {
        // Liste des endpoints à essayer, par ordre de préférence
        $endpoints = [
            'api/v1/settings/sysadmin/system-info',
            'api/v1/settings/sysadmin/sysadmin-info',
            'api/v1/settings/sysadmin/server-settings',
        ];

        foreach ($endpoints as $endpoint) {
            $resp = $this->get($endpoint, $saToken);
            if ($resp['success'] && !empty($resp['data'])) {
                // Aplatir : retirer une éventuelle clé de wrapping (ex: 'systemInfo', 'settings')
                // pour exposer directement les champs version, edition, etc.
                $data = $resp['data'];
                // Si la réponse a une seule clé qui est un tableau, on "déroule" un niveau
                if (count($data) === 1) {
                    $inner = reset($data);
                    if (is_array($inner)) {
                        $data = $inner;
                    }
                }
                return $data;
            }
        }

        // Aucun endpoint n'a répondu — le token SA est valide (sinon on aurait eu 401)
        // mais le serveur ne retourne pas d'infos système (version très ancienne ou restriction)
        return [];
    }


    // =========================================================================
    //  AUTO-LOGIN (SSO)
    // =========================================================================
    //
    //  SmarterMail supporte la connexion automatique (Single Sign-On) via un
    //  token temporaire à usage unique. Cela permet à WHMCS de rediriger l'admin
    //  vers le webmail SmarterMail sans qu'il ait à saisir ses credentials.
    //
    //  FLUX AUTO-LOGIN :
    //    1. Le SA demande un token de login pour un utilisateur/domaine spécifique
    //    2. SmarterMail génère un URL unique avec un token temporaire
    //    3. WHMCS redirige l'utilisateur vers cet URL
    //    4. SmarterMail valide le token et connecte l'utilisateur automatiquement
    //    5. Le token est à usage unique et expire très rapidement (quelques secondes)
    //
    //  UTILISATION DANS LE MODULE :
    //    - Bouton "Accéder à SmarterMail" dans le panneau admin WHMCS (LoginLink)
    //    - L'admin atterrit directement dans la vue de gestion du domaine
    //    - Le paramètre $redirect permet de pointer vers une page spécifique
    // =========================================================================

    /**
     * Génère une URL d'auto-login (SSO) pour l'administrateur d'un domaine.
     *
     * https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.AuthenticationController/RetrieveLoginToken/post
     * 
     * La connexion se fait au NOM de $adminUsername (l'admin du domaine),
     * mais elle est initiée par le SA (qui possède le token $saToken).
     * Cela permet à WHMCS de connecter l'admin dans SmarterMail sans
     * connaître son mot de passe.
     *
     * PARAMÈTRE $redirect :
     * Chemin relatif dans l'interface SmarterMail où l'utilisateur atterrit.
     * Exemples :
     *   "/settings/domain/domain-accounts"  → vue de gestion des comptes du domaine
     *   "/settings/user/users"              → liste des utilisateurs
     *   ""                                  → page d'accueil du webmail
     *
     * DURÉE DE VIE DU TOKEN :
     * Le token auto-login expire en quelques secondes (souvent 30-60s).
     * Ne pas stocker cet URL ni le réutiliser. Régénérer à chaque clic.
     *
     * Endpoint API : POST api/v1/auth/retrieve-login-token
     * Corps : { "username": "adminuser", "domain": "client.com" }
     * Réponse :
     * {
     *   "autoLoginToken": "abc123...",
     *   "autoLoginUrl":   "https://mail.serveur.com/Login?authToken=abc123..."
     * }
     * Token requis : SysAdmin
     *
     * @param string $adminUsername Username de l'admin du domaine (ex: le compte secret généré)
     * @param string $domain        Nom de domaine (ex: "client.com")
     * @param string $saToken       Token SysAdmin
     * @param string $redirect      Chemin de redirection dans SmarterMail (optionnel)
     * @return string               URL d'auto-login complète, ou chaîne vide si erreur
     */
    public function getAutoLoginUrl(
        string $adminUsername,
        string $domain,
        string $saToken,
        string $redirect = ''
    ): string {
        $resp = $this->post('api/v1/auth/retrieve-login-token', [
            'username' => $adminUsername,
            'domain'   => $domain,
        ], $saToken);

        if (!$resp['success']) {
            // Retourner chaîne vide = l'appelant affiche un message d'erreur approprié
            return '';
        }

        $url = $resp['data']['autoLoginUrl'] ?? '';

        // Ajouter la redirection si fournie et si on a bien un URL valide
        if ($url && $redirect) {
            $url .= '&redirect=' . urlencode($redirect);
        }

        return $url;
    }


    // =========================================================================
    //  ALIAS DE DOMAINE (Domain Aliases)
    // =========================================================================
    //
    //  Les alias de domaine permettent à un domaine de recevoir du courrier
    //  adressé à un autre nom de domaine. Par exemple, si « exemple.com » est
    //  le domaine principal et « exemple.ca » est un alias de domaine, alors
    //  un courriel envoyé à « info@exemple.ca » sera livré dans la boîte
    //  « info@exemple.com ».
    //
    //  ATTENTION : Les alias de domaine sont différents des alias de courriel.
    //  Un alias de courriel redirige UNE adresse vers une ou plusieurs cibles.
    //  Un alias de domaine redirige TOUTES les adresses du domaine alias vers
    //  les boîtes correspondantes du domaine principal.
    //
    //  Token requis : Domain Admin (daToken) pour la lecture,
    //                 Domain Admin pour l'ajout et la suppression.
    // =========================================================================

    /**
     * Récupère la liste des alias de domaine configurés.
     *
     * Un alias de domaine permet aux boîtes du domaine principal de recevoir
     * du courrier adressé au domaine alias. Par exemple, si « client.com »
     * possède un alias « client.ca », alors « info@client.ca » sera livré
     * dans la boîte « info@client.com ».
     *
     * Endpoint API : GET api/v1/settings/domain/domain-aliases
     * Token requis : Domain Admin (User-level selon la doc SmarterMail)
     *
     * Structure de retour (champ 'domainAliasData') :
     * [
     *   [
     *     'name'       => 'client.ca',       // Nom de l'alias
     *     'aliasIdn'   => 'client.ca',       // Nom IDN (internationalisé)
     *     'domainName' => 'client.com',      // Domaine principal
     *     'img'        => '',                // Icône (inutilisée)
     *     'url'        => '',                // URL (inutilisée)
     *   ],
     *   ...
     * ]
     *
     * SÉCURITÉ : Le token DA limite la requête au domaine authentifié.
     *            Aucun accès inter-domaine n'est possible.
     *
     * @param string $daToken Token Domain Admin
     * @return array          Liste des alias de domaine, ou tableau vide si erreur
     */
    public function getDomainAliases(string $daToken): array
    {
        // ── Appel GET vers l'endpoint domain-aliases ─────────────────────
        // L'API retourne la liste complète — pas de pagination nécessaire
        // car le nombre d'alias de domaine est généralement très faible.
        $resp = $this->get('api/v1/settings/domain/domain-aliases', $daToken);

        // En cas d'échec (token invalide, domaine inexistant, erreur réseau),
        // retourner un tableau vide — l'appelant gère l'absence de données.
        if (!$resp['success']) {
            return [];
        }

        // Retourner le tableau d'alias, ou un tableau vide si la clé est absente.
        // La clé 'domainAliasData' peut être null si aucun alias n'existe.
        return $resp['data']['domainAliasData'] ?? [];
    }

    /**
     * Ajoute un alias de domaine au domaine principal.
     *
     * Après l'ajout, tout courriel envoyé à une adresse @aliasName sera
     * automatiquement livré dans la boîte correspondante du domaine principal.
     *
     * IMPORTANT : L'alias de domaine doit pointer vers le même serveur
     * SmarterMail (enregistrement MX). Le paramètre $checkMx permet de
     * vérifier les enregistrements MX avant l'ajout — si les MX ne pointent
     * pas vers le serveur, l'API peut rejeter la demande (selon la version
     * de SmarterMail et sa configuration).
     *
     * Endpoint API : POST api/v1/settings/domain/domain-alias-put/{aliasName}/{checkMx?}
     * Token requis : Domain Admin
     *
     * SÉCURITÉ :
     *   1. Le token DA limite l'opération au domaine authentifié.
     *   2. Le nom de l'alias est encodé via urlencode() pour prévenir
     *      toute injection dans l'URL de l'endpoint.
     *   3. Le paramètre checkMx est un booléen strict (true/false).
     *
     * @param string $aliasName Nom de domaine alias (ex: "client.ca")
     * @param string $daToken   Token Domain Admin
     * @param bool   $checkMx   Vérifier les MX avant l'ajout (défaut: false)
     * @return array            Réponse API brute avec 'success', 'data', 'error'
     */
    public function addDomainAlias(string $aliasName, string $daToken, bool $checkMx = false): array
    {
        // ── Construction de l'URL avec les paramètres dans le chemin ──────
        // L'API SmarterMail attend les paramètres dans l'URL, pas dans le corps.
        //
        // IMPORTANT : Le paramètre checkMx est OPTIONNEL (noté {checkMx?}
        // dans la documentation). S'il n'est pas nécessaire, il faut l'OMETTRE
        // complètement de l'URL — passer « /false » provoque une erreur 400
        // sur certaines versions de SmarterMail car le routeur d'URL ne
        // reconnaît pas « false » comme un segment valide.
        //
        // URL générée :
        //   checkMx = false → api/v1/settings/domain/domain-alias-put/client.ca
        //   checkMx = true  → api/v1/settings/domain/domain-alias-put/client.ca/true
        $endpoint = 'api/v1/settings/domain/domain-alias-put/'
            . urlencode($aliasName);

        // N'ajouter le paramètre checkMx que s'il est explicitement demandé
        if ($checkMx) {
            $endpoint .= '/true';
        }

        // ── Appel POST sans corps (paramètres dans l'URL) ────────────────
        // Le corps vide {} est envoyé car la méthode post() encode toujours
        // un tableau en JSON. SmarterMail l'ignore pour cet endpoint.
        $resp = $this->post($endpoint, [], $daToken);

        return $resp;
    }

    /**
     * Supprime un alias de domaine du domaine principal.
     *
     * Après la suppression, le courrier envoyé à des adresses @aliasName
     * ne sera plus livré dans les boîtes du domaine principal.
     *
     * ATTENTION : Cette opération est irréversible. Les courriels en transit
     * vers l'ancien alias seront rejetés (bounce) après la suppression.
     *
     * Endpoint API : POST api/v1/settings/domain/domain-alias-delete/{name}
     * Token requis : Domain Admin
     *
     * SÉCURITÉ :
     *   1. Le token DA limite l'opération au domaine authentifié.
     *   2. Le nom de l'alias est encodé via urlencode() pour prévenir
     *      toute injection dans l'URL de l'endpoint.
     *   3. L'appelant (smartermail.php) doit valider le nom avant d'appeler
     *      cette méthode — cette couche ne fait PAS de validation métier.
     *
     * @param string $aliasName Nom de domaine alias à supprimer (ex: "client.ca")
     * @param string $daToken   Token Domain Admin
     * @return array            Réponse API brute avec 'success', 'data', 'error'
     */
    public function deleteDomainAlias(string $aliasName, string $daToken): array
    {
        // ── Construction de l'URL avec le nom dans le chemin ──────────────
        // urlencode() protège contre les caractères spéciaux et l'injection.
        $endpoint = 'api/v1/settings/domain/domain-alias-delete/'
            . urlencode($aliasName);

        // ── Appel POST sans corps (identifiant dans l'URL) ───────────────
        $resp = $this->post($endpoint, [], $daToken);

        return $resp;
    }
}
