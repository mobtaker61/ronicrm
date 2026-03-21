<?php

namespace App\Http\Controllers;

use App\Jobs\RunScrapTaskJob;
use App\Models\ScrapTask;
use App\Models\ScrapTaskResult;
use App\Services\WebScraperService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScrapTaskController extends Controller
{
    public function index(): Response
    {
        $tasks = ScrapTask::with(['creator', 'urls', 'extractParams', 'listConfig'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('ScrapTasks/Index', [
            'tasks' => $tasks,
        ]);
    }

    public function create(): Response
    {
        $listTasks = ScrapTask::where('type', ScrapTask::TYPE_LIST)
            ->whereHas('results')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name'])
            ->map(function ($t) {
                $items = $t->results()->first()?->extracted_data['items'] ?? [];
                return ['id' => $t->id, 'name' => $t->name, 'items_count' => is_array($items) ? count($items) : 0];
            })
            ->filter(fn ($t) => ($t['items_count'] ?? 0) > 0)
            ->values()
            ->all();

        return Inertia::render('ScrapTasks/Create', [
            'listTasks' => $listTasks,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type', 'detail');
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:list,detail',
        ];

        if ($type === 'list') {
            $rules['url'] = 'required|url';
            $rules['list_config.selector_type'] = 'required|in:xpath,class,id';
            $rules['list_config.selector_value'] = 'required|string|max:500';
            $rules['list_config.value_kind'] = 'required|in:text,attribute';
            $rules['list_config.value_attr'] = 'nullable|string|max:50';
            if ($request->input('list_config.value_kind') === 'attribute') {
                $rules['list_config.value_attr'] = 'required|string|max:50';
            }
        } else {
            $rules['urls'] = 'required|array|min:1';
            $rules['urls.*'] = 'required|url';
            $rules['extract_params'] = 'required|array|min:1';
            $rules['extract_params.*.name'] = 'required|string|max:100';
            $rules['extract_params.*.selector_type'] = 'required|in:xpath,class,id';
            $rules['extract_params.*.selector_value'] = 'required|string|max:500';
        }

        $validated = $request->validate($rules);

        $task = ScrapTask::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        // Debug: verify the task really exists right after creation.
        // This helps track cases where redirect happens but record isn't found.
        Log::debug('ScrapTask store: created', [
            'task_id' => $task->id,
            'type' => $task->type,
            'user_id' => $request->user()?->id,
            'exists_after_create' => ScrapTask::find($task->id) !== null,
        ]);

        if ($type === 'list') {
            $task->urls()->create([
                'url' => trim($validated['url']),
                'status' => 'pending',
            ]);
            $cfg = $validated['list_config'];
            $valueAttr = $cfg['value_attr'] ?? null;
            if (($cfg['value_kind'] ?? '') === 'attribute' && ($valueAttr === null || $valueAttr === '')) {
                $valueAttr = 'href';
            }
            $task->listConfig()->create([
                'selector_type' => $cfg['selector_type'],
                'selector_value' => $cfg['selector_value'],
                'value_kind' => $cfg['value_kind'] ?? 'text',
                'value_attr' => $valueAttr,
                'delay_seconds' => isset($cfg['delay_seconds']) && $cfg['delay_seconds'] !== '' ? (int) $cfg['delay_seconds'] : null,
                'pagination_type' => $cfg['pagination_type'] ?? null,
                'pagination_selector_type' => $cfg['pagination_selector_type'] ?? null,
                'pagination_selector_value' => $cfg['pagination_selector_value'] ?? null,
                'max_pages' => isset($cfg['max_pages']) && $cfg['max_pages'] !== '' ? (int) $cfg['max_pages'] : null,
            ]);
        } else {
            foreach ($validated['urls'] as $url) {
                $task->urls()->create([
                    'url' => trim($url),
                    'status' => 'pending',
                ]);
            }
            foreach (array_values($validated['extract_params']) as $i => $param) {
                $task->extractParams()->create([
                    'name' => $param['name'],
                    'selector_type' => $param['selector_type'],
                    'selector_value' => $param['selector_value'],
                    'sort_order' => $i,
                ]);
            }
        }

        $showUrl = route('scrap-tasks.show', $task);
        Log::debug('ScrapTask store: redirecting', [
            'task_id' => $task->id,
            'show_url' => $showUrl,
        ]);

        return redirect()->to($showUrl)
            ->with('success', 'Scraping task created successfully.');
    }

    public function edit(ScrapTask $scrapTask): Response
    {
        $scrapTask->load([
            'urls' => fn ($q) => $q->orderBy('id'),
            'extractParams' => fn ($q) => $q->orderBy('sort_order'),
            'listConfig',
        ]);

        $listTasks = ScrapTask::where('type', ScrapTask::TYPE_LIST)
            ->where('id', '!=', $scrapTask->id)
            ->whereHas('results')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name'])
            ->map(function ($t) {
                $items = $t->results()->first()?->extracted_data['items'] ?? [];
                return ['id' => $t->id, 'name' => $t->name, 'items_count' => is_array($items) ? count($items) : 0];
            })
            ->filter(fn ($t) => ($t['items_count'] ?? 0) > 0)
            ->values()
            ->all();

        return Inertia::render('ScrapTasks/Edit', [
            'task' => $scrapTask,
            'listTasks' => $listTasks,
        ]);
    }

    public function update(Request $request, ScrapTask $scrapTask)
    {
        $type = $request->input('type', $scrapTask->type);
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:list,detail',
        ];

        if ($type === 'list') {
            $rules['url'] = 'required|url';
            $rules['list_config.selector_type'] = 'required|in:xpath,class,id';
            $rules['list_config.selector_value'] = 'required|string|max:500';
            $rules['list_config.value_kind'] = 'required|in:text,attribute';
            $rules['list_config.value_attr'] = 'nullable|string|max:50';
            $rules['list_config.delay_seconds'] = 'nullable|integer|min:0|max:300';
            $rules['list_config.pagination_type'] = 'nullable|in:next_page,load_more';
            $rules['list_config.pagination_selector_type'] = 'nullable|in:xpath,class,id';
            $rules['list_config.pagination_selector_value'] = 'nullable|string|max:500';
            $rules['list_config.max_pages'] = 'nullable|integer|min:1|max:1000';
            if ($request->input('list_config.value_kind') === 'attribute') {
                $rules['list_config.value_attr'] = 'required|string|max:50';
            }
            if ($request->input('list_config.pagination_type')) {
                $rules['list_config.pagination_selector_type'] = 'required|in:xpath,class,id';
                $rules['list_config.pagination_selector_value'] = 'required|string|max:500';
                $rules['list_config.max_pages'] = 'required|integer|min:1|max:1000';
            }
        } else {
            $rules['urls'] = 'required|array|min:1';
            $rules['urls.*'] = 'required|url';
            $rules['extract_params'] = 'required|array|min:1';
            $rules['extract_params.*.name'] = 'required|string|max:100';
            $rules['extract_params.*.selector_type'] = 'required|in:xpath,class,id';
            $rules['extract_params.*.selector_value'] = 'required|string|max:500';
        }

        $validated = $request->validate($rules);

        $scrapTask->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
        ]);

        $scrapTask->urls()->delete();
        $scrapTask->extractParams()->delete();
        $scrapTask->load('listConfig');
        if ($scrapTask->listConfig) {
            $scrapTask->listConfig->delete();
        }

        if ($type === 'list') {
            $scrapTask->urls()->create([
                'url' => trim($validated['url']),
                'status' => 'pending',
            ]);
            $cfg = $validated['list_config'];
            $valueAttr = $cfg['value_attr'] ?? null;
            if (($cfg['value_kind'] ?? '') === 'attribute' && ($valueAttr === null || $valueAttr === '')) {
                $valueAttr = 'href';
            }
            $scrapTask->listConfig()->create([
                'selector_type' => $cfg['selector_type'],
                'selector_value' => $cfg['selector_value'],
                'value_kind' => $cfg['value_kind'] ?? 'text',
                'value_attr' => $valueAttr,
                'delay_seconds' => isset($cfg['delay_seconds']) && $cfg['delay_seconds'] !== '' ? (int) $cfg['delay_seconds'] : null,
                'pagination_type' => $cfg['pagination_type'] ?? null,
                'pagination_selector_type' => $cfg['pagination_selector_type'] ?? null,
                'pagination_selector_value' => $cfg['pagination_selector_value'] ?? null,
                'max_pages' => isset($cfg['max_pages']) && $cfg['max_pages'] !== '' ? (int) $cfg['max_pages'] : null,
            ]);
        } else {
            foreach ($validated['urls'] as $url) {
                $scrapTask->urls()->create([
                    'url' => trim($url),
                    'status' => 'pending',
                ]);
            }
            foreach (array_values($validated['extract_params']) as $i => $param) {
                $scrapTask->extractParams()->create([
                    'name' => $param['name'],
                    'selector_type' => $param['selector_type'],
                    'selector_value' => $param['selector_value'],
                    'sort_order' => $i,
                ]);
            }
        }

        return redirect()->route('scrap-tasks.show', $scrapTask)
            ->with('success', 'Scraping task updated successfully.');
    }

    public function show(ScrapTask $scrapTask): Response
    {
        Log::debug('ScrapTask show', [
            'task_id' => $scrapTask->id,
            'type' => $scrapTask->type,
        ]);

        $scrapTask->load([
            'urls' => fn ($q) => $q->orderBy('id'),
            'extractParams' => fn ($q) => $q->orderBy('sort_order'),
            'listConfig',
            'results.scrapTaskUrl',
            'creator',
        ]);

        return Inertia::render('ScrapTasks/Show', [
            'task' => $scrapTask,
        ]);
    }

    public function destroy(ScrapTask $scrapTask)
    {
        $scrapTask->delete();
        return redirect()->route('scrap-tasks.index')
            ->with('success', 'Scraping task deleted.');
    }

    /**
     * Run the scrap task in background (Job). For live progress, run: php artisan queue:work
     */
    public function run(Request $request, ScrapTask $scrapTask)
    {
        if (! in_array($scrapTask->status, ['draft', 'failed', 'completed'], true)) {
            return redirect()->route('scrap-tasks.show', $scrapTask)
                ->with('error', 'Only draft, failed, or completed tasks can be run.');
        }

        $scrapTask->update([
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        RunScrapTaskJob::dispatch($scrapTask);

        return redirect()->route('scrap-tasks.show', $scrapTask, 303)
            ->with('success', 'Task started. If a queue worker is running, live progress will be shown.');
    }

    /**
     * Run the scrap task synchronously in this request (no queue). No live progress, but works without worker.
     */
    public function runSync(Request $request, ScrapTask $scrapTask)
    {
        if (! in_array($scrapTask->status, ['draft', 'failed', 'completed'], true)) {
            return redirect()->route('scrap-tasks.show', $scrapTask)
                ->with('error', 'Only draft, failed, or completed tasks can be run.');
        }

        $scrapTask->update([
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $scrapTask->results()->delete();
        foreach ($scrapTask->urls as $url) {
            $url->update(['status' => 'pending', 'error_message' => null]);
        }

        $scraper = new WebScraperService();

        if ($scrapTask->type === ScrapTask::TYPE_LIST) {
            $scrapTask->load(['urls', 'listConfig']);
            $url = $scrapTask->urls->first();
            $config = $scrapTask->listConfig;
            if (! $url) {
                $scrapTask->update(['status' => 'failed', 'completed_at' => now()]);
                return redirect()->route('scrap-tasks.show', $scrapTask)
                    ->with('error', 'No URL configured for this task.');
            }
            if (! $config) {
                $scrapTask->update(['status' => 'failed', 'completed_at' => now()]);
                return redirect()->route('scrap-tasks.show', $scrapTask)
                    ->with('error', 'List selector config not found.');
            }
            // Use extractListWithPagination if pagination is configured, otherwise use extractList
            if ($config->pagination_type && $config->pagination_selector_value && $config->max_pages) {
                $list = $scraper->extractListWithPagination($url->url, [
                    'selector_type' => $config->selector_type,
                    'selector_value' => $config->selector_value,
                    'value_kind' => $config->value_kind,
                    'value_attr' => $config->value_attr ?? '',
                    'delay_seconds' => $config->delay_seconds,
                    'pagination_type' => $config->pagination_type,
                    'pagination_selector_type' => $config->pagination_selector_type,
                    'pagination_selector_value' => $config->pagination_selector_value,
                    'max_pages' => $config->max_pages,
                ]);
            } else {
                $html = $scraper->fetchHtml($url->url);
                if ($html === null) {
                    ScrapTaskResult::create([
                        'scrap_task_id' => $scrapTask->id,
                        'scrap_task_url_id' => $url->id,
                        'extracted_data' => ['items' => []],
                        'status' => 'failed',
                        'error_message' => 'Failed to fetch page.',
                    ]);
                    $url->update(['status' => 'failed', 'error_message' => 'Failed to fetch page.']);
                    $scrapTask->update(['status' => 'completed', 'completed_at' => now()]);
                    return redirect()->route('scrap-tasks.show', $scrapTask)
                        ->with('error', 'Failed to fetch page.');
                }

                // Apply delay if configured
                if ($config->delay_seconds && $config->delay_seconds > 0) {
                    sleep($config->delay_seconds);
                }

                $countCheck = $scraper->countListMatches($html, [
                    'selector_type' => $config->selector_type,
                    'selector_value' => $config->selector_value,
                ]);
                \Illuminate\Support\Facades\Log::info('ScrapTask runSync list', [
                    'task_id' => $scrapTask->id,
                    'html_length' => strlen($html),
                    'selector_match_count' => $countCheck,
                    'value_attr' => $config->value_attr,
                ]);
                $list = $scraper->extractList($html, [
                    'selector_type' => $config->selector_type,
                    'selector_value' => $config->selector_value,
                    'value_kind' => $config->value_kind,
                    'value_attr' => $config->value_attr ?? '',
                ], $url->url);
                \Illuminate\Support\Facades\Log::info('ScrapTask runSync list result', [
                    'task_id' => $scrapTask->id,
                    'items_count' => count($list),
                ]);
            }

            ScrapTaskResult::create([
                'scrap_task_id' => $scrapTask->id,
                'scrap_task_url_id' => $url->id,
                'extracted_data' => ['items' => $list],
                'status' => 'success',
            ]);
            $url->update(['status' => 'success']);
        } else {
            $params = $scrapTask->extractParams->map(fn ($p) => [
                'name' => $p->name,
                'selector_type' => $p->selector_type,
                'selector_value' => $p->selector_value,
            ])->all();

            foreach ($scrapTask->urls as $url) {
                $html = $scraper->fetchHtml($url->url);
                if ($html === null) {
                    ScrapTaskResult::create([
                        'scrap_task_id' => $scrapTask->id,
                        'scrap_task_url_id' => $url->id,
                        'extracted_data' => null,
                        'status' => 'failed',
                        'error_message' => 'Failed to fetch page.',
                    ]);
                    $url->update(['status' => 'failed', 'error_message' => 'Failed to fetch page.']);
                    continue;
                }

                $extracted = $scraper->extract($html, $params);
                ScrapTaskResult::create([
                    'scrap_task_id' => $scrapTask->id,
                    'scrap_task_url_id' => $url->id,
                    'extracted_data' => $extracted,
                    'status' => 'success',
                ]);
                $url->update(['status' => 'success']);
            }
        }

        $scrapTask->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('scrap-tasks.show', $scrapTask)
            ->with('success', 'Task finished.');
    }

    /**
     * Reset a task stuck in "running" so it can be run again.
     */
    public function reset(ScrapTask $scrapTask)
    {
        if ($scrapTask->status !== 'running') {
            return redirect()->route('scrap-tasks.show', $scrapTask)
                ->with('error', 'Only running tasks can be reset.');
        }

        $scrapTask->update(['status' => 'failed']);
        $scrapTask->results()->delete();
        foreach ($scrapTask->urls as $url) {
            $url->update(['status' => 'pending', 'error_message' => null]);
        }

        return redirect()->route('scrap-tasks.show', $scrapTask)
            ->with('success', 'Task reset. You can run it again.');
    }

    /**
     * Return extracted URL list from a list-type task (for importing into detail task).
     */
    public function resultUrls(ScrapTask $scrapTask): \Illuminate\Http\JsonResponse
    {
        if ($scrapTask->type !== ScrapTask::TYPE_LIST) {
            return response()->json(['message' => 'Only for list-type tasks.'], 400);
        }

        $result = $scrapTask->results()->first();
        $items = $result?->extracted_data['items'] ?? [];
        $urls = is_array($items) ? array_values(array_map('strval', $items)) : [];

        return response()->json(['urls' => $urls]);
    }

    /**
     * Test list selector: fetch page and return count of matching elements (for list-type tasks).
     */
    public function testListSelector(ScrapTask $scrapTask): \Illuminate\Http\JsonResponse
    {
        if ($scrapTask->type !== ScrapTask::TYPE_LIST) {
            return response()->json(['success' => false, 'message' => 'Only for list-type tasks.'], 400);
        }

        $scrapTask->load(['urls', 'listConfig']);
        $url = $scrapTask->urls->first();
        $config = $scrapTask->listConfig;
        if (! $url || ! $config) {
            return response()->json(['success' => false, 'message' => 'List URL or selector is not configured.'], 400);
        }

        $scraper = new WebScraperService();
        $html = $scraper->fetchHtml($url->url);
        if ($html === null) {
            return response()->json([
                'success' => false,
                'count' => 0,
                'message' => 'Failed to fetch page.',
            ]);
        }

        $count = $scraper->countListMatches($html, [
            'selector_type' => $config->selector_type,
            'selector_value' => $config->selector_value,
        ]);

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => $count > 0
                ? "Matched elements: {$count}"
                : 'No matching elements found in fetched HTML. The page might be rendered via JavaScript.',
        ]);
    }

    /**
     * JSON endpoint for polling run progress (status + results so far).
     */
    public function runStatus(ScrapTask $scrapTask): \Illuminate\Http\JsonResponse
    {
        $scrapTask->load([
            'urls' => fn ($q) => $q->orderBy('id'),
            'extractParams' => fn ($q) => $q->orderBy('sort_order'),
            'results.scrapTaskUrl',
        ]);

        $total = $scrapTask->urls->count();
        $done = $scrapTask->results->count();

        return response()->json([
            'task' => [
                'id' => $scrapTask->id,
                'type' => $scrapTask->type,
                'status' => $scrapTask->status,
                'started_at' => $scrapTask->started_at?->toIso8601String(),
                'completed_at' => $scrapTask->completed_at?->toIso8601String(),
            ],
            'progress' => [
                'total' => $total,
                'done' => $done,
                'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            ],
            'results' => $scrapTask->results->map(fn ($r) => [
                'id' => $r->id,
                'scrap_task_url_id' => $r->scrap_task_url_id,
                'url' => $r->scrapTaskUrl?->url,
                'status' => $r->status,
                'error_message' => $r->error_message,
                'extracted_data' => $r->extracted_data,
            ]),
        ]);
    }

    /**
     * Export report as Excel-compatible CSV (UTF-8 BOM).
     */
    public function exportExcel(ScrapTask $scrapTask): StreamedResponse
    {
        $scrapTask->load([
            'urls' => fn ($q) => $q->orderBy('id'),
            'extractParams' => fn ($q) => $q->orderBy('sort_order'),
            'results.scrapTaskUrl',
        ]);

        $filename = 'scrap-report-' . $scrapTask->id . '-' . now()->format('Y-m-d-His') . '.csv';

        if ($scrapTask->type === ScrapTask::TYPE_LIST) {
            $results = $scrapTask->results;
            $items = $results->first()?->extracted_data['items'] ?? [];
            return response()->streamDownload(function () use ($items) {
                $out = fopen('php://output', 'w');
                fprintf($out, "\xEF\xBB\xBF");
                fputcsv($out, ['#', 'Value']);
                foreach ($items as $i => $val) {
                    fputcsv($out, [$i + 1, is_array($val) ? implode(' | ', $val) : (string) $val]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $params = $scrapTask->extractParams;
        $results = $scrapTask->results;

        return response()->streamDownload(function () use ($params, $results) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            $headers = ['#', 'URL', ...$params->pluck('name')->all(), 'Status'];
            fputcsv($out, $headers);

            foreach ($results as $i => $r) {
                $row = [
                    $i + 1,
                    $r->scrapTaskUrl?->url ?? '',
                ];
                foreach ($params as $p) {
                    $v = ($r->extracted_data ?? [])[$p->name] ?? '';
                    $row[] = is_array($v) ? implode(' | ', $v) : (string) $v;
                }
                $row[] = $r->status === 'success' ? 'Success' : ($r->status === 'failed' ? 'Failed' : 'Pending');
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
