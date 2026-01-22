<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class GenerateCustomerShareKeys extends Command
{
    protected $signature = 'customers:generate-share-keys';
    protected $description = 'Generate share keys for customers that don\'t have one';

    public function handle(): int
    {
        $customers = Customer::whereNull('share_key')->get();
        
        $this->info("Found {$customers->count()} customers without share keys.");

        foreach ($customers as $customer) {
            $customer->share_key = bin2hex(random_bytes(16));
            $customer->save();
        }

        $this->info("Generated share keys for {$customers->count()} customers.");

        return Command::SUCCESS;
    }
}
