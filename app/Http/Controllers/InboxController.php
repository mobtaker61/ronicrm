<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Display the inbox page
     */
    public function index(Request $request): Response
    {
        $selectedPhone = $request->get('phone');
        $searchPhone = $request->get('search_phone');
        
        // Search customers by phone if search_phone is provided
        $searchResults = [];
        if ($searchPhone) {
            $phone = $this->formatPhone($searchPhone);
            $searchResults = Customer::whereHas('contacts', function ($q) use ($phone) {
                $q->where(function ($q) {
                    $q->where('type', 'phone')->orWhere('type', 'whatsapp');
                })->where('value', 'like', "%{$phone}%");
            })
                ->limit(10)
                ->get()
                ->map(function ($customer) {
                    // Get phone from contacts
                    $phoneContact = $customer->contacts()->where(function ($q) {
                        $q->where('type', 'phone')->orWhere('type', 'whatsapp');
                    })->first();
                    
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $phoneContact?->value,
                        'avatar' => $customer->avatar ? asset('storage/' . $customer->avatar) : null,
                    ];
                });
        }
        
        // Get list of unique conversations (both incoming and outgoing)
        // For incoming: from_phone is the sender (conversation partner)
        // For outgoing: to_phone is the recipient (conversation partner)
        $incomingPhones = WhatsAppMessage::incoming()
            ->select('from_phone as phone')
            ->distinct()
            ->pluck('phone');
            
        $outgoingPhones = WhatsAppMessage::where('direction', 'outgoing')
            ->select('from_phone as phone') // This is actually the recipient's phone in our case
            ->whereNotNull('from_phone')
            ->distinct()
            ->pluck('phone');
        
        // Combine and get unique phone numbers
        $allPhones = $incomingPhones->merge($outgoingPhones)->unique()->filter();
        
        $conversations = collect();
        foreach ($allPhones as $phone) {
            $customer = $this->findCustomerByPhone($phone);
            
            // Get the last message (either incoming or outgoing)
            $lastMessage = WhatsAppMessage::where(function ($q) use ($phone) {
                $q->where('from_phone', $phone)
                    ->orWhere('to_phone', $phone);
            })
                ->latest()
                ->first();
            
            // Count unread incoming messages
            $unreadCount = WhatsAppMessage::where('from_phone', $phone)
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->count();
            
            // Count total messages
            $messageCount = WhatsAppMessage::where(function ($q) use ($phone) {
                $q->where('from_phone', $phone)
                    ->orWhere('to_phone', $phone);
            })->count();
            
            // Get customer name - use customer name if exists, otherwise use phone
            $displayName = $phone;
            if ($customer) {
                $displayName = $customer->name;
            }
            
            $conversations->push([
                'phone' => $phone,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/' . $customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }
        
        // Sort by last message time
        $conversations = $conversations->sortByDesc('last_message_at')->values();

        // Get messages for selected conversation
        $messages = [];
        $selectedCustomer = null;
        if ($selectedPhone) {
            $messages = WhatsAppMessage::where(function ($q) use ($selectedPhone) {
                $q->where('from_phone', $selectedPhone)
                    ->orWhere('to_phone', $selectedPhone);
            })
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) {
                    // Ensure media_url is a full URL if it exists
                    $mediaUrl = $msg->media_url;
                    if ($mediaUrl) {
                        // If it's already a full URL (starts with http), use it as is
                        // Otherwise, convert relative path to full URL
                        if (!str_starts_with($mediaUrl, 'http')) {
                            $mediaUrl = asset('storage/' . ltrim($mediaUrl, '/'));
                        }
                    }
                    
                    return [
                        'id' => $msg->id,
                        'message' => $msg->message,
                        'message_type' => $msg->message_type,
                        'media_url' => $mediaUrl, // Can be null or full URL
                        'direction' => $msg->direction,
                        'status' => $msg->status,
                        'created_at' => $msg->created_at,
                        'read_at' => $msg->read_at,
                    ];
                });
            
            // Mark messages as read
            WhatsAppMessage::where('from_phone', $selectedPhone)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'status' => 'read']);
            
            // Load customer details for selected conversation
            $selectedCustomer = $this->findCustomerByPhone($selectedPhone);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                // Format avatar URL if exists
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/' . $selectedCustomer->avatar);
                }
            }
        }

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'messages' => $messages,
            'selectedPhone' => $selectedPhone,
            'searchResults' => $searchResults,
            'selectedCustomer' => $selectedCustomer,
        ]);
    }

    /**
     * Send a reply message
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'to_phone' => 'required|string',
            'message' => 'nullable|string|max:5000',
            'media_url' => 'nullable|url',
            'media_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB max
        ]);

        try {
            $mediaUrl = $validated['media_url'] ?? null;
            
            // If file is uploaded, save it and get the URL
            if ($request->hasFile('media_file')) {
                $file = $request->file('media_file');
                $path = $file->store('whatsapp-media', 'public');
                
                // Use the current request's scheme and host (works with ngrok)
                $baseUrl = $request->getSchemeAndHttpHost();
                $mediaUrl = $baseUrl . '/storage/' . $path;
                
                Log::info('Media file uploaded for WhatsApp', [
                    'path' => $path,
                    'url' => $mediaUrl,
                    'base_url' => $baseUrl,
                ]);
            }

            // Message is optional if media is provided
            $message = $validated['message'] ?? '';
            if (empty($message) && !$mediaUrl) {
                return redirect()->back()
                    ->with('error', 'Please provide either a message or an image.');
            }

            $whatsappService = app(\App\Services\WhatsAppService::class);
            
            // If we have media but no message, send empty string (caption will be empty)
            // Ronibot API accepts empty message when file is provided
            // Note: WhatsAppService will add a default message "Image" if message is empty and file exists
            $messageToSend = $message ?: '';
            
            Log::info('Preparing to send WhatsApp message', [
                'to_phone' => $validated['to_phone'],
                'has_media' => !empty($mediaUrl),
                'media_url' => $mediaUrl,
                'has_message' => !empty($messageToSend),
                'message' => $messageToSend,
            ]);
            
            $result = $whatsappService->sendMessage(
                $validated['to_phone'],
                $messageToSend,
                $mediaUrl
            );

            Log::info('WhatsApp send result', [
                'success' => $result['success'] ?? false,
                'error' => $result['error'] ?? null,
                'message_id' => $result['message_id'] ?? null,
            ]);

            // Always save the message to database, even if API call fails
            // This ensures the message appears in the inbox
            $customer = $this->findCustomerByPhone($validated['to_phone']);
            
            $savedMessage = WhatsAppMessage::create([
                'message_id' => $result['message_id'] ?? null,
                'from_phone' => $validated['to_phone'], // Recipient's phone (for grouping conversations)
                'to_phone' => $validated['to_phone'], // Recipient's phone
                'message' => $messageToSend ?: null, // Store null if empty, not empty string
                'message_type' => $mediaUrl ? 'image' : 'text',
                'media_url' => $mediaUrl, // Store full URL
                'customer_id' => $customer?->id,
                'direction' => 'outgoing',
                'status' => $result['success'] ? 'sent' : 'failed', // Mark as failed if API call failed
            ]);
            
            Log::info('Outgoing WhatsApp message saved to database', [
                'message_id' => $savedMessage->id,
                'has_media' => !empty($mediaUrl),
                'media_url' => $mediaUrl,
                'has_message' => !empty($messageToSend),
                'message' => $messageToSend,
                'api_success' => $result['success'] ?? false,
            ]);

            if ($result['success']) {
                // Use Inertia redirect to preserve state and show the new message
                return redirect()->route('inbox.index', ['phone' => $validated['to_phone']])
                    ->with('success', 'Message sent successfully.')
                    ->with('refresh', true); // Signal to frontend to refresh messages
            } else {
                Log::error('Failed to send WhatsApp message via API', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'to_phone' => $validated['to_phone'],
                    'has_media' => !empty($mediaUrl),
                    'message_saved_id' => $savedMessage->id,
                ]);
                // Still redirect to show the message in inbox, but with error notification
                return redirect()->route('inbox.index', ['phone' => $validated['to_phone']])
                    ->with('error', 'Message saved but failed to send via WhatsApp: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error sending message: ' . $e->getMessage());
        }
    }

    /**
     * Create customer from phone number
     */
    public function createCustomer(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $phone = $this->formatPhone($validated['phone']);

        // Check if customer already exists
        $customer = $this->findCustomerByPhone($phone);
        if ($customer) {
            return redirect()->back()
                ->with('error', 'Customer already exists with this phone number.');
        }

        // Create new customer
        $customer = Customer::create([
            'name' => $validated['name'],
            'type' => 'person',
            'status' => 'lead',
            'source' => 'whatsapp',
            'created_by' => Auth::id(),
        ]);

        // Create phone contact
        $customer->contacts()->create([
            'type' => 'whatsapp',
            'value' => $phone,
            'is_primary' => true,
        ]);

        // Create email contact if provided
        if (!empty($validated['email'])) {
            $customer->contacts()->create([
                'type' => 'email',
                'value' => $validated['email'],
                'is_primary' => false,
            ]);
        }

        // Update existing messages to link to this customer
        WhatsAppMessage::where('from_phone', $phone)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id]);

        return redirect()->back()
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Find customer by phone number (helper method)
     * Searches only in customer_contacts table
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $phone = $this->formatPhone($phone);

        // Search in customer_contacts table only
        $contact = \App\Models\CustomerContact::where(function ($q) {
            $q->where('type', 'phone')->orWhere('type', 'whatsapp');
        })
            ->where('value', $phone)
            ->first();

        return $contact?->customer;
    }

    /**
     * Format phone number
     */
    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, '+');
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }
        return $phone;
    }
}
