---
title: Configuration du produit
description: Chacune des 23 options du module SmarterMail, regroupées, avec ce que chaque option implique pour la facturation, les clients et la délivrabilité.
---

# Configuration du produit — chaque option du module

Tout le comportement du produit est piloté par les **Module Settings** du produit WHMCS (Configuration → Products/Services → votre produit → *Module Settings*). Il y a **23 options**. Cette page documente chacune **et son implication** — ce que choisir une valeur fait réellement à la facturation, à ce que les clients peuvent faire, et aux vérifications de délivrabilité.

> Les options sont référencées par leur emplacement WHMCS (`configoption1` … `configoption23`) pour pouvoir recouper la config brute du produit au besoin.

---

## Facturation

Ces quatre options fixent les tarifs. Elles définissent *ce qui* est facturé ; le [Modèle de facturation](billing.md) explique *comment* la facture est assemblée.

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 1 | **GB par tranche de facturation** | text | `10` | Taille d'un incrément d'usage disque. Le prix récurrent du produit est facturé **une fois par tranche entamée**. *Implication :* avec `10` et un prix de `6,00 $`, un client à 21 Go est facturé 3 tranches = `18,00 $`, affiché *« 21.00 Go utilisés sur 30 Go facturés »*. Des tranches plus petites = granularité plus fine mais plus de sauts. |
| 2 | **Prix ActiveSync (EAS) / boîte / mois ($)** | text | `2.00` | Frais mensuels ajoutés **par boîte ayant EAS activé** (une ligne de facture chacune). *Implication :* mettre `0` désactive entièrement la facturation EAS (EAS peut quand même être offert gratuitement — voir *Offre de protocoles*). |
| 3 | **Prix MAPI/Exchange / boîte / mois ($)** | text | `3.00` | Frais mensuels par boîte ayant MAPI (Outlook en mode Exchange) activé. *Implication :* mettre `0` désactive la facturation MAPI. |
| 4 | **Prix combiné EAS + MAPI / boîte / mois ($)** | text | `4.50` | Tarif réduit quand une boîte a **les deux**, EAS et MAPI. *Implication :* il **remplace** les deux prix séparés (pas de cumul) — une boîte avec les deux est facturée `4,50`, pas `2,00 + 3,00`. Mettre `0` pour facturer les deux prix séparés même en combinaison. |

> Le **prix** récurrent vit dans l'onglet *Pricing* du produit, pas ici — ces options ne fixent que les **multiplicateurs et les tarifs d'options**.

---

## Paramètres SmarterMail

Comment le domaine est créé sur le serveur SmarterMail.

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 5 | **Chemin des domaines sur le serveur** | text | `C:\SmarterMail\Domains\` | Où vivent les fichiers du domaine sur le serveur Windows. Le nom du domaine est ajouté automatiquement (`…\Domains\client.com\`). *Implication :* doit être un chemin valide sur **ce** serveur ; un mauvais chemin fait échouer *Create*. |
| 6 | **Adresse IP de sortie (outbound)** | text | `default` | L'IP utilisée pour envoyer le courrier sortant de ce domaine. *Implication :* gardez `default` pour l'IP par défaut du serveur ; mettez une IP précise quand vous vendez des **IP d'envoi dédiées** par client (impacte SPF/réputation). |
| 7 | **Nombre max d'utilisateurs par domaine** | text | `0` | Plafond de boîtes du domaine. *Implication :* `0` = illimité ; un nombre positif permet des forfaits paliers (ex. un forfait « 5 boîtes »). Le plafond est appliqué à la création des boîtes. |

---

## Résiliation

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 8 | **Supprimer les données lors de la résiliation** | oui/non | `oui` | À la *résiliation* : **oui** retire le domaine **et toutes ses données courriel** de SmarterMail ; **non** retire le domaine de SmarterMail mais **laisse les fichiers sur disque**. *Implication :* choisissez **non** si vous gérez vous-même l'archivage/rétention ou voulez une fenêtre de grâce avant suppression manuelle — mais ces fichiers continuent de consommer du disque jusqu'à leur retrait. |

---

## Politique de mot de passe

Ces quatre options sont des **indications d'affichage/validation** montrées aux clients quand ils définissent ou changent un mot de passe de boîte dans l'espace client. **Elles ne configurent pas SmarterMail lui-même.**

| # | Option | Type | Défaut |
|---|---|---|---|
| 9 | **Longueur minimale du mot de passe** | text | `8` |
| 10 | **Exiger une lettre majuscule** | oui/non | `oui` |
| 11 | **Exiger un chiffre** | oui/non | `oui` |
| 12 | **Exiger un caractère spécial** | oui/non | `oui` |

> **Implication critique — gardez-les synchronisées.** SmarterMail applique ses **propres** règles de mot de passe (Admin → Configuration → Password Requirements). Ces options indiquent seulement à l'*espace client* quoi afficher et pré-valider. Si elles ne correspondent pas aux vraies règles de SmarterMail, les clients passeront la vérif de l'espace client puis seront rejetés par SmarterMail (ou l'inverse). Réglez-les aux **mêmes valeurs** que celles configurées sur le serveur.

---

## Offre de protocoles

Si les clients peuvent activer eux-mêmes EAS / MAPI. **Indépendant des prix des options 2 à 4.**

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 14 | **Proposer ActiveSync (EAS) aux clients** | oui/non | `oui` | **oui** → la case EAS apparaît quand un client crée/modifie une boîte ; **non** → la case est masquée et seul un admin peut activer EAS (via SmarterMail ou l'admin WHMCS). |
| 15 | **Proposer MAPI/Exchange aux clients** | oui/non | `oui` | Pareil, pour MAPI. |

> **Pourquoi c'est séparé du prix :** vous pouvez *offrir EAS gratuitement* (option 14 = oui, option 2 = `0`), ou *facturer EAS sans laisser les clients l'activer eux-mêmes* (option 14 = non, option 2 = `2,00`, l'admin l'active manuellement). Les bascules d'offre contrôlent la **visibilité dans l'interface** ; les prix contrôlent la **facturation**. Les deux sont volontairement découplés.

---

## Seuil de facturation EAS / MAPI

| # | Option | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|---|
| 16 | **Seuil de facturation EAS/MAPI (jours)** | text | `1` | Jours **cumulatifs** minimum d'activation d'un protocole dans la période de facturation avant qu'il soit facturé pour ce mois. |

**Implications — à lire avant de changer :**

- **Temps cumulatif.** Actif 12 h + désactivé + réactivé 12 h = 1 jour → facturable au seuil `1`. Les brefs essais sous le seuil ne sont **pas** facturés.
- **Idempotent.** Une boîte/protocole est facturée **une seule fois par période**, même si basculée plusieurs fois.
- **Les boîtes supprimées sont quand même facturées.** Si une boîte a franchi le seuil puis est supprimée, elle est **quand même facturée** sur la prochaine facture avec la plage de dates (*« Actif du jj-mmm au jj-mmm »*) — un client ne peut donc pas éviter les frais en activant, utilisant, puis supprimant.
- **`0` = facturation en direct.** Une valeur de `0` désactive le seuil et facture selon l'**état au moment de la facture** (ce qui est activé à la génération).

---

## Alias de domaine

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 17 | **Alias de domaine max** | text | `0` | Combien d'**alias de domaine** le client peut ajouter depuis l'espace client. Un alias de domaine permet aux boîtes du domaine principal de recevoir aussi le courrier adressé à **un autre nom de domaine** (ex. `client.ca` → `client.com`). *Implication :* `0` **masque entièrement la fonctionnalité** (le bloc n'apparaît pas dans l'espace client) ; `N > 0` laisse le client en ajouter jusqu'à N. La limite est appliquée côté serveur (PHP), donc impossible à contourner depuis le navigateur. Les valeurs négatives sont ramenées à `0`. |

---

## SPF

Le guide DNS de l'espace client vérifie l'enregistrement SPF du client et affiche un état vert/rouge.

| # | Option | Type | Défaut | Ce qu'elle fait |
|---|---|---|---|---|
| 13 | **Mécanisme SPF principal** | text | `include:mail.example.com` | Le mécanisme que le client **doit ajouter** (ex. `include:mail.example.com` ou `ip4:1.2.3.4`). C'est celui **affiché et recommandé** dans le guide DNS. |
| 18 | **Mécanismes SPF secondaires** | text | *(vide)* | Mécanismes additionnels **acceptés comme valides**, séparés par des virgules (ex. `include:old-mail.server.com, ip4:203.0.113.5`). |

**Implications :**

- Le SPF est jugé **valide** si le **principal OU n'importe quel secondaire** est trouvé dans le DNS du client. Seul le **principal** est affiché/recommandé ; les secondaires servent **uniquement à la validation** (la pastille vert/rouge).
- Utilisez les secondaires lors des **migrations / configurations multi-serveur** pour que l'ancien et le nouvel hôte d'envoi valident pendant la transition du client. Laissez vide si vous avez un seul hôte d'envoi.

---

## Autodiscover

Le tableau de bord vérifie deux enregistrements pour qu'Outlook / Apple Mail s'auto-configurent :

- **CNAME/A** — `autodiscover.{domaine}` doit pointer sur l'hôte.
- **SRV** — `_autodiscover._tcp.{domaine}` doit cibler l'hôte sur le port **443**.

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 19 | **Hôte Autodiscover attendu** | text | *(vide)* | Cible attendue pour `autodiscover.{domaine}` (CNAME/A). *Implication :* **vide → retombe sur le Hostname du serveur WHMCS.** N'overridez que si l'Autodiscover doit pointer sur un nom différent du hostname du serveur (ex. un `mail.astralinternet.com` dédié). |
| 20 | **Cible SRV Autodiscover** | text | *(vide)* | Cible attendue pour le SRV `_autodiscover._tcp.{domaine}` (port 443 forcé). *Implication :* vide → retombe sur le Hostname du serveur WHMCS. |

---

## DMARC

| # | Option | Type | Défaut | Ce qu'elle fait — et l'implication |
|---|---|---|---|---|
| 21 | **Vérifier DMARC** | oui/non | *(désactivé sauf si réglé)* | Affiche la mini-carte DMARC et vérifie l'enregistrement TXT `_dmarc.{domaine}`. *Implication :* mettez **non** pour les produits où la conformité DMARC est hors périmètre — la carte n'est pas affichée et un DMARC manquant ne marque pas le client comme problématique. |
| 22 | **RUA DMARC suggéré** | text | *(vide)* | Adresse pré-remplie dans le champ « Send Aggregate Reports To » du générateur DMARC. Le client peut la modifier avant de copier. Ex. `dmarc-reports@astralinternet.com`. |
| 23 | **Politique DMARC suggérée** | liste (`none` / `quarantine` / `reject`) | `none` | Politique pré-sélectionnée dans le générateur DMARC. *Implication :* **`none`** = observation seulement (le plus sûr pour démarrer) ; **`quarantine`** = le courrier suspect va au spam ; **`reject`** = bloqué d'emblée. Démarrez à `none` et resserrez une fois les rapports propres. |

---

## Aide-mémoire — les 23 options

| # | Option | Défaut |
|---|---|---|
| 1 | GB par tranche de facturation | `10` |
| 2 | Prix ActiveSync (EAS) / boîte / mois | `2.00` |
| 3 | Prix MAPI/Exchange / boîte / mois | `3.00` |
| 4 | Prix combiné EAS + MAPI / boîte / mois | `4.50` |
| 5 | Chemin des domaines sur le serveur | `C:\SmarterMail\Domains\` |
| 6 | Adresse IP de sortie (outbound) | `default` |
| 7 | Nombre max d'utilisateurs par domaine | `0` (illimité) |
| 8 | Supprimer les données lors de la résiliation | `oui` |
| 9 | Longueur minimale du mot de passe | `8` |
| 10 | Exiger une lettre majuscule | `oui` |
| 11 | Exiger un chiffre | `oui` |
| 12 | Exiger un caractère spécial | `oui` |
| 13 | Mécanisme SPF principal | `include:mail.example.com` |
| 14 | Proposer ActiveSync (EAS) aux clients | `oui` |
| 15 | Proposer MAPI/Exchange aux clients | `oui` |
| 16 | Seuil de facturation EAS/MAPI (jours) | `1` |
| 17 | Alias de domaine max | `0` (désactivé) |
| 18 | Mécanismes SPF secondaires | *(vide)* |
| 19 | Hôte Autodiscover attendu | *(vide → hostname serveur)* |
| 20 | Cible SRV Autodiscover | *(vide → hostname serveur)* |
| 21 | Vérifier DMARC | *(désactivé)* |
| 22 | RUA DMARC suggéré | *(vide)* |
| 23 | Politique DMARC suggérée | `none` |
