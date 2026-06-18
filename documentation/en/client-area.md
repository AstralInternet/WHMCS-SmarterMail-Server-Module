---
title: Client area
description: What the customer can do from WHMCS — manage mailboxes, aliases and domain aliases, and follow the live DNS deliverability guide.
---

# Client area

From the service page in the WHMCS client area, the customer self-manages everything about their email domain. What appears depends on your [product configuration](configuration.md) (e.g. whether EAS/MAPI checkboxes and the domain-alias block are shown).

## Dashboard

The landing page summarises the domain: mailbox count, disk usage, and a **DNS guide** with a green/red status pill per record. Each check uses the values you set in *Module Settings*.

| Check | What's verified | Driven by |
|---|---|---|
| **MX** | The domain's MX points at the mail host | the server hostname |
| **SPF** | Primary **or** any secondary SPF mechanism is present | options 13 + 18 |
| **Autodiscover** | `autodiscover.{domain}` (CNAME/A) and `_autodiscover._tcp` (SRV :443) | options 19 + 20 (fallback: server hostname) |
| **DMARC** | `_dmarc.{domain}` TXT exists (only when enabled) | option 21 |

Each card shows the **exact record to copy** and a generator where relevant (notably the **DMARC generator**, pre-filled with your suggested RUA and policy — options 22/23).

## Mailboxes

The mailbox manager lists every address with its EAS/MAPI status. Customers can:

- **Create** an address — password (with a generator), display name, disk quota, optional forwarding, and — if you offer them — the **EAS** and **MAPI** checkboxes. The max-users cap (option 7) is enforced here.
- **Edit** an address — change the password, display name, disk quota, and toggle EAS/MAPI (when offered).
- **Delete** an address.

> Password fields show the policy you configured (options 9–12). **Those are display hints** — the real enforcement is SmarterMail's own policy, so keep the two in sync (see [Product configuration → Password policy](configuration.md#password-policy)).

## Aliases

An alias forwards mail for one address to one or more destinations. Customers can:

- **View** all aliases and their destinations.
- **Create** an alias — multiple destinations, with send / GAL / internal options.
- **Edit** / **Delete** an alias.

## Domain aliases

Shown **only when *Max domain aliases* (option 17) is greater than 0.** A domain alias lets the primary domain's mailboxes also receive mail addressed to another domain (e.g. `client.ca` delivered into `client.com`). The customer can add up to the configured maximum; the limit is enforced server-side.

## What customers can't do

Provisioning-level actions (creating/suspending/terminating the **domain** itself) are admin-only — see [Admin & automation](admin.md). Customers manage **inside** their domain (mailboxes, aliases, DNS), not the domain's lifecycle.
