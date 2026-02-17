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
            $result = $service->getQrCode();
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
        return redirect()->route('settings.index')->with('success', 'Telegram account disconnected.');
    }
}
