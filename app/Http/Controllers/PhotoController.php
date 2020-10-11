<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreComment;
use App\Http\Requests\StorePhoto;
use App\Models\Photo;
use App\Models\Comment;
use App\Models\Following;
use App\Models\User;
use App\Models\Like;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;
use Illuminate\Http\File;

class PhotoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'download', 'show']);
    }

    /**
     * 写真投稿
     * @param StorePhoto $request
     * @return \Illuminate\Http\Response
     */
    public function create(StorePhoto $request)
    {
        // 投稿写真の拡張子を取得
        $extension = $request->photo->extension();

        // インスタンス生成時にコンストラクタでidがセットされる
        $photo = new Photo();

        $file = $request->photo;
        // $name = $file->getClientOriginalName();

        $newPhotoId = $photo->id;

        // インスタンス生成時に割り振られたランダムなID値と
        // 本来の拡張子を組み合わせてファイル名とする
        $photo->filename = $photo->id . '.' . $extension;
        $photo->filename_resized = $photo->id . '_resized' . '.' . $extension;

        if ($request->photo) {
            $file = $request->photo;

            // 画像を横幅300px・縦幅アスペクト比維持の自動サイズへリサイズ
            $image = Image::make($file)
                ->resize(360, null, function ($constraint) {
                    $constraint->aspectRatio();
                });

            // configファイルに定義したS3のパスへ画像をアップロード
            Storage::disk('s3')->put($photo->filename_resized, (string)$image->encode(), 'public');
        }

        // S3にファイルを保存する
        // 第三引数の'public'はファイルを公開状態で保存するため
        // cloud() を呼んだ場合は config/filesystems.php の cloud の設定にしたがって使用されるストレージが決まります。
        Storage::cloud()->putFileAs('', $request->photo, $photo->filename, 'public');

        // データベースエラー時にファイル削除を行うため
        // トランザクションを利用する
        // トランザクション処理システムは、1つのトランザクション内の全操作がエラー無しに成功するか、全操作が実行されないことを保証する。
        DB::beginTransaction();

        try {
            Auth::user()->photos()->save($photo); // ログイン中のユーザーのidをuser_idにセット？
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            // DBとの不整合を避けるためアップロードしたファイルを削除
            Storage::cloud()->delete($photo->filename);
            throw $exception;
        }

        // #(ハッシュタグ)で始まる単語を取得。結果は、$matchに多次元配列で代入される。
        preg_match_all('/#([a-zA-z0-9０-９ぁ-んァ-ヶ亜-熙]+)/u', $request->tags, $match);

        // $match[0]に#(ハッシュタグ)あり、$match[1]に#(ハッシュタグ)なしの結果が入ってくるので、$match[1]で#(ハッシュタグ)なしの結果のみを使います。
        $tags = [];
        foreach ($match[1] as $tag) {
            $record = Tag::firstOrCreate(['name' => $tag]); // firstOrCreateメソッドで、tags_tableのnameカラムに該当のない$tagは新規登録される。
            array_push($tags, $record); // $recordを配列に追加します(=$tags)
        };

        // 投稿に紐付けされるタグのidを配列化
        $tags_id = [];
        foreach ($tags as $tag) {
            array_push($tags_id, $tag->id); // 新しく作られたtagsテーブルのレコードのidを$tags_idに格納
        };

        Photo::where('id', $newPhotoId)->with('tags')->first()->tags()->attach($tags_id); // 投稿にタグ付するために、attachメソッドをつかい、モデルを結びつけている中間テーブルにレコードを挿入します。

        $photo->id = $newPhotoId;

        // リソースの新規作成なので
        // レスポンスコードは201(CREATED)を返却する
        return response($photo, 201);
    }

    public function deletePhoto(string $id)
    { // $idがphotoのユニークid
        $photo = Photo::where('id', $id)->first();

        Storage::cloud()->delete($photo->filename);
        Storage::cloud()->delete($photo->filename_resized);

        Photo::where('id', $id)->delete();
    }

    /**
     * 写真一覧
     */
    public function index()
    {
        $photos = Photo::with(['owner', 'likes'])->orderBy(Photo::CREATED_AT, 'desc')->paginate();

        // コントローラーからモデルクラスのインスタンスなどを return すると、自動的に JSON に変換されてレスポンスが生成されます。
        return $photos;
    }

    /**
     * 写真ダウンロード
     * @param Photo $photo
     * @return \Illuminate\Http\Response
     */
    public function download(Photo $photo)
    { // URLのパラメーターで検索した結果を$photoに格納している
        // 写真の存在チェック
        if (!Storage::cloud()->exists($photo->filename)) {
            abort(404);
        }

        $disposition = 'attachment; filename="' . $photo->filename . '"';
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => $disposition,
        ];

        return response(Storage::cloud()->get($photo->filename), 200, $headers);
    }

    public function show(string $id)
    {
        // comments.author: comments リレーションから Comment を取得してさらにそこから author リレーションを辿って User の name も取得する
        $photo = Photo::where('id', $id)->with(['owner', 'comments.author', 'likes', 'tags'])->first();

        // 写真データが見つからなかった場合は 404 を返却しています。
        return $photo ?? abort(404);
    }

    /**
     * コメント投稿
     * @param Photo $photo
     * @param StoreComment $request
     * @return \Illuminate\Http\Response
     */
    public function addComment(Photo $photo, StoreComment $request)
    {
        $comment = new Comment();
        $comment->content = $request->get('content');
        $comment->user_id = Auth::user()->id;
        // $photoからcommentsを呼び出すことでphoto_idのリレーションを繋いでいる？
        $photo->comments()->save($comment);

        // authorリレーションをロードするためにコメントを取得しなおす
        $new_comment = Comment::where('id', $comment->id)->with('author')->first();

        return response($new_comment, 201);
    }

    /**
     * いいね
     * @param string $id
     * @return array
     */
    public function like(string $id)
    {
        $photo = Photo::where('id', $id)->with('likes')->first();

        if (!$photo) {
            abort(404);
        }

        $photo->likes()->detach(Auth::user()->id);
        $photo->likes()->attach(Auth::user()->id);

        return ['photo_id' => $id];
    }

    /**
     * いいね解除
     * @param string $id
     * @return array
     */
    public function unlike(string $id)
    {
        $photo = Photo::where('id', $id)->with('likes')->first();

        if (!$photo) {
            abort(404);
        }

        $photo->likes()->detach(Auth::user()->id);

        return ['photo_id' => $id];
    }

    public function checkFollow(string $id)
    {
        $follow = Following::where('following_user_id', $id)->where('user_id', Auth::user()->id)->exists();

        return $follow;
    }

    public function follow(string $userId)
    {
        $user = User::where('id', $userId)->with('followUsers')->first();

        $user->followUsers()->detach(Auth::user()->id);
        $user->followUsers()->attach(Auth::user()->id);

        return ['user_id' => $userId];
    }

    public function unfollow(string $userId)
    {
        $photo = User::where('id', $userId)->with('followUsers')->first();

        if (!$photo) {
            abort(404);
        }

        $photo->followUsers()->detach(Auth::user()->id);

        return ['user_id' => $userId];
    }

    public function showUserPhoto()
    {
        $photos = Photo::with(['owner', 'likes'])->where('user_id', Auth::user()->id)->orderBy(Photo::CREATED_AT, 'desc')->paginate();

        return $photos ?? abort(404);
    }

    public function showUserLike()
    {
        $likes = Like::where('user_id', Auth::user()->id)->get();
        $likesArr = array();
        foreach ($likes as $like) {
            $likesArr[] = $like->photo_id;
        }
        $photos = Photo::with(['owner', 'likes'])->whereIn('id', $likesArr)->orderBy(Photo::CREATED_AT, 'desc')->paginate();

        return $photos ?? abort(404);
    }
}
