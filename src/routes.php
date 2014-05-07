<?php


/*
|--------------------------------------------------------------------------
| Triples Routes
|--------------------------------------------------------------------------
*/
Route::get('all{format?}', 'Tdt\Triples\Controllers\DataController@solveQuery')

->where('format', '\.?[a-zA-Z]*');

Route::get('{dereferenced_uri}', 'Tdt\Triples\Controllers\DataController@resolve')

->where('dereferenced_uri', '^(?!api|discovery).+');

Route::any('api/triples/{id?}', 'Tdt\Triples\Controllers\TriplesController@handle')

->where('id', '[0-9]+');
