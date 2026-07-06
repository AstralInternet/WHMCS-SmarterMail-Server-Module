# Changelog

Toutes les modifications notables de ce module sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le
versionnement respecte [Semantic Versioning](https://semver.org/lang/fr/) :

- **MAJEUR** — changement incompatible avec la version précédente (refonte DB,
  rupture d'API publique, suppression de fonctionnalité).
- **MINEUR** — nouvelle fonctionnalité rétrocompatible.
- **CORRECTIF** — correction de bug ou de sécurité, sans changement de comportement.

## [1.5.0] - 2026-07-06

### Ajouté — quota disque bloquant & modèles de facturation (Phase 2, design §3.3)

Nouvelle capacité de « revente » : chaque produit peut porter un **modèle de
facturation** (forfait fixe ou tranches disque) et un **quota disque** avec trois
modes de dépassement, configurables via une **page d'administration** dédiée.
Réglages stockés dans la table `mod_sm_product_settings` (les 23/24 configoptions
étant consommées). **Strictement rétro-compatible** : un produit sans réglage
conserve le comportement historique (tranches, aucun quota).

- **Table `mod_sm_product_settings` + `_sm_getProductSettings()`** (nouvelle lib
  `SmarterMailProductSettings.php`) : requête séparée, cache statique, **repli sur
  défauts sur toute erreur** — la facturation ne casse jamais. Table auto-créée
  (provisionnée au `DailyCronJob`).
- **`_sm_computeBaseCharge()`** — fonction pure « base + modificateur » partagée
  par le hook de facturation ET l'estimé du tableau de bord (plus de divergence
  possible) :
  - modèle `tiers` (défaut) : réécrit la ligne Hosting = tranches × prix ;
  - modèle `flat` : la ligne Hosting reste au prix produit ;
  - quota `block` : plafonne les tranches facturées au quota ; `bill` : ligne
    d'excédent au prix de tranche excédentaire ; `notify` : aucun effet facture,
    alerte seule.
- **Quota ↔ serveur** : `CreateAccount` pousse `maxSize = quota×1024³` en mode
  `block` (le serveur refuse le stockage au-delà) ; `UsageUpdate` renvoie
  `disklimit` (jauge disque native WHMCS, tous modes) ; nouvelle fonction
  **`smartermail_ChangePackage()`** qui réapplique `userLimit`/`maxSize`/`outgoingIP`
  à chaque changement de forfait et **refuse** un quota `block` inférieur à
  l'utilisation courante (auparavant, un changement de forfait n'avait aucun effet
  serveur).
- **Jauge de quota dans l'espace client** : barre colorée (vert < seuil, orange ≥
  seuil, rouge ≥ 100 %) + message contextuel selon le mode, affichée uniquement si
  un quota est défini. Mode sombre pris en charge.
- **Module addon `smartermail_billing`** : page d'administration pour éditer les
  réglages par produit + bouton « Appliquer aux services actifs » (pousse `maxSize`
  au parc existant). CSRF par jeton de session.
- i18n : clés `inv_overage_label`, `err_change_package`, `err_quota_below_usage`,
  `quota_*` (FR/EN, parité maintenue).

> Les modèles `per_mailbox` et `hybrid` (facturation à la boîte) sont réservés à
> une étape ultérieure (comptage de boîtes) — traités comme `tiers` en attendant,
> et non exposés dans la page addon. Le module addon doit être **activé**
> (Configuration → Modules complémentaires) pour éditer les réglages.

## [1.4.1] - 2026-07-06

### Corrigé — le rollover couvre désormais les boîtes créées hors module

Le report (« rollover ») introduit en 1.3.0 ne fonctionnait que pour les
protocoles EAS/MAPI **tracés** dans `mod_sm_proto_usage`, c.-à-d. activés via le
module (createuser/saveuser). Les boîtes dont EAS/MAPI avait été activé
**directement dans SmarterMail** (ou avant l'existence du suivi) n'étaient jamais
inscrites dans la table : elles étaient facturées uniquement par le **fallback
live (Phase 2)**, sans jamais alimenter le rollover. Résultat : pour ces boîtes,
la facturation récurrente **continuait de dépendre de l'API live** — exactement ce
que le rollover devait éliminer.

- **Seeding Phase 2** (`_sm_seedLiveProtoUsage`, `hooks.php` + `SmarterMailProtoUsage.php`).
  Quand la Phase 2 facture un protocole EAS/MAPI en live, elle **matérialise**
  désormais ce protocole dans `mod_sm_proto_usage`, après le succès d'`UpdateInvoice` :
  - une ligne **période courante** `billed=1` (+ `invoiceid`/`billed_at`) — trace la
    facturation live et la rend annulable par le hook `InvoiceCancelled` ;
  - une ligne **période suivante** `billed=0` (rollover) — dès le cycle suivant, la
    Phase 1 (BD) facture le renouvellement **sans l'API live**, puis le rollover
    normal prend le relais.
- Transaction **séparée** du marquage Phase 1 : un échec du seeding ne peut pas
  annuler le marquage déjà appliqué.
- Seuls les protocoles **réellement facturés** (prix > 0) sont matérialisés, et
  seulement hors « mode live » (`configoption16 = 0`, où le suivi BD est
  volontairement désactivé). Insertions **idempotentes** (garde `exists()` +
  contrainte d'unicité). Journalisé : `[P2-seed] service #N : X protocole(s) …`.

**Effet** : au premier passage de facturation, une boîte non tracée est facturée
en live **et** inscrite dans la table ; dès la facture suivante, elle est couverte
par la Phase 1 (BD) + rollover. La dépendance à l'API live au moment de la
facturation disparaît progressivement pour tout le parc existant.

## [1.4.0] - 2026-07-06

### Ajouté — connexion automatique au webmail (SSO) par boîte courriel

- **Auto-login webmail par boîte, par jeton à usage unique.** Le client ouvre le
  webmail d'une adresse **sans ressaisir ses identifiants** : le module demande au
  SysAdmin un jeton d'auto-login pour la boîte visée (endpoint
  `retrieve-login-token`, via `getAutoLoginUrl()` — jusqu'ici présent dans le
  wrapper mais **jamais branché**) puis redirige le navigateur (302) vers l'URL de
  connexion automatique. L'auto-login cible la **boîte demandée** (partie locale du
  username + domaine du service), et non plus l'admin du domaine.
- **Deux points d'entrée**, tous deux par boîte :
  - le bouton « Ouvrir le webmail » ajouté dans l'en-tête de la **page d'édition
    d'un compte** (ouvre le webmail de la boîte en cours de modification) ;
  - l'icône « boîte de réception » de chaque ligne de la **grille des comptes**
    (ouvre le webmail de cette boîte-là).
- **Repli gracieux** : si l'auto-login échoue (SysAdmin injoignable, domaine non
  provisionné, boîte inexistante, version SmarterMail sans l'endpoint…), le lien
  retombe sur la **page de connexion manuelle** du webmail — il reste donc toujours
  utilisable. L'échec technique est journalisé pour l'administrateur ; le client,
  lui, n'est jamais bloqué.

Notes de sécurité : le jeton d'auto-login est à usage unique, expire en quelques
secondes et n'est **jamais exposé au JavaScript de la page** (redirection 302 côté
serveur). Le domaine provient exclusivement du service WHMCS authentifié et le
username de boîte est re-validé côté serveur (partie locale) — le SysAdmin ne peut
connecter que des comptes **du domaine du service**, jamais d'un domaine tiers.

### Retiré

- **Bouton « Ouvrir le webmail » du tableau de bord principal** (remplacé par
  l'auto-login **par boîte** : le bouton de dashboard, niveau domaine, n'avait plus
  de sens).
- **SSO natif WHMCS niveau domaine** (`smartermail_ServiceSingleSignOn()` +
  métadonnée `ServiceSingleSignOnLabel`) : le SSO est désormais **exclusivement par
  boîte**. Un SSO admin niveau domaine pourra être réintroduit séparément via
  `AdminSingleSignOn` si besoin (réutiliserait le même helper).

## [1.3.0] - 2026-07-03

Phase 1 de la remédiation d'audit — **robustesse**. Restaure l'intégrité de la
facturation récurrente et fiabilise le transport API, les vérifications DNS, la
résiliation et la politique de mots de passe.

### Ajouté / Modifié — facturation récurrente résiliente (rollover `proto_usage`)

- **Report (« rollover ») des lignes `proto_usage` d'une période à l'autre.** La
  récurrence EAS/MAPI ne dépend plus d'un appel API *live* au moment de la
  facturation : après un `UpdateInvoice` réussi, les lignes `grace`/`active` sont
  reportées sur la période suivante (`billed=0`) dans la **même transaction** que
  le marquage. La facture du mois suivant se génère donc même si l'API SmarterMail
  est injoignable ce jour-là. Report ignoré pour les cycles *One Time*.
- **Marquage par critère au lieu de DELETE** : `_sm_markEntriesAsBilled()` pose
  `billed=1` + `invoiceid` + `billed_at` (colonnes ajoutées, avec chemin d'upgrade
  automatique du schéma) au lieu d'effacer les lignes — traçabilité facture ↔ usage.
- **Reprise après échec** : `_sm_finalizeAndGetBillable()` sélectionne désormais
  `period_start <=` la période courante (+ `ORDER BY ASC`), donc les lignes non
  marquées après un échec `UpdateInvoice` sont reprises au cycle suivant au lieu
  d'être perdues.
- **Hook `InvoiceCancelled`** : l'annulation d'une facture rend ses lignes de nouveau
  facturables (`billed=0`, `invoiceid=NULL`) — plus de suppléments « avalés » par une
  facture annulée.
- **Correction d'une double-facturation possible** : la déduplication Phase 1/Phase 2
  comparait une clé e-mail brute (API) à des e-mails stockés en minuscules → ratée
  pour toute casse mixte. Dédup désormais par `strtolower(email)` + protocole.
- **Événements re-ciblés sur la « ligne vivante »** (activation/désactivation d'un
  protocole après facturation dans la même période) pour éviter un événement perdu
  ou une violation de contrainte d'unicité.
- **Purge différée** `_sm_purgeBilledProtoUsage(90 j)` au cron dominical (rétention
  des lignes facturées à des fins d'audit avant nettoyage).

### Corrigé — transport API résilient (`SmarterMailApi`)

- **Retries avec backoff + jitter** sur les échecs transitoires (code réseau `0`,
  HTTP 5xx) pour les requêtes idempotentes (GET/DELETE) — 3 tentatives. Une panne
  réseau momentanée ne fait plus échouer une facturation ou une collecte de métriques.
- **`logModuleCall()` sur échec** (tokens et mots de passe masqués) : les appels API
  ratés sont désormais tracés dans le journal des modules WHMCS (auparavant : aucune
  trace).
- **Cache de token SysAdmin** (90 s, par serveur + identifiants) : évite de se
  ré-authentifier à chaque itération de la boucle `InvoiceCreation` et à chaque
  chargement de page client. TTL court → jamais de réutilisation d'un JWT expiré.
- **Délais configurables** (`setTimeouts()`), séparant connexion et opération.

### Corrigé — vérifications DNS fiables

- **Échec de résolution ≠ absence d'enregistrement.** Un `dns_get_record()` qui
  renvoie `false` (timeout, SERVFAIL, résolveur du serveur WHMCS en panne) n'est plus
  **mis en cache** : la vérification se retente au prochain affichage (auto-guérison)
  au lieu d'afficher un faux « rouge » (« enregistrements manquants ») figé 4 h.
- **Conversion IDN → punycode** (`idn_to_ascii`) : un domaine accentué (« café.ca »)
  est désormais interrogé en `xn--…`, sinon la vérification restait rouge en permanence.
- **Détection des NS via le cache** au lieu d'un lookup DNS *live* à chaque rendu du
  tableau de bord (qui pouvait bloquer la page si le résolveur était lent).
- **Cooldown anti-martèlement** sur `refreshdns` : un rafraîchissement forcé est limité
  à 1 / 20 s / service (au-delà, lecture cache) pour éviter la saturation des workers PHP.
- **`AbortController`** (timeout 20 s) sur les requêtes `refreshdns`/`checkdns` côté
  client : plus de bouton figé en « vérification… » si le serveur ne répond pas.

### Corrigé — résiliation & usage robustes

- **`TerminateAccount` traite un `404` comme un succès idempotent** : une résiliation
  ne reste plus bloquée si le domaine a déjà été supprimé côté SmarterMail. La purge de
  `mod_sm_proto_usage` se fait directement par `serviceid` (l'ancien filtre par statut
  était un no-op qui laissait des lignes orphelines).
- **`UsageUpdate` ne persiste plus `0.0` silencieusement** en cas d'erreur de lecture
  disque (`getDomainDiskUsageGB` renvoie `-1.0` → l'appelant retourne `['error']`),
  évitant une désynchronisation SmarterMail ↔ WHMCS.

### Corrigé — politique de mot de passe unifiée

- **Complexité vérifiée côté serveur** pour les boîtes (`createuser`/`savepassword`/
  `saveuser`) via un cœur de validation partagé (`_sm_validateMailboxPassword`).
  Auparavant `saveuser` ne validait que la longueur → un mot de passe faible était
  acceptable par POST direct. Correction aussi du `(int)'' = 0` (longueur minimale
  jamais nulle).

## [1.2.3] - 2026-07-02

### Corrigé — intégrité de la facturation (Phase 0 de la remédiation d'audit)

Correctifs des fuites de revenus identifiées par l'audit complet
(`AUDIT_COMPLET_smartermail.md`).

- **Marquage « facturé » déplacé APRÈS le succès d'`UpdateInvoice`** (`hooks.php`).
  Auparavant `_sm_markEntriesAsBilled()` s'exécutait en Phase 1, AVANT l'appel
  `localAPI('UpdateInvoice')` : il pose `billed=1` et efface les lignes
  `status='deleted'`. Un échec de l'appel faisait alors disparaître les suppléments
  EAS/MAPI de la période sans qu'aucune ligne de facture n'existe — perte de revenu
  irréversible et quasi silencieuse. Le marquage n'a désormais lieu que dans la
  branche de succès confirmé ; en cas d'échec, rien n'est marqué (suppléments
  préservés) et un avertissement est journalisé.
- **Détection des erreurs métier sous HTTP 200** (`SmarterMailApi::request()`).
  L'API SmarterMail peut répondre `HTTP 200` avec `{ success:false, message:… }` ;
  le contrat était documenté en tête de fichier mais jamais vérifié → un échec
  métier passait pour un succès. `request()` l'honore désormais pour tous les
  appelants, et détecte aussi une réponse 2xx non-JSON (proxy/WAF). En conséquence,
  `createuser`/`saveuser` ne facturent plus un protocole EAS/MAPI dont l'activation
  n'a pas été **confirmée** par l'API.
- **Plus de `disk_gb=0` facturable** (`SmarterMailMetricsProvider` + hook). Le
  provider n'émet plus de métriques à zéro en cas d'échec API (le tenant est omis
  → WHMCS conserve sa dernière valeur). Le hook lit désormais la dernière valeur
  `disk_gb` **non nulle** et journalise un avertissement s'il n'en existe aucune —
  évite la sous-facturation massive (« 1 tranche pour tout le monde ») après une
  panne du cron de métriques.
- **Interrupteur de verbosité du journal** (`SMARTERMAIL_DEBUG`). Les ~2 lignes de
  succès par domaine et par collecte du MetricsProvider (qui noyaient les messages
  financiers critiques) sont désormais conditionnées à
  `define('SMARTERMAIL_DEBUG', true)`. Par défaut, seuls les échecs restent visibles.

### Corrigé — interface client

- **Bouton « Générer » de la modale mot de passe** (`edituser.tpl`) : la fonction
  remplit désormais aussi le champ de confirmation, sinon le bouton de soumission
  restait bloqué et le client devait recopier le mot de passe à la main.

### Documentation

- `AUDIT_COMPLET_smartermail.md` enrichi de 3 sections de conception
  pré-implémentation (validation Fable) : rollover `proto_usage` (§2.2), quota ×
  modèles de facturation (§3.3), et fonctionnalité répondeur automatique (nouvelle
  section 6).

---

## [1.2.2] - 2026-06-09

### Sécurité

Durcissement suite à un audit de sécurité statique externe (verdict : **aucune
vulnérabilité critique ou exploitable** ; code défensif de bonne qualité). Trois
points de faible sévérité traités, deux décisions produit prises.

- **F-1 — Avertissement transport HTTP en clair.** Le constructeur de
  `SmarterMailApi` journalise désormais un avertissement (`logActivity`)
  lorsqu'un serveur est configuré **sans SSL** : dans ce cas, les identifiants
  SysAdmin et les tokens JWT Bearer transitent **en clair**. L'avertissement
  n'est **pas bloquant** (certains déploiements internes utilisent HTTP
  volontairement — décision produit) et est limité à un message par hôte et par
  requête PHP (garde statique). Placé dans le **constructeur** plutôt que
  `fromParams()` pour couvrir aussi les instanciations directes (ex: le hook de
  facturation).

- **F-1 (annexe) — Docblock `$verifySsl` corrigé.** L'ancienne documentation
  indiquait « FALSE (défaut) », en contradiction avec le constructeur (défaut
  `true`) et `fromParams()` (`= $secure`). Réécrite pour refléter le comportement
  réel : vérification TLS **activée par défaut**, stricte en HTTPS
  (`CURLOPT_SSL_VERIFYPEER` + `CURLOPT_SSL_VERIFYHOST = 2`).

- **F-3 — Encodage JSON durci (defense-in-depth).** L'injection des cibles de
  redirection dans le `<script>` d'`editredirect.tpl` ajoute désormais
  `JSON_HEX_APOS | JSON_HEX_QUOT` aux flags existants `JSON_HEX_TAG |
  JSON_UNESCAPED_UNICODE` (`smartermail.php`). Non exploitable en l'état (les
  cibles passent par `FILTER_VALIDATE_EMAIL`), mais protège si la validation
  amont venait à être assouplie.

### Non retenu (volontairement)

- **F-2 — hook `ClientAreaHeadOutput` sans contrôle de propriété** : laissé tel
  quel. Impact réel **nul** (la seule sortie est un bloc `<style>` statique
  chargé d'un fichier du module, aucune donnée de la requête n'est reflétée), et
  ajouter un filtre par `userid` risquerait de casser l'affichage du mode sombre
  lors d'une consultation admin en impersonation — pour aucun gain de sécurité.

---

## [1.2.1] - 2026-06-04

### Corrigé

#### « Token DA absent » → suppléments EAS/MAPI non facturés en production

- **Symptôme** : après déploiement de la 1.2.0, le hook journalisait
  `[UpdateInvoice] OK … + 0 ligne(s) EAS/MAPI` accompagné de `Token DA absent —
  Phase 2 live ignorée`. La ligne disque se facturait correctement, mais aucune
  ligne EAS/MAPI n'était ajoutée pour les boîtes facturées via la **Phase 2
  (fallback live API)**.

- **Cause** : le hook `InvoiceCreation` authentifiait le SysAdmin SmarterMail
  avec `decrypt($service->serverpassword)` **sans** `html_entity_decode`,
  contrairement au reste du module (`loginSysAdminFromParams`,
  `_sm_decodePassword`). Quand le mot de passe SA contient un caractère HTML
  (`&`, `<`, `>`, `"`, `'`), WHMCS le stocke encodé (`p&ss` → `p&amp;ss`) : la
  forme brute fait échouer l'authentification SA → aucun token Domain Admin →
  Phase 2 ignorée. Le `SmarterMailMetricsProvider` fonctionnait déjà (d'où des
  statistiques disque correctes) **car il applique `html_entity_decode`** via
  `loginSysAdminFromParams` — d'où l'asymétrie qui a permis d'isoler la cause.

- **Correctif** : le hook applique désormais
  `html_entity_decode(decrypt(...), ENT_QUOTES | ENT_HTML5, 'UTF-8')` au mot de
  passe serveur avant le login SA, alignant le hook sur le reste du module.
  Opération **idempotente** (no-op si le mot de passe est déjà brut).

### Outils

- Ajout de `tools/diag_billing.php` — script de diagnostic **lecture seule**
  (CLI) qui vérifie, sur un serveur de production, tous les points de
  défaillance de la facturation EAS/MAPI : version WHMCS, code réellement
  déployé, OPcache, compte admin `localAPI` + permission factures, connexion API
  SmarterMail (test du mot de passe **brut** et **décodé** pour isoler le
  problème d'encodage), état de `mod_sm_proto_usage`, puis un **verdict** ciblé.
  À supprimer après usage.

---

## [1.2.0] - 2026-06-04

### Corrigé

#### Facturation EAS/MAPI absente sur WHMCS 9.0 (régression majeure)

- **Symptôme** : après la migration vers **WHMCS 9.0**, les lignes de
  supplément **ActiveSync (EAS)** et **MAPI/Exchange** n'apparaissaient plus
  sur les factures clients. La ligne principale (disque, facturée à la tranche)
  restait correcte.

- **Cause** : WHMCS 9.0 a introduit l'**immutabilité des factures** (*« non-Draft
  invoices are immutable »*). Le hook `InvoiceCreation` écrivait les lignes
  directement dans `tblinvoiceitems` en **SQL brut** (`Capsule::insert/delete`)
  puis mettait à jour `tblinvoices.total` à la main. En 9.0, WHMCS **finalise et
  recalcule la facture après le hook** via sa couche modèle : il conserve la
  ligne `Hosting` (reconnue, `type='Hosting'`/`relid=<serviceid>`) mais
  **élimine les lignes « orphelines »** insérées en SQL brut
  (`type=''`/`relid=0`) — précisément les lignes EAS/MAPI. Le `total` recalculé
  par WHMCS écrasait aussi le total écrit manuellement.

- **Correctif** : le hook `InvoiceCreation` passe désormais par l'**API
  officielle `UpdateInvoice`** (LocalAPI) au lieu de toute écriture SQL directe :
  - Ligne disque modifiée **en place** via `itemdescription` / `itemamount` /
    `itemtaxed` (indexés par `lineItemId`) — préserve `type='Hosting'` et
    `relid`, donc le renouvellement du service reste correct.
  - Lignes EAS/MAPI **ajoutées** via `newitemdescription` / `newitemamount` /
    `newitemtaxed` — WHMCS les conserve comme items légitimes.
  - **Total recalculé automatiquement** par WHMCS (la fonction
    `_sm_recalculerTotalFacture()`, qui écrivait `tblinvoices.total` en direct,
    est supprimée).
  - L'appel `localAPI` s'exécute avec un **compte administrateur actif** (helper
    `_sm_getAdminUsername()`) et **journalise systématiquement son résultat**
    (succès *et* échec) dans `Configuration → Journal d'activité` — aucune
    défaillance silencieuse possible (contrairement au SQL brut).

### Modifié

- **Phase 1 (suivi `mod_sm_proto_usage`) découplée de l'API** : la facturation
  des suppléments **tracés** est désormais appliquée **même si le serveur
  SmarterMail est injoignable** au moment de la génération de la facture.
  Auparavant, l'absence de token Domain Admin faisait sauter *toute* la
  facturation EAS/MAPI (Phase 1 incluse), alors que la Phase 1 ne lit que la
  base de données. La **Phase 2** (fallback live API) reste, elle, conditionnée
  à la connexion API et est simplement ignorée (avec journalisation) si le
  token DA est absent.

- **Point de sortie unique** dans le hook : suppression des `continue`
  prématurés ; toutes les écritures sont accumulées dans un payload appliqué en
  **un seul appel `UpdateInvoice` par service**.

### Compatibilité

- Module **compatible WHMCS 9.0+** (en plus de 8.x). Le hook `InvoiceCreation`
  reste le point d'entrée ; seule la **méthode d'écriture** des lignes change.

### Migration

- **Aucune action manuelle requise** au déploiement. Le correctif prend effet à
  la prochaine génération de factures. Les factures **déjà émises** avant la
  mise à jour ne sont pas rétroactivement corrigées (factures immuables en 9.0).

---

## [1.1.0] - 2026-04-29

### Ajouté

#### Sécurité
- **Protection CSRF** sur toutes les actions mutatives de l'espace client
  (création/modification/suppression de boîtes, changement de mot de passe,
  bascule DKIM, ajout/suppression d'alias et de redirections). Helpers
  `_sm_csrfToken()` et `_sm_checkCsrf()` ajoutés. Validation déléguée à
  `check_token()` de WHMCS quand disponible, fallback manuel à temps constant
  via `hash_equals`. Logging diagnostic en cas de refus.

#### Vérifications DNS étendues (SPF / DKIM / Autodiscover / DMARC)
- Nouvelle bibliothèque `lib/SmarterMailDnsCheck.php` qui centralise toute
  la logique DNS et expose `_sm_collectDnsStatus()`,
  `_sm_dnsLookup()` et `_sm_buildDmarcRecord()`.
- **Vérification Autodiscover** : enregistrement CNAME/A
  `autodiscover.{domaine}` + enregistrement SRV `_autodiscover._tcp.{domaine}`.
  Statut tri-état : ✗ rouge (aucun), ⚠ jaune (un seul), ✓ vert (les deux).
  Gestion intelligente du record A : la cible attendue
  (`configoption19` ou `serverhostname`) est résolue dynamiquement en IP(s)
  via le cache DNS, et le record A du client est validé si SON IP est
  présente dans le set des IPs résolues (gère les configs multi-A et le
  round-robin DNS). Aucune configoption d'IP à maintenir — si l'IP du
  serveur change, la vérification suit automatiquement.
- **Vérification DMARC** : enregistrement TXT `_dmarc.{domaine}`. Statut
  bi-état : ✗ rouge (absent), ✓ vert (présent).
- **Cache DNS partagé** : nouvelle table `mod_sm_dns_cache` avec TTL 4 heures
  (modifiable par appel à `_sm_dnsLookup` avec un autre `$ttl`). Création
  automatique au premier accès (pattern `_sm_ensureDnsCacheTable`). Évite
  les requêtes DNS coûteuses à chaque chargement de page.
- **Lazy-load AJAX au render initial** : si le cache est vide pour une ou
  plusieurs vérifications, la page se rend immédiatement avec des pills/
  mini-cartes en état `loading` (animation spinner bleu). Un appel AJAX
  `customAction=checkdns` est déclenché au `DOMContentLoaded` qui remplit
  le cache et met à jour l'UI en place. Aucune requête DNS ne bloque
  jamais le render PHP — le tableau de bord s'affiche en moins de 100 ms
  même sur cache froid. Le mode `$cacheOnly=true` du helper
  `_sm_dnsLookup` retourne `null` au lieu de faire un live lookup.
- **Purge automatique du cache DNS** :
  - **Immédiate** : `smartermail_TerminateAccount()` appelle
    `_sm_purgeDnsCacheForDomain($domain)` après suppression du domaine
    dans SmarterMail. Cible explicitement les hôtes interrogés par le
    module (SPF, DKIM via LIKE sécurisé, DMARC, Autodiscover CNAME/A,
    Autodiscover SRV).
  - **Hebdomadaire** : `DailyCronJob` (le dimanche, en plus du
    `_sm_cleanProtoUsage` existant) parcourt `tblhosting` pour les
    services avec `domainstatus IN ('Cancelled','Fraud','Terminated')`
    et purge leurs entrées DNS — filet de sécurité si la purge immédiate
    a été manquée. Puis `_sm_cleanDnsCacheStale(7 jours)` retire les
    entrées dont `cached_at` est antérieur à 7 jours, indépendamment
    du statut du service (TTL = 4 h donc 7 j = 42× TTL → aucun risque
    de supprimer une entrée encore valide).
- **Bouton "Actualiser"** dans la barre de titre de la carte DNS — déclenche
  un appel POST AJAX (`customAction=refreshdns`) avec jeton CSRF, qui force une
  nouvelle lecture DNS (bypass cache) et met à jour les pills + mini-cartes
  en place sans recharger la page.
- **Endpoints AJAX JSON** : `customAction=checkdns` (lecture, pas de CSRF) et
  `customAction=refreshdns` (force refresh, CSRF requis). Réponses
  `{ok:true,data:{...},checked_at_iso:"..."}`.

#### Générateur DMARC interactif
- Nouvelle modale **"Générateur DMARC"** accessible depuis la mini-carte DMARC.
- Champs configurables : Politique (none/quarantine/reject), Politique
  sous-domaines, Alignement DKIM/SPF, Pourcentage, Format de rapport
  (AFRF/IODEF), Intervalle, RUA, RUF.
- **Aperçu en direct** du record TXT à chaque modification (via
  `smDmarcUpdatePreview` côté JS).
- Bouton "Copier" sur le record généré.
- Pré-remplissage selon `configoption22` (RUA suggéré) et `configoption23`
  (politique suggérée).
- Fonction PHP `_sm_buildDmarcRecord()` disponible pour validation côté
  serveur si nécessaire.

#### Refonte UI du tableau de bord DNS
- **Grille 2×2** de mini-cartes (SPF / DKIM / Autodiscover / DMARC) avec
  bordure gauche colorée selon l'état. Responsive : passe en 1 colonne sous
  600 px.
- **4 pills** dans la barre de titre (au lieu de 2) avec animation pulse
  pendant un refresh.
- Texte explicatif synthétique au-dessus de la grille.
- Indicateur "Dernière vérification" sous le texte explicatif (mis à jour
  après chaque refresh).
- Toggle DKIM conservé dans la mini-carte DKIM (avec CSRF).
- Modales détaillées Autodiscover et DMARC (en plus des modales SPF/DKIM
  existantes), conçues pour la compacité :
  - Texte explicatif déplacé dans une bulle d'aide (i) à droite du titre
    (tooltip natif via `title`, accessible et sans JS).
  - Modale Autodiscover : enregistrements CNAME et SRV séparés visuellement
    par des cartes encadrées (classe `sm-record-card`), avec en-tête
    indiquant le statut Présent/Manquant en couleur.
  - Modale Autodiscover : champs SRV (Priorité, Poids, Port, Cible) sur une
    seule ligne en proportions 20%-20%-20%-40% (responsive : repli en 2×2
    sous 560 px).
  - Générateur DMARC : 4 rangées denses de 2-3 colonnes au lieu d'un
    empilement vertical, aperçu en 2 colonnes (Hôte | Enregistrement) avec
    input single-line au lieu d'un textarea multi-lignes.

#### Configoptions 19 à 23
- `configoption19` — Hôte Autodiscover attendu (vide = `serverhostname`).
- `configoption20` — Cible SRV Autodiscover (vide = `serverhostname`).
- `configoption21` — Vérifier DMARC (yesno) — désactive la mini-carte si off.
- `configoption22` — RUA DMARC suggéré par défaut (pré-rempli dans le
  Générateur).
- `configoption23` — Politique DMARC suggérée (none/quarantine/reject).

#### Espace client
- **Bouton "Annuler"** dans la barre d'actions de la page d'édition d'une
  adresse courriel (`edituser.tpl`) — renvoie au tableau de bord sans
  soumettre le formulaire.
- **Mappage du mode de paiement** côté tableau de bord — les modules
  `authorize` / `authorizenet` / `authorizecim` sont affichés "Carte de
  crédit (Visa/Mastercard)" (ou "Credit Card (Visa/Mastercard)" en anglais).
  Fallback intelligent vers le *Display Name* configuré dans
  `tblpaymentgateways.value` (setting=`name`), puis `ucfirst` du slug brut.
  Libellés stockés dans les fichiers de langue (`pm_credit_card`).

### Corrigé

- **Tag `<code>` rendu en rouge « erreur »** par certains thèmes WHMCS
  (notamment Lagom, `rgb(199, 37, 78)` sur fond beige) dans le tableau de
  bord client. Le client pouvait croire qu'il s'agissait d'un message
  d'erreur alors que le `<code>` ne sert qu'à afficher une valeur DNS,
  un nom d'hôte, etc. Remplacé par un bleu indigo cohérent avec l'accent
  du module (`#3949ab`), sur fond gris très clair (`#f7f8fa`). Scope
  limité à `.sm-card code` et `.sm-mbox code` pour ne pas affecter
  d'autres modules WHMCS partageant la page.

- **Mots de passe contenant des caractères HTML spéciaux (`&`, `<`, `>`, `"`, `'`)**
  étaient envoyés à SmarterMail sous leur forme HTML-encodée (`foo&bar` →
  `foo&amp;bar`), ce qui rendait impossible la connexion ultérieure avec le
  mot de passe original. Cause probable : WHMCS applique une couche de
  sanitization HTML sur les champs `$params['password']`, `$params['serverpassword']`
  et potentiellement `$_POST['password']` selon la configuration anti-XSS
  active.

  **Fix** : nouveau helper `_sm_decodePassword()` (appliquant
  `html_entity_decode($pwd, ENT_QUOTES | ENT_HTML5, 'UTF-8')` + `trim`) inséré
  à tous les points d'entrée :
  - `_sm_initDomainAdmin` (auth DA via `$params['password']`)
  - `_sm_initApi` → `loginSysAdminFromParams` (auth SA via `$params['serverpassword']`)
  - `smartermail_CreateAccount` (mot de passe admin secret)
  - `smartermail_ChangePassword` (changement admin via WHMCS Admin)
  - `smartermail_savepassword` (changement par le client dans l'espace client)
  - `smartermail_createuser` (création de boîte)
  - `smartermail_saveuser` (modification de boîte + changement de mot de passe)

  Le helper est **idempotent** — `html_entity_decode` sur une chaîne déjà
  brute est un no-op, donc aucun risque de double-décodage si WHMCS cesse
  un jour d'encoder.

- **Nom complet (`fullName`) contenant `&`, apostrophe ou guillemets**
  pouvait subir le même problème (« O'Brien » → « O&#039;Brien »). Même
  fix appliqué dans `smartermail_saveuser` : la chaîne passe désormais par
  `strip_tags → html_entity_decode → trim → mb_substr(0, 100)`.

### Ajouté (suite)

- **Mode sombre Lagom (`html.lagom-dark-mode`)** : le tableau de bord client
  s'adapte automatiquement quand l'utilisateur active le mode sombre depuis
  la barre supérieure du thème Lagom. Couverture exhaustive de tous les
  composants de la dashboard :
  - Cartes (Stats, Info service, DNS, Comptes courriel, Alias de domaine)
  - Mini-cartes DNS (SPF/DKIM/Autodiscover/DMARC) + 4 pills + bouton refresh
  - Modales (Autodiscover, DMARC, DMARC Builder, SPF, DKIM, guide DNS,
    facturation, mot de passe, alias)
  - Tableaux + pagination + boutons + toggle DKIM + tooltips
  - Inputs, textareas, selects (forcés en `#1c1f24` / `#e0e0e0` via `!important`
    pour neutraliser les styles Bootstrap inline)
  - Code blocks → bleu teal (`#80cbc4`) sur fond très sombre pour distinguer
    visuellement du rouge "erreur"
  - Badges de statut et étiquettes (Active/Suspended/etc., EAS/MAPI, alias)
    avec arrière-plans translucides pour conserver la lisibilité
  - Variantes colorées des en-têtes de modale (`.sm-mhead.dark/.green/.red/.orange`)
    assombries pour préserver le contraste avec le texte blanc.

  **Architecture : injection PHP via hook** : toutes les règles dark mode
  + le fix `<code>` sont stockées dans un fichier CSS unique
  `templates/_sm_dark_mode.css`. Un nouveau hook `ClientAreaHeadOutput`
  (`hooks.php`) injecte ce CSS dans le `<head>` de toutes les pages
  `clientarea.php?action=productdetails` dont le service appartient au
  module `smartermail` (vérifié via JOIN sur `tblservers.type`). Avantages :
  - Source unique (un seul fichier à modifier pour les futures évolutions)
  - Aucun chemin Smarty fragile (`{include}` cross-module ne fonctionne pas
    de façon fiable dans le contexte clientarea WHMCS)
  - Injection automatique sur toutes les pages du module (dashboard +
    pages secondaires) sans modification des templates
  - Aucune injection sur les services d'autres modules (filtre `serverType`)

  **Couverture pages secondaires** : règles spécifiques aux pages d'édition
  ajoutées au partial — `.sm-back`, `.sm-header` (hero gradient),
  `.sm-actions`, `.sm-pill`/`.sm-pill.fwd` (alias et forwards),
  `.sm-form-label`/`.sm-form-hint`, `.sm-chk-row label`, `.sm-info-btn`,
  `.sm-email-row`/`.sm-email-suffix`, `.sm-pwd-status`/`.sm-btn-setpwd`,
  `.sm-fwd-opts`, `.sm-price-box`, `.sm-pwd-crit`, `.sm-progress`,
  `.sm-del-warn`, `.sm-btn-pwd`/`.sm-btn-save`.

  **Correction** : la classe `.sm-dns-card-header` (utilisée dans la
  carte DNS de la dashboard, en plus de `.sm-card-header`) avait été
  oubliée — l'en-tête de la carte DNS restait gris clair en mode sombre.
  Maintenant inclus dans le partial.

### Modifié

- **Repositionnement de la carte "Alias de domaine"** : déplacée de sous le
  bloc DNS à sous la table "Comptes courriel" — meilleure cohérence
  fonctionnelle (les alias sont une extension de la gestion des comptes
  plutôt qu'une vérification DNS). Les modales associées sont conservées
  ailleurs dans le DOM mais restent enveloppées par le garde
  `{if $domainAliasMax > 0}`.

- **Carte DNS — alignement à gauche** : ajout de `text-align:left`,
  `align-self:stretch`, `width:100%` sur `.sm-card`, et
  `justify-content:flex-start` explicite sur `.sm-card-header`,
  `.sm-stat-row`, `.sm-info-row`, `.sm-stat-value`, `.sm-info-value` —
  contrecarre un éventuel `flex-end` hérité du thème WHMCS parent.

- **Refactor DNS** : la collecte SPF/DKIM inline (~150 lignes dans
  `smartermail.php`) est remplacée par un appel unique à
  `_sm_collectDnsStatus()`. Logique unifiée pour les 4 vérifications,
  passage automatique par le cache.

- **Commentaire CSRF obsolète corrigé** : la docblock de
  `smartermail_toggledkim` indiquait à tort que `$_POST` "évite le CSRF
  trivial". Mise à jour pour refléter la nouvelle protection par jeton.

### Sécurité (non-CSRF)

- **`SmarterMailApi`** : aucun changement, déjà conforme (CURLOPT_SSL_VERIFYPEER
  activé en HTTPS, urlencode systématique, pas de mot de passe loggé).
- **Validation d'entrée** : conservée intacte (`FILTER_VALIDATE_EMAIL`,
  regex strictes pour les usernames/domaines, whitelist d'actions).

### Internationalisation

- ~50 nouvelles clés de langue dans `lang/french.php` et `lang/english.php`
  pour Autodiscover, DMARC, le Générateur DMARC et les libellés UI
  (Refresh, statuts génériques, etc.).
- Clé `err_csrf` ajoutée pour le message d'erreur de jeton invalide.
- Clé `pm_credit_card` ajoutée pour le libellé de paiement.

### Migration

- **Aucune action manuelle requise** au déploiement. La table
  `mod_sm_dns_cache` est créée automatiquement par le pattern lazy
  (`_sm_ensureDnsCacheTable`) lors de la première vérification DNS, soit
  au premier chargement du tableau de bord d'un service après mise à jour.

---

## [1.0.0] - Version initiale

Première version stable du module. Voir le `README.md` à la racine du dépôt
pour la liste exhaustive des fonctionnalités initialement livrées :

- Provisionnement de comptes SmarterMail depuis WHMCS (CreateAccount,
  SuspendAccount, UnsuspendAccount, TerminateAccount, ChangePackage).
- Gestion des boîtes courriel depuis l'espace client (création, modification,
  changement de mot de passe, suppression).
- Gestion des alias et redirections autonomes.
- Gestion des alias de domaine (limite via configoption).
- Suivi de facturation EAS / MAPI / combiné EAS+MAPI avec table
  `mod_sm_proto_usage` (machine d'états grace → active → deleted).
- Vérification DNS SPF et DKIM avec pills statut.
- Modale de configuration DNS multi-onglets (cPanel, Plesk, Espace client,
  Générique).
- Toggle DKIM côté espace client.
- Métriques d'utilisation disque pour facturation par tranches.
- Authentification déléguée (Domain Admin via impersonification System Admin).
- Localisation française et anglaise complète.
