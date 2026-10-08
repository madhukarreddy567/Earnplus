<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\AdNetwork;
use App\Models\AdPlacement;
use App\Models\AdReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin ad management: network CRUD, placement CRUD (type, device, pages,
 * frequency cap, priority, custom code, enable toggle), impression stats
 * per placement, and the rewarded-ad payout log.
 */
class AdController extends Controller
{
    public function index(): View
    {
        $networks = AdNetwork::withCount('placements')->orderBy('name')->get();

        $placements = AdPlacement::with('network')->orderBy('slot')->orderBy('name')->get();

        $stats = [];
        foreach ($placements as $placement) {
            $stats[$placement->id] = [
                'impressions_today' => AdImpression::where('placement_id', $placement->id)
                    ->whereDate('created_at', today())
                    ->count(),
                'impressions_total' => AdImpression::where('placement_id', $placement->id)->count(),
                'rewards_today' => AdReward::where('placement_id', $placement->id)
                    ->whereDate('created_at', today())
                    ->count(),
                'coins_paid' => (int) AdReward::where('placement_id', $placement->id)->sum('coins'),
            ];
        }

        $networkStats = [];
        foreach ($networks as $network) {
            $placementIds = $network->placements->pluck('id');
            $networkStats[$network->id] = [
                'impressions_total' => AdImpression::whereIn('placement_id', $placementIds)->count(),
                'coins_paid' => (int) AdReward::whereIn('placement_id', $placementIds)->sum('coins'),
            ];
        }

        $recentRewards = AdReward::with(['placement', 'user'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.ads.index', [
            'networks' => $networks,
            'placements' => $placements,
            'stats' => $stats,
            'networkStats' => $networkStats,
            'recentRewards' => $recentRewards,
        ]);
    }

    public function rewards(): View
    {
        $rewards = AdReward::with(['placement.network', 'user'])
            ->latest()
            ->paginate(20);

        return view('admin.ads.rewards', ['rewards' => $rewards]);
    }

    // Networks -----------------------------------------------------------

    public function createNetwork(): View
    {
        return view('admin.ads.networks.form', [
            'network' => new AdNetwork(['enabled' => false, 'type' => AdNetwork::TYPE_CUSTOM]),
        ]);
    }

    public function storeNetwork(Request $request): RedirectResponse
    {
        $network = AdNetwork::create($this->validatedNetwork($request));

        return redirect()->route('admin.ads.index')
            ->with('success', "Network “{$network->name}” created. Paste its ad code into a placement to go live.");
    }

    public function editNetwork(AdNetwork $network): View
    {
        return view('admin.ads.networks.form', ['network' => $network]);
    }

    public function updateNetwork(Request $request, AdNetwork $network): RedirectResponse
    {
        $network->update($this->validatedNetwork($request));

        return redirect()->route('admin.ads.index')
            ->with('success', "Network “{$network->name}” updated.");
    }

    public function destroyNetwork(AdNetwork $network): RedirectResponse
    {
        if ($network->placements()->exists()) {
            return redirect()->route('admin.ads.index')
                ->with('error', 'This network still has placements — delete or move them first.');
        }

        $network->delete();

        return redirect()->route('admin.ads.index')
            ->with('success', "Network “{$network->name}” deleted.");
    }

    // Placements ----------------------------------------------------------

    public function createPlacement(): View
    {
        return view('admin.ads.placements.form', [
            'placement' => new AdPlacement([
                'enabled' => true,
                'placement_type' => AdPlacement::TYPE_BANNER,
                'device' => AdPlacement::DEVICE_ALL,
                'frequency_cap_per_session' => 3,
                'priority' => 10,
                'coins' => 0,
            ]),
            'networks' => AdNetwork::orderBy('name')->get(),
            'slots' => AdPlacement::SLOTS,
        ]);
    }

    public function storePlacement(Request $request): RedirectResponse
    {
        $placement = AdPlacement::create($this->validatedPlacement($request));

        return redirect()->route('admin.ads.index')
            ->with('success', "Placement “{$placement->name}” created for slot “{$placement->slot}”.");
    }

    public function editPlacement(AdPlacement $placement): View
    {
        return view('admin.ads.placements.form', [
            'placement' => $placement,
            'networks' => AdNetwork::orderBy('name')->get(),
            'slots' => AdPlacement::SLOTS,
        ]);
    }

    public function updatePlacement(Request $request, AdPlacement $placement): RedirectResponse
    {
        $placement->update($this->validatedPlacement($request));

        return redirect()->route('admin.ads.index')
            ->with('success', "Placement “{$placement->name}” updated.");
    }

    public function destroyPlacement(AdPlacement $placement): RedirectResponse
    {
        $placement->delete();

        return redirect()->route('admin.ads.index')
            ->with('success', "Placement “{$placement->name}” deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedNetwork(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                'unique:ad_networks,slug,' . $request->route('network')?->id,
            ],
            'enabled' => ['sometimes', 'boolean'],
            'type' => ['required', 'in:' . implode(',', AdNetwork::TYPES)],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['enabled'] = (bool) ($data['enabled'] ?? false);

        // Only keep config keys declared by the network type's schema
        // (e.g. Unity Game IDs + rewarded placement ID + S2S secret).
        // Existing keys (like the seeded docs note) are preserved.
        $schema = AdNetwork::configSchema($data['type']);
        $existing = $request->route('network')?->config ?? [];
        $config = is_array($existing) ? $existing : [];
        foreach (array_keys($schema) as $key) {
            $config[$key] = (string) ($data['config'][$key] ?? '');
        }
        $data['config'] = $config;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPlacement(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                'unique:ad_placements,slug,' . $request->route('placement')?->id,
            ],
            'slot' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
            'network_id' => ['required', 'exists:ad_networks,id'],
            'enabled' => ['sometimes', 'boolean'],
            'placement_type' => ['required', 'in:' . implode(',', AdPlacement::TYPES)],
            'device' => ['required', 'in:' . implode(',', AdPlacement::DEVICES)],
            'pages' => ['nullable', 'string', 'max:1000'],
            'frequency_cap_per_session' => ['required', 'integer', 'min:1', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'coins' => ['required', 'integer', 'min:0', 'max:100000'],
            'custom_code' => ['nullable', 'string', 'max:20000'],
        ]);

        $data['slug'] = Str::slug($data['slug']);
        $data['slot'] = Str::slug($data['slot']);
        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['pages'] = $this->parsePages($data['pages'] ?? null);

        return $data;
    }

    /**
     * "dashboard, tasks" → ["dashboard", "tasks"]; empty → null (all pages).
     *
     * @return list<string>|null
     */
    protected function parsePages(?string $pages): ?array
    {
        if ($pages === null || trim($pages) === '') {
            return null;
        }

        $list = array_values(array_filter(array_map(
            fn ($p) => Str::slug(trim($p)),
            explode(',', $pages)
        )));

        return $list === [] ? null : $list;
    }
}
