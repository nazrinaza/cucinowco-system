<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class BookingConfirmationMail extends CuciNowMailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "Booking confirmed: {$this->booking->booking_number}",
            ['category' => 'booking_confirmation', 'booking_id' => $this->booking->id],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-confirmation');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("booking-{$this->booking->id}-{$this->booking->updated_at->timestamp}");
    }
}
