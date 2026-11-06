"""
How-to blocks added under each finished request in the client's Word file.

`match` is the start of the request's own paragraph in the client's document
(the text under its screenshot). `tracker` is the item number in
docs/changes_requested/november-2026-tracker.md. Images live in screenshots/.
Add one entry per finished item, then run build_doc.py.
"""

ITEMS = [
    {
        "tracker": 1,
        "match": "Batches – dar a opção de somar por data e PM",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Job Sites → Payment Batches.",
            "Scroll below the list to the Totals panel. It adds up every batch the filters keep, not only the page you are on.",
            "\"By project manager and payment date\" shows each PM's total, split by payment date (e.g. PM Alexandre = Oct 8 + Oct 9). \"By payment date\" shows each date's total. The grand total is at the top right.",
            "Use the Project Manager and Payment date filters above the list to narrow both the list and the totals.",
        ],
        "notes": ["The totals add up every line in each batch, exactly like the Total Amount column."],
        "images": [
            ("1-9-batch-totals.png", "Payment Batches — the Totals panel under the list"),
        ],
    },
    {
        "tracker": 2,
        "match": "Batches – Nessa tela, quando selecionar por Subcontractor",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch (pencil icon on Payment Batches).",
            "Choose a Subcontractor in the filter bar (or any other filter).",
            "A \"This selection\" bar appears with how many pending items match the filters and their total, next to the batch's whole pending amount.",
            "Click Approve this selection to approve and pay only those items. Reject this selection rejects only those.",
        ],
        "notes": [
            "The bar appears only when the filters select some, but not all, of the pending items. When they select all of them, Approve All Pending does the same thing.",
            "As with Approve All, every item is checked first. If one cannot be paid (e.g. it exceeds the contract balance), nothing is paid and the reason is shown.",
        ],
        "images": [("2-selection-bar.png", "Batch filtered by subcontractor — the selection total and its buttons")],
    },
    {
        "tracker": 3,
        "match": "Batches – colocar a opção rejected all pending",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch.",
            "Click Reject All Pending (next to Approve All Pending) and confirm.",
            "Every pending item becomes Rejected. The items stay in the batch, are not paid, and move to the bottom of the list.",
        ],
        "notes": ["To reject only part of the batch, filter first and use Reject this selection (see item 2)."],
        "images": [("3-reject-all-pending.png", "The action bar — Reject All Pending")],
    },
    {
        "tracker": 6,
        "match": "Batches –  opção de selecionar por employee",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch.",
            "In the filter bar, pick the employee in the Employee filter (next to Subcontractor). If a subcontractor is chosen, the list shows only that subcontractor's employees.",
            "The table shows only that employee's contracts, and the selection bar shows their pending total with Approve this selection.",
        ],
        "notes": ["The employee filter is for the screen only and is not saved with the batch."],
        "images": [
            ("6-employee-filter.png", "The Employee filter on the batch screen"),
            ("6-employee-selection.png", "The selection for one employee"),
        ],
    },
    {
        "tracker": 7,
        "match": "Batches – o que for rejected mostrar por ultimo",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch. Pending items now come first, then approved ones, then contracts that are not in the batch, and rejected items always come last.",
            "Sorting by a column (click its header) sorts inside each of those groups, so rejected items stay at the bottom.",
        ],
        "notes": ["The batch view, its PDF and its CSV also list pending items first (they used to list approved first)."],
        "images": [("7-8-batch-order.png", "Batch screen — pending first, the rejected line (struck through) last")],
    },
    {
        "tracker": 8,
        "match": "Batches – colocar a sequencia repetindo o mesmo employee",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch. Within each status, rows are ordered by subcontractor, then employee, then lot, so the same employee's contracts come one after another.",
            "For a heading per employee with their totals, choose \"Group by Employee\" in the grouping list.",
        ],
        "notes": ["\"Group by Employee\" is also available on the batch view, its PDF and CSV, and on Contract Payments."],
        "images": [
            ("7-8-batch-order.png", "Rows ordered by subcontractor, then employee"),
            ("8-group-by-employee.png", "Group by Employee — a heading and totals for each employee"),
        ],
    },
    {
        "tracker": 9,
        "match": "Batches – dar a opção de selecionar por PM",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Job Sites → Payment Batches.",
            "Choose the project manager in the Project Manager filter (or \"No project manager\"). Payment date from / to narrows by date.",
            "The list shows that PM's batches, and the Totals panel at the bottom of the page sums them, by payment date and in total.",
            "Clear Filters returns to every batch.",
        ],
        "notes": [],
        "images": [
            ("1-9-batch-filters.png", "Payment Batches — the Project Manager and payment date filters"),
            ("1-9-batch-totals-pm.png", "The Totals panel for one PM"),
        ],
    },
    {
        "tracker": 13,
        "match": "Company Expenses – inserir coluna com o notes",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Company → Company Expenses.",
            "The list now has a Notes column. It shows the first two lines of each expense's note.",
            "Hover over a note to read all of it. A dash (—) means the expense has no note.",
        ],
        "notes": [],
        "images": [("list-company-expenses.png", "Company Expenses list — the Notes column")],
    },
    {
        "tracker": 14,
        "match": "Company Expenses – dar a opção de abrir o que foi anexado",
        "done": "Nov 5, 2026",
        "steps": [
            "In Company → Company Expenses, a paperclip with a number in the Actions column means the expense has files (receipt and attachments) and how many.",
            "Click the paperclip. The expense opens and scrolls straight to its files. Click a file name to open it, or the download icon to save it.",
            "The same paperclip appears on the project and lot expense lists.",
            "On a machine (Company → Equipment → the machine → Costs), every expense tagged to it has the paperclip and an eye icon to open the expense.",
        ],
        "notes": ["New files can be added from the same place: drop them in the box below the list of files."],
        "images": [
            ("14-expense-files.png", "Clicking the paperclip opens the expense at its files"),
            ("14-equipment-costs-tab.png", "Equipment → Costs tab — each expense opens with its files"),
        ],
    },
    {
        "tracker": 15,
        "match": "Company Expenses – Expenses Details – mostrar pra qual maquina",
        "done": "Nov 5, 2026",
        "steps": [
            "When adding or editing an expense, pick the machine in the Machine field (and, if it applies, which maintenance it paid for).",
            "Open the expense (eye icon). The Machine panel shows the machine's name, asset tag, type, make, model and year, and the maintenance it paid for. Click the machine's name to open its page.",
            "On the Company Expenses list, the machine shows as an orange tag under Vendor / Items.",
            "The list's search also finds expenses by machine name, asset tag or plate.",
        ],
        "notes": [],
        "images": [
            ("15-expense-details-machine.png", "Expense details — the Machine panel"),
            ("list-company-expenses.png", "Company Expenses list — the machine tag under Vendor / Items"),
        ],
    },
    {
        "tracker": 20,
        "match": "Payment Details – aqui precisamos selecionar por employee",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Reports → Payment Details.",
            "Optionally choose a Subcontractor first: the Employee list then shows only that subcontractor's employees.",
            "Choose the Employee. The report shows only the contracts with that employee: payments made and open balances.",
            "Every contract row shows \"Employee: name\". Export CSV and the PDF follow the same filter, and the CSV has an Employee column.",
        ],
        "notes": ["Expenses have no employee, so they are left out while an employee is selected."],
        "images": [
            ("20-employee-filter.png", "Payment Details — the Employee filter"),
            ("20-employee-rows.png", "The report filtered to one employee"),
        ],
    },
    {
        "tracker": 21,
        "match": "Lot Details – nessa tela, colocar botão de ir direto para contracts",
        "done": "Nov 5, 2026",
        "steps": [
            "Open a lot (Job Sites → the job site → Lots → the lot).",
            "On the Overview, the right-hand column now has a Contracts card, under Expenses: number of contracts, total, and paid vs. unpaid with a progress bar.",
            "Click View Contracts to go straight to the lot's contracts.",
        ],
        "notes": ["The card appears only for users who are allowed to see contracts."],
        "images": [("21-lot-contracts-card.png", "Lot overview — the Contracts card with View Contracts")],
    },
    {
        "tracker": 22,
        "match": "Lot Details – colocar algum campo que informe o valor que sera pago",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the job site → Lots, and edit the lot (pencil icon).",
            "Fill in Estimated cost with what you expect to pay the subcontractor. It is optional and needs no contract. Save.",
            "The lot Overview shows an Estimated cost card. Before any contract it reads \"No contract yet\". Once contracts exist, it shows the contracted amount and the difference from the estimate.",
        ],
        "notes": ["Information only: the estimate is never included in Profit, so nothing has to be zeroed when the contract is issued."],
        "images": [
            ("22-estimated-cost-field.png", "Edit Lot — the Estimated cost field"),
            ("22-lot-cards.png", "Lot overview — the Estimated cost card"),
        ],
    },
    {
        "tracker": 24,
        "match": "Add expense – abaixo do payment method",
        "done": "Nov 5, 2026 · extended Nov 6, 2026",
        "steps": [
            "Add an expense — on a job site, on a lot, or under Company → Company Expenses → Add Company Expense.",
            "Choose Payment Method \"Check\". A Check number field appears right below it.",
            "Type the check number (or a short note about the check) and save.",
            "The number shows in the expense list under Payments (\"Check no. 29852\") — company, job site and lot lists alike — and in the expense details.",
            "To find an expense by its check, type the number in the list's search box.",
            "The reports show it as well: Payment Details, Expense Report (detail view) and Accounts Payable — on screen, in the PDF and as a \"Check number\" column in the CSV.",
        ],
        "notes": [
            "At first this was requested for company expenses only (built Nov 5). On Nov 6 it was extended, as asked, to job site and lot expenses and to the reports.",
            "Every expense paid by check — company, job site and lot. One number per expense, installments included.",
            "Choosing another payment method clears the number. It can still be edited after the expense is paid.",
            "Check numbers typed into Notes before this change were not moved into the new field.",
        ],
        "images": [
            ("24-check-number-field.png", "Add Company Expense — the Check number field"),
            ("24-search-filters.png", "Searching by check number"),
            ("24-search-result.png", "The result, with the check number under Payments"),
            ("24-lot-expenses-check-number.png", "A lot's expenses — the check number under Payments"),
        ],
    },
    {
        "tracker": 26,
        "match": "Expenses / Equipments – no overview – mostrar os costs",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Company → Equipment and open the machine.",
            "The Overview now has a Costs card: total cost of ownership, purchase cost, tagged expenses (split into maintenance and other), this year, and what is not paid yet.",
            "Below that are the latest five expenses. Click one to open it, or its paperclip to open its files.",
            "Click See all costs for the Costs tab, with every expense and the breakdowns by category, month and maintenance.",
        ],
        "notes": [],
        "images": [
            ("26-equipment-overview.png", "Equipment overview — the Costs card on the right"),
            ("26-equipment-costs-card.png", "The Costs card"),
        ],
    },
    {
        "tracker": 12,
        "match": "Payment – ao deletar um pagamento",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the contract and find the payment under Payment History.",
            "Click the trash icon. A Delete payment dialog opens showing the payment's amount, date and method.",
            "Type the reason (required) and click Delete payment.",
            "The payment is removed, the contract balance goes back up, and the reason is kept in the contract's Change History with the payment's details.",
        ],
        "notes": ["Deleting a payment still needs the \"undo a payment\" permission, as before."],
        "images": [("12-delete-payment-reason.png", "Delete payment — the reason is required")],
    },
    {
        "tracker": 17,
        "match": "Dashborad – Cash to pay",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the Dashboard.",
            "The Cash to Pay card now lists each project manager's share, then \"Company (general)\" for company expenses and \"No project manager\" for job sites without one.",
            "The Grand total under the list is the same figure as the top of the card.",
        ],
        "notes": ["Cash to Pay is what is due this month: expense installments and one-time expenses due this month, plus open contract balances."],
        "images": [("17-cash-to-pay-by-pm.png", "Dashboard — Cash to Pay by project manager")],
    },
    {
        "tracker": 18,
        "match": "Job sites – colocar uma coluna com o valor total de despesas",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Job Sites.",
            "The list now has Total Costs (contracts + expenses, with the split underneath) and Balance (Amount minus Total Costs) — green when positive, red when negative.",
        ],
        "notes": ["Contracts count at their adjusted amount (with their change orders); drafts are not counted. The same figures as the lot overview."],
        "images": [("18-job-site-list-costs.png", "Job Sites — Total Costs and Balance columns")],
    },
    {
        "tracker": 19,
        "match": "Job Sites Overview – Informar total Amount",
        "done": "Nov 5, 2026",
        "steps": [
            "Open a job site. The Overview now starts with four cards: Total Contract Value, Total Expenses, Contracts (paid / unpaid) and Profit or Loss.",
            "Below them, \"Profit or loss by lot\" shows each lot's contract value, expenses, contracts and profit; the totals row equals the cards.",
            "Click a lot's name to open it.",
        ],
        "notes": ["Each lot's row matches the figures on that lot's own overview. Anything filed on the job site itself (no lot) appears on a \"Job Site (General)\" row."],
        "images": [
            ("19-job-site-cards.png", "Job site overview — the totals"),
            ("19-profit-by-lot.png", "Profit or loss by lot"),
        ],
    },
    {
        "tracker": 23,
        "match": "Create Supplier – colocar o endereço pra puxar automático",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Directory → Vendors → Add Supplier (or open a supplier and click Edit).",
            "In Street Address, start typing the address. Google suggestions appear under the field, as on Clients.",
            "Pick the address: street, city, state and ZIP code fill in automatically. Check them and save.",
        ],
        "notes": ["The suggestions need the Google Maps key that the live site already uses for Clients; without it the fields work as plain text."],
        "images": [("23-supplier-address.png", "New Supplier — the address section")],
    },
    {
        "tracker": 25,
        "match": "Company Expenses – gerar relatório do que foi pago",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Company → Company Expenses and set the From / To dates (and Status \"Paid\" if you only want what was paid).",
            "The \"By payment method\" table shows, for those dates, how much was paid by Check, Credit Card, Autopay and so on — count, total, paid and pending.",
            "Click a method in the table (or pick it in the new Payment method filter) to list only those expenses.",
            "Click Export CSV to download the list with the method and check number of each expense, and the method summary at the bottom.",
        ],
        "notes": [
            "Autopay counts expenses set to automatic payment, whatever their method.",
            "The From and To date fields were also widened — they used to cut the date off.",
        ],
        "images": [
            ("25-method-filter.png", "Company Expenses — Payment method filter and the dates"),
            ("25-by-payment-method.png", "By payment method"),
        ],
    },
    {
        "tracker": 27,
        "match": "Supplier – opção de inserir documentos",
        "done": "Nov 5, 2026",
        "steps": [
            "Go to Directory → Vendors and open the supplier. A Documents section is now at the bottom of the page.",
            "Click Upload Document, choose the type (W9, Certificate of Insurance (COI), …), drop the file, and set the expiration date when the type has one.",
            "\"Required documents\" shows which documents are missing, valid or expiring, with an Upload or Renew shortcut on each.",
            "Renew replaces a document with a new version (the old one stays in its History); Archive takes it out of the reminders.",
        ],
        "notes": [
            "It is the same documents feature subcontractors have, with the same expiry reminders.",
            "A company that is both a supplier and a subcontractor has one set of documents, shown on both pages.",
        ],
        "images": [("27-supplier-documents.png", "Supplier — Documents")],
    },
    {
        "tracker": 16,
        "match": "Dashboard – receivables",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the Dashboard. The Receivables card now shows, for every lot with an open contract, the lots' value less what their contracts cost.",
            "Underneath: the lots' value, the contracts, what has already been paid on those contracts and what is still to pay.",
            "The last line is the invoiced amount due this month (the figure the card used to show), with a badge when invoices are past due.",
        ],
        "notes": [
            "A lot counts while it has a subcontractor contract that is not fully paid. When all its contracts are paid it drops out.",
            "Client payments are not recorded in the system, so nothing is taken off for money already received.",
        ],
        "images": [("16-receivables.png", "Dashboard — Receivables")],
    },
    {
        "tracker": 10,
        "match": "Batches – o BC precisa aparecer no relatório de Excel",
        "status": "◐ PARTLY DONE",
        "color": "B45309",
        "shade": "FFFBEB",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch (eye icon on Payment Batches) and click Export CSV.",
            "Each line now has two more columns: Change Orders (the contract's net change orders) and Change order details — date, title and amount of each one, back charges included.",
            "Download PDF prints the same: a Change Orders column with each change order listed under the total.",
        ],
        "notes": ["Back charges are still entered as negative change orders on the contract. The full back-charge flow — approve or decline in the batch, carry to the next payment, record one declined for good — comes with the back-charge redesign."],
        "images": [("10-11-batch-view-change-orders.png", "Batch view — the Change Orders column, opened")],
    },
    {
        "tracker": 11,
        "match": "Batches – após aprovado, precisa que apareca o BC",
        "status": "◐ PARTLY DONE",
        "color": "B45309",
        "shade": "FFFBEB",
        "done": "Nov 5, 2026",
        "steps": [
            "Open the batch (eye icon on Payment Batches), approved or not.",
            "The Change Orders column shows each contract's change orders total. Click the arrow next to it to list them: date, title, description and amount — so a back charge taken on CTR-1204 shows what it was and why.",
            "Show all change orders (next to Group by) opens every line at once.",
        ],
        "notes": ["Showing which back charge was deducted on which payment, with the deduction date, comes with the back-charge redesign."],
        "images": [("10-11-batch-view-change-orders.png", "Batch view — a back charge and a change order under CTR-D201")],
    },
]
