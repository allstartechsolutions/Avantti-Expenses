<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Livewire\Company\CompanyCreate;
use App\Livewire\User\UserCreate;
use App\Livewire\User\UserEdit;
use App\Livewire\User\UserIndex;
use App\Livewire\User\UserShow;
use App\Livewire\Client\ClientCreate;
use App\Livewire\Client\ClientEdit;
use App\Livewire\Client\ClientIndex;
use App\Livewire\Client\ClientShow;
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
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\DailyReport\DailyReportForm;
use App\Http\Controllers\DailyReportPdfController;
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
use App\Livewire\Project\ProjectPurchaseOrders;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Company settings (single company setup)
    Route::get('company/settings', CompanyCreate::class)->name('company.settings');

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

    // Project routes
    Route::get('projects', ProjectIndex::class)->name('projects.index');
    Route::get('projects/create', ProjectCreate::class)->name('projects.create');
    Route::get('projects/{project}/edit', ProjectEdit::class)->name('projects.edit');

    // Project section routes (new navigation structure)
    Route::get('projects/{project}', ProjectOverview::class)->name('projects.overview');
    Route::get('projects/{project}/jobsites', ProjectJobSites::class)->name('projects.jobsites');
    Route::get('projects/{project}/expenses', ProjectExpenses::class)->name('projects.expenses');
    Route::get('projects/{project}/change-orders', ProjectChangeOrders::class)->name('projects.change-orders');
    Route::get('projects/{project}/daily-reports', ProjectDailyReports::class)->name('projects.daily-reports');
    Route::get('projects/{project}/budget', ProjectBudget::class)->name('projects.budget');

    // Legacy route alias (for backward compatibility during migration)
    Route::get('projects/{project}/show', ProjectShow::class)->name('projects.show');

    // Job Site routes
    Route::get('job-sites/{jobSite}', JobSiteShow::class)->name('jobsites.show');

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
