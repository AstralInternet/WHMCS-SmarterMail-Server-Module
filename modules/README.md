# Module WHMCS — SmarterMail (Hébergement Courriel)

## Installation

1. Copier le dossier `modules/servers/smartermail/` dans le répertoire correspondant de votre installation WHMCS.
2. Dans WHMCS, aller dans **Configuration → Serveurs** et ajouter un nouveau serveur :
   - **Type** : SmarterMail (Hébergement Courriel)
   - **Nom d'hôte** : adresse de votre serveur SmarterMail (ex: `mail.votreserveur.com`)
   - **Port** : 443 (HTTPS) ou 9998 (HTTP par défaut SmarterMail)
   - **Sécurisé** : activé si HTTPS
   - **Nom d'utilisateur** : compte administrateur système SmarterMail (sysadmin)
   - **Mot de passe** : mot de passe de ce compte sysadmin

---

## Configuration du produit

Dans **Configuration → Produits/Services**, créer ou modifier un produit :

- **Onglet Module** : sélectionner `SmarterMail (Hébergement Courriel)`
- **Onglet Tarification** : définir le prix mensuel = **prix par tranche de X Go**
  - Ex: 10 Go par tranche à 6$/mois → un client utilisant 21 Go sera facturé 3 × 6$ = 18$

### Paramètres du module (Module Settings)

| Paramètre | Description | Défaut |
|-----------|-------------|--------|
| **GB par tranche de facturation** | Nombre de Go par incrément de facturation | `10` |
| **Prix ActiveSync (EAS) / boîte / mois** | Frais par boîte avec EAS activé | `2.00` |
| **Prix MAPI/Exchange / boîte / mois** | Frais par boîte avec MAPI activé | `3.00` |
| **Prix combiné EAS + MAPI / boîte / mois** | Tarif réduit si les deux sont activés | `4.50` |
| **Chemin des domaines sur le serveur** | Chemin de stockage SmarterMail | `C:\SmarterMail\Domains\` |
| **Adresse IP de sortie** | IP d'envoi des courriels | `default` |
| **Nombre max d'utilisateurs** | 0 = illimité | `0` |
| **Supprimer les données lors de la résiliation** | Efface les données courriel à la résiliation | `Activé` |

---

## Logique de facturation

Le fichier `hooks.php` intercepte la création de chaque facture (`InvoiceCreated`) et :

1. **Stockage disque** : lit `tblhosting.diskusage` (mis à jour automatiquement par le cron WHMCS via `UsageUpdate`), calcule le nombre de tranches, et ajuste le montant de la ligne principale de la facture.
   - Exemple : client utilise 21 Go, tranches de 10 Go, 6$/tranche → **3 tranches × 6$ = 18$**
   - Description affichée : `Hébergement courriel (21.00 Go utilisés sur 30 Go facturés)`

2. **ActiveSync (EAS)** : ajoute une ligne par boîte courriel ayant EAS activé.
   - Description : `ActiveSync (EAS) : bob@domaine.com` → 2.00$

3. **MAPI/Exchange** : ajoute une ligne par boîte courriel ayant MAPI activé.
   - Description : `MAPI/Exchange : bob@domaine.com` → 3.00$

4. **Tarif combiné** : si une boîte a EAS ET MAPI, une seule ligne au prix combiné est ajoutée.
   - Description : `EAS + MAPI/Exchange : bob@domaine.com` → 4.50$

---

## Fonctionnalités admin (WHMCS)

- **Créer** : vérifie que le domaine n'existe pas, crée le domaine + un compte admin secret (username 10 caractères aléatoires, mot de passe sécurisé) → stocké dans les champs username/password du service
- **Suspendre** : désactive le domaine dans SmarterMail (`isEnabled = false`)
- **Réactiver** : réactive le domaine (`isEnabled = true`)
- **Résilier** : supprime le domaine (avec ou sans données selon la configuration)
- **Lien admin** : génère un auto-login vers le panneau SmarterMail du domaine

---

## Fonctionnalités espace client

### Gestion des adresses courriel
- Voir la liste de toutes les boîtes avec statut EAS/MAPI
- Créer une nouvelle adresse (avec génération de mot de passe, redirection optionnelle)
- Modifier une adresse (mot de passe, nom affiché, limite disque, EAS, MAPI)
- Supprimer une adresse

### Gestion des alias
- Voir la liste des alias avec leurs destinations
- Créer un alias (multi-destinations, options d'envoi/GAL/interne)
- Modifier un alias
- Supprimer un alias

---

## Mise à jour de l'utilisation disque

Le cron WHMCS appelle automatiquement la fonction `UsageUpdate` selon la fréquence configurée.
Cette fonction interroge l'API SmarterMail pour obtenir l'utilisation disque réelle du domaine
et met à jour le champ `diskusage` (en MB) dans `tblhosting`.

**Recommandation** : configurer le cron WHMCS pour s'exécuter quotidiennement.

---

## Structure des fichiers

```
modules/servers/smartermail/
├── smartermail.php           — Module principal (toutes les fonctions WHMCS)
├── hooks.php                 — Hook InvoiceCreated pour la facturation dynamique
├── lib/
│   └── SmarterMailApi.php    — Classe wrapper pour l'API REST SmarterMail
└── templates/
    ├── clientarea.tpl        — Tableau de bord client
    ├── manageusers.tpl       — Liste des adresses courriel
    ├── adduser.tpl           — Formulaire d'ajout d'adresse
    ├── edituser.tpl          — Formulaire de modification d'adresse
    ├── managealiases.tpl     — Liste des alias
    ├── addalias.tpl          — Formulaire d'ajout d'alias
    ├── editalias.tpl         — Formulaire de modification d'alias
    └── error.tpl             — Affichage des erreurs
```

---

## Extensions futures prévues

Le module est conçu pour être facilement extensible. Exemples d'ajouts prévus :
- Filtres anti-spam avancés par boîte
- Listes de diffusion (mailing lists)
- Alias de domaine
- Calendriers et contacts partagés
- Tableau de bord d'utilisation détaillé par utilisateur

---

## Compatibilité

- **WHMCS** : v8.0+
- **SmarterMail** : v16+ (API REST v1)
- **PHP** : 8.0+
