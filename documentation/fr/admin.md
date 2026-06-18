---
title: Administration & automatisation
description: Le cycle de vie du domaine automatisé par le module — création, suspension, réactivation, résiliation — plus la connexion admin et le cron d'usage.
---

# Administration & automatisation

Cette page couvre les actions de **niveau domaine** que WHMCS pilote via le module — le cycle de vie auquel le client n'a pas accès depuis l'espace client. La plupart sont automatiques (déclenchées par les commandes, les annulations et le cron) ; quelques-unes sont des boutons manuels sur la page de service de l'admin.

## Cycle de provisionnement

| Action WHMCS | Ce que le module fait sur SmarterMail |
|---|---|
| **Create** (créer) | Crée le domaine sur le serveur avec le chemin de stockage (option 5) et l'IP sortante (option 6), applique le plafond de boîtes (option 7) et provisionne la boîte principale. S'exécute automatiquement à l'acceptation d'une commande, ou manuellement via *Create* sur la page de service. |
| **Suspend** (suspendre) | Désactive le domaine — le courrier est rejeté/retenu et les utilisateurs ne peuvent plus se connecter — sans rien supprimer. Déclenché automatiquement à la suspension pour impayé, ou manuellement. |
| **Unsuspend** (réactiver) | Réactive un domaine suspendu. Déclenché au paiement de la facture en souffrance, ou manuellement. |
| **Terminate** (résilier) | Retire le domaine de SmarterMail. **La suppression ou non des données sur le disque dépend de l'option 8** (*Delete data on termination*) — voir [Configuration du produit → Résiliation](configuration.md#resiliation). |
| **Change password** | Réinitialise le mot de passe de la boîte principale. |

> **La résiliation est destructrice.** Avec l'option 8 = *yes*, résilier retire le domaine **et tout le courrier/les données**. Si vous gardez un délai de grâce ou faites votre propre archivage, mettez l'option 8 = *no* et nettoyez les fichiers vous-même plus tard.

## Connexion admin (authentification unique)

Depuis la page de service de l'admin, vous pouvez entrer directement dans la gestion SmarterMail du domaine en tant qu'administrateur de domaine, sans saisir d'identifiants. Utilisez-la pour inspecter ou corriger le domaine d'un client (filtrage de contenu, partage, règles côté serveur) qui n'est pas exposé dans l'espace client WHMCS.

## Cron d'usage

Le `UsageUpdate` du module s'exécute via le **cron système de WHMCS**. À chaque passage, il interroge l'API SmarterMail pour l'usage disque réel de chaque domaine actif et l'écrit (en Mo) dans `tblhosting.diskusage`. C'est cette valeur stockée que le [hook de facturation](billing.md) lit pour calculer les tranches disque.

- **Planifiez-le quotidiennement** — voir [Installation → Planifier le cron d'usage](installation.md#5-planifier-le-cron-dusage).
- Si le cron ne s'exécute jamais, `diskusage` reste à la dernière valeur connue (ou `0`), donc la facturation disque ne reflète pas la réalité. Un cron absent ou trop espacé est la cause habituelle d'un « usage disque erroné sur la facture ».

## Où chaque réglage s'applique

| Phase | Options consultées |
|---|---|
| Création | 5 (chemin), 6 (IP sortante), 7 (max users) |
| Résiliation | 8 (suppression des données) |
| Cron d'usage | — (lit l'API en direct, écrit `diskusage`) |
| Facture (hook) | 1 à 4 (tarifs), 16 (seuil) |

Tout le reste (9 à 15, 17 à 23) façonne l'**espace client** — indications de mot de passe, offre de protocoles, alias de domaine et guide DNS — et est couvert dans [Espace client](client-area.md) et [Configuration du produit](configuration.md).
