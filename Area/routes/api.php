<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AreaController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\ActionController;
use App\Http\Controllers\API\ReactionController;
use App\Http\Controllers\API\UserServiceController;
use App\Http\Controllers\API\HookController;
use App\Http\Controllers\API\TriggerHistoryController;
use App\Http\Controllers\API\GitHubAuthController;

Route::group(['namespace' => 'App\Http\Controllers\API'], function () {
    Route::post('register', 'AuthenticationController@register')->name('register');
    Route::post('login', 'AuthenticationController@login')->name('login');

    Route::get('auth/{provider}', 'SocialAuthController@redirect');
    Route::get('auth/{provider}/callback', 'SocialAuthController@callback');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('get-user', 'AuthenticationController@userInfo')->name('get-user');
        Route::post('logout', 'AuthenticationController@logOut')->name('logout');

        Route::apiResource('services', ServiceController::class)->only(['index', 'show']);

        Route::apiResource('actions', ActionController::class)->only(['index', 'show']);

        Route::apiResource('reactions', ReactionController::class)->only(['index', 'show']);

        Route::apiResource('user-services', UserServiceController::class);

        Route::apiResource('areas', AreaController::class);

        Route::patch('areas/{area}/toggle', [AreaController::class, 'toggle']);

        Route::apiResource('hooks', HookController::class);

        Route::apiResource('trigger-histories', TriggerHistoryController::class)->only(['index', 'show']);

        Route::post('connect-service/{provider}', 'SocialAuthController@connectService');
        
        Route::prefix('oauth/github')->group(function () {
        Route::post('/redirect', [GitHubAuthController::class, 'redirect']);
        Route::get('/callback', [GitHubAuthController::class, 'callback']);
        Route::post('/test', [GitHubAuthController::class, 'test']);
        Route::post('/disconnect', [GitHubAuthController::class, 'disconnect']);
});
    });
});
