<?php

namespace App\Http\Controllers;

use App\Models\TelegramGroup;
use App\Services\WhatsAppGroupsSyncService;
use App\Support\WhatsAppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppGroupsController extends Controller
{
    public function sync(WhatsAppGroupsSyncService $sync): JsonResponse
    {
        if (! Auth::user()?->canManageOrganizationSettings()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (! WhatsAppSettings::isReady()) {
            return response()->json(['error' => 'WhatsApp is not connected.'], 403);
        }

        try {
            $count = $sync->syncFromApi();

            return response()->json(['success' => true, 'synced' => $count]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function leave(Request $request, TelegramGroup $group, WhatsAppGroupsSyncService $sync): JsonResponse
    {
        if (! Auth::user()?->canManageOrganizationSettings()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (($group->channel ?? '') !== 'whatsapp') {
            return response()->json(['error' => 'Not a WhatsApp group.'], 422);
        }

        try {
            $sync->leaveGroup($group);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
