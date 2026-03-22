<?php

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use App\Support\WhatsappTemplateSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CampaignTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = CampaignTemplate::orderBy('name')->get();

        return Inertia::render('Campaigns/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeContentTranslations($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,email,telegram',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'content_translations' => 'nullable|array',
            'content_translations.*' => 'nullable|string',
            'image' => 'nullable|file|max:51200', // 50MB max - accept all file types
            'image_path' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('campaign-attachments', 'public');
        } elseif ($request->filled('image_path')) {
            $sourcePath = $request->input('image_path');
            if (preg_match('/^media\/[\w\/\.\-]+$/', $sourcePath) && Storage::disk('public')->exists($sourcePath)) {
                $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
                $fileName = 'template_'.time().'_'.uniqid().'.'.$extension;
                $destinationPath = 'campaign-attachments/'.$fileName;
                Storage::disk('public')->copy($sourcePath, $destinationPath);
                $validated['image'] = $destinationPath;
            }
        }
        if (empty($validated['image'])) {
            unset($validated['image']);
        }

        unset($validated['image_path']);
        $validated['whatsapp_settings'] = WhatsappTemplateSettings::normalizeFromRequest(
            $validated['type'],
            $request->input('whatsapp_settings')
        );

        $validated['content_translations'] = $this->sanitizeContentTranslations($validated['content_translations'] ?? null);

        CampaignTemplate::create($validated);

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function update(Request $request, CampaignTemplate $campaignTemplate)
    {
        $this->normalizeContentTranslations($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,email,telegram',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'content_translations' => 'nullable|array',
            'content_translations.*' => 'nullable|string',
            'image' => 'nullable|file|max:51200', // 50MB max - accept all file types
            'image_path' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            if ($campaignTemplate->image) {
                Storage::disk('public')->delete($campaignTemplate->image);
            }
            $validated['image'] = $request->file('image')->store('campaign-attachments', 'public');
        } elseif ($request->filled('image_path')) {
            $sourcePath = $request->input('image_path');
            if (preg_match('/^media\/[\w\/\.\-]+$/', $sourcePath) && Storage::disk('public')->exists($sourcePath)) {
                if ($campaignTemplate->image) {
                    Storage::disk('public')->delete($campaignTemplate->image);
                }
                $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
                $fileName = 'template_'.time().'_'.uniqid().'.'.$extension;
                $destinationPath = 'campaign-attachments/'.$fileName;
                Storage::disk('public')->copy($sourcePath, $destinationPath);
                $validated['image'] = $destinationPath;
            }
        }
        if (empty($validated['image'])) {
            unset($validated['image']);
        }

        unset($validated['image_path']);
        $validated['whatsapp_settings'] = WhatsappTemplateSettings::normalizeFromRequest(
            $validated['type'],
            $request->input('whatsapp_settings')
        );

        $validated['content_translations'] = $this->sanitizeContentTranslations($validated['content_translations'] ?? null);

        $campaignTemplate->update($validated);

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template updated successfully.');
    }

    protected function normalizeContentTranslations(Request $request): void
    {
        $val = $request->input('content_translations');
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            $request->merge(['content_translations' => is_array($decoded) ? $decoded : []]);
        }
    }

    /**
     * Ensure content_translations is valid JSON-compatible data (MySQL CHECK constraint).
     * Empty string or invalid data causes CONSTRAINT failed.
     */
    protected function sanitizeContentTranslations(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && ($v === null || is_string($v))) {
                $out[$k] = $v === null ? '' : $v;
            }
        }
        return empty($out) ? null : $out;
    }

    public function destroy(CampaignTemplate $campaignTemplate)
    {
        $campaignTemplate->delete();

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
