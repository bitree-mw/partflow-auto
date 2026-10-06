<?php

namespace Tests\Feature;

use App\Mail\TestEmailMail;
use App\Models\Role;
use App\Models\User;
use App\Services\MailDiagnosticsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SettingsEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_send_a_test_email_immediately(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->actingAs($this->userWithPermissions(['*']))
            ->post(route('web.settings.test-email'), ['test_email_recipient' => 'owner@example.test'])
            ->assertRedirect(route('web.settings.index').'#company-profile')
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'owner@example.test'));

        Mail::assertSent(TestEmailMail::class, fn (TestEmailMail $mail) => $mail->hasTo('owner@example.test'));
        Mail::assertNothingQueued();
    }

    public function test_mail_server_error_is_reported_to_the_administrator(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.password' => 'super-secret-pass']);
        Mail::shouldReceive('to')->andThrow(new TransportException(
            'Failed to authenticate on SMTP server with username "owner@example.test" using password super-secret-pass: 535 Incorrect authentication data'
        ));

        $response = $this->actingAs($this->userWithPermissions(['*']))
            ->post(route('web.settings.test-email'), ['test_email_recipient' => 'owner@example.test'])
            ->assertRedirect(route('web.settings.index').'#company-profile')
            ->assertSessionHasInput('test_email_recipient', 'owner@example.test');

        $message = session('error');
        $this->assertStringContainsString('Test email failed.', $message);
        $this->assertStringContainsString('rejected the username or password', $message);
        $this->assertStringContainsString('535 Incorrect authentication data', $message);
        $this->assertStringNotContainsString('super-secret-pass', $message);
        $response->assertSessionMissing('success');
    }

    public function test_non_delivering_mailer_is_reported_instead_of_claiming_success(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $this->actingAs($this->userWithPermissions(['*']))
            ->post(route('web.settings.test-email'), ['test_email_recipient' => 'owner@example.test'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'MAIL_MAILER=smtp'));

        Mail::assertNothingSent();
    }

    public function test_test_email_requires_a_valid_address_and_settings_permission(): void
    {
        Mail::fake();

        $this->actingAs($this->userWithPermissions(['*']))
            ->post(route('web.settings.test-email'), ['test_email_recipient' => 'not-an-email'])
            ->assertSessionHasErrors('test_email_recipient');

        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->post(route('web.settings.test-email'), ['test_email_recipient' => 'owner@example.test'])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_settings_page_shows_email_delivery_status(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.example.test',
            'mail.mailers.smtp.port' => 465,
            'queue.default' => 'database',
            'hosting.cron_queue' => true,
        ]);

        $admin = $this->userWithPermissions(['*']);

        $this->actingAs($admin)->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('Send test email')
            ->assertSee('form="settings-test-email-form"', false)
            ->assertSee('via mail.example.test:465')
            ->assertSee('No cron run has been recorded')
            ->assertSee('0 waiting, 0 failed.');

        app(MailDiagnosticsService::class)->recordSchedulerHeartbeat();

        $this->actingAs($admin)->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('Running');
    }

    public function test_scheduler_records_a_heartbeat_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === 'scheduler-heartbeat');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_public_uploads_are_served_without_the_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'png-bytes');

        $this->get('/storage/branding/logo.png')->assertOk();
        $this->get('/storage/branding/missing.png')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();

        // Laravel's paired upload route must stay closed without a signed URL.
        $this->withoutExceptionHandling();

        try {
            $this->put('/storage/branding/upload.png', ['file' => 'x']);
            $this->fail('Unsigned uploads to the public disk must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Storage::disk('public')->assertMissing('branding/upload.png');
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Email Settings Role '.Role::query()->count(),
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
