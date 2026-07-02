<?php

use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('categories', CategoryController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('items', ItemController::class)->parameters(['items' => 'item']);

    Route::get('/stock/in', [StockController::class, 'stockInForm'])->name('stock.in');
    Route::post('/stock/in', [StockController::class, 'stockIn'])->middleware('can:manage_stock')->name('stock.in.store');
    Route::get('/stock/out', [StockController::class, 'stockOutForm'])->name('stock.out');
    Route::post('/stock/out', [StockController::class, 'stockOut'])->middleware('can:manage_stock')->name('stock.out.store');
    Route::get('/stock/adjust', [StockController::class, 'adjustForm'])->name('stock.adjust');
    Route::post('/stock/adjust', [StockController::class, 'adjust'])->middleware('can:manage_stock')->name('stock.adjust.store');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
});

Route::post('/branch/switch', [BranchSwitchController::class, 'update'])
    ->middleware(['auth', 'role.min:Administrator'])
    ->name('branch.switch');

require __DIR__.'/auth.php';
