<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Livewire\Access\AccessIndex;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\JobSite\JobSiteTeam;
use App\Livewire\Project\ProjectTeam;
use App\Livewire\Company\CompanyInfo;
use App\Livewire\User\UserCreate;
use App\Livewire\User\UserAccess;
use App\Livewire\User\UserEdit;
use App\Livewire\User\UserIndex;
use App\Livewire\User\UserShow;
use App\Livewire\Client\ClientCreate;
use App\Livewire\Client\ClientEdit;
use App\Livewire\Client\ClientIndex;
use App\Livewire\Client\ClientShow;
use App\Livewire\Subcontractor\SubcontractorIndex;
use App\Livewire\Subcontractor\SubcontractorCreate;
use App\Livewire\Subcontractor\SubcontractorEdit;
use App\Livewire\Subcontractor\SubcontractorShow;
use App\Livewire\Project\ProjectCreate;
use App\Livewire\Project\ProjectEdit;
use App\Livewire\Project\ProjectIndex;
use App\Livewire\Project\ProjectShow;
use App\Livewire\Project\ProjectOverview;
use App\Livewire\Project\ProjectDocuments;
use App\Livewire\Approval\ApprovalForm;
use App\Livewire\Approval\ApprovalSeedFromBudget;
use App\Livewire\Approval\ApprovalShow;
use App\Livewire\Project\ProjectApprovals;
use App\Livewire\Project\ProjectRfis;
use App\Livewire\Rfi\RfiForm;
use App\Livewire\Rfi\RfiShow;
use App\Livewire\Project\ProjectExpenses;
use App\Livewire\Project\ProjectIncome;
use App\Livewire\Project\ProjectJobSites;
use App\Livewire\Project\ProjectChangeOrders;
use App\Livewire\Project\ProjectDailyReports;
use App\Livewire\Project\ProjectBudget;
use App\Livewire\Project\ProjectFinancialReport;
use App\Livewire\JobSite\JobSiteFinancialReport;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\JobSite\JobSiteContracts;
use App\Livewire\JobSite\JobSiteDocuments;
use App\Livewire\JobSite\JobSiteApprovals;
use App\Livewire\JobSite\JobSiteRfis;
use App\Livewire\JobSite\JobSiteIncome;
use App\Livewire\JobSite\JobSiteQuotations;
use App\Livewire\JobSite\JobSiteRequisitions;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\Expense\ExpenseEdit;
use App\Livewire\DailyReport\DailyReportForm;
use App\Http\Controllers\ContractPaymentsPdfController;
use App\Http\Controllers\AccountsPayableReportPdfController;
use App\Http\Controllers\PaymentDetailReportPdfController;
use App\Http\Controllers\PaymentScheduleReportPdfController;
use App\Http\Controllers\ExpenseReportPdfController;
use App\Http\Controllers\JobSiteFinancialReportPdfController;
use App\Http\Controllers\QuotationMapPdfController;
use App\Http\Controllers\QuotationRfqPdfController;
use App\Http\Controllers\ProjectFinancialReportPdfController;
use App\Http\Controllers\DailyReportPdfController;
use App\Http\Controllers\EstimatePdfController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\DocumentationFileController;
use App\Http\Controllers\DocumentationImageController;
use App\Http\Controllers\DocumentationUploadController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\CollaborationPdfController;
use App\Http\Controllers\MeetingMinutePdfController;
use App\Livewire\Documentation\DocumentationArticle;
use App\Livewire\Documentation\DocumentationForm;
use App\Livewire\Documentation\DocumentationIndex;
use App\Livewire\JobSite\JobSiteTasks;
use App\Livewire\Meeting\MeetingAgenda;
use App\Livewire\Meeting\MeetingForm;
use App\Livewire\Meeting\MeetingIndex;
use App\Livewire\Meeting\MeetingSeriesIndex;
use App\Livewire\Meeting\MeetingShow;
use App\Livewire\Project\ProjectTasks;
use App\Livewire\Quotation\MyQuotations;
use App\Livewire\Task\MyTasks;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SharedDocumentController;
use App\Livewire\Catalog\CatalogItemIndex;
use App\Livewire\Catalog\CatalogItemCreate;
use App\Livewire\Catalog\CatalogItemEdit;
use App\Livewire\Catalog\CatalogCategoryIndex;
use App\Livewire\Catalog\CatalogCategoryCreate;
use App\Livewire\Catalog\CatalogCategoryEdit;
use App\Livewire\Supplier\SupplierIndex;
use App\Livewire\Supplier\SupplierCreate;
use App\Livewire\Supplier\SupplierEdit;
use App\Livewire\Supplier\SupplierShow;
use App\Livewire\Payment\PaymentDashboard;
use App\Livewire\CostCode\CostCodeTemplateIndex;
use App\Livewire\CostCode\CostCodeTemplateCreate;
use App\Livewire\CostCode\CostCodeTemplateShow;
use App\Livewire\CostCode\CostCodeTemplateEdit;
use App\Livewire\Budget\BudgetCreate;
use App\Livewire\Budget\BudgetShow;
use App\Livewire\Budget\BudgetEdit;
use App\Http\Controllers\BudgetCostGridPdfController;
use App\Livewire\Budget\BudgetCostGrid;
use App\Livewire\Budget\CostCodeDetail;
use App\Livewire\PurchaseOrder\PurchaseOrderCreate;
use App\Livewire\PurchaseOrder\PurchaseOrderEdit;
use App\Livewire\PurchaseOrder\PurchaseOrderShow;
use App\Livewire\Contract\ContractCreate;
use App\Livewire\Contract\ContractShow;
use App\Livewire\Contract\ContractEdit;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\PaymentBatch\PaymentBatchIndex;
use App\Livewire\PaymentBatch\PaymentBatchCreate;
use App\Livewire\PaymentBatch\PaymentBatchShow;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\Project\ProjectContracts;
use App\Livewire\Project\ProjectPurchaseOrders;
use App\Livewire\Project\ProjectQuotations;
use App\Livewire\Project\ProjectRequisitions;
use App\Livewire\Estimate\EstimateIndex;
use App\Livewire\Estimate\EstimateCreate;
use App\Livewire\Estimate\EstimateShow;
use App\Livewire\Estimate\EstimateEdit;
use App\Livewire\Invoice\InvoiceIndex;
use App\Livewire\Invoice\InvoiceCreate;
use App\Livewire\Invoice\InvoiceShow;
use App\Livewire\Invoice\InvoiceEdit;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\EmailTrackingController;
use App\Livewire\Invoice\PublicInvoicePay;
use App\Livewire\Share\SharedDocument;
use App\Livewire\SystemSettings\SettingsIndex;
use App\Livewire\Profile\UserProfile;
use App\Livewire\Report\AccountsPayableReport;
use App\Http\Controllers\CompanyFinancialReportPdfController;
use App\Http\Controllers\ContractMeasurementPdfController;
use App\Http\Controllers\ContractSchedulePdfController;
use App\Livewire\Report\CompanyFinancialReport;
use App\Livewire\Report\PaymentDetailReport;
use App\Livewire\Report\PaymentScheduleReport;
use App\Livewire\Report\ExpenseReport;
use App\Livewire\Report\SalesTaxReport;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Setup\SetupWizard;
use App\Models\User;

Route::get('/', function () {
    if (! User::query()->exists()) {
        return redirect('/setup');
    }

    return redirect('/login');
})->name('home');

// One-time installer. SetupWizard::mount() aborts 404 once any user exists,
// so this route is effectively invisible on running systems.
Route::get('setup', SetupWizard::class)->name('setup');

// Public (no auth)
Route::get('email/track/{token}', [EmailTrackingController::class, 'track'])->name('email.track');
Route::get('pay/{token}', PublicInvoicePay::class)->name('invoice.pay')->middleware('throttle:20,1');

// Document share links — public by design, no login. The token is the only
// credential, so every route here is throttled and re-checks the link.
Route::get('s/{token}', SharedDocument::class)
    ->name('documents.share')
    ->middleware('throttle:30,1');
Route::get('s/{token}/view/{document?}', [SharedDocumentController::class, 'view'])
    ->name('documents.share.view')
    ->middleware('throttle:60,1');
Route::get('s/{token}/download/{document?}', [SharedDocumentController::class, 'download'])
    ->name('documents.share.download')
    ->middleware('throttle:30,1');

// Accepting an invitation. Public by necessity — the person has no login yet —
// so the token is the only credential and the route is throttled.
Route::get('invitations/{token}', AcceptInvitation::class)
    ->name('invitations.accept')
    ->middleware('throttle:20,1');

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('profile', UserProfile::class)->name('profile');

    // Company info
    // Company info. It was reachable — and editable — by anybody signed in
    // until M3; the abilities now say who.
    Route::get('company/info', CompanyInfo::class)
        ->middleware('ability:company.view')
        ->name('company.info');

    // Roles & Access. Guarded by the ability rather than the admin middleware:
    // this screen is part of the permission module and is written against it
    // from the start (docs/permissions-module.md).
    Route::get('access', AccessIndex::class)
        ->middleware('ability:access.view')
        ->name('access.index');

    // Users. Converted from the `admin` middleware to the abilities in
    // config/permissions.php (M1) — an administrator still holds all of them,
    // and nobody else does until somebody grants them.
    Route::get('users', UserIndex::class)->middleware('ability:users.view')->name('users.index');
    Route::get('users/create', UserCreate::class)->middleware('ability:users.create')->name('users.create');
    Route::get('users/{user}', UserShow::class)->middleware('ability:users.view')->name('users.show');
    Route::get('users/{user}/edit', UserEdit::class)->middleware('ability:users.edit')->name('users.edit');
    // One person's own company-wide access (F0). Held to `access.view` rather
    // than `users.edit`: this hands out permissions, which is the Roles &
    // Access screen's business and the most sensitive grant there is.
    Route::get('users/{user}/access', UserAccess::class)->middleware('ability:access.view')->name('users.access');

    // Client routes
    Route::get('clients', ClientIndex::class)
        ->middleware('ability:clients.view')->name('clients.index');
    Route::get('clients/create', ClientCreate::class)
        ->middleware('ability:clients.create')->name('clients.create');
    Route::get('clients/{client}', ClientShow::class)->name('clients.show');
    Route::get('clients/{client}/edit', ClientEdit::class)
        ->middleware('ability:clients.edit')->name('clients.edit');

    // Subcontractor routes
    Route::get('subcontractors', SubcontractorIndex::class)
        ->middleware('ability:vendors.view')->name('subcontractors.index');
    Route::get('subcontractors/create', SubcontractorCreate::class)
        ->middleware('ability:vendors.create')->name('subcontractors.create');
    Route::get('subcontractors/{subcontractor}', SubcontractorShow::class)->name('subcontractors.show');
    // Legacy files and uploaded ones alike; the vendor in the URL is checked against the document.
    Route::get('subcontractors/{subcontractor}/documents/{document}/download', [\App\Http\Controllers\SubcontractorDocumentController::class, 'download'])
        ->middleware('ability:vendors.view')
        ->name('subcontractors.documents.download');
    Route::get('subcontractors/{subcontractor}/edit', SubcontractorEdit::class)
        ->middleware('ability:vendors.edit')->name('subcontractors.edit');

    // Vendor merge tool (suppliers + subcontractors share the vendors table)
    Route::get('vendors/duplicates', \App\Livewire\Vendor\VendorDuplicates::class)
        ->middleware('ability:vendors.merge')->name('vendors.duplicates');

    // Project routes
    // The project list and its own record. The per-project screens are guarded
    // by EnsureScopeIsVisible, which covers every route carrying a project.
    Route::get('projects', ProjectIndex::class)->middleware('ability:projects.view')->name('projects.index');
    Route::get('projects/create', ProjectCreate::class)->middleware('ability:projects.create')->name('projects.create');
    Route::get('projects/{project}/edit', ProjectEdit::class)->middleware('ability:project.edit,project')->name('projects.edit');

    // Project section routes (new navigation structure)
    Route::get('projects/{project}', ProjectOverview::class)->name('projects.overview');
    Route::get('projects/{project}/jobsites', ProjectJobSites::class)->name('projects.jobsites');
    Route::get('projects/{project}/expenses', ProjectExpenses::class)->name('projects.expenses');
    Route::get('projects/{project}/income', ProjectIncome::class)->name('projects.income');
    Route::get('projects/{project}/change-orders', ProjectChangeOrders::class)->name('projects.change-orders');
    Route::get('projects/{project}/contracts', ProjectContracts::class)->name('projects.contracts');
    Route::get('projects/{project}/daily-reports', ProjectDailyReports::class)->name('projects.daily-reports');
    Route::get('projects/{project}/budget', ProjectBudget::class)->name('projects.budget');
    Route::get('projects/{project}/report', ProjectFinancialReport::class)
        ->middleware('ability:project-report.view,project')->name('projects.report');
    Route::get('projects/{project}/report/pdf', [ProjectFinancialReportPdfController::class, 'download'])
        ->middleware('ability:project-report.export,project')->name('projects.report.pdf.download');
    Route::get('projects/{project}/report/pdf/view', [ProjectFinancialReportPdfController::class, 'stream'])
        ->middleware('ability:project-report.export,project')->name('projects.report.pdf.view');

    // Legacy route alias (for backward compatibility during migration)
    Route::get('projects/{project}/show', ProjectShow::class)->name('projects.show');

    // Job Site section routes (new navigation structure)
    Route::get('job-sites/{jobSite}', JobSiteOverview::class)->name('jobsites.overview');
    Route::get('job-sites/{jobSite}/expenses', JobSiteShow::class)->name('jobsites.expenses');
    Route::get('job-sites/{jobSite}/income', JobSiteIncome::class)->name('jobsites.income');
    Route::get('job-sites/{jobSite}/change-orders', JobSiteShow::class)->name('jobsites.change-orders');
    Route::get('job-sites/{jobSite}/contracts', JobSiteContracts::class)->name('jobsites.contracts');
    Route::get('job-sites/{jobSite}/purchase-orders', JobSiteShow::class)->name('jobsites.purchase-orders');
    Route::get('job-sites/{jobSite}/requisitions', JobSiteRequisitions::class)->name('jobsites.requisitions');
    Route::get('job-sites/{jobSite}/quotations', JobSiteQuotations::class)->name('jobsites.quotations');
    Route::get('job-sites/{jobSite}/daily-reports', JobSiteShow::class)->name('jobsites.daily-reports');
    Route::get('job-sites/{jobSite}/budget', JobSiteShow::class)->name('jobsites.budget');
    Route::get('job-sites/{jobSite}/report', JobSiteFinancialReport::class)
        ->middleware('ability:project-report.view,jobSite')->name('jobsites.report');
    Route::get('job-sites/{jobSite}/report/pdf', [JobSiteFinancialReportPdfController::class, 'download'])
        ->middleware('ability:project-report.export,jobSite')->name('jobsites.report.pdf.download');
    Route::get('job-sites/{jobSite}/report/pdf/view', [JobSiteFinancialReportPdfController::class, 'stream'])
        ->middleware('ability:project-report.export,jobSite')->name('jobsites.report.pdf.view');

    // Legacy route alias (for backward compatibility during migration)
    Route::get('job-sites/{jobSite}/show', JobSiteShow::class)->name('jobsites.show');

    // Contract routes
    Route::get('contracts/{contract}', ContractShow::class)->name('contracts.show');
    Route::get('contracts/{contract}/edit', ContractEdit::class)->name('contracts.edit');
    Route::get('projects/{project}/contracts/create', ContractCreate::class)->name('contracts.project.create');
    Route::get('job-sites/{jobSite}/contracts/create', ContractCreate::class)->name('contracts.jobsite.create');

    // Expense routes
    Route::get('projects/{project}/expenses/create', ExpenseCreate::class)->name('expenses.project.create');
    Route::get('job-sites/{jobSite}/expenses/create', ExpenseCreate::class)->name('expenses.jobsite.create');
    Route::get('expenses/{expense}/edit', ExpenseEdit::class)->name('expenses.edit');

    // Daily Report routes (Job Site level)
    Route::get('job-sites/{jobSite}/daily-reports/create', DailyReportForm::class)->name('dailyreports.create');
    Route::get('job-sites/{jobSite}/daily-reports/{dailyReport}/edit', DailyReportForm::class)->name('dailyreports.edit');

    // Daily Report routes (Project level)
    Route::get('projects/{project}/daily-reports/create', DailyReportForm::class)->name('dailyreports.project.create');
    Route::get('projects/{project}/daily-reports/{dailyReport}/edit', DailyReportForm::class)->name('dailyreports.project.edit');

    // Daily Report PDF routes
    // Cronograma físico-financeiro
    Route::get('contracts/{contract}/schedule/pdf', [ContractSchedulePdfController::class, 'download'])->name('contracts.schedule.pdf.download');
    Route::get('contracts/{contract}/schedule/pdf/view', [ContractSchedulePdfController::class, 'stream'])->name('contracts.schedule.pdf.view');

    // Boletim de medição (contract measurement)
    Route::get('measurements/{measurement}/pdf', [ContractMeasurementPdfController::class, 'download'])->name('measurements.pdf.download');
    Route::get('measurements/{measurement}/pdf/view', [ContractMeasurementPdfController::class, 'stream'])->name('measurements.pdf.view');

    Route::get('daily-reports/{dailyReport}/pdf', [DailyReportPdfController::class, 'download'])->name('dailyreports.pdf.download');
    Route::get('daily-reports/{dailyReport}/pdf/view', [DailyReportPdfController::class, 'stream'])->name('dailyreports.pdf.view');

    // Catalog routes
    Route::get('catalog', CatalogItemIndex::class)
        ->middleware('ability:catalog.view')->name('catalog.index');
    Route::get('catalog/create', CatalogItemCreate::class)
        ->middleware('ability:catalog.create')->name('catalog.create');
    Route::get('catalog/{item}/edit', CatalogItemEdit::class)->name('catalog.edit');

    // Catalog Category routes
    Route::get('catalog/categories', CatalogCategoryIndex::class)
        ->middleware('ability:catalog.view')->name('catalog.categories.index');
    Route::get('catalog/categories/create', CatalogCategoryCreate::class)
        ->middleware('ability:catalog.create')->name('catalog.categories.create');
    Route::get('catalog/categories/{category}/edit', CatalogCategoryEdit::class)->name('catalog.categories.edit');

    // Supplier routes
    Route::get('suppliers', SupplierIndex::class)
        ->middleware('ability:vendors.view')->name('suppliers.index');
    Route::get('suppliers/create', SupplierCreate::class)
        ->middleware('ability:vendors.create')->name('suppliers.create');
    Route::get('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit', SupplierEdit::class)
        ->middleware('ability:vendors.edit')->name('suppliers.edit');

    // Payment routes — the company-wide money screens. Guarded on the route as
    // well as in the component, because two of them (the batch index and the
    // batch detail) have no mount() to guard (M11).
    Route::get('payments', PaymentDashboard::class)
        ->middleware('ability:payments.view')->name('payments.index');
    Route::get('contract-payments', ContractPayments::class)
        ->middleware('ability:payments.view')->name('contract-payments.index');
    Route::get('contract-payments/pdf', [ContractPaymentsPdfController::class, 'download'])
        ->middleware('ability:payments.view')->name('contract-payments.pdf.download');
    Route::get('contract-payments/pdf/view', [ContractPaymentsPdfController::class, 'stream'])
        ->middleware('ability:payments.view')->name('contract-payments.pdf.view');

    // Payment Batch routes
    Route::get('payment-batches', PaymentBatchIndex::class)
        ->middleware('ability:payments.batch')->name('payment-batches.index');
    Route::get('payment-batches/create', PaymentBatchCreate::class)
        ->middleware('ability:payments.batch')->name('payment-batches.create');
    Route::get('payment-batches/{paymentBatch}', PaymentBatchShow::class)
        ->middleware('ability:payments.batch')->name('payment-batches.show');
    Route::get('payment-batches/{paymentBatch}/edit', PaymentBatchEdit::class)
        ->middleware('ability:payments.batch')->name('payment-batches.edit');

    // Estimate routes
    // The index screens have no mount() to guard, so the grant is asked on the
    // route; the rest of the module guards its own actions (M15).
    Route::get('estimates', EstimateIndex::class)
        ->middleware('ability:estimates.view')->name('estimates.index');
    Route::get('estimates/create', EstimateCreate::class)->name('estimates.create');
    Route::get('estimates/{estimate}', EstimateShow::class)->name('estimates.show');
    Route::get('estimates/{estimate}/edit', EstimateEdit::class)->name('estimates.edit');
    Route::get('estimates/{estimate}/pdf', [EstimatePdfController::class, 'download'])->name('estimates.pdf.download');
    Route::get('estimates/{estimate}/pdf/view', [EstimatePdfController::class, 'stream'])->name('estimates.pdf.view');

    // Invoice routes
    Route::get('invoices', InvoiceIndex::class)
        ->middleware('ability:invoices.view')->name('invoices.index');
    Route::get('invoices/create', InvoiceCreate::class)->name('invoices.create');
    Route::get('invoices/{invoice}', InvoiceShow::class)->name('invoices.show');
    Route::get('invoices/{invoice}/edit', InvoiceEdit::class)->name('invoices.edit');

    // Invoice PDF routes
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
    Route::get('invoices/{invoice}/pdf/view', [InvoicePdfController::class, 'stream'])->name('invoices.pdf.view');

    // Report routes — off the `admin` middleware onto one ability per report
    // (M17). Every report and its PDF answer to the same grant, because a PDF
    // of a report somebody may not open is the same disclosure by another
    // door; the six were reachable only by an administrator before, and the
    // seeds keep them that way.
    //
    // `reports.view` is the umbrella the sidebar group asks for; each report
    // then asks for its own.
    Route::get('reports/company-financials', CompanyFinancialReport::class)
        ->middleware('ability:reports.company_financials')->name('reports.company-financials');
    Route::get('reports/company-financials/pdf', [CompanyFinancialReportPdfController::class, 'download'])
        ->middleware('ability:reports.company_financials')->name('reports.company-financials.pdf.download');
    Route::get('reports/company-financials/pdf/view', [CompanyFinancialReportPdfController::class, 'stream'])
        ->middleware('ability:reports.company_financials')->name('reports.company-financials.pdf.view');

    Route::get('reports/sales-tax', SalesTaxReport::class)
        ->middleware('ability:reports.sales_tax')->name('reports.sales-tax');

    Route::get('reports/expenses', ExpenseReport::class)
        ->middleware('ability:reports.expenses')->name('reports.expenses');
    Route::get('reports/expenses/pdf', [ExpenseReportPdfController::class, 'download'])
        ->middleware('ability:reports.expenses')->name('reports.expenses.pdf.download');
    Route::get('reports/expenses/pdf/view', [ExpenseReportPdfController::class, 'stream'])
        ->middleware('ability:reports.expenses')->name('reports.expenses.pdf.view');

    Route::get('reports/payment-schedule', PaymentScheduleReport::class)
        ->middleware('ability:reports.payment_schedule')->name('reports.payment-schedule');
    Route::get('reports/payment-schedule/pdf', [PaymentScheduleReportPdfController::class, 'download'])
        ->middleware('ability:reports.payment_schedule')->name('reports.payment-schedule.pdf.download');
    Route::get('reports/payment-schedule/pdf/view', [PaymentScheduleReportPdfController::class, 'stream'])
        ->middleware('ability:reports.payment_schedule')->name('reports.payment-schedule.pdf.view');

    Route::get('reports/accounts-payable', AccountsPayableReport::class)
        ->middleware('ability:reports.accounts_payable')->name('reports.accounts-payable');
    Route::get('reports/accounts-payable/pdf', [AccountsPayableReportPdfController::class, 'download'])
        ->middleware('ability:reports.accounts_payable')->name('reports.accounts-payable.pdf.download');
    Route::get('reports/accounts-payable/pdf/view', [AccountsPayableReportPdfController::class, 'stream'])
        ->middleware('ability:reports.accounts_payable')->name('reports.accounts-payable.pdf.view');

    Route::get('reports/payment-details', PaymentDetailReport::class)
        ->middleware('ability:reports.payment_details')->name('reports.payment-details');
    Route::get('reports/payment-details/pdf', [PaymentDetailReportPdfController::class, 'download'])
        ->middleware('ability:reports.payment_details')->name('reports.payment-details.pdf.download');
    Route::get('reports/payment-details/pdf/view', [PaymentDetailReportPdfController::class, 'stream'])
        ->middleware('ability:reports.payment_details')->name('reports.payment-details.pdf.view');

    // System Settings moved off the `admin` middleware onto its ability (M3);
    // the cost code templates below stay admin-only until M6.
    Route::get('system-settings', SettingsIndex::class)
        ->middleware('ability:settings.view')
        ->name('system-settings.index');

    // Cost code templates — the company-wide library a budget is built from.
    // They belong to no project: one set of codes, used everywhere. Off the
    // `admin` middleware in M6; the same people hold them by seed, but it is a
    // grant now and can be handed to whoever keeps the chart of accounts.
    Route::get('cost-codes/templates', CostCodeTemplateIndex::class)
        ->middleware('ability:cost-codes.view')
        ->name('cost-codes.templates.index');
    Route::get('cost-codes/templates/create', CostCodeTemplateCreate::class)
        ->middleware('ability:cost-codes.create')
        ->name('cost-codes.templates.create');
    Route::get('cost-codes/templates/{template}', CostCodeTemplateShow::class)
        ->middleware('ability:cost-codes.view')
        ->name('cost-codes.templates.show');
    Route::get('cost-codes/templates/{template}/edit', CostCodeTemplateEdit::class)
        ->middleware('ability:cost-codes.edit')
        ->name('cost-codes.templates.edit');

    // Budget routes
    Route::get('budgets/{budget}', BudgetShow::class)->name('budgets.show');
    Route::get('budgets/{budget}/edit', BudgetEdit::class)->name('budgets.edit');
    Route::get('budgets/{budget}/cost-grid', BudgetCostGrid::class)->name('budgets.cost-grid');
    Route::get('budgets/{budget}/cost-grid/pdf', [BudgetCostGridPdfController::class, 'download'])->name('budgets.cost-grid.pdf.download');
    Route::get('budgets/{budget}/cost-grid/pdf/view', [BudgetCostGridPdfController::class, 'stream'])->name('budgets.cost-grid.pdf.view');
    Route::get('budgets/{budget}/cost-codes/{budgetItem}', CostCodeDetail::class)->name('budgets.cost-code');
    Route::get('budgets/{budget}/unassigned-costs', CostCodeDetail::class)->name('budgets.unassigned');
    Route::get('projects/{project}/budgets/create', BudgetCreate::class)->name('projects.budgets.create');
    Route::get('job-sites/{jobSite}/budgets/create', BudgetCreate::class)->name('job-sites.budgets.create');

    // Purchase requisition routes (the buy-side chain starts here)
    Route::get('projects/{project}/requisitions', ProjectRequisitions::class)->name('projects.requisitions');
    Route::get('projects/{project}/quotations', ProjectQuotations::class)->name('projects.quotations');
    Route::get('quotations/{quotation}/rfq/pdf', [QuotationRfqPdfController::class, 'download'])->name('quotations.rfq.pdf.download');
    Route::get('quotations/{quotation}/rfq/pdf/view', [QuotationRfqPdfController::class, 'stream'])->name('quotations.rfq.pdf.view');
    Route::get('quotations/{quotation}/map/pdf', [QuotationMapPdfController::class, 'download'])->name('quotations.map.pdf.download');
    Route::get('quotations/{quotation}/map/pdf/view', [QuotationMapPdfController::class, 'stream'])->name('quotations.map.pdf.view');

    // Purchase Order routes
    Route::get('projects/{project}/purchase-orders', ProjectPurchaseOrders::class)->name('projects.purchase-orders');
    Route::get('projects/{project}/purchase-orders/create', PurchaseOrderCreate::class)->name('purchase-orders.project.create');
    Route::get('job-sites/{jobSite}/purchase-orders/create', PurchaseOrderCreate::class)->name('purchase-orders.jobsite.create');
    Route::get('purchase-orders/{purchaseOrder}', PurchaseOrderShow::class)->name('purchase-orders.show');
    Route::get('purchase-orders/{purchaseOrder}/edit', PurchaseOrderEdit::class)->name('purchase-orders.edit');

    // Documentation library — guides shipped with the product plus this
    // company's own (docs/documentation-module.md)
    Route::get('documentation', DocumentationIndex::class)->name('documentation.index');
    Route::get('documentation/write', DocumentationForm::class)->name('documentation.create');
    Route::get('documentation/{article}/edit', DocumentationForm::class)->name('documentation.edit');
    Route::get('documentation/image/{uuid}', DocumentationFileController::class)->name('documentation.image');
    Route::post('documentation/images', DocumentationUploadController::class)->name('documentation.images.store');
    Route::get('documentation/assets/{path}', DocumentationImageController::class)
        ->where('path', '.*')->name('documentation.asset');
    Route::get('documentation/{slug}', DocumentationArticle::class)->name('documentation.show');

    // The buying queue (docs/procurement-assignment-plan.md phase 6). Like My
    // Tasks it is a cross-project list, so it is filtered rather than guarded
    // by a scope — see PurchaseRequisition::visibleTo() and
    // Quotation::visibleTo(). The grant still has to be held.
    Route::get('quotations/mine', MyQuotations::class)
        ->middleware('ability:quotations.view')->name('quotations.mine');

    // Meetings, minutes and tasks (docs/meetings-module-plan.md)
    // My Tasks is a cross-project list, so it is filtered rather than guarded
    // by a scope — see Task::visibleTo(). The grant still has to be held.
    Route::get('tasks/mine', MyTasks::class)
        ->middleware('ability:tasks.view')->name('tasks.mine');
    Route::get('projects/{project}/tasks', ProjectTasks::class)->name('projects.tasks');
    Route::get('job-sites/{jobSite}/tasks', JobSiteTasks::class)->name('jobsites.tasks');

    // A meeting spans several projects through its items, so these are asked
    // without a scope; the components guard their own actions.
    Route::get('meeting-series', MeetingSeriesIndex::class)
        ->middleware('ability:meetings.manage_series')->name('meeting-series.index');
    Route::get('meetings', MeetingIndex::class)
        ->middleware('ability:meetings.view')->name('meetings.index');
    Route::get('meetings/create', MeetingForm::class)
        ->middleware('ability:meetings.create')->name('meetings.create');
    Route::get('meetings/{meeting}', MeetingShow::class)
        ->middleware('ability:meetings.view')->name('meetings.show');
    Route::get('meetings/{meeting}/edit', MeetingForm::class)
        ->middleware('ability:meetings.edit')->name('meetings.edit');
    Route::get('meetings/{meeting}/agenda', MeetingAgenda::class)
        ->middleware('ability:meetings.edit')->name('meetings.agenda');
    Route::get('meetings/{meeting}/minute/pdf', [MeetingMinutePdfController::class, 'download'])
        ->middleware('ability:meetings.view')->name('meetings.minute.pdf.download');
    Route::get('meetings/{meeting}/minute/pdf/view', [MeetingMinutePdfController::class, 'stream'])
        ->middleware('ability:meetings.view')->name('meetings.minute.pdf.view');

    // RFIs — a formal question to the projetista or the owner, with the answer
    // tracked back. Both components guard on `rfis.view` against the record
    // they are opened on, so no `ability:` middleware here.
    // See docs/RFI-Submittals-modules.md phase 3.
    Route::get('projects/{project}/rfis', ProjectRfis::class)->name('projects.rfis');
    Route::get('job-sites/{jobSite}/rfis', JobSiteRfis::class)->name('jobsites.rfis');
    // Raising one. Scoped by the record it will belong to, so the guard has
    // something to answer against before the RFI exists.
    Route::get('projects/{project}/rfis/create', RfiForm::class)->name('projects.rfis.create');
    Route::get('job-sites/{jobSite}/rfis/create', RfiForm::class)->name('jobsites.rfis.create');
    Route::get('rfis/{rfi}/edit', RfiForm::class)->name('rfis.edit');

    // Approvals (aprovações) — the submittal cycle. Both components guard on
    // `approvals.view` against the record they are opened on.
    // See docs/RFI-Submittals-modules.md phase 5.
    Route::get('projects/{project}/approvals', ProjectApprovals::class)->name('projects.approvals');
    Route::get('job-sites/{jobSite}/approvals', JobSiteApprovals::class)->name('jobsites.approvals');

    // Raising one. Scoped by the record it will belong to, so the guard has
    // something to answer against before the approval exists.
    Route::get('projects/{project}/approvals/create', ApprovalForm::class)->name('projects.approvals.create');
    Route::get('job-sites/{jobSite}/approvals/create', ApprovalForm::class)->name('jobsites.approvals.create');
    Route::get('approvals/{approval}/edit', ApprovalForm::class)->name('approvals.edit');

    // "Gerar aprovações do orçamento" — proposes drafts from the budget lines.
    // See docs/RFI-Submittals-modules.md phase 6.
    Route::get('projects/{project}/approvals/from-budget', ApprovalSeedFromBudget::class)
        ->name('projects.approvals.seed');

    // One approval in full: every round of it, and who was asked.
    Route::get('approvals/{approval}', ApprovalShow::class)->name('approvals.show');

    // The printed sheet. Guarded inside the controller against the document's
    // own project — `ability:` middleware resolves a project or job-site route
    // parameter, and these carry the document instead, so naming the bare
    // ability here would be a weaker check than the screen's.
    Route::get('rfis/{rfi}/pdf', [CollaborationPdfController::class, 'rfi'])->name('rfis.pdf.download');
    Route::get('rfis/{rfi}/pdf/view', [CollaborationPdfController::class, 'rfiStream'])->name('rfis.pdf.view');
    Route::get('approvals/{approval}/pdf', [CollaborationPdfController::class, 'approval'])->name('approvals.pdf.download');
    Route::get('approvals/{approval}/pdf/view', [CollaborationPdfController::class, 'approvalStream'])->name('approvals.pdf.view');

    // One RFI in full. Guarded in mount() against the RFI's own project or job
    // site, never against a scope the request supplied.
    Route::get('rfis/{rfi}', RfiShow::class)->name('rfis.show');

    // Document repository (file repository for projects and job sites)
    Route::get('projects/{project}/documents', ProjectDocuments::class)->name('projects.documents');
    Route::get('job-sites/{jobSite}/documents', JobSiteDocuments::class)->name('jobsites.documents');

    // Who is on a project or a job site, and what they may do there. Guarded
    // by the ability on that record rather than a role (docs/permissions-module.md).
    Route::get('projects/{project}/team', ProjectTeam::class)
        ->middleware('ability:team.view,project')
        ->name('projects.team');
    Route::get('job-sites/{jobSite}/team', JobSiteTeam::class)
        ->middleware('ability:team.view,jobSite')
        ->name('jobsites.team');
    Route::get('documents/{document}/download', [DocumentFileController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentFileController::class, 'preview'])->name('documents.preview');
    Route::get('documents/{document}/versions/{version}/download', [DocumentFileController::class, 'downloadVersion'])
        ->name('documents.versions.download');

    // Direct-to-storage upload handshake (no file content passes through PHP)
    Route::post('documents/uploads/init', [DocumentUploadController::class, 'init'])->name('documents.uploads.init');
    Route::post('documents/uploads/parts', [DocumentUploadController::class, 'parts'])->name('documents.uploads.parts');
    Route::post('documents/uploads/complete', [DocumentUploadController::class, 'complete'])->name('documents.uploads.complete');
    Route::post('documents/uploads/abort', [DocumentUploadController::class, 'abort'])->name('documents.uploads.abort');

    // The same handshake for everything that is not a repository document —
    // tasks, task notes and meetings (see docs/meetings-module-plan.md §7)
    Route::post('uploads/init', [FileUploadController::class, 'init'])->name('uploads.init');
    Route::post('uploads/parts', [FileUploadController::class, 'parts'])->name('uploads.parts');
    Route::post('uploads/complete', [FileUploadController::class, 'complete'])->name('uploads.complete');
    Route::post('uploads/abort', [FileUploadController::class, 'abort'])->name('uploads.abort');

    // File download route (protected)
    Route::get('files/download', [FileController::class, 'download'])->name('files.download');
    Route::get('files/show', [FileController::class, 'show'])->name('files.show');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/notifications', 'settings.notifications')->name('notifications.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
