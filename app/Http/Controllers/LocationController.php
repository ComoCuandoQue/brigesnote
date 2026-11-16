<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * Obtener todas las regiones registradas en la base de datos.
     */
    public function getRegions(): JsonResponse
    {
        return $this->successResponse(Region::all());
    }

    /**
     * Obtener provincias de una region segun los dos primeros digitos del ubigeo.
     */
    public function getProvinces(string $idRegion): JsonResponse
    {
        $provinces = $this->filterByPrefix(Province::query(), 'idProvincia', $idRegion, 2);

        return $this->collectionOrNotFound($provinces, 'No se encontraron provincias para esta región.');
    }

    /**
     * Obtener distritos de una provincia segun los cuatro primeros digitos del ubigeo.
     */
    public function getDistricts(string $idProvince): JsonResponse
    {
        $districts = $this->filterByPrefix(District::query(), 'idDistrito', $idProvince, 4);

        return $this->collectionOrNotFound($districts, 'No se encontraron distritos para esta provincia.');
    }

    /**
     * Obtener escuelas de un distrito segun su ubigeo completo.
     */
    public function getSchools(string $districtId): JsonResponse
    {
        $schools = School::where('idDistrito', $districtId)->get();

        return $this->collectionOrNotFound($schools, 'No se encontraron escuelas para este distrito.');
    }

    /**
     * Filtra por prefijo usando LIKE para aprovechar indices y mantener portabilidad entre motores SQL.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private function filterByPrefix(Builder $query, string $column, string $prefix, int $expectedLength): Collection
    {
        if (strlen($prefix) !== $expectedLength || strpbrk($prefix, '%_') !== false) {
            return new Collection;
        }

        return $query->where($column, 'like', $prefix.'%')->get();
    }

    /**
     * @param  Collection<int, Model>  $items
     */
    private function collectionOrNotFound(Collection $items, string $notFoundMessage): JsonResponse
    {
        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => $notFoundMessage,
            ], 404);
        }

        return $this->successResponse($items);
    }

    /**
     * @param  Collection<int, Model>  $data
     */
    private function successResponse(Collection $data): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
