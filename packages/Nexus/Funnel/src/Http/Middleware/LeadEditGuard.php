<?php

namespace Nexus\Funnel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Krayin's ACL maps only the lead edit form, its save and mass update to
 * leads.edit. Every other route that changes a lead (inline attribute edits,
 * the stage bar and board drag-and-drop, products, detaching an email) falls
 * back to "any Leads permission", so a role that may only view leads could
 * still change them. This closes that gap: those routes need leads.edit too.
 *
 * Adding notes, files and activities stays open to activities.create.
 */
class LeadEditGuard
{
    const ROUTES = [
        'admin.leads.attributes.update',
        'admin.leads.stage.update',
        'admin.leads.product.add',
        'admin.leads.product.remove',
        'admin.leads.emails.detach',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (
            $request->routeIs(...self::ROUTES)
            && auth()->guard('user')->check()
            && ! bouncer()->hasPermission('leads.edit')
        ) {
            abort(401, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
