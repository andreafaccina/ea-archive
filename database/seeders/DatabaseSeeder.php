<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (! app()->environment('local')) {
            return;
        }
        $author = User::firstOrCreate(['email' => 'studio@example.invalid'], ['name' => 'Archive Studio', 'password' => bin2hex(random_bytes(24))]);
        $author->role = 'developer';
        $author->save();
        foreach ([
            ['Atlas Trend', 'Trend following', 'EURUSD', 'H1', 'Medio', 'Una strategia dimostrativa che segue i movimenti di mercato con filtri di volatilità. Scheda di esempio: non disponibile per il trading reale.'],
            ['Quiet Range', 'Mean reversion', 'GBPUSD', 'M15', 'Alto', 'Un esempio di sistema dedicato ai mercati laterali e ai ritorni verso la media. Scheda dimostrativa, senza risultati di rendimento verificati.'],
            ['North Star', 'Breakout', 'XAUUSD', 'H4', 'Alto', 'Un modello dimostrativo che osserva la rottura dei livelli e i cambiamenti di volatilità. Non costituisce una raccomandazione di investimento.'],
        ] as [$name,$strategy,$symbols,$timeframe,$risk,$description]) {
            DB::table('experts')->updateOrInsert(['user_id' => $author->id, 'name' => $name], compact('strategy', 'symbols', 'timeframe', 'risk', 'description') + ['version' => '1.0', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
