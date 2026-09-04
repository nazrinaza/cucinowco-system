<?php

namespace App\Providers;

use App\Listeners\HandleResendEmailEvent;
use Illuminate\Support\Facades\Event;
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
