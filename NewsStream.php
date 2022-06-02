<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Auth;

class NewsStream extends Model
{
    protected $table = 'news_stream';
    protected $primaryKey = 'news_stream_id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'title', 'description', 'image','shares' ,'status'];

    public function getUser(){
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function getNewsLikes(){
        return $this->hasOne('App\NewsStreamLikes', 'news_stream_id')->where('user_id',Auth::user()->id);
    }
    // guest login
    public function getNewsLikesGuest(){
        return $this->hasOne('App\NewsStreamLikes', 'news_stream_id');
    }

    // like count
    public function getLike(){
        return $this->hasMany('App\NewsStreamLikes','news_stream_id');
    }

    // Comment count
    public function getComment(){
        return $this->hasMany('App\Comment','news_stream_id')->with(['userDetail'])->orderBy('created_at','DESC');
    }

    public function getNewsUnLikes(){
        return $this->hasOne('App\NewsStreamUnLikes', 'news_stream_id')->where('user_id',Auth::user()->id);
    }
    // guest login
    public function getNewsUnLikesGuest(){
        return $this->hasOne('App\NewsStreamUnLikes', 'news_stream_id');
    }

    // unlike count
    public function getunLike(){
        return $this->hasMany('App\NewsStreamUnLikes','news_stream_id');
    }

}
