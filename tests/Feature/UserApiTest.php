<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * @test
     */
    public function should_ログイン中のユーザーを返却する() {
        // actingAsで擬似的に認証状態にしている？
        $response = $this->actingAs($this->user)->json('GET', route('user'));

        $response->assertStatus(200)->assertJson(['name' => $this->user->name]);
    }

    /**
     * @test
     */
    public function should_ログインされていない場合は空文字を返却する() {
        $response = $this->json('GET', route('user'));

        $response->assertStatus(200);
        // ログインしていないと Auth::user() は null を返しますが、HTTP レスポンスに変換されるときに null は空文字に変わる。
        $this->assertEquals('', $response->content());
    }
}
