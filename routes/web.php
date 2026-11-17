<?php

use App\Http\Controllers\ArchiveController as A;
use App\Http\Controllers\BrokerController;
use App\Http\Controllers\IdentityController as I;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/forgot-password', 'password', ['mode' => 'forgot'])->name('password.request');
    Route::post('/forgot-password', [I::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('password', ['mode' => 'reset', 'token' => $token]))->name('password.reset');
    Route::post('/reset-password', [I::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::view('/email/verify', 'verify')->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [I::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [I::class, 'resend'])->middleware('throttle:3,1')->name('verification.send');
});
Route::get('/', [A::class, 'home'])->name('home');
Route::get('/experts/{id}', [A::class, 'show'])->whereNumber('id');
Route::get('/experts/{id}/image', [A::class, 'image'])->whereNumber('id');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth', ['mode' => 'login'])->name('login');
    Route::view('/register', 'auth', ['mode' => 'register']);
    Route::post('/login', [A::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [A::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [A::class, 'logout']);
    Route::get('/brokers', [BrokerController::class, 'index'])->name('brokers.index');
    Route::get('/brokers/{broker}/image', [BrokerController::class, 'image'])->whereNumber('broker')->name('brokers.image');
    Route::get('/admin/brokers', [BrokerController::class, 'admin'])->name('admin.brokers.index');
    Route::get('/admin/brokers/create', [BrokerController::class, 'create'])->name('admin.brokers.create');
    Route::post('/admin/brokers', [BrokerController::class, 'store'])->name('admin.brokers.store');
    Route::get('/admin/brokers/{broker}/edit', [BrokerController::class, 'edit'])->whereNumber('broker')->name('admin.brokers.edit');
    Route::put('/admin/brokers/{broker}', [BrokerController::class, 'update'])->whereNumber('broker')->name('admin.brokers.update');
    Route::delete('/admin/brokers/{broker}', [BrokerController::class, 'destroy'])->whereNumber('broker')->name('admin.brokers.destroy');
    Route::get('/account', [A::class, 'account']);
    Route::post('/profile', [A::class, 'profile']);
    Route::post('/broker-accounts', [A::class, 'broker'])->middleware(['verified', 'throttle:5,1']);
    Route::post('/experts/{id}/builds', [A::class, 'build'])->middleware(['verified', 'throttle:5,1']);
    Route::post('/experts/{id}/reviews', [A::class, 'review'])->middleware(['verified', 'throttle:10,1']);
    Route::get('/builds/{id}/download', [A::class, 'download'])->middleware('throttle:20,1');
    Route::get('/developer', [A::class, 'developer']);
    Route::post('/developer/experts', [A::class, 'upload'])->middleware(['verified', 'throttle:5,1']);
    Route::get('/admin', [A::class, 'admin']);
    Route::post('/admin/moderate', [A::class, 'moderate']);
    Route::get('/admin/experts/{id}/source', [A::class, 'source']);
});
