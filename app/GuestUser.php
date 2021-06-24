<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class GuestUser extends Model
{
    protected $table = 'guestUser';

    protected  $fillable = ['id', 'firstname', 'lastname', 'email', 'phone', 'remember_token', 'created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    // protected $hidden = [
    //     'remember_token',
    // ];

}
