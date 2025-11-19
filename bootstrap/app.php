<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Middleware Globales
        |--------------------------------------------------------------------------
        | Se ejecutan en TODAS las solicitudes. Aquí colocamos CORS de manera
        | global para evitar problemas tanto en dev como en producción.
        */
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Alias para Middleware de Terceros (Spatie Permissions)
        |--------------------------------------------------------------------------
        | Permite usar en rutas:
        | - middleware: role:ADMIN
        | - middleware: permission:vm.proyecto.read
        | - middleware: role_or_permission:ADMIN|vm.proyecto.read
        */
        $middleware->alias([
            'role'                => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'          => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'  => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Manejo Global de Excepciones (Opcional)
        |--------------------------------------------------------------------------
        | Puedes personalizar errores de validación, CORS, 404, 500, etc.
        | Por ahora lo dejamos limpio para que Laravel gestione todo.
        */

        // Ejemplo (si lo necesitas):
        // $exceptions->renderable(function (\Throwable $e, $request) {
        //     return response()->json([
        //         'error' => $e->getMessage()
        //     ], 500);
        // });

    })
    ->create();
