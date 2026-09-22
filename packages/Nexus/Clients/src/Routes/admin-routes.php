<?php

use Illuminate\Support\Facades\Route;
use Nexus\Clients\Http\Controllers\ClientController;
use Nexus\Clients\Http\Controllers\DocumentController;
use Nexus\Clients\Http\Controllers\InvoiceController;

Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path').'/clients'], function () {
    Route::controller(ClientController::class)->group(function () {
        Route::get('', 'index')->name('admin.clients.index');

        Route::get('create', 'create')->name('admin.clients.create');

        Route::post('', 'store')->name('admin.clients.store');

        Route::get('{id}', 'view')->whereNumber('id')->name('admin.clients.view');

        Route::get('{id}/edit', 'edit')->name('admin.clients.edit');

        Route::put('{id}', 'update')->name('admin.clients.update');

        Route::delete('{id}', 'destroy')->name('admin.clients.delete');
    });

    Route::controller(DocumentController::class)->prefix('{id}/documents')->group(function () {
        Route::post('', 'store')->name('admin.clients.documents.store');

        Route::get('{documentId}', 'download')->name('admin.clients.documents.download');

        Route::delete('{documentId}', 'destroy')->name('admin.clients.documents.delete');
    });

    Route::controller(InvoiceController::class)->prefix('{id}/invoices')->group(function () {
        Route::post('', 'store')->name('admin.clients.invoices.store');

        Route::put('{invoiceId}', 'update')->name('admin.clients.invoices.update');

        Route::get('{invoiceId}/pdf', 'pdf')->name('admin.clients.invoices.pdf');

        Route::delete('{invoiceId}', 'destroy')->name('admin.clients.invoices.delete');
    });
});
