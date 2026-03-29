<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\Industry;
use App\Models\InstagramMessage;
use App\Models\Setting;
use App\Models\TelegramGroup;
use App\Models\TelegramMessage;
use App\Models\TelegramUserConnection;
use App\Models\TikTokConnection;
use App\Models\TikTokMessage;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_customers' => Customer::count(),
            'customers_this_week' => Customer::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'total_campaigns' => Campaign::count(),
            'active_campaigns' => Campaign::whereIn('status', ['scheduled', 'running'])->count(),
            'total_industries' => Industry::count(),
            'total_templates' => CampaignTemplate::whereIn('type', ['whatsapp', 'telegram'])->count(),
            'telegram_groups' => TelegramGroup::active()->count(),
            'telegram_connected' => TelegramUserConnection::where('status', 'connected')->exists(),
            'instagram_connected' => \App\Models\InstagramConnection::whereNotNull('access_token_encrypted')->exists(),
            'tiktok_connected' => TikTokConnection::whereNotNull('access_token_encrypted')->exists(),
            'whatsapp_connected' => (function () {
                $r = Setting::getForOrganization('ronibot', []);
                if (! is_array($r)) {
                    return false;
                }

                return ! empty($r['enabled']) && ! empty($r['appkey']);
            })(),
        ];

        // Unread inbox messages by channel
        $inboxUnread = [
            'telegram' => TelegramMessage::where('direction', 'incoming')->whereNull('read_at')->count(),
            'whatsapp' => WhatsAppMessage::where('direction', 'incoming')->whereNull('read_at')->count(),
            'instagram' => InstagramMessage::where('direction', 'incoming')->whereNull('read_at')->count(),
            'tiktok' => TikTokMessage::where('direction', 'incoming')->whereNull('read_at')->count(),
        ];
        $stats['unread_inbox_total'] = $inboxUnread['telegram'] + $inboxUnread['whatsapp'] + $inboxUnread['instagram'] + $inboxUnread['tiktok'];

        // Customer distribution by status
        $customersByStatus = Customer::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Customer distribution by source (telegram, crawl, exhibition, direct, etc.)
        $customersBySource = Customer::selectRaw('source, count(*) as count')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(6)
            ->pluck('count', 'source')
            ->toArray();

        // Customer distribution by industry
        $customersByIndustry = Industry::withCount('customers')
            ->orderBy('customers_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($industry) => [
                'name' => $industry->name,
                'count' => $industry->customers_count,
            ]);

        // Recent incoming messages (last 8 from any channel)
        $recentInbox = collect();
        $mapInbox = function ($m, string $prefix, string $channel, $fallbackFrom) {
            $name = $m->customer?->name ?? $fallbackFrom;
            $avatar = $m->customer?->avatar ? asset('storage/' . $m->customer->avatar) : null;
            return [
                'id' => $prefix . $m->id,
                'channel' => $channel,
                'from' => $fallbackFrom,
                'name' => $name,
                'avatar' => $avatar,
                'message' => Str::limit($m->message ?? '', 50),
                'created_at' => $m->created_at,
                'unread' => !$m->read_at,
            ];
        };
        TelegramMessage::with('customer')
            ->where('direction', 'incoming')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->each(fn ($m) => $recentInbox->push($mapInbox($m, 'tg-', 'telegram', $m->from_username ?? $m->chat_id)));
        WhatsAppMessage::with('customer')
            ->where('direction', 'incoming')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->each(fn ($m) => $recentInbox->push($mapInbox($m, 'wa-', 'whatsapp', $m->from_phone ?? '')));
        InstagramMessage::with('customer')
            ->where('direction', 'incoming')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->each(fn ($m) => $recentInbox->push($mapInbox($m, 'ig-', 'instagram', $m->from_username ?? $m->ig_user_id ?? '')));
        $recentInbox = $recentInbox->sortByDesc('created_at')->take(8)->values();

        // Recent campaigns
        $recentCampaigns = Campaign::with(['creator'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'inboxUnread' => $inboxUnread,
            'customersByStatus' => $customersByStatus,
            'customersBySource' => $customersBySource,
            'customersByIndustry' => $customersByIndustry,
            'recentInbox' => $recentInbox,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }
}
