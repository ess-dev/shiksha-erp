# Fees module

Built 2026-10-25 from the owner's prototype screenshots (Accountant: Dues, Payments, Fee structure; Parent: Fees). Refunds come in the next sprint; online payment by parents waits for the payment-provider decision.

## Purpose

Bill each enrolled student one installment at a time from their class's fee group, take payments at the counter, reconcile online and cheque payments, and show families what they owe and what they paid. Money is whole **paise** everywhere (`bigint *_paise`, the `Money` value object in `Domain/Shared`).

## Screens

| Screen             | Route                                                  | Needs                                                              | Notes                                                                                                                                                                                                                       |
| ------------------ | ------------------------------------------------------ | ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Fee dues           | `/fees/dues`                                           | `fees.view`                                                        | Unpaid installments of the current year, filters (class, installment, status, sort, search), bulk **Remind** (`fees.remind`), **Collect** link (`fees.collect`), audited CSV. 20 a page.                                    |
| Payments           | `/fees/payments`                                       | `fees.view`                                                        | Pending first, then failed-and-still-owed, then newest. Receipt, Details, Check status / Retry check (`fees.reconcile`), Send pay link (`fees.remind`), Mark cleared / Bounced for cheques (`fees.reconcile`), audited CSV. |
| Collect fee        | `/fees/collect`                                        | `fees.collect`                                                     | Not in the screenshots (designed from `docs/DESIGN.md`): search a student, tick installments, mode (cash, UPI, card, cheque, bank transfer), part payment, receipt.                                                         |
| Fee structure      | `/fees/structure`                                      | `fees.view` to read, `fee_structures.manage` school-wide to change | Class to fee-group table, group cards (edit heads, rename, delete), installment schedule, concessions.                                                                                                                      |
| Receipt print page | `/fees/receipts/{payment}`                             | `fees.view`                                                        | Print or save as PDF from the browser.                                                                                                                                                                                      |
| Parent Fees        | `/parent/fees` (+ `/statement`, `/receipts/{payment}`) | the guardian's own child                                           | Installments with breakdown, payments and receipts, failed-payment alert, session summary, printable statement. Past sessions read only. **No online payment yet.**                                                         |

## Data (tenant database, migration `2026_11_10_000000_create_fees_tables`)

`fee_installments` (per academic year: label, `due_on`, `ends_on`) · `fee_groups` (name, JSONB `heads` per installment) · `fee_group_classes` (class → group per year) · `fee_concessions` (percent or flat, one head, `kind` standard/sibling) · `fee_invoices` (one per student and installment; heads snapshot, gross, concession, net, paid; never deleted) · `fee_payments` (mode, status pending/success/failed, channel counter/online, receipt number, idempotency key, `details` JSONB) · `fee_payment_allocations` (payment → invoice, paise).

Landlord: `tenants.payment_gateway` (migration `2026_11_10_000000_add_payment_gateway_to_tenants_table`).

## Rules

- **Invoices**: all terms are billed when a student has a priced class group (a group with no heads bills nobody; saving its heads bills the waiting students). A student admitted mid-year is billed only for terms that have not ended on their admission day (`ends_on >= admitted_on`). The one-time admission fee in the prototype is **not built**.
- **Sibling concession**: created with the schedule (10% of tuition, illustrative — verify); applied automatically to a student who shares a guardian with an enrolled student admitted earlier. Concessions made on the Fee structure screen are definitions only: **assigning them to students is not built** (needs a student-profile screen). A percentage above 50 is flagged "needs the principal's approval", but no approval flow exists yet.
- **Re-pricing**: changing a group's heads, or moving a class to another group, re-prices only installments due after today with nothing paid. Paid, part-paid and past-due installments keep their amounts.
- **Payments**: confirmed counter payments apply oldest due date first, inside a transaction with the invoice rows locked; a cheque stays pending until cleared. The same idempotency key never records a second payment. If an installment was paid another way before a pending payment is confirmed, the part that cannot be applied is kept as `details.excess_paise` (shown as "due back"); refunds are the next sprint.
- **Receipts**: `PREFIX/YYYY/0001` (prefix is the school setting `fees.receipt_prefix`, default `RCPT`, no screen to edit it yet; year is the academic year's starting year).
- **Scope**: every list and record check goes through `FeeVisibility` (the section's campus / wing / sections scope of the permission). Out-of-scope records are 404. The top-bar campus switcher narrows Dues, Payments and Collect fee.

## Payment gateways

The app only talks to `Fees\Contracts\PaymentGateway` (`status(reference)` and a few flags). Drivers are listed in `config/shiksha.payment_gateways`; the **Super Admin chooses a driver per school** on School details → Payment gateway (landlord column, platform audit event). The only driver today is the `fake` test gateway, allowed in `local` and `testing` only: it reports whatever the accountant picks in the dialog ("Test gateway only: what the gateway reports"). Add a real provider by writing a class that implements the interface and listing it in the config; nothing else changes. Without a gateway the school can still collect at the counter and clear cheques; Check status / Retry check explain that no gateway is set up.

## Permissions

Existing: `fees.view`, `fees.collect`, `fees.refund` (critical, not used yet), `fee_structures.manage`, `concessions.apply` (not used yet). **New (role-template version 12)**: `fees.remind` (reminders and pay links), `fees.reconcile` (confirm, clear, re-check payments), both sensitive.

| Role              | Gets                                                                                                              |
| ----------------- | ----------------------------------------------------------------------------------------------------------------- |
| Principal / owner | everything (template 12)                                                                                          |
| Vice principal    | everything except critical and owner-only (template 12): view, collect, remind, reconcile, structure, concessions |
| Coordinator       | `fees.view` over their wing (template 12)                                                                         |
| Accountant        | view, collect, structure, concessions, refund (unchanged) + remind, reconcile (template 3)                        |

Existing schools see "template update available" and must apply it on Roles and access before non-owner roles see the new permissions.

## Actions (all audited)

`SetUpFeeSchedule` · `CreateFeeGroup` · `RenameFeeGroup` · `DeleteFeeGroup` · `SaveFeeGroupHeads` · `AssignClassToFeeGroup` · `AddConcession` · `BillStudent` (called by `CreateStudent`) · `RecordCounterPayment` · `SettleChequePayment` · `CheckGatewayPayment` (also re-check) · `SendPaymentLink` · `SendFeeReminders` · `ExportDues` · `ExportPayments`. Platform: `SetSchoolPaymentGateway`.

Audit keys: `fees.schedule_created`, `fees.group_created/renamed/deleted`, `fees.group_heads_saved`, `fees.class_moved`, `fees.concession_added`, `fees.student_billed`, `fees.payment_recorded`, `fees.cheque_settled`, `fees.payment_checked/rechecked`, `fees.payment_link_sent`, `fees.reminders_sent`, `fees.dues_exported`, `fees.payments_exported`, `platform.school_payment_gateway_set`.

## Settings and config

Config: `shiksha.fee_schedule` (terms 4, due day 15 — illustrative, verify), `shiksha.payment_gateways`. School setting: `fees.receipt_prefix`. Interface bindings: `FeeNotifier` → `LogFeeNotifier` (reminders and pay links are only logged until an SMS/push provider is chosen).

## Known limitations

- No refunds (next sprint), no late fees, no one-time admission fee, no assigning concessions to students, no approval flow for large concessions.
- Parents cannot pay online yet ("How to pay" explains the counter); no partial-payment minimum (the prototype's `minPartPayment`) because there is no online payment.
- Collect fee takes one student at a time (the prototype can add siblings to one payment).
- Fees columns/tabs on the Students list and profile, and the dashboard fee cards, still show "Not available yet" / sample numbers.
- Installment schedule editing (dates, number of terms) after creation, and a receipt-prefix screen, are not built.
- Importing students or enrolling them before a schedule exists bills nothing until the structure is set up (the warning on Fee structure says so).
- Past sessions have no fee data unless they were billed in this system.
