<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelegramConnectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!\Illuminate\Support\Facades\Auth::check() || !\Illuminate\Support\Facades\Auth::user()->hasRole('admin')) {
                abort(403, 'Unauthorized action.');
            }
            return $next($request);
        });
    }

    /**
     * Get QR code for Telegram login (JSON for polling).
     */
    public function qrCode(Request $request): JsonResponse
    {
        try {
            $service = app(MadelineProtoService::class);
            $wait = $request->boolean('wait');
            $result = $service->getQrCode($wait);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect Telegram user account.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $conn = TelegramUserConnection::getActive();
        if ($conn) {
            $conn->update(['status' => 'expired']);
            $conn->delete();
        }
        return redirect()->route('settings.index', ['tab' => 'telegram'], 303)->with('success', 'Telegram account disconnected.');
    }

    /**
     * Reset session files (fix "Could not read the lightstate file").
     * Deletes session files from disk and sets status to pending for fresh QR login.
     */
    public function resetSession(Request $request): RedirectResponse
    {
        $conn = TelegramUserConnection::getActive()
            ?? TelegramUserConnection::whereIn('status', ['pending', 'connected'])->orderByDesc('updated_at')->first();
        if (!$conn) {
            return redirect()->route('settings.index', ['tab' => 'telegram'], 303)->with('error', 'No Telegram connection found.');
        }
        $conn->resetSessionFiles();
        return redirect()->route('settings.index', ['tab' => 'telegram'], 303)->with('success', 'Telegram session reset. Click "Connect via QR Code" to re-connect.');
    }
}
