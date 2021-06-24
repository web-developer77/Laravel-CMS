<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;
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

    protected $table = 'project';

    protected $appends = ['template_ids'];

    public function units()
    {
        // return $this->hasMany(Unit::class,'projectId','id')->with('floorplanImages')->with('agents')->with('affiliates');
        
        return $this->hasMany(Unit::class,'projectId','id')->leftjoin('unitPrices', 'unitPrices.unitId', '=', 'unit.id')
                ->leftjoin('unitFloors', 'unitFloors.unitId', '=', 'unit.id')
                ->select('unit.*', 'unitPrices.price as unit_price', 'unitFloors.file as floorplan_images')
                ->with('agents')->with('affiliates');
    }

    public function masterAgents()
    {
        // return $this->hasMany(ProjectAgent::class,'projectId','id')->where('projectAgents.role','=','Master Agent');

        return $this->hasMany(AssignProject::class,'projectId','id')->leftjoin('user', 'assignProjects.userId', '=', 'user.id')
            ->select('assignProjects.*', 'user.title', 'user.firstName', 'user.lastName', 'user.mobile', 'user.email', 'user.fax', 'user.phone', 'user.about')
            ->where('user.role', '=', 'Master Agent');
    }

    public function agents()
    {
        // return $this->hasMany(ProjectAgent::class,'projectId','id')->where('projectAgents.role','=','Agent');

        return $this->hasMany(AssignProject::class,'projectId','id')->leftjoin('user', 'assignProjects.userId', '=', 'user.id')
            ->select('assignProjects.*', 'user.title', 'user.firstName', 'user.lastName', 'user.mobile', 'user.email', 'user.fax', 'user.phone', 'user.about')
            ->where('user.role', '=', 'Agent');
    }

    public function affiliates()
    {
        // return $this->hasMany(ProjectAgent::class,'projectId','id')->where('projectAgents.role','=','Affiliate');

        return $this->hasMany(AssignProject::class,'projectId','id')->leftjoin('user', 'assignProjects.userId', '=', 'user.id')
            ->select('assignProjects.*', 'user.title', 'user.firstName', 'user.lastName', 'user.mobile', 'user.email', 'user.fax', 'user.phone', 'user.about')
            ->where('user.role', '=', 'Affiliate');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    public function marketingFiles()
    {
        return $this->hasMany(Images::class,'projectId','id')->where('images.type','=','marketing');
    }

    public function additionalDetail()
    {
        return $this->hasOne(AdditionalProjectDetail::class,'projectId','id');
    }

    public function projectTemplates()
    {
        return $this->hasMany(ProjectTemplate::class,'projectId','id');
    }

    public function getTemplateIdsAttribute()
    {
        $template_ids =  $this->projectTemplates()->get();
        $temp="";
        if ($template_ids) {
            foreach ($template_ids as $template_id) {
                $temp .= ",$template_id->templateId";
            }
            return substr($temp, 1);
        }
    }

}
