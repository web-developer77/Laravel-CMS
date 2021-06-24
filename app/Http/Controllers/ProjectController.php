<?php

namespace App\Http\Controllers;

use App\ProjectDocument;
use App\ProjectDocumentCategory;
use App\Unit;
use App\User;
use App\Images;
use App\Project;
use App\UnitAgent;
use App\ProjectAgent;
use App\ProjectImage;
use App\AssignProject;
use App\ProjectTemplate;
use App\Panoramas;
use App\PanoramaLinks;
use Google\Analytics\Data\V1beta\OrderBy;
use Illuminate\Http\Request;
use App\AdditionalProjectDetail;
use App\Events\DeploymentNoticeEvent;
use Illuminate\Support\Facades\Storage;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;

class ProjectController extends Controller
{
    public $table;

    public function __construct()
    {
        $this->table = with(new Project)->getTable();
    }

     /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function projectLists(Request $request)
    {
        try
        {
            //get loggedin user
            $user = getLoggedInUser();

            //check validation
            $validator = app('validator')->make($request->all(),[
                'sortOrder'      => 'in:asc,desc',
                'pageNumber'     => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search'         => 'json',
                'isEnable'       => 'in:0,1'
            ],[]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            //sorting
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : config('pager.sortBy');
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : config('pager.sortOrder');
            $pager = [];

             // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {

                $projects = new Project;
                if($user['role'] == 'Admin') // || $user['role'] == 'User Manager')
                {
                    $projects = $projects->select('id', 'name', 'deployedAt');
                } else if($user['role'] == 'Project Manager' || $user['role'] == 'User Manager') {
                    $projects = $projects->select($this->table.'.id', $this->table.'.name', $this->table.'.deployedAt')->rightJoin('assignProjects','project.id','=','assignProjects.projectId')->where('assignProjects.userId',$user['id']);
                } else {
                    $projects = $projects->select($this->table.'.id', $this->table.'.name', $this->table.'.deployedAt')->rightJoin('assignProjects','project.id','=','assignProjects.projectId')->where('assignProjects.userId',$user['id']);
                    $projects = $projects->where('deployedAt', '!=', NULL);
                }

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'firstName' || $k == 'lastName' || $k == 'companyName'  || $k == 'office' || $k == 'address' || $k == 'isEnable' || $k == 'role';
                    }, ARRAY_FILTER_USE_KEY);

                    foreach ($search as $field => $value)
                       $projects = $projects->where($this->table.'.'.$field, 'like', '%'.$value.'%');
                }
                $projects = $projects = $projects->orderBy($this->table.'.'.$sortBy, $sortOrder)->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;

                $take = $recordsPerPage;

                // Get project list
                $projects = new Project;

                if($user['role'] == 'Admin') // || $user['role'] == 'User Manager')
                {
                    $projects = $projects->select('id', 'name', 'deployedAt');
                } else if($user['role'] == 'Project Manager' || $user['role'] == 'User Manager') {
                    $projects = $projects->select($this->table.'.id', $this->table.'.name', $this->table.'.deployedAt')->rightJoin('assignProjects','project.id','=','assignProjects.projectId')->where('assignProjects.userId',$user['id']);
                } else {
                    $projects = $projects->select($this->table.'.id', $this->table.'.name', $this->table.'.deployedAt')->rightJoin('assignProjects','project.id','=','assignProjects.projectId')->where('assignProjects.userId',$user['id']);
                    $projects = $projects->where('deployedAt', '!=', NULL);
                }

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'name' || $k == 'address' || $k == 'client'  || $k == 'noOfUnits' || $k == 'totalUnits' || $k == 'commercialSpace' || $k == 'totalCommercialSpace' || $k == 'size' || $k == 'totalSize' || $k == 'developerCompany';
                    }, ARRAY_FILTER_USE_KEY);

                    foreach ($search as $field => $value) {
                       $projects = $projects->where($this->table.'.'.$field, 'like', '%'.$value.'%');
                    }
                }
                //total records
                $totalRecords = $projects->count();

                $projects = $projects->orderBy($this->table.'.'.$sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $projects = $projects->get();

                $filteredRecords = count($projects);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "projects list.",
                ['data' => $projects],
                $pager);

        } catch (\Exception $e) {
            \Log::error("project index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing project",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function getProjects()
    {
        try
        {
            $project = Project::all();

            if(!count($project))
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Project doesn't exist.",
                    ['error' => 'Project does not exist.']);

            //send project information
            return createResponse(config('httpResponse.SUCCESS'),
                "project details",
                ['data' => $project]);

        } catch (\Exception $e) {
            \Log::error("project list api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get project list",
                ['error' => 'Could not get project lists.'.$e->getMessage()]);
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function projectCreate(Request $request)
    {
        try
        {
            $validator = app('validator')->make($request->all(), [
                //normal project details
                'name'                 => 'required',
                'address'              => 'required',
                'postalCode'           => 'required',
                //'state'                => 'required',
                'country'              => 'required',
                'threeDUrl'              => 'required',

                'client'               => 'required',
                'noOfUnits'            => 'required|numeric|max:1000|integer',
                'commercialSpace'      => 'required|numeric|integer',

                //developer details
                'developerCompany'     => 'required',
                'developerEmail'       => 'email',

                //architect details
                'architectCompany'     => 'required',
                'architectEmail'       => 'email',
                'image'                => 'image|mimes:jpeg,png,jpg,JPG,JPEG,PNG|max:300',

                //additional details
                //'additionalDeveloperName'       => 'required',
                //'additionalArchitectName'       => 'required',
                'additionalDeveloperEmail'      => 'email',
                'additionalArchitectEmail'      => 'email',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            // Upload image
            $image = "";
            if($request->hasFile('image'))
            {
                $errors = [];
                $image = 'project'.time().str_random(10).'_'.$request->file('image')->getClientOriginalExtension();
                $imagePath = base_path()."/public/images/projects/".$image;

                // Move and resize uploaded file
                if(!$request->file('image')->move(base_path()."/public/images/projects/", $image))
                    $errors[] = ['Could not upload project image! You may try again.'];

                if(count($errors))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Project image could not be processed. Please try to upload valid image.",
                        ['error' => "Project image could not be processed. Please try to upload valid image."]);
            }
            $user = getLoggedInUser();
            $template_ids = explode(",",$request->get('template_ids'));
            // store project details
            $project = Project::create([
                'name'              => $request->get('name'),
                'address'           => $request->get('address'),
                'postalCode'           => $request->get('postalCode'),
                'state'           => $request->get('state'),
                'country'           => $request->get('country'),
                'threeDUrl'           => $request->get('threeDUrl'),

                'client'            => $request->get('client'),
                'noOfUnits'         => $request->get('noOfUnits'),
                'commercialSpace'   => $request->get('commercialSpace'),
                'projectDescription'   => $request->get('projectDescription'),
                'image'             => $image,

                'developerCompany'  => $request->get('developerCompany'),
                'developerIncharge' => $request->get('developerIncharge'),
                'developerMobile'    => $request->get('developerMobile'),
                'developerEmail'   => $request->get('developerEmail'),
                'developerAddress'  => $request->get('developerAddress'),
                'developerPostalCode'  => $request->get('developerPostalCode'),
                'developerState'  => $request->get('developerState'),
                'developerCountry'  => $request->get('developerCountry'),

                'architectCompany'  => $request->get('architectCompany'),
                'architectIncharge' => $request->get('architectIncharge'),
                'architectMobile'    => $request->get('architectMobile'),
                'architectEmail'   => $request->get('architectEmail'),
                'architectAddress'  => $request->get('architectAddress'),
                'architectPostalCode'  => $request->get('architectPostalCode'),
                'architectState'  => $request->get('architectState'),
                'architectCountry'  => $request->get('architectCountry'),

                'createdBy'         => $user['id']
            ]);

            // Create additional details
            $additional = [];
            $additional[] = [
                'projectId' => $project->id,

                'additionalDeveloperName'=> $request->get('additionalDeveloperName'),
                'additionalDeveloperAuthority'=> $request->get('additionalDeveloperAuthority'),
                'additionalDeveloperIncharge'=> $request->get('additionalDeveloperIncharge'),
                'additionalDeveloperMobile'=> $request->get('additionalDeveloperMobile'),
                'additionalDeveloperEmail'=> $request->get('additionalDeveloperEmail'),
                'additionalDeveloperAddress'=> $request->get('additionalDeveloperAddress'),
                'additionalDeveloperPostalCode'=> $request->get('additionalDeveloperPostalCode'),
                'additionalDeveloperState'=> $request->get('additionalDeveloperState'),
                'additionalDeveloperCountry'=> $request->get('additionalDeveloperCountry'),

                'additionalArchitectName'=> $request->get('additionalArchitectName'),
                'additionalArchitectAuthority'=> $request->get('additionalArchitectAuthority'),
                'additionalArchitectIncharge'=> $request->get('additionalArchitectIncharge'),
                'additionalArchitectMobile'=> $request->get('additionalArchitectMobile'),
                'additionalArchitectEmail'=> $request->get('additionalArchitectEmail'),
                'additionalArchitectAddress'=> $request->get('additionalArchitectAddress'),
                'additionalArchitectPostalCode'=> $request->get('additionalArchitectPostalCode'),
                'additionalArchitectState'=> $request->get('additionalArchitectState'),
                'additionalArchitectCountry'=> $request->get('additionalArchitectCountry'),

                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            AdditionalProjectDetail::insert($additional);

            // Create units based on units count
            $units = [];
            for ($i=0; $i < ($request->get('noOfUnits') + $request->get('commercialSpace')); $i++)
                $units[] = ['projectId' => $project->id, 'status'=>'available', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'settled' => 'no', 'publish' => 'yes'];

            \App\Unit::insert($units);

            //Create template for projects
            $template = '';
            if($template_ids) {
                foreach($template_ids as $template_id) {
                    $template = ['projectId' => $project->id, 'templateId'=>$template_id, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
                    ProjectTemplate::insert($template);
                }

            }
            return createResponse(config('httpResponse.SUCCESS'),
                "The project has been added successfully.",
                ['message' => 'The project has been added successfully']);


        } catch (\Exception $e) {
            \Log::error("Project creation failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create project.",
                ['error' => 'Something went wrong. Could not create project.'.$e->getMessage()]);
        }
    }

    public function projectShowForClients($id)
    {
        try
        {
            $project = Project::where('project.id', $id);
            $project->with(['units','agents','affiliates','masterAgents', 'marketingFiles', 'additionalDetail', 'projectTemplates']);
            $project = $project->get();

            if(!count($project))
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Project doesn't exist.",
                    ['error' => 'Project does not exist.']);

            //send project information
            
            return createResponse(config('httpResponse.SUCCESS'),
                "project details",
                ['data' => $project->first()]);

        } catch (\Exception $e) {
            \Log::error("project details api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get project details",
                ['error' => 'Could not get project details.'.$e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectShow($id)
    {
        try
        {
            $user = getLoggedInUser();

            $project = Project::where('project.id', $id);

            switch ($user['role']) {
                case 'Admin':
                    $project->with(['units','agents','affiliates','masterAgents', 'marketingFiles', 'additionalDetail', 'projectTemplates']);
                    break;

                case 'User Manager':
                    $project->with(['units','agents','affiliates','masterAgents', 'marketingFiles', 'additionalDetail', 'projectTemplates']);
                    break;

                case 'Project Manager':
                    $project->with(['units','agents','affiliates','masterAgents', 'marketingFiles', 'additionalDetail', 'projectTemplates']);
                    break;

                case 'Master Agent' :
                    if(\App\ProjectAgent::where('projectId', $id)->where('userId', $user['id'])->count())
                        $project->with(['agents','affiliates'])
                            ->where('deployedAt', '!=', NULL)
                            ->with(['units'])->with('marketingFiles');
                    break;

                case 'Agent' :
                     $project->where('deployedAt', '!=', NULL)
                        ->with(['affiliates', 'marketingFiles'])
                        ->with(['units' => function($query) use ($user) {
                            $query->join('unitAgents', 'unitAgents.unitId', '=', 'unit.id')
                                ->where('unitAgents.userId', '=',$user['id'])
                                //->where('unit.publish','yes')
                                ->select('unit.*');
                        }]);
                    break;

                case 'Affiliate' :
                    $project->where('deployedAt', '!=', NULL)
                        ->with(['units' => function($query) use ($user) {
                            $query->join('unitAgents', 'unitAgents.unitId', '=', 'unit.id')
                                ->where('unitAgents.userId', '=',$user['id'])
                                //->where('unit.publish','yes')
                                ->select('unit.*');
                        }])->with('marketingFiles');
                    break;

                default:
                    # code...
                    break;
            }

            $project = $project->get();

            if(!count($project))
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Project doesn't exist.",
                    ['error' => 'Project does not exist.']);

            //send project information
            return createResponse(config('httpResponse.SUCCESS'),
                "project details",
                ['data' => $project->first()]);

        } catch (\Exception $e) {
            \Log::error("project details api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get project details",
                ['error' => 'Could not get project details.'.$e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectUpdate(Request $request, $id)
    {
        try
        {
            $validator = app('validator')->make($request->all(),[
                //'size'                 => 'numeric|integer',
                //'totalSize'            => 'numeric|integer',
                'developerEmail'       => 'email',
                'architectEmail'       => 'email',
                'additionalDeveloperEmail'  => 'email',
                'additionalArchitectEmail'  => 'email',
                'masterAgents'         => 'json',
                'image'                => 'image|mimes:jpeg,png,jpg,JPG,JPEG,PNG|max:300',
                'removeImage'          => 'in:0,1',
                'deploy'               => 'in:0,1',
                'delete'               => 'in:0,1',
                'marketing'            => 'array',
                'marketing.*'          => 'mimes:jpg,png,pdf,jpeg,ppt,JPG,JPEG,PNG,PDF,PPT|max:50000',
                ],['marketing.array'     =>  'Marketing files must be an array.',
                'marketing.*.mimes'    =>  'Only pdf,ppt,jpg and png files are allowed.',
                'marketing.*.max'      =>  'The file size should not be more than 50 MB.']);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $user = \App\User::find(getLoggedinUser('id'));
            $template_ids = explode(",",$request->get('template_ids'));

            switch ($user['role']) {
                case 'Admin':
                    $project = Project::where('createdBy',$user->id)->find($id);
                break;
                case 'Project Manager':
                    $project = Project::select('project.id')->join('assignProjects','project.id','=','assignProjects.projectId')
                                ->where('project.id',$id)
                                ->where('assignProjects.userId',$user->id)->first();
                break;
            }

            if(!$project)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Project doesn't exist.",
                    ['error' => 'Project does not exist.']);

            $updateData = array();
            $updateData = filterFields([
                'name', 'address', 'postalCode', 'state', 'country', 'threeDUrl',
                'client', 'projectDescription',
                'developerCompany', 'developerIncharge', 'developerEmail','developerMobile','developerAddress', 'developerPostalCode', 'developerState', 'developerCountry',
                'architectCompany','architectIncharge', 'architectEmail','architectMobile','architectAddress','architectPostalCode', 'architectState', 'architectCountry'], $request);

            if($request->has('deploy') && $request->get('deploy') == 1)
            {
                event(new DeploymentNoticeEvent($project));
                $updateData['deployedAt'] = \Carbon\Carbon::now();
            }

            // Agents assigned to unit
            if($request->has('masterAgents'))
            {
                $masterAgents  = json_decode($request->get('masterAgents'),true);

                $validator = app('validator')->make(['masterAgents'=> $masterAgents], [
                        'masterAgents'   => 'array|max:1',
                        'masterAgents.*' => 'numeric|exists:user,id,role,Master Agent'
                    ], []);

                if ($validator->fails())
                    return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.", ['error' => $validator->errors()->first()]);

                // To delete the extra master agent from table
                $agents = ProjectAgent::where(['projectId' => $id , 'role' => 'Master Agent'])->pluck('userId')->toArray();
                // Get removed data ids
                $removedData = array_diff($agents, $masterAgents);
                if(count($removedData))
                    ProjectAgent::whereIn('userId',$removedData)->where('projectId' , $id)->forceDelete();
                // Get newly added data
                $newData = array_diff($masterAgents, $agents);
                if(count($newData))
                {
                    $projectAgents = [];
                    foreach ($newData as $data)
                        $projectAgents[] = ['projectId' => $id, 'role' => 'Master Agent', 'userId' => $data, 'createdBy' => $user->id, 'created_at' => date('Y-m-d H:i:s')];

                    ProjectAgent::insert($projectAgents);
                }
            }

            // Image Upload
            $projectImage = "";
            if($request->hasFile('image'))
            {
                $existingImage = $project->image;
                $errors = [];
                $projectImage = 'project'.time().str_random(10).'_'.$request->file('image')->getClientOriginalExtension();
                $imagePath = base_path()."/public/images/projects/".$projectImage;

                // Move and resize uploaded file
                if(!$request->file('image')->move(base_path()."/public/images/projects/", $projectImage))
                    $errors[] = ['Could not upload project image! You may try again.'];

                if($existingImage != "" && file_exists(base_path()."/public/images/projects/".$existingImage))
                    unlink(base_path()."/public/images/projects/".$existingImage);

                if(count($errors))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Project image could not be processed. Please try to upload valid image.",
                        ['error' => "Project image could not be processed. Please try to upload valid image."]);

                $updateData['image'] = $projectImage;
            }

            // Marketing Image Upload
            if($request->hasFile('marketing'))
            {
                $marketings = $request->file('marketing');
                $this->uploadFiles($marketings, $id, 'marketing');
            }

            if($request->has('removeImage') && $request->get('removeImage'))
            {
                if($project->image != "" && file_exists(base_path()."/public/images/projects/".$project->image))
                    unlink(base_path()."/public/images/projects/".$project->image);

                $updateData['image'] = "";
            }

            $updateData['updatedBy'] = $user->id;
            $project->update($updateData);

            // Additional data update by project id
            $additional = AdditionalProjectDetail::where('projectId',$id)->first();
            if($additional) {

            $updateAdditionData = array();
            $updateAdditionData = filterFields([
                'additionalDeveloperName', 'additionalDeveloperAuthority', 'additionalDeveloperIncharge','additionalDeveloperMobile','additionalDeveloperEmail', 'additionalDeveloperAddress', 'additionalDeveloperPostalCode', 'additionalDeveloperState', 'additionalDeveloperCountry',
                'additionalArchitectName','additionalArchitectAuthority', 'additionalArchitectIncharge','additionalArchitectMobile','additionalArchitectEmail','additionalArchitectAddress', 'additionalArchitectPostalCode', 'additionalArchitectState', 'additionalArchitectCountry'], $request);


            $additional->update($updateAdditionData);
            }

            //Create template for projects
            $template = '';
            if($template_ids) {
                ProjectTemplate::where('projectId',$id)->delete();
                foreach($template_ids as $template_id) {
                    $template = ['projectId' => $project->id, 'templateId'=>$template_id, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
                    ProjectTemplate::insert($template);
                }

            }

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "The data has been updated successfully.",
                ['data' => $project, 'message' => 'The data has been updated successfully.']);

        } catch (\Exception $e) {
            \Log::error("project update ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update project",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function projectDeploy(Request $request, $id)
    {
        try {
            $user = \App\User::find(getLoggedinUser('id'));

            $project = null;
            switch ($user['role']) {
                case 'Admin':
                    $project = Project::where('createdBy', $user->id)->find($id);
                    break;
                case 'Project Manager':
                    $project = Project::select('project.id')->join('assignProjects', 'project.id', '=', 'assignProjects.projectId')
                        ->where('project.id', $id)
                        ->where('assignProjects.userId', $user->id)->first();
                    break;
            }

            if (!$project)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Project doesn't exist.",
                    ['error' => 'Project does not exist.']);

            $updateData = array();
            event(new DeploymentNoticeEvent($project));
            $updateData['deployedAt'] = \Carbon\Carbon::now();
            $project->update($updateData);

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "The project deployed successfully.",
                ['message' => 'The project deployed successfully.']);

        } catch (\Exception $e) {
            \Log::error("project update " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not deploy project",
                ['error' => 'Server error.' . $e->getMessage()]);
        }
    }

    public function projectDestroy(Request $request, $id)
    {
        try
        {
            $user = getLoggedinUser();
            // Check if user has verified password or not
            // if(! verifyPasswordToken(array_merge($request->all(), ['userId' => $user['id']])))
            //     return createResponse(config('httpResponse.UNPROCESSED'),
            //         "Password verification failed. Please try again.",
            //         ['error' => 'Password verification failed. Please try again.']);

            app('db')->beginTransaction();

            // Get project details
            $project = Project::where('createdBy',$user['id'])->where('id', $id)->get();

            if(!count($project))
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected project has already been deleted or you do not have privileges to delete the project.",
                    ['message' => 'Either selected project has already been deleted or you do not have privileges to delete the project.']);

            $project = $project->first();

            // Delete project image if exists
            if($project->image != "" && file_exists(base_path()."/public/images/projects/".$project->image))
                unlink(base_path()."/public/images/projects/".$project->image);

            // Delete project data
            $project->delete();
            // Detele unit, agent details
            Unit::where('projectId',$id)->delete();
            UnitAgent::where('projectId',$id)->delete();
            ProjectAgent::where('projectId',$id)->delete();
            AdditionalProjectDetail::where('projectId',$id)->delete();
            AssignProject::where('projectId',$id)->delete();

            $images = Images::where('projectId',$id)->get(['id','path','type']);
            foreach($images as $image)
            {
                if($image->path != ""  && file_exists(base_path()."/public/images/".$image->type."/".$image->path))
                    unlink(base_path()."/public/images/".$image->type."/".$image->path);
            }

            Images::where('projectId',$id)->delete();
            app('db')->commit();

            return createResponse(config('httpResponse.SUCCESS'),
                "The project has been deleted successfully.",
                ['message' => 'The project has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("project deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete project",
                ['error' => 'Server error.']);
        }
    }

    /**
     * Project assigned to user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function projectAssign(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'user_ids'                 => 'required',
//                'project_ids'              => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            $project_ids = $request->get('project_ids');
            $project_ids = $project_ids !== null ? $project_ids : [];
            $cancelled_ids = $request->get('cancelled_ids');
            $cancelled_ids = $cancelled_ids !== null ? $cancelled_ids : [];
            $user_ids = $request->get('user_ids');

//            $assign = [];
            $is_assign = false;
            $is_update = false;
            $is_delete = false;

            $assigned = [];
            foreach ($user_ids as $key => $uid) {
                foreach ($project_ids as $key => $id) {
                    $getAssign = AssignProject::where('projectId', $id)->where('userId' , $uid)->first();
                    if($getAssign === null) {
                        $is_assign = true;
                        AssignProject::create(['projectId' => $id, 'userId'=>$uid, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    } else {
                        $updateAssign = AssignProject::where('projectId', $id)->where('userId' , $uid)->where('indirect', 1)->first();
                        if ($updateAssign !== null) {
                            $is_update = true;
                            $updateAssign->indirect = 0;
                            $updateAssign->save();
                        }
                    }
                }
                foreach ($cancelled_ids as $key => $id) {
                    $getAssign = AssignProject::where('projectId', $id)->where('userId' , $uid)->first();
                    $assignedUnits = UnitAgent::where('projectId', $id)->where('userId', $uid)->first();

                    if($getAssign !== null) {
                        $is_delete = true;
                        if ($assignedUnits !== null) {
                            $getAssign->indirect = 1;
                            $getAssign->save();
                        } else {
                            $getAssign->delete();
                        }
                    }
                }

                $data = AssignProject::where('userId', $uid)->where('indirect', 0)->select('id', 'projectId')->get()->toArray();
                $assigned[] = $data;
            }

            if( $is_assign || $is_update || $is_delete) {
//                if ($is_assign) {
//                    AssignProject::insert($assign);
//                }

                return createResponse(config('httpResponse.SUCCESS'),
                "The project has been assign successfully.",
                ['message' => 'The project has been assign successfully', 'data' => $assigned]);
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'),
                "The project has already been assigned.",
                ['error' => "The project has already been assigned."]);
            }


        } catch (\Exception $e) {
            \Log::error("Project assign failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not assign project.",
                ['error' => 'Something went wrong. Could not assign project. '.$e->getMessage()]);
        }

    }

    public function staff(Request $request,$id)
    {
        try
        {
            //get loggedin user
            $userId = getLoggedInUser('id');

            //check validation
            $validator = app('validator')->make($request->all(),[
                'sortOrder'      => 'in:asc,desc',
                'pageNumber'     => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search'         => 'json',
                'isEnable'       => 'in:0,1'
            ],[]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

             //sorting
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : config('pager.sortBy');
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : config('pager.sortOrder');
            $pager = [];

             // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {

                $users = new User;
                $users = $users->select('user.*');

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'title' || $k == 'firstName' || $k == 'lastName' || $k == 'companyName'  || $k == 'office' || $k == 'address' || $k == 'isEnable' || $k == 'role';
                    }, ARRAY_FILTER_USE_KEY);

                    if(isset($search['role']))
                    {
                        switch ($search['role'][0]) {
                            case 'Project Manager':
                                $users = $users->join('project', 'user.id', '=','project.createdBy')->where('project.id',$id);
                                break;
                            case 'Master Agent':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                    ->where('projectAgents.projectId',$id)->where('projectAgents.role','Master Agent');
                                break;
                            case 'Agent':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                   ->where('projectAgents.projectId',$id)->where('projectAgents.role','Agent');
                                break;
                            case 'Affiliate':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                   ->where('projectAgents.projectId',$id)->where('projectAgents.role','Affiliate');
                                break;
                            default:
                                $users = $users;
                                break;
                        }
                        unset($search['role']);
                    }

                    foreach ($search as $field => $value)
                       $users = $users->where('user.'.$field, 'like', '%'.$value.'%');
                }
                $users = $users = $users->orderBy('user.'.$sortBy, $sortOrder)->distinct()->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;

                $take = $recordsPerPage;

                // Get user list
                $users = new User;
                $users = $users->select('user.*');
                 //dd($request->has('search'));
                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'title' || $k == 'firstName' || $k == 'lastName' || $k == 'companyName'  || $k == 'office' || $k == 'address' || $k == 'isEnable' || $k == 'role' || $k == 'name';
                    }, ARRAY_FILTER_USE_KEY);

                    if(isset($search['role']))
                    {
                        switch ($search['role'][0]) {
                            case 'Project Manager':
                                $users = $users->join('project', 'user.id', '=','project.createdBy')->where('project.id',$id);
                                break;
                            case 'Master Agent':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                    ->where('projectAgents.projectId',$id)->where('projectAgents.role','Master Agent');
                                break;
                            case 'Agent':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                   ->where('projectAgents.projectId',$id)->where('projectAgents.role','Agent');
                                break;
                            case 'Affiliate':
                                $users = $users->join('projectAgents', 'projectAgents.userId', '=', 'user.id')
                                   ->where('projectAgents.projectId',$id)->where('projectAgents.role','Affiliate');
                                break;
                            default:
                                $users = $users;
                                break;
                        }
                        unset($search['role']);
                    }

                    // Search users by name
                    if(isset($search['name']))
                    {
                       $users = $users->where(function($query) use ($search) {
                            $query->where('user.'.'firstName', 'like', '%'.$search['name'].'%')
                                ->orWhere('user.'.'lastName', 'like', '%'.$search['name'].'%');
                        });
                        unset($search['name']);
                    }

                    foreach ($search as $field => $value) {
                       $users = $users->where('user.'.$field, 'like', '%'.$value.'%');
                    }
                }
                //total records
                $totalRecords = $users->count();

                $users = $users->orderBy('user.'.$sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $users = $users->distinct()->get();

                $filteredRecords = count($users);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "user list.",
                ['data' => $users],
                $pager);

        } catch (\Exception $e) {
            \Log::error("user index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing user",
                ['error' => 'Server error.']);
        }
    }

    public function getProjectDocuments(Request $request, $project_id) {
        try
        {
            $ProjectDocumentCategories = ProjectDocumentCategory::all();
            $all_documents = [];
            foreach($ProjectDocumentCategories as $category) {
                $documents = new ProjectDocument;
                $documents = $documents->where('projectId', $project_id)->where('categoryId', $category->id)->get();
                $all_documents[] = array(
                    "id" => $category->id,
                    "category_title" => $category->title,
                    "files" => $documents
                );
            }

            $totalSize = ProjectDocument::where('projectId', $project_id)->sum('fileSize');
            return createResponse(config('httpResponse.SUCCESS'),
                "ProjectDocument list.",
                ['data' => [
                    "used" => $totalSize,
                    "categories" => $all_documents
                    ]
                ]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing Project Documents",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function uploadDocumentFiles(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'project_id' => 'required|exists:project,id',
                'category_id' => 'required|exists:projectDocumentCategories,id',
                'files' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

//            $allowedfileExtension = ['pdf','jpg','png','jpeg'];
            $files = $request->file('files');
            $data = [];
            $all_uploaded = true;
            $project_id = $request->get('project_id');
            $project = Project::find($project_id);
            $totalSize = ProjectDocument::where('projectId', $project_id)->sum('fileSize');
            $maxSize = $project->documentSpace*1048576;

            foreach ($files as $file)
            {
                $extension = $file->getClientOriginalExtension();
                $getSize = $file->getSize();
                if ($maxSize < $totalSize + $getSize) {
                    $all_uploaded = false;
                    break;
                }
//                $check = in_array($extension,$allowedfileExtension);

                if(true) { //  $check
                    $fileOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $fileOriginalName . '_' . str_random(6).'.'.$file->guessExtension();
                    Storage::disk('local')->put('projectDocuments/'.$fileName, file_get_contents($file));

                    $data[] = ProjectDocument::create([
                        "projectId" => $request->get('project_id'),
                        "categoryId" => $request->get('category_id'),
                        "originalName" => $file->getClientOriginalName(),
                        "filename" => $fileName,
                        'fileSize' => $getSize,
                    ]);
                } else {
                    app('db')->rollback();
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Invalid document format.",
                        ['error' => "Invalid document format"]);
                }
                $totalSize += $getSize;
            }

            //$data = ProjectImage::insert($projectImages);
            //$data = ProjectImage::create($projectImages);

            app('db')->commit();
            //check if Contact deployed successfully
            return createResponse(config('httpResponse.SUCCESS'),
                $all_uploaded ? "The documents has been added successfully." : "Cannot add more files due to your file warehouse size limit. Please upgrade for more space.",
                ['message' => 'The documents has been added successfully', 'data' => [ "files" => $data, "used" => $totalSize ]]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not Upload Documents.",
                ['error' => 'Something went wrong. Could not Upload Documents.'.$e->getMessage()]);
        }
    }

    public function deleteDocumentFile(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'file_ids' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $files = $request->get('file_ids');
            $deleted_size = 0;
            foreach ($files as $id)
            {
                $file = ProjectDocument::find($id);

                if(!$file) {
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "File doesn't exist.",
                        ['error' => 'File does not exist.']);
                }

                $existingFile = $file->filename;

                if(Storage::disk('local')->exists("projectDocuments/".$existingFile))
                    Storage::disk('local')->delete("projectDocuments/".$existingFile);
                $deleted_size += $file->fileSize;
                $file->delete();
            }
            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "File has been deleted successfully.",
                ['deleted_size' => $deleted_size ]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete file",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function downloadDocumentFile(Request $request, $id)
    {
        try
        {
            $projectDocument = ProjectDocument::find($id);

            if(!$projectDocument)
                return createResponse(config('httpResponse.UNPROCESSED'), "The document does not exist.", ['error' => 'The document does not exist.']);

            return response()->download(storage_path('app/projectDocuments/'.$projectDocument->filename));
        } catch (\Exception $e) {
            \Log::error("file download failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "File download failed.",
                ['error' => 'File download failed.']);
        }
    }

    public function getPanos($id)
    {
        try
        {
            $panoramas = new Panoramas;
            $panos = $panoramas->where('id', $id)->first();

            return createResponse(config('httpResponse.SUCCESS'),
                "Panos list for unit.",
                ['data' => $panos]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while panos listing ",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function createPanos(Request $request)
    {
        try
        {
            $unit_id =  $request->unit_id;
            $project_id = $request->project_id;
            $url = $request->url;
            $order = $request->order;
            $panoramas = new Panoramas;
            $panoramas->insert(
                ['unit_id' => $unit_id, 'project_id' => $project_id, 'url' => $url, 'order' => $order]
            );
            $id = $panoramas->where('unit_id', $unit_id)->select('id')->first();

            return createResponse(config('httpResponse.SUCCESS'),
                "created Panos list for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while create panos list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function updatePanos(Request $request)
    {
        try
        {
            $id = $request->id;
            $unit_id =  $request->unit_id;
            $project_id = $request->project_id;
            $url = $request->url;
            $order = $request->order;
            $panoramas = new Panoramas;
            $panoramas->where('id', $id)->update(
                ['unit_id' => $unit_id, 'project_id' => $project_id, 'url' => $url, 'order' => $order]
            );

            return createResponse(config('httpResponse.SUCCESS'),
                "updated Panos list for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while create panos list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function deletePanos($id)
    {
        try
        {
            $panoramas = new Panoramas;
            $panoramas->where('id', $id)->delete();

            return createResponse(config('httpResponse.SUCCESS'),
                "deleted Pano for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while delete panos list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function getPanoLink($id)
    {
        try
        {
            $panorama_links = new PanoramaLinks;
            $panorama_links = $panorama_links->where('id', $id)->first();

            return createResponse(config('httpResponse.SUCCESS'),
                "PanoLink list for unit.",
                ['data' => $panorama_links]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while panoLink listing ",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function createPanoLink(Request $request)
    {
        try
        {
            $origin =  $request->origin;
            $destination = $request->destination;
            $x = $request->x;
            $y = $request->y;
            $z = $request->z;
            $panorama_links = new PanoramaLinks;
            $panorama_links->insert(
                ['origin' => $origin, 'destination' => $destination, 'x' => $x, 'y' => $y, 'z' => $z]
            );
            $id = $panorama_links->where('origin', $origin)->select('id')->first();

            return createResponse(config('httpResponse.SUCCESS'),
                "created Panolink list for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while create panolink list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function updatePanoLink(Request $request)
    {
        try
        {
            $id = $request->id;
            $$origin =  $request->origin;
            $destination = $request->destination;
            $x = $request->x;
            $y = $request->y;
            $z = $request->z;
            $panorama_links = new PanoramaLinks;
            $panorama_links->where('id', $id)->update(
                ['origin' => $origin, 'destination' => $destination, 'x' => $x, 'y' => $y, 'z' => $z]
            );

            return createResponse(config('httpResponse.SUCCESS'),
                "updated Panolink list for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while update panolink list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function deletePanoLink($id)
    {
        try
        {
            $panorama_links = new PanoramaLinks;
            $panorama_links->where('id', $id)->delete();

            return createResponse(config('httpResponse.SUCCESS'),
                "deleted Panolink for unit.",
                ['data' => $id]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while delete panolink list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function getPanosForUnit(Request $request) 
    {
        try {
            $project_id = $request->project_id;
            $unit_id = $request->unit_id;

            $panoramas = new Panoramas;
            $temp = [];
            $last_temp = [];
            $result = [];
            
            $data = $panoramas->leftJoin('panoramalinks', 'panoramas.id', '=', 'panoramalinks.origin')->where([
                ['project_id', '=', $project_id],
                ['unit_id', '=', $unit_id],
            ])->select('panoramas.id', 'panoramas.url', 'panoramas.order', 'panoramalinks.destination', 'panoramalinks.x', 'panoramalinks.y', 'panoramalinks.z')->get();
            
            if(count($data) != 0){
                $item_id_temp = $data[0]->id;
                $item_url_temp = $data[0]->url;
                $item_order_temp = $data[0]->order;
                foreach( $data as $key => $item) {
                    if($item_id_temp == $item->id){
                        array_push($temp, ['id' => $item->destination, 'x' => $item->x, 'y' => $item->y, 'z' => $item->z]);
                        $last_temp = $temp;
                    }
                    if($item_id_temp != $item->id) {
                        array_push($result, ['id' => $item_id_temp, 'url' => $item_url_temp, 'order' => $item_order_temp, 'link' => $temp]);
                        $temp = [];
                        array_push($temp, ['id' => $item->destination, 'x' => $item->x, 'y' => $item->y, 'z' => $item->z]);
                        $last_temp = $temp;
                        $item_id_temp = $item->id;
                        $item_url_temp = $item->url;
                        $item_order_temp = $item->order;
                    }
                    if($key == count($data) - 1) {
                        array_push($result, ['id' => $item->id, 'url' => $item->url, 'order' => $item->order, 'link' => $last_temp]);
                    }
                }
            }
            return createResponse(config('httpResponse.SUCCESS'),
                "get Panos for unit.",
                ['data' => $result]
            );
        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while get panos for unit",
                ['error' => 'Server error.'.$e->getMessage()]);
        } 
    }

    public function projectPanoFilesUpload(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'project_id' => 'required|exists:project,id',
                'image_files' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            //get loggedin user
            $user = getLoggedInUser();
            $allowedfileExtension=['pdf','jpg','png'];
            $files = $request->file('image_files');
            $errors = [];
            $data = [];
            $projectImages = [];
            foreach ($files as $file)
            {
                $extension = $file->getClientOriginalExtension();
                $getSize = $file->getSize();
                $check = in_array($extension,$allowedfileExtension);

                if($check) {
                    $fileOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $fileName = $fileOriginalName . '_' . str_random(6).'.'.$file->guessExtension();
                    Storage::disk('local')->put('projectFiles/'.$fileName, file_get_contents($file));

                    $data[] = ProjectImage::create([
                            "projectId" => $request->get('project_id'),
                            "image" => $fileName,
                            'fileSize' => $this->size_as_kb($getSize),
                            'created_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    return createResponse(config('httpResponse.UNPROCESSED'),
                    "Invalid image format.",
                    ['error' => "Invalid image format"]);
                }
            }

            //$data = ProjectImage::insert($projectImages);
            //$data = ProjectImage::create($projectImages);

            app('db')->commit();
            //check if Contact deployed successfully
            return createResponse(config('httpResponse.SUCCESS'),
            "The Upload Image has been added successfully.",
            ['message' => 'The Upload Image has been added successfully', 'data' => $data]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not Upload Image.",
                ['error' => 'Something went wrong. Could not Upload Image.'.$e->getMessage()]);
        }


    }

    public function size_as_kb($yoursize) {
        if($yoursize < 1024) {
          return "{$yoursize} bytes";
        } elseif($yoursize < 1048576) {
          $size_kb = round($yoursize/1024);
          return "{$size_kb} KB";
        } else {
          $size_mb = round($yoursize/1048576, 1);
          return "{$size_mb} MB";
        }
    }

    public function projectPanoFilesDelete(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'project_id' => 'required|exists:project,id',
                'file_ids' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $files = $request->get('file_ids');


            foreach ($files as $id)
            {
                $file = ProjectImage::find($id);

                if(!$file)
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "File doesn't exist.",
                        ['error' => 'File does not exist.']);

                    $existingFile = $file->image;

                    if(Storage::disk('local')->exists("projectFiles/".$existingFile))
                        Storage::disk('local')->delete("projectFiles/".$existingFile);

                    $file->delete();

                }
                app('db')->commit();
                return createResponse(config('httpResponse.SUCCESS'),
                "File has been deleted successfully.",
                ['message' => 'File has been deleted successfully.']);


        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete file",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function projectPanoFilesList(Request $request, $project_id)
    {
        try
        {
            //get loggedin user
            $user = getLoggedInUser();
            $ProjectImage = new ProjectImage;

            $ProjectImage = $ProjectImage->where('projectId',$project_id)->get();

            return createResponse(config('httpResponse.SUCCESS'),
                "ProjectImage list.",
                ['data' => $ProjectImage]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing ProjectImage",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    function convertGADateString($string) {
        return substr($string, 0, 4).'-'.substr($string, 4, 2).'-'.substr($string, 6, 2);
    }

    public function confirmStorageUpgrade(Request $request) {
        try {
            // Validate User Plan
            $validator = app('validator')->make($request->all(), [
                'project_id' => 'required|exists:project,id',
                'title' => 'required',
            ], []);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }
            
            $storeage_type = 0; // Docuemnts: 1, Floor Plans: 2
            if ($request->get('title') == "Documents") {
                $storeage_type = 1;

            } else if ($request->get('title') == "Floor plans") {
                $storeage_type = 2;

            }

            $project_id = $request->get('project_id');
            $project = Project::find($project_id);
            $extra_Space = 100;

            if ($storeage_type == 1) {
                $existing_Space = $project->documentSpace;
                $new_Space = $existing_Space + $extra_Space;

                $project->update(['documentSpace' => $new_Space]);

            } else if ($storeage_type == 2) {
                $existing_Space = $project->floorplanSpace;
                $new_Space = $existing_Space + $extra_Space;

                $project->update(['floorplanSpace' => $new_Space]);
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "Upgrade Project Space success!",
                ['updateStorageSpace' => $new_Space]
            );
        } catch (\Exception $e) {
            \Log::error("Upgrading Project Space failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing Project Space ",
                ['error' => "Error while listing Project Space ".$e->getMessage()]);
        }
    }

    public function googleAnalytics() {
        $property_id = '265705074';

        $config = [
            'credentials' => base_path() . getenv('GOOGLE_ANALYTICS_KEY_PATH'),
            'projectId' => getenv('GOOGLE_ANALYTICS_PROJECT_ID'),
        ];

// Using a default constructor instructs the client to use the credentials
// specified in GOOGLE_APPLICATION_CREDENTIALS environment variable.
        try
        {
            $client = new BetaAnalyticsDataClient($config);

// Make an API call.
            $response = $client->runReport([
                'property' => 'properties/' . $property_id,
                'dateRanges' => [
                    new DateRange([
                        'start_date' => '14daysAgo',
                        'end_date' => 'today',
                    ]),
                ],
                'dimensions' => [new Dimension(
                    [
                        'name' => 'date',
                    ]
                ),
                ],
                'metrics' => [new Metric(
                    [
                        'name' => 'activeUsers',
                    ]
                )
                ],
                'keepEmptyRows' => true,
//                'orderBys' => [new OrderBy(
//                    [
//                        'dimension' => new DimensionOrderBy(
//                            ['dimension_name' => 'date', 'order_type' => DimensionOrderBy\OrderType::ALPHANUMERIC]
//                        )
//                    ])
//                ]
            ]);

            $ga_data = [];
            $data = [];
            $fetch_data = [];

            foreach ($response->getRows() as $row) {
                $key = $row->getDimensionValues()[0]->getValue();
                $val = $row->getMetricValues()[0]->getValue();
                $key = $this->convertGADateString($key);
                $fetch_data[$key] = intval($val);
            }

            $fetch_keys = array_keys($fetch_data);
            for ($i=13; $i>=0; $i--) {
                $d  = mktime(0, 0, 0, date("m")  , date("d")-$i, date("Y"));
                $key = date('Y-m-d', $d);
                if (in_array($key, $fetch_keys)) {
                    $data[] = array('key' => $key, 'users' => $fetch_data[$key]);
                } else {
                    $data[] = array('key' => $key, 'users' => 0);
                }

            }


            $ga_data[] = $data;

            return createResponse(config('httpResponse.SUCCESS'),
                "Analytics data.",
                ['data' => $ga_data]);

        } catch (\Exception $e) {
            \Log::error("Project assign failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not fetch data.",
                ['error' => 'Something went wrong. ' . $e->getMessage()]);
        }

    }
}