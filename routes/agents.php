<?php

use App\Http\Controllers\Agents\AuthController;
use App\Http\Controllers\Agents\PortalController;
use App\Http\Middleware\AgentPortalHeaders;
use App\Http\Middleware\AgentSession;
use App\Http\Middleware\ApprovedAgent;
use App\Http\Middleware\CaptureAgentIntroducer;
use Illuminate\Support\Facades\Route;

Route::domain(config('agents.domain'))->prefix(config('agents.domain') ? '' : 'agents')
    ->name('agents.')->middleware([AgentPortalHeaders::class, CaptureAgentIntroducer::class])->group(function () {
        Route::get('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
        Route::get('/register', [AuthController::class, 'register'])->name('register');
        Route::get('/register/check-code', [AuthController::class, 'checkCode'])->middleware('throttle:30,1')->name('register.check-code');
        Route::post('/register', [AuthController::class, 'store'])->middleware('throttle:3,10')->name('register.store');
        Route::view('/guide', 'agents.guide')->name('guide');
        Route::get('/how-it-works', [AuthController::class, 'howItWorks'])->name('how-it-works');
        // Signed, expiring public copy of published HQ content, so Pinterest and pasted HTML can show the image.
        Route::get('/shared/{media}', [PortalController::class, 'shared'])->middleware(['signed', 'throttle:120,1'])->name('media.shared');
        Route::get('/verify/{agent}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('verify');
        Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'emailReset'])->middleware('throttle:3,1')->name('password.email');
        Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
        Route::middleware(AgentSession::class)->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/account', [PortalController::class, 'account'])->name('account');
            Route::patch('/account', [PortalController::class, 'update'])->name('account.update');
            Route::get('/account/photo', [PortalController::class, 'photo'])->name('account.photo');
            Route::post('/account/photo', [PortalController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('account.photo.update');
            Route::delete('/account/photo', [PortalController::class, 'deletePhoto'])->name('account.photo.delete');
            Route::patch('/account/payout', [PortalController::class, 'updatePayout'])->middleware('throttle:10,1')->name('account.payout');
            Route::post('/verification-email', [AuthController::class, 'resend'])->middleware('throttle:1,1')->name('verification.send');
            Route::middleware(ApprovedAgent::class)->group(function () {
                Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
                Route::get('/leads', [PortalController::class, 'leads'])->name('leads');
                Route::get('/analytics', [PortalController::class, 'analytics'])->name('analytics');
                Route::get('/marketing-tools', [PortalController::class, 'media'])->name('media');
                Route::view('/sales-kit', 'agents.sales-kit')->name('sales-kit');
                Route::get('/marketing-tools/{media}/download', [PortalController::class, 'download'])->name('media.download');
                Route::get('/marketing-tools/{media}/preview', [PortalController::class, 'preview'])->name('media.preview');
            });
        });
    });
