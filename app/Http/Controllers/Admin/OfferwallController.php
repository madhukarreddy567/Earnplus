<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfferwallClick;
use App\Models\OfferwallConversion;
use App\Models\OfferwallProvider;
use App\Services\PostbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin offerwall management: provider CRUD, per-provider stats,
 * conversion log with filters, and manual credit/reject of
 * pending conversions (idempotent via PostbackService).
 */
class OfferwallController extends Controller
{
    public function __construct(protected PostbackService $postbacks)
    {
    }

    public function index(): View
    {
        $providers = OfferwallProvider::orderBy('name')->get();

        $stats = [];
        foreach ($providers as $provider) {
            $stats[$provider->id] = [
                'clicks' => OfferwallClick::where('provider_id', $provider->id)->count(),
                'conversions' => OfferwallConversion::where('provider_id', $provider->id)
                    ->where('status', OfferwallConversion::STATUS_CREDITED)->count(),
                'coins_paid' => (int) OfferwallConversion::where('provider_id', $provider->id)
                    ->where('status', OfferwallConversion::STATUS_CREDITED)->sum('user_coins'),
                'pending' => OfferwallConversion::where('provider_id', $provider->id)
                    ->where('status', OfferwallConversion::STATUS_PENDING)->count(),
            ];
        }

        return view('admin.offerwalls.index', [
            'providers' => $providers,
            'stats' => $stats,
        ]);
    }

    public function create(): View
    {
        return view('admin.offerwalls.form', [
            'provider' => new OfferwallProvider(['user_revenue_share' => 70.00]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $provider = OfferwallProvider::create(array_merge($data, [
            'postback_secret' => OfferwallProvider::generateSecret(),
        ]));

        return redirect()->route('admin.offerwalls.edit', $provider)
            ->with('success', "Provider “{$provider->name}” created. Postback URL: " . route('postback.handle', $provider));
    }

    public function edit(OfferwallProvider $provider): View
    {
        return view('admin.offerwalls.form', ['provider' => $provider]);
    }

    public function update(Request $request, OfferwallProvider $provider): RedirectResponse
    {
        $provider->update($this->validated($request));

        return redirect()->route('admin.offerwalls.index')
            ->with('success', "Provider “{$provider->name}” updated.");
    }

    public function destroy(OfferwallProvider $provider): RedirectResponse
    {
        if ($provider->sandbox_mode) {
            return redirect()->route('admin.offerwalls.index')
                ->with('error', 'The demo sandbox provider cannot be deleted — disable it instead.');
        }

        $provider->delete();

        return redirect()->route('admin.offerwalls.index')
            ->with('success', "Provider “{$provider->name}” deleted.");
    }

    public function regenerateSecret(OfferwallProvider $provider): RedirectResponse
    {
        $provider->update(['postback_secret' => OfferwallProvider::generateSecret()]);

        return redirect()->route('admin.offerwalls.edit', $provider)
            ->with('success', 'Postback secret regenerated. Update the provider dashboard with the new secret.');
    }

    public function conversions(Request $request): View
    {
        $request->validate([
            'provider' => ['nullable', 'exists:offerwall_providers,id'],
            'status' => ['nullable', 'in:' . implode(',', OfferwallConversion::STATUSES)],
        ]);

        $conversions = OfferwallConversion::with(['provider', 'user'])
            ->when($request->query('provider'), fn ($q, $id) => $q->where('provider_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.offerwalls.conversions', [
            'conversions' => $conversions,
            'providers' => OfferwallProvider::orderBy('name')->get(),
            'statuses' => OfferwallConversion::STATUSES,
            'filters' => $request->only(['provider', 'status']),
        ]);
    }

    public function creditConversion(OfferwallConversion $conversion): RedirectResponse
    {
        $result = $this->postbacks->creditManually($conversion);

        return redirect()->route('admin.offerwalls.conversions')
            ->with('success', "Conversion #{$conversion->id}: {$result['status']}.");
    }

    public function rejectConversion(Request $request, OfferwallConversion $conversion): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $result = $this->postbacks->rejectManually($conversion, $request->input('reason'));

        return redirect()->route('admin.offerwalls.conversions')
            ->with('success', "Conversion #{$conversion->id}: {$result['status']}.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:offerwall_providers,slug,' . $request->route('provider')?->id],
            'enabled' => ['sometimes', 'boolean'],
            'user_revenue_share' => ['required', 'numeric', 'min:0', 'max:100'],
            'ip_whitelist' => ['nullable', 'string', 'max:2000'],
            'sandbox_mode' => ['sometimes', 'boolean'],
            'offer_url_template' => ['nullable', 'string', 'max:500'],
            'max_payout_coins' => ['nullable', 'integer', 'min:1'],
            'tagline' => ['nullable', 'string', 'max:255'],
        ]);

        $provider = $request->route('provider');

        $config = is_array($provider?->config) ? $provider->config : [];

        foreach (['offer_url_template', 'max_payout_coins', 'tagline'] as $key) {
            if (array_key_exists($key, $data)) {
                $config[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $data['config'] = $config;
        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['sandbox_mode'] = (bool) ($data['sandbox_mode'] ?? false);
        $data['slug'] = Str::slug($data['slug']);

        return $data;
    }
}
