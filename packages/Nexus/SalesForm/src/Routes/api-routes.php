<?php

use Illuminate\Support\Facades\Route;
use Nexus\SalesForm\Http\Controllers\Api\DialerController;
use Nexus\SalesForm\Http\Middleware\DialerToken;

/**
 * The 12Nexus Dialer Chrome extension. Stateless: no session, no CSRF; every
 * call but login carries the rep's dialer token (DialerToken).
 */
Route::controller(DialerController::class)->prefix('api/dialer')->group(function () {
    Route::post('login', 'login')
        ->middleware('throttle:20,1')
        ->name('api.dialer.login');

    Route::middleware([DialerToken::class, 'throttle:120,1'])->group(function () {
        Route::get('me', 'me')->name('api.dialer.me');

        Route::post('logout', 'logout')->name('api.dialer.logout');

        Route::get('lookup', 'lookup')->name('api.dialer.lookup');

        Route::get('day-plan', 'dayPlan')->name('api.dialer.day_plan');

        Route::post('leads', 'storeNewLead')->name('api.dialer.leads.store');

        Route::post('leads/meeting', 'storeMeeting')->name('api.dialer.leads.meeting');

        Route::post('leads/{id}/meeting', 'storeSchedule')->name('api.dialer.leads.schedule');
    });
});
