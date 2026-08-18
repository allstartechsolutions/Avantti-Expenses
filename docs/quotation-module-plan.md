# Quotation Module — research and plan

**Status:** plan agreed, nothing built. Written 2026-08-18.

**Decisions taken with the owner (2026-08-18):**

| Question | Decision |
|---|---|
| Material path | **Quote → Purchase Order → Expense** — the PO module's existing approval creates the expense |
| Split award | **Supported**, but awarding the whole quote to one vendor is the default |
| Minimum proposals | **Warn under 3, block under 2** |
| Requisition (solicitação de compra) | **Included** — the flow starts with a site requisition, not with the quote |

This is the **buy side**: asking several vendors what they would charge, comparing the
offers, negotiating, and awarding one. It is not the client-facing quote — that is the
existing Estimate module (`docs/estimate-module.md`), which stays as it is.

---

## 1. How this is actually done in Brazil

The Brazilian construction purchasing flow is well standardised, and every ERP in that
market (Sienge is the reference) implements the same chain:

```
necessidade → solicitação de compra → cotação (≥3 fornecedores)
   → mapa comparativo (equalização) → negociação → escolha justificada
   → pedido de compra   (material)  →  recebimento → nota fiscal → contas a pagar
   → contrato + medições (serviço)  →  medição → pagamento
```

### The vocabulary that matters

| Term | What it is | In this app |
|---|---|---|
| **Solicitação de compra** | Internal requisition: someone on site says what is needed | **new — `purchase_requisitions`, phase 1** |
| **Cotação** | The round of price requests sent to vendors | the new module |
| **Proposta** | One vendor's answer to that round | `quotation_vendors` + their item prices |
| **Mapa de cotação / mapa comparativo** | The side-by-side comparison sheet | the comparison screen |
| **Equalização de propostas** | Normalising offers so the comparison is honest | see below |
| **Rodada de negociação** | A round of haggling, recorded rather than overwritten | `quotation_negotiations` |
| **Pedido de compra** | Purchase order for materials | existing PO module |
| **Contrato + medições** | Service agreement measured as it progresses | existing Contract module |

**Naming warning:** in pt_BR, *orçamento* means both the sell-side quote and the cost
budget, and this app already uses **Budget** and **Estimate** for those. The buy-side word
is **Cotação** — that is what the BR GUI should say, so nothing collides.

### Equalização — the part most systems get wrong

Comparing raw prices is considered wrong practice; proposals are normalised first on:

1. **unit of measure** (kg vs 25 kg bag vs m³);
2. **freight** — CIF (vendor pays) vs FOB (we pay), added to the comparable total;
3. **taxes**, which vary with the vendor's tax regime;
4. **lead time** (prazo de entrega);
5. **technical spec equivalence** — did they quote the same thing, or a substitute brand;
6. **minimum quantity / packaging**, which changes the real unit cost;
7. **payment terms** — à vista vs 30/60/90 days is a real price difference;
8. **warranty and vendor history**.

The customary rule is **at least three proposals**; the client asked for at least two. The
agreed rule keeps both: **block below two, warn below three.**

The choice must be **justified and recorded** — the map exists precisely so a decision that
is not the lowest price can be defended later. That is why the award needs a reason field,
not just a winner.

### Is "service → contract, item → expense" standard? — Yes, essentially

That is exactly how the Brazilian ERPs split it: the supplies module ends in a **pedido de
compra** for materials, and services live in a separate **Contratos e Medições** module. The
client is describing standard practice, not a house rule.

One correction for this codebase: **materials should not go straight to an expense.** The PO
module already does that conversion — `PurchaseOrder::createExpenseFromPO()` creates the
expense when a PO is approved. So the honest mapping here is:

| Quote is for | Becomes | Why |
|---|---|---|
| **Service** | **Contract** (`contracts`) | already has payment schedule, medições, retention, change orders |
| **Material** | **Purchase Order** → Expense on approval | reuses the approval workflow and the existing PO→expense conversion |
| Material | **never straight to an expense** | the PO carries the approval, and the expense is its output |

---

## 2. Proposed data model

Follows the dual-FK rule from `docs/project-jobsite-parity-rule.md` (`project_id` required,
`job_site_id` nullable) and stores money in cents (`docs/monetary-storage.md`).

### `purchase_requisitions` — the ask from the site
The chain starts here: whoever is on site says what is needed, a manager approves it, and
only then does procurement quote it.

`project_id`, `job_site_id` (nullable), `requisition_number` (REQ-0001), `type`
(`material` | `service`), `title`, `justification`, `needed_by`, `priority`
(`low` | `normal` | `urgent`), `status`, `requested_by`, `reviewed_by`, `reviewed_at`,
`review_notes`, `cost_code_id` / `budget_item_id` (nullable), `created_by`, timestamps.

Status: `draft → pending → approved → quoted → fulfilled`, plus `rejected` and `cancelled`.
`quoted` and `fulfilled` are **derived from the quotations that reference it**, mirroring the
Sienge idea that a requisition is pending / partially / totally attended.

### `purchase_requisition_items` — what was asked for
`purchase_requisition_id`, `catalog_item_id` (nullable), `budget_item_id` (nullable),
`item_name`, `description`, `quantity`, `unit`, `sort_order`.

These are the rows the quotation copies, so the scope the vendors price is literally the
scope the site asked for. A requisition may be quoted in **more than one** quotation (split
by vendor speciality), so `quotations.purchase_requisition_id` is nullable and many-to-one.

### `quotations` — the round
`project_id`, `job_site_id` (nullable), `purchase_requisition_id` (nullable — a quote can
still be raised directly), `quotation_number` (COT-0001), `type`
(`material` | `service`), `title`, `description`, `needed_by` (date the goods/service are
needed), `responses_due_at` (deadline for vendors), `status`, `cost_code_id` /
`budget_item_id` (nullable, to compare against budget), `awarded_vendor_id` (nullable),
`awarded_at`, `awarded_by`, `award_reason`, `converted_type` + `converted_id` (contract, PO
or expense), `created_by`, timestamps.

Status: `draft → sent → comparing → negotiating → awarded → converted`, plus `cancelled`.

### `quotation_items` — the shared scope
One list of what is being asked for, so every vendor prices the **same** thing (the basis of
a fair map): `quotation_id`, `catalog_item_id` (nullable), `budget_item_id` (nullable),
`item_name`, `description`, `quantity`, `unit`, `sort_order`.

### `quotation_vendors` — one row per invited vendor = one proposal
`quotation_id`, `vendor_id`, `status` (`invited` | `responded` | `declined` | `awarded` |
`rejected`), `responded_at`, `proposal_valid_until` (validade), `lead_time_days` (prazo),
`payment_terms` (text or the existing Net-15/30/60/90 list), `freight_type` (`cif` | `fob`),
`freight_amount`, `discount_amount`, `tax_amount`, `notes`, `created_by`, timestamps.

### `quotation_vendor_items` — the prices
`quotation_vendor_id`, `quotation_item_id`, `unit_price`, `total_amount`, `is_unavailable`
(vendor cannot supply this line), `offered_brand` / `offered_spec` (they quoted a
substitute), `notes`. Unique on (`quotation_vendor_id`, `quotation_item_id`).

### `quotation_negotiations` — the rounds, kept
`quotation_vendor_id`, `round` (1, 2, 3…), `previous_total`, `new_total`, `note`,
`negotiated_by`, `negotiated_at`. Negotiating rewrites the prices **and** leaves a row here,
so "we got them down from 48k to 41k in two rounds" survives.

### Attachments
The existing polymorphic attachments system (`docs/expense-audit-and-attachments.md`) on both
`Quotation` and `QuotationVendor` — the vendor's PDF proposal belongs on their row.

---

## 3. The comparison screen (mapa comparativo)

Full-page (per the Design Standard in `CLAUDE.md`): **items as rows, vendors as columns**.

- each cell: unit price and line total, with the best price per row highlighted, and a
  clear marker for "not supplied" or "substitute offered";
- column footer: subtotal → freight → tax → discount → **equalized total**, plus lead time,
  payment terms and proposal validity;
- the winning column highlighted, with savings shown against the highest offer and against
  the budget item when one is linked;
- a per-row award tick if split awards are enabled (open question 3);
- expired proposals flagged (validade past) rather than silently compared;
- PDF export of the map, following the existing report-PDF controller pattern.

---

## 4. Award and conversion

1. **Award** — pick a vendor, write the **reason**, confirm. Status → awarded; losing
   proposals marked `rejected`; everything frozen for audit.
   - **Whole-quote award is the default**; a "split across vendors" toggle reveals a per-item
     winner picker, and then the conversion produces **one contract/PO per winning vendor**.
   - **Fewer than 2 responded proposals blocks the award**; fewer than 3 shows a warning the
     user can acknowledge, which is the customary BR minimum.
2. **Convert** — one action, prefilled from the award:
   - **service →** contract create form: vendor as subcontractor, amount = awarded total,
     quotation items seeded as budget allocations / schedule lines;
   - **material →** purchase order create form: vendor as supplier, items copied 1:1 into
     `purchase_order_items`; approval then creates the expense as it does today;
   - links both ways (`quotation_id` on the contract/PO, `converted_*` on the quotation) so
     each side shows where it came from.
3. **Feed the catalog** — awarded unit prices update `catalog_item_price_history`, so the
   next quotation starts from real recent prices.

---

## 5. Build order (one page at a time, per CLAUDE.md rule 7)

| Phase | Deliverable |
|---|---|
| 1 | Requisition: migrations, models, index/create/detail, approve–reject with audit trail |
| 2 | Quotation round: migrations, models, create from a requisition (or standalone), items, invited vendors |
| 3 | Proposal entry per vendor (prices, terms, freight, validity, attachments) |
| 4 | Comparison map, full page, with equalization |
| 5 | Negotiation rounds |
| 6 | Award with justification, whole or split, with the 2/3-proposal rule |
| 7 | Conversion to contract / PO, with backlinks both ways |
| 8 | PDF of the map, budget + catalog price-history integration, pt_BR sweep, docs |

Each phase gets tested before the next starts.

---

## 6. Open questions

Settled — see the decisions table at the top: material path, split award, minimum proposals,
and the requisition step.

Still open, and none of them blocks phase 1:

| # | Question | Assumption if you do not say otherwise |
|---|---|---|
| 1 | Who may **approve a requisition** — any manager, or admin only? | Admin, matching the existing delete/approve pattern |
| 2 | Who may **award** a quotation? Value thresholds? | Admin; no thresholds |
| 3 | Equalization depth | Phase 4 shows freight + tax + discount + lead time + terms side by side; unit-of-measure conversion and payment-term present value only if asked |
| 4 | Budget enforcement | Warn when the awarded total exceeds the linked budget item, never block |
| 5 | Do vendors ever type their own prices (portal/e-mail link)? | No — procurement keys in what the vendors send |
| 6 | Module access | New `quotations` module in `config/modules.php` covering `requisitions.*` and `quotations.*`, so an install can switch the whole chain off |

---

## 7. Sources

- Sienge — [fluxograma do processo de compras](https://sienge.com.br/blog/fluxograma-do-processo-de-compras/),
  [mapa comparativo de preços](https://sienge.com.br/blog/mapa-comparativo-de-precos/),
  [equalização de propostas](https://sienge.com.br/blog/equalizacao-de-propostas-como-comparar-fornecedores-de-forma-justa/),
  [mapa de comparação (help)](https://ajuda.sienge.com.br/support/solutions/articles/153000221262-suprimentos-compras-mapa-de-comparac%C3%A3o)
- [Mais Controle ERP — mapa de cotação](https://maiscontroleerp.com.br/mapa-de-cotacao/)
- [ObraPlay — mapa de cotação](https://www.obraplay.com/mapa-de-cotacao/)
- Contract types: [prestação de serviços vs empreitada](https://melocampos.com.br/2022/09/29/as-diferencas-entre-o-contrato-de-prestacao-de-servicos-e-de-empreitada/)
