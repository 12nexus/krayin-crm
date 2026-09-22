<?php

namespace Nexus\SalesForm\Support;

use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Repositories\LeadRepository;

/**
 * Finds a lead the signed-in user is allowed to see, matching Krayin's own
 * visibility rule: a sales executive limited to their own leads gets a 404 for
 * anyone else's rather than a hint that it exists.
 */
class LeadAccess
{
    public static function findOrFail(int $id): Lead
    {
        $lead = app(LeadRepository::class)->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds && ! in_array($lead->user_id, $userIds)) {
            abort(404);
        }

        return $lead;
    }
}
