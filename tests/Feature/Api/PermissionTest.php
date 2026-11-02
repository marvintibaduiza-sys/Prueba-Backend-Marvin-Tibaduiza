<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/permissions';

    public function test_admin_lists_all_18_permissions(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(18, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']]])
            ->assertJsonFragment(['name' => 'products.delete']);
    }

    public function test_seller_cannot_list_permissions(): void
    {
        $this->actingAsSeller();

        $this->getJson(self::URI)->assertForbidden();
    }

    public function test_guest_cannot_list_permissions(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }
}
