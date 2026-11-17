<?php

namespace App\Http\Controllers;

use App\Jobs\CompileExpert;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ArchiveController extends Controller
{
    public function home(Request $r)
    {
        $q = DB::table('experts')->join('users', 'users.id', '=', 'experts.user_id')->where('experts.status', 'published');
        if ($r->filled('q')) {
            $q->where('experts.name', 'like', '%'.$r->string('q').'%');
        }
        $experts = $q->select('experts.*', 'users.name as author')->latest('experts.id')->paginate(12)->withQueryString();

        return view('catalog', compact('experts'));
    }

    public function show(int $id)
    {
        $expert = DB::table('experts')->where('id', $id)->where('status', 'published')->firstOrFail();
        $reviews = DB::table('reviews')->join('users', 'users.id', '=', 'reviews.user_id')->where('expert_id', $id)->select('reviews.*', 'users.name')->latest('reviews.id')->paginate(20);
        $accounts = auth()->check() ? DB::table('broker_accounts')->where('user_id', auth()->id())->where('status', 'verified')->get() : collect();

        return view('expert', compact('expert', 'reviews', 'accounts'));
    }

    public function register(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)]]);
        $u = User::create($d);
        event(new Registered($u));
        Auth::login($u);
        $r->session()->regenerate();

        return redirect('/account');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($d)) {
            return back()->withErrors(['email' => 'Credenziali non valide.']);
        }
        $r->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function account(Request $r)
    {
        $accounts = DB::table('broker_accounts')->where('user_id', $r->user()->id)->get();
        $builds = DB::table('builds')->join('experts', 'experts.id', '=', 'builds.expert_id')->where('builds.user_id', $r->user()->id)->select('builds.*', 'experts.name')->latest('builds.id')->paginate(20);

        return view('account', compact('accounts', 'builds'));
    }

    public function profile(Request $r)
    {
        $r->user()->update($r->validate(['name' => 'required|string|max:100']));

        return back()->with('success', 'Profilo aggiornato.');
    }

    public function broker(Request $r)
    {
        $d = $r->validate(['login' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'], 'server' => 'required|string|max:120']);
        DB::transaction(function () use ($r, $d) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            abort_if(DB::table('broker_accounts')->where('login', $d['login'])->where('server', $d['server'])->exists(), 422, 'Conto già presente.');
            DB::table('broker_accounts')->insert($d + ['user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Conto inviato per la verifica.');
    }

    public function build(Request $r, int $id)
    {
        $d = $r->validate(['broker_account_id' => 'required|integer']);
        DB::transaction(function () use ($r, $d, $id) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            DB::table('experts')->where('id', $id)->where('status', 'published')->firstOrFail();
            DB::table('broker_accounts')->where('id', $d['broker_account_id'])->where('user_id', $r->user()->id)->where('status', 'verified')->firstOrFail();
            abort_if(DB::table('builds')->where('user_id', $r->user()->id)->whereIn('status', ['queued', 'compiling'])->exists(), 422, 'Hai già una compilazione in corso.');
            $bid = DB::table('builds')->insertGetId(['user_id' => $r->user()->id, 'expert_id' => $id, 'broker_account_id' => $d['broker_account_id'], 'created_at' => now(), 'updated_at' => now()]);
            CompileExpert::dispatch($bid)->onQueue('compilers')->afterCommit();
        });

        return redirect('/account')->with('success', 'Compilazione richiesta.');
    }

    public function download(Request $r, int $id)
    {
        $b = DB::table('builds')->where('id', $id)->where('user_id', $r->user()->id)->where('status', 'ready')->firstOrFail();
        DB::table('broker_accounts')->where('id', $b->broker_account_id)->where('status', 'verified')->firstOrFail();
        abort_unless(Storage::disk('local')->exists($b->artifact_path), 404);
        DB::table('builds')->where('id', $id)->update(['downloaded_at' => now()]);

        return Storage::disk('local')->download($b->artifact_path, 'expert-'.$id.'.ex5', ['Cache-Control' => 'private, no-store']);
    }

    public function review(Request $r, int $id)
    {
        DB::table('experts')->where('id', $id)->where('status', 'published')->firstOrFail();
        abort_unless(DB::table('builds')->where('user_id', $r->user()->id)->where('expert_id', $id)->whereNotNull('downloaded_at')->exists(), 403);
        $d = $r->validate(['rating' => 'required|integer|between:1,5', 'body' => 'required|string|min:10|max:2000']);
        DB::table('reviews')->updateOrInsert(['user_id' => $r->user()->id, 'expert_id' => $id], $d + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Recensione salvata.');
    }

    public function developer(Request $r)
    {
        abort_unless(in_array($r->user()->role, ['developer', 'admin']), 403);
        $experts = DB::table('experts')->where('user_id', $r->user()->id)->latest()->paginate(20);

        return view('developer', compact('experts'));
    }

    public function upload(Request $r)
    {
        abort_unless(in_array($r->user()->role, ['developer', 'admin']), 403);
        $d = $r->validate(['name' => 'required|string|max:120', 'description' => 'required|string|min:30|max:10000', 'strategy' => 'required|string|max:100', 'symbols' => 'required|string|max:100', 'timeframe' => 'required|string|max:30', 'risk' => 'required|in:Basso,Medio,Alto', 'version' => 'required|string|max:30', 'source' => 'required|file|max:2048', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096']);
        abort_unless(strtolower($r->file('source')->getClientOriginalExtension()) === 'mq5', 422, 'Carica un sorgente MQ5.');
        unset($d['source'],$d['image']);
        $d['source_path'] = $r->file('source')->store('sources', 'local');
        $d['image_path'] = $r->file('image')?->store('images', 'local');
        DB::table('experts')->insert($d + ['user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'EA ricevuto: in attesa di revisione.');
    }

    public function image(int $id)
    {
        $e = DB::table('experts')->where('id', $id)->where('status', 'published')->firstOrFail();
        abort_unless($e->image_path && Storage::disk('local')->exists($e->image_path), 404);

        return Storage::disk('local')->response($e->image_path);
    }

    public function admin(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return view('admin', ['users' => DB::table('users')->latest('id')->paginate(30), 'accounts' => DB::table('broker_accounts')->where('status', 'pending')->limit(50)->get(), 'experts' => DB::table('experts')->where('status', 'pending')->limit(50)->get()]);
    }

    public function source(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $e = DB::table('experts')->where('id', $id)->firstOrFail();

        return Storage::disk('local')->download($e->source_path, 'review-'.$id.'.mq5');
    }

    public function moderate(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['type' => 'required|in:role,account,expert', 'id' => 'required|integer', 'value' => 'required|string']);
        DB::transaction(function () use ($r, $d) {
            [$table,$field,$allowed] = match ($d['type']) {
                'role' => ['users', 'role', ['user', 'developer']],'account' => ['broker_accounts', 'status', ['verified', 'rejected']],'expert' => ['experts', 'status', ['published', 'rejected']]
            };
            abort_unless(in_array($d['value'], $allowed), 422);
            $row = DB::table($table)->where('id', $d['id'])->lockForUpdate()->firstOrFail();
            abort_if($d['type'] === 'role' && $row->role === 'admin', 403);
            $change = [$field => $d['value'], 'updated_at' => now()];
            if ($d['type'] === 'account') {
                $change['verified_by'] = $r->user()->id;
            }
            DB::table($table)->where('id', $d['id'])->update($change);
            DB::table('audit_logs')->insert(['user_id' => $r->user()->id, 'action' => $d['value'], 'subject' => $table.':'.$d['id'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Operazione registrata.');
    }
}
