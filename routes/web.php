<?php

declare(strict_types=1);

use App\Http\Controllers\Pagina\PaginaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Páginas
|--------------------------------------------------------------------------
|
| Substituem os antigos ficheiros .php do document root. Os dados continuam
| todos a vir por fetch de /api/* (ver routes/api.php); estas rotas só servem
| o HTML e resolvem o que a página precisa no primeiro render.
|
*/

Route::get('/', [PaginaController::class, 'inicio'])->name('inicio');

Route::get('/login', [PaginaController::class, 'login'])->name('login');
Route::get('/registo', [PaginaController::class, 'registo'])->name('registo');
Route::match(['get', 'post'], '/logout', [PaginaController::class, 'logout'])->name('logout');

Route::middleware(['auth:utilizador', 'tipo:passageiro'])
    ->get('/passageiro/painel', [PaginaController::class, 'painelPassageiro'])
    ->name('passageiro.painel');

Route::middleware(['auth:utilizador', 'tipo:condutor'])
    ->get('/condutor/painel', [PaginaController::class, 'painelCondutor'])
    ->name('condutor.painel');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [PaginaController::class, 'loginAdmin'])->name('login');

    // O gate da senha temporária vive no middleware e não no controller: a
    // regra é a mesma destas páginas e da /api/admin/* (ver routes/api.php),
    // e tê-la num sítio só evita que as duas se separem.
    Route::middleware(['auth:admin', 'senha_definitiva'])->group(function () {
        Route::get('/painel', [PaginaController::class, 'painelAdmin'])->name('painel');
        Route::get('/trocar-senha', [PaginaController::class, 'trocarSenhaAdmin'])
            ->withoutMiddleware('senha_definitiva')
            ->name('trocar-senha');
    });
});
