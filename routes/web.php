<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('/newsletter', [SubscriberController::class, 'store'])->middleware('throttle:5,1')->name('newsletter.store');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/admin/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::patch('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
    Route::post('/quotes/{quote}/book', [QuoteController::class, 'book'])->name('quotes.book');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->name('invoices.payments');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
});
