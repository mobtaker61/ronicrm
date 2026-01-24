<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSocialMedia;
use App\Models\Industry;
use App\Models\SocialMediaType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        } else {
            // Remove avatar from validated if no new file is uploaded
            // This ensures the existing avatar is preserved
            unset($validated['avatar']);
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

        return back()->with('success', 'Customer updated successfully.');
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

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240', // 10MB max
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with([
                'import_result' => [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => ['خطا در validation: ' . implode(', ', $e->validator->errors()->all())],
                ],
            ]);
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        Log::info('Import started', [
            'filename' => $file->getClientOriginalName(),
            'extension' => $extension,
            'size' => $file->getSize(),
        ]);

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                // Handle Excel files
                $data = $this->readExcelFile($file);
            } else {
                // Handle CSV files
                $data = $this->readCsvFile($file);
            }

            Log::info('File read', ['rows_count' => count($data)]);

            if (empty($data)) {
                return back()->with([
                    'import_result' => [
                        'success' => 0,
                        'failed' => 0,
                        'errors' => ['فایل خالی است یا قابل خواندن نیست.'],
                    ],
                ]);
            }

            // Get header row
            $headers = array_map('strtolower', array_map('trim', array_shift($data)));

            DB::beginTransaction();

            foreach ($data as $rowIndex => $row) {
                try {
                    // Map row data to array
                    $rowData = [];
                    foreach ($headers as $index => $header) {
                        $rowData[$header] = $row[$index] ?? '';
                    }

                    // Validate required fields
                    if (empty($rowData['name']) || empty($rowData['type'])) {
                        $failedCount++;
                        $errors[] = "Row " . ($rowIndex + 2) . ": Missing required fields (name or type)";
                        continue;
                    }

                    // Prepare customer data (only fields that exist in customers table)
                    $customerData = [
                        'name' => trim($rowData['name']),
                        'type' => strtolower(trim($rowData['type'])) === 'company' ? 'company' : 'person',
                        'company_name' => trim($rowData['company_name'] ?? ''),
                        'address' => trim($rowData['address'] ?? ''),
                        'status' => $this->mapStatus(trim($rowData['status'] ?? 'lead')),
                        'source' => $this->mapSource(trim($rowData['source'] ?? 'other')),
                        'gender' => $this->mapGender(trim($rowData['gender'] ?? '')),
                        'language' => trim($rowData['language'] ?? ''),
                        'contact_person' => trim($rowData['contact_person'] ?? ''),
                        'notes' => trim($rowData['notes'] ?? ''),
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ];

                    // Handle industry
                    if (!empty($rowData['industry_name'])) {
                        $industry = Industry::firstOrCreate(
                            ['name' => trim($rowData['industry_name'])],
                            ['created_by' => auth()->id(), 'updated_by' => auth()->id()]
                        );
                        $customerData['industry_id'] = $industry->id;
                    } elseif (!empty($rowData['industry_id'])) {
                        $customerData['industry_id'] = $rowData['industry_id'];
                    }

                    // Create customer
                    $customer = Customer::create($customerData);

                    // Handle contacts
                    $this->importContacts($customer, $rowData);

                    // Handle social media
                    $this->importSocialMedia($customer, $rowData);

                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMessage = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    $errors[] = $errorMessage;
                    Log::warning('Import row failed', [
                        'row' => $rowIndex + 2,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Import completed', [
                'success' => $successCount,
                'failed' => $failedCount,
                'errors_count' => count($errors),
            ]);

            // Return Inertia response with import result
            return back()->with([
                'import_result' => [
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with([
                'import_result' => [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => ['خطا در import: ' . $e->getMessage()],
                ],
            ]);
        }
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->readExcelFile($file);
            } else {
                $data = $this->readCsvFile($file);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'فایل خالی است یا قابل خواندن نیست.',
                    'headers' => [],
                    'rows' => [],
                ]);
            }

            // Get header row
            $headers = array_map('strtolower', array_map('trim', array_shift($data)));
            
            // Limit to first 10 rows for preview
            $previewRows = array_slice($data, 0, 10);

            // Map rows to objects
            $mappedRows = [];
            foreach ($previewRows as $rowIndex => $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? '';
                }
                $mappedRows[] = $rowData;
            }

            return response()->json([
                'success' => true,
                'headers' => $headers,
                'rows' => $mappedRows,
                'total_rows' => count($data),
                'preview_rows' => count($previewRows),
            ]);
        } catch (\Exception $e) {
            Log::error('Preview failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در خواندن فایل: ' . $e->getMessage(),
                'headers' => [],
                'rows' => [],
            ]);
        }
    }

    protected function readCsvFile($file): array
    {
        $data = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle !== false) {
            // Check for BOM (UTF-8 BOM: EF BB BF)
            $firstBytes = fread($handle, 3);
            if ($firstBytes !== "\xEF\xBB\xBF") {
                // No BOM, rewind to start
                rewind($handle);
            }
            // If BOM exists, we're already past it, so continue reading

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }

        return $data;
    }

    protected function readExcelFile($file): array
    {
        // For Excel files, we'll use a simple approach
        // If PhpSpreadsheet is not available, convert to CSV first
        $data = [];
        
        // Try to read as CSV first (some Excel files can be read as CSV)
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }

        // If that doesn't work, we'll need PhpSpreadsheet
        // For now, we'll support CSV format that can be exported from Excel
        return $data;
    }

    protected function importContacts($customer, $rowData): void
    {
        $contactTypes = ['phone', 'email', 'whatsapp', 'telegram'];
        
        // Handle individual contact fields (phone, email, whatsapp, telegram)
        foreach ($contactTypes as $type) {
            $value = trim($rowData[$type] ?? '');
            if (!empty($value) && $value !== '-') {
                // Handle format like "phone:09123456789" or just "09123456789"
                if (str_contains($value, ':')) {
                    $parts = explode(':', $value, 2);
                    $value = trim($parts[1]);
                }
                
                if (!empty($value)) {
                    $customer->contacts()->create([
                        'type' => $type,
                        'value' => $value,
                        'is_primary' => false,
                    ]);
                }
            }
        }
        
        // Handle combined contacts field (e.g., "whatsapp:+971502354865")
        if (!empty($rowData['contacts'])) {
            $contactsStr = trim($rowData['contacts']);
            if ($contactsStr !== '-' && !empty($contactsStr)) {
                // Split by comma if multiple contacts
                $contacts = explode(',', $contactsStr);
                foreach ($contacts as $contact) {
                    $contact = trim($contact);
                    if (empty($contact) || $contact === '-') {
                        continue;
                    }
                    
                    // Parse format like "whatsapp:+971502354865" or "phone:09123456789"
                    if (str_contains($contact, ':')) {
                        $parts = explode(':', $contact, 2);
                        $contactType = strtolower(trim($parts[0]));
                        $contactValue = trim($parts[1]);
                        
                        // Validate contact type
                        if (in_array($contactType, $contactTypes) && !empty($contactValue)) {
                            $customer->contacts()->create([
                                'type' => $contactType,
                                'value' => $contactValue,
                                'is_primary' => false,
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function importSocialMedia($customer, $rowData): void
    {
        $socialMediaTypes = ['instagram', 'telegram', 'linkedin', 'facebook', 'twitter', 'website'];
        
        // Handle individual social media fields
        foreach ($socialMediaTypes as $type) {
            $handle = trim($rowData[$type] ?? '');
            if (!empty($handle) && $handle !== '-') {
                // Handle format like "instagram:username" or just "username"
                if (str_contains($handle, ':')) {
                    $parts = explode(':', $handle, 2);
                    $handle = trim($parts[1]);
                }
                
                if (!empty($handle)) {
                    // Find or create social media type
                    $socialMediaType = SocialMediaType::where('name', $type)->first();
                    if ($socialMediaType) {
                        $customer->socialMedia()->create([
                            'social_media_type_id' => $socialMediaType->id,
                            'handle' => $handle,
                            'is_primary' => false,
                        ]);
                    }
                }
            }
        }
        
        // Handle combined social_media field (e.g., "Website:abrahclinics.com")
        if (!empty($rowData['social_media'])) {
            $socialMediaStr = trim($rowData['social_media']);
            if ($socialMediaStr !== '-' && !empty($socialMediaStr)) {
                // Split by comma if multiple social media
                $socialMedias = explode(',', $socialMediaStr);
                foreach ($socialMedias as $sm) {
                    $sm = trim($sm);
                    if (empty($sm) || $sm === '-') {
                        continue;
                    }
                    
                    // Parse format like "Website:abrahclinics.com" or "instagram:username"
                    if (str_contains($sm, ':')) {
                        $parts = explode(':', $sm, 2);
                        $smType = strtolower(trim($parts[0]));
                        $smHandle = trim($parts[1]);
                        
                        // Normalize type names
                        if ($smType === 'website' || $smType === 'web' || $smType === 'site') {
                            $smType = 'website';
                        }
                        
                        if (!empty($smHandle)) {
                            $socialMediaType = SocialMediaType::where('name', $smType)->first();
                            if ($socialMediaType) {
                                $customer->socialMedia()->create([
                                    'social_media_type_id' => $socialMediaType->id,
                                    'handle' => $smHandle,
                                    'is_primary' => false,
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        // Handle domain field (add as website social media)
        if (!empty($rowData['domain'])) {
            $domain = trim($rowData['domain']);
            if ($domain !== '-' && !empty($domain)) {
                // Remove http:// or https:// if present
                $domain = preg_replace('#^https?://#', '', $domain);
                $domain = trim($domain, '/');
                
                if (!empty($domain)) {
                    $socialMediaType = SocialMediaType::where('name', 'website')->first();
                    if ($socialMediaType) {
                        // Check if already exists
                        $exists = $customer->socialMedia()
                            ->where('social_media_type_id', $socialMediaType->id)
                            ->where('handle', $domain)
                            ->exists();
                        
                        if (!$exists) {
                            $customer->socialMedia()->create([
                                'social_media_type_id' => $socialMediaType->id,
                                'handle' => $domain,
                                'is_primary' => false,
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'lead' => 'lead',
            'prospect' => 'prospect',
            'customer' => 'customer',
            'inactive' => 'inactive',
        ];
        
        return $statusMap[strtolower($status)] ?? 'lead';
    }

    protected function mapSource(string $source): string
    {
        $sourceMap = [
            'website' => 'website',
            'referral' => 'referral',
            'advertisement' => 'advertisement',
            'social_media' => 'social_media',
            'other' => 'other',
        ];
        
        return $sourceMap[strtolower($source)] ?? 'other';
    }

    protected function mapGender(?string $gender): ?string
    {
        if (empty($gender)) {
            return null;
        }
        
        $genderMap = [
            'male' => 'male',
            'm' => 'male',
            'female' => 'female',
            'f' => 'female',
            'other' => 'other',
        ];
        
        return $genderMap[strtolower($gender)] ?? null;
    }
}
