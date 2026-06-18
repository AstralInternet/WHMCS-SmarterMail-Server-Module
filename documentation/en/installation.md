---
title: Installation
description: Install the module, add the SmarterMail server in WHMCS, create the product, and schedule the usage cron.
---

# Installation

## 1. Requirements

- **WHMCS 8.0+**, **PHP 8.0+**.
- A **SmarterMail 16+** server (Windows) with its **REST API v1** enabled.
- A SmarterMail **system administrator** (sysadmin) account — the module authenticates with it to create and manage domains.

## 2. Install the module files

Copy the module folder into your WHMCS installation:

```
modules/servers/smartermail/   →   <whmcs>/modules/servers/smartermail/
```

No database changes are required — WHMCS discovers the module automatically.

## 3. Add the SmarterMail server in WHMCS

In WHMCS, go to **Configuration → System Settings → Servers** and **Add New Server**:

| Field | Value |
|---|---|
| **Name** | A label for this SmarterMail server |
| **Hostname** | Your SmarterMail server address, e.g. `mail.yourserver.com` |
| **Module** | `SmarterMail (Email Hosting)` |
| **Port** | `443` (HTTPS) or `9998` (SmarterMail default HTTP) |
| **Secure** | Enabled when using HTTPS |
| **Username** | The SmarterMail **sysadmin** account |
| **Password** | That sysadmin account's password |

The **Hostname** you set here is also the default used by the client-area DNS guide for Autodiscover when you leave those product options blank — see [Product configuration → Autodiscover](configuration.md#autodiscover).

## 4. Create the product

In **Configuration → System Settings → Products/Services**, create or edit a product:

1. **Module Settings tab** — choose `SmarterMail (Email Hosting)` and the server (or server group) from step 3.
2. **Module Settings** — the 23 options that define your offer (billing rates, password policy, which protocols customers may enable, DNS values…). **Every option and its implication is documented in [Product configuration](configuration.md).**
3. **Pricing tab** — set the recurring price to the **price per tranche of GB** (see below).

> The monthly price you set is **not** a flat price — it is multiplied by the number of disk-usage tranches the customer consumes. Example: a price of `$6.00` with *GB per billing tranche* `= 10` bills a customer using 21 GB at `3 × $6.00 = $18.00`. See [Billing model](billing.md).

## 5. Schedule the usage cron

Disk-usage billing relies on WHMCS calling the module's `UsageUpdate` function, which queries the SmarterMail API for each domain's real disk usage and writes it to `tblhosting.diskusage`.

- Make sure the **WHMCS system cron** is configured (Utilities → System → System Cleanup / the documented WHMCS cron).
- **Run it at least daily** so invoices reflect current usage.

Without the cron, `diskusage` stays at its last known value and invoices may bill stale usage.

## 6. Verify

- Place a test order (or use *Create* on an existing pending service). The domain should appear in SmarterMail with a hidden admin account.
- Open the service in the WHMCS client area — you should see the dashboard, the mailbox manager and the DNS guide.

If *Create* fails, the most common causes are: wrong sysadmin credentials, the REST API disabled on SmarterMail, or a firewall blocking the API port.
