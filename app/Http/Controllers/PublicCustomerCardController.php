<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCustomerCardController extends Controller
{
    public function show(string $shareKey): Response
    {
        $customer = Customer::where('share_key', $shareKey)
            ->with(['industry', 'contacts', 'socialMedia.socialMediaType'])
            ->firstOrFail();

        $shareUrl = url()->route('public.customer.card', $shareKey);

        return Inertia::render('Public/CustomerCard', [
            'customer' => $customer,
            'shareUrl' => $shareUrl,
        ]);
    }

    public function shareViaWhatsApp(Request $request, string $shareKey)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $customer = Customer::where('share_key', $shareKey)->firstOrFail();

        $shareUrl = route('public.customer.card', $shareKey);
        $message = "Check out {$customer->name}'s contact card:\n{$shareUrl}";

        $whatsappService = app(\App\Services\WhatsAppService::class);
        $result = $whatsappService->sendMessage(
            $validated['phone'],
            $message
        );

        if ($result['success']) {
            return redirect()->back()->with('success', 'Card shared successfully via WhatsApp!');
        }

        return redirect()->back()->withErrors([
            'phone' => $result['error'] ?? 'Failed to send WhatsApp message.',
        ]);
    }
}
