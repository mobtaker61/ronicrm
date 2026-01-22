<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Industry;
use App\Models\SocialMediaType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::with(['industry', 'creator', 'contacts']);

        // Filters
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('contacts', function ($q) use ($search) {
                        $q->where('value', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('industry_id') && $request->industry_id) {
            $query->where('industry_id', $request->industry_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(25);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'industries' => Industry::orderBy('name')->get(),
            'filters' => $request->only(['search', 'type', 'industry_id', 'status', 'source']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create', [
            'industries' => Industry::orderBy('name')->get(),
            'socialMediaTypes' => SocialMediaType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'gender' => 'nullable|in:male,female,other',
            'language' => 'nullable|string|max:10',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'industry_id' => 'nullable|exists:industries,id',
            'status' => 'required|in:lead,prospect,customer,inactive',
            'source' => 'required|in:website,referral,advertisement,social_media,other',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.type' => 'required|in:phone,email,whatsapp,telegram',
            'contacts.*.value' => 'required|string|max:255',
            'contacts.*.is_primary' => 'nullable|boolean',
            'social_media' => 'nullable|array',
            'social_media.*.social_media_type_id' => 'required|exists:social_media_types,id',
            'social_media.*.handle' => 'required|string|max:255',
            'social_media.*.is_primary' => 'nullable|boolean',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $contacts = $validated['contacts'] ?? [];
        $socialMedia = $validated['social_media'] ?? [];
        unset($validated['contacts'], $validated['social_media']);

        $customer = Customer::create($validated);

        // Create contacts
        foreach ($contacts as $contact) {
            $customer->contacts()->create($contact);
        }

        // Create social media
        foreach ($socialMedia as $sm) {
            $customer->socialMedia()->create($sm);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['industry', 'contacts', 'socialMedia.socialMediaType', 'notes.user', 'creator', 'updater', 'campaignRecipients.campaign']);

        // Get customer phone numbers from contacts
        $phoneNumbers = $customer->contacts()
            ->where(function ($q) {
                $q->where('type', 'phone')->orWhere('type', 'whatsapp');
            })
            ->pluck('value')
            ->map(function ($phone) {
                // Format phone (remove non-numeric characters)
                return preg_replace('/[^0-9]/', '', $phone);
            })
            ->filter()
            ->unique()
            ->toArray();

        // Get last 5 WhatsApp messages (incoming or outgoing)
        $recentMessages = collect();
        if (!empty($phoneNumbers)) {
            $recentMessages = \App\Models\WhatsAppMessage::where(function ($q) use ($phoneNumbers, $customer) {
                $q->where(function ($q) use ($phoneNumbers) {
                    $q->whereIn('from_phone', $phoneNumbers)
                        ->orWhereIn('to_phone', $phoneNumbers);
                })
                ->orWhere('customer_id', $customer->id);
            })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($msg) {
                    // Ensure media_url is a full URL if it exists
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
                    ];
                })
                ->reverse()
                ->values();
        }

        // Get campaigns where this customer is a recipient
        $campaigns = $customer->campaignRecipients()
            ->with('campaign')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($recipient) {
                return [
                    'id' => $recipient->campaign->id,
                    'name' => $recipient->campaign->name,
                    'type' => $recipient->campaign->type,
                    'status' => $recipient->status,
                    'sent_at' => $recipient->sent_at,
                    'created_at' => $recipient->campaign->created_at,
                ];
            });

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'recentMessages' => $recentMessages,
            'campaigns' => $campaigns,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $customer->load(['industry', 'contacts', 'socialMedia.socialMediaType']);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'industries' => Industry::orderBy('name')->get(),
            'socialMediaTypes' => SocialMediaType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'gender' => 'nullable|in:male,female,other',
            'language' => 'nullable|string|max:10',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'industry_id' => 'nullable|exists:industries,id',
            'status' => 'required|in:lead,prospect,customer,inactive',
            'source' => 'required|in:website,referral,advertisement,social_media,other',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|exists:customer_contacts,id',
            'contacts.*.type' => 'required|in:phone,email,whatsapp,telegram',
            'contacts.*.value' => 'required|string|max:255',
            'contacts.*.is_primary' => 'nullable|boolean',
            'social_media' => 'nullable|array',
            'social_media.*.id' => 'nullable|exists:customer_social_media,id',
            'social_media.*.social_media_type_id' => 'required|exists:social_media_types,id',
            'social_media.*.handle' => 'required|string|max:255',
            'social_media.*.is_primary' => 'nullable|boolean',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($customer->avatar) {
                \Storage::disk('public')->delete($customer->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $validated['updated_by'] = auth()->id();

        $contacts = $validated['contacts'] ?? [];
        $socialMedia = $validated['social_media'] ?? [];
        unset($validated['contacts'], $validated['social_media']);

        $customer->update($validated);

        // Update contacts
        $existingContactIds = collect($contacts)->pluck('id')->filter()->toArray();
        $customer->contacts()->whereNotIn('id', $existingContactIds)->delete();

        foreach ($contacts as $contactData) {
            if (isset($contactData['id'])) {
                $customer->contacts()->where('id', $contactData['id'])->update([
                    'type' => $contactData['type'],
                    'value' => $contactData['value'],
                    'is_primary' => $contactData['is_primary'] ?? false,
                ]);
            } else {
                $customer->contacts()->create([
                    'type' => $contactData['type'],
                    'value' => $contactData['value'],
                    'is_primary' => $contactData['is_primary'] ?? false,
                ]);
            }
        }

        // Update social media
        $existingSocialMediaIds = collect($socialMedia)->pluck('id')->filter()->toArray();
        $customer->socialMedia()->whereNotIn('id', $existingSocialMediaIds)->delete();

        foreach ($socialMedia as $smData) {
            if (isset($smData['id'])) {
                $customer->socialMedia()->where('id', $smData['id'])->update([
                    'social_media_type_id' => $smData['social_media_type_id'],
                    'handle' => $smData['handle'],
                    'is_primary' => $smData['is_primary'] ?? false,
                ]);
            } else {
                $customer->socialMedia()->create([
                    'social_media_type_id' => $smData['social_media_type_id'],
                    'handle' => $smData['handle'],
                    'is_primary' => $smData['is_primary'] ?? false,
                ]);
            }
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function quickUpdate(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:lead,prospect,customer,inactive',
            'industry_id' => 'sometimes|nullable|exists:industries,id',
        ]);

        $validated['updated_by'] = auth()->id();

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'customer' => $customer->load('industry'),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function shareViaWhatsApp(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        if (!$customer->share_key) {
            return response()->json([
                'success' => false,
                'error' => 'Customer does not have a share key.',
            ], 400);
        }

        $shareUrl = route('public.customer.card', $customer->share_key);
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
