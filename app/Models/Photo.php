<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class Photo extends Model
{
    use HasFactory;

    /** プライマリーキーの型 */
    protected $keyType = 'string';

    const ID_LENGTH = 12;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!Arr::get($this->attributes, 'id')) {
            $this->setId();
        }
    }

    /**
     * ランダムなID値をid属性に代入する
     */
    private function setId()
    {
        $this->attributes['id'] = $this->getRandomId();
    }

    protected $appends = [
        'url', 'likes_count', 'liked_by_user', 'resized_url'
    ];

    /** JSONに含める属性 */
    protected $visible = [
        'id', 'owner', 'url', 'comments', 'likes_count', 'liked_by_user', 'user_id', 'tags', 'resized_url'
    ];

    protected $perPage = 10;

    /**
     * ランダムなID値を生成する
     * @return string
     */
    private function getRandomId()
    {
        // マージして新しい配列を作る
        $characters = array_merge(
            range(0, 9),
            range('a', 'z'),
            range('A', 'Z'),
            ['-', '_']
        );

        $length = count($characters);

        $id = "";

        // $characters配列からランダムに選んだ文字を計12回$idに足し合わせていく
        for ($i = 0; $i < self::ID_LENGTH; $i++) {
            // random_int: 引数の間でランダムな整数を出力する
            $id .= $characters[random_int(0, $length - 1)];
        }

        return $id;
    }

    /**
     * リレーションシップ - usersテーブル
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id', 'users');
    }

    /**
     * アクセサ - url
     * @return string
     */
    public function getUrlAttribute()
    { // アップロードした画像のURLをJSONに加える
        // Storage::cloud()->url(URL)でクラウドストレージの url メソッドは S3 上のファイルの公開 URL を返却します。具体的には .env で定義した AWS_URL と引数のファイル名を結合した値になります。
        return Storage::cloud()->url($this->attributes['filename']);
    }

    /**
     * アクセサ - resized_url
     * @return string
     */
    public function getResizedUrlAttribute()
    { // アップロードした画像のURLをJSONに加える
        return Storage::cloud()->url($this->attributes['filename_resized']);
    }

    /**
     * リレーションシップ - commentsテーブル
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany('App\Models\Comment')->orderBy('id', 'desc');
    }

    /**
     * リレーションシップ - usersテーブル
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function likes()
    {
        // likes リレーションから取得できるのはユーザーモデル
        // 第２引数は結合テーブルの名前のオーバーライド？
        return $this->belongsToMany('App\Models\User', 'likes')->withTimestamps();
    }

    /**
     * アクセサ - likes_count
     * @return int
     */
    public function getLikesCountAttribute()
    {
        return $this->likes->count();
    }

    /**
     * アクセサ - liked_by_user
     * @return boolean
     */
    public function getLikedByUserAttribute()
    {
        if (Auth::guest()) {
            return false;
        }

        // いいねの中にuser_idが現在ログインしているユーザーのidを一致しているものがあるか
        return $this->likes->contains(function ($user) {
            return $user->id === Auth::user()->id;
        });
    }

    public function tags()
    {
        return $this->belongsToMany('App\Models\Tag')->withTimestamps();
    }
}
