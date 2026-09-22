<?php

namespace Nexus\Clients\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Onboarded Clients: a profile for each client 12 Nexus signs, holding their
 * details, documents, the contract, and monthly invoices with PDFs. Takes the
 * place of Krayin's Quotes in the menu.
 */
class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'clients');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'clients');

        $this->hideQuotes();

        /**
         * "Onboard as client" on a won lead.
         */
        Event::listen('admin.leads.view.title.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('clients::leads.onboard-button');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/clients.php', 'clients');
    }

    /**
     * Onboarded Clients replaces Quotes, so the Quotes entry leaves the menu.
     * The module itself stays installed, keeping upgrades clean.
     */
    protected function hideQuotes(): void
    {
        $menu = collect(config('menu.admin', []))
            ->reject(fn ($item) => ($item['key'] ?? '') === 'quotes' || str_starts_with($item['key'] ?? '', 'quotes.'))
            ->values()
            ->all();

        config(['menu.admin' => $menu]);
    }
}
