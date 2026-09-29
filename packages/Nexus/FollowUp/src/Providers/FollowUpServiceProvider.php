<?php

namespace Nexus\FollowUp\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Nexus\FollowUp\Listeners\QueueIntroEmail;

class FollowUpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'follow_up');

        /**
         * Fires for every lead, whichever screen created it.
         */
        Event::listen('lead.create.after', [QueueIntroEmail::class, 'handle']);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/follow_up.php', 'follow_up');

        $this->mergeConfigFrom(dirname(__DIR__).'/Config/mailers.php', 'mail.mailers');
    }
}
