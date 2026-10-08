<?php

namespace Database\Seeders;

use App\Models\PolicyPage;
use Illuminate\Database\Seeder;

/**
 * Seed the four policy pages with clearly-marked placeholder content
 * (published). The owner edits every word from /admin/policies.
 */
class PolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            PolicyPage::SLUG_TERMS => <<<'HTML'
<h2>Terms of Service — DRAFT</h2>
<p><strong>This is placeholder content. Please replace it with your real terms before launch.</strong></p>
<p>Welcome to EarnPlus. By creating an account and using our app, you agree to the following:</p>
<ol>
<li><strong>Earning coins:</strong> Coins are earned by completing tasks, watching ads, spinning the wheel, daily check-ins and referrals. Coins have no cash value until redeemed through an approved withdrawal method.</li>
<li><strong>Fair use:</strong> One account per person. Using VPNs, emulators, bots or any automation to earn coins is fraud and will lead to a permanent ban with forfeiture of the balance.</li>
<li><strong>Withdrawals:</strong> Withdrawals are processed to the payout account you provide. We may ask for identity verification before the first payout. Fraudulent activity voids pending withdrawals.</li>
<li><strong>Changes:</strong> We may update these terms at any time. Continued use of the app means you accept the current version.</li>
</ol>
<p>Contact us at the support e-mail listed in the app with any questions.</p>
HTML,
            PolicyPage::SLUG_PRIVACY => <<<'HTML'
<h2>Privacy Policy — DRAFT</h2>
<p><strong>This is placeholder content. Please replace it with your real privacy policy before launch.</strong></p>
<p>EarnPlus collects the minimum data needed to run the service:</p>
<ul>
<li><strong>Account data:</strong> your name, e-mail address and (optionally) mobile number — used for sign-in, verification and payouts.</li>
<li><strong>Earning activity:</strong> tasks completed, ads viewed and coins earned — used to credit your wallet and detect fraud.</li>
<li><strong>Device data:</strong> device type and IP address — used for fraud prevention and to show you compatible tasks.</li>
</ul>
<p>We never sell your personal data. Payout partners (UPI, Paytm, banks, PayPal) receive only the details needed to send your money. You may request deletion of your account and data at any time by contacting support.</p>
HTML,
            PolicyPage::SLUG_REFUND => <<<'HTML'
<h2>Refund Policy — DRAFT</h2>
<p><strong>This is placeholder content. Please replace it with your real refund policy before launch.</strong></p>
<p>EarnPlus is a free rewards app — there are no purchases, so there is nothing to refund.</p>
<ul>
<li>Coins credited by mistake or through fraud are removed without notice.</li>
<li>Rejected withdrawals return the coins to your wallet automatically.</li>
<li>If a completed payout did not reach your account, contact support with your withdrawal ID and we will investigate.</li>
</ul>
HTML,
            PolicyPage::SLUG_ABOUT => <<<'HTML'
<h2>About EarnPlus — DRAFT</h2>
<p><strong>This is placeholder content. Please replace it with your real story before launch.</strong></p>
<p>EarnPlus is a rewards app that lets you earn coins by completing simple tasks, watching ads, spinning the daily wheel and inviting friends — then redeem those coins for real payouts via UPI, Paytm, bank transfer or PayPal.</p>
<p>Our mission is simple: make every spare minute rewarding.</p>
HTML,
        ];

        foreach ($pages as $slug => $body) {
            // Idempotent: never touch an existing page (re-seeding must
            // not bump versions or archive the owner's edits).
            if (PolicyPage::where('slug', $slug)->exists()) {
                continue;
            }

            $page = new PolicyPage(['slug' => $slug]);
            $page->title = PolicyPage::TITLES[$slug];
            $page->saveNewVersion($body, true);
        }
    }
}
