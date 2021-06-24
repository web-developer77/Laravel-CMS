<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class PanoramaLinks extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    protected $table = 'panoramalinks';

}