# Contract Payment Schedule (Cronograma Físico-Financeiro)

**Status as of 2026-08-17:** Phases 1, 2 and 2.5 are complete, code-reviewed, fixed and
functionally tested (committed on the branch). **Phase 4 (approval-gated payment linking,
undo of an approval, and retenção) is done and tested, uncommitted in the working tree**
plus one new migration to run. All work lives on branch
**`feature/contract-payment-schedule`**; `main` is untouched, so the client on the current
system is unaffected.

**Next: Phase 3 — medição por produção (Regime B)**, which is what actually starts
withholding retention — see "Next phases" below.

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

## 6. Next phases (in recommended order)

### Phase 4 — DONE (see §5 and §5.1)
The gross → retenção → net breakdown *inside* the payment modal belongs to medição
payments, so it lands with phase 3; parcela payments stay simple net cash.
Still true for any new caller: mutate payments then read retention/settled getters in
the same request and you must `refresh()` first (the getters sum loaded relations —
documented on `getRetentionHeld()`).

### Phase 3 — Medição por produção (Regime B)
- `Contract/ContractMeasurements.php`: draft → measure % per cost code → approve
  (boletim: scheduled / % anterior / % atual / valor período / retenção / líquido).
- **MUST use `ContractMeasurement::createNumbered()`** and enforce one draft per
  contract before creating. Items pre-fill previous % from the last approved medição,
  falling back to `costCodeSchedule()` for legacy contracts. Items lock on approval.
- Paying an approved medição: payment linked via `contract_measurement_id`, amount
  pre-filled with net; auto-create `contract_payment_items` at **net per cost code**
  with `percent_complete` = medição current % (keeps the existing SOV grid and the
  "items must sum to payment" validation working).
- Cancelling: blocked if the medição has payments (delete the payment first).

### Phase 5 — Payment batch integration
- Suggested items when building a batch: released/due parcelas and approved-unpaid
  medições (net amount) for the filtered vendor. Selecting one stores the link fields
  on the batch item; `processApprovedItem()` already carries them to the payment.

### Phase 6 — PDFs (dompdf, existing pattern: `buildPdfData()`, DejaVu Sans, #3F5189)
- Cronograma físico-financeiro (parcelas, previsto/realizado/saldo, delays, releases).
- Boletim de medição per medição.

### Phase 7 — Translation sweep
- Every phase already ships pt_BR keys with its views (project rule); do a final
  audit for coverage/regressions when the feature completes.

## 7. Process rules for this feature (user-set)

- **Never commit** — leave everything in the working tree; the user commits.
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
10. Retenção: set a % on Editar Contrato → the Retenção block appears on the contract
    (zeros until a medição exists, so it is fully visible only after phase 3). With
    retention held, "Liberar Retenção" appears; releasing more than the outstanding
    silently caps at it, and the payment shows the orange "Liberação de Retenção" chip.

## 9. Review history (4 review rounds, 33 findings fixed total)

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
