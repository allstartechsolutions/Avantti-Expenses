import { login } from './login.mjs';

// Screens for the November 2026 change requests, one file per instruction image.
const findText = (sel, text) => `[...document.querySelectorAll(${JSON.stringify(sel)})].find(e => e.textContent.includes(${JSON.stringify(text)}))`;

// The card/panel around a heading: climb from the heading until the box is at least minWidth wide.
const card = (heading, minWidth = 300) => `
  const h = [...document.querySelectorAll('h1,h2,h3,h4,p,label')].find(e => e.textContent.trim() === ${JSON.stringify(heading)});
  let el = h; while (el && el.parentElement && !(el.getBoundingClientRect().width >= ${minWidth} && /rounded/.test(el.className))) el = el.parentElement;
  return el;`;

export default async (a) => {
  await login(a);

  // #24 — check number appears when the method is Check.
  await a.go('/company-expenses/create', 2000);
  await a.js(`const s = document.querySelector('select[wire\\\\:model\\\\.live="expense_payment_method"]'); s.value = 'check'; s.dispatchEvent(new Event('change', {bubbles: true}));`, 1800);
  await a.js(`const i = document.querySelector('input[wire\\\\:model="expense_check_number"]'); i.value = '29852'; i.dispatchEvent(new Event('input', {bubbles: true}));`, 600);
  await a.mark(`const i = document.querySelector('input[wire\\\\:model="expense_check_number"]'); let el = i; for (let n = 0; n < 6 && el.parentElement; n++) { el = el.parentElement; if (el.getBoundingClientRect().width > 900) break; } return el;`);
  await a.shot('24-check-number-field', { selector: '[data-shot]', pad: 16 });

  // Company Expenses list: notes column (#13), check no. (#24), machine (#15), paperclip (#14).
  await a.go('/company-expenses', 2500);
  await a.mark(`return document.querySelector('table').closest('.rounded-lg, .shadow-sm') || document.querySelector('table');`);
  await a.shot('list-company-expenses', { selector: '[data-shot]' });

  // #24 — search finds the expense by its check number.
  await a.js(`const s = document.querySelector('#search'); s.value = '29852'; s.dispatchEvent(new Event('input', {bubbles: true}));`, 2000);
  await a.mark(`return document.querySelector('#search').closest('.rounded-lg');`);
  await a.shot('24-search-filters', { selector: '[data-shot]' });
  await a.mark(`return document.querySelector('table').closest('.rounded-lg, .shadow-sm') || document.querySelector('table');`);
  await a.shot('24-search-result', { selector: '[data-shot]' });

  // #15 — expense details show the machine and the maintenance.
  await a.go('/company-expenses', 2500);
  await a.js(`const row = ${findText('tbody tr', 'Hydraulic hoses')}; row.querySelector('button[title="View"]').click();`, 2500);
  await a.mark(card('Expense Details', 700));
  await a.shot('15-expense-details-machine', { selector: '[data-shot]', pad: 0, maxHeight: 596 });

  // #14 — paperclip opens the expense at its files.
  await a.go('/company-expenses', 2500);
  await a.js(`const row = ${findText('tbody tr', 'Hydraulic hoses')}; row.querySelector('button[wire\\\\:click^="openExpenseFiles"]').click();`, 3000);
  await a.shot('14-expense-files', { view: true });

  // #26 — equipment overview with the costs card. #14 — costs tab rows open.
  await a.go('/equipment/1', 2500);
  await a.shot('26-equipment-overview', { view: true });
  await a.mark(card('Costs', 380));
  await a.shot('26-equipment-costs-card', { selector: '[data-shot]' });
  await a.go('/equipment/1?tab=costs', 2500);
  await a.shot('14-equipment-costs-tab', { view: true });

  // #21 / #22 — lot overview: Estimated cost card and Contracts card with View Contracts.
  await a.go('/job-sites/1', 2500);
  await a.mark(`const c = ${findText('.grid', 'Estimated cost')}; return [...document.querySelectorAll('.grid')].filter(g => g.textContent.includes('Estimated cost') && g.textContent.includes('Total Contract Value')).sort((x, y) => x.textContent.length - y.textContent.length)[0];`);
  await a.shot('22-lot-cards', { selector: '[data-shot]' });
  await a.mark(card('Contracts', 380));
  await a.shot('21-lot-contracts-card', { selector: '[data-shot]' });

  // #22 — the Estimated cost field on the lot form.
  await a.go('/projects/1/jobsites', 2500);
  await a.js(`const row = ${findText('tbody tr, .grid > div', 'Lot 12')}; (row.querySelector('[wire\\\\:click^="editJobSite"]') || row.querySelector('button[title="Edit"]')).click();`, 2500);
  await a.mark(`const i = document.querySelector('#estimated_cost'); let el = i; while (el.parentElement && !(el.getBoundingClientRect().width > 1200)) el = el.parentElement; return el;`);
  await a.shot('22-estimated-cost-field', { selector: '[data-shot]', maxHeight: 300 });

  // #1 / #9 — batch list: PM and payment date filters, the Totals panel.
  await a.go('/payment-batches', 2500);
  await a.mark(card('Totals', 900));
  await a.shot('1-9-batch-totals', { selector: '[data-shot]' });
  await a.go('/payment-batches?pmFilter=3', 2500);
  await a.mark(`return document.querySelector('#pmFilter').closest('.rounded-lg');`);
  await a.shot('1-9-batch-filters', { selector: '[data-shot]' });
  await a.mark(card('Totals', 900));
  await a.shot('1-9-batch-totals-pm', { selector: '[data-shot]' });

  // #3 — Reject All Pending beside Approve All Pending. #7 / #8 — row order.
  await a.go('/payment-batches/1/edit', 3000);
  await a.mark(`return [...document.querySelectorAll('button')].find(b => b.textContent.includes('Reject All Pending')).closest('.rounded-xl');`);
  await a.shot('3-reject-all-pending', { selector: '[data-shot]' });
  await a.mark(`return document.querySelector('table').closest('.rounded-xl');`);
  await a.shot('7-8-batch-order', { selector: '[data-shot]' });

  // #8 — group by employee.
  await a.js(`const s = document.querySelector('select[wire\\\\:model\\\\.live="groupBy"]'); s.value = 'employee'; s.dispatchEvent(new Event('change', {bubbles: true}));`, 2500);
  await a.mark(`return document.querySelector('table').closest('.rounded-xl');`);
  await a.shot('8-group-by-employee', { selector: '[data-shot]', maxHeight: 640 });

  // #10 / #11 (part) — change orders on the batch view.
  await a.go('/payment-batches/1', 3000);
  await a.js(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Show all change orders')).click();`, 2500);
  await a.mark(`return document.querySelector('table').closest('.rounded-xl, .rounded-lg');`);
  await a.shot('10-11-batch-view-change-orders', { selector: '[data-shot]', maxHeight: 560 });

  // #2 / #6 — filter by subcontractor, then employee: the selection bar.
  await a.go('/payment-batches/1/edit', 3000);
  await a.js(`const s = document.querySelector('select[wire\\\\:model\\\\.live="subcontractorFilter"]'); s.value = '1'; s.dispatchEvent(new Event('change', {bubbles: true}));`, 2500);
  await a.mark(`return [...document.querySelectorAll('p')].find(p => p.textContent.trim() === 'This selection').closest('.rounded-xl');`);
  await a.shot('2-selection-bar', { selector: '[data-shot]' });
  await a.js(`const s = document.querySelector('select[wire\\\\:model\\\\.live="employeeFilter"]'); s.value = '1'; s.dispatchEvent(new Event('change', {bubbles: true}));`, 2500);
  await a.mark(`return document.querySelector('select[wire\\\\:model\\\\.live="employeeFilter"]').closest('.rounded-xl');`);
  await a.shot('6-employee-filter', { selector: '[data-shot]' });
  await a.mark(`return [...document.querySelectorAll('p')].find(p => p.textContent.trim() === 'This selection').closest('.rounded-xl');`);
  await a.shot('6-employee-selection', { selector: '[data-shot]' });

  // #12 — deleting a contract payment asks for a reason.
  await a.go('/contracts/1', 3000);
  await a.js(`document.querySelector('[wire\\\\:click^="startDeletePayment"]').click();`, 2000);
  await a.js(`const t = document.querySelector('#delete-payment-reason'); t.value = 'Entered twice — check 29903 was voided'; t.dispatchEvent(new Event('input', {bubbles: true}));`, 500);
  await a.mark(`return document.querySelector('#delete-payment-reason').closest('form');`);
  await a.shot('12-delete-payment-reason', { selector: '[data-shot]' });

  // #17 — Cash to Pay by project manager.
  await a.go('/dashboard', 3500);
  await a.mark(`const p = [...document.querySelectorAll('p')].find(e => e.textContent.trim() === 'Cash to Pay'); let el = p; while (el && !/rounded-lg/.test(el.className)) el = el.parentElement; return el;`);
  await a.shot('17-cash-to-pay-by-pm', { selector: '[data-shot]' });

  // #16 — Receivables: lots with an open contract.
  await a.go('/dashboard', 3500);
  await a.mark(`const p = [...document.querySelectorAll('p')].find(e => e.textContent.trim() === 'Receivables'); let el = p; while (el && !/rounded-lg/.test(el.className)) el = el.parentElement; return el;`);
  await a.shot('16-receivables', { selector: '[data-shot]' });

  // #18 — job site list: Total Costs and Balance.
  await a.go('/projects', 2500);
  await a.mark(`return document.querySelector('table').closest('.rounded-lg');`);
  await a.shot('18-job-site-list-costs', { selector: '[data-shot]' });

  // #19 — job site overview: cards and profit or loss by lot.
  await a.go('/projects/1', 3000);
  await a.mark(`return [...document.querySelectorAll('.grid')].find(g => g.textContent.includes('Total Contract Value') && g.textContent.includes('Contracts'));`);
  await a.shot('19-job-site-cards', { selector: '[data-shot]' });
  await a.mark(card('Profit or loss by lot', 800));
  await a.shot('19-profit-by-lot', { selector: '[data-shot]' });

  // #23 — supplier address lookup.
  await a.go('/suppliers/create', 2500);
  await a.mark(`return document.querySelector('[x-ref="streetInput"]').closest('.rounded-lg');`);
  await a.shot('23-supplier-address', { selector: '[data-shot]' });

  // #25 — company expenses by payment method.
  await a.go('/company-expenses', 2500);
  await a.mark(`return document.querySelector('#methodFilter').closest('.rounded-lg');`);
  await a.shot('25-method-filter', { selector: '[data-shot]' });
  await a.mark(card('By payment method', 800));
  await a.shot('25-by-payment-method', { selector: '[data-shot]' });

  // #27 — supplier documents.
  await a.go('/suppliers/3', 3000);
  await a.mark(`const h = [...document.querySelectorAll('h2')].find(e => e.textContent.trim() === 'Documents'); return h.closest('.mt-6');`);
  await a.shot('27-supplier-documents', { selector: '[data-shot]', maxHeight: 900 });

  // #20 — Payment Details filtered by employee.
  await a.go('/reports/payment-details?subcontractorFilter=1&employeeFilter=1&fromDate=2026-10-01&toDate=2026-12-31', 3000);
  await a.mark(`return document.querySelector('select[wire\\\\:model\\\\.live="employeeFilter"]').closest('.rounded-lg');`);
  await a.shot('20-employee-filter', { selector: '[data-shot]' });
  await a.mark(`return document.querySelector('table').closest('.rounded-lg, .shadow-sm');`);
  await a.shot('20-employee-rows', { selector: '[data-shot]' });
};
