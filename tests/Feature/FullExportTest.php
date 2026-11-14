<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;
use ZipArchive;

class FullExportTest extends TestCase
{
    use FinalBookingFixtures, RefreshDatabase;

    public function test_full_export_is_super_admin_only(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['agent'])->get('/exports/full')->assertForbidden();

        $response = $this->actingAs($fixture['admin'])->get('/exports/full')->assertOk()
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $zipPath = tempnam(sys_get_temp_dir(), 'estateflow-test-export-');
        file_put_contents($zipPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($zipPath);

        $this->assertContains('customers.csv', $names);
        $this->assertContains('bookings.csv', $names);
        $this->assertContains('users.csv', $names);
        $this->assertNotContains('sessions.csv', $names);
        $this->assertNotContains('password_reset_tokens.csv', $names);
        $this->assertNotContains('migrations.csv', $names);
    }

    public function test_full_export_csv_contains_seeded_customer(): void
    {
        $fixture = $this->fixture();

        $response = $this->actingAs($fixture['admin'])->get('/exports/full')->assertOk();

        $zipPath = tempnam(sys_get_temp_dir(), 'estateflow-test-export-');
        file_put_contents($zipPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $customers = $zip->getFromName('customers.csv');
        $zip->close();
        @unlink($zipPath);

        $this->assertNotFalse($customers);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $customers);
        $this->assertStringContainsString('DEMO Booking purchaser', $customers);
    }
}
