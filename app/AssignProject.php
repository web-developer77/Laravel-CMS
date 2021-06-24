<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignProject extends Model
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

    protected $table = 'assignProjects';

    public function userInfo()
    {
        return $this->hasOne(User::class,'id','userId');
    }

    public function master_agent()
    {
        return $this->hasOne(User::class,'id','userId')->where('user.role', '=', 'Master Agent');
    }

    public function agent()
    {
        return $this->hasOne(User::class,'id','userId')->where('user.role', '=', 'Agent');
    }

    public function affiliate()
    {
        return $this->hasOne(User::class,'id','userId')->where('user.role', '=', 'Affiliate');
    }

}
