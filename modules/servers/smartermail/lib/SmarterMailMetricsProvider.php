<?php
/**
 * ============================================================================
 *  SmarterMailMetricsProvider.php — Fournisseur de métriques WHMCS
 * ============================================================================
 *
 * Smartermail API doc : https://mail.smartertools.com/Documentation/api#/topics/overview
 * 
 * Implémente \WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface pour
 * exposer les statistiques SmarterMail au système de métriques natif WHMCS.
 *
 * COMPATIBILITÉ : WHMCS 7.9+
 *
 * MÉTRIQUES EXPOSÉES :
 *   disk_gb        — Espace disque utilisé (GigaBytes, TYPE_SNAPSHOT)
 *   email_accounts — Boîtes courriel actives (Comptes, TYPE_SNAPSHOT)
 *   aliases        — Alias de redirection (Comptes, TYPE_SNAPSHOT)
 *   eas_accounts   — Boîtes ActiveSync activées (Comptes, TYPE_SNAPSHOT)
 *   mapi_accounts  — Boîtes MAPI/Exchange activées (Comptes, TYPE_SNAPSHOT)
 *
 * TYPE_SNAPSHOT est utilisé pour toutes les métriques car SmarterMail
 * ne réinitialise pas ces valeurs périodiquement — elles représentent
 * l'état actuel du domaine (pas un cumul qui se remet à zéro).
 *
 * CHARGEMENT AUTOMATIQUE :
 * WHMCS enregistre automatiquement le namespace WHMCS\Module\Server\Smartermail
 * pour les classes dans le répertoire lib/ du module.
 * Ce fichier est donc chargé automatiquement par l'autoloader WHMCS.
 *
 * CONNEXION API :
 * L'API SmarterMail est initialisée de façon paresseuse (lazy) pour éviter
 * des connexions inutiles quand seule la méthode metrics() est appelée
 * (ce qui arrive dans les contextes de configuration produit, sans serveur).
 */

namespace WHMCS\Module\Server\Smartermail;

if (!defined('WHMCS')) { die('Accès direct interdit.'); }

use WHMCS\UsageBilling\Contracts\Metrics\MetricInterface;
use WHMCS\UsageBilling\Contracts\Metrics\ProviderInterface;
use WHMCS\UsageBilling\Metrics\Metric;
use WHMCS\UsageBilling\Metrics\Units\GigaBytes;
use WHMCS\UsageBilling\Metrics\Units\Accounts;
use WHMCS\UsageBilling\Metrics\Usage;
use WHMCS\Database\Capsule;

class SmarterMailMetricsProvider implements ProviderInterface
{
    // ─────────────────────────────────────────────────────────────────────────
    // Propriétés
    // ─────────────────────────────────────────────────────────────────────────

    /** @var array Paramètres du serveur fournis par WHMCS */
    private array $params;

    /** @var \SmarterMailApi|null Instance API (lazy init) */
    private ?\SmarterMailApi $api = null;

    /** @var string|null Token SysAdmin (lazy init) */
    private ?string $saToken = null;


    // ─────────────────────────────────────────────────────────────────────────
    // Constructeur
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $params Paramètres WHMCS du serveur :
     *   - serverid        : ID du serveur dans tblservers
     *   - serverhostname  : Hostname SmarterMail
     *   - serversecure    : HTTPS activé
     *   - serverport      : Port
     *   - serverusername  : Username SA
     *   - serverpassword  : Mot de passe SA (décrypté par WHMCS)
     */
    public function __construct(array $params)
    {
        $this->params = $params;

        // Charger SmarterMailApi si pas encore disponible.
        // Nécessaire car MetricProvider peut être invoqué avant smartermail.php
        // dans certains contextes WHMCS (configuration produit, etc.)
        if (!class_exists('SmarterMailApi')) {
            require_once __DIR__ . '/SmarterMailApi.php';
        }
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — Accès API (lazy)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne l'instance API, initialisée à la première utilisation.
     */
    private function api(): \SmarterMailApi
    {
        if ($this->api === null) {
            $this->api = \SmarterMailApi::fromParams($this->params);
        }
        return $this->api;
    }

    /**
     * Retourne le token SysAdmin, obtenu à la première utilisation.
     * Retourne null si la connexion échoue (serveur injoignable, credentials invalides).
     */
    private function saToken(): ?string
    {
        if ($this->saToken === null) {
            $this->saToken = $this->api()->loginSysAdminFromParams($this->params);
            if (!$this->saToken) {
                logActivity('SmarterMail MetricProvider [saToken] FAIL : SA login échoué.'
                    . ' host=' . ($this->params['serverhostname'] ?? '?')
                    . ' user=' . ($this->params['serverusername'] ?? '?'));
            }
        }
        return $this->saToken;
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Interface ProviderInterface
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne la liste des métriques disponibles pour ce module.
     *
     * IMPORTANT : Cette méthode est appelée dans des contextes SANS serveur
     * (ex: page de configuration d'un produit WHMCS). Elle ne doit JAMAIS
     * faire d'appel réseau ou accéder à la base de données.
     * Elle retourne uniquement les définitions (nom, type, unité).
     *
     * @return MetricInterface[]
     */
    public function metrics(): array
    {
        return [
            new Metric(
                'disk_gb',
                'Espace disque',
                MetricInterface::TYPE_SNAPSHOT,
                new GigaBytes()
            ),
            new Metric(
                'email_accounts',
                'Adresses courriel',
                MetricInterface::TYPE_SNAPSHOT,
                new Accounts('Boîtes')
            ),
            new Metric(
                'aliases',
                'Alias',
                MetricInterface::TYPE_SNAPSHOT,
                new Accounts('Alias')
            ),
            new Metric(
                'eas_accounts',
                'ActiveSync (EAS)',
                MetricInterface::TYPE_SNAPSHOT,
                new Accounts('Comptes')
            ),
            new Metric(
                'mapi_accounts',
                'MAPI/Exchange',
                MetricInterface::TYPE_SNAPSHOT,
                new Accounts('Comptes')
            ),
        ];
    }

    /**
     * Retourne les métriques pour TOUS les services actifs de ce serveur.
     *
     * Appelé par le cron WHMCS lors de la collecte globale des métriques.
     * Le tableau retourné est indexé par le username du service (tblhosting.username),
     * qui est le compte administrateur secret créé lors du provisionnement.
     *
     * PERFORMANCE :
     * Pour éviter N+1 appels API (une impersonification par domaine), on fait :
     *   1. Une connexion SA (1 appel)
     *   2. Pour chaque domaine : getDomainDiskUsageGB + loginDomainAdmin + 4 appels
     * Une optimisation future pourrait pré-charger les infos de tous les domaines
     * via l'API SA en une seule requête.
     *
     * @return array [ username => MetricInterface[], ... ]
     */
    public function usage(): array
    {
        $saToken = $this->saToken();
        if (!$saToken) {
            return [];
        }

        // Récupérer tous les services actifs rattachés à CE serveur.
        // On indexe par domain car c'est ce que WHMCS utilise comme $tenant.
        try {
            $services = Capsule::table('tblhosting')
                ->where('server', $this->params['serverid'])
                ->where('domainstatus', 'Active')
                ->select('domain')
                ->get();
        } catch (\Exception $e) {
            return [];
        }

        $usage = [];
        foreach ($services as $svc) {
            $usage[$svc->domain] = $this->collectStats($svc->domain, $saToken);
        }

        return $usage;
    }

    /**
     * Retourne les métriques pour UN service spécifique (tenant).
     *
     * Appelé dans les contextes spécifiques à un service (affichage admin,
     * page de service client, etc.). Le paramètre $tenant correspond au
     * champ tblhosting.username du service.
     *
     * @param string $tenant Username du service (notre admin secret, ex: "xkqmtparis")
     * @return MetricInterface[]
     */
    public function tenantUsage($tenant): array
    {
        // ── Étape 1 : SA Token ────────────────────────────────────────────
        $saToken = $this->saToken();
        if (!$saToken) {
            logActivity('SmarterMail MetricProvider [tenantUsage] FAIL étape 1 : SA login échoué.'
                . ' tenant=' . $tenant
                . ' server=' . ($this->params['serverhostname'] ?? '?'));
            return $this->emptyMetrics();
        }

        // ── Étape 2 : Résolution du domaine ───────────────────────────────
        //
        // WHMCS passe tblhosting.domain comme $tenant (pas tblhosting.username).
        // Le domaine est donc directement utilisable — on valide juste son existence.
        //
        // On tente quand même un fallback par username au cas où le comportement
        // changerait selon la version de WHMCS.
        $domain = null;
        $serverId = (int) ($this->params['serverid'] ?? 0);

        try {
            // Cas 1 (normal) : $tenant est le domaine (tblhosting.domain)
            $row = Capsule::table('tblhosting')
                ->where('domain', $tenant)
                ->when($serverId > 0, fn($q) => $q->where('server', $serverId))
                ->select('domain', 'username')
                ->first();

            if ($row) {
                $domain = $row->domain;
                logActivity('SmarterMail MetricProvider [tenantUsage] étape 2 OK (par domain) : '
                    . 'tenant=' . $tenant . ' domain=' . $domain . ' serverid=' . $serverId);
            }

            // Cas 2 (fallback) : $tenant est le username (tblhosting.username)
            if (!$domain) {
                $row = Capsule::table('tblhosting')
                    ->where('username', $tenant)
                    ->when($serverId > 0, fn($q) => $q->where('server', $serverId))
                    ->select('domain', 'username')
                    ->first();

                if ($row) {
                    $domain = $row->domain;
                    logActivity('SmarterMail MetricProvider [tenantUsage] étape 2 OK (fallback par username) : '
                        . 'tenant=' . $tenant . ' domain=' . $domain . ' serverid=' . $serverId);
                }
            }

        } catch (\Exception $e) {
            logActivity('SmarterMail MetricProvider [tenantUsage] FAIL étape 2 : '
                . 'erreur DB — ' . $e->getMessage());
            return $this->emptyMetrics();
        }

        if (!$domain) {
            logActivity('SmarterMail MetricProvider [tenantUsage] FAIL étape 2 : '
                . 'aucun domaine trouvé pour tenant=' . $tenant
                . ' serverid=' . $serverId);
            return $this->emptyMetrics();
        }

        return $this->collectStats($domain, $saToken);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — Collecte des statistiques
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Collecte toutes les métriques pour un domaine SmarterMail.
     *
     * STRATÉGIE D'APPELS API :
     *
     *   1. loginDomainAdmin()        — Impersonification DA (1 appel SA)
     *   2. getDomainData()           — disk + userCount + aliasCount (1 appel DA)
     *   3. getActiveSyncMailboxes()  — Comptes EAS (1 appel DA)
     *   4. getMapiMailboxes()        — Comptes MAPI (1 appel DA)
     *
     * Total : 4 appels au lieu de 6+ avec l'ancienne approche (SA disk + getUsers + getAliases).
     * getDomainData() utilise l'endpoint GET api/v1/settings/domain/data qui retourne
     * disk + userCount + aliasCount en une seule requête DA.
     *
     * Déclenché par :
     *   - Le cron WHMCS (usage() → collectStats par domaine)
     *   - Le bouton "Refresh Now" dans WHMCS (tenantUsage() → collectStats du service)
     *
     * @param string $domain  Nom de domaine (ex: "client.com")
     * @param string $saToken Token SysAdmin valide (pour l'impersonification DA)
     * @return MetricInterface[]
     */
    private function collectStats(string $domain, string $saToken): array
    {
        $api = $this->api();

        $diskGB     = 0.0;
        $emailCount = 0;
        $aliasCount = 0;
        $easCount   = 0;
        $mapiCount  = 0;

        try {
            // ── Étape 3 : Impersonification DA ────────────────────────────
            $daToken = $api->loginDomainAdmin($saToken, $domain);

            if (!$daToken) {
                logActivity('SmarterMail MetricProvider [collectStats] FAIL étape 3 : '
                    . 'DA impersonification échouée pour domain=' . $domain);
                // Retourner des métriques à 0 plutôt que de planter
                return $this->buildMetrics(0.0, 0, 0, 0, 0);
            }

            // ── Étape 4 : getDomainData (disk + users + aliases) ──────────
            $domainData = $api->getDomainData($daToken);

            if (empty($domainData)) {
                logActivity('SmarterMail MetricProvider [collectStats] AVIS étape 4 : '
                    . 'getDomainData vide pour domain=' . $domain
                    . ' — disk sera 0, userCount/aliasCount seront 0');
            } else {
                $sizeMb     = (float) ($domainData['sizeMb'] ?? 0);
                $diskGB     = round($sizeMb / 1024, 4);
                $emailCount = (int) ($domainData['userCount']  ?? 0);
                $aliasCount = (int) ($domainData['aliasCount'] ?? 0);

                logActivity('SmarterMail MetricProvider [collectStats] étape 4 OK : '
                    . 'domain=' . $domain
                    . ' sizeMb=' . $sizeMb
                    . ' diskGB=' . $diskGB
                    . ' userCount=' . $emailCount
                    . ' aliasCount=' . $aliasCount);
            }

            // ── Étape 5 : EAS ─────────────────────────────────────────────
            $easMailboxes = $api->getActiveSyncMailboxes($daToken);
            $easCount     = count($easMailboxes);

            // ── Étape 6 : MAPI ────────────────────────────────────────────
            $mapiMailboxes = $api->getMapiMailboxes($daToken);
            $mapiCount     = count($mapiMailboxes);

            logActivity('SmarterMail MetricProvider [collectStats] étape 5+6 OK : '
                . 'domain=' . $domain
                . ' EAS=' . $easCount
                . ' MAPI=' . $mapiCount);

        } catch (\Exception $e) {
            logActivity('SmarterMail MetricProvider [collectStats] EXCEPTION : '
                . 'domain=' . $domain . ' — ' . $e->getMessage());
        }

        return $this->buildMetrics($diskGB, $emailCount, $aliasCount, $easCount, $mapiCount);
    }

    /**
     * Construit le tableau de métriques avec les valeurs fournies.
     */
    private function buildMetrics(
        float $diskGB,
        int   $emailCount,
        int   $aliasCount,
        int   $easCount,
        int   $mapiCount
    ): array {
        $values = [
            'disk_gb'        => $diskGB,
            'email_accounts' => $emailCount,
            'aliases'        => $aliasCount,
            'eas_accounts'   => $easCount,
            'mapi_accounts'  => $mapiCount,
        ];

        $result = [];
        foreach ($this->metrics() as $metric) {
            $result[] = $metric->withUsage(
                new Usage($values[$metric->systemName()] ?? 0)
            );
        }
        return $result;
    }



    /**
     * Retourne toutes les métriques avec la valeur 0.
     * Utilisé en cas d'erreur pour retourner un résultat valide.
     *
     * @return MetricInterface[]
     */
    private function emptyMetrics(): array
    {
        return $this->buildMetrics(0.0, 0, 0, 0, 0);
    }
}
