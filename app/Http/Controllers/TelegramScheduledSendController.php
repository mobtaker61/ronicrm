<?php

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use App\Models\TelegramGroupCategory;
use App\Models\TelegramScheduledSend;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TelegramScheduledSendController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $conn = TelegramUserConnection::getActive();
        $templates = CampaignTemplate::where('type', 'telegram')->orderBy('name')->get(['id', 'name']);
        $categories = TelegramGroupCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        $schedules = TelegramScheduledSend::with(['template:id,name', 'category:id,name'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'type_label' => $s->type === 'template' ? 'Template' : 'Forward',
                'template' => $s->template ? ['id' => $s->template->id, 'name' => $s->template->name] : null,
                'post_link' => $s->post_link,
                'category' => $s->category ? ['id' => $s->category->id, 'name' => $s->category->name] : null,
                'send_at_time' => $s->send_at_time ? (is_string($s->send_at_time) ? substr($s->send_at_time, 0, 5) : $s->send_at_time->format('H:i')) : null,
                'days_count' => $s->days_count,
                'runs_count' => $s->runs_count,
                'last_sent_at' => $s->last_sent_at?->toIso8601String(),
                'status' => $s->status,
                'created_at' => $s->created_at->toIso8601String(),
            ]);

        return Inertia::render('Telegram/ScheduledSends', [
            'telegramConnected' => $conn !== null,
            'templates' => $templates,
            'categories' => $categories,
            'schedules' => $schedules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['template', 'forward'])],
            'campaign_template_id' => ['required_if:type,template', 'nullable', 'exists:campaign_templates,id'],
            'post_link' => ['required_if:type,forward', 'nullable', 'string', 'max:500'],
            'telegram_group_category_id' => ['required', 'exists:telegram_group_categories,id'],
            'send_at_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'days_count' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        if ($validated['type'] === 'forward') {
            $parsed = MadelineProtoService::parseTelegramPostLink($validated['post_link'] ?? '');
            if (! $parsed) {
                return response()->json(['error' => 'Invalid Telegram post link.'], 422);
            }
        }

        if ($validated['type'] === 'template') {
            $tmpl = CampaignTemplate::find($validated['campaign_template_id']);
            if (! $tmpl || $tmpl->type !== 'telegram') {
                return response()->json(['error' => 'Invalid template.'], 422);
            }
        }

        $time = $validated['send_at_time'];
        if (preg_match('/^\d{1}:\d{2}$/', $time)) {
            $time = '0' . $time;
        }
        $sendAtTime = \Carbon\Carbon::parse('2000-01-01 ' . $time)->format('H:i:s');

        $schedule = TelegramScheduledSend::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'campaign_template_id' => $validated['type'] === 'template' ? $validated['campaign_template_id'] : null,
            'post_link' => $validated['type'] === 'forward' ? trim($validated['post_link']) : null,
            'telegram_group_category_id' => $validated['telegram_group_category_id'],
            'send_at_time' => $sendAtTime,
            'days_count' => $validated['days_count'],
            'status' => 'active',
        ]);

        $schedule->load(['template:id,name', 'category:id,name']);

        return response()->json([
            'success' => true,
            'schedule' => [
                'id' => $schedule->id,
                'type' => $schedule->type,
                'type_label' => $schedule->type === 'template' ? 'Template' : 'Forward',
                'template' => $schedule->template ? ['id' => $schedule->template->id, 'name' => $schedule->template->name] : null,
                'post_link' => $schedule->post_link,
                'category' => $schedule->category ? ['id' => $schedule->category->id, 'name' => $schedule->category->name] : null,
                'send_at_time' => $schedule->send_at_time ? (is_string($schedule->send_at_time) ? substr($schedule->send_at_time, 0, 5) : $schedule->send_at_time->format('H:i')) : null,
                'days_count' => $schedule->days_count,
                'runs_count' => $schedule->runs_count,
                'last_sent_at' => $schedule->last_sent_at?->toIso8601String(),
                'status' => $schedule->status,
                'created_at' => $schedule->created_at->toIso8601String(),
            ],
        ]);
    }

    public function stop(TelegramScheduledSend $schedule): JsonResponse
    {
        if ($schedule->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $schedule->stop();

        return response()->json(['success' => true, 'status' => 'stopped']);
    }
}
