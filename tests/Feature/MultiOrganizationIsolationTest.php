<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_customer_from_another_organization(): void
    {
        $orgA = Organization::query()->create([
            'name' => 'Org A',
            'slug' => 'org-a',
            'is_active' => true,
        ]);
        $orgB = Organization::query()->create([
            'name' => 'Org B',
            'slug' => 'org-b',
            'is_active' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'current_organization_id' => $orgA->id,
        ]);
        $user->organizations()->attach($orgA->id, [
            'role_in_org' => 'org_admin',
            'is_default' => true,
            'status' => 'active',
        ]);

        $foreignCustomer = Customer::query()->create([
            'organization_id' => $orgB->id,
            'name' => 'Foreign Customer',
            'type' => 'person',
            'status' => 'lead',
            'source' => 'other',
        ]);

        $this->actingAs($user)
            ->get('/customers/'.$foreignCustomer->id)
            ->assertNotFound();
    }
}
