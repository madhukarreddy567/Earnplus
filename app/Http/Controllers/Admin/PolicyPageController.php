<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rich-text policy pages (Phase 9): terms, privacy, refund, about.
 * Admin edits HTML through a local WYSIWYG editor; the server
 * sanitizes it, bumps the version and keeps a revision history.
 */
class PolicyPageController extends Controller
{
    public function index(): View
    {
        $pages = PolicyPage::query()
            ->orderByRaw('FIELD(slug, "terms", "privacy", "refund", "about")')
            ->get();

        return view('admin.policies.index', compact('pages'));
    }

    public function edit(PolicyPage $policy): View
    {
        return view('admin.policies.edit', [
            'policy' => $policy,
            'revisions' => $policy->revisions()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, PolicyPage $policy): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body_html' => ['required', 'string', 'max:200000'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $policy->title = $validated['title'];
        $policy->saveNewVersion($validated['body_html'], $request->boolean('publish'));

        return redirect()
            ->route('admin.policies.edit', $policy)
            ->with('status', 'Saved as version ' . $policy->version
                . ($policy->is_published ? ' and published.' : ' (draft — not visible publicly).'));
    }

    /**
     * Roll back to a previous revision: saves it as a new version.
     */
    public function restore(PolicyPage $policy, int $revision): RedirectResponse
    {
        $rev = $policy->revisions()->findOrFail($revision);

        $policy->saveNewVersion($rev->body_html, $policy->is_published);

        return redirect()
            ->route('admin.policies.edit', $policy)
            ->with('status', 'Restored version ' . $rev->version . ' as new version ' . $policy->version . '.');
    }
}
