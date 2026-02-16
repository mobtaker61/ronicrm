<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\InstagramMessage;
use App\Models\TelegramMessage;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * Display the inbox page (WhatsApp or Telegram channel).
     */
    public function index(Request $request): Response
    {
        $channel = $request->get('channel', 'whatsapp');
        $selectedContact = $request->get('phone');
        if ($channel === 'telegram') {
            $selectedContact = $request->get('chat_id', $selectedContact);
        }
        if ($channel === 'instagram') {
            $selectedContact = $request->get('ig_user_id', $selectedContact);
        }
        $searchPhone = trim((string) $request->get('search_phone', ''));

        $searchResults = [];
        if (strlen($searchPhone) >= 2) {
            $searchTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchPhone);
            $phoneDigits = preg_replace('/[^0-9]/', '', $searchPhone);

            if ($channel === 'telegram') {
                $query = Customer::query()
                    ->whereHas('contacts', function ($cq) {
                        $cq->where('type', 'telegram');
                    })
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('type', 'telegram')->where('value', 'like', '%' . $searchTerm . '%');
                            });
                    });
                $searchResults = $query->limit(15)->get()->map(function ($customer) {
                    $tg = $customer->contacts()->where('type', 'telegram')->first();
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => null,
                        'chat_id' => $tg?->value,
                        'ig_user_id' => null,
                        'avatar' => $customer->avatar ? asset('storage/' . $customer->avatar) : null,
                    ];
                })->values()->all();
            } elseif ($channel === 'instagram') {
                $query = Customer::query()
                    ->whereHas('contacts', function ($cq) {
                        $cq->where('type', 'instagram');
                    })
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('type', 'instagram')->where('value', 'like', '%' . $searchTerm . '%');
                            });
                    });
                $searchResults = $query->limit(15)->get()->map(function ($customer) {
                    $ig = $customer->contacts()->where('type', 'instagram')->first();
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => null,
                        'chat_id' => null,
                        'ig_user_id' => $ig?->value,
                        'avatar' => $customer->avatar ? asset('storage/' . $customer->avatar) : null,
                    ];
                })->values()->all();
            } else {
                $query = Customer::query()
                    ->where(function ($q) use ($searchTerm, $phoneDigits) {
                        $q->where('name', 'like', '%' . $searchTerm . '%');
                        if (strlen($phoneDigits) >= 2) {
                            $q->orWhereHas('contacts', function ($cq) use ($phoneDigits) {
                                $cq->where(function ($cq) {
                                    $cq->where('type', 'phone')->orWhere('type', 'whatsapp');
                                })->where('value', 'like', '%' . $phoneDigits . '%');
                            });
                        }
                    });
                $searchResults = $query->limit(15)->get()->map(function ($customer) {
                    $phoneContact = $customer->contacts()->where(function ($q) {
                        $q->where('type', 'phone')->orWhere('type', 'whatsapp');
                    })->first();
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $phoneContact?->value,
                        'chat_id' => null,
                        'ig_user_id' => null,
                        'avatar' => $customer->avatar ? asset('storage/' . $customer->avatar) : null,
                    ];
                })->filter(fn ($r) => !empty($r['phone']))->values()->all();
            }
        }

        if ($channel === 'telegram') {
            $conversations = $this->buildTelegramConversations();
            $messages = [];
            $selectedCustomer = null;
            if ($selectedContact) {
                $messages = TelegramMessage::forChat($selectedContact)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($msg) {
                        return [
                            'id' => $msg->id,
                            'message' => $msg->message,
                            'message_type' => $msg->message_type,
                            'media_url' => $msg->media_url,
                            'direction' => $msg->direction,
                            'status' => $msg->status,
                            'created_at' => $msg->created_at,
                            'read_at' => $msg->read_at,
                        ];
                    });
                TelegramMessage::forChat($selectedContact)->whereNull('read_at')
                    ->update(['read_at' => now(), 'status' => 'read']);
                $selectedCustomer = $this->findCustomerByTelegramChatId($selectedContact);
                if ($selectedCustomer) {
                    $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                    if ($selectedCustomer->avatar) {
                        $selectedCustomer->avatar = asset('storage/' . $selectedCustomer->avatar);
                    }
                }
            }
        } elseif ($channel === 'instagram') {
            $conversations = $this->buildInstagramConversations();
            $messages = [];
            $selectedCustomer = null;
            if ($selectedContact) {
                $messages = InstagramMessage::forIgUser($selectedContact)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($msg) {
                        return [
                            'id' => $msg->id,
                            'message' => $msg->message,
                            'message_type' => $msg->message_type,
                            'media_url' => $msg->media_url,
                            'direction' => $msg->direction,
                            'status' => $msg->status,
                            'created_at' => $msg->created_at,
                            'read_at' => $msg->read_at,
                        ];
                    });
                InstagramMessage::forIgUser($selectedContact)->whereNull('read_at')
                    ->update(['read_at' => now(), 'status' => 'read']);
                $selectedCustomer = $this->findCustomerByInstagramId($selectedContact);
                if ($selectedCustomer) {
                    $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                    if ($selectedCustomer->avatar) {
                        $selectedCustomer->avatar = asset('storage/' . $selectedCustomer->avatar);
                    }
                }
            }
        } else {
            $conversations = $this->buildWhatsAppConversations();
            $messages = [];
            $selectedCustomer = null;
            if ($selectedContact) {
                $messages = WhatsAppMessage::where(function ($q) use ($selectedContact) {
                    $q->where('from_phone', $selectedContact)->orWhere('to_phone', $selectedContact);
                })
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(function ($msg) {
                        $mediaUrl = $msg->media_url;
                        if ($mediaUrl && !str_starts_with($mediaUrl, 'http')) {
                            $mediaUrl = asset('storage/' . ltrim($mediaUrl, '/'));
                        }
                        return [
                            'id' => $msg->id,
                            'message' => $msg->message,
                            'message_type' => $msg->message_type,
                            'media_url' => $mediaUrl,
                            'direction' => $msg->direction,
                            'status' => $msg->status,
                            'created_at' => $msg->created_at,
                            'read_at' => $msg->read_at,
                        ];
                    });
                WhatsAppMessage::where('from_phone', $selectedContact)
                    ->whereNull('read_at')
                    ->update(['read_at' => now(), 'status' => 'read']);
                $selectedCustomer = $this->findCustomerByPhone($selectedContact);
                if ($selectedCustomer) {
                    $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                    if ($selectedCustomer->avatar) {
                        $selectedCustomer->avatar = asset('storage/' . $selectedCustomer->avatar);
                    }
                }
            }
        }

        return Inertia::render('Inbox/Index', [
            'channel' => $channel,
            'conversations' => $conversations,
            'messages' => $messages,
            'selectedPhone' => $channel === 'whatsapp' ? $selectedContact : null,
            'selectedChatId' => $channel === 'telegram' ? $selectedContact : null,
            'selectedIgUserId' => $channel === 'instagram' ? $selectedContact : null,
            'searchResults' => $searchResults,
            'selectedCustomer' => $selectedCustomer ?? null,
        ]);
    }

    protected function buildWhatsAppConversations(): \Illuminate\Support\Collection
    {
        $incomingPhones = WhatsAppMessage::incoming()->select('from_phone as phone')->distinct()->pluck('phone');
        $outgoingPhones = WhatsAppMessage::where('direction', 'outgoing')
            ->select('from_phone as phone')->whereNotNull('from_phone')->distinct()->pluck('phone');
        $allPhones = $incomingPhones->merge($outgoingPhones)->unique()->filter();

        $conversations = collect();
        foreach ($allPhones as $phone) {
            $customer = $this->findCustomerByPhone($phone);
            $lastMessage = WhatsAppMessage::where(function ($q) use ($phone) {
                $q->where('from_phone', $phone)->orWhere('to_phone', $phone);
            })->latest()->first();
            $unreadCount = WhatsAppMessage::where('from_phone', $phone)->where('direction', 'incoming')->whereNull('read_at')->count();
            $messageCount = WhatsAppMessage::where(function ($q) use ($phone) {
                $q->where('from_phone', $phone)->orWhere('to_phone', $phone);
            })->count();
            $customerName = $customer?->name ?? '';
            $displayName = ($customer && trim((string) $customerName) !== '' && $customerName !== $phone) ? $customerName : $phone;
            $conversations->push([
                'phone' => $phone,
                'chat_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/' . $customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }
        return $conversations->sortByDesc('last_message_at')->values();
    }

    protected function buildTelegramConversations(): \Illuminate\Support\Collection
    {
        $incomingChatIds = TelegramMessage::incoming()->select('chat_id')->distinct()->pluck('chat_id');
        $outgoingChatIds = TelegramMessage::where('direction', 'outgoing')->select('chat_id')->distinct()->pluck('chat_id');
        $allChatIds = $incomingChatIds->merge($outgoingChatIds)->unique()->filter();

        $conversations = collect();
        foreach ($allChatIds as $chatId) {
            $customer = $this->findCustomerByTelegramChatId($chatId);
            $lastMessage = TelegramMessage::forChat($chatId)->latest()->first();
            $unreadCount = TelegramMessage::forChat($chatId)->where('direction', 'incoming')->whereNull('read_at')->count();
            $messageCount = TelegramMessage::forChat($chatId)->count();
            $displayName = $customer ? $customer->name : ($lastMessage ? ($lastMessage->from_username ?? $chatId) : $chatId);
            $conversations->push([
                'phone' => null,
                'chat_id' => $chatId,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/' . $customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }
        return $conversations->sortByDesc('last_message_at')->values();
    }

    protected function buildInstagramConversations(): \Illuminate\Support\Collection
    {
        $incomingIds = InstagramMessage::incoming()->select('ig_user_id')->distinct()->pluck('ig_user_id');
        $outgoingIds = InstagramMessage::where('direction', 'outgoing')->select('ig_user_id')->distinct()->pluck('ig_user_id');
        $allIds = $incomingIds->merge($outgoingIds)->unique()->filter();

        $conversations = collect();
        foreach ($allIds as $igUserId) {
            $customer = $this->findCustomerByInstagramId($igUserId);
            $lastMessage = InstagramMessage::forIgUser($igUserId)->latest()->first();
            $unreadCount = InstagramMessage::forIgUser($igUserId)->where('direction', 'incoming')->whereNull('read_at')->count();
            $messageCount = InstagramMessage::forIgUser($igUserId)->count();
            $displayName = $customer ? $customer->name : ($lastMessage ? ($lastMessage->from_username ?? $igUserId) : $igUserId);
            $conversations->push([
                'phone' => null,
                'chat_id' => null,
                'ig_user_id' => $igUserId,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/' . $customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }
        return $conversations->sortByDesc('last_message_at')->values();
    }

    protected function findCustomerByTelegramChatId(string $chatId): ?Customer
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $chatId)->first();
        return $contact?->customer;
    }

    protected function findCustomerByInstagramId(string $igUserId): ?Customer
    {
        $contact = CustomerContact::where('type', 'instagram')->where('value', $igUserId)->first();
        if ($contact) {
            return $contact->customer;
        }
        $msg = InstagramMessage::where('ig_user_id', $igUserId)->whereNotNull('customer_id')->first();
        return $msg?->customer;
    }

    /**
     * Send a reply message (WhatsApp or Telegram).
     */
    public function sendMessage(Request $request)
    {
        $channel = $request->get('channel', 'whatsapp');
        $mediaUrlInput = $request->input('media_url');
        if ($mediaUrlInput !== null && (trim((string) $mediaUrlInput) === '' || $mediaUrlInput === 'null')) {
            $request->merge(['media_url' => null]);
        }

        $rules = [
            'message' => 'nullable|string|max:5000',
            'media_url' => 'nullable|url',
            'media_file' => 'nullable|file|max:51200',
        ];
        if ($channel === 'telegram') {
            $rules['to_chat_id'] = 'required|string';
        } elseif ($channel === 'instagram') {
            $rules['to_ig_user_id'] = 'required|string';
        } else {
            $rules['to_phone'] = 'required|string';
        }
        $validated = $request->validate($rules, [
            'media_file.file' => 'فایل آپلود نشد. حجم (حداکثر ۵۰ مگ) یا تنظیمات سرور را بررسی کنید.',
            'media_file.max' => 'حجم فایل نباید بیشتر از ۵۰ مگابایت باشد.',
        ]);

        try {
            $mediaUrl = $validated['media_url'] ?? null;
            $fileType = 'text';
            if ($request->hasFile('media_file')) {
                $file = $request->file('media_file');
                $path = $file->store($channel === 'telegram' ? 'telegram-media' : 'whatsapp-media', 'public');
                $mediaUrl = $this->getPublicFileUrl($path, $request);
                $fileType = $this->getFileType($file);
            }

            $message = $validated['message'] ?? '';
            if (empty($message) && !$mediaUrl) {
                return redirect()->back()
                    ->with('error', 'Please provide either a message or a file.');
            }
            $messageToSend = $message ?: '';

            if ($channel === 'telegram') {
                $toChatId = $validated['to_chat_id'];
                $telegramService = app(\App\Services\TelegramService::class);
                $result = $telegramService->sendMessage($toChatId, $messageToSend, $mediaUrl);
                $customer = $this->findCustomerByTelegramChatId($toChatId);
                TelegramMessage::create([
                    'telegram_message_id' => $result['message_id'] ?? null,
                    'chat_id' => $toChatId,
                    'from_username' => null,
                    'message' => $messageToSend ?: null,
                    'message_type' => $mediaUrl ? $fileType : 'text',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $request->hasFile('media_file') ? $request->file('media_file')->getMimeType() : null,
                    'customer_id' => $customer?->id,
                    'direction' => 'outgoing',
                    'status' => $result['success'] ? 'sent' : 'failed',
                ]);
                if ($result['success']) {
                    return redirect()->route('inbox.index', ['channel' => 'telegram', 'chat_id' => $toChatId])
                        ->with('success', 'Message sent successfully.')->with('refresh', true);
                }
                return redirect()->route('inbox.index', ['channel' => 'telegram', 'chat_id' => $toChatId])
                    ->with('error', 'Message saved but failed to send: ' . ($result['error'] ?? 'Unknown error'));
            }
            if ($channel === 'instagram') {
                $toIgUserId = $validated['to_ig_user_id'];
                $instagramService = app(\App\Services\InstagramMessagingService::class);
                $result = $instagramService->sendMessage($toIgUserId, $messageToSend, $mediaUrl);
                $customer = $this->findCustomerByInstagramId($toIgUserId);
                $igConn = \App\Models\InstagramConnection::getActive();
                InstagramMessage::create([
                    'instagram_connection_id' => $igConn?->id,
                    'instagram_message_id' => $result['message_id'] ?? null,
                    'ig_user_id' => $toIgUserId,
                    'from_username' => null,
                    'message' => $messageToSend ?: null,
                    'message_type' => $mediaUrl ? $fileType : 'text',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $request->hasFile('media_file') ? $request->file('media_file')->getMimeType() : null,
                    'customer_id' => $customer?->id,
                    'direction' => 'outgoing',
                    'status' => $result['success'] ? 'sent' : 'failed',
                ]);
                if ($result['success']) {
                    return redirect()->route('inbox.index', ['channel' => 'instagram', 'ig_user_id' => $toIgUserId])
                        ->with('success', 'Message sent successfully.')->with('refresh', true);
                }
                return redirect()->route('inbox.index', ['channel' => 'instagram', 'ig_user_id' => $toIgUserId])
                    ->with('error', 'Message saved but failed to send: ' . ($result['error'] ?? 'Unknown error'));
            }

            $whatsappService = app(\App\Services\WhatsAppService::class);
            $result = $whatsappService->sendMessage($validated['to_phone'], $messageToSend, $mediaUrl);
            $customer = $this->findCustomerByPhone($validated['to_phone']);
            WhatsAppMessage::create([
                'message_id' => $result['message_id'] ?? null,
                'from_phone' => $validated['to_phone'],
                'to_phone' => $validated['to_phone'],
                'message' => $messageToSend ?: null,
                'message_type' => $mediaUrl ? $fileType : 'text',
                'media_url' => $mediaUrl,
                'media_mime_type' => $request->hasFile('media_file') ? $request->file('media_file')->getMimeType() : null,
                'customer_id' => $customer?->id,
                'direction' => 'outgoing',
                'status' => $result['success'] ? 'sent' : 'failed',
            ]);
            if ($result['success']) {
                return redirect()->route('inbox.index', ['phone' => $validated['to_phone']])
                    ->with('success', 'Message sent successfully.')->with('refresh', true);
            }
            if (!str_contains($result['error'] ?? '', 'timed out')) {
                Log::error('Failed to send WhatsApp message via API: ' . ($result['error'] ?? 'Unknown error'));
            }
            return redirect()->route('inbox.index', ['phone' => $validated['to_phone']])
                ->with('error', 'Message saved but failed to send via WhatsApp: ' . ($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error sending message: ' . $e->getMessage());
        }
    }

    /**
     * Create customer from phone (WhatsApp) or chat_id (Telegram).
     */
    public function createCustomer(Request $request)
    {
        $channel = $request->get('channel', 'whatsapp');
        $rules = ['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255'];
        if ($channel === 'telegram') {
            $rules['chat_id'] = 'required|string';
        } elseif ($channel === 'instagram') {
            $rules['ig_user_id'] = 'required|string';
        } else {
            $rules['phone'] = 'required|string';
        }
        $validated = $request->validate($rules);

        if ($channel === 'instagram') {
            $igUserId = (string) $validated['ig_user_id'];
            $customer = $this->findCustomerByInstagramId($igUserId);
            if ($customer) {
                return redirect()->back()->with('error', 'Customer already exists for this Instagram user.');
            }
            $customer = Customer::create([
                'name' => $validated['name'],
                'type' => 'person',
                'status' => 'lead',
                'source' => 'instagram',
                'created_by' => Auth::id(),
            ]);
            $customer->contacts()->create([
                'type' => 'instagram',
                'value' => $igUserId,
                'is_primary' => true,
            ]);
            if (!empty($validated['email'])) {
                $customer->contacts()->create([
                    'type' => 'email',
                    'value' => $validated['email'],
                    'is_primary' => false,
                ]);
            }
            InstagramMessage::where('ig_user_id', $igUserId)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
            return redirect()->back()->with('success', 'Customer created successfully.');
        }

        if ($channel === 'telegram') {
            $chatId = (string) $validated['chat_id'];
            $customer = $this->findCustomerByTelegramChatId($chatId);
            if ($customer) {
                return redirect()->back()->with('error', 'Customer already exists for this Telegram chat.');
            }
            $customer = Customer::create([
                'name' => $validated['name'],
                'type' => 'person',
                'status' => 'lead',
                'source' => 'telegram',
                'created_by' => Auth::id(),
            ]);
            $customer->contacts()->create([
                'type' => 'telegram',
                'value' => $chatId,
                'is_primary' => true,
            ]);
            if (!empty($validated['email'])) {
                $customer->contacts()->create([
                    'type' => 'email',
                    'value' => $validated['email'],
                    'is_primary' => false,
                ]);
            }
            TelegramMessage::where('chat_id', $chatId)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
            return redirect()->back()->with('success', 'Customer created successfully.');
        }

        $phone = $this->formatPhone($validated['phone']);
        $customer = $this->findCustomerByPhone($phone);
        if ($customer) {
            return redirect()->back()->with('error', 'Customer already exists with this phone number.');
        }
        $customer = Customer::create([
            'name' => $validated['name'],
            'type' => 'person',
            'status' => 'lead',
            'source' => 'whatsapp',
            'created_by' => Auth::id(),
        ]);
        $customer->contacts()->create([
            'type' => 'whatsapp',
            'value' => $phone,
            'is_primary' => true,
        ]);
        if (!empty($validated['email'])) {
            $customer->contacts()->create([
                'type' => 'email',
                'value' => $validated['email'],
                'is_primary' => false,
            ]);
        }
        WhatsAppMessage::where('from_phone', $phone)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
        return redirect()->back()->with('success', 'Customer created successfully.');
    }

    /**
     * Find customer by phone number (helper method)
     * Searches only in customer_contacts table
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $normalized = $this->formatPhone($phone);
        if ($normalized === '') {
            return null;
        }

        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        $valuesToTry = array_unique(array_filter([$normalized, $digitsOnly], fn ($v) => $v !== ''));

        if (empty($valuesToTry)) {
            return null;
        }

        // اول تطبیق دقیق
        $contact = \App\Models\CustomerContact::where(function ($q) {
            $q->where('type', 'phone')->orWhere('type', 'whatsapp');
        })
            ->whereIn('value', $valuesToTry)
            ->first();

        if ($contact) {
            return $contact->customer;
        }

        // اگر پیدا نشد: تطبیق فقط با رقم‌ها (فرمت ذخیره ممکن است متفاوت باشد: فاصله، +، ۰ اول و...)
        $contacts = \App\Models\CustomerContact::where(function ($q) {
            $q->where('type', 'phone')->orWhere('type', 'whatsapp');
        })->with('customer')->get();

        foreach ($contacts as $c) {
            $storedDigits = preg_replace('/[^0-9]/', '', (string) $c->value);
            if ($storedDigits !== '' && $storedDigits === $normalized) {
                return $c->customer;
            }
        }

        return null;
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

    /**
     * Determine file type based on MIME type or extension
     */
    protected function getFileType($file): string
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // Image types
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        // Video types
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // Audio types
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        // Document types
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
        ];

        if (in_array($mimeType, $documentMimes)) {
            return 'document';
        }

        // Default to document for unknown types
        return 'document';
    }

    /**
     * Get public URL for a file stored in public disk
     * Priority: 1. APP_URL from .env, 2. Request host (for ngrok), 3. Storage::url()
     */
    protected function getPublicFileUrl(string $path, Request $request): string
    {
        $appUrl = config('app.url');
        
        // If APP_URL is set and is not localhost, use it
        if (!empty($appUrl) && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/') . '/storage/' . $path;
        }
        
        // Otherwise, use request host (works with ngrok or if accessed via public URL)
        $baseUrl = $request->getSchemeAndHttpHost();
        
        // If request host is also localhost, log a warning
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            Log::warning('File URL is using localhost. API may not be able to access it. Please set APP_URL in .env or use ngrok.', [
                'path' => $path,
                'base_url' => $baseUrl,
                'app_url' => $appUrl,
            ]);
        }
        
        return $baseUrl . '/storage/' . $path;
    }
}
