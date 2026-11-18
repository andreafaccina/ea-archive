<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrokerController extends Controller
{
    public function index(): View
    {
        $brokers = DB::table('brokers')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->paginate(12);

        return view('brokers.index', compact('brokers'));
    }

    public function image(Request $request, int $broker): StreamedResponse
    {
        $query = DB::table('brokers')->where('id', $broker);
        if ($request->user()->role !== 'admin') {
            $query->where('is_active', true);
        }
        $record = $query->firstOrFail();
        abort_unless(Storage::disk('local')->exists($record->image_path), 404);

        return Storage::disk('local')->response($record->image_path);
    }

    public function admin(Request $request): View
    {
        $this->authorizeAdmin($request);
        $brokers = DB::table('brokers')->orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('brokers.admin', compact('brokers'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('brokers.form', ['broker' => null]);
    }

    public function edit(Request $request, int $broker): View
    {
        $this->authorizeAdmin($request);

        return view('brokers.form', ['broker' => DB::table('brokers')->where('id', $broker)->firstOrFail()]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $broker): RedirectResponse
    {
        return $this->save($request, $broker);
    }

    private function save(Request $request, ?int $broker = null): RedirectResponse
    {
        $this->authorizeAdmin($request);
        if ($broker !== null) {
            DB::table('brokers')->where('id', $broker)->firstOrFail();
        }
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'required|string|max:10000',
            'affiliate_url' => ['required', 'string', 'url:https', 'max:2048', function ($attribute, $value, $fail) {
                if (parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null) {
                    $fail(__('Il link non deve contenere credenziali.'));
                }
            }],
            'image' => [$broker === null ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:1000000',
        ]);
        unset($data['image']);
        $newImage = $request->file('image')?->store('broker-images', 'local');
        $oldImage = null;
        try {
            DB::transaction(function () use ($request, $broker, $data, $newImage, &$oldImage) {
                if ($broker !== null) {
                    $existing = DB::table('brokers')->where('id', $broker)->lockForUpdate()->firstOrFail();
                    $oldImage = $existing->image_path;
                }
                if ($newImage !== null) {
                    $data['image_path'] = $newImage;
                }
                $data['updated_at'] = now();
                if ($broker === null) {
                    $data['created_at'] = now();
                    $id = DB::table('brokers')->insertGetId($data);
                } else {
                    DB::table('brokers')->where('id', $broker)->update($data);
                    $id = $broker;
                }
                $this->audit($request, $broker === null ? 'broker.created' : 'broker.updated', $id);
            });
        } catch (\Throwable $exception) {
            if ($newImage !== null) {
                Storage::disk('local')->delete($newImage);
            }
            throw $exception;
        }
        if ($newImage !== null && $oldImage !== null) {
            Storage::disk('local')->delete($oldImage);
        }

        return redirect()->route('admin.brokers.index')->with('success', __('Broker salvato.'));
    }

    public function destroy(Request $request, int $broker): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $image = DB::transaction(function () use ($request, $broker) {
            $record = DB::table('brokers')->where('id', $broker)->lockForUpdate()->firstOrFail();
            DB::table('brokers')->where('id', $broker)->delete();
            $this->audit($request, 'broker.deleted', $broker);

            return $record->image_path;
        });
        Storage::disk('local')->delete($image);

        return redirect()->route('admin.brokers.index')->with('success', __('Broker eliminato.'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    private function audit(Request $request, string $action, int $broker): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'subject' => 'brokers:'.$broker, 'created_at' => now(), 'updated_at' => now()]);
    }
}
