<?php

use App\Http\Controllers\ArisanController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\SavingsPayoutController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::get('/receipts/{type}/{id}', [ReceiptController::class, 'show'])
        ->whereIn('type', ['cash', 'savings', 'loan-payment', 'arisan-payment', 'arisan-payout'])
        ->whereNumber('id')
        ->name('receipts.show');
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');

    Route::resource('members', MemberController::class)->except('destroy');
    Route::post('/members/{member}/deactivate', [MemberController::class, 'deactivate'])
        ->name('members.deactivate');
    Route::post('/members/{member}/activate', [MemberController::class, 'activate'])
        ->name('members.activate');

    Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
    Route::get('/cash/create', [CashController::class, 'create'])->name('cash.create');
    Route::post('/cash/categories', [CashController::class, 'storeCategory'])->name('cash.categories.store');
    Route::post('/cash', [CashController::class, 'store'])->name('cash.store');
    Route::get('/cash/{transaction}', [CashController::class, 'show'])->name('cash.show');
    Route::post('/cash/{transaction}/reverse', [CashController::class, 'reverse'])->name('cash.reverse');

    Route::get('/savings/payouts', [SavingsPayoutController::class, 'index'])->name('savings.payouts.index');
    Route::post('/savings/payouts', [SavingsPayoutController::class, 'store'])->middleware('role:ketua')->name('savings.payouts.store');
    Route::get('/savings/payouts/{batch}', [SavingsPayoutController::class, 'show'])->name('savings.payouts.show');
    Route::post('/savings/payouts/{batch}/approve', [SavingsPayoutController::class, 'approve'])->middleware('role:ketua')->name('savings.payouts.approve');
    Route::post('/savings/payouts/{batch}/process', [SavingsPayoutController::class, 'process'])->middleware('role:bendahara')->name('savings.payouts.process');
    Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
    Route::get('/savings/{account}', [SavingsController::class, 'show'])->name('savings.show');
    Route::get('/savings/{account}/{type}', [SavingsController::class, 'createTransaction'])->name('savings.transaction.create');
    Route::post('/savings/{account}/transactions', [SavingsController::class, 'storeTransaction'])->name('savings.transaction.store');

    Route::get('/arisan', [ArisanController::class, 'index'])->name('arisan.index');
    Route::get('/arisan/create', [ArisanController::class, 'create'])->name('arisan.create');
    Route::post('/arisan', [ArisanController::class, 'store'])->name('arisan.store');
    Route::get('/arisan/{cycle}', [ArisanController::class, 'show'])->name('arisan.show');
    Route::post('/arisan/rounds/{round}/payments', [ArisanController::class, 'payment'])->name('arisan.payment');
    Route::post('/arisan/rounds/{round}/draw', [ArisanController::class, 'draw'])->name('arisan.draw');
    Route::post('/arisan/payouts/{payout}/pay', [ArisanController::class, 'payPayout'])->name('arisan.payout.pay');

    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/approvals', [LoanController::class, 'approvals'])->name('loans.approvals');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('/loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
    Route::get('/installments', [InstallmentController::class, 'index'])->name('installments.index');
    Route::get('/installments/payments', [InstallmentController::class, 'payments'])->name('installments.payments');
    Route::post('/loans/{loan}/payments', [InstallmentController::class, 'store'])->name('loans.payments.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/validate', [ReportController::class, 'validate'])->name('reports.validate');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::middleware('role:ketua')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'create'])->name('create');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->name('download');
        Route::post('/restore', [BackupController::class, 'restore'])->name('restore');
    });

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
});
