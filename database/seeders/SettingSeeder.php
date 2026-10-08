<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed the default EarnPlus settings.
     * Every toggle lives in the DB — nothing is hardcoded in the app.
     */
    public function run(): void
    {
        $defaults = [
            // Branding
            ['key' => 'site_name', 'value' => 'EarnPlus', 'group' => 'branding'],
            ['key' => 'site_tagline', 'value' => 'Earn coins. Redeem rewards.', 'group' => 'branding'],

            // Coin economy
            ['key' => 'coins_per_rupee', 'value' => '100', 'group' => 'coins'],
            ['key' => 'signup_bonus_coins', 'value' => '50', 'group' => 'coins'],
            ['key' => 'daily_checkin_coins', 'value' => '10', 'group' => 'coins'],
            ['key' => 'daily_checkin_streak_bonus', 'value' => '50', 'group' => 'coins'],
            ['key' => 'daily_checkin_streak_days', 'value' => '7', 'group' => 'coins'],
            ['key' => 'referral_bonus_coins', 'value' => '25', 'group' => 'coins'],

            // Spin wheel
            ['key' => 'spin_min_coins', 'value' => '5', 'group' => 'spin'],
            ['key' => 'spin_max_coins', 'value' => '100', 'group' => 'spin'],
            ['key' => 'spin_daily_limit', 'value' => '1', 'group' => 'spin'],
            ['key' => 'spin_win_probability', 'value' => '40', 'group' => 'spin'],
            ['key' => 'spin_ad_gate_enabled', 'value' => '1', 'group' => 'spin'],
            ['key' => 'spin_claim_expiry_minutes', 'value' => '15', 'group' => 'spin'],

            // Dashboard banner (placeholder creative until Phase 8 branding)
            ['key' => 'banner_enabled', 'value' => '1', 'group' => 'branding'],
            ['key' => 'banner_title', 'value' => 'Invite friends, earn together!', 'group' => 'branding'],
            ['key' => 'banner_subtitle', 'value' => 'Share your referral code — you both earn bonus coins.', 'group' => 'branding'],

            // Feature toggles
            ['key' => 'otp_enabled', 'value' => '0', 'group' => 'features'],
            ['key' => 'recaptcha_enabled', 'value' => '0', 'group' => 'features'],
            ['key' => 'spin_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'daily_checkin_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'referral_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'withdrawals_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'features'],

            // E-mail/password auth: OFF by default — Google is the only
            // sign-in. Flip on in the admin panel to restore e-mail auth.
            ['key' => 'email_auth_enabled', 'value' => '0', 'group' => 'features'],

            // Security (Phase 10)
            ['key' => 'trust_proxy_headers', 'value' => '0', 'group' => 'security'],
            ['key' => 'trusted_proxy_cidrs', 'value' => "173.245.48.0/20\n103.21.244.0/22\n103.22.200.0/22\n103.31.4.0/22\n141.101.64.0/18\n108.162.192.0/18\n190.93.240.0/20\n188.114.96.0/20\n197.234.240.0/22\n198.41.128.0/17\n162.158.0.0/15\n104.16.0.0/13\n104.24.0.0/14\n172.64.0.0/13\n131.0.72.0/22\n2400:cb00::/32\n2606:4700::/32\n2803:f800::/32\n2405:b500::/32\n2405:8100::/32\n2a06:98c0::/29\n2c0f:f248::/32", 'group' => 'security'],
            ['key' => 'security_ip_whitelist', 'value' => '', 'group' => 'security'],
            ['key' => 'hsts_enabled', 'value' => '0', 'group' => 'security'],

            // Google OAuth ("Continue with Google"). Empty = the feature is
            // inert and the buttons hide. The owner creates these in the
            // Google Cloud Console (see README).
            ['key' => 'google_client_id', 'value' => '', 'group' => 'auth'],
            ['key' => 'google_client_secret', 'value' => '', 'group' => 'auth'],

            // Withdrawals
            ['key' => 'min_withdrawal_rupees', 'value' => '10', 'group' => 'withdrawals'],
            ['key' => 'usd_per_rupee', 'value' => '0.012', 'group' => 'withdrawals'],
            // Max withdrawal requests per user per day.
            ['key' => 'withdrawal_max_per_day', 'value' => '3', 'group' => 'withdrawals'],
            // Optional tax deducted from payouts. Off by default.
            ['key' => 'withdrawal_tax_enabled', 'value' => '0', 'group' => 'withdrawals'],
            ['key' => 'withdrawal_tax_percent', 'value' => '0', 'group' => 'withdrawals'],

            // Mail (Gmail SMTP defaults; credentials EMPTY by default so
            // mails are logged instead of failing — admin pastes a Gmail
            // app password into these settings later)
            ['key' => 'mail_host', 'value' => 'smtp.gmail.com', 'group' => 'mail'],
            ['key' => 'mail_port', 'value' => '587', 'group' => 'mail'],
            ['key' => 'mail_username', 'value' => '', 'group' => 'mail'],
            ['key' => 'mail_password', 'value' => '', 'group' => 'mail'],
            ['key' => 'mail_encryption', 'value' => 'tls', 'group' => 'mail'],
            ['key' => 'mail_from_address', 'value' => '', 'group' => 'mail'],
            ['key' => 'mail_from_name', 'value' => 'EarnPlus', 'group' => 'mail'],

            // reCAPTCHA v2 (keys empty = widget never renders until configured)
            ['key' => 'recaptcha_site_key', 'value' => '', 'group' => 'security'],
            ['key' => 'recaptcha_secret_key', 'value' => '', 'group' => 'security'],

            // Ads (Phase 5)
            ['key' => 'rewarded_ad_daily_limit', 'value' => '10', 'group' => 'ads'],
            ['key' => 'rewarded_ad_countdown_seconds', 'value' => '15', 'group' => 'ads'],
            ['key' => 'rewarded_ad_min_interval_seconds', 'value' => '20', 'group' => 'ads'],
            ['key' => 'rewarded_ad_max_per_hour', 'value' => '5', 'group' => 'ads'],

            // Promotions (Phase 7): best_only = highest multiplier wins;
            // multiply = all stack multiplicatively, capped.
            ['key' => 'promotion_stacking', 'value' => 'best_only', 'group' => 'promotions'],
            ['key' => 'promotion_max_stacked_multiplier', 'value' => '5.0', 'group' => 'promotions'],

            // Site settings (Phase 9)
            ['key' => 'support_email', 'value' => '', 'group' => 'general'],
            ['key' => 'default_currency', 'value' => 'INR', 'group' => 'general'],
            ['key' => 'default_language', 'value' => 'en', 'group' => 'general'],
            ['key' => 'maintenance_message', 'value' => 'We are doing scheduled maintenance. Please check back soon.', 'group' => 'general'],

            // Module toggles (Phase 9)
            ['key' => 'offerwalls_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'ads_enabled', 'value' => '1', 'group' => 'features'],
            ['key' => 'promotions_enabled', 'value' => '1', 'group' => 'features'],

            // Cron system (Phase 11)
            ['key' => 'log_retention_days', 'value' => '90', 'group' => 'cron'],
            ['key' => 'payout_max_attempts', 'value' => '5', 'group' => 'cron'],
            ['key' => 'payout_retry_delay_minutes', 'value' => '60', 'group' => 'cron'],
        ];

        foreach ($defaults as $row) {
            Setting::set($row['key'], $row['value'], $row['group']);
        }
    }
}
