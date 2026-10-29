<?php

namespace App\Mail;

use App\Models\ProformaInvoice;
use App\Support\QuotationHtmlSanitizer;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class ProformaInvoiceMail extends CuciNowMailable
{
    public function __construct(public ProformaInvoice $proformaInvoice) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "CuciNow proforma invoice {$this->proformaInvoice->proforma_number}",
            ['category' => 'proforma_invoice', 'proforma_invoice_id' => $this->proformaInvoice->id],
        );
    }

    public function content(): Content
    {
        $sanitizer = app(QuotationHtmlSanitizer::class);
        $items = $this->proformaInvoice->items->map(function ($item) use ($sanitizer): string {
            $notes = $sanitizer->toPlainText($item->notes);

            return "- {$item->description}: RM ".number_format($item->amount, 2).($notes ? "\n  ".str_replace("\n", "\n  ", $notes) : '');
        })->implode("\n");

        return $this->brandedContent('emails.proforma-invoice', implode("\n", [
            "Proforma Invoice {$this->proformaInvoice->proforma_number}",
            'Generated: '.$this->proformaInvoice->issued_at->format('d M Y'),
            $this->proformaInvoice->title,
            'Bill to: '.($this->proformaInvoice->customer->company_name ?: $this->proformaInvoice->customer->name),
            $this->proformaInvoice->customer->name,
            '', $items, '',
            'Deposit payment of '.$this->percent($this->proformaInvoice->deposit_percentage).'% (RM '.number_format($this->proformaInvoice->deposit_amount, 2).') to confirm slot',
            'Balance of '.$this->percent($this->proformaInvoice->balance_percentage).'% (RM '.number_format($this->proformaInvoice->balance_amount, 2).') after job completed',
            'Grand Total: RM '.number_format($this->proformaInvoice->total, 2),
            ...filled($this->proformaInvoice->sales_person_name) ? ['', 'CuciNow sales contact', $this->proformaInvoice->sales_person_name, $this->proformaInvoice->sales_person_title, ...filled($this->proformaInvoice->sales_person_phone) ? [$this->proformaInvoice->sales_person_phone] : []] : [],
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("proforma-{$this->proformaInvoice->id}-{$this->proformaInvoice->updated_at->timestamp}");
    }

    private function percent(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }
}
