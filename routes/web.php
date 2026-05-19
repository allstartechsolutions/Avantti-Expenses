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
use App\Livewire\Project\ProjectExpenses;
use App\Livewire\Project\ProjectJobSites;
use App\Livewire\Project\ProjectChangeOrders;
use App\Livewire\Project\ProjectDailyReports;
use App\Livewire\Project\ProjectBudget;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\JobSite\JobSiteContracts;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\DailyReport\DailyReportForm;
use App\Http\Controllers\ContractPaymentsPdfController;
use App\Http\Controllers\DailyReportPdfController;
use App\Http\Controllers\EstimatePdfController;
use App\Http\Controllers\FileController;
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
use App\Livewire\SystemSettings\SettingsIndex;
use App\Livewire\Profile\UserProfile;
use App\Livewire\Report\SalesTaxReport;

Route::get('/', function () {
    return redirect('/login');
})->name('home');

// Public (no auth)
Route::get('email/track/{token}', [EmailTrackingController::class, 'track'])->name('email.track');
Route::get('pay/{token}', PublicInvoicePay::class)->name('invoice.pay')->middleware('throttle:20,1');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Profile
    Route::get('profile', UserProfile::class)->name('profile');

    // Company info
    Route::get('company/info', CompanyInfo::class)->name('company.info');

    // User routes
    Route::get('users', UserIndex::class)->name('users.index');
    Route::get('users/create', UserCreate::class)->name('users.create');
    Route::get('users/{user}', UserShow::class)->name('users.show');
    Route::get('users/{user}/edit', UserEdit::class)->name('users.edit');

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

    // Project routes
    Route::get('projects', ProjectIndex::class)->name('projects.index');
    Route::get('projects/create', ProjectCreate::class)->name('projects.create');
    Route::get('projects/{project}/edit', ProjectEdit::class)->name('projects.edit');

    // Project section routes (new navigation structure)
    Route::get('projects/{project}', ProjectOverview::class)->name('projects.overview');
    Route::get('projects/{project}/jobsites', ProjectJobSites::class)->name('projects.jobsites');
    Route::get('projects/{project}/expenses', ProjectExpenses::class)->name('projects.expenses');
    Route::get('projects/{project}/change-orders', ProjectChangeOrders::class)->name('projects.change-orders');
    Route::get('projects/{project}/contracts', ProjectContracts::class)->name('projects.contracts');
    Route::get('projects/{project}/daily-reports', ProjectDailyReports::class)->name('projects.daily-reports');
    Route::get('projects/{project}/budget', ProjectBudget::class)->name('projects.budget');

    // Legacy route alias (for backward compatibility during migration)
    Route::get('projects/{project}/show', ProjectShow::class)->name('projects.show');

    // Job Site section routes (new navigation structure)
    Route::get('job-sites/{jobSite}', JobSiteOverview::class)->name('jobsites.overview');
    Route::get('job-sites/{jobSite}/expenses', JobSiteShow::class)->name('jobsites.expenses');
    Route::get('job-sites/{jobSite}/change-orders', JobSiteShow::class)->name('jobsites.change-orders');
    Route::get('job-sites/{jobSite}/contracts', JobSiteContracts::class)->name('jobsites.contracts');
    Route::get('job-sites/{jobSite}/purchase-orders', JobSiteShow::class)->name('jobsites.purchase-orders');
    Route::get('job-sites/{jobSite}/daily-reports', JobSiteShow::class)->name('jobsites.daily-reports');
    Route::get('job-sites/{jobSite}/budget', JobSiteShow::class)->name('jobsites.budget');

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

    // Report routes
    Route::get('reports/sales-tax', SalesTaxReport::class)->name('reports.sales-tax');

    // System Settings routes
    Route::get('system-settings', SettingsIndex::class)->name('system-settings.index');

    // Cost Code Template routes
    Route::get('cost-codes/templates', CostCodeTemplateIndex::class)->name('cost-codes.templates.index');
    Route::get('cost-codes/templates/create', CostCodeTemplateCreate::class)->name('cost-codes.templates.create');
    Route::get('cost-codes/templates/{template}', CostCodeTemplateShow::class)->name('cost-codes.templates.show');
    Route::get('cost-codes/templates/{template}/edit', CostCodeTemplateEdit::class)->name('cost-codes.templates.edit');

    // Budget routes
    Route::get('budgets/{budget}', BudgetShow::class)->name('budgets.show');
    Route::get('budgets/{budget}/edit', BudgetEdit::class)->name('budgets.edit');
    Route::get('projects/{project}/budgets/create', BudgetCreate::class)->name('projects.budgets.create');
    Route::get('job-sites/{jobSite}/budgets/create', BudgetCreate::class)->name('job-sites.budgets.create');

    // Purchase Order routes
    Route::get('projects/{project}/purchase-orders', ProjectPurchaseOrders::class)->name('projects.purchase-orders');
    Route::get('projects/{project}/purchase-orders/create', PurchaseOrderCreate::class)->name('purchase-orders.project.create');
    Route::get('job-sites/{jobSite}/purchase-orders/create', PurchaseOrderCreate::class)->name('purchase-orders.jobsite.create');
    Route::get('purchase-orders/{purchaseOrder}', PurchaseOrderShow::class)->name('purchase-orders.show');
    Route::get('purchase-orders/{purchaseOrder}/edit', PurchaseOrderEdit::class)->name('purchase-orders.edit');

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
