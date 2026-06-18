---
title: SmarterMail Hébergement courriel — Documentation
description: Module d'approvisionnement WHMCS pour SmarterMail — facturation à l'usage, options EAS/MAPI, libre-service des boîtes et alias, et vérifications DNS de délivrabilité.
---

# SmarterMail Hébergement courriel — module WHMCS

> Par [Astral Internet inc.](https://www.astralinternet.com) — un **module serveur/d'approvisionnement** WHMCS qui vend et automatise l'hébergement courriel SmarterMail.

Ce module transforme un serveur SmarterMail en produit WHMCS entièrement automatisé : les domaines sont créés, suspendus, réactivés et résiliés automatiquement, les clients gèrent eux-mêmes leurs boîtes et alias depuis l'espace client WHMCS, la facturation suit l'usage disque réel et les options de protocole par boîte (ActiveSync / MAPI), et un guide DNS intégré accompagne chaque client pour SPF, DMARC et Autodiscover.

## Index de la documentation

| Document | Contenu |
|---|---|
| [Installation](installation.md) | Ajouter le serveur SmarterMail dans WHMCS, créer le produit, régler le cron |
| [Configuration du produit](configuration.md) | **Chacune des 23 options du module, regroupées, avec ce que chacune implique** |
| [Modèle de facturation](billing.md) | Tranches d'usage disque, options EAS/MAPI par boîte, tarif combiné, seuil d'activation |
| [Espace client](client-area.md) | Ce que le client peut faire : boîtes, alias, alias de domaine, et le guide DNS |
| [Admin et automatisation](admin.md) | Créer / Suspendre / Réactiver / Résilier, auto-login admin, mise à jour de l'usage disque |

## Ce qu'il fait, en une minute

- **Approvisionnement** — *Create* vérifie que le domaine n'existe pas, le crée sur SmarterMail avec un compte admin caché généré aléatoirement (stocké dans les champs username/password du service), et applique vos réglages produit (chemin du domaine, IP de sortie, plafond d'utilisateurs). *Suspend / Unsuspend* basculent l'indicateur `isEnabled` du domaine ; *Terminate* retire le domaine (en conservant optionnellement les données sur disque).
- **Facturation à l'usage** — le cron WHMCS enregistre l'usage disque réel des boîtes ; un hook `InvoiceCreated` réécrit chaque facture pour facturer **par tranche de Go** plus une ligne **par boîte** ayant ActiveSync (EAS) et/ou MAPI/Exchange activé.
- **Libre-service client** — depuis l'espace client, les clients créent/modifient/suppriment des boîtes (mot de passe, nom affiché, quota disque, EAS, MAPI), gèrent les alias et alias de domaine, et suivent un guide DNS en direct qui vérifie MX, SPF, DMARC et Autodiscover.
- **Délivrabilité** — le tableau de bord client valide le DNS du client contre les valeurs que vous configurez (SPF principal + secondaire, hôte/SRV Autodiscover attendu, DMARC) et affiche des pastilles vert/rouge avec des enregistrements prêts à copier.

## Compatibilité

| Prérequis | Version |
|---|---|
| WHMCS | 8.0+ |
| SmarterMail | 16+ (API REST v1) |
| PHP | 8.0+ |
| OS SmarterMail | Windows (le chemin de stockage des domaines est un chemin Windows) |

> **Commencez ici :** lisez [Installation](installation.md) pour connecter le serveur et créer le produit, puis [Configuration du produit](configuration.md) pour régler chaque option de votre offre.
