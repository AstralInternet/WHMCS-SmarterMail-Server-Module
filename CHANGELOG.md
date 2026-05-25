# Changelog

Toutes les modifications notables de ce module sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le
versionnement respecte [Semantic Versioning](https://semver.org/lang/fr/) :

- **MAJEUR** — changement incompatible avec la version précédente (refonte DB,
  rupture d'API publique, suppression de fonctionnalité).
- **MINEUR** — nouvelle fonctionnalité rétrocompatible.
- **CORRECTIF** — correction de bug ou de sécurité, sans changement de comportement.

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
