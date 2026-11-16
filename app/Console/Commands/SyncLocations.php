<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncLocations extends Command
{
    private const string BASE_URL = 'https://escale.minedu.gob.pe/padron/rest';

    private const int TIMEOUT_SECONDS = 30;

    protected $signature = 'sync:locations';

    protected $description = 'Sincroniza regiones, provincias, distritos y escuelas desde la API y las registra en la base de datos';

    public function handle(): int
    {
        $this->info('Iniciando sincronización de ubicaciones...');

        $this->syncRegions();

        $this->info('Sincronización completa.');

        return self::SUCCESS;
    }

    private function syncRegions(): void
    {
        $this->info('Sincronizando regiones...');

        $payload = $this->fetchJson('/regiones', [], 'Error al obtener regiones');
        if ($payload === null) {
            return;
        }

        foreach ($this->normalizeRecords($payload['region'] ?? null, 'idRegion') as $region) {
            Region::updateOrCreate(
                ['idRegion' => $region['idRegion']],
                [
                    'nombreRegion' => $region['nombreRegion'] ?? null,
                    'pointX' => $region['pointX'] ?? null,
                    'pointY' => $region['pointY'] ?? null,
                    'zoom' => $region['zoom'] ?? null,
                ]
            );

            $this->syncProvinces((string) $region['idRegion']);
        }

        $this->info('Regiones sincronizadas correctamente.');
    }

    private function syncProvinces(string $regionId): void
    {
        $this->info("Sincronizando provincias para la región {$regionId}...");

        $payload = $this->fetchJson(
            '/provincias',
            ['idRegion' => $regionId],
            "Error al obtener provincias para la región {$regionId}"
        );
        if ($payload === null) {
            return;
        }

        foreach ($this->normalizeRecords($payload['provincia'] ?? null, 'idProvincia') as $province) {
            Province::updateOrCreate(
                ['idProvincia' => $province['idProvincia']],
                [
                    'nombreProvincia' => $province['nombreProvincia'] ?? null,
                    'pointX' => $province['pointX'] ?? null,
                    'pointY' => $province['pointY'] ?? null,
                ]
            );

            $this->syncDistricts((string) $province['idProvincia']);
        }

        $this->info("Provincias para la región {$regionId} sincronizadas correctamente.");
    }

    private function syncDistricts(string $provinceId): void
    {
        $this->info("Sincronizando distritos para la provincia {$provinceId}...");

        $payload = $this->fetchJson(
            '/distritos',
            ['idProvincia' => $provinceId],
            "Error al obtener distritos para la provincia {$provinceId}"
        );
        if ($payload === null) {
            return;
        }

        foreach ($this->normalizeRecords($payload['distrito'] ?? null, 'idDistrito') as $district) {
            District::updateOrCreate(
                ['idDistrito' => $district['idDistrito']],
                [
                    'nombreDistrito' => $district['nombreDistrito'] ?? null,
                ]
            );

            $this->syncSchools((string) $district['idDistrito']);
        }

        $this->info("Distritos para la provincia {$provinceId} sincronizados correctamente.");
    }

    private function syncSchools(string $districtId): void
    {
        $this->info("Sincronizando escuelas para el distrito {$districtId}...");

        $payload = $this->fetchJson(
            '/instituciones',
            ['estados' => 1, 'ubigeo' => $districtId],
            "Error al obtener escuelas para el distrito {$districtId}"
        );
        if ($payload === null) {
            return;
        }

        if (! isset($payload['items'])) {
            $this->error('No se encontraron registros de escuelas');

            return;
        }

        foreach ($this->normalizeRecords($payload['items'], 'codMod') as $school) {
            School::updateOrCreate(
                ['codMod' => $school['codMod']],
                [
                    'idDistrito' => $districtId,
                    'nombreCenEdu' => $school['cenEdu'] ?? null,
                    'cenPob' => $school['cenPob'] ?? null,
                    'dirCen' => $school['dirCen'] ?? null,
                    'director' => $school['director'] ?? null,
                    'dis_crecer' => $school['dis_crecer'] ?? null,
                    'dis_juntos' => $school['dis_juntos'] ?? null,
                ]
            );
        }

        $this->info("Escuelas para el distrito {$districtId} sincronizadas correctamente.");
    }

    /**
     * Consulta un endpoint de ESCALE y devuelve su carga JSON decodificada o null ante fallo.
     *
     * @param  array<string, int|string>  $query
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $endpoint, array $query, string $errorMessage): ?array
    {
        $response = Http::acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->get(self::BASE_URL.$endpoint, $query);

        if ($response->failed()) {
            $this->error($errorMessage);

            return null;
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * ESCALE serializa un unico elemento como objeto asociativo y multiples elementos como lista.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRecords(mixed $payload, string $itemKey): array
    {
        if (! is_array($payload) || $payload === []) {
            return [];
        }

        return array_key_exists($itemKey, $payload) ? [$payload] : array_values($payload);
    }
}
