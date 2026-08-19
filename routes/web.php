<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Livewire\Company\CompanyInfo;
use App\Livewire\User\UserCreate;
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
use App\Livewire\JobSite\JobSiteIncome;
use App\Livewire\JobSite\JobSiteQuotations;
use App\Livewire\JobSite\JobSiteRequisitions;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Expense\ExpenseCreate;
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
use App\Livewire\Budget\BudgetCostGrid;
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

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('profile', UserProfile::class)->name('profile');

    // Company info
    Route::get('company/info', CompanyInfo::class)->name('company.info');

    // User routes (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('users', UserIndex::class)->name('users.index');
        Route::get('users/create', UserCreate::class)->name('users.create');
        Route::get('users/{user}', UserShow::class)->name('users.show');
        Route::get('users/{user}/edit', UserEdit::class)->name('users.edit');
    });

    // Client routes
    Route::get('clients', ClientIndex::class)->name('clients.index');
    Route::get('clients/create', ClientCreate::class)->name('clients.create');
    Route::get('clients/{client}', ClientShow::class)->name('clients.show');
    Route::get('clients/{client}/edit', ClientEdit::class)->name('clients.edit');

    // Subcontractor routes
    Route::get('subcontractors', SubcontractorIndex::class)->name('subcontractors.index');
    Route::get('subcontractors/create', SubcontractorCreate::class)->name('subcontractors.create');
    Route::get('subcontractors/{subcontractor}', SubcontractorShow::class)->name('subcontractors.show');
    Route::get('subcontractors/{subcontractor}/edit', SubcontractorEdit::class)->name('subcontractors.edit');

    // Vendor merge tool (suppliers + subcontractors share the vendors table)
    Route::get('vendors/duplicates', \App\Livewire\Vendor\VendorDuplicates::class)->name('vendors.duplicates');

    // Project routes
    Route::get('projects', ProjectIndex::class)->name('projects.index');
    Route::get('projects/create', ProjectCreate::class)->name('projects.create');
    Route::get('projects/{project}/edit', ProjectEdit::class)->name('projects.edit');

    // Project section routes (new navigation structure)
    Route::get('projects/{project}', ProjectOverview::class)->name('projects.overview');
    Route::get('projects/{project}/jobsites', ProjectJobSites::class)->name('projects.jobsites');
    Route::get('projects/{project}/expenses', ProjectExpenses::class)->name('projects.expenses');
    Route::get('projects/{project}/income', ProjectIncome::class)->name('projects.income');
    Route::get('projects/{project}/change-orders', ProjectChangeOrders::class)->name('projects.change-orders');
    Route::get('projects/{project}/contracts', ProjectContracts::class)->name('projects.contracts');
    Route::get('projects/{project}/daily-reports', ProjectDailyReports::class)->name('projects.daily-reports');
    Route::get('projects/{project}/budget', ProjectBudget::class)->name('projects.budget');
    Route::get('projects/{project}/report', ProjectFinancialReport::class)->name('projects.report');
    Route::get('projects/{project}/report/pdf', [ProjectFinancialReportPdfController::class, 'download'])->name('projects.report.pdf.download');
    Route::get('projects/{project}/report/pdf/view', [ProjectFinancialReportPdfController::class, 'stream'])->name('projects.report.pdf.view');

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
    Route::get('job-sites/{jobSite}/report', JobSiteFinancialReport::class)->name('jobsites.report');
    Route::get('job-sites/{jobSite}/report/pdf', [JobSiteFinancialReportPdfController::class, 'download'])->name('jobsites.report.pdf.download');
    Route::get('job-sites/{jobSite}/report/pdf/view', [JobSiteFinancialReportPdfController::class, 'stream'])->name('jobsites.report.pdf.view');

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
    Route::get('catalog', CatalogItemIndex::class)->name('catalog.index');
    Route::get('catalog/create', CatalogItemCreate::class)->name('catalog.create');
    Route::get('catalog/{item}/edit', CatalogItemEdit::class)->name('catalog.edit');

    // Catalog Category routes
    Route::get('catalog/categories', CatalogCategoryIndex::class)->name('catalog.categories.index');
    Route::get('catalog/categories/create', CatalogCategoryCreate::class)->name('catalog.categories.create');
    Route::get('catalog/categories/{category}/edit', CatalogCategoryEdit::class)->name('catalog.categories.edit');

    // Supplier routes
    Route::get('suppliers', SupplierIndex::class)->name('suppliers.index');
    Route::get('suppliers/create', SupplierCreate::class)->name('suppliers.create');
    Route::get('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit', SupplierEdit::class)->name('suppliers.edit');

    // Payment routes
    Route::get('payments', PaymentDashboard::class)->name('payments.index');
    Route::get('contract-payments', ContractPayments::class)->name('contract-payments.index');
    Route::get('contract-payments/pdf', [ContractPaymentsPdfController::class, 'download'])->name('contract-payments.pdf.download');
    Route::get('contract-payments/pdf/view', [ContractPaymentsPdfController::class, 'stream'])->name('contract-payments.pdf.view');

    // Payment Batch routes
    Route::get('payment-batches', PaymentBatchIndex::class)->name('payment-batches.index');
    Route::get('payment-batches/create', PaymentBatchCreate::class)->name('payment-batches.create');
    Route::get('payment-batches/{paymentBatch}', PaymentBatchShow::class)->name('payment-batches.show');
    Route::get('payment-batches/{paymentBatch}/edit', PaymentBatchEdit::class)->name('payment-batches.edit');

    // Estimate routes
    Route::get('estimates', EstimateIndex::class)->name('estimates.index');
    Route::get('estimates/create', EstimateCreate::class)->name('estimates.create');
    Route::get('estimates/{estimate}', EstimateShow::class)->name('estimates.show');
    Route::get('estimates/{estimate}/edit', EstimateEdit::class)->name('estimates.edit');
    Route::get('estimates/{estimate}/pdf', [EstimatePdfController::class, 'download'])->name('estimates.pdf.download');
    Route::get('estimates/{estimate}/pdf/view', [EstimatePdfController::class, 'stream'])->name('estimates.pdf.view');

    // Invoice routes
    Route::get('invoices', InvoiceIndex::class)->name('invoices.index');
    Route::get('invoices/create', InvoiceCreate::class)->name('invoices.create');
    Route::get('invoices/{invoice}', InvoiceShow::class)->name('invoices.show');
    Route::get('invoices/{invoice}/edit', InvoiceEdit::class)->name('invoices.edit');

    // Invoice PDF routes
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
    Route::get('invoices/{invoice}/pdf/view', [InvoicePdfController::class, 'stream'])->name('invoices.pdf.view');

    // Report routes (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('reports/company-financials', CompanyFinancialReport::class)->name('reports.company-financials');
        Route::get('reports/company-financials/pdf', [CompanyFinancialReportPdfController::class, 'download'])->name('reports.company-financials.pdf.download');
        Route::get('reports/company-financials/pdf/view', [CompanyFinancialReportPdfController::class, 'stream'])->name('reports.company-financials.pdf.view');
        Route::get('reports/sales-tax', SalesTaxReport::class)->name('reports.sales-tax');
        Route::get('reports/expenses', ExpenseReport::class)->name('reports.expenses');
        Route::get('reports/expenses/pdf', [ExpenseReportPdfController::class, 'download'])->name('reports.expenses.pdf.download');
        Route::get('reports/expenses/pdf/view', [ExpenseReportPdfController::class, 'stream'])->name('reports.expenses.pdf.view');
        Route::get('reports/payment-schedule', PaymentScheduleReport::class)->name('reports.payment-schedule');
        Route::get('reports/payment-schedule/pdf', [PaymentScheduleReportPdfController::class, 'download'])->name('reports.payment-schedule.pdf.download');
        Route::get('reports/payment-schedule/pdf/view', [PaymentScheduleReportPdfController::class, 'stream'])->name('reports.payment-schedule.pdf.view');
        Route::get('reports/accounts-payable', AccountsPayableReport::class)->name('reports.accounts-payable');
        Route::get('reports/accounts-payable/pdf', [AccountsPayableReportPdfController::class, 'download'])->name('reports.accounts-payable.pdf.download');
        Route::get('reports/accounts-payable/pdf/view', [AccountsPayableReportPdfController::class, 'stream'])->name('reports.accounts-payable.pdf.view');
        Route::get('reports/payment-details', PaymentDetailReport::class)->name('reports.payment-details');
        Route::get('reports/payment-details/pdf', [PaymentDetailReportPdfController::class, 'download'])->name('reports.payment-details.pdf.download');
        Route::get('reports/payment-details/pdf/view', [PaymentDetailReportPdfController::class, 'stream'])->name('reports.payment-details.pdf.view');
    });

    // System Settings + Cost Code Templates (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('system-settings', SettingsIndex::class)->name('system-settings.index');

        Route::get('cost-codes/templates', CostCodeTemplateIndex::class)->name('cost-codes.templates.index');
        Route::get('cost-codes/templates/create', CostCodeTemplateCreate::class)->name('cost-codes.templates.create');
        Route::get('cost-codes/templates/{template}', CostCodeTemplateShow::class)->name('cost-codes.templates.show');
        Route::get('cost-codes/templates/{template}/edit', CostCodeTemplateEdit::class)->name('cost-codes.templates.edit');
    });

    // Budget routes
    Route::get('budgets/{budget}', BudgetShow::class)->name('budgets.show');
    Route::get('budgets/{budget}/edit', BudgetEdit::class)->name('budgets.edit');
    Route::get('budgets/{budget}/cost-grid', BudgetCostGrid::class)->name('budgets.cost-grid');
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

    // Document repository (file repository for projects and job sites)
    Route::get('projects/{project}/documents', ProjectDocuments::class)->name('projects.documents');
    Route::get('job-sites/{jobSite}/documents', JobSiteDocuments::class)->name('jobsites.documents');
    Route::get('documents/{document}/download', [DocumentFileController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentFileController::class, 'preview'])->name('documents.preview');
    Route::get('documents/{document}/versions/{version}/download', [DocumentFileController::class, 'downloadVersion'])
        ->name('documents.versions.download');

    // Direct-to-storage upload handshake (no file content passes through PHP)
    Route::post('documents/uploads/init', [DocumentUploadController::class, 'init'])->name('documents.uploads.init');
    Route::post('documents/uploads/parts', [DocumentUploadController::class, 'parts'])->name('documents.uploads.parts');
    Route::post('documents/uploads/complete', [DocumentUploadController::class, 'complete'])->name('documents.uploads.complete');
    Route::post('documents/uploads/abort', [DocumentUploadController::class, 'abort'])->name('documents.uploads.abort');

    // File download route (protected)
    Route::get('files/download', [FileController::class, 'download'])->name('files.download');
    Route::get('files/show', [FileController::class, 'show'])->name('files.show');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
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
