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
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\DailyReport\DailyReportForm;
use App\Http\Controllers\DailyReportPdfController;
use App\Http\Controllers\FileController;
use App\Livewire\Catalog\CatalogItemIndex;
use App\Livewire\Catalog\CatalogItemCreate;
use App\Livewire\Catalog\CatalogItemEdit;
use App\Livewire\Supplier\SupplierIndex;
use App\Livewire\Supplier\SupplierCreate;
use App\Livewire\Supplier\SupplierEdit;
use App\Livewire\Supplier\SupplierShow;

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
    Route::get('projects/{project}', ProjectShow::class)->name('projects.show');
    Route::get('projects/{project}/edit', ProjectEdit::class)->name('projects.edit');

    // Job Site routes
    Route::get('job-sites/{jobSite}', JobSiteShow::class)->name('jobsites.show');

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

    // Supplier routes
    Route::get('suppliers', SupplierIndex::class)->name('suppliers.index');
    Route::get('suppliers/create', SupplierCreate::class)->name('suppliers.create');
    Route::get('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit', SupplierEdit::class)->name('suppliers.edit');

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
