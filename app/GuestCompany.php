<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class GuestCompany extends Model
{
    protected $table = 'guestCompany';

    protected $fillable = ['id', 'name', 'yourname', 'email','phone', 'remember_token', 'created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    // protected $hidden = [
    //     'remember_token',
    // ];

}
