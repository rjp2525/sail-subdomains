<?php

namespace Reno\SailSubdomains;

use Illuminate\Support\ServiceProvider;
use Reno\SailSubdomains\Console\PublishCommand;
use Illuminate\Contracts\Support\DeferrableProvider;

class SailSubdomainServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
        $this->configurePublishing();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishCommand::class,
            ]);
        }
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../runtimes' => $this->app->basePath('docker'),
            ], ['sail-subdomains']);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            PublishCommand::class,
        ];
    }
}
