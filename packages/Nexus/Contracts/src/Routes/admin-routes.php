<?php

use Illuminate\Support\Facades\Route;
use Nexus\Contracts\Http\Controllers\ContractController;

/**
 * Named under admin.leads.* and mapped to leads.contracts in the ACL; the lead
 * must also be one the user may see, checked in the controller.
 */
Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::controller(ContractController::class)->prefix('leads/{id}/contracts')->group(function () {
        Route::get('', 'edit')->name('admin.leads.contracts.edit');

        Route::put('', 'update')->name('admin.leads.contracts.update');

        Route::get('{document}', 'download')
            ->whereIn('document', ['nda', 'agreement'])
            ->name('admin.leads.contracts.download');
    });
});
