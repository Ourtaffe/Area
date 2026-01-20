<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AboutController;

Route::get('/about.json', [AboutController::class, 'index']);

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/', function () {
    $routes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'method' => implode('|', $route->methods()),
            'uri'    => $route->uri(),
            'name'   => $route->getName(),
            'action' => ltrim($route->getActionName(), '\\'),
        ];
    })->filter(function ($route) {
        return str_starts_with($route['uri'], 'api/');
    });

    return response()->json($routes->values(), 200, [], JSON_PRETTY_PRINT);
})->name('api.index');
