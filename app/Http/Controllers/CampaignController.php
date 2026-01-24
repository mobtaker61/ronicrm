<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\Industry;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
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
                    'image' => $template->image ? asset('storage/' . $template->image) : null,
                    'type' => $template->type,
                ];
            }),
            'industries' => Industry::with('parent', 'children')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'customers' => Customer::with(['contacts', 'industry'])->orderBy('name')->get(),
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
            'scheduled_at' => 'nullable|date',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:customers,id',
            'filters' => 'nullable|array',
        ]);

        // Handle file upload (image, document, etc.)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('campaign-attachments', 'public');
        } elseif ($validated['template_id']) {
            // If template is selected and no new file is uploaded, copy template's file
            $template = CampaignTemplate::find($validated['template_id']);
            if ($template && $template->image) {
                // Copy template's file to campaign-attachments directory
                $sourcePath = storage_path('app/public/' . $template->image);
                if (file_exists($sourcePath)) {
                    $extension = pathinfo($template->image, PATHINFO_EXTENSION);
                    $fileName = 'campaign_' . time() . '_' . uniqid() . '.' . $extension;
                    $destinationPath = 'campaign-attachments/' . $fileName;
                    
                    // Copy file
                    Storage::disk('public')->copy($template->image, $destinationPath);
                    $imagePath = $destinationPath;
                }
            }
        }

        $campaign = Campaign::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduled_at'] ? now()->parse($validated['scheduled_at']) : null,
            'subject' => $validated['subject'] ?? null,
            'content' => $validated['content'],
            'image' => $imagePath,
            'created_by' => auth()->id(),
        ]);

        // Create recipients
        foreach ($validated['recipient_ids'] as $customerId) {
            $campaign->recipients()->create([
                'customer_id' => $customerId,
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
            'creator',
            'logs'
        ]);

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
        ]);
    }

    public function start(Campaign $campaign)
    {
        // Only allow starting draft or scheduled campaigns
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->back()
                ->with('error', 'Campaign cannot be started. Current status: ' . $campaign->status);
        }

        // Check if scheduled time has passed
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            return redirect()->back()
                ->with('error', 'Campaign is scheduled for a future time. Please wait until ' . $campaign->scheduled_at->format('Y-m-d H:i:s'));
        }

        // Update campaign status
        $campaign->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Load recipients with customer data
        $campaign->load(['recipients.customer.contacts']);

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
                    Log::error('Error sending campaign message: ' . $e->getMessage(), [
                        'recipient_id' => $recipient->id,
                        'campaign_id' => $campaign->id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Update recipient status to failed
                    try {
                        $recipient->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    } catch (\Exception $updateException) {
                        Log::error('Error updating recipient status: ' . $updateException->getMessage());
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
                    Log::error('Error sending campaign message: ' . $e->getMessage(), [
                        'recipient_id' => $recipient->id,
                        'campaign_id' => $campaign->id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Update recipient status to failed
                    try {
                        $recipient->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    } catch (\Exception $updateException) {
                        Log::error('Error updating recipient status: ' . $updateException->getMessage());
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
                // Get WhatsApp contact (not phone, as they are separate entities)
                $whatsappContact = $customer->contacts()->where('type', 'whatsapp')->first();
                $phone = $whatsappContact?->value;
                
                if (!$phone) {
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No WhatsApp contact found',
                    ]);
                    return;
                }

                // Replace variables in content
                $message = $this->replaceVariables($campaign->content ?? '', $customer);
                
                // Build full URL for image if exists
                $imageUrl = null;
                if ($campaign->image) {
                    $imageUrl = asset('storage/' . $campaign->image);
                }
                
                $result = $whatsappService->sendMessage($phone, $message, $imageUrl);
            } elseif ($campaign->type === 'email') {
                $emailService = app(EmailService::class);
                $email = $customer->email ?? $customer->contacts()->where('type', 'email')->first()?->value;
                
                if (!$email) {
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No email address found',
                    ]);
                    return;
                }

                // Replace variables in content
                $content = $this->replaceVariables($campaign->content ?? '', $customer);
                $subject = $campaign->subject ? $this->replaceVariables($campaign->subject, $customer) : 'Campaign Message';
                
                $result = $emailService->sendHtmlEmail($email, $subject, $content);
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
                
                $recipient->update($updateData);
            } else {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Campaign message sending failed: ' . $e->getMessage());
            $recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function replaceVariables(string $content, $customer): string
    {
        $variables = [
            '{name}' => $customer->name,
            '{company}' => $customer->company_name ?? '',
            '{email}' => $customer->email ?? '',
            '{phone}' => $customer->phone ?? '',
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    public function getStatus(Campaign $campaign)
    {
        $campaign->load(['recipients.customer']);
        
        $recipients = $campaign->recipients->map(function ($recipient) {
            return [
                'id' => $recipient->id,
                'customer_name' => $recipient->customer->name ?? 'Unknown',
                'status' => $recipient->status,
                'error_message' => $recipient->error_message,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
            ];
        });

        $total = $campaign->recipients->count();
        $sent = $campaign->recipients->where('status', 'sent')->count();
        $delivered = $campaign->recipients->where('status', 'delivered')->count();
        $failed = $campaign->recipients->where('status', 'failed')->count();
        $pending = $campaign->recipients->where('status', 'pending')->count();
        
        $isCompleted = $pending === 0 && ($sent + $delivered + $failed) === $total;
        
        if ($isCompleted && $campaign->status === 'running') {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_status' => $campaign->status,
            'total' => $total,
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'is_completed' => $isCompleted,
            'recipients' => $recipients,
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
