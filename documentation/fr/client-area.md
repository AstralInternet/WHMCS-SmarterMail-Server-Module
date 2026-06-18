---
title: Espace client
description: Ce que le client peut faire depuis WHMCS — gérer les boîtes, les alias et les alias de domaine, et suivre le guide DNS de délivrabilité en temps réel.
---

# Espace client

Depuis la page du service dans l'espace client WHMCS, le client gère lui-même tout ce qui concerne son domaine courriel. Ce qui s'affiche dépend de votre [configuration du produit](configuration.md) (p. ex. si les cases EAS/MAPI et le bloc d'alias de domaine sont visibles).

## Tableau de bord

La page d'accueil résume le domaine : nombre de boîtes, usage disque, et un **guide DNS** avec une pastille verte/rouge par enregistrement. Chaque vérification utilise les valeurs réglées dans *Module Settings*.

| Vérification | Ce qui est validé | Piloté par |
|---|---|---|
| **MX** | Le MX du domaine pointe vers le serveur de courriel | le nom d'hôte du serveur |
| **SPF** | Le mécanisme SPF primaire **ou** un secondaire est présent | options 13 + 18 |
| **Autodiscover** | `autodiscover.{domaine}` (CNAME/A) et `_autodiscover._tcp` (SRV :443) | options 19 + 20 (repli : nom d'hôte) |
| **DMARC** | L'enregistrement TXT `_dmarc.{domaine}` existe (seulement si activé) | option 21 |

Chaque carte montre l'**enregistrement exact à copier** et un générateur au besoin (notamment le **générateur DMARC**, pré-rempli avec votre RUA et votre politique suggérés — options 22/23).

## Boîtes

Le gestionnaire de boîtes liste chaque adresse avec son état EAS/MAPI. Le client peut :

- **Créer** une adresse — mot de passe (avec générateur), nom d'affichage, quota disque, redirection optionnelle, et — si vous les offrez — les cases **EAS** et **MAPI**. Le plafond de boîtes (option 7) est appliqué ici.
- **Modifier** une adresse — changer le mot de passe, le nom d'affichage, le quota disque, et basculer EAS/MAPI (si offerts).
- **Supprimer** une adresse.

> Les champs de mot de passe affichent la politique configurée (options 9 à 12). **Ce sont des indications visuelles** — la vraie application est la politique propre de SmarterMail, alors gardez les deux synchronisées (voir [Configuration du produit → Politique de mot de passe](configuration.md#politique-de-mot-de-passe)).

## Alias

Un alias redirige le courrier d'une adresse vers une ou plusieurs destinations. Le client peut :

- **Voir** tous les alias et leurs destinations.
- **Créer** un alias — destinations multiples, avec options d'envoi / GAL / interne.
- **Modifier** / **Supprimer** un alias.

## Alias de domaine

Affiché **seulement quand *Max domain aliases* (option 17) est supérieur à 0.** Un alias de domaine permet aux boîtes du domaine principal de recevoir aussi le courrier adressé à un autre domaine (p. ex. `client.ca` livré dans `client.com`). Le client peut en ajouter jusqu'au maximum configuré ; la limite est appliquée côté serveur.

## Ce que le client ne peut pas faire

Les actions de provisionnement (créer / suspendre / résilier le **domaine** lui-même) sont réservées à l'administrateur — voir [Administration & automatisation](admin.md). Le client gère **à l'intérieur** de son domaine (boîtes, alias, DNS), pas le cycle de vie du domaine.
