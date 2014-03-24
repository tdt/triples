<?php


/*
|--------------------------------------------------------------------------
| Triples Routes
|--------------------------------------------------------------------------
*/

Route::get('{dereferenced_uri}', 'Tdt\Triples\Controllers\DataController@resolve')

->where('dereferenced_uri', '^(?!api|discovery).+');

Route::any('api/triples/{id?}', 'Tdt\Triples\Controllers\TriplesController@handle')

->where('id', '[0-9]+');
