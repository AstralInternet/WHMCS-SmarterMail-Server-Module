# Audit complet — Module WHMCS SmarterMail

> **Date :** 2026-07-02 · **Version auditée :** 1.2.2 (commit `55556a1`, arbre propre)
> **Périmètre :** ~17 900 lignes — PHP (smartermail.php 5021, hooks.php 1053, lib/ 4356), templates Smarty (5940), lang FR/EN (1553)
> **Méthode :** audit multi-agents en 3 phases — 8 dimensions d'analyse en parallèle (chaque agent lit réellement le code), **contre-vérification adversariale** de chaque défaut majeur par 1-2 agents sceptiques indépendants (angles *correctitude* et *atteignabilité*), puis 5 volets de propositions (généricité, feuille de route, veille concurrentielle, CSS, GUI). 67 agents au total.
> **Résultat :** 37 constats **CONFIRMÉS** par contre-vérification, 1 constat **RÉFUTÉ** (retiré, documenté en §3.9), 47 propositions priorisées.

---

## Sommaire

1. [Synthèse exécutive](#1-synthèse-exécutive)
2. [Partie I — Audit du module actuel](#2-partie-i--audit-du-module-actuel)
3. [Partie II — Flexibilité multi-entreprise](#3-partie-ii--flexibilité-multi-entreprise)
4. [Partie III — Fluidité client, CSS et GUI](#4-partie-iii--fluidité-client-css-et-gui)
5. [Plan d'action global priorisé](#5-plan-daction-global-priorisé)

---

# 1. Synthèse exécutive

## Verdict global

Le module est **fonctionnel, sécuritaire et exceptionnellement bien documenté** — dispatcher à whitelist stricte, CSRF systématique, validation d'entrée rigoureuse, compatibilité WHMCS 9 acquise (UpdateInvoice), aucune primitive dangereuse. C'est un socle sain.

L'audit révèle cependant que **la chaîne de facturation — le cœur de la valeur du module — comporte des fuites de revenus structurelles**, toutes confirmées par contre-vérification dans le code :

| # | Constat | Impact |
|---|---------|--------|
| 1 | `_sm_markEntriesAsBilled()` exécuté **AVANT** `UpdateInvoice` — si l'appel échoue, les lignes sont marquées facturées/effacées sans qu'aucune ligne de facture n'existe | **Perte définitive** de revenus + disque non multiplié |
| 2 | La facturation **récurrente** EAS/MAPI dépend à 100 % de l'API live après la 1ʳᵉ période (aucun report des lignes de suivi) — le scénario « Token DA absent » déjà vécu en production omet tous les suppléments | Perte récurrente, silencieuse |
| 3 | Panne API pendant le cron de métriques → `disk_gb=0` écrit puis facturé → **tous les clients à 1 tranche** ce cycle | Sous-facturation massive ponctuelle |
| 4 | Facture annulée/régénérée → `billed=1` jamais réinitialisé, lignes `deleted` purgées → suppléments partiellement irrécupérables | Perte conditionnelle |
| 5 | L'API SmarterMail peut répondre **HTTP 200 avec échec métier** — contrat documenté dans l'en-tête du wrapper mais **jamais vérifié** → possibles faux succès (création de boîte) et facturation d'un protocole jamais activé | Intégrité + facturation |

Aucun de ces défauts n'est exploitable par un tiers ; ce sont des **défauts de robustesse à impact financier**, déclenchés par des pannes ou des actions admin. Les correctifs n°1 et n°2 sont prioritaires (quelques lignes pour le n°1).

## Réponses aux trois questions posées

1. **Audit complet** → Partie I. 37 défauts confirmés (1 critique, 12 élevés, le reste moyen/faible), aucun critique de sécurité. Le point structurel : `smartermail_ClientArea` (1085 lignes, 8 responsabilités) et une duplication systématique (validation mot de passe ×4 chemins incohérents, blocs EAS/MAPI ×2, résolution d'alias ×4).
2. **Flexibilité multi-entreprise** → Partie II. Le cœur technique est déjà générique ; les blocages sont concentrés et bien délimités : marque Astral en dur (NS, exemples, onglet portail), `$` et devise ID 1 figés, admin 100 % francophone, quotas jamais bloquants (`maxSize=0` en dur), pas de `ChangePackage`, pas d'adoption de domaines existants. 21 propositions priorisées, dont 5 à effort trivial/faible.
3. **Fluidité client / CSS / GUI** → Partie III. État des lieux chiffré : 854 lignes CSS dupliquées dans 5 templates, 96 couleurs hex sans aucune variable, ~30 fonctions JS copiées (avec **un bug réel de divergence** : le bouton « Générer » de la modale mot de passe d'edituser bloque la soumission), perte de saisie totale après erreur serveur, aucun message de succès. Plan de standardisation en 5 chantiers + 13 simplifications GUI, toutes réalisables en Smarty/CSS/JS vanilla.

---

# 2. Partie I — Audit du module actuel

## 2.1 Forces confirmées (ne pas « corriger »)

- **Sécurité** : dispatcher à whitelist (smartermail.php:2041-2066), CSRF sur toutes les actions mutatives avec `check_token()` + fallback `hash_equals` (:758-791), PRG avec nettoyage CRLF (:2175), path-traversal bloqué dans `_sm_lang` (:654-685), CSPRNG partout, `urlencode` systématique côté API, durcissements v1.2.2 tous vérifiés présents.
- **Compatibilité WHMCS 9** : plus aucune écriture directe `tblinvoice*` ; payload unique via `localAPI('UpdateInvoice')` journalisé succès/échec (hooks.php:718-738). Seule écriture directe restante hors `mod_sm_*` : `tblhosting.username/password` dans CreateAccount (choix argumenté, risque faible).
- **Compatibilité PHP 8.3** : aucune fonction dépréciée, aucune propriété dynamique, signatures typées, `match()`.
- **Factorisation d'initialisation API** : `_sm_initApi`/`_sm_initDomainAdmin` utilisés en tête de toutes les fonctions métier — aucun bloc cURL dupliqué (hors une exception, voir 2.4).
- **i18n client** : parité parfaite des fichiers de langue (521 clés strictement identiques FR/EN), templates quasi intégralement en `{$lang.*}`.
- **DNS** : cache DB avec contrainte UNIQUE, TXT fragmentés >255 o correctement concaténés, purge à 3 niveaux, stratégie lazy (rendu jamais bloqué par les 4 vérifications), fallback autodiscover par intersection d'IPs.
- **Templates** : namespace `sm-` rigoureux, seulement 7 `!important`, dark mode déjà centralisé selon le bon patron (fichier unique + hook d'injection).

## 2.2 Facturation — les constats financiers (dimension la plus critique)

### 🔴 CRITIQUE — Marquage « facturé » avant l'appel UpdateInvoice ([hooks.php:625](modules/servers/smartermail/hooks.php#L625) vs [:726](modules/servers/smartermail/hooks.php#L726)) — CONFIRMÉ ×2

`_sm_markEntriesAsBilled()` (qui **DELETE physiquement** les lignes `status='deleted'` et pose `billed=1` sur grace/active — SmarterMailProtoUsage.php:509-529) s'exécute à la ligne 625, alors que le payload n'est appliqué que par `localAPI('UpdateInvoice')` à la ligne 726. Trois chemins d'échec post-marquage existent : (a) aucun admin actif → abandon sans appel ; (b) `UpdateInvoice` échoue → simple logActivity ; (c) exception entre 625 et 726 (noter : le catch Phase 2 n'attrape que `\Exception` — un `\Error` saute directement au catch global en court-circuitant le localAPI). Dans tous ces cas : suppléments EAS/MAPI de la période **perdus définitivement** (lignes deleted effacées sans trace des montants) ET ligne disque restée au prix de base.

> **Correctif (quelques lignes)** : déplacer `_sm_markEntriesAsBilled` APRÈS le localAPI et le conditionner à `$apiRes['result'] === 'success'`. En échec : ne rien marquer (reprise au cycle suivant) + alerte admin par courriel, pas seulement logActivity.

### 🟠 ÉLEVÉ — Récurrence EAS/MAPI 100 % dépendante de l'API live ([hooks.php:641](modules/servers/smartermail/hooks.php#L641)) — CONFIRMÉ ×2

Les lignes `mod_sm_proto_usage` ne sont créées qu'à l'événement OFF→ON et **jamais reportées à la période suivante** (elles restent `billed=1` avec l'ancien `period_start`). À chaque facture ultérieure, la Phase 1 (DB) ne matche rien → tout repose sur la Phase 2 (live), qui exige le token DA. « Token DA absent » au moment du cron = facture émise **sans aucun supplément**, sans rattrapage (scénario déjà observé en production avant la v1.2.1). Aggravant : trois conséquences en cascade confirmées —

- **Dédup par courriel au lieu de courriel+protocole** (hooks.php:586/660) : jean active EAS en N puis MAPI en N+1 → en N+1 seule la ligne MAPI est facturée et la Phase 2 saute l'adresse entière → supplément EAS + tarif combiné perdus pour la période.
- **Désactivation d'un protocole hérité** (SmarterMailProtoUsage.php:295) : `_sm_recordProtoDeactivation` ne trouve pas de ligne pour la période courante → retour muet → les semaines d'utilisation partielles ne sont jamais facturées. La machine d'états « DELETED » documentée ne fonctionne que pendant la période d'activation initiale.
- **Facture annulée/régénérée** : aucun hook `InvoiceCancelled` ; `billed=1` irréversible et lignes deleted purgées → refacturation partielle uniquement (protocoles encore actifs en live).

> **Correctif structurel unique** : après facturation **réussie**, réinsérer les lignes grace/active avec le `period_start` suivant et `billed=0` (rollover). Cela rend la Phase 1 (DB) autonome pour les renouvellements et corrige d'un coup les 3 cas en cascade. Compléter par : dédup par email+protocole, conservation des lignes deleted (purge différée) + stockage de l'`invoiceid` + hook `InvoiceCancelled` remettant `billed=0`.

### 🟠 ÉLEVÉ — Métriques à zéro écrites puis facturées ([SmarterMailMetricsProvider.php:333-338](modules/servers/smartermail/lib/SmarterMailMetricsProvider.php#L333)) — CONFIRMÉ ×2

`collectStats()` retourne des métriques **à 0** (au lieu d'omettre le tenant) sur échec DA/API. Le hook lit la **dernière** ligne `disk_gb` sans contrôle de valeur ni de fraîcheur (hooks.php:416-420) → `max(1, ceil(0/tranche)) = 1 tranche`. Une panne SmarterMail en cours de collecte la nuit précédant la génération = clients facturés 1 tranche (ex. réel : 155 Go → 1 au lieu de 16). Le symétrique existe : valeur périmée facturée sans limite d'âge.

> **Correctif** : ne jamais émettre `disk_gb=0` sur échec (omettre le tenant) ; côté hook, refuser une métrique nulle ou > 48 h et retomber sur la dernière valeur non nulle + alerte admin.

### 🟠 ÉLEVÉ — Erreurs métier sous HTTP 200 jamais vérifiées ([SmarterMailApi.php:361-371](modules/servers/smartermail/lib/SmarterMailApi.php#L361)) — CONFIRMÉ ×2

L'en-tête du wrapper (lignes 89-91) avertit que l'API peut répondre `200` avec `{success:false, message:...}` — mais `request()` ne calcule le succès que sur le code HTTP, et **aucun code du module ne lit `data['success']`/`data['message']`**. Pire pour la facturation : `setActiveSyncEnabled`/`setMapiEnabled` ne sont même pas testés (retour jeté, smartermail.php:3773/4106/4134) avant `_sm_recordProtoActivation` → un client peut être **facturé pour un protocole jamais activé** (même sur simple erreur HTTP). Le pattern « 200 sans effet » est attesté en production par le module lui-même (workaround DKIM, smartermail.php:1217-1229).

> **Correctif à effet global** : dans `request()`, si `$success && isset($decoded['success']) && $decoded['success']===false` → forcer `success=false, error=$decoded['message']`. Un changement, tous les appelants corrigés. Vérifier aussi `json_last_error()` (200 non-JSON d'un proxy = faux succès actuellement). Et tester le retour des `set*Enabled` avant d'enregistrer l'activation facturable.

### Autres constats facturation confirmés

| Sévérité | Constat | Localisation |
|---|---|---|
| Moyen | Phase 2 sans garde de période : 2ᵉ facture Hosting dans le même cycle → disque re-multiplié + suppléments refacturés intégralement | hooks.php:641, 435-437 |
| Moyen* | Symbole `$` codé en dur dans les libellés de facture + prix unitaire non formaté (« $6.5 ») + `number_format` anglophone | french.php:643, english.php:640, hooks.php:457-460 |
| Faible | `round()` au lieu de `floor()` sur le temps écoulé : seuil de grâce atteint jusqu'à 30 min trop tôt (facturation contraire à la promesse documentée) | SmarterMailProtoUsage.php:307, 381, 437 |
| Faible | Cycles « One Time »/« Free Account » traités comme mensuels ; « 0.00 Go utilisé » sur la facture de commande | SmarterMailProtoUsage.php:158 |
| Faible | `configoption16=0` (mode live) : les activations sont quand même enregistrées → croissance illimitée de `mod_sm_proto_usage` | smartermail.php:4091 |
| Info | Docblocks de `usage()`/`tenantUsage()` contredisent le code (username vs domain) — piège pour un futur mainteneur | SmarterMailMetricsProvider.php:179-187 |

*\* rétrogradé Faible par les vérificateurs : cosmétique (les montants restent exacts), mais visible sur chaque facture.*

## 2.3 Wrapper API — robustesse du transport (5 constats élevés, tous confirmés ×2)

1. **Aucun retry/backoff** ([SmarterMailApi.php:341](modules/servers/smartermail/lib/SmarterMailApi.php#L341)) : un unique `curl_exec`, toute erreur cURL retourne immédiatement `code=0`. Une microcoupure pendant un cron = factures incomplètes/métriques à zéro.
2. **Erreurs converties en valeurs facturables** : ~15 getters « simplifiés » retournent `[]`/`0.0` sur toute erreur (réseau, 401, 404) — indistinguable d'un vrai vide. `UsageUpdate` écrit alors `diskusage=0` comme un succès.
3. **Échecs réseau silencieux** : `request()` ne journalise rien ; **`logModuleCall` (l'outil de debug standard WHMCS) est absent de tout le module** (0 occurrence vs 111 `logActivity`). Une panne post-authentification n'apparaît nulle part — le client voit « 0 compte ».
4. **N+1 et authentifications répétées** : aucun cache de token hors MetricsProvider ; 17 sites d'appel `_sm_initDomainAdmin` ; le rendu du tableau de bord = ~80 requêtes HTTPS séquentielles pour 50 boîtes (`getMailboxForwardList` par boîte, `getAlias` appelé 2× pour les mêmes alias, aucun keep-alive) ; un clic client = 4-9 `authenticate-user` (POST→redirect→AJAX).
5. **Timeouts fixes 10/30 s non configurables** : hook InvoiceCreation bloqué jusqu'à ~120 s/service si le serveur est lent ; `loginSysAdmin` appelé **dans** la boucle des services sans mémorisation d'échec par hôte.

Constats moyens : couche cURL entièrement **recopiée dans `addDomainAlias`** (~40 lignes, en violation de la règle d'en-tête du fichier) ; `loginDomainAdminDirect` = code mort ; 7 conventions de retour différentes ; `take=99999` sans détection de troncature.

> **Priorités** : (1) détection erreur métier dans `request()` ; (2) jamais de 0/[] facturable sur erreur + 2-3 retries à backoff sur code=0/5xx ; (3) `logModuleCall` dans `request()` au moins sur échec (tokens masqués) ; (4) cache statique de tokens par hôte/domaine (modèle : MetricsProvider) ; (5) timeouts configurables.

## 2.4 DNS — exactitude et disponibilité

- 🟠 **Échec du résolveur indiscernable d'un enregistrement absent, mis en cache 4 h** ([SmarterMailDnsCheck.php:169-179](modules/servers/smartermail/lib/SmarterMailDnsCheck.php#L169)) — CONFIRMÉ ×2. `dns_get_record()===false` (timeout/SERVFAIL) est converti en `[]` **puis écrit au cache** → 4 pastilles rouges mensongères pendant 4 h ; le client risque de modifier son DNS inutilement. *Correctif : tester `false` séparément, ne pas cacher, propager un statut `unknown` rendu en gris.*
- 🟠 **`refreshdns` : jusqu'à 6-7 lookups bloquants sans timeout** — CONFIRMÉ ×2. DNS autoritaire du domaine injoignable (domaine expiré = cas courant) → requête AJAX de 30-180 s, worker PHP-FPM occupé, `fetch()` sans AbortController, aucun rate-limit serveur. *Correctif : librairie DNS avec timeout (ou court-circuit au 1ᵉʳ échec) + cooldown 15-30 s/service + AbortController JS.*
- **IDN non gérés** (aucun `idn_to_ascii` dans le module) : un domaine accenté donne des vérifications rouges permanentes — CONFIRMÉ.
- **Requête NS live non cachée à chaque rendu du dashboard** (smartermail.php:2925) — contredit la stratégie lazy documentée 30 lignes plus haut ; blocage possible 10-30 s. — CONFIRMÉ.
- **Cible SRV validée par égalité textuelle stricte** : faux « warn » si la cible est un CNAME du serveur attendu (le fallback IP existe déjà pour le CNAME/A — l'étendre au SRV). — CONFIRMÉ.
- Faible/info : TTL 14400 en littéral ×7 ; commentaires « cache 5 min » erronés (réel : 4 h) ; `updateOrInsert` non atomique (bruit de logs) ; purge DKIM en LIKE à wildcard initial (full scan, acceptable).

## 2.5 Robustesse et compatibilité

- **TerminateAccount : purge « immédiate » = no-op** — CONFIRMÉ. `_sm_cleanProtoUsage` filtre `domainstatus IN ('Cancelled','Fraud','Terminated')`, or WHMCS ne bascule le statut **qu'après** le retour du module → `$staleServiceIds` vide. Le commentaire décrit un comportement qui n'a jamais lieu (filet hebdo = seule vraie purge). *Correctif : DELETE direct par serviceid dans ce contexte.*
- **TerminateAccount non idempotent** — CONFIRMÉ. Domaine déjà supprimé côté SM (404) → résiliation WHMCS **bloquée indéfiniment** via le module. *Correctif : traiter 404 comme succès idempotent.*
- **UsageUpdate silencieux sur domaine disparu** — CONFIRMÉ. 404 → `0.0` sans log → sous-facturation disque récurrente + désynchronisation SM↔WHMCS invisible.
- **Spam du journal d'activité** : ~25 `logActivity` de **succès**, dont 2/domaine/collecte dans le MetricsProvider → 400 lignes/jour pour 200 domaines. **Les messages financiers critiques (« UpdateInvoice ÉCHEC », « Token DA absent ») sont noyés** — c'est ce qui rend les pertes du §2.2 indétectables. *Correctif : helper `_sm_logDebug()` conditionné (constante ou configoption), ou migration vers `logModuleCall`.*
- Confirmés faibles : estimation client en devise ID 1 codée en dur (smartermail.php:2302) ; `error.tpl` sans `$lang`/`$serviceid` sur les branches d'erreur du routeur (titre vide, lien retour cassé) ; fenêtre de course finalize/mark non transactionnelle (réelle mais ~ms, lignes orphelines seulement) ; pas d'anti double-soumission sur les formulaires.

## 2.6 Architecture et qualité de code

- 🟠 **`smartermail_ClientArea` : 1085 lignes, 8 responsabilités** (routeur, AJAX, facturation, enrichissement N+1, redirections, machine DKIM 5 états, détection NS, 62 variables template) — CONFIRMÉ ×2 (sévérité effective : dette de maintenabilité, pas un bug). 6 fonctions > 150 lignes (liste exhaustive vérifiée à la ligne près).
- **Validation de mot de passe incohérente entre 4 chemins** — CONFIRMÉ ×2, le plus concret des constats architecture : `_sm_validateAdminPassword` (complet, plancher 8) pour l'admin ; `createuser`/`savepassword` (longueur défaut **6**, pas de complexité) ; `saveuser` (**longueur seule**). Les critères majuscule/chiffre/spécial affichés au client ne sont validés **qu'en JavaScript** → un POST direct `saveuser` avec `aaaaaaaa` passe. Aggravant découvert en vérification : `configoption9=''` → `(int)'' = 0` → aucune longueur minimale sur les 3 chemins client. *Correctif : `_sm_validateMailboxPassword()` unique appelée aux 3 endroits.*
- **Normalisation username incohérente** — CONFIRMÉ : `createuser` fait `strtolower` + regex stricte ; les 4 autres actions non → doublon possible à casse mixte dans `aliasTargetList` (saveuser:4203).
- Duplications : résolution alias→cibles ×4 (avec **double appel HTTP `getAlias`** pour les mêmes alias dans ClientArea), bloc EAS/MAPI createuser/saveuser ×2, validation des cibles ×2, regex ×2-5.
- Documentation : en-tête du fichier liste **8 fonctions qui n'existent plus** ; `ServiceSingleSignOnLabel` déclaré sans fonction `ServiceSingleSignOn` (métadonnée morte — l'API a pourtant `getAutoLoginUrl()` jamais branché) ; **README nettement périmé** (templates fantômes, hook `InvoiceCreated` au lieu d'`InvoiceCreation`, 8 options documentées sur 23, « Alias de domaine » listé comme extension future alors qu'implémenté).
- Ergonomie admin : 23 configoptions en liste plate, groupes éclatés par l'historique ; `configoption21` (DMARC) seul yesno **sans Default** → désactivé silencieusement sur un nouveau produit.

## 2.7 Internationalisation

- **Factures** : `date('d-M-y')` → mois **anglais** sur factures françaises (« 15-Aug-26 ») — CONFIRMÉ ×2 ; libellés émis dans la **langue système**, jamais celle du client — CONFIRMÉ ×2 (un client anglophone d'un WHMCS FR reçoit « Go utilisé · Tranche(s)... », figé dans la facture).
- **Fallbacks incohérents** — CONFIRMÉ : `_sm_hookLang` retombe sur **`french`** (2 niveaux), `_sm_lang` sur `english`. Une entreprise dont la langue système n'est ni FR ni EN aurait l'espace client en anglais et **les factures en français**.
- 3 chaînes françaises codées en dur côté client (clientarea.tpl:740-741, adduser.tpl:461, smartermail.php:3825) — CONFIRMÉ ; 5 appels `error.tpl` sans `lang` — CONFIRMÉ ; `$` en dur ×19 dans les templates — CONFIRMÉ ; pluralisation `{if $tiers > 1}s{/if}` fragile ; `date_format` Smarty dépendant de la locale serveur ; fallbacks `??` tous en français (une 3ᵉ langue incomplète fuirait vers le français).
- Points forts : parité FR/EN parfaite (521/521), 3ᵉ langue possible en déposant `lang/<langue>.php` (mais non documenté).
- **Interface admin 100 % française** (23 configoptions, boutons, métriques, logs) — principal obstacle à une diffusion hors francophonie (voir Partie II).

## 2.8 Sécurité (delta — l'audit complet antérieur reste valide)

- ✅ Les 3 durcissements v1.2.2 sont **présents et corrects** (avertissement HTTP dans le constructeur avec garde par hôte ; `JSON_HEX_APOS|JSON_HEX_QUOT` ; docblock `$verifySsl`).
- 🟡 **`refreshdns` sans rate-limit** : un client authentifié peut forcer en boucle ~7 lookups DNS + un login SA complet par appel. Pas une faille (CSRF requis, domaine forcé au service), mais un manque de garde-fou de disponibilité — recoupe le constat DNS §2.4. *Correctif : cooldown 15-30 s/service en session.*
- 🟡 Le log CSRF écrit un **préfixe de 8 hex du token attendu** en clair dans le fallback manuel (smartermail.php:787-788) — fuite mineure, à remplacer par présent/vide/longueur.
- Le code récent (alias de domaine, DNS) est propre : regex strictes, urlencode, `|escape`/`textContent`.

## 2.9 Constat réfuté (transparence de la méthode)

Un constat de sévérité moyenne — *« la Phase 1 dépendrait de l'ordre WHMCS incrément-de-nextduedate vs hook »* — a été **RÉFUTÉ** en contre-vérification : WHMCS avance `nextinvoicedate` à la génération et `nextduedate` **au paiement** (comportement stable WHMCS 6→9, structurellement requis par la suspension automatique). La période calculée au moment du hook est donc identique à celle de l'activation. Le constat est retiré ; seul subsiste le cas limite du paiement tardif décalant `period_start` (couvert par le correctif de rollover du §2.2).

---

# 3. Partie II — Flexibilité multi-entreprise

## 3.1 Inventaire de ce qui est spécifique à Astral / codé en dur

**Vérifié dans le code (fichier:ligne) :**

| Zone | Détail | Localisation |
|---|---|---|
| **Nameservers Astral** | Détection de l'onglet DNS via `ns3/ns4.astralinternet.com`, `ns20/ns21`, `zone1-3`, `hosting-management.com` — tout autre hébergeur tombe sur « generic » | smartermail.php:2913-2921 |
| **Onglet « Espace client (Astral Internet) »** | Décrit le portail d'Astral (« Mes Domaines », « Gestion de la Zone DNS ») — affiché à tous | french.php:573-589, english.php:570 |
| **Exemples astralinternet.com** | Descriptions de configoption19/20/22 | smartermail.php:484, 496, 526 |
| **Passerelles de paiement** | Seul Authorize.net mappé → « Carte de crédit (Visa/Mastercard) » | smartermail.php:2277-2281 |
| **Devise** | `$` en dur ×19 templates + libellés de facture ; `tblpricing where currency=1` + colonne `monthly` figées pour l'estimé client | clientarea.tpl:611…, smartermail.php:2302-2307 |
| **Langue** | Fallback facture `french` ; admin 100 % FR (23 configoptions, MetaData, métriques, bouton admin, ~82 logActivity) | hooks.php:159/177, smartermail.php:196-541 |
| **Branding** | `#3949ab` ×55 dans 6 fichiers, zéro variable CSS ; dark mode verrouillé sur `html.lagom-dark-mode` (×183) | templates/* |
| **Défauts produits** | `C:\SmarterMail\Domains\`, `include:mail.example.com`, hostname `mail.` figé | smartermail.php:254, 335, 1474 |

## 3.2 Limites de forfait — état réel (vérifié)

C'est le point clé pour votre question « forfaits avec X d'espace maximal » :

- **Quota disque du domaine : jamais bloquant.** `maxSize` est **forcé à 0 (illimité)** à la création (smartermail.php:1476 — « notre facturation gère ça ») ; `aliasLimit`/`listLimit` aussi à 0. Le wrapper **supporte pourtant** `maxSize`/`userLimit` (createDomain :746-765, setDomainSettings :790-791) — l'infrastructure existe, elle n'est simplement jamais utilisée. Un modèle « quota dur » est aujourd'hui impossible sans modifier le code.
- **Max utilisateurs (configoption7) : à moitié appliqué.** Poussé comme `userLimit` à la création (SmarterMail le fait respecter côté serveur), mais **aucune garde PHP** dans `createuser` (le client reçoit une erreur générique au lieu d'un message de quota) et **aucune fonction `smartermail_ChangePackage`** : modifier l'option après création n'a aucun effet sur les domaines existants.
- **Taille par boîte : libre** (0 à 1 To, cap en dur), aucune option « taille max/défaut par boîte ».
- **Contrainte structurelle** : 23 des 24 slots configoption sont consommés → les extensions multi-réglages devront passer par une table `mod_sm_product_settings` (+ page addon), pas par de nouvelles configoptions.

## 3.3 Feuille de route produit priorisée

### Priorité HAUTE

| Proposition | Effort | Design résumé |
|---|---|---|
| **1. Forfaits à quota maximal bloquant** | Moyen | Table `mod_sm_product_settings` (quota_gb, `overage_mode` ∈ block/bill/notify). CreateAccount pousse `maxSize=quota_gb×1024³` (champ déjà supporté) ; le hook facture selon le mode : *block* = prix fixe sans tranches, *bill* = tranches au-delà du quota inclus, *notify* = actuel + alerte. `UsageUpdate` retourne `disklimit=quota` (jauge WHMCS native) ; jauge + bannière ≥ 90 % dans clientarea. Quota=0 conserve le comportement actuel — les deux modèles coexistent. |
| **2. `smartermail_ChangePackage`** | Faible-moyen | Nouvelle fonction appelant `setDomainSettings(userLimit, maxSize, outgoingIP)` — active les upgrades/downgrades natifs WHMCS (les 2 concurrents l'offrent ; aujourd'hui un changement de forfait n'a aucun effet serveur). |
| **3. Limites réellement appliquées** | Faible | Garde PHP configoption7 dans `createuser` (compter via `getDomainData→userCount`) + message localisé « limite atteinte, mettez à niveau » + bouton Ajouter désactivé avec compteur « X/N boîtes » ; taille par défaut/max par boîte ; `aliasLimit` poussé. C'est ce qui rend crédibles des grilles Bronze/Argent/Or. |
| **4. Adoption de domaines existants** | Moyen | **Bloquant commercial n°1** pour tout acheteur déjà équipé : CreateAccount refuse tout domaine existant (smartermail.php:1389). Mode « adopter » : créer/réutiliser l'admin secret sans setDomainSettings destructif + bouton admin + outil de rapprochement en masse (domaines SM orphelins ↔ services WHMCS) + `syncprotousage` existant pour amorcer la facturation. |
| **5. Modèles de facturation sélectionnables** | Moyen | `billing_model` ∈ flat / tiers (actuel) / per_mailbox / hybrid (Go+boîtes inclus, excédents facturés). Le hook branche après lecture du service ; miroir obligatoire dans l'estimé du dashboard. Prérequis : corriger `currency=1` en dur. |
| **6. Neutraliser la marque Astral** | Trivial-moyen | Exemples → `votre-serveur.com` ; NS→onglets configurables (3 configoptions CSV ou JSON, onglet masqué si vide) ; onglet portail renommé/configurable ; overrides `lang/overrides/{lang}.php` fusionnés par `array_merge`. |

### Priorité MOYENNE

- **Courriel de bienvenue avec les infos DNS** (quick win — zéro courriel envoyé aujourd'hui) : gabarit WHMCS « SmarterMail Welcome » + `localAPI('SendEmail')` en fin de CreateAccount avec spf_record, autodiscover, webmail_url, DKIM, DMARC suggéré — toutes les valeurs sont déjà calculées pour le dashboard. Option 2 : bouton client « M'envoyer le guide DNS ».
- **Vrai SSO webmail** : le bouton actuel est un simple lien statique (le client ressaisit ses identifiants). Implémenter `smartermail_ServiceSingleSignOn` + auto-login par jeton (`getAutoLoginUrl()` existe déjà dans le wrapper, jamais branché) — par domaine et par boîte. Le module officiel SmarterTools l'offre.
- **`smartermail_LoginLink` admin** (annoncé dans l'en-tête du fichier mais inexistant) + `AdminServicesTabFields` (usage disque, nb boîtes, EAS/MAPI dans l'onglet admin).
- **Interface admin en anglais** (langue pivot WHMCS) : 23 configoptions, MetaData, métriques, boutons — sans cela, aucun hébergeur non francophone ne peut administrer le produit.
- **Formatage monétaire par devise du client** (`formatCurrency`/tblcurrencies) + facture dans la **langue du client** (`tblinvoices.userid → tblclients.language` dans `_sm_hookLang`).
- **Mapping passerelles piloté par les données** : `$lang['pm_'.$slug] ?? displayName ?? ucfirst($slug)` — chaque hébergeur ajoute `pm_stripe`, `pm_paypal` dans ses fichiers de langue.
- **Fonctionnalités SmarterMail non exposées**, par valeur : listes de diffusion (les 2 concurrents les offrent ; patron des redirections réutilisable), quotas par défaut des boîtes (`user-defaults`), catch-all (très demandé), anti-spam par domaine (lecture d'abord — **aucun concurrent ne l'offre**, différenciateur), archivage (argument conformité).

### Priorité BASSE

- Rapports/alertes admin (dépassement à 90 %, rapport mensuel, réconciliation domaines SM ↔ services WHMCS — le squelette DailyCronJob le prévoit déjà en commentaire).
- Statut/dernière connexion par boîte + toggle enable/disable (parité ModulesGarden).
- Métriques facturables étendues (mailboxes, aliases, domain_aliases) — offres « à la boîte » sans nouvelle infrastructure.
- Dark mode indépendant de Lagom (`prefers-color-scheme` + classe configurable).
- Préfixe hostname configurable (`mail.` → `smtp.`/`courriel.`).

## 3.4 Veille concurrentielle (recherche web, sources vérifiées)

- **ModulesGarden « SmarterMail Email For WHMCS » : n'est plus au catalogue** (page produit en 404, wiki derrière login — comportement typique de leurs produits en fin de vie). Leur référence email actuelle est *Zimbra & Carbonio Email For WHMCS* (129,95 $/an, 382 clients), utilisée comme proxy de fonctionnalités.
- **Seul concurrent direct actif : le module officiel SmarterTools** (WHMCS Marketplace #4239, **gratuit**, open source, MAJ 2024-07, compatible WHMCS 8.0-8.7). Offre : plans avec limites (taille compte, disque domaine), options configurables vendables (users, mailbox size, domain size, aliases, EAS, MAPI, Exchange), upgrade/downgrade, mailing lists, **vrai SSO webmail**. Manques signalés par sa communauté : paliers fixes difficiles, pas d'anti-spam, bugs de suppression de domaine.
- **Positionnement de notre module** : nettement plus riche sur le DNS (dashboard SPF/DKIM/DMARC/Autodiscover + générateur DMARC — **aucun concurrent ne l'a**), la facturation fine (tranches Go + EAS/MAPI à seuil avec machine d'états — unique sur ce créneau), le bilinguisme et le dark mode. **Manques de parité** : ChangePackage/options configurables, vrai SSO, mailing lists, LoginLink admin, adoption de domaines.

---

# 4. Partie III — Fluidité client, CSS et GUI

## 4.1 État des lieux chiffré (mesuré sur les 10 templates, 5 940 lignes)

| Indicateur | Mesure |
|---|---|
| CSS inline | **854 lignes** dans 5 blocs `<style>` (clientarea 496, edituser 132, adduser 107, editredirect 60, addredirect 59) |
| CSS dupliqué | **98 lignes sur 619 uniques** présentes dans 2 à 5 templates (kit modal ×5, `.sm-btn-*` ×5, `@keyframes smFadeIn` ×5, pills ×4) — avec dérive amorcée (`.sm-mbox` 420 vs 680px, `.sm-btn-cancel` 3 variantes, `.sm-btn-add` indigo vs vert) |
| JS inline | **~1 571 lignes** ; **~30 des 83 fonctions vivantes sont des copies** (smOpen/smClose/smBg ×5 en 4 variantes, widget mot de passe ×3, escHtml/escAttr ×4, helpers alias/fwd ×2) |
| Couleurs | **96 hex distinctes, 918 occurrences, zéro variable CSS** — 3 rouges, 4 verts, 4 oranges concurrents pour les mêmes rôles ; bouton webmail `#0080c4` hors palette |
| `style=""` inline | 180 attributs (127 dans clientarea), dont 9× le même style d'input complet |
| Dark mode | 277 lignes / 183 sélecteurs `html.lagom-dark-mode` redéfinissant chaque couleur en dur |
| `!important` | Seulement 7 (excellent) |
| Breakpoints | 560/600/767 px mélangés dans la même page ; **zéro** media query dans addredirect/editredirect |
| Accessibilité | **37/44 labels non associés**, **23 modales sans role/aria-modal/focus-trap**, Échap absent dans adduser/edituser, 12 `outline:none`, contrastes 2:1-2,8:1 sur boutons « non prêts »/placeholders, typo 100 % px (17× 10px) |
| Templates morts | **222 lignes** : pwdmodal.tpl, pwdwidget.tpl, _dns_copy_field.tpl — jamais inclus, mais des commentaires vivants pointent dessus comme source de vérité |

### 🐛 Bug réel découvert (conséquence directe de la duplication) — CONFIRMÉ

**[edituser.tpl:552-565](modules/servers/smartermail/templates/edituser.tpl#L552)** : la copie de `smGeneratePwd()` d'edituser **ne remplit pas le champ de confirmation** (le correctif `fc.value = pwd` existe dans adduser.tpl:487-489 et dans pwdmodal.tpl — fichier mort — mais pas dans la copie vivante). Résultat : dans la modale « Changer le mot de passe », cliquer « Générer » laisse le bouton de soumission **bloqué** ; le client doit recopier manuellement le mot de passe généré. *Correctif immédiat : 2 lignes.*

## 4.2 Plan de standardisation CSS — 5 chantiers

1. **Extraction vers `templates/_sm_styles.css` unique** (Haute/Moyen) — injecté par le **hook `ClientAreaHeadOutput` existant** (même mécanisme éprouvé que `_sm_dark_mode.css`, qui couvre déjà les 5 pages puisqu'elles passent par `action=productdetails`). 854 → ~450-500 lignes après déduplication. Les divergences deviennent des variantes explicites (`.sm-mbox--lg`). Ordre de cascade favorable (préfixe `sm-`, jamais ciblé par les thèmes) ; conserver les 7 `!important` existants ; plan de test : 5 pages × Twenty-One clair × Lagom clair/sombre.
2. **Design tokens `--sm-*`** (Haute/Faible, même commit) — ~25 variables : `--sm-accent:#3949ab`, `--sm-success:#2e7d32` (unifie 4 verts), `--sm-danger:#c62828` (unifie 3 rouges), `--sm-warn:#e65100`, neutres (`--sm-surface/-alt`, `--sm-border`, `--sm-text/-2/-3`), géométrie (`--sm-radius`, `--sm-gap`), typo (`--sm-fs/-sm/-xs`). Bonus : `class="sm-page--green"` sur les pages redirect redéfinit 3 tokens et supprime ~15 règles dupliquées.
3. **Dark mode par redéfinition de tokens** (Moyenne/Moyen) — le bloc `html.lagom-dark-mode{...}` redéfinit ~25 variables au lieu de 183 sélecteurs : **277 → ~60-80 lignes**, synchronisation clair/sombre automatique. Extensible à d'autres thèmes en ajoutant un sélecteur.
4. **Convention de nommage** (Moyenne/Faible) — conserver `sm-` (pas de renommage massif) ; normaliser : états `.is-*` (au lieu de `.active`/`.open`/`.ready` — collisions possibles avec les JS Bootstrap des thèmes), modificateurs `--*` ; fusionner les doublons (`.sm-btn-delete`→`.sm-btn-del`, `.sm-info-btn`→`.sm-help-i`, `.sm-record-wrap`→`.sm-dns-copy-wrap`).
5. **Accent white-label** (Basse/Faible) — configoption « Couleur d'accent » validée `^#[0-9a-fA-F]{3,6}$`, injectée en `:root{--sm-accent:...}` par le hook (avec `color-mix` pour les dérivés). Corriger au passage le bouton webmail hors palette.

**+ `_sm_common.js`** : kit modal (smOpen/smClose/smBg + Échap + focus-trap), escHtml/escAttr, widget mot de passe unique — supprime ~30 copies et la classe de bugs du §4.1. **+ purge des 3 templates morts** (222 lignes) et des commentaires trompeurs qui pointent dessus.

## 4.3 Simplification GUI — constats de parcours et propositions

**Parcours mesurés** : créer une boîte = 2 écrans + 1 modale obligatoire (~6 clics minimum, +3 interactions par alias) ; DNS = **11 surfaces** (4 pills + 4 mini-cartes + 6 modales dont 2 chaînées + guide à 4 onglets/16 champs copiables) ; dashboard = 6 blocs + 9 modales dans une page de 3 072 lignes ; statut du service affiché 2×, facturation étalée sur 3 surfaces.

**Incohérences de patterns** : boîtes/redirections = pages, alias de domaine = modales à POST immédiat, alias de boîte = pills non persistées jusqu'à « Sauvegarder » (sans indicateur), toggle DKIM = `window.confirm()` natif au milieu de modales stylées.

### Propositions priorisées

| P | Proposition | Détail |
|---|---|---|
| 🔴 | **Préserver la saisie après erreur serveur** | Aujourd'hui toute erreur POST rend `error.tpl` pleine page : **le client perd tout** (nom, alias, options). Mapper chaque action vers sa page d'origine et ré-afficher le formulaire pré-rempli (`'old' => $_POST` assaini, jamais le mot de passe) + bandeau d'erreur. Retirer le préfixe technique `[createuser]`. |
| 🔴 | **Message de succès (flash PRG)** | Aucun feedback après action réussie. `&smmsg=<action>` whitelisté → bandeau vert auto-effaçant + ~10 clés de langue. Effort faible. |
| 🔴 | **Saisie inline des alias/redirections** | Remplacer les micro-modales à 1 champ par une rangée inline (input + `@domaine` + bouton [+], Entrée = ajout, focus conservé) : 1 interaction par élément au lieu de 3. Le JS existant est conservé. |
| 🔴 | **Panneau DNS unifié** | Une modale « Enregistrements à créer » (tableau Type/Hôte/Valeur copiable/État, ancres par section, générateur DMARC en accordéon) au lieu de 4 modales de détail + résumé « 3/4 vérifications OK » sur la carte repliée. Supprime ~250 lignes. |
| 🟠 | **Widget mot de passe dans la page adduser** | Supprime la modale obligatoire + le champ caché + l'état ambigu du bouton « gris mais cliquable » ; parcours 2 écrans → 1. |
| 🟠 | **Alléger le dashboard** | Fusion Stats+Service en carte « Aperçu » 2 colonnes, webmail dans l'en-tête, détail par protocole relégué à la modale (il y est déjà), suppression du statut dupliqué : 6 blocs → 5, première vue ~-30 %. |
| 🟠 | **Unifier confirmations/persistance** | DKIM → modale stylée comme les autres ; badge « Modifications non enregistrées » sur les pills d'edituser ; `beforeunload` étendu à adduser/addredirect. |
| 🟠 | **3 casses mobiles < 600 px** (~15 lignes CSS) | `.sm-toolbar` sans flex-wrap (déborde < 430 px), en-tête carte DNS sans wrap (375 px), grilles inline du générateur DMARC insurchargeables par media query. |
| 🟠 | **Anti double-soumission + spinner** | Aucun bouton n'est désactivé pendant le POST alors que `createuser` enchaîne plusieurs appels API — helper `smLockSubmit()` commun. |
| 🟡 | **Assistant première configuration DNS** | Bandeau « étape 1/3 » quand ≥ 2 vérifications rouges, chaque étape = record à copier + bouton « J'ai ajouté » qui déclenche le refresh AJAX existant. |
| 🟡 | **Table des adresses : compteurs cliquables** | Colonnes alias/redirections → badges « 3 alias » dépliables : rangées ~-60 % de hauteur, lisible sur mobile. |
| 🟡 | Internationaliser les 6 chaînes FR en dur des templates | Dont 2 paragraphes EAS/MAPI d'edituser redondants avec les clés existantes. |

---

# 5. Plan d'action global priorisé

## P0 — Correctifs financiers (immédiats, faible effort)

1. Déplacer `_sm_markEntriesAsBilled` **après** le succès d'`UpdateInvoice` + alerte admin en échec — *quelques lignes, élimine la perte définitive*.
2. Détection des erreurs métier HTTP 200 dans `request()` + test du retour de `set*Enabled` avant enregistrement facturable + garde `json_last_error`.
3. MetricsProvider : ne plus émettre de zéros sur échec ; hook : refuser `disk_gb` nul/périmé.
4. Réduire les logs de succès (mode debug / `logModuleCall`) — pour que les échecs financiers redeviennent **visibles**.
5. Bug `smGeneratePwd` d'edituser (2 lignes).

## P1 — Robustesse structurelle (court terme)

6. **Rollover des lignes proto_usage** à la nouvelle période après facturation réussie (corrige récurrence, dédup, désactivation héritée) + dédup email+protocole + conservation des lignes deleted + hook `InvoiceCancelled`.
7. Retry/backoff dans `request()` + `logModuleCall` + cache de tokens par hôte + timeouts configurables.
8. DNS : statut `unknown` (échec résolveur ≠ absent, non caché) + cooldown refreshdns + IDN + NS via cache.
9. TerminateAccount : purge directe par serviceid + 404 idempotent ; UsageUpdate : détection domaine absent.
10. `_sm_validateMailboxPassword()` unique (politique serveur non contournable) + normalisation username uniforme.

## P2 — Produit multi-entreprise (moyen terme)

11. Quota bloquant + ChangePackage + limites appliquées (§3.3 n°1-3) — le socle « forfaits ».
12. Adoption de domaines existants (déverrouille le marché installé).
13. Neutralisation marque/devise/langue admin (§3.3 n°6 + admin EN + formatCurrency + langue client sur factures).
14. Courriel de bienvenue + vrai SSO webmail + LoginLink (parité concurrentielle).

## P3 — UX/CSS (peut avancer en parallèle de P2)

15. `_sm_styles.css` + tokens + dark mode par variables + `_sm_common.js` + purge des templates morts (§4.2).
16. Préservation de saisie + flash de succès + saisie inline + panneau DNS unifié (§4.3).
17. Passe accessibilité groupée (labels, modales ARIA, focus, contrastes) + mobiles.

## Maintenance documentaire

18. Réécrire `modules/README.md` (structure réelle, hook `InvoiceCreation`, 23 options, WHMCS 9) ; régénérer l'index d'en-tête de smartermail.php (8 fonctions fantômes) ; corriger les commentaires erronés relevés (cache « 5 min », docblocks usage/tenantUsage, `N = 1 à 8`).

---

*Rapport généré par audit multi-agents (67 agents, 8 dimensions, contre-vérification adversariale à 2 vérificateurs par défaut majeur). Chaque constat cité a été vérifié dans le code à la ligne près ; le seul constat non confirmé a été retiré et documenté en §2.9. Les audits précédents (sécurité v1.2.2) restent valides et ne sont pas dupliqués ici.*
