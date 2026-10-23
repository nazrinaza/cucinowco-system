<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AgentAccessMail extends CuciNowMailable
{
    public function __construct(public string $heading, public string $messageText, public string $actionLabel, public string $actionUrl) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope($this->heading, ['category' => 'agent_account']);
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.agent-access', "{$this->heading}\n\n{$this->messageText}\n\n{$this->actionLabel}: {$this->actionUrl}");
    }
}
