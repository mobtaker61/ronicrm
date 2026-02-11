<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CampaignHtmlMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<int, array{path: string, name: string}> */
    public array $attachmentsList;

    public function __construct(
        public string $subject,
        public string $htmlContent,
        public string $to,
        public string $fromAddress,
        public string $fromName = 'RoniCRM',
        array $attachments = []
    ) {
        $this->attachmentsList = is_array($attachments) ? $attachments : [];
    }

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
        // استفاده از htmlString تا بدون وابستگی به فایل ویو، ایمیل حتماً به صورت HTML ارسال شود
        return new Content(
            view: null,
            html: null,
            text: null,
            markdown: null,
            with: [],
            htmlString: $this->htmlContent,
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->attachmentsList as $att) {
            if (! is_array($att)) {
                continue;
            }
            $path = $att['path'] ?? null;
            $name = $att['name'] ?? ($path ? basename($path) : 'attachment');
            if (! $path || ! is_string($path)) {
                continue;
            }
            try {
                if (Storage::disk('public')->exists($path)) {
                    $out[] = Attachment::fromStorageDisk('public', $path)->as($name);
                }
            } catch (\Throwable $e) {
                // در صورت خطا (مثلاً فایل حذف شده) از آن پیوست صرف‌نظر می‌کنیم
                continue;
            }
        }
        return $out;
    }
}
