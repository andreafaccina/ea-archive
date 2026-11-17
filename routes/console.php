<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('archive:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Registra prima questo utente.');

        return 1;
    }
    $user->role = 'admin';
    $user->save();
    $this->info('Amministratore abilitato.');
})->purpose('Promuove un utente esistente ad amministratore');
