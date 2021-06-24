<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class UnitAgent extends Model
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

    protected $table = 'unitAgents';

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unitId', 'id')->select(['id','unitNo']);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

}
