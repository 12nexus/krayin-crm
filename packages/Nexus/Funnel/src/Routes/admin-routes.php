<?php

use Illuminate\Support\Facades\Route;
use Nexus\Funnel\Http\Controllers\FunnelController;

/**
 * Named under admin.leads.* so Krayin's fail-closed route check authorises them
 * through the Leads feature. Each action also needs leads.edit and a lead the
 * user may see; both are checked in the controller.
 */
Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::controller(FunnelController::class)->prefix('leads/{id}/funnel')->group(function () {
        Route::post('valid', 'valid')->name('admin.leads.funnel.valid');

        Route::post('invalid', 'invalid')->name('admin.leads.funnel.invalid');

        Route::post('restore', 'restore')->name('admin.leads.funnel.restore');

        Route::post('held', 'held')->name('admin.leads.funnel.held');

        Route::post('no-show', 'noShow')->name('admin.leads.funnel.no_show');

        Route::post('meeting', 'meeting')->name('admin.leads.funnel.meeting');
    });
});
