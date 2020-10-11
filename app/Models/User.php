<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $appends = [
        'followed_by_user'
    ];

    protected $visible = [
        'name', 'followed_by_user'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * リレーションシップ - photosテーブル
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function photos()
    {
        return $this->hasMany('App\Models\Photo');
    }

    public function followUsers()
    {
        return $this->belongsToMany('App\Models\User', 'followings', 'following_user_id', 'user_id');
    }

    /**
     * アクセサ - followed_by_user
     * @return boolean
     */
    public function getFollowedByUserAttribute()
    {
        if (Auth::guest()) {
            return false;
        }

        // いいねの中にuser_idが現在ログインしているユーザーのidを一致しているものがあるか
        return $this->followUsers->contains(function ($user) {
            return $user->user_id === Auth::user()->id;
        });
    }
}
