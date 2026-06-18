---
title: Billing model
description: How the SmarterMail invoice is assembled — disk-usage tranches plus per-mailbox EAS/MAPI add-ons, the combined rate, and the activation threshold.
---

# Billing model

The recurring invoice for a SmarterMail service is **assembled dynamically** each billing cycle. The module's `hooks.php` listens on the WHMCS `InvoiceCreated` event and rewrites the invoice so it reflects **actual usage** at the moment it is generated.

There are two billed dimensions: **disk usage** (the base line) and **per-mailbox protocol add-ons** (EAS / MAPI).

## 1. Disk usage — the base line

- The WHMCS cron periodically calls the module's `UsageUpdate`, which queries the SmarterMail API for the domain's real disk usage and stores it (in MB) in `tblhosting.diskusage`. *(Schedule the cron daily — see [Installation](installation.md#5-schedule-the-usage-cron).)*
- At invoice time the hook reads `diskusage`, divides by **GB per billing tranche** (option 1), rounds **up** to whole tranches, and multiplies the product's recurring price by that count.

**Example** — price `$6.00`, tranche `10 GB`, customer at 21 GB:

```
ceil(21 / 10) = 3 tranches  →  3 × $6.00 = $18.00
Line description: "Email hosting (21.00 GB used of 30 GB billed)"
```

## 2. ActiveSync (EAS) and MAPI/Exchange — per mailbox

For each mailbox, the module knows whether **EAS** and/or **MAPI** is enabled, and adds invoice lines accordingly:

| Mailbox state | Line added | Price (option) |
|---|---|---|
| EAS only | `ActiveSync (EAS): bob@domain.com` | option 2 (`2.00`) |
| MAPI only | `MAPI/Exchange: bob@domain.com` | option 3 (`3.00`) |
| **EAS + MAPI** | `EAS + MAPI/Exchange: bob@domain.com` | option 4 combined (`4.50`) — **replaces** the two separate lines |

- A price of `0` (option 2/3) disables billing for that protocol — it can still be offered for free.
- The combined rate (option 4) is a **discount, not a sum**: a both-enabled mailbox bills `4.50`, not `2.00 + 3.00`. Set option 4 to `0` to bill the two separate lines even when combined.

## 3. The activation threshold (option 16)

By default a protocol must have been enabled for **≥ 1 cumulative day** within the period before it is billed. This avoids charging for brief trials and is **idempotent** (billed once per period regardless of how many times it's toggled).

Key behaviours:

- **Cumulative**, not continuous — 12 h + 12 h across the period counts as 1 day.
- **Deleted mailboxes that crossed the threshold are still billed** on the next invoice, with the active date range (*"Active from DD-MMM to DD-MMM"*) — so the charge can't be dodged by deleting the mailbox before invoicing.
- **`0` = live billing** — bills whatever is enabled at the exact moment the invoice is generated, with no minimum.

See [Product configuration → EAS / MAPI billing threshold](configuration.md#eas-mapi-billing-threshold) for the full implications.

## Setting the price

The **per-tranche price** is the product's normal recurring price (Products/Services → *Pricing* tab). Everything on the *Module Settings* tab (options 1–4) only sets **how that price is multiplied** and the **add-on rates** — see [Product configuration → Billing](configuration.md#billing).

> **Tip:** because the base line is rewritten to "N tranches", set the product's *Pricing* to the price of **one** tranche, not the expected total.
