<?php

use Illuminate\Support\Facades\Route;
use TakepartMedia\StatamicSop\Http\Controllers\SopConsentController;
use TakepartMedia\StatamicSop\Http\Controllers\SopController;

// Registered inside Statamic's `statamic.cp.authenticated` group, so these
// inherit the `cp` prefix, the `statamic.cp.` name prefix, session, CSRF and
// auth. Full names: statamic.cp.sop.consent{,.store} — the gate matches on
// exactly those to avoid locking users out of the screen that unlocks them.

Route::get('sop/consent', [SopConsentController::class, 'show'])->name('sop.consent');
Route::post('sop/consent', [SopConsentController::class, 'store'])->name('sop.consent.store');

// SOP management CRUD. Kept below the consent routes above: `sop/consent`
// must not be swallowed by the `sop/{sop}` wildcard below. `can:manage sops`
// runs after the gate (prepended ahead of everything in this group, see
// ServiceProvider::registerGate()) — a manager with pending SOPs of their own
// still gets redirected to the consent screen first.
Route::middleware('can:manage sops')->group(function () {
    Route::get('sop', [SopController::class, 'index'])->name('sop.index');
    Route::get('sop/create', [SopController::class, 'create'])->name('sop.create');
    Route::post('sop', [SopController::class, 'store'])->name('sop.store');
    Route::get('sop/{sop}', [SopController::class, 'show'])->name('sop.show');
    Route::get('sop/{sop}/edit', [SopController::class, 'edit'])->name('sop.edit');
    Route::patch('sop/{sop}', [SopController::class, 'update'])->name('sop.update');
    Route::delete('sop/{sop}', [SopController::class, 'destroy'])->name('sop.destroy');
});
