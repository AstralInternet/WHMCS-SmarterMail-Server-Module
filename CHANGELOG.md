# Changelog

Toutes les modifications notables de ce module sont documentées dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le
versionnement respecte [Semantic Versioning](https://semver.org/lang/fr/) :

- **MAJEUR** — changement incompatible avec la version précédente (refonte DB,
  rupture d'API publique, suppression de fonctionnalité).
- **MINEUR** — nouvelle fonctionnalité rétrocompatible.
- **CORRECTIF** — correction de bug ou de sécurité, sans changement de comportement.

## [1.21.0] - 2026-07-08

### Modifié — facturation sur les forfaits : hook + estimé client — P4b

La **génération de factures** (hook `InvoiceCreation`) et l'**estimé du tableau de bord
client** lisent désormais la config résolue. **Byte-identique** pour les produits sans
forfait (garanti par `diag_packages.php`).

⚠️ **À valider en bac-à-sable avant production** — factures WHMCS 9.0 immuables :
générez une facture pour un produit lié à un forfait ET pour un produit hérité, puis
comparez les lignes / montants avant-après.

- **Hook `InvoiceCreation`** : le SELECT expose `configoption24` ; prix EAS/MAPI/combiné,
  seuil de facturation, Go/tranche et sous-forme disque (→ `_sm_computeBaseCharge`)
  proviennent du forfait résolu (`_sm_packageFromServiceRow()`). Planchers `max(0)` / `max(1)`
  conservés à l'identique. Le resolver ne lève jamais → repli hérité → défauts (la
  facturation ne casse jamais).
- **Estimé du tableau de bord** : Go/tranche, prix, seuil, quota, offres EAS/MAPI tirés du
  forfait → l'estimé correspond exactement à la facture, pour un forfait comme en hérité.

**Reste sur l'ancien chemin (→ P5)**, sans impact tant qu'aucun forfait ne diverge des
options du produit : le minutage d'enregistrement `proto_usage` (`configoption16` à
l'ajout / édition / suppression de boîte), l'affichage des prix EAS/MAPI des pages
d'ajout / édition de boîte, et l'*enforcement* des règles de mot de passe.

## [1.20.0] - 2026-07-08

### Modifié — provisioning serveur sur les forfaits — P4a

Le provisioning SmarterMail lit désormais la config résolue (`_sm_packageFromParams()`)
au lieu des configoptions brutes. **Byte-identique** pour les produits sans forfait.

- **CreateAccount** : chemin des domaines, IP de sortie, `userLimit`, `maxSize`
  (quota bloquant) et activation EAS/MAPI proviennent du forfait résolu.
- **ChangePackage** : idem (`userLimit` / `maxSize` / `outgoingIP` re-poussés) + le refus
  « quota bloqué < usage courant » utilise le quota du forfait.
- **UsageUpdate** : la jauge disque native WHMCS (`disklimit`) reflète le quota résolu.

Un produit lié à un forfait provisionne donc selon le forfait. La **facturation**
(hook InvoiceCreation) reste sur l'ancien chemin jusqu'à P4b.

## [1.19.0] - 2026-07-08

### Modifié — bascule des consommateurs d'affichage sur les forfaits — P3

Première bascule réelle vers le resolver de forfaits, limitée aux consommateurs
**sans impact sur la facturation ni le provisioning** (affichage / vérifications
DNS). Byte-identique pour les produits sans forfait (garanti par `diag_packages.php`).

- **Vérifications DNS** (`lib/SmarterMailDnsCheck.php`) : SPF (primaire + secondaires),
  Autodiscover (hôte + cible SRV) et DMARC (activation) lisent désormais la config
  résolue (`_sm_packageFromParams()`) au lieu des configoptions brutes.
- **Espace client** : les défauts du générateur DMARC (RUA + politique) et les
  critères de mot de passe affichés (adduserpage + edituserpage) passent par le
  resolver.
- **Nameservers du guide DNS** : `_sm_providerNameservers()` lit le réglage global
  `provider_nameservers` (page addon → Réglages globaux) avec repli sur les valeurs
  par défaut (`_sm_defaultNameservers()`). Le dé-branding des NS se fait donc sans
  toucher au code.

Un produit lié à un forfait voit désormais ses **réglages DNS / mot de passe (affichés)
issus du forfait**. Provisioning et facturation restent sur l'ancien chemin jusqu'à P4.

## [1.18.0] - 2026-07-08

### Ajouté — GUI du gestionnaire de forfaits (packages) — P2

L'addon « SmarterMail — Forfaits » (anciennement « Facturation & Quotas ») devient
une interface à onglets pour créer et gérer les forfaits. **Toujours sans brancher
les consommateurs** : provisioning, facturation et espace client restent inchangés
tant qu'aucun produit ne pointe vers un forfait.

- **Liste des forfaits** : tableau (nom, type, quota, boîte max, nombre de produits
  liés) + création / modification / suppression (soft-delete, avec avertissement si
  le forfait est encore utilisé par des produits).
- **Éditeur à onglets** (Bootstrap) : Général (protocoles EAS/MAPI, prix, seuil,
  serveur, boîtes, alias, résiliation) · Mot de passe · DNS (SPF / autodiscover /
  SRV / DMARC) · Disque & facturation (type de forfait, quota, tranche, taille max
  par boîte, excédent, seuil). Le « type de forfait » (à l'usage / excédent / bloqué)
  est une projection de `overage_mode` ; `_sm_computeBaseCharge` reste intact.
- **Réglages globaux** : disponibilité EAS/MAPI du serveur (masque la section EAS/MAPI
  des forfaits si désactivée) + nameservers du guide DNS — stockés dans `mod_sm_settings`.
- **Panneau produit « hérité » conservé** (onglet dédié) pour les produits non convertis.
- Helpers CRUD `_sm_savePackage()` / `_sm_deletePackage()` / `_sm_listPackages()` +
  `_sm_defaultNameservers()` (`lib/SmarterMailPackages.php`).

Prochaine étape : bascule progressive des consommateurs sur le resolver — P3 (affichage
espace client, DNS, nameservers globaux), puis P4 (provisioning + facture).

## [1.17.0] - 2026-07-08

### Ajouté — fondation du gestionnaire de forfaits (packages) — P0 + P1

Première étape (invisible et sans risque) du chantier « forfaits » pour la
revente : une couche de données + un pont config-option, **sans encore rien
brancher** — les produits existants restent strictement inchangés.

- **Forme normalisée + resolver + convertisseur** (`lib/SmarterMailPackages.php`,
  nouveau) : une représentation unique de la config d'un produit, résolue soit
  depuis un **forfait nommé** (table `mod_sm_packages`, référencé par
  `configoption24`), soit — à défaut — **convertie depuis les 23 configoptions
  historiques + `mod_sm_product_settings`** de façon **byte-identique**. Le
  resolver ne lève jamais d'exception (forfait → hérité → défauts). Valeurs
  brutes pour les champs à plancher divergent (`gb_per_tier`,
  `billing_threshold_days`) : chaque site d'appel conserve son propre plancher.
- **Tables** `mod_sm_packages` (forfaits, avec soft-delete) et `mod_sm_settings`
  (réglages globaux clé/valeur JSON) — auto-créées à la demande, non bloquantes.
- **`configoption24` « Forfait »** : nouveau dropdown (dernier slot, 24/24) peuplé
  depuis `mod_sm_packages`. Vide = « (Hérité) » = comportement historique. En cas
  d'absence de table / d'erreur, seule l'option héritée s'affiche (la page de
  configuration produit ne casse jamais).
- **Harnais byte-identique** (`tools/diag_packages.php`, lecture seule) : vérifie
  pour chaque produit que le convertisseur reproduit EXACTEMENT chaque expression
  consommateur (défaut + cast + plancher module ET hook). Garde-fou à lancer avant
  de brancher les consommateurs.

**Aucun consommateur ne lit encore les forfaits** — provisioning, facturation et
espace client sont inchangés. Prochaines étapes : GUI à onglets (P2), puis bascule
progressive des consommateurs (P3 affichage, P4 provisioning/facture).

## [1.16.0] - 2026-07-08

### Modifié — factures dans la langue & la devise du client (Phase 2, généricité)

Suite du chantier revente : les factures générées par le module s'adaptent
désormais au **client**, plus seulement à la configuration système. **Sans
changer les montants** facturés en mono-devise (cas d'Astral : identique).

- **Langue de la facture** : les libellés ajoutés par le hook `InvoiceCreation`
  (détail d'utilisation disque, en-têtes EAS/MAPI, dépassement de quota, mention
  « désactivé le… ») sont rendus dans la **langue du client propriétaire** de la
  facture (`tblclients.language`) au lieu de la langue système. Repli propre sur
  la langue système si le client n'a pas de langue définie. Nouveaux helpers
  `_sm_loadLangArray()` (chargeur mutualisé, cache par langue, chemin sécurisé)
  et `_sm_invoiceLang()`.
- **Symbole de devise sur la facture** : le `$` codé en dur dans les libellés de
  prix (« × $6 ») est remplacé par le **symbole de la devise du client**
  (`_sm_hookCurrencySymbol()`). Les *montants* des lignes étaient déjà dans la
  bonne devise (gérés par WHMCS) ; seul le symbole du texte était figé.
- **Estimé du tableau de bord** : correction du tarif produit lu en dur dans la
  devise 1 (`currency=1`) → lecture dans la **devise du client**
  (`_sm_clientCurrencyId()`), avec repli sur la devise par défaut si le produit
  n'a pas de tarif dans cette devise. L'estimé affiché correspond enfin à la
  facture pour un client multidevise.

**Limitation connue** (chantier séparé) : les suppléments EAS/MAPI sont des
valeurs brutes de configoptions (`configoption2/3`), **non converties** par
devise — un revendeur multidevise doit en tenir compte. Aucune incidence en
mono-devise.

## [1.15.0] - 2026-07-08

### Modifié — devise & dé-branding pour la revente (Phase 2, généricité)

Premiers pas vers un module revendable à d'autres hébergeurs, **sans changer le
provisioning**.

- **Devise dynamique** : tous les prix affichés dans l'espace client (estimé du
  tableau de bord, coûts par protocole, détail de facturation, estimés
  d'ajout / édition de boîte) utilisent désormais le **symbole de la devise du
  client** (via WHMCS ; repli sur la devise par défaut puis `$`) au lieu du `$`
  codé en dur — helper `_sm_currencySymbol()` + global JS `SM_CURRENCY`. Le
  suffixe « /mois » réutilise la clé i18n existante `per_month`.
- **Dé-branding** : les nameservers qui pré-sélectionnent l'onglet du guide DNS
  sont extraits dans un helper **documenté et personnalisable**
  (`_sm_providerNameservers()`) — un revendeur y renseigne SES nameservers,
  sinon onglet « générique ». Exemples de configuration `astralinternet.com`
  remplacés par `example.com`.

*Reste de la Phase 2 (chantiers séparés, plus risqués ou lourds) : adoption de
domaines existants (change le provisioning), courriel de bienvenue, LoginLink
admin, fonctions SmarterMail (catch-all, listes…), rapports admin.*

## [1.14.0] - 2026-07-08

### Ajouté — répondeur automatique / réponse d'absence par boîte (Phase 4)

Chaque boîte peut désormais configurer un **répondeur automatique** (réponse
d'absence) depuis l'espace client, via l'endpoint dédié SmarterMail
`api/v1/settings/auto-responder` (token utilisateur, obtenu par impersonification
comme la liste de redirection).

- **API** : `getAutoResponder()` / `setAutoResponder()` (`SmarterMailApi.php`) —
  `null` si le token utilisateur est indisponible (jamais de formulaire vide
  écrasant une config invisible) ; whitelist stricte des 10 champs officiels +
  merge best-effort ; constantes `AR_AUDIENCE_*`.
- **Action** `saveautoresponder` (séparée de saveuser) + handler
  `smartermail_saveautoresponder()` : validation (sujet ≤ 200, corps/externe
  ≤ 20 000, audience 0-2, sujet+corps requis si activé, plage de dates
  début < fin), dates normalisées en UTC, repli corps → réponse externe.
  PRG + flash de succès.
- **UI** (edituser) : carte d'état (Actif / Programmé / Désactivé + aperçu) et
  modale de configuration (sujet, message texte, plage de dates saisie en heure
  locale → convertie en ISO UTC, destinataires externes, courrier direct
  uniquement). Mode dégradé si le répondeur est inaccessible. Accessibilité et
  anti double-soumission hérités du kit modale.
- **i18n** : 28 clés `ar_*` FR/EN (parité stricte). Stratégie texte brut
  (avertissement si un message HTML existant est détecté).

⚠️ Le mapping `externalAudience` (0 = personne / 1 = contacts / 2 = tout le
monde) est déduit de l'enum OOF Exchange — **à confirmer sur un serveur de test**
avant mise en production (centralisé dans `AR_AUDIENCE_*`).

## [1.13.0] - 2026-07-08

### Ajouté — accessibilité : modales + labels de formulaire (Piste B, ♿)

Toutes les modales du module sont désormais correctement exposées au clavier et
aux technologies d'assistance, via le kit modale partagé (`_sm_common.js`) — sans
modifier chaque modale une par une :

- **ARIA** : la boîte de dialogue reçoit `role="dialog"`, `aria-modal="true"` et
  `aria-labelledby` (vers son titre `<h4>`), posés automatiquement à l'ouverture.
- **Piège de focus** : tant qu'une modale est ouverte, la tabulation (Tab /
  Maj+Tab) **cycle** à l'intérieur de la modale au lieu de partir vers la page.
- **Restauration du focus** : à la fermeture, le focus revient sur l'élément qui
  avait ouvert la modale. Le focus initial va au premier champ éditable, sinon au
  premier élément focusable.
- **Labels de formulaire associés** (`for` / `id`) : champs de création et
  d'édition de boîte (nom d'utilisateur, mot de passe, confirmation, taille) et
  de redirection (nom d'alias) ; `aria-label` ajouté aux nouveaux champs de
  saisie inline (alias / redirections / cibles).
- **Labels des enregistrements DNS copiables** (DKIM / SPF) et de **tous les
  champs du générateur DMARC** associés (`for` / `id`) — 14 champs.

*Reste de la passe accessibilité : revue des contrastes (clair + sombre) —
nécessite un rendu visuel de ta part.*

## [1.12.0] - 2026-07-08

### Modifié — saisie inline des alias / redirections (Piste B, UX)

Ajouter un alias, une redirection de boîte ou une destination de redirection ne
passe plus par une **micro-modale** : le champ de saisie est désormais
**directement dans la carte**, sous les pastilles. On tape et on valide (bouton +
ou touche Entrée) sans ouvrir de fenêtre.

- **6 micro-modales supprimées** (edituser : alias + redirection ; adduser :
  idem ; addredirect + editredirect : destination), remplacées par un composant
  partagé `.sm-inline-add` (champ + suffixe @domaine éventuel + bouton +).
- Les fonctions partagées `smAddAlias` / `smAddFwd` / `smAddTarget` sont
  **inchangées** (elles lisent le même id d'input ; leur `smClose` devient un
  no-op inoffensif en l'absence de modale).
- addredirect : les renvois JS qui ouvraient la modale (Entrée sur le nom
  d'alias, validation « aucune destination ») **focalisent** désormais le champ
  inline. Styles `.sm-inline-*` (mode sombre géré).

## [1.11.0] - 2026-07-08

### Modifié — panneau DNS unifié : 1 modale au lieu de 4 (Piste B, UX)

Les 4 modales de détail d'enregistrement DNS (SPF, DKIM, DMARC, Autodiscover)
sont fusionnées en **une seule modale « Enregistrements DNS »**, avec une
**section par enregistrement** (champs Host/Value copiables inchangés). Les
boutons « détails » des mini-cartes ouvrent cette modale et **défilent** jusqu'à
la section concernée (brève surbrillance). Le guide DNS à onglets et le
générateur DMARC restent des modales distinctes (usages différents).

- Nouveau helper `smDnsRecords('<clé>')` : ouvre `sm-dns-records-modal` et
  `scrollIntoView` vers `#sm-dns-rec-<clé>`.
- Contenu des enregistrements (champs copiables, alertes, états) **préservé à
  l'identique** — seules les enveloppes de modale redondantes (en-tête / pied)
  ont été retirées. Le bouton « Générateur DMARC » est déplacé dans la section
  DMARC.
- Nouvelle clé i18n `dns_records_title` (FR/EN) ; styles `.sm-dns-rec-*` (mode
  sombre géré). Structure vérifiée (équilibre `{if}` / `<div>` / `<section>`
  inchangé vs avant remaniement).

## [1.10.0] - 2026-07-08

### Modifié — widget mot de passe inline à la création de boîte (Piste B, UX)

À la création d'une adresse courriel, le widget mot de passe (champ + afficher /
générer + barre de force + critères + confirmation) est désormais **directement
dans le formulaire**, au lieu d'une **modale obligatoire** à ouvrir avant de
pouvoir créer. Le bouton « Créer » passe au vert dès que le nom d'utilisateur ET
le mot de passe sont valides.

- Modale « Définir le mot de passe » + bouton/statut associés **supprimés** ; le
  champ mot de passe (`name="password"`) est soumis directement (plus de champ
  caché intermédiaire ni de `smApplyPwd` / `smPwdSet`).
- Nouveau `smUpdateCreate()` : revalide (via `smCheckPwd` partagé) et bascule
  l'état « prêt » du bouton Créer à chaque frappe (mot de passe, confirmation, nom
  d'utilisateur) et après « Générer ».
- **edituser conserve** sa modale de changement de mot de passe (action
  `savepassword` distincte et **optionnelle** — pas un point de friction).

## [1.9.1] - 2026-07-08

### Corrigé — 3 casses de mise en page mobile (< 600 px, Piste B)

- **Barre d'outils** (recherche + filtres) du tableau de bord : ajout de
  `flex-wrap` — elle débordait sous ~430 px. Sous 600 px, chaque groupe passe
  pleine largeur et le champ de recherche devient extensible.
- **En-tête de la carte DNS** : `flex-wrap` sur l'en-tête et sa partie gauche —
  le titre, les pastilles d'état et le résumé ne débordent plus vers 375 px.
- **Générateur DMARC** : les grilles de champs, jusqu'ici en styles **inline**
  (non surchargeables par media query), passent en classes `.sm-dmarc-row`
  (`.g2` / `.g3`) et s'**empilent en 1 colonne** sous 600 px.

## [1.9.0] - 2026-07-08

### Ajouté — message de succès après une action (flash PRG, Piste B, UX)

Après une action réussie (création / modification / suppression de boîte, mot de
passe, redirection, alias de domaine, DKIM), une **bannière verte de
confirmation** s'affiche à l'arrivée de la redirection Post-Redirect-Get, en
complément de la préservation de la saisie sur erreur (1.7.0).

- **`_sm_flashMessage()`** : le dispatcher ajoute `?smok=<action>` à l'URL de
  redirection ; à l'arrivée, le code d'action est relu et mappé vers un message
  **localisé** via une **whitelist stricte** (aucun contenu arbitraire affiché).
- Bannière `.sm-flash-success` (couleurs par tokens succès → mode sombre géré)
  affichée en tête du **tableau de bord** et de la **page d'édition de boîte**
  (retour après un changement de mot de passe).
- **10 clés i18n `flash_*`** FR / EN (parité stricte).

## [1.8.0] - 2026-07-08

### Ajouté — anti double-soumission des formulaires (Piste B, UX)

Les formulaires de l'espace client (création/modification de boîte, mot de passe,
suppression, redirections, alias de domaine, DKIM) ne peuvent plus être soumis
deux fois : au premier envoi, les boutons de soumission sont **désactivés et
affichent un spinner** jusqu'à la navigation. Évite les doublons de création et
les actions répétées par double-clic ou double-appui sur Entrée.

- **`smLockForm()` + verrou global** dans `_sm_common.js` : un écouteur `submit`
  verrouille tout formulaire mutatif du module (repéré par son champ caché
  `customAction`) et bloque un 2ᵉ envoi. La **validation est respectée**
  (`e.defaultPrevented` : un `onsubmit`/`onclick` qui annule n'entraîne pas de
  verrou) et la validation HTML5 native (`required`) n'est pas gênée.
- **Envois JavaScript** (edituser « Enregistrer », suppression de redirection)
  qui appellent `form.submit()` — lequel ne déclenche pas l'événement `submit` —
  verrouillent explicitement via `smLockForm()`.
- Verrou par chargement de page : après un ré-affichage (ex. erreur serveur avec
  saisie préservée), le formulaire revient déverrouillé.

## [1.7.0] - 2026-07-08

### Ajouté — préservation de la saisie après une erreur serveur (Piste B, UX)

Quand la création ou la modification d'une boîte courriel ou d'une redirection
échoue côté serveur, le client ne perd plus sa saisie : au lieu d'une **page
d'erreur pleine page**, le **formulaire d'origine est ré-affiché pré-rempli**
avec une bannière d'erreur inline. S'applique à `createuser`, `saveuser`,
`createredirect` et `saveredirect`.

- **Mécanisme** (dispatcher) : sur échec d'une action POST disposant d'un
  formulaire source, ce formulaire est re-rendu via son renderer, auquel le
  message d'erreur est transmis (`$params['__sm_formError']`) ; le renderer relit
  `$_POST` pour repeupler les champs. Le jeton **CSRF est régénéré** (re-soumission
  valide). Repli propre sur la page d'erreur si le renderer lui-même échoue (ex.
  domaine injoignable). Les suppressions/bascules conservent la page d'erreur.
- **Champs préservés** : nom d'utilisateur / d'alias, taille de boîte, protocoles
  EAS/MAPI, alias et redirections (pills), options de redirection, destinations.
  Le **mot de passe n'est volontairement pas re-rempli** (le client le re-saisit).
- **Bannière partagée** `.sm-form-error` dans `_sm_styles.css` (couleurs par
  tokens danger → mode sombre géré automatiquement).
- **Sûreté edituser** : l'état RÉEL des protocoles (champs cachés `was_eas` /
  `was_mapi` que `saveuser` compare pour détecter un changement) est préservé
  indépendamment de l'affichage pré-rempli (nouveaux vars `easWas` / `mapiWas`),
  évitant qu'un changement de protocole soit silencieusement ignoré au 2ᵉ envoi.

## [1.6.0] - 2026-07-08

### Modifié — socle CSS/JS partagé & mode sombre par tokens (Piste A, Phase 3)

Refonte interne de l'espace client : les styles et le JavaScript, autrefois
copiés dans chaque template (~850 lignes de `<style>` très dupliquées + ~30
fonctions JS), sont centralisés dans des fichiers uniques injectés par le hook
`ClientAreaHeadOutput`. Palette **unifiée** via des design tokens `--sm-*`.
Facturation inchangée ; seuls changements visuels **volontaires** : les
redirections passent du vert à l'indigo commun.

- **`_sm_styles.css`** (nouveau, injecté) — kit modale, widget mot de passe et
  composants partagés (pills, boutons, boîtes de prix…) + ~22 **design tokens**
  (primaire indigo, succès vert unique, danger, avertissement, surfaces /
  bordures / texte). Chaque template ne conserve que ses vraies spécificités.
- **`_sm_common.js`** (nouveau, injecté dans le `<head>`) — fonctions JS
  partagées, retirées des templates : kit modale (`smOpen` / `smClose` / `smBg`
  + fermeture Échap), échappement (`escHtml` / `escAttr`), widget mot de passe
  (`smTogglePwd` / `smGeneratePwd` / `smCrit` / `smCheckPwd`) et gestion des
  listes d'adresses (ajout/retrait d'alias, de redirections et de cibles + rendu
  des cibles). Gardes `typeof` pour les rappels propres à un template
  (`smMarkDirty`, `smCheckReady`). Effets bénéfiques : fermeture Échap sur toutes
  les pages, focus automatique à l'ouverture des modales. Le rendu des pills
  d'alias/redirection reste local (modèles de soumission distincts entre
  edituser et adduser).
- **Mode sombre par redéfinition de tokens** — `_sm_dark_mode.css` redéfinit les
  tokens `--sm-*` sous `html.lagom-dark-mode` ; les règles tokenisées s'adaptent
  seules. Modales de redirection basculées sur les variantes partagées
  `.info` / `.del` (cohérentes clair **et** sombre).
- **Palette unifiée (changement visuel volontaire)** — pages de redirection en
  indigo commun ; un des deux verts concurrents disparaît au profit d'un vert
  « succès » unique.
- **Templates morts purgés** — `pwdmodal.tpl`, `pwdwidget.tpl`,
  `_dns_copy_field.tpl` (222 lignes, confirmés inutilisés).

### Corrigé

- **Info-bulle « Retirer » des pills alias/redirection** (edituser) : le
  `title="{$lang.btn_remove}"` piégé dans un bloc `{literal}` s'affichait
  littéralement au lieu d'être traduit → nouveau global `SM_LANG_BTN_REMOVE`
  (réutilise la clé i18n existante `btn_remove`). Ajouté aussi aux pills
  d'adduser pour cohérence.

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
