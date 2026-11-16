<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncLocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_locations_persists_hierarchy_from_list_and_single_object_responses(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/regiones')) {
                return Http::response([
                    'region' => [
                        [
                            'idRegion' => '15',
                            'nombreRegion' => 'LIMA',
                            'pointX' => -12.046374,
                            'pointY' => -77.042793,
                            'zoom' => 10,
                        ],
                        [
                            'idRegion' => '07',
                            'nombreRegion' => 'CALLAO',
                            'pointX' => -12.056590,
                            'pointY' => -77.118140,
                            'zoom' => 12,
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/provincias')) {
                return match ($request['idRegion']) {
                    '15' => Http::response([
                        'provincia' => [
                            ['idProvincia' => '1501', 'nombreProvincia' => 'LIMA', 'pointX' => -12.04, 'pointY' => -77.04],
                            ['idProvincia' => '1502', 'nombreProvincia' => 'BARRANCA', 'pointX' => -10.75, 'pointY' => -77.76],
                        ],
                    ]),
                    '07' => Http::response([
                        'provincia' => [
                            'idProvincia' => '0701',
                            'nombreProvincia' => 'CALLAO',
                            'pointX' => -12.05,
                            'pointY' => -77.11,
                        ],
                    ]),
                    default => Http::response([], 404),
                };
            }

            if (str_contains($url, '/distritos')) {
                return match ($request['idProvincia']) {
                    '1501' => Http::response([
                        'distrito' => [
                            ['idDistrito' => '150101', 'nombreDistrito' => 'LIMA'],
                            ['idDistrito' => '150102', 'nombreDistrito' => 'ANCON'],
                        ],
                    ]),
                    '1502' => Http::response([
                        'distrito' => ['idDistrito' => '150201', 'nombreDistrito' => 'BARRANCA'],
                    ]),
                    '0701' => Http::response([
                        'distrito' => ['idDistrito' => '070101', 'nombreDistrito' => 'CALLAO'],
                    ]),
                    default => Http::response([], 404),
                };
            }

            if (str_contains($url, '/instituciones')) {
                return match ($request['ubigeo']) {
                    '150101' => Http::response([
                        'items' => [
                            [
                                'codMod' => '0273572',
                                'cenEdu' => 'COLEGIO A',
                                'cenPob' => 'LIMA',
                                'dirCen' => 'JR. LAMPA 240',
                                'director' => 'ANA RUIZ',
                            ],
                            [
                                'codMod' => '0273573',
                                'cenEdu' => 'COLEGIO B',
                                'cenPob' => 'LIMA',
                                'dirCen' => 'AV. ABANCAY 100',
                            ],
                        ],
                    ]),
                    '150102' => Http::response([
                        'items' => [
                            'codMod' => '0310001',
                            'cenEdu' => 'COLEGIO ANCON',
                            'cenPob' => 'ANCON',
                            'dirCen' => 'CALLE 1',
                        ],
                    ]),
                    '150201' => Http::response([]),
                    '070101' => Http::response([], 500),
                    default => Http::response([], 404),
                };
            }

            return Http::response([], 404);
        });

        $this->artisan('sync:locations')->assertSuccessful();

        $this->assertDatabaseCount(Region::class, 2);
        $this->assertDatabaseCount(Province::class, 3);
        $this->assertDatabaseCount(District::class, 4);
        $this->assertDatabaseCount(School::class, 3);

        $this->assertDatabaseHas(School::class, [
            'codMod' => '0273572',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'COLEGIO A',
            'director' => 'ANA RUIZ',
        ]);

        $this->assertDatabaseHas(School::class, [
            'codMod' => '0310001',
            'idDistrito' => '150102',
            'nombreCenEdu' => 'COLEGIO ANCON',
        ]);
    }

    public function test_sync_locations_updates_existing_records_without_duplicating(): void
    {
        Http::preventStrayRequests();

        Region::create(['idRegion' => '15', 'nombreRegion' => 'NOMBRE ANTIGUO', 'zoom' => 5]);

        Http::fake([
            'https://escale.minedu.gob.pe/padron/rest/regiones*' => Http::response([
                'region' => [
                    'idRegion' => '15',
                    'nombreRegion' => 'LIMA',
                    'pointX' => -12.046374,
                    'pointY' => -77.042793,
                    'zoom' => 10,
                ],
            ]),
            'https://escale.minedu.gob.pe/padron/rest/provincias*' => Http::response([]),
        ]);

        $this->artisan('sync:locations')->assertSuccessful();

        $this->assertDatabaseCount(Region::class, 1);
        $this->assertDatabaseHas(Region::class, [
            'idRegion' => '15',
            'nombreRegion' => 'LIMA',
            'zoom' => 10,
        ]);
    }

    public function test_sync_locations_handles_upstream_failure_gracefully(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'https://escale.minedu.gob.pe/padron/rest/regiones*' => Http::response([], 503),
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('Error al obtener regiones')
            ->assertSuccessful();

        $this->assertDatabaseCount(Region::class, 0);
    }
}
