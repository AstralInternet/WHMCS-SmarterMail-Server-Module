---
title: Admin & automation
description: The domain lifecycle the module automates — create, suspend, unsuspend, terminate — plus admin login and the usage cron.
---

# Admin & automation

This page covers the **domain-level** actions WHMCS drives through the module — the lifecycle a customer can't touch from the client area. Most are automatic (triggered by orders, cancellations and the cron); a few are manual buttons on the admin service page.

## Provisioning lifecycle

| WHMCS action | What the module does on SmarterMail |
|---|---|
| **Create** | Creates the domain on the server using the storage path (option 5) and outbound IP (option 6), applies the max-users cap (option 7), and provisions the primary mailbox. Runs automatically when an order is accepted, or manually via *Create* on the service page. |
| **Suspend** | Disables the domain so mail is rejected/held and users can't log in — without deleting anything. Triggered automatically on overdue suspension, or manually. |
| **Unsuspend** | Re-enables a suspended domain. Triggered when the overdue invoice is paid, or manually. |
| **Terminate** | Removes the domain from SmarterMail. **Whether the data on disk is deleted depends on option 8** (*Delete data on termination*) — see [Product configuration → Termination](configuration.md#termination). |
| **Change password** | Resets the primary mailbox password. |

> **Terminate is destructive.** With option 8 = *yes*, terminating removes the domain **and all mail/data**. If you keep a grace window or do your own archival, set option 8 = *no* and clean up the files yourself later.

## Admin login (single sign-on)

From the admin service page you can jump straight into the domain's SmarterMail management as the domain administrator, without entering credentials. Use it to inspect or fix a customer's domain (content filtering, sharing, server-side rules) that isn't exposed in the WHMCS client area.

## Usage cron

The module's `UsageUpdate` runs on the **WHMCS system cron**. Each run queries the SmarterMail API for every active domain's real disk usage and writes it (in MB) to `tblhosting.diskusage`. That stored value is what the [billing hook](billing.md) reads to compute disk tranches.

- **Schedule it daily** — see [Installation → Schedule the usage cron](installation.md#5-schedule-the-usage-cron).
- If the cron never runs, `diskusage` stays at the last known value (or `0`), so disk billing won't reflect reality. A missing/under-scheduled cron is the usual cause of "disk usage looks wrong on the invoice".

## Where each setting takes effect

| Phase | Options consulted |
|---|---|
| Create | 5 (path), 6 (outbound IP), 7 (max users) |
| Terminate | 8 (delete data) |
| Usage cron | — (reads live API, writes `diskusage`) |
| Invoice (hook) | 1–4 (rates), 16 (threshold) |

Everything else (9–15, 17–23) shapes the **client area** — password hints, protocol offering, domain aliases and the DNS guide — and is covered in [Client area](client-area.md) and [Product configuration](configuration.md).
