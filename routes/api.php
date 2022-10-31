<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BarbershopController;



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
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('login', 'login');
    Route::post('signup', 'signup'); 
    Route::get('logout', 'logout'); 
});

//USER CLIENT
Route::controller(UserController::class)->middleware('auth:api')->prefix('users')->group(function () {
    Route::get('/profile', 'show');
    Route::get('/profile/{user_id}', 'getUser');
    Route::get('/turns', 'getTurns');
    Route::get('/turns/{turn_id}', 'showTurn');    
    Route::post('/turns', 'storeTurn');
    Route::post('/turns/cancel/{turn_id}', 'cancelTurn');
});

//USER BARBER
Route::controller(BarbershopController::class)->middleware('auth:api')->prefix('barbershops')->group(function () {
    Route::post('/', 'store');
    Route::get('/', 'getBarbershops');
    Route::get('/barbershop/my-barbershop', 'getMyBarbershop');
    Route::get('/{barbershop_id}', 'getBarbershop');
    Route::get('/turns/all', 'getTurns');
    Route::post('/turns/accept/{turn_id}', 'acceptTurn');
    Route::post('/turns/cancel/{turn_id}', 'cancelTurn');
    Route::get('/products', 'getProducts');
    Route::post('/products', 'storeProducts');
    Route::delete('/products/{product_id}', 'destroyProducts');
});
