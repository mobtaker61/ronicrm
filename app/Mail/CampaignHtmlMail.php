<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignHtmlMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subject,
        public string $htmlContent,
        public string $to,
        public string $fromAddress,
        public string $fromName = 'RoniCRM'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
            from: new \Illuminate\Mail\Mailables\Address($this->fromAddress, $this->fromName),
            to: [new \Illuminate\Mail\Mailables\Address($this->to)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-html',
            with: ['content' => $this->htmlContent],
        );
    }
}
