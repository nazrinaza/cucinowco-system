# CuciNow.co System — Review & Improvement Roadmap (Claude Code)

Snapshot: `origin/main` @ `2ad2646` (13 Nov 2026), cloned into `cucinowco-system-byClaude`, working branch `claude/improvements`.

Baseline: `php artisan test`: **134/134 pass** (after `npm run build`; without the Vite manifest, 44 tests fail with a 500 error).

> ⚠️ This copy's `origin` still points to `github.com/nazrinaza/cucinowco-system`. Never push `main` or `deploy` from here. Push only the `claude/*` branches (or change the remote to a new repo) so Codex's work isn't touched.

## 1. What the system has today

| Area | Features |
|---|---|
| Public site | BM/EN landing page, service pages, sitemap, SEO, Google Tag, quote estimator (Livewire), site-visit form, WhatsApp enquiry form and redirect |
| Sales pipeline | Site visit → assessment + photos → Quote (several per visit) → Proforma (synced from the quote, deposit) → Invoice → Payment + receipt |
| Operations | Bookings + FullCalendar, staff assignment, Google Calendar sync (bookings + site visits) |
| Marketing | Subscribers (segments, import/export), newsletter campaigns (sent through Resend, paced for shared hosting), analytics, referral QR sources |
| Agents | Separate portal (`agents.cucinow.co`), registration + email verification, HQ approval, referral code, introducer attribution, leads/stage tracking, visit analytics, marketing media library |
| Integrations | Resend (email + webhooks), Meta WhatsApp Cloud API, Google Calendar OAuth |
| Infra | Laravel 13, Livewire 4, Tailwind 4, database queue run by cPanel cron, `deploy` branch built by GitHub Actions |

## 2. Agents module: what's missing for percentages and commission

The current state (`app/Support/AgentReporting.php`) only has **one** percentage, `conversion = closed / leads × 100`. The portal says outright: *"Commission and payout settings will be announced by HQ. No earnings are calculated yet."*

The attribution chain is already in place and is enough to calculate commission:

```
Agent → referral_source_id → SiteVisitRequest.referral_source_id
      → Quote(s) (site_visit_request_id | legacy quote_id)
      → Invoice(quote_id) → Payments
```

### Proposed design

**Tables**
- `commission_plans`: `name`, `rate_percent decimal(5,2)`, `basis` (`net_sales` | `total` | `collected`), `min_invoice`, `cap_per_invoice`, `introducer_rate_percent` (optional 2-tier), `is_default`, `effective_from`
- `agents.commission_plan_id` (nullable, falls back to the default plan) + `agents.commission_rate_override` (for special deals)
- `agent_commissions` (ledger): `agent_id`, `invoice_id`, `type` (`direct` | `introducer`), `basis_amount`, `rate_percent` (**snapshot**), `amount`, `status` (`pending` → `approved` → `paid` / `void`), `approved_by_user_id`, `paid_at`, `payout_reference`, unique (`invoice_id`, `type`)

**Flow**
1. `InvoiceController::payment()` → when an invoice reaches `paid`, dispatch `CommissionCalculator::recordFor($invoice)` (idempotent, guarded by the unique key).
2. HQ reviews it → approve → mark paid (batch payout + CSV export).
3. Refund or cancelled invoice → `void`.
4. Portal: "Earnings" card (Pending / Approved / Paid this month), plus a per-lead column showing the commission amount.

**Extra percentages for the dashboard**: visit→lead rate, lead→closed (already there), closed→paid rate, average deal value, total sales generated, and effective commission %.

**Technical rules**
- Calculate in **sen (integers)** or with `bcmath`, never with plain `float` (avoids RM 0.01 rounding errors).
- Snapshot the rate in the ledger so later plan changes don't alter past commissions.
- Wrap it in `DB::transaction` + `lockForUpdate` on the invoice (same pattern as `AgentController::update`).

**Business decisions HQ needs to make before coding:** basis (subtotal after discount, before tax?), standard rate, whether introducers get an override, whether it's paid on full payment or on deposit, payout cycle.

## 3. Other improvement ideas (by priority)

### Correctness / risk
1. **Payment race condition**: `InvoiceController::payment()` has no transaction or lock. A double-clicked submit can record two payments, because `max:balance` is validated against a stale balance. Fix: `DB::transaction` + `Invoice::lockForUpdate()` and recheck the balance inside it.
2. **Dashboard "Revenue this year"** sums `invoices.amount_paid` filtered by `issued_at`. A December invoice paid in January lands in the wrong year. Use `payments.sum(amount)` filtered by `paid_at`.
3. **Commission attribution**: `AgentReporting::summary` runs about 6 queries per call. As leads grow, consider caching it or adding a `referral_source_id` snapshot on `quotes`/`invoices`.

### UX & visuals
4. Sidebar icons are text badges (`OV`, `SV`, ...). Switching to inline SVG icons (Lucide/Heroicons) gives an instant visual upgrade.
5. Dashboard: add a monthly revenue chart, a funnel (site visit → quote → booking → paid), and a "Needs attention" list (overdue invoices, quotes about to expire, unassigned bookings).
6. Agent portal: progress bar per lead (stage timeline), leaderboard (optional, anonymised), shareable QR download.
7. Dark mode + `prefers-reduced-motion` for the admin area.
8. Global search (customer / phone / reference number) in the topbar.

### Engineering
9. Several Blade views are written on a single very long line. Split them into components (`<x-admin.metric>`, `<x-admin.card>`) so they're easier to review.
10. Add `CLAUDE.md` / `AGENTS.md` describing the conventions (cPanel, no SSH, DB queue) so both Claude and Codex follow the same rules.
11. Audit log for money-related changes (payments, discounts, commissions).
