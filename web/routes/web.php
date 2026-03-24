<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConsultantController;
use App\Http\Controllers\DailyCallReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSequenceController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimesheetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'page'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Call reports
    Route::get('/calls', [DailyCallReportController::class, 'index'])->name('calls.index');
    Route::post('/calls', [DailyCallReportController::class, 'store'])->name('calls.store');
    Route::get('/calls/report/monthly', [DailyCallReportController::class, 'reportMonthly'])->name('calls.report.monthly');
    Route::get('/calls/report/yearly', [DailyCallReportController::class, 'reportYearly'])->name('calls.report.yearly');
    Route::get('/calls/report', [DailyCallReportController::class, 'aggregate'])->name('calls.report');

    // Placements
    Route::get('/placements', [PlacementController::class, 'index'])->name('placements.index');
    Route::post('/placements', [PlacementController::class, 'store'])->name('placements.store');
    Route::put('/placements/{placement}', [PlacementController::class, 'update'])->name('placements.update');
    Route::delete('/placements/{placement}', [PlacementController::class, 'destroy'])->name('placements.destroy');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('admin/users', AdminUserController::class)->names('admin.users');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/logo', [SettingsController::class, 'setLogo'])->name('settings.logo');
    Route::post('settings/test-smtp', [SettingsController::class, 'testSmtp'])->name('settings.test-smtp');

    Route::resource('invoice-sequence', InvoiceSequenceController::class)->only(['index', 'update']);

    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('backups/{backup}', [BackupController::class, 'show'])->name('backups.show');

    Route::post('payroll/upload', [PayrollController::class, 'upload'])->name('payroll.upload');
    Route::post('payroll/recompute-margins', [PayrollController::class, 'recomputeMargins'])->name('payroll.recompute.margins');
    Route::get('payroll/api/aggregate', [PayrollController::class, 'apiAggregate'])->name('payroll.api.aggregate');
    Route::post('payroll/api/goal', [PayrollController::class, 'apiGoalSet'])->name('payroll.api.goal.set');
    Route::get('payroll/api/mappings', [PayrollController::class, 'apiMappings'])->name('payroll.api.mappings');
    Route::put('payroll/api/mappings', [PayrollController::class, 'apiMappingsUpdate'])->name('payroll.api.mappings.update');
});

Route::middleware(['auth', 'role:admin,account_manager'])->group(function () {
    Route::get('dashboard/stats', [DashboardController::class, 'index'])->name('dashboard.stats');
    Route::get('dashboard/calls-stats', [DashboardController::class, 'callsStats'])->name('dashboard.calls-stats');

    Route::resource('clients', ClientController::class)->except(['create', 'edit']);

    Route::get('consultants/end-date-alerts', [ConsultantController::class, 'endDateAlerts'])->name('consultants.end-date-alerts');
    Route::get('consultants/{consultant}/onboarding', [ConsultantController::class, 'onboardingIndex'])->name('consultants.onboarding.index');
    Route::put('consultants/{consultant}/onboarding', [ConsultantController::class, 'onboardingUpdate'])->name('consultants.onboarding.update');
    Route::post('consultants/{consultant}/extend-end-date', [ConsultantController::class, 'extendEndDate'])->name('consultants.extend-end-date');
    Route::post('consultants/{consultant}/w9', [ConsultantController::class, 'w9Upload'])->name('consultants.w9.upload');
    Route::get('consultants/{consultant}/w9', [ConsultantController::class, 'w9Path'])->name('consultants.w9.show');
    Route::delete('consultants/{consultant}/w9', [ConsultantController::class, 'w9Delete'])->name('consultants.w9.destroy');
    Route::post('consultants/{consultant}/deactivate', [ConsultantController::class, 'deactivate'])->name('consultants.deactivate');
    Route::patch('consultants/{consultant}/field', [ConsultantController::class, 'patchField'])->name('consultants.patch-field');
    Route::resource('consultants', ConsultantController::class)->except(['create', 'edit']);

    Route::post('timesheets/upload', [TimesheetController::class, 'upload'])->name('timesheets.upload');
    Route::post('timesheets/save', [TimesheetController::class, 'save'])->name('timesheets.save');
    Route::post('timesheets/preview-ot', [TimesheetController::class, 'previewOt'])->name('timesheets.preview-ot');
    Route::post('timesheets', [TimesheetController::class, 'storeManual'])->name('timesheets.store');
    Route::get('timesheets/check-duplicate', [TimesheetController::class, 'checkDuplicate'])->name('timesheets.check-duplicate');
    Route::get('timesheets/template/download', [TimesheetController::class, 'downloadTemplate'])->name('timesheets.template');
    Route::patch('timesheets/{timesheet}/hours', [TimesheetController::class, 'updateHours'])->name('timesheets.update-hours');
    Route::resource('timesheets', TimesheetController::class)->only(['index', 'show']);

    Route::post('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/{invoice}/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.status');
    Route::post('invoices/update-po', [InvoiceController::class, 'updatePo'])->name('invoices.update-po');
    Route::post('invoices/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);

    Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/monthly-csv', [ReportController::class, 'downloadMonthlyCsv'])->name('reports.monthly-csv');
    Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('reports/year-end', [ReportController::class, 'yearEnd'])->name('reports.year-end');
    Route::get('reports/quickbooks', [ReportController::class, 'quickbooks'])->name('reports.quickbooks');
    Route::post('reports/save-pdf', [ReportController::class, 'savePdf'])->name('reports.save-pdf');

    Route::get('budget', [BudgetController::class, 'index'])->name('budget.index');
    Route::get('budget/{year}', [BudgetController::class, 'show'])->name('budget.show');
    Route::put('budget/{year}', [BudgetController::class, 'update'])->name('budget.update');
    Route::post('budget/check-alerts', [BudgetController::class, 'alerts'])->name('budget.alerts');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/api/dashboard', [PayrollController::class, 'apiDashboard'])->name('payroll.api.dashboard');
    Route::get('payroll/api/consultants', [PayrollController::class, 'apiConsultants'])->name('payroll.api.consultants');
});

require __DIR__.'/auth.php';
