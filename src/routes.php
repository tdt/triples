<?php


/*
|--------------------------------------------------------------------------
| Triples Routes
|--------------------------------------------------------------------------
*/

Route::any('api/triples/{id?}', 'Tdt\Triples\Controllers\TriplesController@handle')->where('id', '[0-9]+');