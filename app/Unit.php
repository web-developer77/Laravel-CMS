<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class Unit extends Model
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

    protected $table = 'unit';

    public function agents()
    {
        return $this->hasMany(UnitAgent::class,'unitId','id')->where('role','=','Agent');
    }

    public function affiliates()
    {
        return $this->hasMany(UnitAgent::class,'unitId','id')->where('role','=','Affiliate');
    }

    public function marketingImages()
    {
        return $this->hasMany(Images::class,'unitId','id')->where('images.type','=','marketing');
    }

    public function floorplanImages()
    {
        return $this->hasMany(Images::class,'unitIds','id')->where('images.type','=','floorplan');
    }

    public function project()
    {
        return $this->belongsTo(Project::class,'projectId','id');
    }

    public function unitprice()
    {
        return $this->hasMany(unitPrice::class,'unitId','id')->with('user');
    }

    public function floorPlan()
    {
        return $this->hasMany(unitFloor::class,'unitId','id')->with('user');
    }

    public function assigned() {
        return $this->hasMany(UnitAgent::class,'unitId','id');
    }
}
