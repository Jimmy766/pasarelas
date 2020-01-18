<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/autoriza', 'Controller@autoriza')->name('api.autoriza');
Route::post('/otroPago', 'Controller@simulaPagoRecurrente')->name('api.otroPago');
Route::get('/clientSecret', 'Controller@clientSecret')->name('api.clientSecret');
Route::get('/clientSecret3d', 'Controller@clientSecret3d')->name('api.clientSecret3d');
Route::post('/autorizaStripe', 'Controller@autorizaStripe')->name('api.autorizaStripe');
Route::post('/recurrentePagoStripe', 'Controller@recurrentePagoStripe')->name('api.recurrentePagoStripe');
Route::post('/hooks', 'Controller@hooks')->name('api.hooks');
