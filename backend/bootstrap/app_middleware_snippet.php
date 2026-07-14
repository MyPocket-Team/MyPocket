<?php

// Dans ton bootstrap/app.php existant, à l'intérieur de ->withMiddleware(function (Middleware $middleware) {...})
// ajoute cette ligne pour créer l'alias 'super_admin' :

use App\Http\Middleware\EnsureUserIsSuperAdmin;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'super_admin' => EnsureUserIsSuperAdmin::class,
    ]);
})

// Exemple de fichier bootstrap/app.php complet typique en Laravel 12 :

/*
<?php

use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
*/
