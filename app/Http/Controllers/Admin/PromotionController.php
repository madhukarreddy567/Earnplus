<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfferwallProvider;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin promotion management: date-based festival promotions with
 * coin multipliers. List is grouped Active / Upcoming / Expired
 * (+ Disabled); overlapping same-scope promotions only warn.
 */
class PromotionController extends Controller
{
    public function index(): View
    {
        $promotions = Promotion::with('provider')->orderBy('priority')->orderBy('starts_at')->get();

        $grouped = [
            'active' => [],
            'upcoming' => [],
            'expired' => [],
            'disabled' => [],
        ];

        foreach ($promotions as $promotion) {
            $grouped[$promotion->status()][] = $promotion;
        }

        return view('admin.promotions.index', ['grouped' => $grouped]);
    }

    public function create(): View
    {
        return view('admin.promotions.form', [
            'promotion' => new Promotion([
                'enabled' => true,
                'multiplier' => 2.00,
                'scope' => Promotion::SCOPE_GLOBAL,
                'priority' => 0,
            ]),
            'providers' => OfferwallProvider::orderBy('name')->get(),
            'overlapWarning' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'] ?? null);
        $data['created_by'] = $request->user('admin')->id;

        $promotion = Promotion::create($data);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion “{$promotion->name}” created.")
            ->with('warning', $this->overlapWarning($promotion));
    }

    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.form', [
            'promotion' => $promotion,
            'providers' => OfferwallProvider::orderBy('name')->get(),
            'overlapWarning' => $this->overlapWarning($promotion),
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $data = $this->validated($request, $promotion);

        // Keep the current slug unless the admin typed a new one —
        // regenerating it on every save would break saved URLs.
        if ($request->filled('slug')) {
            $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'], $promotion);
        } else {
            unset($data['slug']);
        }

        $promotion->update($data);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion “{$promotion->name}” updated.")
            ->with('warning', $this->overlapWarning($promotion));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $name = $promotion->name;
        $promotion->delete();

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion “{$name}” deleted.");
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['enabled' => ! $promotion->enabled]);

        $state = $promotion->enabled ? 'enabled' : 'disabled';

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion “{$promotion->name}” {$state}.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Promotion $promotion = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9-]+$/'],
            'enabled' => ['nullable', 'boolean'],
            'multiplier' => ['required', 'numeric', 'min:1.1', 'max:10'],
            'scope' => ['required', 'string', 'in:' . implode(',', Promotion::SCOPES)],
            'provider_id' => ['nullable', 'integer', 'exists:offerwall_providers,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'banner_title' => ['nullable', 'string', 'max:160'],
            'banner_subtitle' => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:16'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['priority'] = (int) ($data['priority'] ?? 0);

        // A provider only narrows task_offerwall promotions.
        if (($data['scope'] ?? null) !== Promotion::SCOPE_TASK_OFFERWALL) {
            $data['provider_id'] = null;
        }

        return $data;
    }

    protected function uniqueSlug(string $name, ?string $wanted, ?Promotion $ignore = null): string
    {
        $base = $wanted !== null && $wanted !== ''
            ? Str::slug($wanted)
            : Str::slug($name);

        $base = $base !== '' ? $base : 'promotion';
        $slug = $base;
        $i = 2;

        while (
            Promotion::where('slug', $slug)
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Warn (never block) when another promotion with an overlapping
     * scope runs during any part of this promotion's window.
     */
    protected function overlapWarning(Promotion $promotion): ?string
    {
        $overlap = Promotion::query()
            ->where('id', '!=', $promotion->id)
            ->where('enabled', true)
            ->where('starts_at', '<=', $promotion->ends_at)
            ->where('ends_at', '>=', $promotion->starts_at)
            ->where(function ($q) use ($promotion) {
                $q->whereIn('scope', [Promotion::SCOPE_GLOBAL, $promotion->scope]);
            })
            ->pluck('name')
            ->unique()
            ->values();

        if ($overlap->isEmpty()) {
            return null;
        }

        return 'Heads up: this overlaps with ' . $overlap->implode(', ')
            . ' — stacking rules decide the final payout.';
    }
}
