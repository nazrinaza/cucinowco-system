<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AgentMediaController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GoogleCalendarController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ReferralSourceController;
use App\Http\Controllers\Admin\SiteVisitController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServicePageController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\WhatsAppRedirectController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

require __DIR__.'/agents.php';

Route::view('/', 'welcome')->name('home');
Route::get('/whatsapp', WhatsAppRedirectController::class)
    ->middleware('throttle:60,1')
    ->name('whatsapp.redirect');
Route::get('/services/{service}', [ServicePageController::class, 'show'])
    ->whereIn('service', array_keys(config('cleaning-services')))
    ->name('services.show');
Route::get('/sitemap.xml', function () {
    $urls = collect(config('cleaning-services'))
        ->keys()
        ->map(fn (string $service) => route('services.show', $service))
        ->prepend(route('home'));

    return response()
        ->view('sitemap', compact('urls'))
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::view('/resend/webhook', 'resend-webhook-status')->name('resend.webhook.status');
Route::post('/newsletter', [SubscriberController::class, 'store'])->middleware('throttle:5,1')->name('newsletter.store');
Route::match(['get', 'post'], '/newsletter/unsubscribe/{subscriber}', [SubscriberController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('newsletter.unsubscribe');

Route::middleware('guest:web')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/admin/logout', [AuthController::class, 'destroy'])->middleware('auth:web')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth:web', EnsureActiveUser::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/site-visits', [SiteVisitController::class, 'index'])->name('site-visits.index');
    Route::get('/site-visits/calendar/events', [SiteVisitController::class, 'calendarEvents'])->name('site-visits.calendar-events');
    Route::get('/site-visits/{siteVisit}', [SiteVisitController::class, 'show'])->name('site-visits.show');
    Route::patch('/site-visits/{siteVisit}', [SiteVisitController::class, 'update'])->name('site-visits.update');
    Route::patch('/site-visits/{siteVisit}/assessment', [SiteVisitController::class, 'updateAssessment'])->name('site-visits.assessment.update');
    Route::post('/site-visits/{siteVisit}/photos', [SiteVisitController::class, 'uploadPhoto'])->middleware('throttle:20,1')->name('site-visits.photos.store');
    Route::get('/site-visits/{siteVisit}/photos/{photo}', [SiteVisitController::class, 'photo'])->name('site-visits.photos.show');
    Route::delete('/site-visits/{siteVisit}/photos/{photo}', [SiteVisitController::class, 'deletePhoto'])->middleware('can:manage-users')->name('site-visits.photos.destroy');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');

    Route::middleware('can:manage-documents')->group(function () {
        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::get('/agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
        Route::patch('/agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::get('/agent-media', [AgentMediaController::class, 'index'])->name('agent-media.index');
        Route::post('/agent-media', [AgentMediaController::class, 'store'])->name('agent-media.store');
        Route::patch('/agent-media/{media}', [AgentMediaController::class, 'update'])->name('agent-media.update');
        Route::get('/agent-media/{media}/download', [AgentMediaController::class, 'download'])->name('agent-media.download');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::patch('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::patch('/quotes/{quote}/items', [QuoteController::class, 'updateItems'])->name('quotes.items.update');
        Route::patch('/quotes/{quote}/contacts', [QuoteController::class, 'updateContacts'])->name('quotes.contacts.update');
        Route::patch('/quotes/{quote}/site-visit', [QuoteController::class, 'updateSiteVisit'])->name('quotes.site-visit.update');
        Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
        Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
        Route::post('/quotes/{quote}/book', [QuoteController::class, 'book'])->name('quotes.book');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->name('invoices.payments');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('/bookings/{booking}/send', [BookingController::class, 'send'])->name('bookings.send');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::post('/subscribers', [SubscriberController::class, 'adminStore'])->name('subscribers.store');
        Route::post('/subscribers/import', [SubscriberController::class, 'import'])->name('subscribers.import');
        Route::get('/subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
        Route::patch('/subscribers/segments', [SubscriberController::class, 'updateSegments'])->name('subscribers.segments');
        Route::delete('/subscribers', [SubscriberController::class, 'bulkDestroy'])->name('subscribers.bulk-destroy');
        Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/images', [CampaignController::class, 'uploadImage'])->middleware('throttle:20,1')->name('campaigns.images.store');
        Route::get('/campaigns/{campaign}/analytics', [CampaignController::class, 'analytics'])->name('campaigns.analytics');
        Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
        Route::get('/referrals', [ReferralSourceController::class, 'index'])->name('referrals.index');
        Route::post('/referrals', [ReferralSourceController::class, 'store'])->name('referrals.store');
        Route::patch('/referrals/{referral}', [ReferralSourceController::class, 'update'])->name('referrals.update');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::get('/integrations/google-calendar', [GoogleCalendarController::class, 'index'])->name('integrations.google-calendar.index');
        Route::get('/integrations/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('integrations.google-calendar.connect');
        Route::get('/integrations/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('integrations.google-calendar.callback');
        Route::post('/integrations/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('integrations.google-calendar.disconnect');
        Route::post('/integrations/google-calendar/sync-upcoming', [GoogleCalendarController::class, 'syncUpcoming'])->name('integrations.google-calendar.sync-upcoming');
        Route::post('/bookings/{booking}/calendar/retry', [GoogleCalendarController::class, 'retry'])->name('bookings.calendar.retry');
        Route::post('/site-visits/{siteVisit}/calendar/retry', [GoogleCalendarController::class, 'retrySiteVisit'])->name('site-visits.calendar.retry');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
