import { login } from './login.mjs';

// Client feedback, 16 Nov 2026: batch totals less the back charges (tracker item 11).
// Needs seed_demo_back_charges.php. Run from the project root:
//   node docs/changes_requested/instructions/capture/cdp.mjs docs/changes_requested/instructions/capture/steps-batch-net.mjs docs/changes_requested/instructions/screenshots
export default async (a) => {
  await login(a);

  // The list: the Total Amount column is the checks, with the gross and the deduction under it.
  await a.go('/payment-batches?search=Demo', 3000);
  await a.mark(`return document.querySelector('table').closest('.rounded-lg, .shadow-sm');`);
  await a.shot('11-batch-list-net', { selector: '[data-shot]' });

  // The approved batch: the Approved card shows its own deduction and the check.
  await a.go('/payment-batches/4', 3000);
  await a.mark(`return [...document.querySelectorAll('.grid')].find(g => g.textContent.includes('Total Amount') && g.textContent.includes('Approved') && g.textContent.includes('Rejected'));`);
  await a.shot('11-batch-view-approved-card', { selector: '[data-shot]' });
};
