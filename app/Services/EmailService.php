<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EmailService
{
    public function sendEmail(string $to, string $subject, string $content, ?string $from = null): array
    {
        try {
            $from = $from ?? config('mail.from.address');

            Mail::raw($content, function ($message) use ($to, $subject, $from) {
                $message->to($to)
                    ->subject($subject)
                    ->from($from, config('mail.from.name'));
            });

            return [
                'success' => true,
                'status' => 'sent',
            ];
        } catch (\Exception $e) {
            Log::error('Email sending error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    public function sendHtmlEmail(string $to, string $subject, string $htmlContent, ?string $from = null, ?array $attachments = null): array
    {
        try {
            $smtpSettings = Setting::get('smtp', []);

            if (! empty($smtpSettings['host']) && ! empty($smtpSettings['username']) && ! empty($smtpSettings['password'])) {
                $this->applySmtpConfigFromSettings($smtpSettings);
            } else {
                Log::warning('EmailService: SMTP not configured in Settings. Using default mailer (' . config('mail.default') . '). Configure SMTP in Settings to actually send emails.');
            }

            $from = $from ?? config('mail.from.address');
            $fromName = config('mail.from.name') ?: 'RoniCRM';

            // ارسال با Mail::html() تا فقط بخش HTML ارسال شود و در کلاینت به‌درستی رندر شود (لینک‌ها و تگ‌ها)
            $bodyHtml = $this->wrapHtmlDocument($htmlContent);
            $attachmentsList = $attachments ?? [];

            Mail::html(new HtmlString($bodyHtml), function ($message) use ($to, $subject, $from, $fromName, $attachmentsList) {
                $message->to($to)
                    ->subject($subject)
                    ->from($from, $fromName);
                foreach ($attachmentsList as $att) {
                    if (! is_array($att)) {
                        continue;
                    }
                    $path = $att['path'] ?? null;
                    $name = $att['name'] ?? ($path ? basename($path) : 'attachment');
                    if ($path && is_string($path)) {
                        try {
                            if (Storage::disk('public')->exists($path)) {
                                $fullPath = Storage::disk('public')->path($path);
                                $message->attach($fullPath, ['as' => $name]);
                            }
                        } catch (\Throwable $e) {
                            // در صورت خطا از این پیوست صرف‌نظر می‌کنیم
                        }
                    }
                }
            });

            // فقط از طریق IMAP در پوشه Sent سرور ذخیره شود (بدون کپی در اینباکس)
            if (($smtpSettings['save_to_sent'] ?? false) && function_exists('imap_open') && !empty($smtpSettings['imap_host'])) {
                $this->saveToSentFolder($from, $to, $subject, $bodyHtml, $smtpSettings);
            } elseif (($smtpSettings['save_to_sent'] ?? false)) {
                if (empty($smtpSettings['imap_host'])) {
                    Log::info('IMAP host is empty in Settings. Fill "IMAP Host" in Settings > SMTP to save copies to Sent folder.');
                } else {
                    Log::info('IMAP extension not available. Email sent via SMTP only; not saved to Sent folder.');
                }
            }

            return [
                'success' => true,
                'status' => 'sent',
            ];
        } catch (\Exception $e) {
            Log::error('Email sending error: ' . $e->getMessage());
            Log::error('Email sending stack trace: ' . $e->getTraceAsString());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    /**
     * قرار دادن محتوا در قالب سند HTML تا در ایمیل به‌درستی به صورت HTML رندر شود (لینک‌ها، تگ‌ها، فرمت).
     */
    private function wrapHtmlDocument(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body></body></html>';
        }
        $lower = strtolower(substr($html, 0, 300));
        if (str_contains($lower, '<!doctype') || str_contains($lower, '<html')) {
            return $html;
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' . $html . '</body></html>';
    }

    private function applySmtpConfigFromSettings(array $smtpSettings): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $smtpSettings['host'],
            'mail.mailers.smtp.port' => $smtpSettings['port'] ?? 587,
            'mail.mailers.smtp.encryption' => $smtpSettings['encryption'] ?? 'tls',
            'mail.mailers.smtp.username' => $smtpSettings['username'],
            'mail.mailers.smtp.password' => $smtpSettings['password'],
            'mail.from.address' => $smtpSettings['from_address'] ?? $smtpSettings['username'],
            'mail.from.name' => $smtpSettings['from_name'] ?? 'RoniCRM',
        ]);
        app()->forgetInstance('mail.manager');
    }

    private function saveToSentFolder(string $from, string $to, string $subject, string $htmlContent, array $smtpSettings): void
    {
        if (!function_exists('imap_open')) {
            Log::warning('IMAP extension is not installed. Cannot save email to Sent folder.');
            return;
        }

        try {
            $imapHost = $smtpSettings['imap_host'] ?? '';
            $imapPort = $smtpSettings['imap_port'] ?? 993;
            $imapEncryption = $smtpSettings['imap_encryption'] ?? 'ssl';
            $username = $smtpSettings['username'] ?? '';
            $password = $smtpSettings['password'] ?? '';
            $fromName = $smtpSettings['from_name'] ?? 'RoniCRM';

            if (empty($imapHost) || empty($username) || empty($password)) {
                Log::warning('IMAP settings are incomplete. Cannot save email to Sent folder.');
                return;
            }

            // ساخت connection string برای IMAP
            // برای Gmail و برخی سرویس‌ها، نام folder ممکن است متفاوت باشد
            $encryptionFlag = $imapEncryption === 'ssl' ? '/ssl' : ($imapEncryption === 'tls' ? '/tls' : '');
            
            // ابتدا به IMAP متصل شو بدون folder خاص
            $connectionString = "{{$imapHost}:{$imapPort}/imap{$encryptionFlag}}";
            
            Log::info('Attempting IMAP connection', ['host' => $imapHost, 'port' => $imapPort]);
            
            $imap = @imap_open($connectionString, $username, $password, OP_HALFOPEN);
            
            if (!$imap) {
                $error = imap_last_error();
                Log::warning('IMAP initial connection failed: ' . ($error ?: 'Unknown error'));
                return;
            }
            
            Log::info('IMAP connection successful');

            // لیست تمام folder ها را بگیر و پوشه Sent را پیدا کن (Gmail: [Gmail]/Sent Mail، Outlook: Sent Items و غیره)
            $folders = @imap_list($imap, $connectionString, "*");
            $sentMailbox = null;
            
            if ($folders) {
                // اول به دنبال نام‌های شناخته‌شده بگرد (Gmail، Outlook)
                $preferredNames = ['[Gmail]/Sent Mail', 'Sent Mail', 'INBOX.Sent', 'INBOX/Sent', 'Sent Items', 'Sent'];
                foreach ($preferredNames as $preferred) {
                    foreach ($folders as $folder) {
                        $folderName = imap_utf7_decode($folder);
                        if (stripos($folderName, $preferred) !== false || $folderName === $connectionString . $preferred) {
                            $sentMailbox = $folder;
                            Log::info('Found Sent folder: ' . $folderName);
                            break 2;
                        }
                    }
                }
                // اگر پیدا نشد، هر پوشه‌ای که در نامش Sent باشد
                if (!$sentMailbox) {
                    foreach ($folders as $folder) {
                        $folderName = imap_utf7_decode($folder);
                        if (stripos($folderName, 'Sent') !== false) {
                            $sentMailbox = $folder;
                            Log::info('Using Sent folder: ' . $folderName);
                            break;
                        }
                    }
                }
            }
            
            if (!$sentMailbox) {
                // fallback: نام‌های معمول را امتحان کن
                foreach (['[Gmail]/Sent Mail', 'INBOX.Sent', 'Sent', 'Sent Items'] as $try) {
                    $full = $connectionString . $try;
                    $list = @imap_list($imap, $connectionString, $try);
                    if ($list && count($list) > 0) {
                        $sentMailbox = $list[0];
                        Log::info('Using Sent folder (fallback): ' . $try);
                        break;
                    }
                }
            }
            
            if (!$sentMailbox) {
                $sentMailbox = $connectionString . 'INBOX.Sent';
                Log::info('Using default Sent: INBOX.Sent');
            }
            
            $mailbox = $sentMailbox;

            // دامنه برای Message-ID (از آدرس ایمیل فرستنده)
            $domain = (\is_string($from) && str_contains($from, '@')) ? explode('@', $from)[1] : 'localhost';
            $messageId = '<' . time() . '.' . uniqid() . '@' . $domain . '>';

            // ساخت header ایمیل با فرمت RFC 2822 و خط‌های \r\n
            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";

            // بدنه با خط‌های \r\n (برخی سرورها فقط این را می‌پذیرند)
            $body = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $htmlContent);
            $message = $headers . "\r\n" . $body;
            
            // برخی سرورها انتهای پیام را با \r\n می‌خواهند
            if (!str_ends_with($message, "\r\n")) {
                $message .= "\r\n";
            }
            
            Log::info('Attempting to save email to Sent folder', ['mailbox' => $mailbox, 'size' => strlen($message)]);
            
            $result = @imap_append($imap, $mailbox, $message, '\\Seen');
            
            if (!$result) {
                $error = imap_last_error();
                Log::warning('Failed to save email to Sent folder');
                Log::warning('Error: ' . ($error ?: 'Unknown error'));
                Log::warning('Mailbox: ' . $mailbox);
                Log::warning('Connection: ' . $connectionString);
                
                // امتحان کردن با mailbox ساده‌تر
                $simpleMailbox = $connectionString . 'INBOX.Sent';
                if ($simpleMailbox !== $mailbox) {
                    Log::info('Trying alternative mailbox: ' . $simpleMailbox);
                    $result2 = @imap_append($imap, $simpleMailbox, $message);
                    if ($result2) {
                        Log::info('Email successfully saved to alternative Sent folder');
                    } else {
                        Log::warning('Alternative mailbox also failed: ' . imap_last_error());
                    }
                }
            } else {
                Log::info('Email successfully saved to Sent folder: ' . $mailbox);
            }

            @imap_close($imap);
        } catch (\Exception $e) {
            Log::error('IMAP save to sent folder error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
