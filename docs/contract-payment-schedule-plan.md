# Contract Payment Schedule (Cronograma Físico-Financeiro)

**Status as of 2026-08-18:** Phases **1, 2, 2.5, 3, 4 and 5 are done and committed on
`main`** — schema, cronograma card, approval-gated payment linking with undo, retenção,
medição por produção (boletim with two-way %/valor entry, parcela link, payment), and
payment-batch integration. Reviewed in 6 rounds; every migration is additive, so a deploy
needs `php artisan migrate` and `view:clear`.

Beyond the original plan, contract money is now dated in the reports (payment schedule and
accounts payable, see `docs/payment-schedule.md`) and feeds the new company-wide report
(`docs/company-financials.md`).

**All seven phases are complete.** What is left is optional polish, tracked in
`docs/wishlist.md` if it is ever wanted.

---

## 1. Concept and decisions (why it's built this way)

Feature: Brazilian-style payment schedule for subcontractor contracts. Research into
Brazilian practice (TCU medição/pagamento criteria, eventograma model for empreitada
por preço global, Caixa staged financing releases, boletim de medição structure)
established two distinct regimes, and the design follows them:

- **Regime A — Eventos (empreitada global / residential practice), IMPLEMENTED:**
  The contract carries parcelas: date-based (entrada, installments with vencimento)
  or milestone-based **eventos** (etapa concluída, with optional cost-code scope and
  data prevista). An evento pays only after its etapa is confirmed concluded by
  **vistoria** — a user clicks "Confirmar Etapa Concluída", recording who/when/notes.
  No partial release of an evento; partial *payments* against a released parcela are
  fine and tracked.
- **Regime B — Medição por produção (empreitada por preço unitário), PHASE 3:**
  No fixed parcelas; periodic boletim de medição measures % executed per cost code
  and the measured value (minus retention) is what gets paid.

Other confirmed decisions:
- **Approval is the only payment gate (user rule, 2026-08-17):** a parcela becomes
  payable only when it is approved — the vistoria for an evento, the liberação for a
  date parcela. A vencimento arriving does **not** make money payable (it only makes
  the parcela late). `isDue()` therefore means "approved (or released by an approved
  medição)" for every trigger type, and `release()` accepts date parcelas too.
- **A contract with a cronograma is paid through it:** when the contract has any
  parcelas, every payment must select one; the free-form payment stays only for
  contracts without a schedule.
- **An approval can be undone (user rule, 2026-08-17):** a mistaken approval is
  reverted with the orange ↺ action, which clears `released_at/by/notes` and logs a
  `release_reverted` audit row. It is refused — in the UI, in the component and in the
  model — once a payment or medição is linked; the payment must be deleted first.
- **Retenção contratual**: per-contract `retention_percent`; withheld proportionally
  to cash paid per approved medição; released at the end via an `is_retention_release`
  payment. Payments always store **net cash actually paid**.
- **Settlement semantics**: parcela status/balance compare gross scheduled vs
  **settled** (cash + retention withheld, grossed up per medição) so a medição-paid
  parcela reads as quitada while retention stays outstanding at contract level.
  Retention "held" is proportional to cash received — an approved-but-unpaid medição
  holds nothing.
- **Delays**: `due_date` is the vencimento for date parcelas and the **data prevista**
  for eventos; a parcela past that date and not fully settled shows
  "Em atraso há X dias" (partial payments keep it delayed).
- **Audit**: every create/update/delete/release of a schedule item is logged with
  actor and field-level old → new values (user requirement: "any change needs to be
  tracked, what was changed and who changed").
- **Statuses are derived, never stored** (from payments/medições/release state) so
  they cannot go out of sync. Contract-level `updateStatusFromPayments()` needed no
  changes — with retention, the outstanding balance equals the retained amount until
  the liberação, which is correct.

## 2. Database schema (all migrated)

| Migration | What |
|---|---|
| `2026_08_14_100000` | `contracts.retention_percent` decimal(5,2) nullable |
| `2026_08_14_100001` | `contract_schedule_items` — parcelas: `trigger_type` (date/milestone), nullable `due_date`, optional `budget_item_id` scope, exactly one of `percent` / nullable `amount` (cents), notes |
| `2026_08_14_100002` | `contract_measurements` — medições: unique (contract_id, measurement_number), status draft/approved/cancelled, snapshot `gross/retention/net_amount`, optional link to a schedule item, created/approved by+at |
| `2026_08_14_100003` | `contract_measurement_items` — per cost code: scheduled snapshot, previous/current cumulative %, period amount |
| `2026_08_14_100004` | `contract_payments` + `payment_batch_items` both get `contract_schedule_item_id`, `contract_measurement_id` (nullOnDelete), `is_retention_release` |
| `2026_08_15_100000` | `contract_schedule_items` release fields: `released_at`, `released_by`, `release_notes` |
| `2026_08_15_100001` | `contract_schedule_changes` — audit log: item FK (nullOnDelete) + description snapshot, action enum created/updated/deleted/released, JSON `changes`, `changed_by` |
| `2026_08_17_100000` | `contract_schedule_changes.action` enum widened with `release_reverted` (raw `MODIFY COLUMN`, the codebase pattern for enums) |

Money is stored as cents with dollar accessors, matching the codebase pattern.

## 3. Models and invariants (all enforced at model level, tested)

**`ContractScheduleItem`**
- `getScheduledAmount()` — percent rows compute from `Contract::getAdjustedAmount()`
  live (never stored), so change orders re-flow automatically.
- `getSettledAmount()` — cash + grossed-up retention; each payment counts through
  exactly ONE path (a payment with `contract_measurement_id` counts only via that
  medição's parcela; retention releases never count toward parcelas).
- `getStatus()` → pending / due / partially_paid / paid; `isDelayed()` / `getDelayDays()`.
- `release(User, ?notes)` — vistoria release; only milestone, only once.
- **Guards (throw LogicException):** value/trigger immutable once payments or
  medições are linked; percent+amount both set is rejected; percent ≤ 0 normalizes
  to null. Zero-scheduled parcelas are never "delayed" and never "due".
- **Audit hooks:** created/updated/deleted/released auto-logged to
  `contract_schedule_changes` (sort_order excluded; delete logs in `deleting` so the
  FK exists, then detaches via nullOnDelete keeping the description snapshot).

**`ContractMeasurement`**
- `approve(User)` — draft-only guard, wraps in transaction, snapshots gross/retention/net
  with the retention % in force (later contract changes never retro-apply).
- `isPaid()` — summed net payments vs `net_amount` (full-retention net=0 medição is
  settled at approval). `getSettledAmountRaw()` / `getRetentionWithheldRaw()` —
  approved-only, proportional to cash.
- **`createNumbered()` is the only safe creation path** — auto-numbering outside a
  transaction throws (lockForUpdate needs the transaction to hold).

**`Contract`** — `scheduleItems`, `measurements`, `scheduleChanges` relations;
`getRetentionHeld/Released/Outstanding()` (outstanding floored at 0 — release actions
must cap at it, see phase 4); `getScheduledTotal()` / `getUnscheduledAmount()`
(N+1-safe); `getChangeOrdersTotal()` uses loaded relation when available;
`generateContractNumber()` fixed to numeric max (lexicographic bug).

**`ContractScheduleChange`** — audit rows with feminine pt labels (Parcela Criada /
Alterada / Excluída / Liberada).

**`PaymentBatchEdit`** — `processApprovedItem()` is the single batch-item → payment
mapping (used by both approveItem and approveAll), already carrying the three new
link fields.

**`ResolvesContractBudget`** (Livewire concern) — shared budget + cost-code-options
lookup used by ContractSchedule and ContractChangeOrders.

## 4. UI (Livewire `Contract/ContractSchedule` on the contract show page)

- **Card** between Cost Codes and Change Orders: parcelas with scope chip, trigger
  info (vencimento / prevista, release info "Liberada em ... por ..."), red delay
  line, Previsto / Realizado / Saldo, status badge; footer with Total Previsto and
  amber "Saldo Não Programado" vs the adjusted contract.
- **Full-screen grid editor** ("Editar Cronograma") — Excel-like: all rows inline,
  add/remove/reorder, % ↔ valor per row with live calculated value, live footer
  totals, one-transaction save, per-cell validation errors, locked value/trigger
  cells for rows with linked money, wire:confirm on row removal. Refused deletions
  (row gained payments while grid was open) survive renumbered with a specific error.
- **Vistoria modal** (green ✓ on unreleased milestones) — "Confirmar Etapa Concluída"
  with optional inspection notes; race-safe (double release → friendly flash).
- **Histórico modal** — the audit trail: action badge, item, who, when, old → new
  per field.
- Listens to `change-orders-updated` (percent parcelas re-flow) and dispatches
  `schedule-updated` (ContractShow refreshes flashes/financials).
- Full pt_BR coverage with standard terms (Parcela, Liberação, Etapa, Vencimento,
  Data Prevista, Previsto, Realizado, Em Atraso, Vistoria...). Unused keys cleaned.

## 5. Approval → payment (Phase 4 step 1, DONE 2026-08-17)

Approving a parcela marks it payable, flips it to **A Pagar**, and logs the release.
The green ✓ now sits on **every** unreleased parcela; the modal says "Confirmar Etapa
Concluída" (vistoria wording, notes = observações da vistoria) for eventos and
"Aprovar Parcela para Pagamento" for date parcelas. An approved parcela with nothing
linked shows the orange ↺ "Reverter Aprovação" instead (wire:confirm, no modal); once
a payment or medição exists the action disappears and both the component and
`ContractScheduleItem::revertRelease()` refuse it.

The contract's Record Payment modal then:
- lists only **approved, still-owing** parcelas (description · saldo · vencimento);
- is **required** when the contract has a cronograma — no free-form payment; with no
  parcela approved yet the modal shows an amber note and hides the form and the submit;
- pre-fills the parcela balance (capped at the contract balance due) and, when the
  parcela is scoped to a cost code and no line was touched, that cost-code line;
- re-validates the choice at save (parcela paid/edited/deleted while the modal was
  open is refused) and rejects an amount above the parcela balance — partials are fine;
- writes `contract_schedule_item_id` on the payment, shows it as a chip in Payment
  History, and dispatches `payments-updated` so the cronograma card refreshes.

Contracts **without** a schedule keep the previous behaviour: amount pre-filled with
the whole balance due, no parcela involved.

**Partial cronogramas** (parcelas covering less than the adjusted amount, or a change
order raising it afterwards) keep their remainder payable: the select then offers
"Saldo não programado" as the empty choice, pre-filled with
`unscheduledRemaining` = unscheduled amount − payments already made off-schedule
(capped at the balance due). Choosing it records an unlinked payment, and the amount
is refused above that remainder. Once the cronograma covers everything, the parcela
becomes required again.

## 5.1 Retenção (Phase 4 steps 2–4, DONE 2026-08-17)

- **`retention_percent` input** on ContractCreate/ContractEdit next to the amount,
  0–50% (`Retention cannot exceed 50%.`), empty = none. Editing it only affects
  medições approved from then on — approved ones keep their snapshot.
- **Retention block** in the contract's financial card (only when the contract has a
  retention %): Retido / Liberada / A Liberar.
- **"Liberar Retenção"** action in the sidebar, shown only while outstanding > 0. The
  modal pre-fills the outstanding amount and takes method/date/reference/notes.
  `releaseRetention()` reopens the contract row **`lockForUpdate()` inside the
  transaction**, recomputes the outstanding there and caps the amount at it, so
  concurrent liberações can never hand back more than was withheld. It writes an
  `is_retention_release` payment (orange chip in Payment History) and itemizes it
  across the cost codes that still hold retention.
- **`Contract::getRetentionOutstandingByCostCode()`** is the split: each medição's
  withheld retention spread over its items by period amount, minus what earlier
  liberações already returned to each code. The allocation is floor + largest
  remainder, so the lines add up to the payment to the cent and no code gets back
  more than it holds.
- Retention only ever accumulates through **approved medições** (Regime B), so on a
  contract with no medições the block reads zeros and the action never appears —
  the feature degrades to invisible until phase 3 ships.

## 5.2 Medição por produção — Regime B (Phase 3, DONE 2026-08-17)

**`Contract/ContractMeasurements`** renders a **Medições** card on every contract, below
the cronograma.

- **New Measurement** creates the draft via `createNumbered()` (race-safe numbering),
  one row per cost code of `costCodeSchedule()`, with the period running from the day
  after the last approved medição to today — and never start > end when that period
  already reaches today. Only one draft per contract; a second click reopens it.
- **% anterior** comes from the last approved medição, falling back to the payment
  history so part-paid contracts continue from where they are.
- **The boletim can be filled from either side**: type % and the value follows, or type
  the value and `% = anterior + valor ÷ previsto`. The cumulative % is the stored truth,
  so a typed value snaps to the nearest 0,01% and the field is rewritten with the snapped
  figure — never a silent mismatch. Values clamp at 100%; going below the previously
  measured % is refused (`period_amount` is unsigned).
- **Parcela link** (optional, draft-only): the editor's Parcela select carries previsto /
  saldo / vencimento / status, and a context strip shows the selected parcela's
  previsto, realizado, saldo and this boletim's gross, warning when the medição exceeds
  what the parcela still owes. Approving a linked medição makes the parcela **A Pagar**;
  its payments settle the parcela grossed up by retention.
- **Approve** banks the on-screen values first, then snapshots gross/retenção/líquido and
  locks the medição (reopening is read-only). Empty boletins are refused.
- **Paying**: the medição row's Pagar action (or the payment modal's Medição select)
  pre-fills the remaining net and fills the cost-code lines from the boletim —
  proportional to each code's period amount, floor + largest remainder, carrying the
  medição's `percent_complete`. Editing the amount re-splits the lines. A parcela
  measured by an approved medição with net still owing is **removed** from the parcela
  select, so the same work can never be paid twice.
- **Cancel** is refused while payments point at the medição; drafts get delete.

Known edge, not yet handled: cancelling an approved medição after a later draft was
created leaves that draft's `previous_percent` at the cancelled baseline.

## 6. Next phases (in recommended order)

### Phase 4 — DONE (see §5 and §5.1)
The gross → retenção → net breakdown *inside* the payment modal belongs to medição
payments, so it lands with phase 3; parcela payments stay simple net cash.
Still true for any new caller: mutate payments then read retention/settled getters in
the same request and you must `refresh()` first (the getters sum loaded relations —
documented on `getRetentionHeld()`).

### Phase 3 — DONE (see §5.2)

### Phase 5 — DONE 2026-08-17
The batch table has a **Paga** column per contract row: a select listing that contract's
payable parcelas and approved medições with net still owing (`payableTargetsFor()` — the
same rules the contract page uses). Choosing one fills the row's amount with what the
item still owes and `saveDraft()` stores `contract_schedule_item_id` /
`contract_measurement_id` on the batch item, which `processApprovedItem()` already
carried to the payment.

`targetError()` gates **both** approval paths (`approveItem` and `approveAll`): a
contract with a cronograma or medições must name what the money pays, the target must
still be payable, and the amount may not exceed what it owes. So batch money can no
longer bypass the cronograma and leave a parcela payable a second time.

Both gates share one rule for money the cronograma does not cover:
`Contract::getUnscheduledRemaining()` (see `docs/payment-schedule.md`), so a partial
cronograma's remainder stays payable from the contract page *and* from a batch, capped at
the same figure in both.

**Schema limit worth knowing:** `payment_batch_items` is unique per
(batch, contract), so a batch can settle at most **one** parcela or medição per contract.
Paying two parcelas of the same contract needs two batches (or a payment on the contract
page). Lifting it would mean dropping that unique index.

### Phase 6 — DONE 2026-08-18
Both PDFs follow the house pattern (dompdf, DejaVu Sans, #3F5189, logo + scope header).

**Boletim de medição** — `ContractMeasurementPdfController` +
`pdf/contract-measurement.blade.php`, routes `measurements/{measurement}/pdf[/view]`,
printer button on every medição row. Carries the contract identification block, the
boletim grid (cost code / previsto / % anterior / % atual / valor do período), the
Bruto → Retenção (at the snapshotted %) → Líquido totals, the payment position
(líquido / pago / saldo), notes, the created-and-approved trail, and **signature lines for
both parties** — the reason the document is printed at all.

**Cronograma físico-financeiro** — `ContractSchedulePdfController` +
`pdf/contract-schedule.blade.php`, routes `contracts/{contract}/schedule/pdf[/view]`,
PDF button in the cronograma card header. Lists the parcelas with previsto / realizado /
saldo / status, the trigger with its date, delay lines, approval info, the totals with
the unscheduled balance, and the retention position when the contract has one.

Both render for every state (draft / approved / cancelled medição; contracts with and
without parcelas or retention).

### Phase 7 — DONE 2026-08-18
Audit script: extract every `__('…')` / `@lang('…')` literal from `resources/views` and
`app`, diff against `lang/pt_BR.json`.

Result: **2.014 strings used, 0 missing translations** — every translated string in the
codebase has a pt_BR key. Also verified: **0 placeholder mismatches** (a `:name` in a key
that the translation drops would silently print the placeholder), 0 empty translations, and
the 27 key/value pairs that are identical are legitimately identical in Portuguese
(Menu, Status, Total, Bairro, brand names).

Fixed — strings that were never wrapped in `__()` at all, so no key could have covered them:
- `contract-show.blade.php`: the page title and breadcrumb ("Contract " + number), the
  status label map (Active / Completed / Partially Paid / Paid / Cancelled), the
  "Not specified" and "Unknown" fallbacks, and "by {user}" in the payment history.
- `contract-change-orders.blade.php`: the "Unknown" fallback.
- `ContractPayment::getPaymentMethodLabel()` returned English; some call sites wrapped it
  in `__()` and some did not. It now translates at the source, which fixes every consumer
  (a second `__()` on a translated string is a no-op).

**180 orphan keys** (present in pt_BR.json, no literal call site) were deliberately left
alone: call sites like `__($item->getPaymentMethodLabel())` and `__($status)` resolve keys
at runtime, so a static scan cannot prove a key is dead. Deleting them risks silent
regressions for no gain.

Every sweep must end with the full-view compile check — a bad sweep once wrapped a PHP
property (`$changeOrder->{{ __('amount') }}`) and 500'd three pages:
`Blade::compileString()` over all views, then `php -l` on the output. **172 views, 0 errors.**

## 7. Process rules for this feature (user-set)

- **Never commit and never merge** — leave everything in the working tree; the user
  commits, merges and deploys (restated 2026-08-17: "never merge I'm the one merging").
- **After each phase: run /code-review, fix the findings, then STOP and wait for the
  user's OK before the next phase.**
- One page at a time, tested before moving on (CLAUDE.md).
- Docs live in this file; keep it updated as phases complete.

## 8. How to test what exists (browser)

1. Open any contract → the "Cronograma de Pagamento" card sits below Cost Codes.
2. "Editar Cronograma" → add an entrada (data fixa + valor) and eventos (etapa + %),
   watch live totals and Saldo Não Programado; save; reorder; try deleting.
3. Green ✓ on any parcela → confirm (vistoria for eventos, aprovação for date
   parcelas) with notes → status becomes A Pagar, release info shows under the
   trigger column.
4. Set a due/prevista date in the past → red "Em atraso há X dias" (an unapproved
   overdue parcela is Pendente **and** Em Atraso — being late does not make it payable).
5. "Histórico" → every change with who/when and old → new values.
6. Add a change order → percent parcelas and totals re-flow immediately.
7. "Registrar Pagamento" before approving anything → amber note, no form.
   After approving → the parcela appears in the Parcela select with its saldo,
   choosing it pre-fills the amount; saving links the payment (chip in Payment
   History) and the cronograma card updates its Realizado/Saldo/status at once.
   Try an amount above the parcela saldo → refused.
8. A contract with no cronograma → the modal has no Parcela select and pre-fills the
   whole balance due, exactly as before.
9. Orange ↺ on an approved parcela → confirm → back to Pendente, out of the payment
   dropdown, "Aprovação Revertida" in the Histórico. Approve again, record a payment,
   and the ↺ is gone (delete the payment to get it back).
10. Medições: "Nova Medição" → the boletim opens with % anterior pre-filled; type a %
    or a valor (either fills the other) → Bruto/Retenção/Líquido update live; pick a
    Parcela to see its previsto/realizado/saldo and the over-measure warning; Aprovar
    locks it; the Pagar action pre-fills the líquido and the cost-code lines.
11. Retenção: set a % on Editar Contrato → the Retenção block appears on the contract
    (zeros until a medição exists, so it is fully visible only after phase 3). With
    retention held, "Liberar Retenção" appears; releasing more than the outstanding
    silently caps at it, and the payment shows the orange "Liberação de Retenção" chip.

## 9. Review history (7 review rounds, 53 findings fixed total)

- **Round 7 (income receivables, 4 findings — all fixed):** the project page's "Total
  Income" card summed expected money into received, so it disagreed with the company
  report; "This Month" counted an expected record's reference date; the list ordered by
  `income_date` while showing `effectiveDate()`; the view modal ignored the new status.
  Also reversed a judgment call it surfaced — `markReceived()` no longer clears
  `due_date`, since destroying it on one click (no undo) loses what was expected for
  nothing.
- **Round 6 (phase 5, 8 findings — all fixed):** two were in code written to fix round 5 —
  the `LogicException` retry re-threw because `update()` leaves the model dirty (needs
  `refresh()` first), and batch rows could be created that no approval would ever accept
  (a cronograma contract with nothing approved yet now says so and hides the amount box).
  Plus: a stale saved target stayed invisible and unclearable; the medição line-ownership
  flag was still lossy (indexes are tracked now, and choosing a medição says it replaced
  the lines); `payTargets` missing from the save key merge; a typed batch amount being
  overwritten; a stale `colspan`; and an N+1 on the new Pays cell.

- **Round 5 (phase 3, 7 findings — 6 fixed, 1 became phase 5):**
  - *Fixed:* `openPayableItems()` could report more open money than the contract owed
    when payments did not settle a parcela (now scaled to the balance due — both reports
    were over-stating); selecting a parcela wiped hand-typed cost-code lines (line
    ownership is now tracked, and a manual edit keeps it); the medição dropdown was
    ordered newest-first because the relation's own `orderByDesc` won (`reorder()`);
    `openReleaseModal()` 404'd on a parcela deleted in another tab; a `LogicException`
    from the model guard escaped `saveGrid()`'s transaction and 500'd the whole save;
    a deferred amount change overwrote a manually edited medição line.
  - *Became phase 5:* batch payments bypassing the cronograma.

- **Round 4 (phase 4, 8 findings — 4 fixed, 4 accepted/deferred):**
  - *Fixed:* unscheduled contract money became unpayable once every parcela was paid
    (now the "Saldo não programado" choice, §5); switching parcelas left the previously
    pre-filled cost-code line behind and broke the "lines must add up" rule
    (`autoFilledItemIndex` tracks and clears it, and a manual edit releases the claim);
    the check-then-act race on release/revert threw an uncaught `LogicException` (500)
    on a double click — both now catch it and flash the friendly message; asking to
    release more retention than is outstanding was silently capped — it is now a
    validation error, with the lock-time cap kept as the safety net and its own flash
    when it actually bites.
  - *Deferred / accepted:* payment batches still create payments with no
    `contract_schedule_item_id` (nothing sets the batch-item link fields — that is
    exactly **phase 5**; until then a batch payment does not settle its parcela, so the
    parcela stays selectable and the same money can be paid again up to the contract
    balance — worth doing before batches meet cronogramas in production); retention
    stays at zero until medições exist (**phase 3**, by design); a retention release
    itemizes less than its amount when a medição line has no cost code (documented —
    the SOV fallback bucket absorbs it); `generateContractNumber()` is MySQL-only and
    read-then-insert racy without a unique index (pre-existing, outside this feature).

- **Round 1 (phase 1, 10 findings):** gross-vs-net settlement design bug, approve()
  guard+transaction, retention held only when cash withheld, dual-link double count,
  outstanding floor, isPaid by sum, batch link fields, percent-0 shadowing, numbering
  race, contract-number lexicographic bug.
- **Round 2 (phase 2, 9 findings):** flash feedback event, edit guard on linked
  parcelas, single-path payment attribution, approved-only settlement, full-retention
  edge, batch mapping dedup, dead-code removal, release-cap deferral documented.
- **Round 3 (phase 2.5, 10 findings):** change-orders listener, race-safe release,
  refused-deletion renumbering, model-level immutability guards, both-set rejection,
  transaction-enforced numbering, zero-scheduled delay edge, N+1 fixes, shared budget
  concern, wire:confirm + translation cleanup.
