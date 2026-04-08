<?php
/**
 * ============================================================================
 *  SmarterMailProtoUsage.php — Suivi EAS/MAPI pour facturation par utilisation
 * ============================================================================
 *
 * Partagé entre smartermail.php (événements temps réel) et hooks.php (facture).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  MACHINE D'ÉTATS — mod_sm_proto_usage.status
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   ┌──────────────────────────────────────────────────────────────────────┐
 *   │  [Activation]                                                        │
 *   │      ↓                                                               │
 *   │  ┌────────┐   < X jours ET désactivation    ┌──────────────────┐   │
 *   │  │ GRACE  │ ──────────────────────────────→  │  (ligne effacée) │   │
 *   │  └────────┘                                  └──────────────────┘   │
 *   │      │                                                               │
 *   │      │  ≥ X jours (cron ou désactivation)                           │
 *   │      ↓                                                               │
 *   │  ┌────────┐                                                          │
 *   │  │ ACTIVE │                                                          │
 *   │  └────────┘                                                          │
 *   │      │  Désactivation                                                │
 *   │      ↓                                                               │
 *   │  ┌─────────┐   Facture                        ┌──────────────────┐  │
 *   │  │ DELETED │ ──────────────────────────────→  │  (ligne effacée) │  │
 *   │  └─────────┘                                  └──────────────────┘  │
 *   └──────────────────────────────────────────────────────────────────────┘
 *
 *  GRACE   : Protocole activé, délai X jours pas encore écoulé.
 *            Facturé si actif au moment de la facture. Effacé si désactivé
 *            avant d'avoir atteint X jours.
 *
 *  ACTIVE  : Délai X jours écoulé. Facturable même si désactivé ensuite.
 *            Transition déclenchée par le cron quotidien ou à la désactivation.
 *
 *  DELETED : Protocole désactivé alors qu'il était ACTIVE. La ligne reste
 *            jusqu'à la prochaine facture (avec date de suppression), puis
 *            est effacée de la DB définitivement.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 *  TABLE : mod_sm_proto_usage
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  serviceid       INT           — tblhosting.id
 *  email           VARCHAR(255)  — adresse normalisée en minuscules
 *  protocol        ENUM          — 'eas' | 'mapi'
 *  status          ENUM          — 'grace' | 'active' | 'deleted'
 *  period_start    DATE          — début de la période de facturation
 *  threshold_hours INT           — seuil en heures (configoption16 × 24)
 *  activated_at    DATETIME      — moment de l'activation
 *  deleted_at      DATETIME NULL — moment de la désactivation (status=deleted)
 *  billed          TINYINT(1)    — 1 après ajout sur une facture
 *
 *  UNIQUE (serviceid, email, protocol, period_start)
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

use WHMCS\Database\Capsule;


// =============================================================================
//  INITIALISATION DE LA TABLE
// =============================================================================

/**
 * Crée la table mod_sm_proto_usage si elle n'existe pas.
 * Cache statique : vérification unique par exécution PHP.
 */
function _sm_ensureProtoUsageTable(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        if (Capsule::schema()->hasTable('mod_sm_proto_usage')) return;

        Capsule::schema()->create('mod_sm_proto_usage', function ($table) {
            $table->increments('id');

            // ── Identité ──────────────────────────────────────────────────
            $table->integer('serviceid')->unsigned();
            $table->string('email', 255);                     // Toujours en minuscules
            $table->enum('protocol', ['eas', 'mapi']);

            // ── État de la machine d'états ────────────────────────────────
            $table->enum('status', ['grace', 'active', 'deleted'])->default('grace');

            // ── Période de facturation ────────────────────────────────────
            // Calculée depuis nextduedate + billingcycle au moment de l'activation.
            $table->date('period_start');

            // ── Configuration (snapshot au moment de l'activation) ────────
            // On stocke le seuil au moment de la création pour que la logique
            // de facturation ne dépende pas de la config produit courante.
            $table->integer('threshold_hours')->unsigned()->default(24); // 1 jour = 24h

            // ── Chronologie ───────────────────────────────────────────────
            $table->dateTime('activated_at');                 // Début de la période active
            $table->dateTime('deleted_at')->nullable();       // Moment de désactivation (status=deleted)

            // ── Facturation ───────────────────────────────────────────────
            $table->tinyInteger('billed')->default(0);        // 1 = déjà sur une facture

            // ── Contrainte d'unicité ──────────────────────────────────────
            // Une seule entrée par adresse/protocole/période de facturation.
            $table->unique(
                ['serviceid', 'email', 'protocol', 'period_start'],
                'uq_sm_proto_period'
            );
        });

        logActivity('SmarterMail [proto-usage] Table mod_sm_proto_usage créée.');

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] Erreur création table : ' . $e->getMessage());
    }
}


// =============================================================================
//  CALCUL DE LA PÉRIODE DE FACTURATION
// =============================================================================

/**
 * Détermine la période de facturation courante d'un service WHMCS.
 *
 * Utilise nextduedate et billingcycle pour calculer period_start et period_end.
 * Exemple : nextduedate=2025-02-15, billingcycle=Monthly
 *           → start=2025-01-15, end=2025-02-15
 *
 * @param  int   $serviceid
 * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d'] ou nulls si indisponible
 */
function _sm_getBillingPeriod(int $serviceid): array
{
    $empty = ['start' => null, 'end' => null];

    try {
        $svc = Capsule::table('tblhosting')
            ->where('id', $serviceid)
            ->select('billingcycle', 'nextduedate')
            ->first();

        if (!$svc || empty($svc->nextduedate) || $svc->nextduedate === '0000-00-00') {
            return $empty;
        }

        $end      = new DateTime($svc->nextduedate);
        $start    = clone $end;
        $cycle    = strtolower(trim($svc->billingcycle ?? 'monthly'));
        $interval = match ($cycle) {
            'quarterly'     => new DateInterval('P3M'),
            'semi-annually' => new DateInterval('P6M'),
            'annually'      => new DateInterval('P1Y'),
            'biennially'    => new DateInterval('P2Y'),
            'triennially'   => new DateInterval('P3Y'),
            default         => new DateInterval('P1M'), // monthly + Free Account
        };

        $start->sub($interval);

        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] getBillingPeriod error (service #'
            . $serviceid . '): ' . $e->getMessage());
        return $empty;
    }
}


// =============================================================================
//  ÉVÉNEMENT : ACTIVATION
// =============================================================================

/**
 * Enregistre l'activation d'EAS ou MAPI pour une boîte courriel.
 *
 * Appelé dans createuser() et saveuser() quand le protocole passe OFF → ON.
 *
 * CAS POSSIBLES :
 *   - Aucune ligne existante pour la période → INSERT avec status=grace
 *   - Ligne grace/active existante → rien à faire (idempotent)
 *   - Ligne deleted existante (désactivé puis réactivé même période, pas encore facturé)
 *     → reset à grace, deleted_at effacé (la prochaine facture verra la ligne comme grace)
 *
 * @param int    $serviceid      ID du service WHMCS
 * @param string $email          Adresse courriel (normalisée en minuscules)
 * @param string $protocol       'eas' ou 'mapi'
 * @param int    $thresholdHours Seuil en heures (configoption16 × 24)
 */
function _sm_recordProtoActivation(
    int    $serviceid,
    string $email,
    string $protocol,
    int    $thresholdHours
): void {
    _sm_ensureProtoUsageTable();

    $period = _sm_getBillingPeriod($serviceid);
    if (!$period['start']) return;

    $now   = date('Y-m-d H:i:s');
    $email = strtolower($email);

    try {
        $existing = Capsule::table('mod_sm_proto_usage')
            ->where('serviceid',    $serviceid)
            ->where('email',        $email)
            ->where('protocol',     $protocol)
            ->where('period_start', $period['start'])
            ->first();

        if (!$existing) {
            // ── Première activation de la période → grace ─────────────────
            Capsule::table('mod_sm_proto_usage')->insert([
                'serviceid'       => $serviceid,
                'email'           => $email,
                'protocol'        => $protocol,
                'status'          => 'grace',
                'period_start'    => $period['start'],
                'threshold_hours' => max(1, $thresholdHours),
                'activated_at'    => $now,
                'deleted_at'      => null,
                'billed'          => 0,
            ]);

        } elseif ($existing->status === 'deleted' && !$existing->billed) {
            // ── Réactivation après désactivation, pas encore facturé ───────
            // On repart en grace — la ligne deleted non facturée est recyclée.
            // Cas rare : désactivé puis réactivé dans la même période avant la facture.
            Capsule::table('mod_sm_proto_usage')
                ->where('id', $existing->id)
                ->update([
                    'status'       => 'grace',
                    'activated_at' => $now,
                    'deleted_at'   => null,
                    'billed'       => 0,
                ]);
        }
        // grace ou active existant → déjà tracké, rien à faire

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] recordActivation error ('
            . $email . '/' . $protocol . '): ' . $e->getMessage());
    }
}


// =============================================================================
//  ÉVÉNEMENT : DÉSACTIVATION
// =============================================================================

/**
 * Enregistre la désactivation d'EAS ou MAPI.
 *
 * Appelé dans saveuser() quand le protocole passe ON → OFF.
 *
 * RÈGLES :
 *   1. Calculer le temps écoulé depuis activated_at (arrondi à l'heure)
 *   2. Si status = grace ET temps < seuil  → DELETE la ligne (pas facturable)
 *   3. Si status = grace ET temps ≥ seuil  → transition active, puis deleted
 *   4. Si status = active                  → deleted avec deleted_at = NOW()
 *   5. Si status = deleted ou billed       → rien (déjà traité)
 *
 * @param int    $serviceid ID du service WHMCS
 * @param string $email     Adresse courriel
 * @param string $protocol  'eas' ou 'mapi'
 */
function _sm_recordProtoDeactivation(
    int    $serviceid,
    string $email,
    string $protocol
): void {
    _sm_ensureProtoUsageTable();

    $period = _sm_getBillingPeriod($serviceid);
    if (!$period['start']) return;

    $now   = date('Y-m-d H:i:s');
    $email = strtolower($email);

    try {
        $existing = Capsule::table('mod_sm_proto_usage')
            ->where('serviceid',    $serviceid)
            ->where('email',        $email)
            ->where('protocol',     $protocol)
            ->where('period_start', $period['start'])
            ->first();

        // Rien à faire si pas de ligne, déjà deleted, ou déjà facturé
        if (!$existing || $existing->status === 'deleted' || $existing->billed) {
            return;
        }

        // ── Calculer le temps écoulé depuis l'activation (arrondi à l'heure) ─
        $activatedAt  = new DateTime($existing->activated_at);
        $now_dt       = new DateTime($now);
        $elapsedSecs  = $now_dt->getTimestamp() - $activatedAt->getTimestamp();
        $elapsedHours = (int) round($elapsedSecs / 3600); // Arrondi à l'heure

        if ($existing->status === 'grace' && $elapsedHours < (int) $existing->threshold_hours) {
            // ── CAS : grace ET sous le seuil → effacer la ligne ──────────
            // Le client a activé puis désactivé avant d'atteindre le seuil.
            // Aucune facturation → on nettoie simplement la DB.
            Capsule::table('mod_sm_proto_usage')->where('id', $existing->id)->delete();

        } else {
            // ── CAS : grace ≥ seuil OU active → marquer deleted ───────────
            // Si grace : le seuil est atteint → transition grace → deleted directe
            //            (on passe directement à deleted, le billing le verra)
            // Si active : désactivation normale → deleted avec date
            Capsule::table('mod_sm_proto_usage')
                ->where('id', $existing->id)
                ->update([
                    'status'     => 'deleted',
                    'deleted_at' => $now,
                ]);
        }

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] recordDeactivation error ('
            . $email . '/' . $protocol . '): ' . $e->getMessage());
    }
}


// =============================================================================
//  ÉVÉNEMENT : SUPPRESSION D'ADRESSE
// =============================================================================

/**
 * Traite la suppression d'une adresse courriel.
 *
 * Appelé dans deleteuser() AVANT la suppression dans SmarterMail.
 *
 * RÈGLES (même logique que la désactivation) :
 *   - grace ET sous le seuil → DELETE (pas facturable)
 *   - grace ≥ seuil OU active → transition vers deleted avec deleted_at=NOW()
 *   - deleted existant → déjà traité, rien à faire
 *
 * @param int    $serviceid     ID du service WHMCS
 * @param string $email         Adresse supprimée
 * @param int    $thresholdHours Seuil (pour les lignes sans threshold_hours stocké)
 */
function _sm_markMailboxProtoDeleted(
    int    $serviceid,
    string $email,
    int    $thresholdHours
): void {
    _sm_ensureProtoUsageTable();

    $period = _sm_getBillingPeriod($serviceid);
    if (!$period['start']) return;

    $now   = date('Y-m-d H:i:s');
    $email = strtolower($email);

    try {
        $entries = Capsule::table('mod_sm_proto_usage')
            ->where('serviceid',    $serviceid)
            ->where('email',        $email)
            ->where('period_start', $period['start'])
            ->where('billed',       0)
            ->get();

        foreach ($entries as $entry) {
            if ($entry->status === 'deleted') continue; // Déjà traité

            // Calculer le temps écoulé depuis l'activation (arrondi à l'heure)
            $activatedAt  = new DateTime($entry->activated_at);
            $now_dt       = new DateTime($now);
            $elapsedSecs  = $now_dt->getTimestamp() - $activatedAt->getTimestamp();
            $elapsedHours = (int) round($elapsedSecs / 3600);
            $effectiveSeuil = max(1, (int) ($entry->threshold_hours ?: $thresholdHours));

            if ($entry->status === 'grace' && $elapsedHours < $effectiveSeuil) {
                // Sous le seuil → effacer, pas facturable
                Capsule::table('mod_sm_proto_usage')->where('id', $entry->id)->delete();
            } else {
                // Seuil atteint ou déjà active → deleted avec date de suppression
                Capsule::table('mod_sm_proto_usage')
                    ->where('id', $entry->id)
                    ->update([
                        'status'     => 'deleted',
                        'deleted_at' => $now,
                    ]);
            }
        }

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] markMailboxDeleted error ('
            . $email . '): ' . $e->getMessage());
    }
}


// =============================================================================
//  CRON QUOTIDIEN : TRANSITION GRACE → ACTIVE
// =============================================================================

/**
 * Fait avancer les entrées grace → active quand le seuil est atteint.
 *
 * Appelé depuis le hook DailyCronJob dans hooks.php.
 * Garantit que les transitions d'état sont visibles dans l'interface client
 * même si l'utilisateur n'a pas modifié ses paramètres depuis l'activation.
 *
 * @param int $serviceid ID du service WHMCS (0 = tous les services)
 */
function _sm_transitionGraceToActive(int $serviceid = 0): void
{
    _sm_ensureProtoUsageTable();
    $now = date('Y-m-d H:i:s');

    try {
        $query = Capsule::table('mod_sm_proto_usage')
            ->where('status', 'grace')
            ->where('billed', 0);

        if ($serviceid > 0) {
            $query->where('serviceid', $serviceid);
        }

        $graceRows = $query->get();

        foreach ($graceRows as $row) {
            $activatedAt  = new DateTime($row->activated_at);
            $now_dt       = new DateTime($now);
            $elapsedHours = (int) round(
                ($now_dt->getTimestamp() - $activatedAt->getTimestamp()) / 3600
            );

            if ($elapsedHours >= (int) $row->threshold_hours) {
                Capsule::table('mod_sm_proto_usage')
                    ->where('id', $row->id)
                    ->update(['status' => 'active']);
            }
        }

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] transitionGraceToActive error: ' . $e->getMessage());
    }
}


// =============================================================================
//  FACTURATION — HOOK InvoiceCreated
// =============================================================================

/**
 * Retourne les entrées facturables pour un service et une période donnés.
 *
 * RÈGLES DE FACTURATION :
 *   status = grace   → facturer (service actif au moment de la facture)
 *   status = active  → facturer (service actif ou était actif ≥ seuil)
 *   status = deleted → facturer avec date de suppression, puis effacer la ligne
 *
 * @param  int    $serviceid   ID du service WHMCS
 * @param  string $periodStart Début de la période (Y-m-d)
 * @return array  email → ['eas' => row, 'mapi' => row]
 */
function _sm_finalizeAndGetBillable(int $serviceid, string $periodStart): array
{
    _sm_ensureProtoUsageTable();

    try {
        // Toutes les lignes non encore facturées pour cette période
        // (grace + active + deleted)
        $rows = Capsule::table('mod_sm_proto_usage')
            ->where('serviceid',    $serviceid)
            ->where('period_start', $periodStart)
            ->where('billed',       0)
            ->whereIn('status',     ['grace', 'active', 'deleted'])
            ->get();

        // Structurer par email → protocole
        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->email])) $result[$row->email] = [];
            $result[$row->email][$row->protocol] = $row;
        }
        return $result;

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] finalizeAndGetBillable error (service #'
            . $serviceid . '): ' . $e->getMessage());
        return [];
    }
}

/**
 * Marque les entrées comme facturées et nettoie les lignes deleted.
 *
 * Appelé dans le hook InvoiceCreated après avoir ajouté les lignes de facture.
 *
 *   grace/active → billed = 1 (reste en DB pour la période, sera recalculé)
 *   deleted      → DELETE de la DB (dette réglée, ne pas facturer à nouveau)
 *
 * @param array $billableByEmail Retourné par _sm_finalizeAndGetBillable()
 */
function _sm_markEntriesAsBilled(array $billableByEmail): void
{
    foreach ($billableByEmail as $email => $protocols) {
        foreach ($protocols as $protocol => $row) {
            try {
                if ($row->status === 'deleted') {
                    // Facturé avec date de suppression → effacer de la DB
                    Capsule::table('mod_sm_proto_usage')->where('id', $row->id)->delete();
                } else {
                    // grace ou active → marquer billed=1 (reste actif pour la période)
                    Capsule::table('mod_sm_proto_usage')
                        ->where('id', $row->id)
                        ->update(['billed' => 1]);
                }
            } catch (\Throwable $e) {
                logActivity('SmarterMail [proto-usage] markAsBilled error (id='
                    . $row->id . '): ' . $e->getMessage());
            }
        }
    }
}


// =============================================================================
//  AFFICHAGE — DÉTAIL POUR LE POPUP (i)
// =============================================================================

/**
 * Retourne le détail d'utilisation EAS/MAPI pour la période courante.
 *
 * Utilisé par ClientArea pour le popup de facturation.
 * Retourne toutes les entrées facturables (grace, active, deleted) + déjà facturées (billed).
 *
 * Structure de chaque élément :
 *   [
 *     'email'      => string,
 *     'type'       => 'combined' | 'eas' | 'mapi',
 *     'price'      => float,
 *     'status'     => 'grace' | 'active' | 'deleted',
 *     'deleted_at' => string|null,
 *     'billed'     => bool,
 *   ]
 *
 * @param  int   $serviceid
 * @param  float $easPrice
 * @param  float $mapiPrice
 * @param  float $bundlePrice
 * @return array
 */
function _sm_getProtoUsageDetail(
    int   $serviceid,
    float $easPrice,
    float $mapiPrice,
    float $bundlePrice
): array {
    _sm_ensureProtoUsageTable();

    $period = _sm_getBillingPeriod($serviceid);
    if (!$period['start']) return [];

    try {
        $rows = Capsule::table('mod_sm_proto_usage')
            ->where('serviceid',    $serviceid)
            ->where('period_start', $period['start'])
            ->get();

        if ($rows->isEmpty()) return [];

        // Grouper par email
        $byEmail = [];
        foreach ($rows as $row) {
            $byEmail[$row->email][$row->protocol] = $row;
        }

        $effectiveBundle = $bundlePrice > 0 ? $bundlePrice : ($easPrice + $mapiPrice);
        $result          = [];

        // ── Helper : statut effectif d'une ligne ─────────────────────────────
        // Si la DB indique encore 'grace' mais que le seuil est dépassé,
        // on retourne 'active' pour l'affichage — sans attendre le cron quotidien.
        // La transition DB est faite par _sm_transitionGraceToActive() appelée
        // au chargement de ClientArea. Ce helper est un filet de sécurité.
        $effectiveStatus = function (object $entry) use ($now): string {
            if ($entry->status !== 'grace') return $entry->status;
            try {
                $elapsed = (new DateTime($now))->getTimestamp()
                         - (new DateTime($entry->activated_at))->getTimestamp();
                return (($elapsed / 3600) >= (int) $entry->threshold_hours) ? 'active' : 'grace';
            } catch (\Throwable $e) {
                return 'grace';
            }
        };

        foreach ($byEmail as $email => $protos) {
            $hasEas  = isset($protos['eas']);
            $hasMapi = isset($protos['mapi']);

            // Un protocole apparaît dans le détail s'il est grace, active ou deleted
            $easVisible  = $hasEas;
            $mapiVisible = $hasMapi;

            if (!$easVisible && !$mapiVisible) continue;

            // Statut dominant calculé avec le statut effectif (grace recalculé si seuil dépassé)
            $statusPriority = fn($s) => match ($s) {
                'deleted' => 2, 'active' => 1, default => 0
            };

            $dominantStatus = 'grace';
            $deletedAt      = null;
            foreach ($protos as $p) {
                $eff = $effectiveStatus($p);
                if ($statusPriority($eff) > $statusPriority($dominantStatus)) {
                    $dominantStatus = $eff;
                }
                if ($p->status === 'deleted' && $p->deleted_at) {
                    $deletedAt = $p->deleted_at;
                }
            }

            $line = [
                'email'      => $email,
                'status'     => $dominantStatus,
                'deleted_at' => $deletedAt,
                'billed'     => collect($protos)->every(fn($p) => $p->billed),
            ];

            // Type et prix
            if ($easVisible && $mapiVisible) {
                $line['type']  = 'combined';
                $line['price'] = $effectiveBundle;
            } elseif ($easVisible) {
                $line['type']  = 'eas';
                $line['price'] = $easPrice;
            } else {
                $line['type']  = 'mapi';
                $line['price'] = $mapiPrice;
            }

            $result[] = $line;
        }

        // Trier : combined → mapi → eas, puis alphabétique
        usort($result, function ($a, $b) {
            $order = ['combined' => 0, 'mapi' => 1, 'eas' => 2];
            $diff  = ($order[$a['type']] ?? 9) <=> ($order[$b['type']] ?? 9);
            return $diff !== 0 ? $diff : strcmp($a['email'], $b['email']);
        });

        return $result;

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] getProtoUsageDetail error (service #'
            . $serviceid . '): ' . $e->getMessage());
        return [];
    }
}


// =============================================================================
//  MAINTENANCE — NETTOYAGE DES ENREGISTREMENTS OBSOLÈTES
// =============================================================================

/**
 * Supprime les enregistrements mod_sm_proto_usage liés à des services
 * annulés, frauduleux ou résiliés.
 *
 * RÈGLES DE SUPPRESSION :
 *   - Services avec domainstatus IN ('Cancelled', 'Fraud', 'Terminated')
 *   - Optionnellement limité à un serviceid spécifique (pour le bouton admin)
 *   - Les enregistrements billed=0 (non encore facturés) sont inclus :
 *     un service annulé ne sera jamais facturé → inutile de les conserver.
 *   - Les enregistrements billed=1 sont aussi supprimés : la facture a été
 *     émise, la ligne est conservée dans tblinvoiceitems — on peut purger la
 *     ligne de suivi.
 *
 * @param  int $serviceid  0 = tous les services, sinon nettoyage limité à ce service
 * @return array ['deleted' => int, 'services' => int]
 */
function _sm_cleanProtoUsage(int $serviceid = 0): array
{
    _sm_ensureProtoUsageTable();

    try {
        // Trouver les serviceids concernés par les statuts inactifs
        $query = Capsule::table('tblhosting')
            ->whereIn('domainstatus', ['Cancelled', 'Fraud', 'Terminated'])
            ->select('id as serviceid');

        if ($serviceid > 0) {
            $query->where('id', $serviceid);
        }

        $staleServiceIds = $query->pluck('serviceid')->toArray();

        if (empty($staleServiceIds)) {
            return ['deleted' => 0, 'services' => 0];
        }

        $deleted = Capsule::table('mod_sm_proto_usage')
            ->whereIn('serviceid', $staleServiceIds)
            ->delete();

        logActivity(sprintf(
            'SmarterMail [proto-usage] Nettoyage : %d enregistrement(s) supprimé(s) pour %d service(s) annulé(s)/résiliés.',
            $deleted,
            count($staleServiceIds)
        ));

        return ['deleted' => $deleted, 'services' => count($staleServiceIds)];

    } catch (\Throwable $e) {
        logActivity('SmarterMail [proto-usage] cleanProtoUsage error : ' . $e->getMessage());
        return ['deleted' => 0, 'services' => 0];
    }
}
