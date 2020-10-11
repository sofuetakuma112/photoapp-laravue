<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Photo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PhotoListApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function should_正しい構造のJSONを返却する()
    {
        // 5つの写真データを生成する
        Photo::factory(5)->create();

        $response = $this->json('GET', route('photo.index'));

        // 生成した写真データを作成日降順で取得
        $photos = Photo::with(['owner'])->orderBy('created_at', 'desc')->get();

        // data項目の期待値
        $expected_data = $photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'url' => $photo->url,
                'user_id' => $photo->user_id,
                'owner' => [
                    // usersテーブルから引っ張ってきている
                    'name' => $photo->owner->name,
                ],
                'liked_by_user' => false,
                'likes_count' => 0,
            ];
        })->all();

        // assertJsonCount: レスポンスJSONのdata項目に含まれる要素が5つであること
        // assertJsonFragment: レスポンスJSONのdata項目が期待値と合致すること
        $response->assertStatus(200)->assertJsonCount(5, 'data')->assertJsonFragment(["data" => $expected_data,]);
    }
}
