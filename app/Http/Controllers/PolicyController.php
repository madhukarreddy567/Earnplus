<?php

namespace App\Http\Controllers;

use App\Models\PolicyPage;
use Illuminate\View\View;

/**
 * Public policy pages: render the latest *published* version with
 * SEO meta. Unpublished pages return 404.
 */
class PolicyController extends Controller
{
    public function show(string $slug): View
    {
        abort_unless(in_array($slug, PolicyPage::SLUGS, true), 404);

        $page = PolicyPage::published($slug);

        abort_if($page === null, 404);

        return view('policies.show', ['page' => $page]);
    }
}
