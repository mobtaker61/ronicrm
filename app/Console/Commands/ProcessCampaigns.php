<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use Illuminate\Console\Command;

class ProcessCampaigns extends Command
{
    protected $signature = 'campaigns:process';
    protected $description = 'Process scheduled campaigns and send messages';

    public function handle(): int
    {
        $campaigns = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with(['recipients.customer'])
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            foreach ($campaign->recipients as $recipient) {
                if ($recipient->status === 'pending') {
                    SendCampaignMessage::dispatch(
                        $recipient,
                        $campaign->type,
                        $campaign->content ?? '',
                        $campaign->subject ?? null,
                        $campaign->image ?? null
                    )->onQueue('campaigns');
                }
            }

            $this->info("Campaign '{$campaign->name}' started processing.");
        }

        // Check for completed campaigns
        $runningCampaigns = Campaign::where('status', 'running')
            ->withCount(['recipients as pending_count' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->get();

        foreach ($runningCampaigns as $campaign) {
            if ($campaign->pending_count === 0) {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                $this->info("Campaign '{$campaign->name}' completed.");
            }
        }

        return Command::SUCCESS;
    }
}
