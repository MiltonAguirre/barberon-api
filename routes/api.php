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
Route::group([
    'controller' => AuthController::class,
    'prefix' => 'auth'
], function () {
    Route::post('login', 'login')->name('login');
    Route::post('signup', 'signup'); 
    Route::get('logout', 'logout'); 
});

//USER CLIENT
Route::group([
    'controller' => UserController::class,
    'middleware' => ['auth:sanctum'],
    'prefix' => 'users'
],function () {
    Route::get('/profile', 'show');
    Route::group([
        'prefix'=>'turns', 
        'middleware'=>['CheckRole:client']
    ], function(){
        Route::get('/', 'getTurns');
        Route::post('/', 'storeTurn');
        Route::get('/show/{turn_id}', 'showTurn');    
        Route::post('/cancel/{turn_id}', 'cancelTurn');
    });
});

//BARBERSHOPS
Route::group([
    'controller' => BarbershopController::class,
    'prefix' => 'barbershops'
],function () {
    Route::get('/', 'getBarbershops');
    Route::get('/show/{barbershop_id}', 'getBarbershop');
    Route::group(['middleware'=>'auth:sanctum'], function(){
        Route::post('/', 'store');
        Route::get('/my-barbershop', 'getMyBarbershop');
        //PRODUCTS
        Route::group(['prefix'=>'products'], function(){
            Route::get('/{barbershop_id}', 'getProducts');
            Route::group(['middleware' => ['CheckRole:barber']], function(){
                Route::post('/', 'storeProducts');
                Route::delete('/{product_id}', 'destroyProducts');
            });
        });
        //TURNS OF BARBERSHOP
        Route::prefix('turns')->group(function(){
            Route::get('/show/{turn_id}', 'showTurn');
            Route::group(['middleware' => ['CheckRole:barber']], function(){
                Route::post('/accept/{turn_id}', 'acceptTurn');
                Route::post('/cancel/{turn_id}', 'cancelTurn');
                Route::get('/all', 'getTurns');
            });
        });
    });
});