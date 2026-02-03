<?php

namespace App\Jobs;

use App\Models\ScrapTask;
use App\Models\ScrapTaskResult;
use App\Services\WebScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScrapTaskJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public ScrapTask $scrapTask
    ) {}

    public function handle(): void
    {
        $task = $this->scrapTask->fresh(['urls', 'extractParams', 'listConfig']);
        if (! in_array($task->status, ['running'], true)) {
            return;
        }

        $task->results()->delete();
        foreach ($task->urls as $url) {
            $url->update(['status' => 'pending', 'error_message' => null]);
        }

        $scraper = new WebScraperService();

        if ($task->type === ScrapTask::TYPE_LIST) {
            $url = $task->urls->first();
            $config = $task->listConfig;
            if (! $url || ! $config) {
                $task->update(['status' => 'completed', 'completed_at' => now()]);
                return;
            }
            $html = $scraper->fetchHtml($url->url);
            if ($html === null) {
                ScrapTaskResult::create([
                    'scrap_task_id' => $task->id,
                    'scrap_task_url_id' => $url->id,
                    'extracted_data' => ['items' => []],
                    'status' => 'failed',
                    'error_message' => 'دریافت صفحه با خطا مواجه شد.',
                ]);
                $url->update(['status' => 'failed', 'error_message' => 'دریافت صفحه با خطا مواجه شد.']);
            } else {
                $list = $scraper->extractList($html, [
                    'selector_type' => $config->selector_type,
                    'selector_value' => $config->selector_value,
                    'value_kind' => $config->value_kind,
                    'value_attr' => $config->value_attr ?? '',
                ], $url->url);
                ScrapTaskResult::create([
                    'scrap_task_id' => $task->id,
                    'scrap_task_url_id' => $url->id,
                    'extracted_data' => ['items' => $list],
                    'status' => 'success',
                ]);
                $url->update(['status' => 'success']);
            }
        } else {
            $params = $task->extractParams->map(fn ($p) => [
                'name' => $p->name,
                'selector_type' => $p->selector_type,
                'selector_value' => $p->selector_value,
            ])->all();

            foreach ($task->urls as $url) {
                $html = $scraper->fetchHtml($url->url);
                if ($html === null) {
                    ScrapTaskResult::create([
                        'scrap_task_id' => $task->id,
                        'scrap_task_url_id' => $url->id,
                        'extracted_data' => null,
                        'status' => 'failed',
                        'error_message' => 'دریافت صفحه با خطا مواجه شد.',
                    ]);
                    $url->update(['status' => 'failed', 'error_message' => 'دریافت صفحه با خطا مواجه شد.']);
                    continue;
                }

                $extracted = $scraper->extract($html, $params);
                ScrapTaskResult::create([
                    'scrap_task_id' => $task->id,
                    'scrap_task_url_id' => $url->id,
                    'extracted_data' => $extracted,
                    'status' => 'success',
                ]);
                $url->update(['status' => 'success']);
            }
        }

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
