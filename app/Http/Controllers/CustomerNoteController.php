<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Http\Request;

class CustomerNoteController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        $customer->notes()->create([
            'user_id' => auth()->id(),
            'note' => $validated['note'],
        ]);

        return redirect()->back()
            ->with('success', 'Note added successfully.');
    }

    public function destroy(CustomerNote $note)
    {
        $note->delete();

        return redirect()->back()
            ->with('success', 'Note deleted successfully.');
    }
}
