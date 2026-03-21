<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\CampaignRecipient;
use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\Industry;
use App\Models\Project;
use App\Services\CampaignMessageComposer;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Support\WhatsappTemplateSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = Campaign::with(['creator', 'recipients'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campaigns/Create', [
            'templates' => CampaignTemplate::orderBy('name')->get()->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'content' => $template->content,
                    'subject' => $template->subject,
                    'image' => $template->image ? asset('storage/'.$template->image) : null,
                    'type' => $template->type,
                    'whatsapp_settings' => $template->whatsapp_settings,
                ];
            }),
            'industries' => Industry::with('parent', 'children')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'projects' => Project::orderBy('name')->get(),
            'customers' => Customer::with(['contacts', 'industry', 'project'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:whatsapp,email',
            'template_id' => 'nullable|exists:campaign_templates,id',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|file|max:51200', // 50MB max - accept all file types
            'image_path' => 'nullable|string|max:500', // path from Media library (e.g. media/2026/02/xxx.jpg)
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480', // 20MB per file for email attachments
            'scheduled_at' => 'nullable|date',
            'recipient_entries' => 'required', // JSON string (frontend always stringifies) or array
            'filters' => 'nullable|array',
            'whatsapp_settings' => 'nullable', // JSON string or array (فقط نوع whatsapp)
        ]);

        $recipientEntries = $validated['recipient_entries'];
        if (is_string($recipientEntries)) {
            $decoded = json_decode($recipientEntries, true);
            $recipientEntries = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($recipientEntries) || count($recipientEntries) < 1) {
            return redirect()->back()->withErrors(['recipient_entries' => ['At least one recipient is required.']])->withInput();
        }
        foreach ($recipientEntries as $entry) {
            $cid = (int) ($entry['customer_id'] ?? 0);
            if (! $cid || ! Customer::where('id', $cid)->exists()) {
                return redirect()->back()->withErrors(['recipient_entries' => ['Invalid customer in recipient list.']])->withInput();
            }
            $contactId = isset($entry['customer_contact_id']) ? (int) $entry['customer_contact_id'] : null;
            if ($contactId && ! \App\Models\CustomerContact::where('id', $contactId)->where('customer_id', $cid)->exists()) {
                return redirect()->back()->withErrors(['recipient_entries' => ['Invalid contact for customer.']])->withInput();
            }
        }

        // Handle file upload (image, document, etc.)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('campaign-attachments', 'public');
        } elseif ($request->filled('image_path')) {
            $sourcePath = $request->input('image_path');
            if (preg_match('/^media\/[\w\/\.\-]+$/', $sourcePath) && Storage::disk('public')->exists($sourcePath)) {
                $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
                $fileName = 'campaign_'.time().'_'.uniqid().'.'.$extension;
                $destinationPath = 'campaign-attachments/'.$fileName;
                Storage::disk('public')->copy($sourcePath, $destinationPath);
                $imagePath = $destinationPath;
            }
        } elseif ($validated['template_id']) {
            // If template is selected and no new file is uploaded, copy template's file
            $template = CampaignTemplate::find($validated['template_id']);
            if ($template && $template->image) {
                // Copy template's file to campaign-attachments directory
                $sourcePath = storage_path('app/public/'.$template->image);
                if (file_exists($sourcePath)) {
                    $extension = pathinfo($template->image, PATHINFO_EXTENSION);
                    $fileName = 'campaign_'.time().'_'.uniqid().'.'.$extension;
                    $destinationPath = 'campaign-attachments/'.$fileName;

                    // Copy file
                    Storage::disk('public')->copy($template->image, $destinationPath);
                    $imagePath = $destinationPath;
                }
            }
        }

        // پیوست‌های ایمیل (فقط برای کمپین نوع email)
        $attachmentList = [];
        if ($validated['type'] === 'email' && $request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('campaign-attachments', 'public');
                $attachmentList[] = ['path' => $path, 'name' => $file->getClientOriginalName()];
            }
        }

        $whatsappSettings = WhatsappTemplateSettings::normalizeFromRequest(
            $validated['type'],
            $request->input('whatsapp_settings')
        );

        $campaign = Campaign::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduled_at'] ? now()->parse($validated['scheduled_at']) : null,
            'subject' => $validated['subject'] ?? null,
            'content' => $validated['content'],
            'image' => $imagePath,
            'attachments' => $attachmentList ?: null,
            'whatsapp_settings' => $whatsappSettings,
            'created_by' => Auth::id(),
        ]);

        // Create recipients (one per contact method)
        foreach ($recipientEntries as $entry) {
            $campaign->recipients()->create([
                'customer_id' => (int) $entry['customer_id'],
                'customer_contact_id' => ! empty($entry['customer_contact_id']) ? (int) $entry['customer_contact_id'] : null,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function show(Campaign $campaign): Response
    {
        $campaign->load([
            'recipients.customer.contacts',
            'recipients.customer.industry',
            'recipients.customerContact',
            'creator',
            'logs',
        ]);

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
        ]);
    }

    public function start(Campaign $campaign)
    {
        // Only allow starting draft or scheduled campaigns
        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->back()
                ->with('error', 'Campaign cannot be started. Current status: '.$campaign->status);
        }

        // Check if scheduled time has passed
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            return redirect()->back()
                ->with('error', 'Campaign is scheduled for a future time. Please wait until '.$campaign->scheduled_at->format('Y-m-d H:i:s'));
        }

        // Update campaign status
        $campaign->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Load recipients with customer and contact data
        $campaign->load(['recipients.customer.contacts', 'recipients.customerContact']);

        // Start sending messages in background without queue
        $pendingRecipients = $campaign->recipients->where('status', 'pending');

        // Prepare response
        $response = response()->json([
            'success' => true,
            'campaign_id' => $campaign->id,
            'total' => $pendingRecipients->count(),
            'message' => 'Campaign started. Sending messages...',
        ]);

        // If fastcgi_finish_request is available, send response immediately and process in background
        if (function_exists('fastcgi_finish_request')) {
            // Send response to client immediately
            $response->send();

            // Finish request and continue processing in background
            fastcgi_finish_request();

            // Now send messages one by one in background
            foreach ($pendingRecipients as $recipient) {
                try {
                    // Send message directly (no queue, immediate execution)
                    $this->sendMessageToRecipient($recipient, $campaign);
                } catch (\Exception $e) {
                    // Update recipient status to failed using query builder
                    try {
                        CampaignRecipient::where('id', $recipient->id)->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    } catch (\Exception $updateException) {
                        // Silent fail - recipient update error
                    }
                }
            }

            // Exit to prevent any further output
            exit;
        } else {
            // If fastcgi_finish_request is not available, send messages directly
            // This will take some time, but frontend will poll for status
            foreach ($pendingRecipients as $recipient) {
                try {
                    // Send message directly (no queue, immediate execution)
                    $this->sendMessageToRecipient($recipient, $campaign);
                } catch (\Exception $e) {
                    // Update recipient status to failed using query builder
                    try {
                        CampaignRecipient::where('id', $recipient->id)->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    } catch (\Exception $updateException) {
                        // Silent fail - recipient update error
                    }
                }
            }

            // Return JSON response
            return $response;
        }
    }

    protected function sendMessageToRecipient(CampaignRecipient $recipient, Campaign $campaign): void
    {
        try {
            $customer = $recipient->customer;
            $result = null;

            if ($campaign->type === 'whatsapp') {
                $whatsappService = app(WhatsAppService::class);
                $phone = null;
                if ($recipient->customer_contact_id) {
                    $contact = $recipient->customerContact;
                    $phone = $contact?->type === 'whatsapp' ? $contact->value : null;
                }
                if (! $phone) {
                    $whatsappContact = $customer->contacts()->where('type', 'whatsapp')->first();
                    $phone = $whatsappContact?->value;
                }
                if (! $phone) {
                    CampaignRecipient::where('id', $recipient->id)->update([
                        'status' => 'failed',
                        'error_message' => 'No WhatsApp contact found',
                    ]);

                    return;
                }

                $composer = app(CampaignMessageComposer::class);
                $message = $composer->render(
                    $campaign->content ?? '',
                    $customer,
                    $campaign->whatsapp_settings,
                    true
                );

                // Build full URL for image if exists
                $imageUrl = null;
                if ($campaign->image) {
                    $imageUrl = asset('storage/'.$campaign->image);
                }

                $result = $whatsappService->sendMessage($phone, $message, $imageUrl);
            } elseif ($campaign->type === 'email') {
                $emailService = app(EmailService::class);
                $email = null;
                if ($recipient->customer_contact_id) {
                    $contact = $recipient->customerContact;
                    $email = $contact?->type === 'email' ? $contact->value : null;
                }
                if (! $email) {
                    $email = $customer->email ?? $customer->contacts()->where('type', 'email')->first()?->value;
                }
                if (! $email) {
                    CampaignRecipient::where('id', $recipient->id)->update([
                        'status' => 'failed',
                        'error_message' => 'No email address found',
                    ]);

                    return;
                }

                $composer = app(CampaignMessageComposer::class);
                $content = $composer->render(
                    $campaign->content ?? '',
                    $customer,
                    $campaign->whatsapp_settings,
                    false
                );
                $subject = $campaign->subject
                    ? $composer->render($campaign->subject, $customer, $campaign->whatsapp_settings, false)
                    : 'Campaign Message';

                $result = $emailService->sendHtmlEmail($email, $subject, $content, null, $campaign->attachments);
                if ($result && $result['success']) {
                    CampaignLog::create([
                        'campaign_id' => $campaign->id,
                        'recipient_id' => $recipient->id,
                        'action' => 'sent',
                        'details' => [
                            'to' => $email,
                            'subject' => $subject,
                            'sent_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            if ($result && $result['success']) {
                $updateData = [
                    'status' => 'sent',
                    'sent_at' => now(),
                ];

                // If there's a warning (like timeout but message sent), include it in error_message
                if (isset($result['warning'])) {
                    $updateData['error_message'] = $result['warning'];
                }

                // Update recipient status directly using query builder to bypass model cache
                CampaignRecipient::where('id', $recipient->id)->update($updateData);
            } else {
                // Update recipient status directly using query builder to bypass model cache
                CampaignRecipient::where('id', $recipient->id)->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            // Only log critical errors (not timeouts)
            if (! str_contains($e->getMessage(), 'timed out')) {
                Log::error('Campaign message sending failed: '.$e->getMessage());
            }
            // Update recipient status directly using query builder
            CampaignRecipient::where('id', $recipient->id)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function getStatus(Campaign $campaign)
    {
        // Get fresh recipients from database (bypass cache)
        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->with('customer')
            ->get();

        $recipientsData = $recipients->map(function ($recipient) {
            return [
                'id' => $recipient->id,
                'customer_name' => $recipient->customer->name ?? 'Unknown',
                'status' => $recipient->status,
                'error_message' => $recipient->error_message,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
            ];
        });

        $total = $recipients->count();
        $sent = $recipients->where('status', 'sent')->count();
        $delivered = $recipients->where('status', 'delivered')->count();
        $failed = $recipients->where('status', 'failed')->count();
        $pending = $recipients->where('status', 'pending')->count();

        // Campaign is completed when there are no pending recipients
        $isCompleted = $pending === 0 && $total > 0;

        // Update campaign status if completed
        if ($isCompleted) {
            $campaign->refresh();
            if ($campaign->status === 'running') {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        // Refresh campaign to get latest status
        $campaign->refresh();

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_status' => $campaign->status,
            'total' => $total,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'is_completed' => $isCompleted,
            'recipients' => $recipientsData,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->status === 'running') {
            return redirect()->back()
                ->with('error', 'Cannot delete a running campaign.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }
}
