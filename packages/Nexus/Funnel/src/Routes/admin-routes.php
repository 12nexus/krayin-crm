<?php

use Illuminate\Support\Facades\Route;
use Nexus\Funnel\Http\Controllers\FunnelController;

/**
 * Every action here needs leads.edit and a lead the user may see; both are
 * checked in the controller.
 */
Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::controller(FunnelController::class)->prefix('leads/{id}/funnel')->group(function () {
        Route::post('valid', 'valid')->name('admin.funnel.valid');

        Route::post('invalid', 'invalid')->name('admin.funnel.invalid');

        Route::post('restore', 'restore')->name('admin.funnel.restore');

        Route::post('held', 'held')->name('admin.funnel.held');

        Route::post('no-show', 'noShow')->name('admin.funnel.no_show');

        Route::post('meeting', 'meeting')->name('admin.funnel.meeting');
    });
});
