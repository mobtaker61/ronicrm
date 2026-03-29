<?php

namespace App\Http\Controllers;

use App\Jobs\MarkInboxConversationReadJob;
use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSocialMedia;
use App\Models\InstagramMessage;
use App\Models\SocialMediaType;
use App\Models\TelegramMessage;
use App\Models\TikTokMessage;
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
        $channel = $request->get('channel', 'all');
        if (! in_array($channel, ['all', 'whatsapp', 'telegram', 'instagram', 'tiktok'], true)) {
            $channel = 'all';
        }

        $messageChannel = null;
        $selectedContact = null;
        $instagramCustomerId = $request->get('customer_id');
        $tiktokCustomerId = $request->get('customer_id');

        if ($channel === 'all') {
            if ($request->filled('tiktok_open_id')) {
                $selectedContact = $request->get('tiktok_open_id');
                $messageChannel = 'tiktok';
            } elseif ($request->filled('ig_user_id')) {
                $selectedContact = $request->get('ig_user_id');
                $messageChannel = 'instagram';
            } elseif ($request->filled('chat_id')) {
                $selectedContact = $request->get('chat_id');
                $messageChannel = 'telegram';
            } elseif ($request->filled('phone')) {
                $selectedContact = $request->get('phone');
                $messageChannel = 'whatsapp';
            } elseif ($request->filled('customer_id')) {
                $cust = Customer::find($request->get('customer_id'));
                if ($cust) {
                    $igContact = $cust->contacts()->where('type', 'instagram')->first();
                    if ($igContact) {
                        $selectedContact = $igContact->value;
                        $messageChannel = 'instagram';
                    } else {
                        $ttContact = $cust->contacts()->where('type', 'tiktok')->first();
                        if ($ttContact) {
                            $selectedContact = $ttContact->value;
                            $messageChannel = 'tiktok';
                        }
                    }
                }
            }
        } elseif ($channel === 'telegram') {
            $messageChannel = 'telegram';
            $selectedContact = $request->get('chat_id', $request->get('phone'));
        } elseif ($channel === 'instagram') {
            $messageChannel = 'instagram';
            $selectedContact = $request->get('ig_user_id', $request->get('phone'));
            if ($instagramCustomerId && ! $selectedContact) {
                $cust = Customer::find($instagramCustomerId);
                if ($cust) {
                    $igContact = $cust->contacts()->where('type', 'instagram')->first();
                    if ($igContact) {
                        $selectedContact = $igContact->value;
                    }
                }
            }
        } elseif ($channel === 'tiktok') {
            $messageChannel = 'tiktok';
            $selectedContact = $request->get('tiktok_open_id', $request->get('phone'));
            if ($tiktokCustomerId && ! $selectedContact) {
                $cust = Customer::find($tiktokCustomerId);
                if ($cust) {
                    $ttContact = $cust->contacts()->where('type', 'tiktok')->first();
                    if ($ttContact) {
                        $selectedContact = $ttContact->value;
                    }
                }
            }
        } else {
            $messageChannel = 'whatsapp';
            $selectedContact = $request->get('phone');
        }

        $searchPhone = trim((string) $request->get('search_phone', ''));

        $searchResults = [];
        if (strlen($searchPhone) >= 2) {
            $searchTerm = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchPhone);
            $phoneDigits = preg_replace('/[^0-9]/', '', $searchPhone);

            if ($channel === 'all') {
                $searchResults = $this->buildUnifiedInboxSearchResults($searchTerm, $phoneDigits);
            } elseif ($channel === 'telegram') {
                $telegramTypeId = SocialMediaType::where('name', 'Telegram')->value('id');
                $query = Customer::query()
                    ->where(function ($q) use ($searchTerm, $telegramTypeId) {
                        $q->where('name', 'like', '%'.$searchTerm.'%')
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('type', 'telegram')->where('value', 'like', '%'.$searchTerm.'%');
                            });
                        if ($telegramTypeId) {
                            $q->orWhereHas('socialMedia', function ($sq) use ($searchTerm, $telegramTypeId) {
                                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
                                $sq->where('social_media_type_id', $telegramTypeId)
                                    ->where('handle', 'like', '%'.$escaped.'%');
                            });
                        }
                    });
                $searchResults = $query->limit(15)->get()->map(function ($customer) {
                    $tg = $customer->contacts()->where('type', 'telegram')->first();
                    $tgHandle = $customer->socialMedia()->whereHas('socialMediaType', fn ($q) => $q->where('name', 'Telegram'))->first();

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => null,
                        'chat_id' => $tg?->value ?? ($tgHandle ? ($tgHandle->handle[0] === '@' ? $tgHandle->handle : '@'.$tgHandle->handle) : null),
                        'ig_user_id' => null,
                        'tiktok_open_id' => null,
                        'avatar' => $customer->avatar ? asset('storage/'.$customer->avatar) : null,
                    ];
                })->filter(fn ($r) => ! empty($r['chat_id']))->values()->all();
            } elseif ($channel === 'instagram') {
                $instagramTypeId = SocialMediaType::where('name', 'Instagram')->value('id');
                $byContact = Customer::query()
                    ->whereHas('contacts', function ($cq) {
                        $cq->where('type', 'instagram');
                    })
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%'.$searchTerm.'%')
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('type', 'instagram')->where('value', 'like', '%'.$searchTerm.'%');
                            });
                    })
                    ->limit(20)
                    ->get();
                $byHandle = collect();
                if ($instagramTypeId) {
                    $byHandle = Customer::query()
                        ->whereHas('socialMedia', function ($sq) use ($instagramTypeId, $searchTerm) {
                            $sq->where('social_media_type_id', $instagramTypeId)
                                ->where('handle', 'like', '%'.$searchTerm.'%');
                        })
                        ->limit(20)
                        ->get();
                }
                $merged = $byContact->merge($byHandle)->unique('id');
                $searchResults = $merged->take(15)->map(function ($customer) {
                    $ig = $customer->contacts()->where('type', 'instagram')->first();

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => null,
                        'chat_id' => null,
                        'ig_user_id' => $ig?->value,
                        'tiktok_open_id' => null,
                        'avatar' => $customer->avatar ? asset('storage/'.$customer->avatar) : null,
                    ];
                })->values()->all();
            } elseif ($channel === 'tiktok') {
                $tiktokTypeId = SocialMediaType::where('name', 'TikTok')->value('id');
                $byContact = Customer::query()
                    ->whereHas('contacts', function ($cq) {
                        $cq->where('type', 'tiktok');
                    })
                    ->where(function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%'.$searchTerm.'%')
                            ->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                                $cq->where('type', 'tiktok')->where('value', 'like', '%'.$searchTerm.'%');
                            });
                    })
                    ->limit(20)
                    ->get();
                $byHandle = collect();
                if ($tiktokTypeId) {
                    $byHandle = Customer::query()
                        ->whereHas('socialMedia', function ($sq) use ($tiktokTypeId, $searchTerm) {
                            $sq->where('social_media_type_id', $tiktokTypeId)
                                ->where('handle', 'like', '%'.$searchTerm.'%');
                        })
                        ->limit(20)
                        ->get();
                }
                $merged = $byContact->merge($byHandle)->unique('id');
                $searchResults = $merged->take(15)->map(function ($customer) {
                    $tt = $customer->contacts()->where('type', 'tiktok')->first();

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => null,
                        'chat_id' => null,
                        'ig_user_id' => null,
                        'tiktok_open_id' => $tt?->value,
                        'avatar' => $customer->avatar ? asset('storage/'.$customer->avatar) : null,
                    ];
                })->values()->all();
            } else {
                $query = Customer::query()
                    ->where(function ($q) use ($searchTerm, $phoneDigits) {
                        $q->where('name', 'like', '%'.$searchTerm.'%');
                        if (strlen($phoneDigits) >= 2) {
                            $q->orWhereHas('contacts', function ($cq) use ($phoneDigits) {
                                $cq->where(function ($cq) {
                                    $cq->where('type', 'phone')->orWhere('type', 'whatsapp');
                                })->where('value', 'like', '%'.$phoneDigits.'%');
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
                        'tiktok_open_id' => null,
                        'avatar' => $customer->avatar ? asset('storage/'.$customer->avatar) : null,
                    ];
                })->filter(fn ($r) => ! empty($r['phone']))->values()->all();
            }
        }

        if ($channel === 'all') {
            $conversations = $this->buildAllConversations();
        } elseif ($channel === 'telegram') {
            $conversations = $this->buildTelegramConversations();
        } elseif ($channel === 'instagram') {
            $conversations = $this->buildInstagramConversations();
        } elseif ($channel === 'tiktok') {
            $conversations = $this->buildTikTokConversations();
        } else {
            $conversations = $this->buildWhatsAppConversations();
        }

        $messages = [];
        $selectedCustomer = null;

        if ($messageChannel === 'telegram' && $selectedContact) {
            $telegramRows = TelegramMessage::forChat($selectedContact)
                ->orderBy('created_at', 'asc')
                ->get();

            $messages = $telegramRows
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

            $unreadIds = $telegramRows
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->pluck('id')
                ->values()
                ->all();
            $this->scheduleMarkConversationReadAfterResponse('telegram', $selectedContact, $unreadIds);
            $selectedCustomer = $this->findCustomerByTelegramChatId($selectedContact);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        } elseif ($messageChannel === 'instagram' && $selectedContact) {
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
            $this->scheduleMarkConversationReadAfterResponse('instagram', $selectedContact);
            $selectedCustomer = $this->findCustomerByInstagramId($selectedContact);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        } elseif ($messageChannel === 'tiktok' && $selectedContact) {
            $messages = TikTokMessage::forOpenId($selectedContact)
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
            $this->scheduleMarkConversationReadAfterResponse('tiktok', $selectedContact);
            $selectedCustomer = $this->findCustomerByTiktokOpenId($selectedContact);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        } elseif ($messageChannel === 'whatsapp' && $selectedContact) {
            $messages = WhatsAppMessage::where(function ($q) use ($selectedContact) {
                $q->where('from_phone', $selectedContact)->orWhere('to_phone', $selectedContact);
            })
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($msg) {
                    $mediaUrl = $msg->media_url;
                    if ($mediaUrl && ! str_starts_with($mediaUrl, 'http')) {
                        $mediaUrl = asset('storage/'.ltrim($mediaUrl, '/'));
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
            $this->scheduleMarkConversationReadAfterResponse('whatsapp', $selectedContact);
            $selectedCustomer = $this->findCustomerByPhone($selectedContact);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        }

        if (! $selectedCustomer && ! empty($instagramCustomerId) && $messageChannel === 'instagram') {
            $selectedCustomer = Customer::find($instagramCustomerId);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        }
        if (! $selectedCustomer && ! empty($tiktokCustomerId) && $messageChannel === 'tiktok') {
            $selectedCustomer = Customer::find($tiktokCustomerId);
            if ($selectedCustomer) {
                $selectedCustomer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);
                if ($selectedCustomer->avatar) {
                    $selectedCustomer->avatar = asset('storage/'.$selectedCustomer->avatar);
                }
            }
        }

        $templates = CampaignTemplate::whereIn('type', ['whatsapp', 'telegram'])
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'content' => $t->content,
                'image' => $t->image ? asset('storage/'.$t->image) : null,
                'whatsapp_settings' => $t->whatsapp_settings,
            ]);

        return Inertia::render('Inbox/Index', [
            'channel' => $channel,
            'messageChannel' => $messageChannel,
            'conversations' => $conversations,
            'messages' => $messages,
            'selectedPhone' => $request->filled('phone') ? $request->get('phone') : null,
            'selectedChatId' => $request->filled('chat_id') ? $request->get('chat_id') : null,
            'selectedIgUserId' => $request->filled('ig_user_id') ? $request->get('ig_user_id') : null,
            'selectedTikTokOpenId' => $request->filled('tiktok_open_id') ? $request->get('tiktok_open_id') : null,
            'searchResults' => $searchResults,
            'selectedCustomer' => $selectedCustomer ?? null,
            'templates' => $templates,
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
                'ig_user_id' => null,
                'tiktok_open_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
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
                'ig_user_id' => null,
                'tiktok_open_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
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
                'tiktok_open_id' => null,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }

        return $conversations->sortByDesc('last_message_at')->values();
    }

    protected function buildTikTokConversations(): \Illuminate\Support\Collection
    {
        $incomingIds = TikTokMessage::incoming()->select('tiktok_open_id')->distinct()->pluck('tiktok_open_id');
        $outgoingIds = TikTokMessage::where('direction', 'outgoing')->select('tiktok_open_id')->distinct()->pluck('tiktok_open_id');
        $allIds = $incomingIds->merge($outgoingIds)->unique()->filter();

        $conversations = collect();
        foreach ($allIds as $openId) {
            $customer = $this->findCustomerByTiktokOpenId($openId);
            $lastMessage = TikTokMessage::forOpenId($openId)->latest()->first();
            $unreadCount = TikTokMessage::forOpenId($openId)->where('direction', 'incoming')->whereNull('read_at')->count();
            $messageCount = TikTokMessage::forOpenId($openId)->count();
            $displayName = $customer ? $customer->name : ($lastMessage ? ($lastMessage->from_display_name ?? $openId) : $openId);
            $conversations->push([
                'phone' => null,
                'chat_id' => null,
                'ig_user_id' => null,
                'tiktok_open_id' => $openId,
                'name' => $displayName,
                'customer_id' => $customer?->id,
                'avatar' => $customer?->avatar ? asset('storage/'.$customer->avatar) : null,
                'last_message' => $lastMessage?->message,
                'last_message_at' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
                'message_count' => $messageCount,
            ]);
        }

        return $conversations->sortByDesc('last_message_at')->values();
    }

    protected function buildAllConversations(): \Illuminate\Support\Collection
    {
        return $this->buildWhatsAppConversations()
            ->map(fn ($c) => array_merge($c, ['channel' => 'whatsapp']))
            ->concat($this->buildTelegramConversations()->map(fn ($c) => array_merge($c, ['channel' => 'telegram'])))
            ->concat($this->buildInstagramConversations()->map(fn ($c) => array_merge($c, ['channel' => 'instagram'])))
            ->concat($this->buildTikTokConversations()->map(fn ($c) => array_merge($c, ['channel' => 'tiktok'])))
            ->sortByDesc(fn ($c) => $c['last_message_at'] ?? null)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildUnifiedInboxSearchResults(string $searchTerm, string $phoneDigits): array
    {
        $telegramTypeId = SocialMediaType::where('name', 'Telegram')->value('id');
        $instagramTypeId = SocialMediaType::where('name', 'Instagram')->value('id');
        $tiktokTypeId = SocialMediaType::where('name', 'TikTok')->value('id');

        $query = Customer::query()
            ->where(function ($q) use ($searchTerm, $phoneDigits, $telegramTypeId, $instagramTypeId, $tiktokTypeId) {
                $q->where('name', 'like', '%'.$searchTerm.'%');
                $q->orWhereHas('contacts', function ($cq) use ($searchTerm) {
                    $cq->where('value', 'like', '%'.$searchTerm.'%');
                });
                if (strlen($phoneDigits) >= 2) {
                    $q->orWhereHas('contacts', function ($cq) use ($phoneDigits) {
                        $cq->where(function ($cq) {
                            $cq->where('type', 'phone')->orWhere('type', 'whatsapp');
                        })->where('value', 'like', '%'.$phoneDigits.'%');
                    });
                }
                if ($telegramTypeId) {
                    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
                    $q->orWhereHas('socialMedia', function ($sq) use ($escaped, $telegramTypeId) {
                        $sq->where('social_media_type_id', $telegramTypeId)
                            ->where('handle', 'like', '%'.$escaped.'%');
                    });
                }
                if ($instagramTypeId) {
                    $q->orWhereHas('socialMedia', function ($sq) use ($searchTerm, $instagramTypeId) {
                        $sq->where('social_media_type_id', $instagramTypeId)
                            ->where('handle', 'like', '%'.$searchTerm.'%');
                    });
                }
                if ($tiktokTypeId) {
                    $q->orWhereHas('socialMedia', function ($sq) use ($searchTerm, $tiktokTypeId) {
                        $sq->where('social_media_type_id', $tiktokTypeId)
                            ->where('handle', 'like', '%'.$searchTerm.'%');
                    });
                }
            });

        return $query->limit(15)->get()->map(function ($customer) use ($telegramTypeId) {
            $phoneContact = $customer->contacts()->where(function ($q) {
                $q->where('type', 'phone')->orWhere('type', 'whatsapp');
            })->first();
            $tg = $customer->contacts()->where('type', 'telegram')->first();
            $ig = $customer->contacts()->where('type', 'instagram')->first();
            $tt = $customer->contacts()->where('type', 'tiktok')->first();
            $tgHandle = $telegramTypeId
                ? $customer->socialMedia()->where('social_media_type_id', $telegramTypeId)->first()
                : null;

            $chatId = $tg?->value ?? ($tgHandle ? (($tgHandle->handle[0] ?? '') === '@' ? $tgHandle->handle : '@'.$tgHandle->handle) : null);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $phoneContact?->value,
                'chat_id' => $chatId,
                'ig_user_id' => $ig?->value,
                'tiktok_open_id' => $tt?->value,
                'avatar' => $customer->avatar ? asset('storage/'.$customer->avatar) : null,
            ];
        })->filter(function ($r) {
            return ! empty($r['phone']) || ! empty($r['chat_id']) || ! empty($r['ig_user_id']) || ! empty($r['tiktok_open_id']);
        })->values()->all();
    }

    protected function findCustomerByTelegramChatId(string $chatId): ?Customer
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $chatId)->first();

        return $contact?->customer;
    }

    protected function findCustomerByTelegramHandle(string $handle): ?Customer
    {
        $normalized = strtolower(ltrim($handle, '@'));
        if ($normalized === '') {
            return null;
        }
        $telegramTypeId = \App\Models\SocialMediaType::where('name', 'Telegram')->value('id');
        if (! $telegramTypeId) {
            return null;
        }
        $sm = CustomerSocialMedia::where('social_media_type_id', $telegramTypeId)
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(TRIM(LEADING ? FROM handle)) = ?', ['@', $normalized]);
            })
            ->first();

        return $sm?->customer;
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

    protected function findCustomerByTiktokOpenId(string $openId): ?Customer
    {
        $contact = CustomerContact::where('type', 'tiktok')->where('value', $openId)->first();
        if ($contact) {
            return $contact->customer;
        }
        $msg = TikTokMessage::where('tiktok_open_id', $openId)->whereNotNull('customer_id')->first();

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
        } elseif ($channel === 'tiktok') {
            $rules['to_tiktok_open_id'] = 'required|string';
        } else {
            $rules['to_phone'] = 'required|string';
        }
        $validated = $request->validate($rules, [
            'media_file.file' => 'فایل آپلود نشد. حجم (حداکثر ۵۰ مگ) یا تنظیمات سرور را بررسی کنید.',
            'media_file.max' => 'حجم فایل نباید بیشتر از ۵۰ مگابایت باشد.',
        ]);

        try {
            $mediaUrl = $validated['media_url'] ?? null;
            $storedPath = null;
            $fileType = 'text';
            if ($request->hasFile('media_file')) {
                $file = $request->file('media_file');
                $diskFolder = match ($channel) {
                    'telegram' => 'telegram-media',
                    'tiktok' => 'tiktok-media',
                    default => 'whatsapp-media',
                };
                $storedPath = $file->store($diskFolder, 'public');
                $mediaUrl = $this->getPublicFileUrl($storedPath, $request);
                $fileType = $this->getFileType($file);
            }

            $message = $validated['message'] ?? '';
            if (empty($message) && ! $mediaUrl) {
                return redirect()->back()
                    ->with('error', 'Please provide either a message or a file.');
            }
            $messageToSend = $message ?: '';

            if ($channel === 'telegram') {
                $toChatId = trim((string) $validated['to_chat_id']);
                $conn = \App\Models\TelegramUserConnection::getActive();
                $mediaPath = $storedPath ? storage_path('app/public/'.$storedPath) : $mediaUrl;

                if ($conn && $conn->isConnected()) {
                    $madelineService = new \App\Services\MadelineProtoService($conn);
                    $result = $madelineService->sendPrivateMessage($toChatId, $messageToSend, $mediaPath);
                    $storeChatId = $result['resolved_chat_id'] ?? $toChatId;
                } else {
                    $allowBotFallback = $request->boolean('force_bot', false);
                    if (! $allowBotFallback) {
                        return redirect()->route('inbox.index', ['channel' => 'telegram', 'chat_id' => $toChatId])
                            ->with('error', 'Telegram User Account is not connected. Message was not sent via bot fallback.');
                    }
                    $telegramService = app(\App\Services\TelegramService::class);
                    $result = $telegramService->sendMessage($toChatId, $messageToSend, $mediaUrl);
                    $storeChatId = $toChatId;
                }

                $customer = $this->findCustomerByTelegramChatId($storeChatId)
                    ?? $this->findCustomerByTelegramHandle($toChatId);
                TelegramMessage::create([
                    'telegram_message_id' => $result['message_id'] ?? null,
                    'chat_id' => $storeChatId,
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
                    return redirect()->route('inbox.index', ['channel' => 'telegram', 'chat_id' => $storeChatId])
                        ->with('success', 'Message sent successfully.')->with('refresh', true);
                }

                return redirect()->route('inbox.index', ['channel' => 'telegram', 'chat_id' => $storeChatId])
                    ->with('error', 'Message saved but failed to send: '.($result['error'] ?? 'Unknown error'));
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
                    ->with('error', 'Message saved but failed to send: '.($result['error'] ?? 'Unknown error'));
            }
            if ($channel === 'tiktok') {
                $toOpenId = $validated['to_tiktok_open_id'];
                $tiktokService = app(\App\Services\TikTokMessagingService::class);
                $result = $tiktokService->sendMessage($toOpenId, $messageToSend, $mediaUrl);
                $customer = $this->findCustomerByTiktokOpenId($toOpenId);
                $ttConn = \App\Models\TikTokConnection::getActive();
                TikTokMessage::create([
                    'tiktok_connection_id' => $ttConn?->id,
                    'tiktok_message_id' => $result['message_id'] ?? null,
                    'tiktok_open_id' => $toOpenId,
                    'from_display_name' => null,
                    'message' => $messageToSend ?: null,
                    'message_type' => $mediaUrl ? $fileType : 'text',
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $request->hasFile('media_file') ? $request->file('media_file')->getMimeType() : null,
                    'customer_id' => $customer?->id,
                    'direction' => 'outgoing',
                    'status' => $result['success'] ? 'sent' : 'failed',
                ]);
                if ($result['success']) {
                    return redirect()->route('inbox.index', ['channel' => 'tiktok', 'tiktok_open_id' => $toOpenId])
                        ->with('success', 'Message sent successfully.')->with('refresh', true);
                }

                return redirect()->route('inbox.index', ['channel' => 'tiktok', 'tiktok_open_id' => $toOpenId])
                    ->with('error', 'Message saved but failed to send: '.($result['error'] ?? 'Unknown error'));
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
            if (! str_contains($result['error'] ?? '', 'timed out')) {
                Log::error('Failed to send WhatsApp message via API: '.($result['error'] ?? 'Unknown error'));
            }

            return redirect()->route('inbox.index', ['phone' => $validated['to_phone']])
                ->with('error', 'Message saved but failed to send via WhatsApp: '.($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error sending message: '.$e->getMessage());
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
        } elseif ($channel === 'tiktok') {
            $rules['tiktok_open_id'] = 'required|string';
        } else {
            $rules['phone'] = 'required|string';
        }
        $validated = $request->validate($rules);

        if ($channel === 'tiktok') {
            $openId = (string) $validated['tiktok_open_id'];
            $customer = $this->findCustomerByTiktokOpenId($openId);
            if ($customer) {
                return redirect()->back()->with('error', 'Customer already exists for this TikTok user.');
            }
            $customer = Customer::create([
                'name' => $validated['name'],
                'type' => 'person',
                'status' => 'lead',
                'source' => 'tiktok',
                'created_by' => Auth::id(),
            ]);
            $customer->contacts()->create([
                'type' => 'tiktok',
                'value' => $openId,
                'is_primary' => true,
            ]);
            if (! empty($validated['email'])) {
                $customer->contacts()->create([
                    'type' => 'email',
                    'value' => $validated['email'],
                    'is_primary' => false,
                ]);
            }
            TikTokMessage::where('tiktok_open_id', $openId)->whereNull('customer_id')->update(['customer_id' => $customer->id]);

            return redirect()->back()->with('success', 'Customer created successfully.');
        }

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
            if (! empty($validated['email'])) {
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
            if (! empty($validated['email'])) {
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
        if (! empty($validated['email'])) {
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
     * Assign Instagram or TikTok conversation to an existing customer.
     */
    public function assignToCustomer(Request $request)
    {
        $validated = $request->validate([
            'channel' => 'required|in:instagram,tiktok',
            'ig_user_id' => 'nullable|required_if:channel,instagram|string',
            'tiktok_open_id' => 'nullable|required_if:channel,tiktok|string',
            'customer_id' => 'required|integer|exists:customers,id',
        ]);
        $customer = Customer::findOrFail($validated['customer_id']);

        if ($validated['channel'] === 'instagram') {
            $igUserId = (string) $validated['ig_user_id'];
            $existing = CustomerContact::where('type', 'instagram')->where('value', $igUserId)->first();
            if ($existing) {
                if ($existing->customer_id === $customer->id) {
                    return redirect()->back()->with('info', 'Already assigned to this customer.');
                }

                return redirect()->back()->with('error', 'This Instagram user is already linked to another customer.');
            }
            $customer->contacts()->create([
                'type' => 'instagram',
                'value' => $igUserId,
                'is_primary' => false,
            ]);
            InstagramMessage::where('ig_user_id', $igUserId)->whereNull('customer_id')->update(['customer_id' => $customer->id]);

            return redirect()->back()->with('success', 'Conversation assigned to customer.');
        }

        $openId = (string) $validated['tiktok_open_id'];
        $existing = CustomerContact::where('type', 'tiktok')->where('value', $openId)->first();
        if ($existing) {
            if ($existing->customer_id === $customer->id) {
                return redirect()->back()->with('info', 'Already assigned to this customer.');
            }

            return redirect()->back()->with('error', 'This TikTok user is already linked to another customer.');
        }
        $customer->contacts()->create([
            'type' => 'tiktok',
            'value' => $openId,
            'is_primary' => false,
        ]);
        TikTokMessage::where('tiktok_open_id', $openId)->whereNull('customer_id')->update(['customer_id' => $customer->id]);

        return redirect()->back()->with('success', 'Conversation assigned to customer.');
    }

    /**
     * JSON: list customers for assign modal (search by name).
     */
    public function customersForAssign(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $customers = Customer::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%'))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name']);

        return response()->json($customers);
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
        if (! empty($appUrl) && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
            return rtrim($appUrl, '/').'/storage/'.$path;
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

        return $baseUrl.'/storage/'.$path;
    }

    /**
     * علامت‌گذاری پیام‌ها به‌عنوان خوانده‌شده را بعد از ارسال پاسخ HTTP انجام می‌دهد تا با INSERT/UPDATE همزمان
     * (مثلاً daemon تلگرام + باز کردن اینباکس) قفل InnoDB (1205) کل درخواست را نشکند.
     */
    protected function scheduleMarkConversationReadAfterResponse(string $channel, ?string $contactKey, array $specificIds = []): void
    {
        if ($contactKey === null || $contactKey === '') {
            return;
        }
        // Prefer immediate update so unread state changes reliably.
        // If DB is locked, retry a few times; then fallback to queued job.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                if ($channel === 'telegram') {
                    $q = TelegramMessage::forChat($contactKey)
                        ->where('direction', 'incoming')
                        ->whereNull('read_at');
                    if ($specificIds !== []) {
                        $q->whereIn('id', $specificIds);
                    }
                    $q->update(['read_at' => now(), 'status' => 'read']);
                } elseif ($channel === 'instagram') {
                    InstagramMessage::forIgUser($contactKey)
                        ->whereNull('read_at')
                        ->update(['read_at' => now(), 'status' => 'read']);
                } elseif ($channel === 'tiktok') {
                    TikTokMessage::forOpenId($contactKey)
                        ->whereNull('read_at')
                        ->update(['read_at' => now(), 'status' => 'read']);
                } else {
                    WhatsAppMessage::where('from_phone', $contactKey)
                        ->whereNull('read_at')
                        ->update(['read_at' => now(), 'status' => 'read']);
                }

                return;
            } catch (\Throwable $e) {
                if (! str_contains($e->getMessage(), '1205') || $attempt === 2) {
                    Log::warning('Inbox mark-as-read immediate failed; queue fallback', [
                        'channel' => $channel,
                        'contact' => $contactKey,
                        'error' => $e->getMessage(),
                    ]);
                    dispatch(new MarkInboxConversationReadJob($channel, $contactKey, $specificIds));
                    return;
                }
                usleep(200000);
            }
        }
    }
}
