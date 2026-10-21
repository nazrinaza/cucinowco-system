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
        $service = $this->booking->service?->name ?? $this->booking->quote?->items?->first()?->description ?? 'Cleaning service';
        $time = $this->booking->scheduled_start?->format('g:i A');
        if ($this->booking->scheduled_end) {
            $time .= ' - '.$this->booking->scheduled_end->format('g:i A');
        }

        return $this->brandedContent('emails.booking-confirmation', implode("\n", [
            "Booking confirmed: {$this->booking->booking_number}",
            "Hello {$this->booking->customer->name}, your CuciNow cleaning appointment has been scheduled.",
            "Service: {$service}",
            'Date: '.$this->booking->scheduled_start?->format('l, d M Y'),
            "Time: {$time}",
            "Location: {$this->booking->service_address}",
            'Manage via WhatsApp: '.route('whatsapp.redirect', ['text' => 'Hi CuciNow, I am contacting you about booking '.$this->booking->booking_number]),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("booking-{$this->booking->id}-{$this->booking->updated_at->timestamp}");
    }
}
