<?php

namespace Tests\Feature\AdminPanel;

use App\Models\PolicyPage;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PolicyPageSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9: rich-text policy pages — admin CRUD, version history,
 * sanitization, public rendering and auth gates.
 */
class PolicyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(PolicyPageSeeder::class);
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);
    }

    /** @test */
    public function guests_cannot_touch_policy_admin(): void
    {
        $page = PolicyPage::where('slug', 'terms')->firstOrFail();

        $this->get('/admin/policies')->assertRedirect(route('admin.login'));
        $this->get('/admin/policies/' . $page->id)->assertRedirect(route('admin.login'));
        $this->put('/admin/policies/' . $page->id, [])->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function admin_lists_and_edits_pages(): void
    {
        $this->loginAsAdmin();

        $this->get('/admin/policies')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Privacy Policy');

        $page = PolicyPage::where('slug', 'terms')->firstOrFail();
        $this->get('/admin/policies/' . $page->id)->assertOk()->assertSee('Version history');
    }

    /** @test */
    public function update_bumps_version_and_archives_revision(): void
    {
        $this->loginAsAdmin();
        $page = PolicyPage::where('slug', 'terms')->firstOrFail();

        $this->put('/admin/policies/' . $page->id, [
            'title' => 'Terms of Service',
            'body_html' => '<p>New <strong>terms</strong> text.</p>',
            'publish' => '1',
        ])->assertRedirect();

        $page->refresh();

        $this->assertSame(2, $page->version);
        $this->assertTrue($page->is_published);
        $this->assertStringContainsString('<strong>terms</strong>', $page->body_html);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame(1, $page->revisions()->first()->version);
    }

    /** @test */
    public function scripts_and_javascript_urls_are_stripped_on_save(): void
    {
        $this->loginAsAdmin();
        $page = PolicyPage::where('slug', 'privacy')->firstOrFail();

        $this->put('/admin/policies/' . $page->id, [
            'title' => 'Privacy Policy',
            'body_html' => '<p>Hello</p><script>alert("xss")</script><a href="javascript:alert(1)">click</a><a href="https://example.com">ok</a>',
            'publish' => '1',
        ])->assertRedirect();

        $page->refresh();

        $this->assertStringNotContainsString('<script>', $page->body_html);
        $this->assertStringNotContainsString('javascript:', $page->body_html);
        $this->assertStringContainsString('https://example.com', $page->body_html);
        $this->assertStringContainsString('<p>Hello</p>', $page->body_html);
    }

    /** @test */
    public function restore_rolls_back_to_an_earlier_revision(): void
    {
        $this->loginAsAdmin();
        $page = PolicyPage::where('slug', 'about')->firstOrFail();

        $this->put('/admin/policies/' . $page->id, [
            'title' => 'About Us',
            'body_html' => '<p>Version two.</p>',
            'publish' => '1',
        ]);

        $page->refresh();
        $revision = $page->revisions()->firstOrFail();

        $this->post('/admin/policies/' . $page->id . '/restore/' . $revision->id)
            ->assertRedirect();

        $page->refresh();

        $this->assertSame(3, $page->version);
        $this->assertStringContainsString('placeholder content', $page->body_html);
    }

    /** @test */
    public function public_pages_render_the_published_version_with_seo_meta(): void
    {
        $response = $this->get('/policies/terms');

        $response->assertOk();
        $response->assertSee('Terms of Service');
        $response->assertSee('name="description"', false);
        $response->assertSee('og:title', false);
    }

    /** @test */
    public function unknown_slugs_and_unpublished_pages_404(): void
    {
        $this->get('/policies/nope')->assertNotFound();

        $page = PolicyPage::where('slug', 'refund')->firstOrFail();
        $page->update(['is_published' => false]);

        $this->get('/policies/refund')->assertNotFound();
    }
}
