<?php

use App\Http\Controllers\AdminPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Private admin panel shell. The path must match the server-side ADMIN_PATH
// configuration exactly (see AdminPanelController); the shell is never
// linked publicly and the document is noindex + no-store.
Route::get('/{panel}', [AdminPanelController::class, 'show'])
    ->where('panel', '[a-zA-Z0-9][a-zA-Z0-9_-]{7,}')
    ->name('admin.panel');
