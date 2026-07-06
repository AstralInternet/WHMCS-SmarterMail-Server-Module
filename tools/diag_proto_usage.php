<?php
/**
 * ============================================================================
 *  diag_proto_usage.php — État du suivi EAS/MAPI mod_sm_proto_usage (LECTURE SEULE)
 * ============================================================================
 *
 *  À QUOI ÇA SERT :
 *  Visualiser le contenu de la table mod_sm_proto_usage pour un service — statut,
 *  billed, invoiceid, périodes — afin de VÉRIFIER le rollover (1.3.0) et le
 *  seeding Phase 2 (1.4.1) SANS rien modifier. Recoupe aussi l'état LIVE côté
 *  SmarterMail : quelles boîtes EAS/MAPI actives ne sont PAS encore tracées
 *  (= facturées par la Phase 2 et matérialisées au prochain succès de facture).
 *
 *  USAGE (en ligne de commande, sur le serveur) :
 *      php diag_proto_usage.php [serviceid]
 *
 *      - sans argument         : résumé global de la table + instructions
 *      - avec <serviceid>       : détail complet du service (tblhosting.id)
 *
 *  LECTURE TYPIQUE POUR VÉRIFIER LE SEEDING :
 *      1. Avant facture : le service n'a AUCUNE ligne, mais le recoupement live
 *         liste des boîtes EAS/MAPI actives « NON TRACÉES ».
 *      2. Générez une facture pour ce service.
 *      3. Après facture : relancez → une ligne billed=1 (période courante,
 *         invoiceid renseigné) + une ligne billed=0 (période suivante) par
 *         protocole ; plus aucune boîte « NON TRACÉE ».
 *
 *  PLACEMENT : copiez ce fichier n'importe où sous la racine WHMCS (il remonte
 *  automatiquement jusqu'à init.php).
 *
 *  ⚠️  SÉCURITÉ : refuse l'exécution via le web. SUPPRIMEZ le fichier après usage.
 * ============================================================================
 */

// Refuser l'exécution via un navigateur (évite toute exposition publique).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script s'exécute uniquement en CLI : php diag_proto_usage.php [serviceid]\n");
}

// ── Localiser et charger init.php (remonte jusqu'à 8 niveaux de dossiers) ──
$dir  = __DIR__;
$init = null;
for ($i = 0; $i < 8; $i++) {
    if (is_file($dir . '/init.php')) { $init = $dir . '/init.php'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if (!$init) {
    fwrite(STDERR, "ERREUR : init.php introuvable. Placez ce script sous la racine WHMCS.\n");
    exit(1);
}
$ROOT = dirname($init);
require $init;

use WHMCS\Database\Capsule;

function dline(string $k, string $v): void { printf("  %-30s %s\n", $k, $v); }
function dhead(string $t): void { echo "\n── $t ──\n"; }
/** Abrège une chaîne à $n caractères (pour l'alignement des colonnes). */
function dcut(?string $s, int $n): string {
    $s = (string) $s;
    return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n - 1) . '…') : $s;
}
/** Formate une valeur nullable pour l'affichage tableau. */
function dnull(?string $s): string { return ($s === null || $s === '') ? '-' : $s; }

$serviceId = (int) ($argv[1] ?? 0);

echo "==========================================================\n";
echo "  Diagnostic mod_sm_proto_usage (LECTURE SEULE)\n";
echo "==========================================================\n";

// ── 1. Environnement ─────────────────────────────────────────────────────
dhead('Environnement');
$ver = Capsule::table('tblconfiguration')->where('setting', 'Version')->value('value');
dline('WHMCS version', (string) ($ver ?: 'inconnu'));
dline('PHP', PHP_VERSION . ' (' . PHP_SAPI . ')');
dline('WHMCS root', $ROOT);

// ── 2. Table mod_sm_proto_usage ──────────────────────────────────────────
dhead('Table mod_sm_proto_usage');
$hasTable = Capsule::schema()->hasTable('mod_sm_proto_usage');
dline('Existe', $hasTable ? 'OUI ✓' : 'NON ✗  (créée au 1er passage du suivi EAS/MAPI)');
if (!$hasTable) {
    echo "\n  La table n'existe pas encore : aucun protocole n'a été tracé, ou le module\n";
    echo "  n'a jamais tourné depuis l'introduction du suivi. Rien d'autre à analyser.\n";
    echo "\n(Diagnostic terminé — aucune donnée modifiée. Pensez à SUPPRIMER ce fichier.)\n";
    exit(0);
}

$total    = (int) Capsule::table('mod_sm_proto_usage')->count();
$distinct = Capsule::table('mod_sm_proto_usage')->pluck('serviceid')->unique()->count();
dline('Lignes (tous services)', (string) $total);
dline('Services distincts', (string) $distinct);

// ── 3. Sans service : résumé global + instructions ───────────────────────
if ($serviceId <= 0) {
    if ($total > 0) {
        dhead('Répartition globale');
        // Comptages simples (pas de SQL brut ni d'agrégat groupé — robuste toutes
        // versions d'Illuminate embarquées par WHMCS).
        $cnt = fn(array $w) => (int) Capsule::table('mod_sm_proto_usage')->where($w)->count();
        dline('Par statut', sprintf('grace=%d  active=%d  deleted=%d',
            $cnt(['status' => 'grace']), $cnt(['status' => 'active']), $cnt(['status' => 'deleted'])));
        dline('Par billed', sprintf('billed=0 : %d   billed=1 : %d',
            $cnt(['billed' => 0]), $cnt(['billed' => 1])));

        dhead('Services avec le plus de lignes');
        // Regroupement côté PHP (une seule colonne chargée).
        $counts = Capsule::table('mod_sm_proto_usage')->pluck('serviceid')
            ->countBy()->sortDesc()->take(10);
        foreach ($counts as $sid => $n) {
            $dom = Capsule::table('tblhosting')->where('id', $sid)->value('domain');
            printf("  service #%-6d %-30s %d ligne(s)\n", (int) $sid, dcut($dom, 30), $n);
        }
    }
    echo "\n⚠ Aucun <serviceid> fourni — relancez avec l'ID d'un service pour le détail :\n";
    echo "      php diag_proto_usage.php <serviceid>\n";
    echo "\n(Diagnostic terminé — aucune donnée modifiée. Pensez à SUPPRIMER ce fichier.)\n";
    exit(0);
}

// ── 4. Service ciblé ─────────────────────────────────────────────────────
dhead("Service #$serviceId");
$svc = Capsule::table('tblhosting')
    ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
    ->leftJoin('tblservers', 'tblhosting.server', '=', 'tblservers.id')
    ->where('tblhosting.id', $serviceId)
    ->select(
        'tblhosting.domain', 'tblhosting.domainstatus', 'tblhosting.billingcycle',
        'tblhosting.packageid',
        'tblproducts.servertype',
        'tblproducts.configoption2', 'tblproducts.configoption3',
        'tblproducts.configoption4', 'tblproducts.configoption16',
        'tblservers.hostname', 'tblservers.secure', 'tblservers.port',
        'tblservers.username', 'tblservers.password'
    )
    ->first();

if (!$svc) {
    dline('Résultat', 'service introuvable ou sans produit ✗');
    exit(1);
}
$isSmarterMail = ($svc->servertype === 'smartermail');
$lockDays      = (int) ($svc->configoption16 ?: 0);
dline('Domaine', (string) $svc->domain);
dline('Statut', (string) $svc->domainstatus);
dline('Cycle de facturation', (string) $svc->billingcycle);
dline('servertype', (string) $svc->servertype . ($isSmarterMail ? ' ✓' : ' ✗ (PAS ce module !)'));
dline('Prix EAS / MAPI / combo', sprintf('%.2f / %.2f / %.2f',
    (float) $svc->configoption2, (float) $svc->configoption3, (float) $svc->configoption4));
dline('Seuil (configoption16)', $lockDays
    . ($lockDays >= 1 ? '  → Phase 1 (DB) + rollover + seeding ACTIFS' : '  → mode live (Phase 1/seeding OFF)'));

require_once $ROOT . '/modules/servers/smartermail/lib/SmarterMailProtoUsage.php';
$period = _sm_getBillingPeriod($serviceId);
$pStart = $period['start'] ?? null;
$pEnd   = $period['end'] ?? null;
dline('Période courante', dnull($pStart) . ' → ' . dnull($pEnd));

// ── 4b. Réglages produit (mod_sm_product_settings) — LECTURE DIRECTE ──────
// On lit la table sans passer par _sm_getProductSettings() (qui créerait la
// table) pour préserver le caractère 100 % lecture seule du diagnostic.
$pid = (int) $svc->packageid;
dhead("Réglages produit #$pid (mod_sm_product_settings)");
if (!Capsule::schema()->hasTable('mod_sm_product_settings')) {
    dline('Table', 'absente → tous les produits en défauts (tiers, quota 0). '
        . 'Sera créée au prochain DailyCronJob / à la 1re lecture.');
} else {
    $ps = Capsule::table('mod_sm_product_settings')->where('product_id', $pid)->first();
    if (!$ps) {
        dline('Ligne dédiée', 'NON → défauts (comportement historique : tiers, quota 0)');
    } else {
        $q = (int) $ps->quota_gb;
        dline('Modèle de facturation', (string) $ps->billing_model);
        dline('Quota disque', $q > 0 ? ($q . ' Go (overage=' . $ps->overage_mode . ')') : 'illimité (0)');
        if ($q > 0) {
            dline('  prix excédent / seuil alerte', sprintf('%.2f / %d%%',
                (float) $ps->overage_price, (int) $ps->notify_threshold_pct));
        }
        if (in_array($ps->billing_model, ['per_mailbox', 'hybrid'], true)) {
            dline('  prix/boîte, Go inclus, boîtes incl.', sprintf('%.2f / %d / %d',
                (float) $ps->per_mailbox_price, (int) $ps->included_gb, (int) $ps->included_mailboxes));
        }
    }
}

// ── 5. Toutes les lignes du service ──────────────────────────────────────
dhead("Lignes mod_sm_proto_usage — service #$serviceId");
$rows = Capsule::table('mod_sm_proto_usage')
    ->where('serviceid', $serviceId)
    ->orderBy('period_start', 'asc')
    ->orderBy('email', 'asc')
    ->orderBy('protocol', 'asc')
    ->get();

if (count($rows) === 0) {
    echo "  (aucune ligne — ce service n'a aucun protocole tracé pour l'instant)\n";
} else {
    printf("  %-10s  %-26s  %-5s  %-7s  %-6s  %-8s  %-19s  %-19s\n",
        'période', 'email', 'proto', 'statut', 'billed', 'facture', 'activée le', 'facturée le');
    printf("  %s\n", str_repeat('-', 104));
    foreach ($rows as $r) {
        printf("  %-10s  %-26s  %-5s  %-7s  %-6s  %-8s  %-19s  %-19s\n",
            (string) $r->period_start,
            dcut($r->email, 26),
            (string) $r->protocol,
            (string) $r->status,
            (string) $r->billed,
            $r->invoiceid ? ('#' . $r->invoiceid) : '-',
            dnull($r->activated_at),
            dnull($r->billed_at)
        );
    }
}

// ── 6. Résumé période courante / suivante (lecture du rollover + seeding) ─
dhead('Résumé (rollover + seeding)');
$curr = $rows->where('period_start', (string) $pStart);
$next = $rows->where('period_start', (string) $pEnd); // vide si $pEnd null (non utilisé)
dline('Total lignes du service', (string) count($rows));
if ($pStart) {
    dline("Période courante ($pStart)", sprintf('%d ligne(s) — facturables(billed=0)=%d, facturées(billed=1)=%d',
        $curr->count(), $curr->where('billed', 0)->count(), $curr->where('billed', 1)->count()));
}
if ($pEnd) {
    dline("Période suivante ($pEnd)", sprintf('%d ligne(s) reportée(s)/seedée(s) — facturables(billed=0)=%d',
        $next->count(), $next->where('billed', 0)->count()));
    if ($next->where('billed', 0)->count() > 0) {
        echo "  ✓ Des lignes billed=0 existent pour la période suivante → le prochain cycle\n";
        echo "    sera facturé par la Phase 1 (BD), sans dépendre de l'API live.\n";
    }
}

// ── 7. Recoupement LIVE : protocoles actifs NON tracés ───────────────────
dhead('Recoupement live SmarterMail (non tracés = candidats Phase 2 / seeding)');
if (!$isSmarterMail) {
    echo "  (ignoré — service non smartermail)\n";
} elseif (empty($svc->hostname)) {
    echo "  (ignoré — aucun serveur associé au service)\n";
} else {
    require_once $ROOT . '/modules/servers/smartermail/lib/SmarterMailApi.php';
    $api = new SmarterMailApi(
        (string) $svc->hostname,
        (bool) $svc->secure,
        (int) ($svc->port ?: ($svc->secure ? 443 : 80))
    );
    // Décodage du mot de passe serveur (brut, puis html_entity_decode en repli —
    // même logique que le hook / diag_billing).
    $rawPwd  = (string) decrypt($svc->password);
    $decoded = html_entity_decode($rawPwd, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $saToken = null;
    try { $saToken = $api->loginSysAdmin((string) $svc->username, $rawPwd); } catch (\Throwable $e) {}
    if (!$saToken && $rawPwd !== $decoded) {
        try { $saToken = $api->loginSysAdmin((string) $svc->username, $decoded); } catch (\Throwable $e) {}
    }
    $daToken = $saToken ? $api->loginDomainAdmin($saToken, (string) $svc->domain) : null;

    if (!$daToken) {
        echo "  ⚠ Connexion API impossible (token DA absent) — recoupement live ignoré.\n";
        echo "    (Le détail BD ci-dessus reste valide ; lancez diag_billing.php pour la connexion.)\n";
    } else {
        try {
            $liveEas  = array_map('strtolower', array_keys($api->getActiveSyncMailboxes($daToken)));
            $liveMapi = array_map('strtolower', array_keys($api->getMapiMailboxes($daToken)));
            dline('Boîtes EAS actives (live)', (string) count($liveEas));
            dline('Boîtes MAPI actives (live)', (string) count($liveMapi));

            // Ensemble des (email, protocole) déjà tracés « vivants » (billed=0)
            // OU facturés sur la période courante — c.-à-d. couverts par la BD.
            $tracked = [];
            foreach ($rows as $r) {
                // Une ligne « couvre » le protocole si elle est billed=0 (à venir/en
                // cours) OU billed=1 sur la période courante (facturée ce cycle).
                if ((int) $r->billed === 0
                    || ($pStart && (string) $r->period_start === (string) $pStart)) {
                    $tracked[strtolower($r->email) . '|' . $r->protocol] = true;
                }
            }

            $untracked = [];
            foreach ($liveEas as $e) {
                if (empty($tracked[$e . '|eas'])) $untracked[] = $e . '  (eas)';
            }
            foreach ($liveMapi as $e) {
                if (empty($tracked[$e . '|mapi'])) $untracked[] = $e . '  (mapi)';
            }

            if (empty($untracked)) {
                echo "  ✓ Tous les protocoles EAS/MAPI actifs sont déjà tracés dans la BD.\n";
                echo "    → Facturation récurrente 100 % Phase 1 (BD) : aucune dépendance à l'API live.\n";
            } else {
                echo "  ⚠ Protocoles actifs NON TRACÉS (" . count($untracked) . ") — facturés par la Phase 2 (live)\n";
                echo "    et matérialisés dans proto_usage au prochain SUCCÈS de facture (seeding) :\n";
                foreach ($untracked as $u) {
                    echo "      - $u\n";
                }
                if ($lockDays < 1) {
                    echo "    NOTE : seuil=0 (mode live) → le seeding est DÉSACTIVÉ ; ces protocoles\n";
                    echo "           resteront facturés en live à chaque cycle. Mettez configoption16 ≥ 1\n";
                    echo "           pour activer le suivi BD + rollover.\n";
                }
            }
        } catch (\Throwable $e) {
            echo "  ⚠ Erreur lors de la lecture live : " . $e->getMessage() . "\n";
        }
    }
}

echo "\n(Diagnostic terminé — aucune donnée modifiée. Pensez à SUPPRIMER ce fichier.)\n";
