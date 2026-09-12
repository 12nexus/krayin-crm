<?php

use Illuminate\Support\Facades\Route;
use Nexus\SalesForm\Http\Controllers\SalesFormController;

Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::controller(SalesFormController::class)->prefix('sales-form')->group(function () {
        Route::get('', 'index')->name('admin.sales_form.index');

        Route::get('lookup', 'lookup')->name('admin.sales_form.lookup');

        Route::post('', 'store')->name('admin.sales_form.store');

        Route::get('activity/{id}/calendar', 'activityCalendar')
            ->name('admin.sales_form.activity_calendar');
    });
});
