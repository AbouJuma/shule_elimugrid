<?php

namespace Tests\Feature\Transport;

use App\Models\Place;
use App\Models\School;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function makeUser(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        setPermissionsTeamId($school->id);
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Admin',
            'email' => 'admin@sunrise.test', 'password' => 'password', 'status' => 'active',
        ]);
        $user->assignRole('School Administrator');

        return [$school, $user];
    }

    public function test_local_results_are_returned_without_calling_nominatim(): void
    {
        [, $user] = $this->makeUser();

        Place::create(['name' => 'Kariakoo Market', 'latitude' => -6.8188, 'longitude' => 39.2685, 'source' => 'local']);

        Http::fake(); // any real HTTP call in this test fails the assertion below

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/places/search?q=Kariakoo')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Kariakoo Market');
        Http::assertNothingSent();
    }

    public function test_falls_back_to_nominatim_when_local_cache_is_thin_and_caches_the_result(): void
    {
        [, $user] = $this->makeUser();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Mwenge, Kinondoni, Dar es Salaam, Tanzania',
                    'lat' => '-6.7699',
                    'lon' => '39.2528',
                ],
            ], 200),
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/places/search?q=Mwenge')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Mwenge, Kinondoni, Dar es Salaam, Tanzania');

        $this->assertDatabaseHas('places', [
            'name' => 'Mwenge, Kinondoni, Dar es Salaam, Tanzania',
            'source' => 'nominatim',
        ]);

        // Second search for the same place must be served from the local
        // cache now, without hitting Nominatim again.
        Http::fake();
        $second = $this->getJson('/api/v1/places/search?q=Mwenge')->assertOk();
        $second->assertJsonCount(1, 'data');
        Http::assertNothingSent();
    }

    public function test_blank_query_returns_no_results_and_makes_no_external_call(): void
    {
        [, $user] = $this->makeUser();

        Http::fake();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/places/search?q=')->assertOk()->assertJsonCount(0, 'data');
        Http::assertNothingSent();
    }
}
