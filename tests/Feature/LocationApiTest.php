<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_regiones_returns_empty_success_payload_when_no_records_exist(): void
    {
        $this->getJson('/api/regiones')
            ->assertOk()
            ->assertExactJson([
                'status' => 'success',
                'data' => [],
            ]);
    }

    public function test_regiones_lists_all_regions_and_includes_cors_headers(): void
    {
        Region::create([
            'idRegion' => '15',
            'nombreRegion' => 'LIMA',
            'pointX' => -12.0463740,
            'pointY' => -77.0427930,
            'zoom' => 10,
        ]);

        Region::create([
            'idRegion' => '01',
            'nombreRegion' => 'AMAZONAS',
            'pointX' => -5.5000000,
            'pointY' => -78.0000000,
            'zoom' => 8,
        ]);

        $response = $this->get('/api/regiones', [
            'Origin' => 'https://admision.example.com',
        ]);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');
    }

    public function test_provincias_filters_by_two_digit_region_prefix(): void
    {
        Province::create(['idProvincia' => '1501', 'nombreProvincia' => 'LIMA']);
        Province::create(['idProvincia' => '1502', 'nombreProvincia' => 'BARRANCA']);
        Province::create(['idProvincia' => '0101', 'nombreProvincia' => 'CHACHAPOYAS']);

        $this->getJson('/api/provincias/15')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['idProvincia' => '1501', 'nombreProvincia' => 'LIMA'])
            ->assertJsonFragment(['idProvincia' => '1502', 'nombreProvincia' => 'BARRANCA'])
            ->assertJsonMissing(['idProvincia' => '0101']);
    }

    public function test_provincias_returns_404_when_missing_or_invalid_prefix(): void
    {
        Province::create(['idProvincia' => '1501', 'nombreProvincia' => 'LIMA']);

        foreach (['99', '1', '1%'] as $regionId) {
            $this->getJson("/api/provincias/{$regionId}")
                ->assertNotFound()
                ->assertExactJson([
                    'status' => 'error',
                    'message' => 'No se encontraron provincias para esta región.',
                ]);
        }
    }

    public function test_distritos_filters_by_four_digit_province_prefix(): void
    {
        District::create(['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']);
        District::create(['idDistrito' => '150102', 'nombreDistrito' => 'ANCON']);
        District::create(['idDistrito' => '150201', 'nombreDistrito' => 'BARRANCA']);

        $this->getJson('/api/distritos/1501')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['idDistrito' => '150101', 'nombreDistrito' => 'LIMA'])
            ->assertJsonFragment(['idDistrito' => '150102', 'nombreDistrito' => 'ANCON'])
            ->assertJsonMissing(['idDistrito' => '150201']);
    }

    public function test_distritos_returns_404_when_missing_or_invalid_prefix(): void
    {
        District::create(['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']);

        foreach (['9999', '150', '150%'] as $provinceId) {
            $this->getJson("/api/distritos/{$provinceId}")
                ->assertNotFound()
                ->assertExactJson([
                    'status' => 'error',
                    'message' => 'No se encontraron distritos para esta provincia.',
                ]);
        }
    }

    public function test_colegios_filters_by_exact_district_ubigeo_and_returns_404_when_missing(): void
    {
        School::create([
            'codMod' => '0273572',
            'idDistrito' => '150101',
            'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
            'cenPob' => 'LIMA',
            'dirCen' => 'JR. LAMPA 240',
            'director' => 'PEDRO PEREZ',
        ]);

        School::create([
            'codMod' => '0999999',
            'idDistrito' => '150102',
            'nombreCenEdu' => 'COLEGIO ANCON',
        ]);

        $this->getJson('/api/colegios/150101')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'codMod' => '0273572',
                'idDistrito' => '150101',
                'nombreCenEdu' => 'JUAN JIMENEZ PIMENTEL',
            ]);

        $this->getJson('/api/colegios/999999')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron escuelas para este distrito.',
            ]);
    }
}
