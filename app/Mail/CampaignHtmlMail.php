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

    /** آدرس گیرنده (نام $to در والد استفاده شده) */
    protected string $recipientEmail;

    /** موضوع ایمیل (نام $subject در والد استفاده شده) */
    protected string $subjectLine;

    /** محتوای HTML */
    protected string $htmlBody;

    /** آدرس فرستنده */
    protected string $senderEmail;

    /** نام فرستنده */
    protected string $senderName;

    public function __construct(
        string $subject,
        string $htmlContent,
        string $to,
        string $fromAddress,
        string $fromName = 'RoniCRM',
        array $attachments = []
    ) {
        $this->subjectLine = $subject;
        $this->htmlBody = $htmlContent;
        $this->recipientEmail = $to;
        $this->senderEmail = $fromAddress;
        $this->senderName = $fromName;
        $this->attachmentsList = is_array($attachments) ? $attachments : [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: new \Illuminate\Mail\Mailables\Address($this->senderEmail, $this->senderName),
            to: [new \Illuminate\Mail\Mailables\Address($this->recipientEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: null,
            html: null,
            text: null,
            markdown: null,
            with: [],
            htmlString: $this->htmlBody,
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
