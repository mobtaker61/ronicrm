<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
     * ?wait=1 = long-poll for scan. ?conn_id=123 = use specific connection (for poll).
     */
    public function qrCode(Request $request): JsonResponse
    {
        try {
            $connId = $request->integer('conn_id', 0) ?: null;
            $service = app(MadelineProtoService::class);
            $wait = $request->boolean('wait');
            $result = $service->getQrCode($wait, $connId);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete Telegram 2FA login after QR scan.
     */
    public function complete2fa(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'password' => ['required', 'string'],
                'conn_id' => ['nullable', 'integer'],
            ]);
            $service = app(MadelineProtoService::class);
            $result = $service->complete2faLogin(
                (string) ($validated['password'] ?? ''),
                isset($validated['conn_id']) ? (int) $validated['conn_id'] : null
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'logged_in' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect Telegram user account.
     * Removes ALL connected records and their session folders.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $connected = TelegramUserConnection::where('status', 'connected')->get();
        foreach ($connected as $conn) {
            $this->deleteConnectionSession($conn);
            $conn->update(['status' => 'expired']);
            $conn->delete();
        }
        $userId = \Illuminate\Support\Facades\Auth::id() ?? 0;
        Cache::forget(MadelineProtoService::CACHE_KEY_QR_CONN . '_' . $userId);
        return redirect()->route('settings.index', ['tab' => 'telegram'], 303)->with('success', 'Telegram account disconnected.');
    }

    protected function deleteConnectionSession(TelegramUserConnection $conn): void
    {
        if (!$conn->session_path) {
            return;
        }
        $path = storage_path('app/' . $conn->session_path);
        try {
            if (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        } catch (\Throwable) {
            // Ignore
        }
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
