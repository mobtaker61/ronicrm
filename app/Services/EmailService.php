<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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

    public function sendHtmlEmail(string $to, string $subject, string $htmlContent, ?string $from = null): array
    {
        try {
            $smtpSettings = Setting::get('smtp', []);

            if (! empty($smtpSettings['host']) && ! empty($smtpSettings['username']) && ! empty($smtpSettings['password'])) {
                $this->applySmtpConfigFromSettings($smtpSettings);
            } else {
                Log::warning('EmailService: SMTP not configured in Settings. Using default mailer (' . config('mail.default') . '). Configure SMTP in Settings to actually send emails.');
            }

            $from = $from ?? config('mail.from.address');

            Mail::html($htmlContent, function ($message) use ($to, $subject, $from, $smtpSettings) {
                $message->to($to)
                    ->subject($subject)
                    ->from($from, config('mail.from.name'));
                
                // اگر save_to_sent فعال باشد، یک کپی به فرستنده بفرست (BCC)
                // این روش ایمیل را به inbox می‌فرستد، نه sent folder
                // برای sent folder نیاز به IMAP extension است
                if ($smtpSettings['save_to_sent'] ?? false) {
                    $message->bcc($from);
                }
            });

            // اگر IMAP فعال باشد و extension نصب باشد، ایمیل را در Sent folder ذخیره کن
            if (($smtpSettings['save_to_sent'] ?? false)) {
                if (function_exists('imap_open') && !empty($smtpSettings['imap_host'])) {
                    $this->saveToSentFolder($from, $to, $subject, $htmlContent, $smtpSettings);
                } else {
                    // اگر IMAP extension نصب نیست، فقط BCC کار می‌کند
                    Log::info('IMAP extension not available or IMAP host not configured. Using BCC only.');
                }
            }

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
            
            // امتحان کردن نام‌های مختلف برای Sent folder
            $sentFolders = ['INBOX.Sent', 'INBOX/Sent', 'Sent', 'Sent Items'];
            $imap = null;
            $mailbox = null;

            // ابتدا به IMAP متصل شو بدون folder خاص
            $connectionString = "{{$imapHost}:{$imapPort}/imap{$encryptionFlag}}";
            
            Log::info('Attempting IMAP connection');
            Log::info('Host: ' . $imapHost . ', Port: ' . $imapPort . ', Encryption: ' . $imapEncryption);
            Log::info('Connection string: ' . $connectionString);
            
            $imap = @imap_open($connectionString, $username, $password, OP_HALFOPEN);
            
            if (!$imap) {
                $error = imap_last_error();
                Log::warning('IMAP initial connection failed: ' . ($error ?: 'Unknown error'));
                Log::warning('Please check: 1) IMAP host/port are correct, 2) Username/password are correct, 3) IMAP is enabled on your email server');
                return;
            }
            
            Log::info('IMAP connection successful');

            // لیست تمام folder ها را بگیر
            $folders = @imap_list($imap, $connectionString, "*");
            $sentMailbox = null;
            
            Log::info('Searching for Sent folder in ' . ($folders ? count($folders) : 0) . ' folders');
            
            if ($folders) {
                foreach ($folders as $folder) {
                    $folderName = imap_utf7_decode($folder);
                    // بررسی نام‌های مختلف برای Sent folder
                    if (stripos($folderName, 'Sent') !== false || 
                        stripos($folderName, 'INBOX.Sent') !== false ||
                        stripos($folderName, 'INBOX/Sent') !== false) {
                        $sentMailbox = $folder;
                        Log::info('Found Sent folder: ' . $folderName);
                        break;
                    }
                }
            } else {
                Log::warning('No folders found or imap_list failed: ' . imap_last_error());
            }
            
            // اگر Sent folder پیدا نشد، نام‌های استاندارد را امتحان کن
            if (!$sentMailbox) {
                foreach ($sentFolders as $sentFolder) {
                    $testMailbox = $connectionString . $sentFolder;
                    // بررسی وجود folder
                    $testFolders = @imap_list($imap, $connectionString, $sentFolder);
                    if ($testFolders && count($testFolders) > 0) {
                        $sentMailbox = $testMailbox;
                        Log::info('Using Sent folder: ' . $sentFolder);
                        break;
                    }
                }
            }
            
            // اگر هنوز پیدا نشد، از INBOX.Sent استفاده کن (Gmail style)
            if (!$sentMailbox) {
                $sentMailbox = $connectionString . 'INBOX.Sent';
                Log::info('Using default Sent folder: INBOX.Sent');
            }
            
            $mailbox = $sentMailbox;

            // ساخت header ایمیل با فرمت RFC 2822
            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . time() . "." . uniqid() . "@" . parse_url($from, PHP_URL_HOST) . ">\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";

            // ذخیره ایمیل در Sent folder
            $message = $headers . "\r\n" . $htmlContent;
            
            Log::info('Attempting to save email to Sent folder');
            Log::info('Mailbox: ' . $mailbox);
            Log::info('Message size: ' . strlen($message) . ' bytes');
            
            $result = @imap_append($imap, $mailbox, $message);
            
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
