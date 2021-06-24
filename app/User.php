<?php

namespace App;
/*
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;*/
use Illuminate\Database\Eloquent\Model;
/*use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;*/
use GenTux\Jwt\JwtPayloadInterface;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model implements JwtPayloadInterface//AuthenticatableContract, AuthorizableContract
{
    //use Authenticatable, Authorizable;
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
    protected $hidden = [
        'password', 'otp', 'otpCreatedAt'
    ];

    protected $table = 'user';

    public function assigned()
    {
        return $this->hasMany(AssignProject::class,'userId','id');
    }

    public function assignedUnit()
    {
        return $this->hasMany(UnitAgent::class,'userId','id');
    }

    public function getPayload()
    {
        return [
            'sub' => $this->id,
            'exp' => time() + 72000,
            'context' => [
                'id'        => $this->id,
                'role'      => $this->role,
                'isEnable'  => $this->isEnable
            ]
        ];
    }
}
