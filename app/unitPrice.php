<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class unitPrice extends Model
{
//    use SoftDeletes;
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

    protected $table = 'unitPrices';

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unitId', 'id');
    }
}
