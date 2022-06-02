<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Helper\GlobalHelper;
use Illuminate\Support\Facades\Hash;
use App\LearnBox;
use Auth;
use Validator;
use Session;
use Image;
use DB;
use URL;
use Giphy;
use App\Role;
use App\Notifications\NewsUpdatedNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Yajra\DataTables\Facades\DataTables;
use App\Gifs;
use App\User;
use App\NewsStream;
use App\Comment;
use App\Favourite;
use App\NewsStreamAds;

class UserNewStreamController extends Controller
{

    public function searchNews(Request $request)
    {
        if($request->search_in){
            if (strpos($request->search_in, ',') !== false) {
                $search_arr = explode(",",$request->search_in);
            }else if (strpos($request->search_in, '-') !== false) {
                $search_arr = explode("-",$request->search_in);
            }else if (strpos($request->search_in, ' ') !== false) {
                $search_arr = explode(" ",$request->search_in);
            }
            $sql = DB::select(DB::raw('select GROUP_CONCAT(user_fav_id) as favUsers from favourites where user_id ='.Auth::user()->id));
            $searchResultQuery =  NewsStream::select('*',DB::raw('news_stream.created_at as post_creation_date'))->join('users','users.id','=','news_stream.user_id')
                        ->whereIn('news_stream.user_id',explode(',',$sql[0]->favUsers));
            if(isset($search_arr) && count($search_arr) > 0){
                    $searchResultQuery->Where(function($query) use ($search_arr){
                        foreach($search_arr as $search){
                            if($search){
                                $search = trim($search);
                                $query->orWhere('news_description', 'LIKE' , "%{$search}%")
                                      ->orWhere('user_id', 'LIKE', "%{$search}%");
                            }
                        }
                    });
            }else{
                $searchResultQuery->where(function($query) use ($request){
                                    $query->where('news_description', 'LIKE' , "%{$request->search_in}%")
                                          ->orWhere('user_id', 'LIKE', "%{$request->search_in}%");
                });
            }
            $searchResult = $searchResultQuery->where('news_stream_id','<>','1')
                                              ->where('status','1')
                                              ->paginate(81);
        }
        else{
            $sql = DB::select(DB::raw('select GROUP_CONCAT(user_fav_id) as favUsers from favourites where user_id ='.Auth::user()->id));
            $searchResult =NewsStream::select('*',DB::raw('news_stream.created_at as post_creation_date'))->join('users','users.id','=','news_stream.user_id')
                          ->whereIn('news_stream.user_id',explode(',',$sql[0]->favUsers))
                            ->where('status','1')
                            // ->where('user_status','1')
                            ->orderBy(DB::raw('RAND()'))
                            ->paginate(81);
        }
        $newsAds = NewsStreamAds::select('news_ads_url', 'news_ads_image', 'news_google_flag')->where('status', '1')->inRandomOrder()->get();
        $box4 =  LearnBox::find(5);
        return view('searchNews',compact('view', 'searchResult', 'ads', 'box4','user','newsAds'));
    }
    public function listPost(Request $request) {
      $sql = DB::select(DB::raw('select GROUP_CONCAT(user_fav_id) as favUsers from favourites where user_id ='.Auth::user()->id));
      $posts = NewsStream::select('*',DB::raw('news_stream.created_at as post_creation_date'))->join('users','users.id','=','news_stream.user_id')
                  ->whereIn('news_stream.user_id',explode(',',$sql[0]->favUsers))
                  ->with(['getNewsLikes','getComment','getNewsUnLikes','getNewsLikesGuest','getNewsUnLikesGuest'])
                  ->withCount('getLike','getunLike')
                  ->where('news_stream.status', '1')
                  ->orderBy('news_stream.created_at','DESC')
                  ->paginate(15);
        $users = Auth()->user();
        $newsAds = NewsStreamAds::select('news_ads_url', 'news_ads_image', 'news_google_flag')->where('status', '1')->inRandomOrder()->get();
        $box4 = LearnBox::find(5);
        if ($request->ajax()) {
            $view = view('news-stream-post', compact(['posts','users','box4','user','user_id','random','box4','likeCount','newsAds']))->render();
            return response()->json(['html'=>$view]);
        }
        return view('news-stream',compact(['posts','users','user','user_id','random','box4','likeCount','newsAds']));
    }

    public function postDetail($name,Request $request,$id) {
      $post = NewsStream::select('news_stream.*','news_stream_likes.rating')
                  ->leftjoin('news_stream_likes','news_stream.news_stream_id','=','news_stream_likes.news_stream_id')
                  ->where('news_stream.news_stream_id',$id)
                  ->where('news_stream.status', '1');
                  if(Auth::user()){
                    $post  = $post->with(['getNewsLikes','getComment','getNewsUnLikes']);
                  }else {
                    $post  = $post->with(['getComment','getNewsLikesGuest','getNewsUnLikesGuest']);
                  }
                  $post = $post->withCount(['getLike','getunLike'])
                  ->first();
        $newsAds = NewsStreamAds::select('news_ads_url', 'news_ads_image', 'news_google_flag')->where('status', '1')->inRandomOrder()->get();
        if($post) {
            $user = User::find($post->user_id);
            $box4 = LearnBox::find(5);
            return view('news-stream-details',compact(['post','user','box4','newsAds']));
        } else {
            abort(404);
        }
    }
    public function UserpostList($name,Request $request) {

        $posts = NewsStream::select('news_stream.*','users.id','users.profile_image','users.user_name','users.name',DB::raw('news_stream.created_at as post_creation_date'))
                    ->join('users','news_stream.user_id','=','users.id');
                    if(Auth::user()){
                      $posts  = $posts->with(['getNewsLikes','getComment','getNewsUnLikes']);
                    }else {
                      $posts  = $posts->with(['getComment','getNewsLikesGuest','getNewsUnLikesGuest']);
                    }
                    $posts = $posts->withCount(['getLike','getunLike'])
                    ->where('users.user_name',$name)
                    ->where('news_stream.status', '1')
                    ->orderBy('news_stream.created_at','DESC')
                    ->paginate(11);
        $user = User::where('user_name',$name)->first();
        $newsAds = NewsStreamAds::select('news_ads_url', 'news_ads_image', 'news_google_flag')->where('status', '1')->inRandomOrder()->get();
        $box4 = LearnBox::find(5);
        if ($request->ajax()) {
            $view = view('news-stream-post', compact(['posts','user','box4']))->render();
            return response()->json(['html'=>$view]);
        }
        return view('news-stream',compact(['posts','users','user','user_id','random','box4','likeCount','newsAds']));
    }

    public function post(Request $request){
      // dd($request->all());
      $rules = [
        'news_description' => 'required'
      ];

      $messages = [
      ];

      $validator = Validator::make(Input::all(), $rules, $messages);

      if ($validator->fails()) {
          return redirect()->back()
                      ->withErrors($validator)
                      ->withInput();
      } else {

          $userDetail = User::find(Auth()->user()->id);
          $post = new NewsStream();
          $post->user_id = Auth()->user()->id;
          // $post->post_user_id = Auth()->user()->id;
          $post->news_description = $request->news_description;
          $post->news_url_video = $request->news_url_video;
          // dd($post->image);
          if($request->image && $request->hasFile('image')){
              if($post->image && file_exists(base_path().'/resources/uploads/newsStream/'.$post->image)){
                unlink(base_path().'/resources/uploads/newsStream/'.$post->image); // correct
              }

              $file = $request->file('image');
              $file->getClientOriginalName();
              $fileExtension = $file->getClientOriginalExtension();
              $file->getRealPath();
              $file->getSize();
              $file->getMimeType();
              $fileName = md5(microtime() . $file->getClientOriginalName()) . "." . $fileExtension;
              $destinationPath = base_path().'/resources/uploads/newsStream/';
              $file->move($destinationPath, $fileName);
              chmod($destinationPath.$fileName,0777);
              $post->image = $fileName;
        }
          if($request->gif && $request->hasFile('gif')){
              if($post->image && file_exists(base_path().'/resources/uploads/newsStream/'.$post->image)){
                unlink(base_path().'/resources/uploads/newsStream/'.$post->image); // correct
              }

              $file = $request->file('gif');
              $file->getClientOriginalName();
              $fileExtension = $file->getClientOriginalExtension();
              $file->getRealPath();
              $file->getSize();
              $file->getMimeType();
              $fileName = md5(microtime() . $file->getClientOriginalName()) . "." . $fileExtension;
              $destinationPath = base_path().'/resources/uploads/newsStream/';
              $file->move($destinationPath, $fileName);
              chmod($destinationPath.$fileName,0777);
              $post->image = $fileName;
        }
          if($post->save()) {

              Session::flash('message', 'Your post successfully posted');
              Session::flash('alert-class', 'success');
              return redirect('/'.$userDetail->user_name.'/news-stream');

          }else{
              Session::flash('message', 'Oops!!, Something went wrong');
              Session::flash('alert-class', 'error');
              return redirect('/'.$userDetail->user_name.'/news-stream');
          }
      }
    }

     public function deletePost($id,Request $request)
     {
         $deletePost = NewsStream::find($id);
         $deletePost->delete();
         return $deletePost;
     }

     public function deleteMedia($id)
     {
         $media = NewsStream::where('news_stream_id',$id)
         ->update([
                  'image' => null,
                ]);
         return $media;

     }
}
