<?php

use App\Http\Controllers\CompilerController;
use App\Http\Middleware\AuthenticateCompiler;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/compiler')->middleware([AuthenticateCompiler::class, 'throttle:120,1'])->group(function () {
    Route::post('/claim', [CompilerController::class, 'claim']);
    Route::get('/builds/{id}/source', [CompilerController::class, 'source'])->whereNumber('id');
    Route::post('/builds/{id}/complete', [CompilerController::class, 'complete'])->whereNumber('id');
    Route::post('/builds/{id}/fail', [CompilerController::class, 'fail'])->whereNumber('id');
});
