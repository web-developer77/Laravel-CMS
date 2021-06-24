<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectDocumentCategory extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [];

    protected $table = 'projectDocumentCategories';
}
