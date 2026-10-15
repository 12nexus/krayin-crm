<?php

namespace Nexus\Phone\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The 12Nexus web phone inside the CRM: a phone dock on every page signed in as
 * the current user (calls and texts), Call and Text buttons on a lead's numbers,
 * and each call logged as a "call" activity and each text as a note on its lead
 * (see Services/CallLog).
 */
class PhoneServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'phone');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'phone');

        Event::listen('admin.layout.body.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('phone::layouts.dock');
        });

        Event::listen('admin.leads.view.person.contact_numbers.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('phone::leads.call-buttons');
        });

        Event::listen('admin.components.activities.content.activity.item.description.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('phone::activities.recording');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/phone.php', 'phone');
    }
}
