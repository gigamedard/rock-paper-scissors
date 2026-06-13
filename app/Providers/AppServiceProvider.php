<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PreMove;
use App\Observers\PreMoveObserver;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiToken;
use App\Services\ApiTokenGuard;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }



    public function boot()
    {   
        PreMove::observe(PreMoveObserver::class);
        Auth::extend('api-token', function ($app, $name, array $config) {
            return new ApiTokenGuard($config['provider']);
        });

        // Compatibility macros for skipLocked()
        \Illuminate\Database\Query\Builder::macro('skipLocked', function () {
            try {
                $connection = $this->getConnection();
                $driver = $connection->getDriverName();
                if ($driver === 'mysql') {
                    $version = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
                    $isMariaDb = str_contains(strtolower($version), 'mariadb');
                    if ($isMariaDb) {
                        preg_match('/10\.([6-9]|\d{2,})\./', $version, $matches);
                        if (!empty($matches)) {
                            $this->lock = $this->lock === true ? 'FOR UPDATE SKIP LOCKED' : $this->lock . ' SKIP LOCKED';
                        }
                    } else {
                        if (version_compare($version, '8.0.0', '>=')) {
                            $this->lock = $this->lock === true ? 'FOR UPDATE SKIP LOCKED' : $this->lock . ' SKIP LOCKED';
                        }
                    }
                } elseif ($driver === 'pgsql') {
                    $this->lock = $this->lock === true ? 'FOR UPDATE SKIP LOCKED' : $this->lock . ' SKIP LOCKED';
                }
            } catch (\Exception $e) {
                // Fallback to safe no-op if connection/version check fails
            }
            return $this;
        });

        \Illuminate\Database\Eloquent\Builder::macro('skipLocked', function () {
            $this->getQuery()->skipLocked();
            return $this;
        });
    }

}
