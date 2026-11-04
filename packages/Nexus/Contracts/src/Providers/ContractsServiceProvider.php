<?php

namespace Nexus\Contracts\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Client contracts on a lead: the NDA and the Agreement to Hire Virtual
 * Assistant, filled from one set of fields that starts out from the lead's
 * details. The fields are saved on the lead, and both documents download as
 * PDFs ready to upload to Google Drive for e-signature.
 */
class ContractsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'contracts');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'contracts');

        /**
         * "Contracts" bar on the lead view: prepare the fields, download the PDFs.
         */
        Event::listen('admin.leads.view.title.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('contracts::leads.panel');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/contracts.php', 'contracts');
    }
}
