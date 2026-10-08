<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branding, media & design settings (Phase 8).
 *
 * Media tab: logo, banner, favicon, background, email header and default
 * avatar uploads — processed by BrandingService, paths in DB settings.
 * Design tab: colors, hero gradient, font scale, button shape, light/dark
 * theme, background style and the master animations toggle. Everything is
 * emitted as CSS variables from the layout, so changes apply instantly.
 */
class BrandingController extends Controller
{
    /**
     * Branding page (media + design tabs, with live preview).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'media');

        if (! in_array($tab, ['media', 'design'], true)) {
            $tab = 'media';
        }

        return view('admin.branding.index', [
            'tab' => $tab,
            'types' => BrandingService::TYPES,
            'design' => $this->designValues(),
        ]);
    }

    /**
     * Upload + process one branding asset.
     */
    public function storeMedia(Request $request, BrandingService $branding): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(BrandingService::TYPES))],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . BrandingService::MAX_KB],
        ]);

        try {
            $branding->store($validated['file'], $validated['type']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.branding.index', ['tab' => 'media'])
                ->with('error', 'Could not process that image. Please try a different file.');
        }

        $label = BrandingService::TYPES[$validated['type']]['label'];

        return redirect()
            ->route('admin.branding.index', ['tab' => 'media'])
            ->with('success', "{$label} updated.");
    }

    /**
     * Remove one branding asset (files + settings) — the site falls back
     * to its built-in defaults.
     */
    public function destroyMedia(string $type, BrandingService $branding): RedirectResponse
    {
        if (! isset(BrandingService::TYPES[$type])) {
            abort(404);
        }

        $branding->delete($type);

        $label = BrandingService::TYPES[$type]['label'];

        return redirect()
            ->route('admin.branding.index', ['tab' => 'media'])
            ->with('success', "{$label} removed — the default is showing again.");
    }

    /**
     * Save the design settings (colors, scale, shape, theme, toggles).
     */
    public function storeDesign(Request $request): RedirectResponse
    {
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $validated = $request->validate([
            'design_primary' => $hex,
            'design_accent' => $hex,
            'design_hero_from' => $hex,
            'design_hero_to' => $hex,
            'font_scale' => ['required', 'in:90,100,110'],
            'button_shape' => ['required', 'in:rounded,pill,square'],
            'theme' => ['required', 'in:light,dark'],
            'background_style' => ['required', 'in:cover,blur'],
            'animations_enabled' => ['nullable', 'boolean'],
        ]);

        foreach (['design_primary', 'design_accent', 'design_hero_from', 'design_hero_to'] as $key) {
            if (! empty($validated[$key])) {
                \App\Models\Setting::set($key, strtolower($validated[$key]), 'design');
            }
        }

        \App\Models\Setting::set('font_scale', $validated['font_scale'], 'design');
        \App\Models\Setting::set('button_shape', $validated['button_shape'], 'design');
        \App\Models\Setting::set('theme', $validated['theme'], 'design');
        \App\Models\Setting::set('background_style', $validated['background_style'], 'design');
        \App\Models\Setting::set('animations_enabled', $request->boolean('animations_enabled') ? '1' : '0', 'design');

        return redirect()
            ->route('admin.branding.index', ['tab' => 'design'])
            ->with('success', 'Design settings saved — they apply instantly across the site.');
    }

    /**
     * Current design values for the form (DB settings with safe fallbacks).
     *
     * @return array<string, string>
     */
    protected function designValues(): array
    {
        return [
            'design_primary' => design_color('design_primary', '#0e9f6e'),
            'design_accent' => design_color('design_accent', '#0ea5e9'),
            'design_hero_from' => design_color('design_hero_from', '#0e9f6e'),
            'design_hero_to' => design_color('design_hero_to', '#075e43'),
            'font_scale' => (string) setting_int('font_scale', 100),
            'button_shape' => (string) setting('button_shape', 'pill'),
            'theme' => (string) setting('theme', 'light'),
            'background_style' => (string) setting('background_style', 'cover'),
            'animations_enabled' => setting_bool('animations_enabled', true) ? '1' : '0',
        ];
    }
}
