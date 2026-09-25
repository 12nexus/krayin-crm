<?php

use Illuminate\Support\Facades\Route;
use Nexus\SalesForm\Http\Controllers\GoogleCalendarController;
use Nexus\SalesForm\Http\Controllers\SalesFormController;

Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::controller(SalesFormController::class)->prefix('sales-form')->group(function () {
        Route::get('', 'index')->name('admin.sales_form.index');

        Route::get('lookup', 'lookup')->name('admin.sales_form.lookup');

        Route::post('', 'store')->name('admin.sales_form.store');

        Route::get('activity/{id}/calendar', 'activityCalendar')
            ->name('admin.sales_form.activity_calendar');

        /**
         * The shared sales calendar's events on a day, for the day plan.
         */
        Route::get('day-plan', 'dayPlan')->name('admin.sales_form.day_plan');

        /**
         * The sales form against a lead that already exists.
         */
        Route::get('lead/{id}', 'schedule')->name('admin.sales_form.schedule');

        Route::post('lead/{id}', 'storeSchedule')->name('admin.sales_form.schedule.store');
    });

    /**
     * Quick "Create Lead" form, reached from the New Lead column only. Named
     * under admin.leads.* so Krayin's route check authorises it through the
     * Leads feature; the controller also requires leads.create.
     */
    Route::controller(SalesFormController::class)->prefix('leads/new-lead')->group(function () {
        Route::get('', 'createNewLead')->name('admin.leads.new_lead');

        Route::post('', 'storeNewLead')->name('admin.leads.new_lead.store');
    });

    /**
     * Settings → Google Calendar (administrators). Named under admin.settings.*
     * so Krayin's route check also requires a settings permission.
     */
    Route::controller(GoogleCalendarController::class)->prefix('google-calendar')->group(function () {
        Route::get('', 'index')->name('admin.settings.google_calendar.index');

        Route::get('connect', 'connect')->name('admin.settings.google_calendar.connect');

        Route::get('callback', 'callback')->name('admin.settings.google_calendar.callback');

        Route::post('disconnect', 'disconnect')->name('admin.settings.google_calendar.disconnect');

        Route::post('link-existing', 'linkExisting')->name('admin.settings.google_calendar.link_existing');
    });
});
