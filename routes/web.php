<?php

use Illuminate\Support\Facades\Route;

Route::get('/photos/{photo}/download', 'App\Http\Controllers\PhotoController@download');

// any パラメータはあってもなくてもいい（?）し、ある場合はどんな文字列でもいい（.+）ということ
Route::get('/{any?}', fn () => view('index'))->where('any', '.+');

Auth::routes();