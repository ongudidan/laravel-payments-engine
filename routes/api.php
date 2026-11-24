<?php

use Illuminate\Support\Facades\Route;
use Ongudidan\PaymentsEngine\Http\Controllers\MpesaConfigController;
use Ongudidan\PaymentsEngine\Http\Controllers\MpesaWebhookController;
use Ongudidan\PaymentsEngine\Http\Controllers\PaymentAccountTransferController;

Route::prefix(config('payments-engine.routes.prefix', 'api/v1/payments-engine'))
    ->middleware(config('payments-engine.routes.middleware', ['api']))
    ->group(function () {
        // M-Pesa Webhook Endpoints
        Route::post('/mpesa/validation', [MpesaWebhookController::class, 'validation'])->name('payments-engine.mpesa.validation');
        Route::post('/mpesa/confirmation', [MpesaWebhookController::class, 'confirmation'])->name('payments-engine.mpesa.confirmation');
        Route::post('/mpesa/stk-callback', [MpesaWebhookController::class, 'stkCallback'])->name('payments-engine.mpesa.stk-callback');

        // M-Pesa Configuration Endpoints
        Route::get('/mpesa/configs', [MpesaConfigController::class, 'index'])->name('payments-engine.mpesa.configs.index');
        Route::post('/mpesa/configs', [MpesaConfigController::class, 'store'])->name('payments-engine.mpesa.configs.store');
        Route::put('/mpesa/configs/{config}', [MpesaConfigController::class, 'update'])->name('payments-engine.mpesa.configs.update');
        Route::delete('/mpesa/configs/{config}', [MpesaConfigController::class, 'destroy'])->name('payments-engine.mpesa.configs.destroy');
        Route::post('/mpesa/configs/{config}/toggle-active', [MpesaConfigController::class, 'toggleActive'])->name('payments-engine.mpesa.configs.toggle-active');
        Route::post('/mpesa/configs/{config}/register-urls', [MpesaConfigController::class, 'registerUrls'])->name('payments-engine.mpesa.configs.register-urls');

        // Transfer Endpoints
        Route::post('/transfers', [PaymentAccountTransferController::class, 'initiate'])->name('payments-engine.transfers.initiate');
        Route::post('/transfers/{transfer}/approve', [PaymentAccountTransferController::class, 'approve'])->name('payments-engine.transfers.approve');
        Route::post('/transfers/{transfer}/reject', [PaymentAccountTransferController::class, 'reject'])->name('payments-engine.transfers.reject');
    });
