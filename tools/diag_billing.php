<?php
/**
 * ============================================================================
 *  diag_billing.php — Diagnostic facturation EAS/MAPI SmarterMail (LECTURE SEULE)
 * ============================================================================
 *
 *  Pourquoi : « fonctionne en dev, pas en prod » = différence d'ENVIRONNEMENT
 *  ou de DONNÉES. Ce script vérifie, sur le serveur de PRODUCTION, tous les
 *  points de défaillance possibles de la facturation EAS/MAPI, SANS rien
 *  modifier (aucune écriture en base, aucune facture touchée).
 *
 *  USAGE (en ligne de commande, sur le serveur de production) :
 *      php diag_billing.php <serviceid>
 *
 *  où <serviceid> = ID d'un service SmarterMail ACTIF ayant des boîtes EAS/MAPI
 *  (tblhosting.id — visible dans l'URL admin du service, clientshosting.php?id=...).
 *
 *  PLACEMENT : copiez ce fichier dans la racine de l'installation WHMCS
 *  (à côté de init.php), ou n'importe où sous celle-ci — il remonte
 *  automatiquement jusqu'à init.php.
 *
 *  ⚠️  SÉCURITÉ : refuse l'exécution via le web. SUPPRIMEZ le fichier après usage.
 * ============================================================================
 */

// Refuser l'exécution via un navigateur (évite toute exposition publique).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script s'exécute uniquement en CLI : php diag_billing.php <serviceid>\n");
}

// ── Localiser et charger init.php (remonte jusqu'à 8 niveaux de dossiers) ──
$dir = __DIR__;
$init = null;
for ($i = 0; $i < 8; $i++) {
    if (is_file($dir . '/init.php')) { $init = $dir . '/init.php'; break; }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if (!$init) {
    fwrite(STDERR, "ERREUR : init.php introuvable. Placez ce script dans la racine WHMCS.\n");
    exit(1);
}
$ROOT = dirname($init);
require $init;

use WHMCS\Database\Capsule;

function dline(string $k, string $v): void { printf("  %-30s %s\n", $k, $v); }
function dhead(string $t): void { echo "\n── $t ──\n"; }

$serviceId = (int) ($argv[1] ?? 0);

echo "====================================================\n";
echo "  Diagnostic facturation SmarterMail (LECTURE SEULE)\n";
echo "====================================================\n";

// Drapeaux pour le verdict final
$newCodeDeployed = false;
$adminOk         = false;
$apiDaOk         = false;
$easCount        = 0;
$mapiCount       = 0;
$lockDays        = 0;
$protoBillable   = 0;
$protoBilled     = 0;
$isSmarterMail   = false;
$hasPwdFix       = false;

// ── 1. Environnement ─────────────────────────────────────────────────────
dhead('Environnement');
$ver = Capsule::table('tblconfiguration')->where('setting', 'Version')->value('value');
dline('WHMCS version', (string) ($ver ?: 'inconnu'));
dline('PHP', PHP_VERSION . ' (' . PHP_SAPI . ')');
dline('WHMCS root', $ROOT);

// ── 2. Code déployé (hooks.php) ──────────────────────────────────────────
dhead('Code déployé — hooks.php');
$hooks = $ROOT . '/modules/servers/smartermail/hooks.php';
if (is_file($hooks)) {
    $src    = (string) file_get_contents($hooks);
    $hasNew = (strpos($src, "localAPI('UpdateInvoice'") !== false)
              && (strpos($src, '_sm_getAdminUsername') !== false);
    // VRAI insert SQL = « ->insert([ » (avec crochet). On NE matche PAS la simple
    // mention en commentaire « ->insert(). » — sinon faux positif sur le nouveau code.
    $hasOld = (strpos($src, "tblinvoiceitems')->insert([") !== false)
              || (preg_match('/function\s+_sm_recalculerTotalFacture/', $src) === 1);
    // Correctif v1.2.1 : décodage HTML du mot de passe serveur dans le hook
    $hasPwdFix = (strpos($src, 'html_entity_decode') !== false);
    $newCodeDeployed = $hasNew && !$hasOld;
    dline('Chemin', $hooks);
    dline('Modifié le', date('Y-m-d H:i:s', (int) filemtime($hooks)));
    dline('Contient UpdateInvoice (1.2.0)', $hasNew ? 'OUI ✓' : 'NON ✗  (ancien code sur le disque !)');
    dline('Contient ancien SQL brut', $hasOld ? 'OUI ⚠  (code ancien/mixte !)' : 'NON ✓');
    dline('Contient décodage mdp (1.2.1)', $hasPwdFix ? 'OUI ✓' : 'NON ✗  (déployez la v1.2.1 !)');
} else {
    dline('Chemin', $hooks . '  — INTROUVABLE ✗');
}

// ── 3. OPcache (peut continuer à servir l'ancien code compilé) ───────────
dhead('OPcache');
if (function_exists('opcache_get_configuration')) {
    $cfg = @opcache_get_configuration();
    $st  = function_exists('opcache_get_status') ? @opcache_get_status(true) : null;
    dline('Activé (ce SAPI)', !empty($st['opcache_enabled']) ? 'OUI' : 'non');
    $validate = $cfg['directives']['opcache.validate_timestamps'] ?? null;
    dline('validate_timestamps', $validate ? 'OUI' : 'NON ⚠  (ne recharge PAS les fichiers modifiés)');
    dline('revalidate_freq', (string) ($cfg['directives']['opcache.revalidate_freq'] ?? '?'));
    if ($st && !empty($st['scripts'][$hooks]['timestamp'])) {
        dline('hooks.php compilé le', date('Y-m-d H:i:s', (int) $st['scripts'][$hooks]['timestamp']));
    }
    echo "  NOTE : le cron WHMCS tourne en CLI (OPcache souvent distinct/désactivé).\n";
    echo "         Si les factures sont générées via l'admin web (php-fpm), REDÉMARREZ\n";
    echo "         php-fpm (ou videz OPcache) après tout déploiement de hooks.php.\n";
} else {
    dline('OPcache', 'non disponible dans ce SAPI (' . PHP_SAPI . ')');
}

// ── 4. Compte admin pour localAPI('UpdateInvoice') ───────────────────────
dhead('Compte admin (requis par localAPI)');
$admin = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id')->value('username');
dline('Admin sélectionné', (string) ($admin ?: 'AUCUN ✗  (UpdateInvoice impossible)'));
if ($admin) {
    $probe = localAPI('GetInvoices', ['limitnum' => 1], $admin);
    $probeOk = (($probe['result'] ?? '') === 'success');
    $adminOk = $probeOk;
    dline('Test API factures', $probeOk ? 'success ✓' : ('ÉCHEC ✗ : ' . ($probe['message'] ?? json_encode($probe))));
    if (!$probeOk) {
        echo "  ⚠ Cet admin ne peut pas utiliser l'API factures → UpdateInvoice échouera.\n";
        echo "    Vérifiez son RÔLE (Configuration → Administrateurs → Rôles) : il lui faut\n";
        echo "    l'accès aux factures (List/Edit Invoices).\n";
    }
}

// ── 5. Service ciblé ─────────────────────────────────────────────────────
if ($serviceId <= 0) {
    echo "\n⚠ Aucun <serviceid> fourni — checks par service ignorés.\n";
    echo "  Relancez avec l'ID d'un service SmarterMail actif :\n";
    echo "      php diag_billing.php <serviceid>\n";
    exit(0);
}

dhead("Service #$serviceId");
$svc = Capsule::table('tblhosting')
    ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
    ->join('tblservers', 'tblhosting.server', '=', 'tblservers.id')
    ->where('tblhosting.id', $serviceId)
    ->select(
        'tblhosting.domain', 'tblhosting.domainstatus',
        'tblproducts.servertype',
        'tblproducts.configoption2', 'tblproducts.configoption3',
        'tblproducts.configoption4', 'tblproducts.configoption16',
        'tblservers.hostname', 'tblservers.secure', 'tblservers.port',
        'tblservers.username', 'tblservers.password'
    )
    ->first();

if (!$svc) {
    dline('Résultat', 'service introuvable ou sans produit/serveur ✗');
    exit(1);
}
$isSmarterMail = ($svc->servertype === 'smartermail');
$lockDays      = (int) ($svc->configoption16 ?: 0);
dline('Domaine', (string) $svc->domain);
dline('Statut', (string) $svc->domainstatus . ($svc->domainstatus === 'Active' ? ' ✓' : ' ⚠ (non actif)'));
dline('servertype', (string) $svc->servertype . ($isSmarterMail ? ' ✓' : ' ✗ (PAS ce module !)'));
dline('Prix EAS / MAPI / combo', sprintf('%.2f / %.2f / %.2f',
    (float) $svc->configoption2, (float) $svc->configoption3, (float) $svc->configoption4));
dline('Seuil (configoption16)', $lockDays
    . ($lockDays >= 1 ? '  → Phase 1 (DB) active' : '  → Phase 1 OFF ; Phase 2 (live API) uniquement'));

// ── 6. Connexion API SmarterMail (prod) ──────────────────────────────────
dhead('Connexion API SmarterMail');
require_once $ROOT . '/modules/servers/smartermail/lib/SmarterMailApi.php';
$api = new SmarterMailApi(
    (string) $svc->hostname,
    (bool) $svc->secure,
    (int) ($svc->port ?: ($svc->secure ? 443 : 80))
);
dline('Cible', ($svc->secure ? 'https' : 'http') . '://' . $svc->hostname . ':' . ($svc->port ?: ($svc->secure ? 443 : 80)));
$saToken = null;
$daToken = null;
$pwdNeededDecode = false;

// Le hook décrypte le mot de passe serveur puis (depuis le correctif) applique
// html_entity_decode. On teste les DEUX formes pour identifier la cause exacte
// du « Token DA absent » observé en production.
$rawPwd  = (string) decrypt($svc->password);
$decoded = html_entity_decode($rawPwd, ENT_QUOTES | ENT_HTML5, 'UTF-8');
dline('Mot de passe serveur', ($rawPwd !== $decoded)
    ? 'contient des entités HTML (& < > " \') ⚠' : 'sans entité HTML');

// (a) Mot de passe BRUT — ce que faisait l'ANCIEN hook (avant correctif)
try {
    $saToken = $api->loginSysAdmin((string) $svc->username, $rawPwd);
} catch (\Throwable $e) {
    dline('Exception SA (brut)', $e->getMessage());
}
dline('Login SA (decrypt brut)', $saToken ? 'OK ✓' : 'ÉCHEC ✗');

// (b) Si échec ET que le mot de passe contient des entités, retester décodé
//     (= le correctif appliqué au hook)
if (!$saToken && $rawPwd !== $decoded) {
    try {
        $saToken = $api->loginSysAdmin((string) $svc->username, $decoded);
    } catch (\Throwable $e) {
        dline('Exception SA (décodé)', $e->getMessage());
    }
    if ($saToken) {
        $pwdNeededDecode = true;
        dline('Login SA (html_entity_decode)', 'OK ✓  ← CAUSE CONFIRMÉE');
    } else {
        dline('Login SA (html_entity_decode)', 'ÉCHEC ✗  (mot de passe serveur réellement incorrect ?)');
    }
}

if ($saToken) {
    $daToken = $api->loginDomainAdmin($saToken, (string) $svc->domain);
    dline('Impersonation DA', $daToken ? 'OK ✓' : 'ÉCHEC ✗  (domaine absent côté SmarterMail ?)');
}
if ($daToken) {
    $apiDaOk = true;
    try {
        $easCount  = count($api->getActiveSyncMailboxes($daToken));
        $mapiCount = count($api->getMapiMailboxes($daToken));
        dline('Boîtes EAS (live)', (string) $easCount);
        dline('Boîtes MAPI (live)', (string) $mapiCount);
    } catch (\Throwable $e) {
        dline('Exception EAS/MAPI', $e->getMessage());
    }
} else {
    echo "  ⚠ Sans token DA, la Phase 2 (live) ne facture RIEN. Seule la Phase 1 (DB) peut agir.\n";
}

// ── 7. État du suivi mod_sm_proto_usage (période courante) ───────────────
dhead('Suivi mod_sm_proto_usage (période courante)');
require_once $ROOT . '/modules/servers/smartermail/lib/SmarterMailProtoUsage.php';
$period = _sm_getBillingPeriod($serviceId);
dline('Période de facturation', (string) ($period['start'] ?? '?') . ' → ' . (string) ($period['end'] ?? '?'));
if (!empty($period['start'])) {
    $rows          = Capsule::table('mod_sm_proto_usage')
        ->where('serviceid', $serviceId)
        ->where('period_start', $period['start'])
        ->get();
    $protoBillable = $rows->where('billed', 0)->count();
    $protoBilled   = $rows->where('billed', 1)->count();
    dline('Lignes (toutes)', (string) count($rows));
    dline('  facturables (billed=0)', (string) $protoBillable);
    dline('  déjà facturées (billed=1)', (string) $protoBilled);
    if ($lockDays >= 1 && $protoBillable === 0 && $protoBilled > 0) {
        echo "  ⚠ Toutes les lignes sont billed=1 → la Phase 1 n'ajoutera RIEN ce mois-ci.\n";
        echo "    (Héritage probable des factures cassées : marquées facturées sans charge.)\n";
        echo "    → Le revenu dépend alors de la Phase 2 (live) → donc du token DA ci-dessus.\n";
    }
}

// ── 8. Verdict heuristique ───────────────────────────────────────────────
dhead('VERDICT (cause la plus probable)');
$phase1WillBill = ($lockDays >= 1 && $protoBillable > 0);
$phase2WillBill = ($apiDaOk && ($easCount + $mapiCount) > 0);

if (!$isSmarterMail) {
    echo "  ✗ Ce service N'UTILISE PAS le module smartermail — le hook l'ignore. Vérifiez l'ID.\n";
} elseif (!$newCodeDeployed) {
    echo "  ✗ Le hooks.php de PRODUCTION n'est pas (ou pas seulement) la version corrigée.\n";
    echo "    → Redéployez hooks.php, PUIS videz OPcache / redémarrez php-fpm.\n";
} elseif ($pwdNeededDecode) {
    echo "  ✓ CAUSE CONFIRMÉE : l'authentification SysAdmin n'aboutit qu'avec html_entity_decode\n";
    echo "    sur le mot de passe serveur (forme brute = ÉCHEC). C'est la raison du « Token DA absent ».\n";
    if ($hasPwdFix) {
        echo "    ✓ Le hooks.php déployé CONTIENT déjà le correctif v1.2.1 (html_entity_decode).\n";
        echo "    → Générez une facture de test : les lignes EAS/MAPI doivent désormais apparaître.\n";
    } else {
        echo "    ✗ Le hooks.php déployé ne contient PAS encore le décodage → DÉPLOYEZ la v1.2.1.\n";
    }
    echo "    Boîtes EAS/MAPI live détectées : EAS=$easCount, MAPI=$mapiCount.\n";
    if (($easCount + $mapiCount) === 0) {
        echo "    ⚠ Ce domaine n'a AUCUNE boîte EAS/MAPI active — testez avec un service qui en a.\n";
    }
} elseif (!$adminOk) {
    echo "  ✗ L'admin utilisé par localAPI ne peut pas modifier les factures.\n";
    echo "    → Donnez le droit « factures » au rôle de l'admin #" . ($admin ?: '?') . ".\n";
} elseif (!$apiDaOk) {
    echo "  ✗ Connexion API SmarterMail impossible (token DA absent) malgré le décodage du mot de passe.\n";
    echo "    → Le mot de passe SA stocké en prod (Configuration → Serveurs) est probablement\n";
    echo "      erroné : ressaisissez-le, ou vérifiez pare-feu/SSL vers le serveur SmarterMail.\n";
} elseif (!$phase1WillBill && !$phase2WillBill) {
    echo "  ✗ Connexion OK mais aucune source de facturation EAS/MAPI pour ce service :\n";
    echo "      - Phase 1 (DB) : " . ($lockDays < 1 ? "désactivée (configoption16=0)" : "rien de facturable (billed=1 ?)") . "\n";
    echo "      - Phase 2 (live) : 0 boîte EAS/MAPI active sur ce domaine (EAS=$easCount, MAPI=$mapiCount)\n";
    echo "    → « 0 ligne » est alors NORMAL pour ce service. Testez avec un service ayant des boîtes EAS/MAPI.\n";
} else {
    echo "  ✓ Les pré-requis semblent OK (code à jour, admin OK, source de facturation présente).\n";
    echo "    → Générez une facture de test pour ce service, puis lisez le Journal d'activité :\n";
    echo "      cherchez « SmarterMail InvoiceCreation [UpdateInvoice] » — le message indiquera\n";
    echo "      OK (+ nb lignes) ou la raison précise de l'échec.\n";
}

echo "\n(Diagnostic terminé — aucune donnée modifiée. Pensez à SUPPRIMER ce fichier.)\n";
