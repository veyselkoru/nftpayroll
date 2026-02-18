<?php

namespace Tests\Feature;

use App\Mail\CompanyOwnerWelcomeMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CompanyOwnerAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_creation_creates_owner_user_and_sends_welcome_email(): void
    {
        Mail::fake();

        $creator = User::factory()->create([
            'role' => User::ROLE_COMPANY_OWNER,
        ]);

        $response = $this->actingAs($creator)->postJson('/api/companies', [
            'name' => 'New Co',
            'contact_email' => 'new-owner@example.com',
            'owner_name' => 'New Owner',
        ])->assertCreated();

        $companyId = $response->json('company.id');
        $ownerUserId = $response->json('owner_user_id');

        $company = Company::findOrFail($companyId);
        $owner = User::findOrFail($ownerUserId);

        $this->assertSame((int) $owner->id, (int) $company->owner_id);
        $this->assertSame((int) $company->id, (int) $owner->company_id);
        $this->assertSame(User::ROLE_COMPANY_OWNER, $owner->normalizedRole());
        $this->assertSame('new-owner@example.com', $owner->email);

        Mail::assertQueued(CompanyOwnerWelcomeMail::class, function (CompanyOwnerWelcomeMail $mail) use ($owner): bool {
            return $mail->hasTo($owner->email)
                && strlen($mail->plainPassword) === 6
                && ctype_digit($mail->plainPassword)
                && Hash::check($mail->plainPassword, $owner->password);
        });
    }
}
