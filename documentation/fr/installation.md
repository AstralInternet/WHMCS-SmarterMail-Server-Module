---
title: Installation
description: Installer le module, ajouter le serveur SmarterMail dans WHMCS, créer le produit et planifier le cron d'usage.
---

# Installation

## 1. Prérequis

- **WHMCS 8.0+**, **PHP 8.0+**.
- Un serveur **SmarterMail 16+** (Windows) avec son **API REST v1** activée.
- Un **administrateur système** SmarterMail (sysadmin) — le module s'authentifie avec lui pour créer et gérer les domaines.

## 2. Installer les fichiers du module

Copiez le dossier du module dans votre installation WHMCS :

```
modules/servers/smartermail/   →   <whmcs>/modules/servers/smartermail/
```

Aucune modification de base de données n'est requise — WHMCS découvre le module automatiquement.

## 3. Ajouter le serveur SmarterMail dans WHMCS

Dans WHMCS, allez dans **Configuration → System Settings → Servers** et **Add New Server** :

| Champ | Valeur |
|---|---|
| **Name** | Une étiquette pour ce serveur SmarterMail |
| **Hostname** | L'adresse de votre serveur SmarterMail, ex. `mail.votreserveur.com` |
| **Module** | `SmarterMail (Email Hosting)` |
| **Port** | `443` (HTTPS) ou `9998` (HTTP par défaut de SmarterMail) |
| **Secure** | Activé si vous utilisez HTTPS |
| **Username** | Le compte **sysadmin** SmarterMail |
| **Password** | Le mot de passe de ce compte sysadmin |

Le **Hostname** réglé ici sert aussi de valeur par défaut au guide DNS de l'espace client pour l'Autodiscover, quand vous laissez ces options produit vides — voir [Configuration du produit → Autodiscover](configuration.md#autodiscover).

## 4. Créer le produit

Dans **Configuration → System Settings → Products/Services**, créez ou modifiez un produit :

1. **Onglet Module Settings** — choisissez `SmarterMail (Email Hosting)` et le serveur (ou groupe de serveurs) de l'étape 3.
2. **Module Settings** — les 23 options qui définissent votre offre (tarifs, politique de mot de passe, protocoles que les clients peuvent activer, valeurs DNS…). **Chaque option et son implication sont documentées dans [Configuration du produit](configuration.md).**
3. **Onglet Pricing** — réglez le prix récurrent au **prix par tranche de Go** (voir ci-dessous).

> Le prix mensuel que vous réglez n'est **pas** un prix fixe — il est multiplié par le nombre de tranches d'usage disque consommées par le client. Exemple : un prix de `6,00 $` avec *GB par tranche de facturation* `= 10` facture un client utilisant 21 Go à `3 × 6,00 $ = 18,00 $`. Voir [Modèle de facturation](billing.md).

## 5. Planifier le cron d'usage

La facturation à l'usage disque repose sur l'appel par WHMCS de la fonction `UsageUpdate` du module, qui interroge l'API SmarterMail pour l'usage disque réel de chaque domaine et l'écrit dans `tblhosting.diskusage`.

- Assurez-vous que le **cron système WHMCS** est configuré (le cron WHMCS documenté).
- **Exécutez-le au moins quotidiennement** pour que les factures reflètent l'usage courant.

Sans le cron, `diskusage` reste à sa dernière valeur connue et les factures peuvent facturer un usage périmé.

## 6. Vérifier

- Passez une commande de test (ou utilisez *Create* sur un service en attente). Le domaine devrait apparaître dans SmarterMail avec un compte admin caché.
- Ouvrez le service dans l'espace client WHMCS — vous devriez voir le tableau de bord, le gestionnaire de boîtes et le guide DNS.

Si *Create* échoue, les causes les plus courantes sont : mauvais identifiants sysadmin, API REST désactivée sur SmarterMail, ou un pare-feu bloquant le port de l'API.
