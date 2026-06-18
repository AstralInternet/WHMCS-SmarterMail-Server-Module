---
title: Product configuration
description: Every one of the 23 Module Settings of the SmarterMail product, grouped, with what each option implies for billing, customers and deliverability.
---

# Product configuration — every Module Setting

All product behaviour is driven by the **Module Settings** of the WHMCS product (Configuration → Products/Services → your product → *Module Settings*). There are **23 options**. This page documents each one **and its implication** — what choosing a value actually does to billing, to what customers can do, and to deliverability checks.

> Options are referenced by their WHMCS slot (`configoption1` … `configoption23`) so you can cross-reference the raw product config if needed.

---

## Billing

These four set the rates. They define *what* is billed; the [Billing model](billing.md) explains *how* the invoice is assembled.

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 1 | **GB per billing tranche** | text | `10` | Size of one disk-usage increment. The product's recurring price is charged **once per started tranche**. *Implication:* with `10` and a `$6.00` price, a customer at 21 GB is billed 3 tranches = `$18.00`, shown as *"21.00 GB used of 30 GB billed"*. Smaller tranches = finer granularity but more line jumps. |
| 2 | **ActiveSync (EAS) price / mailbox / month** | text | `2.00` | Monthly fee added **per mailbox that has EAS enabled** (one invoice line each). *Implication:* set to `0` to disable EAS billing entirely (EAS can still be offered for free — see *Protocol offering*). |
| 3 | **MAPI/Exchange price / mailbox / month** | text | `3.00` | Monthly fee per mailbox with MAPI (Outlook Exchange mode) enabled. *Implication:* set to `0` to disable MAPI billing. |
| 4 | **Combined EAS + MAPI price / mailbox / month** | text | `4.50` | Discounted rate when a mailbox has **both** EAS and MAPI. *Implication:* it **replaces** the two separate prices (no stacking) — a both-enabled mailbox bills `4.50`, not `2.00 + 3.00`. Set to `0` to bill the two separate prices even when combined. |

> The recurring **price** lives on the product's *Pricing* tab, not here — these options only set the **multipliers and add-on rates**.

---

## SmarterMail settings

How the domain is created on the SmarterMail server.

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 5 | **Domain storage path on the server** | text | `C:\SmarterMail\Domains\` | Where the domain's files live on the Windows server. The domain name is appended automatically (`…\Domains\client.com\`). *Implication:* must be a valid path on **that** server; a wrong path makes *Create* fail. |
| 6 | **Outbound IP address** | text | `default` | The IP used to send this domain's outbound mail. *Implication:* keep `default` to use the server's default IP; set a specific IP when you sell **dedicated sending IPs** per customer (affects SPF/reputation). |
| 7 | **Max users per domain** | text | `0` | Mailbox cap for the domain. *Implication:* `0` = unlimited; a positive number lets you build tiered plans (e.g. a "5 mailboxes" plan). The cap is enforced when creating mailboxes. |

---

## Termination

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 8 | **Delete data on termination** | yes/no | `yes` | On *Terminate*: **yes** removes the domain **and all its mail/data** from SmarterMail; **no** removes the domain from SmarterMail but **leaves the files on disk**. *Implication:* choose **no** when you do your own archival/retention or want a grace window before manual deletion — but those files keep consuming disk until you remove them. |

---

## Password policy

These four are **display/validation hints** shown to customers when they set or change a mailbox password in the client area. **They do not configure SmarterMail itself.**

| # | Setting | Type | Default |
|---|---|---|---|
| 9 | **Minimum password length** | text | `8` |
| 10 | **Require an uppercase letter** | yes/no | `yes` |
| 11 | **Require a digit** | yes/no | `yes` |
| 12 | **Require a special character** | yes/no | `yes` |

> **Critical implication — keep these in sync.** SmarterMail enforces its **own** password rules (Admin → Configuration → Password Requirements). These options only tell the *client area* what to display and pre-validate. If they don't match SmarterMail's real rules, customers will pass the client-area check and then be rejected by SmarterMail (or vice-versa). Set them to the **same values** you configured on the server.

---

## Protocol offering

Whether customers may toggle EAS / MAPI themselves. **Independent of the prices in options 2–4.**

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 14 | **Offer ActiveSync (EAS) to customers** | yes/no | `yes` | **yes** → the EAS checkbox appears when a customer creates/edits a mailbox; **no** → the checkbox is hidden and only an admin can enable EAS (via SmarterMail or WHMCS admin). |
| 15 | **Offer MAPI/Exchange to customers** | yes/no | `yes` | Same, for MAPI. |

> **Why this is separate from price:** you can *offer EAS for free* (option 14 = yes, option 2 = `0`), or *bill EAS but not let customers self-enable it* (option 14 = no, option 2 = `2.00`, admin enables it manually). The offering toggles control **UI visibility**; the prices control **billing**. The two are deliberately decoupled.

---

## EAS / MAPI billing threshold

| # | Setting | Type | Default | What it does |
|---|---|---|---|---|
| 16 | **EAS/MAPI billing threshold (days)** | text | `1` | Minimum **cumulative** days a protocol must have been enabled within the billing period before it is billed for that month. |

**Implications — read before changing:**

- **Cumulative time.** Enabled 12 h + disabled + re-enabled 12 h = 1 day → billable at threshold `1`. Brief trials under the threshold are **not** billed.
- **Idempotent.** A mailbox/protocol is billed **once per period**, even if toggled on/off many times.
- **Deleted mailboxes still bill.** If a mailbox crossed the threshold and is then deleted, it is **still billed** on the next invoice with the active date range (*"Active from DD-MMM to DD-MMM"*) — so a customer can't avoid the charge by enabling, using, then deleting.
- **`0` = live billing.** A value of `0` disables the threshold and bills on the **state at invoice time** (whatever is enabled the moment the invoice is generated).

---

## Domain aliases

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 17 | **Max domain aliases** | text | `0` | How many **domain aliases** the customer may add from the client area. A domain alias lets the primary domain's mailboxes also receive mail addressed to **another domain name** (e.g. `client.ca` → `client.com`). *Implication:* `0` **hides the feature entirely** (the block doesn't appear in the client area); `N > 0` lets the customer add up to N. The limit is enforced server-side (PHP), so it can't be bypassed from the browser. Negative values are coerced to `0`. |

---

## SPF

The client-area DNS guide checks the customer's SPF record and shows a green/red status.

| # | Setting | Type | Default | What it does |
|---|---|---|---|---|
| 13 | **Primary SPF mechanism** | text | `include:mail.example.com` | The mechanism the customer **should add** (e.g. `include:mail.example.com` or `ip4:1.2.3.4`). This is the one **shown and recommended** in the DNS guide. |
| 18 | **Secondary SPF mechanisms** | text | *(blank)* | Additional mechanisms **accepted as valid**, comma-separated (e.g. `include:old-mail.server.com, ip4:203.0.113.5`). |

**Implications:**

- SPF is considered **valid** if the **primary OR any secondary** mechanism is found in the customer's DNS. Only the **primary** is displayed/recommended; secondaries are used **for validation only** (the green/red pill).
- Use secondaries during **migrations / multi-server** setups so both old and new sending hosts validate while the customer transitions. Leave blank if you have a single sending host.

---

## Autodiscover

The dashboard verifies two records so Outlook / Apple Mail auto-configure:

- **CNAME/A** — `autodiscover.{domain}` should point to the host.
- **SRV** — `_autodiscover._tcp.{domain}` should target the host on port **443**.

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 19 | **Expected Autodiscover host** | text | *(blank)* | Target expected for `autodiscover.{domain}` (CNAME/A). *Implication:* **blank → falls back to the WHMCS server Hostname.** Override only when Autodiscover should point at a different name than the server hostname (e.g. a dedicated `mail.astralinternet.com`). |
| 20 | **Autodiscover SRV target** | text | *(blank)* | Target expected for the `_autodiscover._tcp.{domain}` SRV (port 443 is forced). *Implication:* blank → falls back to the WHMCS server Hostname. |

---

## DMARC

| # | Setting | Type | Default | What it does — and the implication |
|---|---|---|---|---|
| 21 | **Check DMARC** | yes/no | *(off unless set)* | Shows the DMARC mini-card and checks the `_dmarc.{domain}` TXT record. *Implication:* turn **off** for products where DMARC compliance is out of scope — the card isn't shown and a missing DMARC won't flag the customer as having a problem. |
| 22 | **Suggested DMARC RUA** | text | *(blank)* | Address pre-filled in the "Send Aggregate Reports To" field of the DMARC generator. The customer can edit it before copying. E.g. `dmarc-reports@astralinternet.com`. |
| 23 | **Suggested DMARC policy** | dropdown (`none` / `quarantine` / `reject`) | `none` | Policy pre-selected in the DMARC generator. *Implication:* **`none`** = observation only (safest to start); **`quarantine`** = suspicious mail goes to spam; **`reject`** = blocked outright. Start at `none` and tighten once reports look clean. |

---

## Quick reference — all 23 options

| # | Setting | Default |
|---|---|---|
| 1 | GB per billing tranche | `10` |
| 2 | ActiveSync (EAS) price / mailbox / month | `2.00` |
| 3 | MAPI/Exchange price / mailbox / month | `3.00` |
| 4 | Combined EAS + MAPI price / mailbox / month | `4.50` |
| 5 | Domain storage path on the server | `C:\SmarterMail\Domains\` |
| 6 | Outbound IP address | `default` |
| 7 | Max users per domain | `0` (unlimited) |
| 8 | Delete data on termination | `yes` |
| 9 | Minimum password length | `8` |
| 10 | Require an uppercase letter | `yes` |
| 11 | Require a digit | `yes` |
| 12 | Require a special character | `yes` |
| 13 | Primary SPF mechanism | `include:mail.example.com` |
| 14 | Offer ActiveSync (EAS) to customers | `yes` |
| 15 | Offer MAPI/Exchange to customers | `yes` |
| 16 | EAS/MAPI billing threshold (days) | `1` |
| 17 | Max domain aliases | `0` (disabled) |
| 18 | Secondary SPF mechanisms | *(blank)* |
| 19 | Expected Autodiscover host | *(blank → server hostname)* |
| 20 | Autodiscover SRV target | *(blank → server hostname)* |
| 21 | Check DMARC | *(off)* |
| 22 | Suggested DMARC RUA | *(blank)* |
| 23 | Suggested DMARC policy | `none` |
