<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\Request;

class CustomerContactController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type' => 'required|in:phone,email,whatsapp,telegram',
            'value' => 'required|string|max:255',
            'is_primary' => 'nullable|boolean',
        ]);

        // If this is primary, unset others
        if ($validated['is_primary'] ?? false) {
            $customer->contacts()->update(['is_primary' => false]);
        }

        $customer->contacts()->create($validated);

        return redirect()->back()
            ->with('success', 'Contact added successfully.');
    }

    public function update(Request $request, Customer $customer, CustomerContact $contact)
    {
        $validated = $request->validate([
            'type' => 'required|in:phone,email,whatsapp,telegram',
            'value' => 'required|string|max:255',
            'is_primary' => 'nullable|boolean',
        ]);

        // If this is primary, unset others
        if ($validated['is_primary'] ?? false) {
            $customer->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return redirect()->back()
            ->with('success', 'Contact updated successfully.');
    }

    public function destroy(Customer $customer, CustomerContact $contact)
    {
        $contact->delete();

        return redirect()->back()
            ->with('success', 'Contact deleted successfully.');
    }
}
