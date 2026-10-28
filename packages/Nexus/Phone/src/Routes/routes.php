<?php

use Illuminate\Support\Facades\Route;
use Nexus\Phone\Http\Controllers\AiCallerController;
use Nexus\Phone\Http\Controllers\HookController;
use Nexus\Phone\Http\Controllers\SsoController;
use Nexus\Phone\Http\Middleware\VerifyPhoneSignature;

/**
 * Named under admin.leads.* so Krayin's fail-closed route check lets anyone with
 * access to leads open the phone.
 */
Route::group(['middleware' => ['web', 'admin_locale', 'user'], 'prefix' => config('app.admin_path')], function () {
    Route::get('leads/phone/sso', [SsoController::class, 'redirect'])->name('admin.leads.phone.sso');
    Route::get('leads/phone/ai-recording/{callId}', [AiCallerController::class, 'recording'])->name('admin.leads.phone.ai-recording');
});

/**
 * Called by the phone server, signed with the shared secret.
 */
Route::middleware([VerifyPhoneSignature::class, 'throttle:120,1'])->prefix('nexus-phone')->group(function () {
    Route::get('users', [HookController::class, 'users']);
    Route::get('lookup', [HookController::class, 'lookup']);
    Route::post('names', [HookController::class, 'names']);
    Route::post('calls', [HookController::class, 'calls']);
    Route::post('texts', [HookController::class, 'texts']);

    // The phone's AI follow-up caller.
    Route::get('ai/queue', [AiCallerController::class, 'queue']);
    Route::get('ai/slots', [AiCallerController::class, 'slots']);
    Route::post('ai/book', [AiCallerController::class, 'book']);
    Route::post('ai/result', [AiCallerController::class, 'result']);
});
