<?php

use App\Http\Controllers\Agents\AuthController;
use App\Http\Controllers\Agents\PortalController;
use App\Http\Middleware\AgentPortalHeaders;
use App\Http\Middleware\AgentSession;
use App\Http\Middleware\ApprovedAgent;
use Illuminate\Support\Facades\Route;

Route::domain(config('agents.domain'))->prefix(config('agents.domain') ? '' : 'agents')
    ->name('agents.')->middleware(AgentPortalHeaders::class)->group(function () {
        Route::get('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
        Route::get('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:3,10')->name('register.store');
        Route::view('/guide', 'agents.guide')->name('guide');
        Route::get('/verify/{agent}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('verify');
        Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'emailReset'])->middleware('throttle:3,1')->name('password.email');
        Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
        Route::middleware(AgentSession::class)->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/account', [PortalController::class, 'account'])->name('account');
            Route::patch('/account', [PortalController::class, 'update'])->name('account.update');
            Route::post('/verification-email', [AuthController::class, 'resend'])->middleware('throttle:1,1')->name('verification.send');
            Route::middleware(ApprovedAgent::class)->group(function () {
                Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
                Route::get('/leads', [PortalController::class, 'leads'])->name('leads');
                Route::get('/analytics', [PortalController::class, 'analytics'])->name('analytics');
                Route::get('/marketing-tools', [PortalController::class, 'media'])->name('media');
                Route::get('/marketing-tools/{media}/download', [PortalController::class, 'download'])->name('media.download');
                Route::get('/marketing-tools/{media}/preview', [PortalController::class, 'preview'])->name('media.preview');
            });
        });
    });
