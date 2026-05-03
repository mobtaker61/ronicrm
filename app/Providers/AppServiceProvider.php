<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Organization;
use App\Observers\CustomerObserver;
use App\Policies\MediaFilePolicy;
use App\Policies\MediaFolderPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->stabilizeMysqlPdoSession();
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(MediaFolder::class, MediaFolderPolicy::class);
        Gate::policy(MediaFile::class, MediaFilePolicy::class);

        Customer::observe(CustomerObserver::class);
    }

    /**
     * PHP 8.4 + PDO/MySQL on some hosts may start with orphan tx/autocommit off.
     * This makes INSERT/UPDATE appear successful but not persisted.
     */
    protected function stabilizeMysqlPdoSession(): void
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return;
            }

            $pdo = $connection->getPdo();

            // Ensure session autocommit is ON for expected Laravel behavior.
            $connection->statement('SET SESSION autocommit = 1');

            // If PDO believes there is an open tx but Laravel level is 0, commit it.
            if ($pdo->inTransaction() && $connection->transactionLevel() === 0) {
                try {
                    $pdo->commit();
                } catch (\Throwable) {
                    // Ignore driver-level no-active-transaction errors.
                }
            }
        } catch (\Throwable) {
            // Never block app boot due to DB session stabilization failure.
        }
    }
}
