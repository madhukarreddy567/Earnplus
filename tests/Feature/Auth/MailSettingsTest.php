<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Services\MailSettings;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_empty_credentials_fall_back_to_log_driver(): void
    {
        Setting::set('mail_username', '');

        MailSettings::apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_configured_credentials_switch_to_smtp(): void
    {
        Setting::set('mail_username', 'earnplus@gmail.com');
        Setting::set('mail_password', 'app-password-here');

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('earnplus@gmail.com', config('mail.mailers.smtp.username'));
        $this->assertSame('app-password-here', config('mail.mailers.smtp.password'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
    }

    public function test_from_address_defaults_to_smtp_username(): void
    {
        Setting::set('mail_username', 'earnplus@gmail.com');
        Setting::set('mail_password', 'app-password-here');
        Setting::set('mail_from_address', '');

        MailSettings::apply();

        $this->assertSame('earnplus@gmail.com', config('mail.from.address'));
        $this->assertSame('EarnPlus', config('mail.from.name'));
    }

    public function test_seeder_installs_gmail_smtp_defaults_with_empty_credentials(): void
    {
        $this->assertSame('smtp.gmail.com', setting('mail_host'));
        $this->assertSame(587, setting_int('mail_port'));
        $this->assertSame('', setting('mail_username'));
        $this->assertSame('', setting('mail_password'));
        $this->assertSame('tls', setting('mail_encryption'));
    }
}
