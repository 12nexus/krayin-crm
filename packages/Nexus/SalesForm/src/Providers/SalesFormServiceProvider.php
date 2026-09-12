<?php

namespace Nexus\SalesForm\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Nexus\SalesForm\Console\Commands\ImportViciDialAgents;
use Nexus\SalesForm\Listeners\LeadCreated;

class SalesFormServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'sales_form');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sales_form');

        /**
         * Fires for every lead, whichever screen created it: the sales form,
         * Krayin's own create page, and the public web forms all dispatch this.
         */
        Event::listen('lead.create.after', [LeadCreated::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportViciDialAgents::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Merge the package's ACL entries and admin menu items into Krayin's.
     *
     * Both files are numerically indexed, so `mergeConfigFrom` appends them to
     * whatever the Admin package already registered.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/sales_form.php', 'sales_form');
    }
}
