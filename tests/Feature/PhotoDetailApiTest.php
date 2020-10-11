<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Photo;
use App\Models\Comment;

class PhotoDetailApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function should_正しい構造のJSONを返却する()
    {
        Photo::factory()->create()->each(function ($photo) {
            $photo->comments()->saveMany(Comment::factory(3)->make());
        });
        $photo = Photo::first();

        $response = $this->json('GET', route('photo.show', [
            'id' => $photo->id,
        ]));

        // レスポンスが指定したJSONの一部を含んでいることをアサート
        $response->assertStatus(200)->assertJsonFragment([
            'id' => $photo->id,
            'url' => $photo->url,
            'user_id' => $photo->user_id,
            'owner' => [
                'name' => $photo->owner->name,
            ],
            'comments' => $photo->comments->sortByDesc('id')->map(function ($comment) {
                return [
                    'author' => [
                        'name' => $comment->author->name,
                    ],
                    'content' => $comment->content
                ];
            })->all(),
            'liked_by_user' => false,
            'likes_count' => 0,
        ]);
    }
}
