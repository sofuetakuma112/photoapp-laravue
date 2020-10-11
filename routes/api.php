<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', 'App\Http\Controllers\Auth\RegisterController@register')->name('register');
Route::post('/login', 'App\Http\Controllers\Auth\LoginController@login')->name('login');
Route::post('/logout', 'App\Http\Controllers\Auth\LoginController@logout')->name('logout');
// Auth::user()で現在ログインしているユーザーの情報を返す
Route::get('/user', fn () => Auth::user())->name('user');
Route::post('/photos', 'App\Http\Controllers\PhotoController@create')->name('photo.create');
Route::get('/photos', 'App\Http\Controllers\PhotoController@index')->name('photo.index');
Route::delete('/photos/{id}/delete', 'App\Http\Controllers\PhotoController@deletePhoto');

Route::get('/photos/userPhoto', 'App\Http\Controllers\PhotoController@showUserPhoto')->name('photo.showUserPhoto');
Route::get('/photos/userLike', 'App\Http\Controllers\PhotoController@showUserLike')->name('photo.showUserLike');

Route::get('/photos/{id}', 'App\Http\Controllers\PhotoController@show')->name('photo.show');

Route::post('/photos/{photo}/comments', 'App\Http\Controllers\PhotoController@addComment')->name('photo.comment');

Route::put('/photos/{id}/like', 'App\Http\Controllers\PhotoController@like')->name('photo.like');
Route::delete('/photos/{id}/like', 'App\Http\Controllers\PhotoController@unlike');

Route::put('/photos/{userId}/follow', 'App\Http\Controllers\PhotoController@follow')->name('photo.follow');
Route::delete('/photos/{userId}/unfollow', 'App\Http\Controllers\PhotoController@unfollow');

Route::get('/user/{userId}/checkFollow', 'App\Http\Controllers\PhotoController@checkFollow');

Route::get('/refresh-token', function (Illuminate\Http\Request $request) {
  $request->session()->regenerateToken();

  return response()->json();
});
