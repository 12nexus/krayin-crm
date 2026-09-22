<?php

namespace Nexus\Funnel\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Nexus\Funnel\Listeners\CardData;
use Nexus\Funnel\Listeners\MeetingGuard;
use Nexus\Funnel\Listeners\StageSync;

/**
 * The 12Nexus sales funnel on top of Krayin's leads:
 *
 *   New Lead → Meeting Scheduled → No Show → Follow Up → Won / Lost
 *
 * with invalid leads archived to their own pipeline. Hooks into Krayin through
 * its events and render hooks rather than by editing its templates.
 */
class FunnelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'funnel');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'funnel');

        /**
         * Kanban card: meeting time, part-time/full-time and validity.
         */
        Event::listen('admin.leads.resource.extra', [CardData::class, 'handle']);

        /**
         * Keep "Meeting Appeared?" in step with drag-and-drop stage moves.
         */
        Event::listen('lead.update.after', [StageSync::class, 'handle']);

        /**
         * Meetings go through the sales form's meeting fields, not Krayin's
         * generic activity form.
         */
        Event::listen('activity.create.before', [MeetingGuard::class, 'creating']);

        Event::listen('activity.update.before', [MeetingGuard::class, 'updating']);

        /**
         * Funnel panel on the lead view: current meeting and the next moves.
         */
        Event::listen('admin.leads.view.title.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('funnel::leads.panel');
        });

        /**
         * "Reschedule" on an open meeting in the lead's activity list, in place
         * of Krayin's edit form, which has no timezone.
         */
        Event::listen(
            'admin.components.activities.content.activity.item.more_actions.dropdown.menu_item.before',
            function ($viewRenderEventManager) {
                $viewRenderEventManager->addTemplate('funnel::activities.reschedule-menu-item');
            }
        );

        /**
         * Click-to-copy for email addresses and phone numbers.
         */
        Event::listen('admin.layout.body.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('funnel::layouts.copy');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/funnel.php', 'funnel');
    }
}
