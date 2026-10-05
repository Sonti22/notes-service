<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

// Invalid/non-positive identifiers cannot match; missing records return JSON 404.
Route::pattern('note', '[1-9][0-9]{0,17}');
Route::apiResource('notes', NoteController::class);
