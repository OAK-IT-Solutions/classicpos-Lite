<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\BusinessProfile;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Sync;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Warehouse $warehouse;
    protected Product $product;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->branch = Branch::factory()->create();
        $this->user->branch_id = $this->branch->id;
        $this->user->save();

        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('name', 'admin')->first();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $this->branch->id]);

        $this->warehouse = Warehouse::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->product = Product::factory()->create([
            'price' => 25.00,
            'is_active' => true,
        ]);
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);
    }

    /** @test */
    public function it_accepts_a_batch_of_offline_sales()
    {
        $payload = [
            'sales' => [
                [
                    'local_id' => 'offline-test-001',
                    'branch_id' => $this->branch->id,
                    'items' => [
                        [
                            'product_id' => $this->product->id,
                            'product_name' => $this->product->name,
                            'quantity' => 2,
                            'price' => 25.00,
                        ],
                    ],
                    'payment_method' => 'cash',
                    'subtotal' => 50.00,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 50.00,
                    'cash_received' => 50.00,
                    'change_due' => 0.00,
                ],
            ],
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/sync/sales', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results' => [
                '*' => ['local_id', 'success', 'server_id', 'invoice_number'],
            ],
            'summary' => ['total', 'succeeded', 'failed'],
        ]);

        $this->assertEquals(1, $response->json('summary.succeeded'));
        $this->assertEquals(1, Sale::count());
    }

    /** @test */
    public function it_is_idempotent_for_duplicate_local_ids()
    {
        $localId = 'offline-test-duplicate-001';
        $payload = [
            'sales' => [
                [
                    'local_id' => $localId,
                    'branch_id' => $this->branch->id,
                    'items' => [
                        [
                            'product_id' => $this->product->id,
                            'product_name' => $this->product->name,
                            'quantity' => 1,
                            'price' => 25.00,
                        ],
                    ],
                    'payment_method' => 'cash',
                    'subtotal' => 25.00,
                    'tax_amount' => 0,
                    'total_amount' => 25.00,
                ],
            ],
        ];

        $first = $this->withToken($this->token)->postJson('/api/v1/sync/sales', $payload);
        $first->assertStatus(200);
        $this->assertEquals(1, $first->json('summary.succeeded'));
        $this->assertEquals(1, Sale::count());

        $second = $this->withToken($this->token)->postJson('/api/v1/sync/sales', $payload);
        $second->assertStatus(200);
        $this->assertEquals(1, $second->json('summary.succeeded'));
        // Sale count must remain 1 (idempotency)
        $this->assertEquals(1, Sale::count());
        // Result should be marked as duplicate
        $this->assertTrue($second->json('results.0.duplicate'));
    }

    /** @test */
    public function it_validates_sync_settings_get_request()
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/sync/settings');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['sync_mode']]);
        $this->assertEquals('auto', $response->json('data.sync_mode'));
    }

    /** @test */
    public function it_updates_sync_mode()
    {
        $payload = [
            'sync_mode' => 'manual',
            'auto_sync_interval_seconds' => 60,
            'printer_config' => [
                'type' => 'usb',
                'drawer_pin' => 2,
            ],
        ];

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/sync/settings', $payload);

        $response->assertStatus(200);
        $this->assertEquals('manual', $response->json('data.sync_mode'));
        $this->assertEquals(60, $response->json('data.auto_sync_interval_seconds'));
        $this->assertEquals('usb', $response->json('data.printer_config.type'));

        // Verify it was persisted
        $profile = BusinessProfile::where('branch_id', $this->branch->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals('manual', $profile->settings['sync_mode']);
    }

    /** @test */
    public function it_rejects_invalid_sync_mode()
    {
        $payload = ['sync_mode' => 'invalid-mode'];

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/sync/settings', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_printer_config_structure()
    {
        $payload = [
            'sync_mode' => 'auto',
            'printer_config' => [
                'type' => 'invalid-type',
            ],
        ];

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/sync/settings', $payload);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_sync_status_with_pending_counts()
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/sync/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'online',
                'sync_mode',
                'tables',
                'pending_offline_sales',
            ],
        ]);
    }
}
