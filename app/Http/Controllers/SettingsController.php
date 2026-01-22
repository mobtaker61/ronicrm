<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SocialMediaType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct()
    {
        // Check if user is admin for all methods
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->hasRole('admin')) {
                abort(403, 'Unauthorized action. Only administrators can access settings.');
            }
            return $next($request);
        });
    }

    public function index(): Response
    {
        $isAdmin = auth()->user()->hasRole('admin');
        
        $users = [];
        $roles = [];
        if ($isAdmin) {
            $users = \App\Models\User::with('roles')->orderBy('name')->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'created_at' => $user->created_at,
                ];
            });
            $roles = \Spatie\Permission\Models\Role::orderBy('name')->get()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                ];
            });
        }
        
        return Inertia::render('Settings/Index', [
            'isAdmin' => $isAdmin,
            'users' => $users,
            'roles' => $roles,
            'socialMediaTypes' => SocialMediaType::orderBy('sort_order')->get(),
            'smtpSettings' => Setting::get('smtp', [
                'host' => '',
                'port' => '587',
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from_address' => '',
                'from_name' => '',
                'enabled' => false,
                'save_to_sent' => false,
                'imap_host' => '',
                'imap_port' => '993',
                'imap_encryption' => 'ssl',
            ]),
            'ronibotSettings' => Setting::get('ronibot', [
                'api_url' => 'https://ronibot.com/api/create-message',
                'appkey' => '',
                'authkey' => '',
                'webhook_url' => 'https://crm.roniplus.ae/wpwebhook',
                'enabled' => false,
            ]),
        ]);
    }

    public function updateSmtp(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'enabled' => 'boolean',
            'save_to_sent' => 'boolean',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
        ]);

        // Don't update password if it's empty (keep existing)
        if (empty($validated['password'])) {
            $existing = Setting::get('smtp', []);
            if (isset($existing['password'])) {
                $validated['password'] = $existing['password'];
            }
        }

        Setting::set('smtp', $validated);

        // Update .env file or config cache if needed
        // For now, we'll just store in database

        return redirect()->back()
            ->with('success', 'SMTP settings updated successfully.');
    }

    public function updateRonibot(Request $request)
    {
        $validated = $request->validate([
            'api_url' => 'required|url|max:500',
            'appkey' => 'required|string|max:255',
            'authkey' => 'required|string|max:255',
            'webhook_url' => 'nullable|url|max:500',
            'enabled' => 'boolean',
        ]);

        Setting::set('ronibot', $validated);

        return redirect()->back()
            ->with('success', 'Ronibot settings updated successfully.');
    }

    public function testRonibot(Request $request)
    {
        $validated = $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        try {
            $ronibotSettings = Setting::get('ronibot', []);
            
            if (empty($ronibotSettings['appkey']) || empty($ronibotSettings['authkey'])) {
                return redirect()->back()
                    ->with('error', 'Please configure Ronibot settings first (App Key and Auth Key are required).');
            }

            if (!($ronibotSettings['enabled'] ?? false)) {
                return redirect()->back()
                    ->with('error', 'Please enable Ronibot first.');
            }

            $whatsappService = app(\App\Services\WhatsAppService::class);
            $message = $validated['test_message'] ?? 'This is a test message from RoniCRM. If you received this message, your Ronibot settings are working correctly!';
            
            $result = $whatsappService->sendMessage($validated['test_phone'], $message);

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', 'Test WhatsApp message sent successfully to ' . $validated['test_phone'] . '!');
            } else {
                return redirect()->back()
                    ->with('error', 'Failed to send test message: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Ronibot test error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to send test message: ' . $e->getMessage());
        }
    }

    public function testSmtp(Request $request)
    {
        $validated = $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $smtpSettings = Setting::get('smtp', []);
            
            if (empty($smtpSettings['host']) || empty($smtpSettings['username'])) {
                return redirect()->back()
                    ->with('error', 'Please configure SMTP settings first.');
            }

            if (empty($smtpSettings['password'])) {
                return redirect()->back()
                    ->with('error', 'SMTP password is required. Please save your SMTP settings with a password first.');
            }

            // Configure mail settings temporarily
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

            // Clear mailer instance to force reconfiguration
            app()->forgetInstance('mail.manager');

            Mail::raw('This is a test email from RoniCRM SMTP configuration. If you received this email, your SMTP settings are working correctly!', function ($message) use ($validated, $smtpSettings) {
                $message->to($validated['test_email'])
                    ->subject('RoniCRM SMTP Test Email')
                    ->from($smtpSettings['from_address'] ?? $smtpSettings['username'], $smtpSettings['from_name'] ?? 'RoniCRM');
            });

            return redirect()->back()
                ->with('success', 'Test email sent successfully to ' . $validated['test_email'] . '! Please check your inbox.');
        } catch (\Swift_TransportException $e) {
            Log::error('SMTP Transport Error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'SMTP connection failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('SMTP Test Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'smtp_settings' => [
                    'host' => $smtpSettings['host'] ?? null,
                    'port' => $smtpSettings['port'] ?? null,
                    'username' => $smtpSettings['username'] ?? null,
                ],
            ]);
            return redirect()->back()
                ->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }
}
