# Checklist de test — serveur de développement

> **Cible : version `1.22.0`** · Contexte : chantier « forfaits » (P0→P5) + remédiation d'audit
> (P0→P3) livrés, **jamais validés en conditions réelles**. Objectif de cette passe :
> (1) prouver que **rien n'est cassé** pour les produits existants, (2) valider que les
> **forfaits** sont honorés partout, (3) lever les **2 points bloquants** avant prod.
>
> Principe de sûreté du chantier forfaits : **produit sans forfait + réglages globaux au
> défaut ⇒ comportement historique strict** (byte-identique). Les tests §2 le vérifient.

---

## 0. Préparation

- [ ] Déployer le code sur le bac-à-sable et **vider OPcache / redémarrer php-fpm** (le cron tourne en CLI, l'admin en fpm — les deux doivent recharger).
- [ ] Activer / ré-ouvrir l'addon **Configuration → Modules complémentaires → « SmarterMail — Forfaits »** (crée les tables `mod_sm_packages` / `mod_sm_settings`).
- [ ] Avoir sous la main : 1 produit SmarterMail **existant** avec ≥ 1 service actif ; de quoi créer 1 produit **de test**.

## 1. 🧪 Garde-fou byte-identique (LE prérequis)

- [ ] `php tools/diag_packages.php` → **« ✓ BYTE-IDENTIQUE — 0 écart »**.
      *Si écart : ne pas déployer en prod, corriger `_sm_legacyConfigToPackage` d'abord.*
- [ ] `php tools/diag_billing.php <serviceid>` → connexion API OK, admin localAPI OK.

## 2. 🔒 Non-régression — produit SANS forfait (comportement historique)

*Sur un produit dont l'option « Forfait » = « (Hérité) » — donc tout le parc actuel.*

- [ ] **Espace client** : tableau de bord (jauge disque, estimé, badges), cartes DNS (SPF/DKIM/autodiscover/DMARC), guide DNS (onglet pré-sélectionné selon les NS), générateur DMARC pré-rempli — **identiques à avant**.
- [ ] **Boîtes** : créer / éditer / supprimer une boîte ; critères de mot de passe affichés = politique habituelle ; alias / redirections OK.
- [ ] **Répondeur** : ouvrir la carte, activer/désactiver.
- [ ] **Provisioning** : créer un compte (CreateAccount) → domaine créé (chemin, IP sortie, userLimit) ; changer de forfait produit (ChangePackage).
- [ ] **Facture** : générer une facture → lignes disque + EAS/MAPI **identiques à avant** (idéalement comparer à une facture d'un cycle précédent).
- [ ] Mode **sombre** : parcours rapide (aucun décalage).

## 3. 📦 Forfaits — création & GUI (addon)

- [ ] **Créer un forfait** : les 4 onglets s'affichent et **basculent** (Général · Mot de passe · DNS · Disque & facturation).
- [ ] Renseigner des valeurs **différentes** du produit de test (prix EAS/MAPI, quota, Go/tranche, règles mdp dont **minuscule**, SPF, DMARC, taille max/boîte).
- [ ] Enregistrer → le forfait apparaît dans la **liste** (type, quota, boîte max, « produits liés » = 0).
- [ ] **Modifier** le forfait (les valeurs se rechargent bien dans les onglets) ; **supprimer** un forfait de test (soft-delete + avertissement si lié).
- [ ] Produit de test → **Module Settings** → l'option **« Forfait »** liste le forfait ; le sélectionner + enregistrer.
      *(Si le champ « Forfait » n'apparaît pas : ré-enregistrer les réglages du module du produit.)*

## 4. 📦 Provisioning SUR forfait

*Produit de test lié au forfait.*

- [ ] **CreateAccount** → vérifier côté SmarterMail : chemin, IP de sortie, `userLimit`, `maxSize` (si quota bloqué) = **valeurs du forfait**.
- [ ] **ChangePackage** vers/depuis ce produit → `userLimit`/`maxSize`/IP re-poussés ; refus si quota bloqué < usage courant.
- [ ] **Taille max par boîte** : créer une boîte en demandant plus que `max_mailbox_size_gb` → la taille est **plafonnée** au forfait (et « illimité » client devient le plafond).

## 5. 💵 Facturation SUR forfait — ⚠️ CRITIQUE (factures WHMCS 9.0 immuables)

- [ ] Générer une facture pour le **produit lié au forfait** → prix EAS/MAPI, seuil, tranches, quota/excédent = **ceux du forfait**.
- [ ] Générer une facture pour un **produit hérité** en parallèle → **inchangée**.
- [ ] **Comparer** les lignes/montants au comportement attendu (diff avant-après). Journal d'activité : `SmarterMail InvoiceCreation [UpdateInvoice] OK`.
- [ ] Tableau de bord du produit forfait : l'**estimé correspond à la facture** générée.

## 6. 🌐 Réglages globaux

- [ ] **EAS/MAPI désactivé** (Réglages globaux, décocher) → recharger un forfait : la section EAS/MAPI **disparaît** de l'éditeur ; espace client : **plus aucune** UI EAS/MAPI (offre ni prix).
- [ ] Réactiver → EAS/MAPI **réapparaît**.
- [ ] **Nameservers** : renseigner tes NS → l'onglet du guide DNS se **pré-sélectionne** selon les NS du domaine du client.

## 7. 🔁 Bouton « Convertir en forfait »

- [ ] Onglet « Réglages produits (hérité) » → sur un produit hérité, cliquer **« Convertir en forfait »**.
- [ ] Un forfait « Converti — <produit> » est créé, reflétant la config actuelle ; le produit y est **lié** (si aucun forfait n'était sélectionné).
- [ ] Vérifier dans l'onglet « Forfaits » que les valeurs correspondent à la config d'origine.

## 8. 🆕 Nouvelles règles

- [ ] **Mot de passe minuscule** : forfait avec « minuscule obligatoire » activé → à l'ajout de boîte, le critère apparaît, la barre/le bouton exigent une minuscule ; un POST direct sans minuscule est **rejeté serveur**.
- [ ] Forfait **sans** minuscule → aucun critère minuscule (= comportement historique).

## 9. ✅ Répondeur automatique (audience confirmée + correctifs 1.24.0)

**Mapping `externalAudience` confirmé (2026-07-09)** : `0`=None / `1`=Contacts / `2`=All. **Plus bloquant.**

À revérifier après les correctifs **1.24.0** (le répondeur était « non accessible ») :
- [ ] Éditeur d'une boîte → la carte **Répondeur** propose bien de configurer (fini le « non accessible pour cette boîte »).
- [ ] Activer un répondeur (sujet + message) → enregistrer → **vérifier dans le webmail SmarterMail** qu'il est actif avec le bon contenu.
- [ ] Recharger la page client → l'état est **relu** correctement.
- [ ] Confirmer que **le même message** part en interne ET en externe (`body` = `externalReply`), l'**audience** (0/1/2) ne réglant que qui reçoit une réponse hors du domaine.
- [ ] Tester une **plage de dates** active (début < fin).

## 10. 🌍 Devise & langue de facture (si client multidevise disponible)

- [ ] Client dans une **devise ≠ défaut** : facture + estimé affichent le **bon symbole** ; produit tarifé dans cette devise.
- [ ] Client dans une **langue ≠ système** : les libellés de facture (détail disque, en-têtes EAS/MAPI, DMARC) sont dans **sa langue**.

---

### Rappels
- **Limitation connue** : les suppléments EAS/MAPI ne sont **pas convertis** entre devises (valeurs brutes) — sans effet en mono-devise.
- En cas d'anomalie de facturation : **ne pas** rejouer en prod ; le resolver ne lève jamais (repli hérité → défauts), donc une anomalie = donnée de forfait à corriger, pas un blocage de facturation.
- Tous les diags sont en **lecture seule** — supprimer les fichiers `tools/diag_*.php` du serveur après usage.
