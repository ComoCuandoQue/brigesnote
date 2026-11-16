<?php

use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas publicas de consulta territorial y de instituciones educativas
| registradas en bootstrap/app.php bajo el prefijo /api.
|
*/

Route::get('/regiones', [LocationController::class, 'getRegions']);
Route::get('/provincias/{idRegion}', [LocationController::class, 'getProvinces']);
Route::get('/distritos/{idProvince}', [LocationController::class, 'getDistricts']);
Route::get('/colegios/{ubigeo}', [LocationController::class, 'getSchools']);
