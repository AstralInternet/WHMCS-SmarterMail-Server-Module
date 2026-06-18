---
title: Modèle de facturation
description: Comment la facture SmarterMail est assemblée — tranches d'usage disque plus options EAS/MAPI par boîte, tarif combiné et seuil d'activation.
---

# Modèle de facturation

La facture récurrente d'un service SmarterMail est **assemblée dynamiquement** à chaque cycle. Le `hooks.php` du module écoute l'événement WHMCS `InvoiceCreated` et réécrit la facture pour qu'elle reflète l'**usage réel** au moment où elle est générée.

Deux dimensions sont facturées : l'**usage disque** (la ligne de base) et les **options de protocole par boîte** (EAS / MAPI).

## 1. Usage disque — la ligne de base

- Le cron WHMCS appelle périodiquement le `UsageUpdate` du module, qui interroge l'API SmarterMail pour l'usage disque réel du domaine et le stocke (en Mo) dans `tblhosting.diskusage`. *(Planifiez le cron quotidiennement — voir [Installation](installation.md#5-planifier-le-cron-dusage).)*
- À la facturation, le hook lit `diskusage`, divise par **GB par tranche de facturation** (option 1), arrondit **vers le haut** au nombre entier de tranches, et multiplie le prix récurrent du produit par ce nombre.

**Exemple** — prix `6,00 $`, tranche `10 Go`, client à 21 Go :

```
ceil(21 / 10) = 3 tranches  →  3 × 6,00 $ = 18,00 $
Description de la ligne : « Hébergement courriel (21.00 Go utilisés sur 30 Go facturés) »
```

## 2. ActiveSync (EAS) et MAPI/Exchange — par boîte

Pour chaque boîte, le module sait si **EAS** et/ou **MAPI** est activé, et ajoute les lignes en conséquence :

| État de la boîte | Ligne ajoutée | Prix (option) |
|---|---|---|
| EAS seulement | `ActiveSync (EAS) : bob@domaine.com` | option 2 (`2.00`) |
| MAPI seulement | `MAPI/Exchange : bob@domaine.com` | option 3 (`3.00`) |
| **EAS + MAPI** | `EAS + MAPI/Exchange : bob@domaine.com` | option 4 combinée (`4.50`) — **remplace** les deux lignes séparées |

- Un prix de `0` (option 2/3) désactive la facturation de ce protocole — il peut quand même être offert gratuitement.
- Le tarif combiné (option 4) est un **rabais, pas une somme** : une boîte avec les deux est facturée `4,50`, pas `2,00 + 3,00`. Mettez l'option 4 à `0` pour facturer les deux lignes séparées même en combinaison.

## 3. Le seuil d'activation (option 16)

Par défaut, un protocole doit avoir été activé **≥ 1 jour cumulatif** dans la période avant d'être facturé. Cela évite de facturer les brefs essais, et c'est **idempotent** (facturé une fois par période, peu importe le nombre de bascules).

Comportements clés :

- **Cumulatif**, pas continu — 12 h + 12 h dans la période comptent comme 1 jour.
- **Les boîtes supprimées qui ont franchi le seuil sont quand même facturées** sur la prochaine facture, avec la plage de dates (*« Actif du jj-mmm au jj-mmm »*) — les frais ne peuvent donc pas être évités en supprimant la boîte avant la facturation.
- **`0` = facturation en direct** — facture ce qui est activé au moment exact de la génération, sans minimum.

Voir [Configuration du produit → Seuil de facturation EAS / MAPI](configuration.md#seuil-de-facturation-eas-mapi) pour toutes les implications.

## Régler le prix

Le **prix par tranche** est le prix récurrent normal du produit (Products/Services → onglet *Pricing*). Tout ce qui est dans l'onglet *Module Settings* (options 1 à 4) ne fait que régler **comment ce prix est multiplié** et les **tarifs d'options** — voir [Configuration du produit → Facturation](configuration.md#facturation).

> **Astuce :** comme la ligne de base est réécrite en « N tranches », réglez le *Pricing* du produit au prix d'**une seule** tranche, pas au total attendu.
