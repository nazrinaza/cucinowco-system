<?php

namespace App\Providers;

use App\Listeners\HandleResendEmailEvent;
use App\Listeners\RecordDocumentEmailSent;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailClicked;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDelivered;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;
use Resend\Laravel\Events\EmailOpened;
use Resend\Laravel\Events\EmailSent;
use Resend\Laravel\Events\EmailSuppressed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-documents', fn (User $user) => in_array($user->role, ['admin', 'office'], true));
        Gate::define('manage-users', fn (User $user) => $user->role === 'admin');

        Event::listen(MessageSent::class, RecordDocumentEmailSent::class);
        foreach ([
            EmailSent::class,
            EmailDelivered::class,
            EmailDeliveryDelayed::class,
            EmailOpened::class,
            EmailClicked::class,
            EmailBounced::class,
            EmailComplained::class,
            EmailSuppressed::class,
            EmailFailed::class,
        ] as $event) {
            Event::listen($event, HandleResendEmailEvent::class);
        }
    }
}
