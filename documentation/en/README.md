---
title: SmarterMail Email Hosting — Documentation
description: WHMCS provisioning module for SmarterMail — usage-based billing, EAS/MAPI add-ons, mailbox and alias self-service, and DNS deliverability checks.
---

# SmarterMail Email Hosting — WHMCS module

> By [Astral Internet inc.](https://www.astralinternet.com) — a WHMCS **server/provisioning module** that sells and automates SmarterMail email hosting.

This module turns a SmarterMail server into a fully automated WHMCS product: domains are created, suspended, unsuspended and terminated automatically, customers manage their own mailboxes and aliases from the WHMCS client area, billing follows real disk usage and per-mailbox protocol add-ons (ActiveSync / MAPI), and a built-in DNS guide walks each customer through SPF, DMARC and Autodiscover.

## Documentation index

| Document | Contents |
|---|---|
| [Installation](installation.md) | Add the SmarterMail server in WHMCS, create the product, set the cron |
| [Product configuration](configuration.md) | **Every one of the 23 Module Settings, grouped, with what each one implies** |
| [Billing model](billing.md) | Disk-usage tranches, EAS/MAPI per-mailbox add-ons, the combined rate, and the activation threshold |
| [Client area](client-area.md) | What the customer can do: mailboxes, aliases, domain aliases, and the DNS guide |
| [Admin & automation](admin.md) | Create / Suspend / Unsuspend / Terminate, admin auto-login, disk-usage updates |

## What it does, in one minute

- **Provisioning** — *Create* checks the domain doesn't exist, creates it on SmarterMail with a randomly-generated hidden admin account (stored in the service's username/password), and applies your product settings (domain path, outbound IP, user cap). *Suspend / Unsuspend* flip the domain's `isEnabled` flag; *Terminate* removes the domain (optionally keeping the data on disk).
- **Usage-based billing** — the WHMCS cron records real mailbox disk usage; an `InvoiceCreated` hook rewrites each invoice to bill **per tranche of GB** plus a line **per mailbox** that has ActiveSync (EAS) and/or MAPI/Exchange enabled.
- **Customer self-service** — from the client area, customers create/edit/delete mailboxes (password, display name, disk quota, EAS, MAPI), manage aliases and domain aliases, and follow a live DNS guide that verifies MX, SPF, DMARC and Autodiscover.
- **Deliverability** — the client dashboard validates the customer's DNS against the values you configure (primary + secondary SPF, expected Autodiscover host/SRV, DMARC) and shows green/red status pills with copy-ready records.

## Compatibility

| Requirement | Version |
|---|---|
| WHMCS | 8.0+ |
| SmarterMail | 16+ (REST API v1) |
| PHP | 8.0+ |
| SmarterMail OS | Windows (domain storage path is a Windows path) |

> **Start here:** read [Installation](installation.md) to connect the server and create the product, then [Product configuration](configuration.md) to set every option for your offer.
