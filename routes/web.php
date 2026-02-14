<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\AdminController;

// Public Routes - Complaint Submission
Route::get('/', [ComplaintController::class, 'create'])->name('complaint.create');
Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaint.store');
Route::get('/complaints/success/{ticketNumber}', [ComplaintController::class, 'success'])->name('complaint.success');

// Admin Routes - Dashboard
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/complaints/{complaint}', [AdminController::class, 'show'])->name('show');
    Route::post('/complaints/{complaint}/approve', [AdminController::class, 'approve'])->name('approve');
    Route::post('/complaints/{complaint}/update-response', [AdminController::class, 'updateResponse'])->name('update-response');
    Route::post('/complaints/{complaint}/resolve', [AdminController::class, 'resolve'])->name('resolve');
});
