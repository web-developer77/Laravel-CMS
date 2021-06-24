<?php

namespace App\Http\Controllers;

use App\AssignProject;
use App\Unit;
use App\User;
use App\Images;
use App\Message;
use App\Project;
use App\UnitAgent;
use App\unitFloor;
use App\unitPrice;
use App\ProjectAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UnitController extends Controller
{

    public function update(Request $request, $id)
    {
        try
        {
            $unit = Unit::find($id);
            //get loggedin user
            $user = User::find(getLoggedInUser('id'));

            // Check if the project has already been settled
            if($unit->settled == 'yes' && (! in_array($user->role, ['Project Manager', 'Master Agent'])))
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Could not update details. The unit has already been settled",
                    ['error' => 'Could not update details. The unit has already been settled']);


            // Create an array of fields which require password authentication in order to update it
            $restrictedFields = ['settled','price'];
            if(count(array_intersect_key(array_flip($restrictedFields), $request->input())) > 0)
            {
                // Check if user has verified password or not
                if(! verifyPasswordToken(['eventId' => $request->get('eventId'), 'token' => $request->get('token'), 'userId' => $user['id']]))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Password verification failed. Please try again.",
                        ['error' => 'Password verification failed. Please try again.']);
            }

            $message = [
                'floorplan.array'            =>  'Floorplan files must be an array.',
                'floorplan.*.mimes'          =>  'Only pdf,ppt,jpg and png files are allowed.',
                'floorplan.*.max'            =>  'The upload file size should not be greate than 1MB.',
                'floorplan.max'              =>  'Maximum two files are allowed for floorplan.',
                'agents.json'                =>  'Incorrect agents request.',
                'agents.max'                 =>  'Only one agent can be assinged to the project.',
                'affiliates.json'            =>  'Incorrect affiliates request.',
            ];

            $validator = app('validator')->make($request->all(),[
                'beds'              => 'numeric',
                'baths'             => 'numeric',
                'storage'           => 'numeric',
                'parking'           => 'numeric',
                'totalArea'         => 'numeric',
                'internalArea'      => 'numeric',
                'externalArea'      => 'numeric',
                'agents'            => 'json',
                'affiliates'        => 'json',
                'floorplan'         => 'array|max:2',
                'floorplan.*'       => 'mimes:jpg,png,pdf,jpeg,ppt,JPG,JPEG,PNG,PDF,PPT|max:1024',
                'price'             => 'numeric',
                'status'            => 'in:available,reserved,sold',
                'publish'           => 'in:yes,no',
                'settled'           => 'in:yes,no',
                ],$message);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $updateData =[];
            switch ($user->role)
            {
                case 'Project Manager':
                    if(! \App\Project::where(['createdBy' => $user->id, 'id' => $unit->projectId])->count()) // Check if the project belongs to the project manager
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);
                    $updateData = filterFields(['unitNo', 'floor', 'beds', 'baths','storage', 'totalArea', 'externalArea', 'internalArea', 'parking','price','status','publish','settled', 'link'], $request);
                    break;
                case 'Master Agent':
                    if(! \App\ProjectAgent::where([ 'projectId' => $unit->projectId, 'userId' => $user->id])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);
                    $updateData = filterFields(['price','status','settled'], $request);
                    break;
                case 'Agent':
                    if(! \App\UnitAgent::where(['projectId' => $unit->projectId, 'userId' => $user->id])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);
                    $updateData = filterFields(['status'], $request);
                        break;
                default:
                    $updateData = [];
                    break;
            }

            // Agents assigned to unit
            if($request->has('agents') && in_array($user['role'],['Project Manager','Master Agent']))
            {
                $agents  = json_decode($request->get('agents'),true);

                $validator = app('validator')->make(['agents'=> $agents], [
                        'agents'   => 'array',
                        'agents.*' => 'numeric|distinct|exists:user,id,role,Agent'
                    ], $message);

                if ($validator->fails())
                    return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.", ['error' => $validator->errors()->first()]);

                $this->createAgentOrAffiliate($agents, $unit,'Agent',$user['id']);
            }

            // Affiliates assigned to unit
            if($request->has('affiliates'))
            {
                $affiliates  = json_decode($request->get('affiliates'),true);
                $validator = app('validator')->make(['affiliates' => $affiliates], [
                        'affiliates'   => 'array',
                        'affiliates.*' => 'required|numeric|distinct|exists:user,id,role,Affiliate'
                    ], $message);

                if ($validator->fails())
                    return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

                $this->createAgentOrAffiliate($affiliates, $unit, 'Affiliate', $user['id']);
            }

            // Floorplan Image Upload
            if($request->hasFile('floorplan'))
            {
                if($user['role'] != 'Project Manager')
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Only project manager can upload the floor plan material.",
                        ['error' => 'Only project manager can upload the floor plan material.']);

                $existingFloorplansCount = Images::where(['unitId' => $unit->id, 'type' => 'floorplan'])->count();
                if($existingFloorplansCount + count($request->file('floorplan')) > 2)
                    return createResponse(config('httpResponse.UNPROCESSED'), "Maximum two floor plans can be uploaded.", ['error' => 'Maximum two floor plans can be uploaded.']);

                $floorplans = $request->file('floorplan');
                $this->uploadFiles($floorplans, $unit, 'floorplan');
            }

            $unit->update($updateData);

            // Get unit details
            $unit->agents;
            $unit->affiliates;
            $unit->floorplanImages;

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "The data has been updated successfully.",
                ['data' => $unit, "message" => "The data has been updated successfully."]);

        } catch (\Exception $e) {
            \Log::error("Unit updation failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unit",
                ['error' => 'Server error.']);
        }
    }

    public function show(Request $request, $id)
    {
        try
        {
            $user = getLoggedInUser();

            $unit = Unit::find($id);

            if(! $unit)
                return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not exist.", ['error' => 'The unit does not exist.']);

            switch ($user['role']) {
                case 'Project Manager':
                    if(! \App\Project::where('id', $unit->projectId)->where('createdBy', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                case 'Master Agent' :
                    if(!\App\ProjectAgent::where('projectId', $unit->projectId)->where('userId', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                case 'Agent' :
                case 'Affiliate' :
                    if(!\App\UnitAgent::where('projectId', $unit->projectId)->where('userId', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                default:
                    return createResponse(config('httpResponse.SERVER_ERROR'), "Could not get unit details", ['error' => 'Could not get unit details.']);
            }

            $unit = Unit::where('id', $id)->with(['floorplanImages','agents','affiliates', 'project'])->get()->first();

            //send project information
            return createResponse(config('httpResponse.SUCCESS'),
                "Unit details",
                ['data' => $unit]);

        } catch (\Exception $e) {
            \Log::error("unit details api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get unit details",
                ['error' => 'Could not get unit details.']);
        }
    }


    public function uploadFiles($files, $unit, $type)
    {
        $unitImages = [];
        foreach ($files as $file)
        {
            $fileOriginalName = $file->getClientOriginalName();
            $fileName =  $type.time().str_random(10).'.'.$file->guessExtension();

            Storage::disk('local')->put('unitFiles/'.$fileName, file_get_contents($file));

            $unitImages[] = ['unitId' => $unit->id, "name" =>$fileOriginalName, "projectId" => $unit->projectId, "type" => $type, "path" => $fileName, 'created_at' => date('Y-m-d H:i:s')];
        }

        Images::insert($unitImages);
    }

    public function createAgentOrAffiliate($data, $unit, $role, $createdBy)
    {
        // To delete the extra agent from table
        $agents = UnitAgent::where(['unitId' => $unit->id , 'role' => $role])->pluck('userId')->toArray();
        // Get removed data ids
        $removedData = array_diff($agents, $data);
        if(count($removedData))
            UnitAgent::whereIn('userId',$removedData)->where('unitId' , $unit->id)->delete();
        // Get newly added data
        $newData = array_diff($data, $agents);
        if(count($newData))
        {
            $unitAgents = [];
            foreach ($newData as $data)
                $unitAgents[] = ['unitId' => $unit->id, 'projectId' => $unit->projectId, 'role' => $role, 'userId' => $data, 'createdBy' => $createdBy, 'created_at' => date('Y-m-d H:i:s')];

            UnitAgent::insert($unitAgents);
        }
    }


    public function deleteImage($unitId, $id)
    {
        try
        {
            // GEt file details
            $image = Images::where('unitId',$unitId)->find($id);
            $user = getLoggedInUser();
            if(!$image)
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "File doesn't exist.",
                    ['error' => 'File does not exist.']);

             // Check if the project belongs to logged in user
            if(! \App\Project::where('id', $image->projectId)->where('createdBy', $user['id'])->count())
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "You do not have privilege to remove this file.",
                    ['error' => 'You do not have privilege to remove this file.']);



            $existingFile = $image->path;
            $type = $image->type;

            if(Storage::disk('local')->exists("unitFiles/".$existingFile))
                Storage::disk('local')->delete("unitFiles/".$existingFile);

            $image->delete();

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "Image has been deleted successfully.",
                ['message' => 'Image has been deleted successfully.']);

        } catch (\Exception $e) {
            \Log::error("Image deletion failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete image",
                ['error' => 'Server error.']);
        }
    }


    // Send an email to change the status of unit
    public function requestStatusChange(Request $request, $id)
    {
        try{

            $validator = app('validator')->make($request->all(),['status'   => 'required|in:available,reserved,sold'],[]);
            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $user = User::find(getLoggedInUser('id'));
            // Check if the affilate has been assigned to unit or not
            if(! \App\UnitAgent::where(['unitId' => $id, 'userId' => $user->id])->count())
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "You do not have privilege to request the status change.",
                    ['error' => 'You do not have privilege to request the status change.']);

            $unit = Unit::find($id);
            // Get agent details
            $agents = UnitAgent::where(['unitId' => $id, 'role' => 'Agent'])->with('user')->get();

            if(! count($agents))
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Could not send request. No agent has been assigned to the project yet.",
                    ['error' => 'Could not send request. No agent has been assigned to the project yet.']);


            foreach($agents as $agent)
            {
                // Send email
                App('mailer')->send('emails.statusChangeRequest', ['status' => $request->get('status'), 'unit' => $unit, 'user' => $user ,'agent' => $agent ], function ($mail) use ($agent) {
                        $mail->from('info@metroengine.com', 'Metroengine')
                            ->to($agent->user->email)
                            ->subject('Status change request | Metroengine');
                });

                // To send the message to Agent
                Message::create(['senderId' => $user['id'] ,'receiverId' => $agent->user->id ,'content' => $user['firstName'].' has requested to change the status of UnitNo:  '.$unit->unitNo.' to '.$request->get('status').'.']);
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "Request for unit status change has been sent to Agent successfully.",
                ['message' => 'Request for unit status change has been sent to Agent successfully.']);

        } catch (\Exception $e) {
            \Log::error("Unit status request failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not make the request.",
                ['error' => 'Could not make the request.']);
        }
    }

    public function downloadFile(Request $request, $id, $fileId)
    {
        try
        {
            $user = getLoggedInUser();

            $unit = Unit::find($id);

            if(! $unit)
                return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not exist.", ['error' => 'The unit does not exist.']);

            switch ($user['role']) {
                case 'Project Manager':
                    if(! \App\Project::where('id', $unit->projectId)->where('createdBy', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                case 'Master Agent' :
                    if(!\App\ProjectAgent::where('projectId', $unit->projectId)->where('userId', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                case 'Agent' :
                case 'Affiliate' :
                    if(!\App\UnitAgent::where('projectId', $unit->projectId)->where('userId', $user['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "The unit does not belongs to you.", ['error' => 'The unit does not belongs to you.']);
                    break;

                default:
                    return createResponse(config('httpResponse.SERVER_ERROR'), "Could not get unit details", ['error' => 'Could not get unit details.']);
            }

            $image = \App\Images::find($fileId);

            return response()->download(storage_path('app/unitFiles/'.$image->path));
        } catch (\Exception $e) {
            \Log::error("file download api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "File download failed.",
                ['error' => 'File download failed.']);
        }
    }

    public function updateMultiple(Request $request)
    {
        try
        {
            $units = json_decode($request->get('units'), true);
            $request->merge(['units' => $units]);

            $validator = app('validator')->make($request->all(),[
                'units'                   => 'required|array|min:1',
                'units.*.id'              => 'required|numeric',
                'units.*.beds'            => 'numeric',
                'units.*.baths'           => 'numeric',
                'units.*.storage'         => 'numeric',
                'units.*.parking'         => 'numeric',
                'units.*.totalArea'       => 'numeric',
                'units.*.internalArea'    => 'numeric',
                'units.*.externalArea'    => 'numeric',
                'units.*.price'           => 'numeric',
                'units.*.status'          => 'in:available,reserved,sold',
                'units.*.publish'         => 'in:yes,no',
                'units.*.settled'         => 'in:yes,no',
                ],[
                'units.*.id.required'          =>   'Unit id is required.',
                'units.*.beds.numeric'          =>  'Beds should be numeric value.',
                'units.*.baths.numeric'         =>  'Baths should be numeric value.',
                'units.*.storage.numeric'       =>  'Storage should be numeric value.',
                'units.*.totalArea.numeric'     =>  'Total area should be numeric value.',
                'units.*.internalArea.numeric'  =>  'Internal area should be numeric value.',
                'units.*.externalArea.numeric'  =>  'External area should be numeric value.',
                'units.*.price.numeric'         =>  'Price should be numeric value.',
                'units.*.status.in'             =>  'Status must be available, reserved, or sold.',
                'units.*.publish.in'            =>  'Publish must be yes or no.',
                'units.*.settled.in'            =>  'Settled must be yes or no.',
            ]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $unitIds = array_pluck($units, 'id');
            $oldUnits = Unit::whereIn('id', $unitIds)->get();

            //get loggedin user
            $user = User::find(getLoggedInUser('id'));

            // Check if unit
            foreach ($oldUnits as $oldUnit)
            {
                // Check if the project has already been settled
                if($oldUnit->settled == 'yes' && (! in_array($user->role, ['Project Manager', 'Master Agent'])))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "Could not update details. The unit ".$oldUnit->unitNo." has already been settled.",
                        ['error' => 'Could not update details. The unit '.$oldUnit->unitNo.' has already been settled.']);
            }

            // Create an array of fields which require password authentication in order to update it
            $restrictedFields = ['settled', 'price'];
            $requirePassword = false;
            foreach ($units as $unit)
            {
                if(count(array_intersect_key(array_flip($restrictedFields), $unit)) > 0)
                {
                    $requirePassword = true;
                    break;
                }
            }
            // Verify password details
            if($requirePassword && ! verifyPasswordToken(['eventId' => $request->get('eventId'), 'token' => $request->get('token'), 'userId' => $user['id']]))
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Password verification failed. Please try again.",
                    ['error' => 'Password verification failed. Please try again.']);


            $updateData =[];
            $projectIds = array_unique(array_pluck($oldUnits, 'projectId'));

            switch ($user->role)
            {
                case 'Project Manager':
                    if(! \App\Project::where('createdBy', $user->id)->whereIn('id', $projectIds )->count()) // Check if the project belongs to the project manager || Assuming that all the units to be udpated belongs to same project
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);

                    $filterFields = ['unitNo', 'floor', 'beds', 'baths','storage', 'totalArea', 'externalArea', 'internalArea', 'parking','price','status','publish','settled', 'link'];
                    break;
                case 'Master Agent':
                    if(! \App\ProjectAgent::whereIn('projectId', $projectIds)->where('userId', $user->id)->count() ) //  Assuming that all the units to be udpated belongs to same project
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);

                    $filterFields = ['price','status','settled'];
                    break;
                case 'Agent':
                    if(! \App\UnitAgent::whereIn('projectId', $projectIds)->where('userId', $user->id)->count()) //Assuming that all the units to be udpated belongs to same project
                        return createResponse(config('httpResponse.UNPROCESSED'), "It seems like the project does not belong to you.", ['error' => 'It seems like the project does not belong to you.']);
                    $filterFields = ['status'];
                        break;
                default:
                    $filterFields = [];
                    break;
            }

            // Update each units
            foreach ($units as $unit)
            {
                $updateData = filterFields($filterFields, $unit);
                Unit::where('id', $unit['id'])->update($updateData);
            }

            $units = Unit::whereIn('id', $unitIds)->with(['agents', 'affiliates', 'floorplanImages'])->get();

            return createResponse(config('httpResponse.SUCCESS'),
                "The data has been updated successfully.",
                ["message" => "The data has been updated successfully.", "units" => $units]);

        } catch (\Exception $e) {
            \Log::error("Multiple unit updation failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update units.",
                ['error' => 'Server error.']);
        }
    }

    public function assignAgentsAffiliates(Request $request)
    {
        try
        {
            $validator = app('validator')->make($request->all(),[
                'id'            => 'required',
                'users'         => 'array',
                'roles'          => 'required|array',
                'cancelled'     => 'array'
            ]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $userIds = $request->get('users');
            $cancelled = $request->get('cancelled');
            $unitId = $request->get('id');
            $roles = $request->get('roles');
            $unit = Unit::find($unitId);

            if (User::whereIn('id', $userIds)->whereNotIn('role', $roles)->count())
                return createResponse(config('httpResponse.UNPROCESSED'), "Some of them does not have right role to assign.", ['error' => "Some of them does not have right role to assign."]);

            $loggedinUser = getLoggedInUser();

            // To check if logged in user is allowed to assign unit
            switch ($loggedinUser['role']) {
                case 'Project Manager':
                case 'Master Agent' :
                    if(! AssignProject::where('projectId', $unit->projectId)->where('userId', $loggedinUser['id'])->count())
                        return createResponse(config('httpResponse.UNPROCESSED'), "Selected unit does not belong to you.", ['error' => "Selected unit does not belong to you."]);
                    break;

                default:
                    # code...
                    break;
            }

            $users = User::whereIn('id', $userIds)->get(['id', 'role']);

            $newUnitAgents = [];
            // Create unitAgent details
            foreach ($users as $user)
            {
                // Add new if does not exist
                if(! UnitAgent::where('userId', $user->id)->where('unitId', $unitId)->count())
                {
                    $newUnitAgents[] = [
                        'unitId'        => $unitId,
                        'projectId'     => $unit->projectId,
                        'role'          => $user->role,
                        'userId'        => $user->id,
                        'createdBy'     => $loggedinUser['id'],
                        'created_at'    => date('Y-m-d H:i:s')
                    ];
                }
                if (!AssignProject::where('userId', $user->id)->where('projectId', $unit->projectId)->count()) {
                    AssignProject::create([
                        "userId" => $user->id,
                        "projectId" => $unit->projectId,
                        "indirect" => 1
                    ]);
                }
            }

            if(count($newUnitAgents) > 0) UnitAgent::insert($newUnitAgents);

            // do cancelled
            foreach($cancelled as $key => $userId) {
                UnitAgent::where('userId', $userId)->where('unitId', $unitId)->delete();
                $stillAssignedProjectByUnit = UnitAgent::where('userId',$userId)->where('projectId', $unit->projectId)->count();
                if (!$stillAssignedProjectByUnit) {
                    AssignProject::where('userId', $userId)->where('projectId', $unit->projectId)->where('indirect', 1)->delete();
                }
            }

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "Assigned successfully.",
                ['message' => 'Assigned successfully.']);

        } catch (\Exception $e) {
            \Log::error("Multiple unit assign API failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not save the data.",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }


     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function unitCreate(Request $request)
    {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'project_id'          => 'required|exists:project,id',
                'units'              => 'required|array',
            ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            Unit::where('projectId', $request->get('project_id'))->forceDelete();

            $unitassign = [];
            $units = $request->get('units');
            $user = getLoggedInUser();

            foreach ($units as $key => $unit) {
                // store unit details
//                $unitassign[] =
                Unit::create([
                    'projectId'         => $request->get('project_id'),
                    'masterUnitNo'      => $unit['masterUnitNo'],
                    'unitNo'            => $unit['unitNo'],
                    'floor'             => $unit['floor'],
                    'beds'              => $unit['beds'],
                    'baths'             => $unit['baths'],
                    'cars'              => $unit['cars'],
                    'storage'           => $unit['storage'],
                    'totalArea'         => $unit['totalArea'],
                    'internalArea'      => $unit['intArea'],
                    'externalArea'      => $unit['extArea'],
                    'orientation'       => $unit['orientation'],
                    'status'            => $unit['status'],
                    'publish'           => $unit['publish'],
                    'exchanged'         => $unit['exchanged'],
                    'share'             => $unit['share'],
                    'stats'             => $unit['stats'],
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                    'isDisplayPrice'    => $unit['price']['isDisplayed'],
                    'isDisplayFloor'    => $unit['floorPlan']['isDisplayed']
                ]);
                $id = DB::getPdo()->lastInsertId();
                if($unit['price']) {
                    $price = $unit['price'];
                    $data = unitPrice::create([
                        'unitId'         => $id,
                        'userId'         => $user['id'],
                        'price'          => $price['price'],
                        'isActive'       => $price['isActive'] ? 1 : 0,
                    ]);
                }
            }

            return createResponse(config('httpResponse.SUCCESS'),
            "unit list.",
            ['data' => array()]
            );

        } catch (\Exception $e) {
            \Log::error("Unit creation failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create unit.",
                ['error' => 'Something went wrong. Could not create unit.'.$e->getMessage()]);
        }
    }

    public function unitDelete(Request $request) {
        try
        {
            Unit::where('id', $request->get('id'))->forceDelete();
            return createResponse(config('httpResponse.SUCCESS'),
                "Success.",
                null
            );

        } catch (\Exception $e) {
            \Log::error("Unit deletion failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not delete unit.",
                ['error' => 'Something went wrong. Could not delele unit.'.$e->getMessage()]);
        }
    }
    /**
     * Unit status to user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function unitFieldChange(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'fieldName'     => 'required',
                'fieldValue'    => 'required',

                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            if($request->has('id')) {

                $unitNo = Unit::where('id', $request->get('id'))->first();

                //Status update
                if($request->get('fieldName') == 'status') {
                    $updateData['status'] = $request->get('fieldValue');
                }

                //publish update
                if($request->get('fieldName') == 'publish') {
                    $updateData['publish'] = $request->get('fieldValue');
                }

                //publish update
                if($request->get('fieldName') == 'exchanged') {
                    $updateData['exchanged'] = $request->get('fieldValue');
                }

                $unitNo->update($updateData);
            } else {
                DB::table('unit')->update([$request->get('fieldName') => $request->get('fieldValue')]);
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "The unit has been update successfully.",
                ['message' => 'The unit has been update successfully']);

        } catch (\Exception $e) {
            \Log::error("unit update failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not update unit.",
                ['error' => 'Something went wrong. Could not update unit.'.$e->getMessage()]);
        }

    }


    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function unitByProjectId(Request $request, $projectId)
    {
        try
        {
            $units = Unit::where('unit.projectId',$projectId);

            $user = getLoggedInUser();
            switch ($user['role']) {
                case 'Agent' :
                case 'Affiliate':
                    $assigned = AssignProject::where('projectId', $projectId)->where('userId', $user['id'])->where('indirect', 0)->count();
                    if (!$assigned) {
                        $units = $units->join('unitAgents', 'unit.id', '=', 'unitAgents.unitId')
                        ->where('unitAgents.userId', $user['id'])
                        ->select('unit.*');
                    }
                    break;
            }

            $units = $units->with(['unitprice' =>  function ($query) {
                $query->select('unitPrices.id', 'unitPrices.unitId', 'unitPrices.userId', 'unitPrices.price', 'unitPrices.isActive', 'unitPrices.created_at', 'unitPrices.updated_at');
            }])->with(['floorPlan' =>  function ($query) {
                $query->select('unitFloors.id', 'unitFloors.unitId', 'unitFloors.userId', 'unitFloors.file', 'unitFloors.isActive', 'unitFloors.created_at', 'unitFloors.updated_at');
            }]);
            $units = $units->get();

            $data = [];
            foreach($units as $key => $unit) {

                $data[$key]['id'] = $unit->id;
                $data[$key]['projectId'] = $unit->projectId;
                $data[$key]['unitNo'] = $unit->unitNo;
                $data[$key]['masterUnitNo'] = $unit->masterUnitNo;
                $data[$key]['floor'] = $unit->floor;
                $data[$key]['beds'] = $unit->beds;
                $data[$key]['baths'] = $unit->baths;
                $data[$key]['storage'] = $unit->storage;
                $data[$key]['totalArea'] = $unit->totalArea;
                $data[$key]['internalArea'] = $unit->internalArea;
                $data[$key]['externalArea'] = $unit->externalArea;
                $data[$key]['stats'] = $unit->stats;
                $data[$key]['orientation'] = $unit->orientation;
                $data[$key]['cars'] = $unit->cars;
                $data[$key]['status'] = $unit->status;
                $data[$key]['publish'] = $unit->publish;
                $data[$key]['settled'] = $unit->settled;
                $data[$key]['link'] = $unit->link;
                $data[$key]['share'] = $unit->share;
                $data[$key]['exchanged'] = $unit->exchanged;
                $data[$key]['created_at'] = $unit->created_at->format('Y-m-d H:i:s');
                //$data[$key]['price']['data'] = $unit->unitprice;

                foreach($unit->unitprice as $p => $price) {
                    $data[$key]['price']['data'][$p]['id'] = $price->id;
                    $data[$key]['price']['data'][$p]['unitId'] = $price->unitId;
                    $data[$key]['price']['data'][$p]['entryMadeBy'] = $price->user->firstName .' '.$price->user->lastName;
                    $data[$key]['price']['data'][$p]['price'] = $price->price;
                    $data[$key]['price']['data'][$p]['isActive'] = $price->isActive;
                    $data[$key]['price']['data'][$p]['inputDate'] = $price->created_at->format('Y-m-d H:i:s');
                }
                $data[$key]['price']['isDisplayed'] = $unit->isDisplayPrice;

               // $data[$key]['floorPlan']['data'] = $unit->floorPlan;
               foreach($unit->floorPlan as $f => $floor) {
                    $data[$key]['floorPlan']['data'][$f]['id'] = $floor->id;
                    $data[$key]['floorPlan']['data'][$f]['unitId'] = $floor->unitId;
                    $data[$key]['floorPlan']['data'][$f]['entryMadeBy'] = $floor->user->firstName .' '.$floor->user->lastName;
                    $data[$key]['floorPlan']['data'][$f]['file'] = $floor->file;
                    $data[$key]['floorPlan']['data'][$f]['isActive'] = $floor->isActive;
                    $data[$key]['floorPlan']['data'][$f]['inputDate'] = $floor->created_at->format('Y-m-d H:i:s');
                }
                $data[$key]['floorPlan']['isDisplayed'] = $unit->isDisplayFloor;

            }

            $units = Unit::select(['id'])->where('projectId', $projectId)->get()->toArray();
            $units = array_map(function($n) { return $n["id"]; }, $units);
            $floors = unitFloor::whereIn('unitId', $units);
            $floorSize = $floors->sum('fileSize');

            return createResponse(config('httpResponse.SUCCESS'),
            "unit list.",
            ['data' => $data, "floorplanSize" => $floorSize, "units" => $units ]
            );

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing unit",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    /************************************* */
        /* Unit price module */
    /************************************* */
    public function unitPriceCreate(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'unitNo'             => 'required',
                'price'              => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

                $user = getLoggedInUser();

                $data = unitPrice::create([
                    'unitId'         => $request->get('unitNo'),
                    'userId'         => $user['id'],
                    'price'          => $request->get('price'),
                    'isActive'       => $request->get('isActive') ? 1 : 0,
                ]);

                Unit::where('id', $request->get('unitNo'))->update(['isDisplayPrice' => $request->get('isDisplayed') ? 1 : 0]);

                $data = unitPrice::where('id', $data->id)->with(['user' =>  function ($query) {
                    $query->select('id', DB::raw("CONCAT(firstName,' ',lastName) as fullname"));
                }])->get();

                //$data['fullname'] = 'asdad';
                return createResponse(config('httpResponse.SUCCESS'),
                "The price has been to the unit successfully.",
                ['message' => 'The price has been to the unit successfully', 'data' => $data]);

        } catch (\Exception $e) {
            \Log::error("Project assign failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not assign price.",
                ['error' => 'Something went wrong. Could not assign price.'.$e->getMessage()]);
        }

    }


    public function unitPriceDelete(Request $request, $id)
    {
        try
        {
            app('db')->beginTransaction();
            $user = getLoggedinUser();
            // Get unitPrice details
            $unitPrice = unitPrice::where('id', $id)->first();

            if($unitPrice === null)
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected unitprice has already been deleted or you do not have privileges to delete the unitprice.",
                    ['message' => 'Either selected unitprice has already been deleted or you do not have privileges to delete the unitprice.']);


            // Delete unit price data
            $unitPrice->delete();
            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unit price has been deleted successfully.",
                ['message' => 'The unit price has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("unit price deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete unit price",
                ['error' => 'Server error.']);
        }
    }


    public function unitPriceActivated(Request $request, $id)
    {
        try
        {
            app('db')->beginTransaction();

            $validator = app('validator')->make($request->all(), [
                'unitNo'             => 'required',
                'isActive'           => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            $user = getLoggedinUser();
            // Get unitPrice details
            $unitPrice = unitPrice::where('id', $id)->first();

            if($unitPrice === null)
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected unitprice has already been deleted or you do not have privileges to delete the unitprice.",
                    ['message' => 'Either selected unitprice has already been deleted or you do not have privileges to delete the unitprice.']);

            unitPrice::where('isActive', 1)->where('unitId', $request->get('unitNo'))->update(['isActive' => 0]);
            $unitPrice = $unitPrice->update(['isActive' => $request->get('isActive')]);

            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unitprice has been activated successfully.",
                ['message' => 'The unitprice has been activated successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("project activation failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unitprice",
                ['error' => 'Server error.']);
        }
    }


    public function unitUpdateDisplayPrice(Request $request)
    {
        try
        {
            app('db')->beginTransaction();

            $validator = app('validator')->make($request->all(), [
                'isDisplayed'           => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            $project_id = $request->projectId;
            DB::table('unit')->where('projectId', $project_id)->update(['isDisplayPrice' => $request->get('isDisplayed') ? 1 : 0]);

            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unit has been update successfully.",
                ['message' => 'The unit has been update successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("project deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unit",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    /************************************* */
        /* Unit floor module */
    /************************************* */

    public function unitFloorCreate(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'unitId'             => 'required',
                'file'              => 'required|mimes:jpeg,png,jpg,gif,svg,pdf',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

                $user = getLoggedInUser();
                $fileSize = 0;
                $unitId = $request->get('unitId');
                $unit = Unit::find($unitId);
                $project = Project::find($unit->projectId);

                $units = Unit::select(['id'])->where('projectId', $project->id)->get()->toArray();
                $units = array_map(function($n) { return $n["id"]; }, $units);
                $floors = unitFloor::whereIn('unitId', $units);
                $floorSize = $floors->sum('fileSize');

                $data = [];

                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $fileSize = $file->getSize();
                    if ($fileSize+$floorSize<$project->floorplanSpace*1048576) {
                        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $fileName = $fileName . '_' . (str_random(6)).'.'.$file->guessExtension();
                        $request->file('file')->move(base_path()."/public/images/unitfiles/", $fileName);

                        $data = unitFloor::create([
                            'unitId'        => $unitId,
                            'userId'        => $user['id'],
                            'file'          =>   $fileName,
                            'fileSize'      => $fileSize,
                            'isActive'      => $request->get('isActive')  == 'true' ? 1 : 0,
                        ]);
                        //$data['file'] = url('images/unitfiles/'.$fileName);
                        Unit::where('id', $unitId)->update(['isDisplayFloor' => $request->get('isDisplayFloor') == 'true' ? 1 : 0]);

                        $data = unitFloor::where('id', $data->id)->with(['user' =>  function ($query) {
                            $query->select('id', DB::raw("CONCAT(firstName,' ',lastName) as fullname"));
                        }])->get();
                    } else {
                        $fileSize = 0;
                    }
                }



                return createResponse(config('httpResponse.SUCCESS'),
                $fileSize > 0 ? "The unit floor has been to the created successfully." : "Cannot add more files due to your file warehouse size limit. Please upgrade for more space.",
                ['message' => 'The unit floor has been to the created successfully', 'data' => $data, "used" => $fileSize+$floorSize ]);

        } catch (\Exception $e) {
            \Log::error("Project assign failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create floor.",
                ['error' => 'Something went wrong. Could not create floor.'.$e->getMessage()]);
        }

    }

    public function unitFloorDelete(Request $request, $id)
    {
        try
        {
            app('db')->beginTransaction();
            $user = getLoggedinUser();
            // Get unitPrice details
            $unitFloor = unitFloor::where('id', $id)->first();

            if($unitFloor === null)
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected unitFloor has already been deleted or you do not have privileges to delete the unitFloor.",
                    ['message' => 'Either selected unitFloor has already been deleted or you do not have privileges to delete the unitFloor.']);

            // Delete project data
            $deleted_size = $unitFloor->fileSize;
            $unitFloor->delete();
            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unitfloor has been deleted successfully.",
                ['message' => 'The unitfloor has been deleted successfully.', "freed" => $deleted_size ]);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("unitfloor deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete unitfloor",
                ['error' => 'Server error.']);
        }
    }

    public function unitFloorActivated(Request $request, $id)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'unitNo'             => 'required',
                'isActive'           => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            $user = getLoggedinUser();
            // Get unitFloor details
            $unitFloor = unitFloor::where('id', $id)->first();

            if($unitFloor === null)
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected unitFloor has already been deleted or you do not have privileges to delete the unitFloor.",
                    ['message' => 'Either selected unitFloor has already been deleted or you do not have privileges to delete the unitFloor.']);

            unitFloor::where('isActive', 1)->where('unitId', $request->get('unitNo'))->update(['isActive' => 0]);
            $unitFloor = $unitFloor->update(['isActive' => $request->get('isActive')]);

            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unitFloor has been activated successfully.",
                ['message' => 'The unitFloor has been activated successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unitFloor",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function unitUpdateDisplayFloor(Request $request)
    {
        try
        {
            app('db')->beginTransaction();

            $validator = app('validator')->make($request->all(), [
                'isDisplayFloor'           => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);
            
            $project_id = $request->projectId;
            DB::table('unit')->where('projectId', $project_id)->update(['isDisplayFloor' => $request->get('isDisplayFloor') ? 1 : 0]);

            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unit has been update successfully.",
                ['message' => 'The unit has been update successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unit",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function unitDisplay(Request $request)
    {

        try
        {
            $validator = app('validator')->make($request->all(), [
                'unitFor'             => 'required',
                'unitNo'              => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

                $user = getLoggedInUser();
                $unitId = $request->get('unitNo');
                $isDisplayed = $request->get('isDisplayed');

                if($request->get('unitFor') == 'floorPlan'){
                    $unit = unitFloor::where('unitId', $unitId)->with(['unit' =>  function ($query) use ($isDisplayed) {
                        $query->select('id', 'projectId', 'unitNo', 'isDisplayFloor')->where('isDisplayFloor', '=', $isDisplayed);
                    }])->get();
                }
                if($request->get('unitFor') == 'price'){
                    $unit = unitPrice::where('unitId', $unitId)->with(['unit' =>  function ($query) use ($isDisplayed) {
                        $query->select('id', 'projectId', 'unitNo', 'isDisplayPrice')->where('isDisplayPrice', '=', $isDisplayed);
                    }])->get();
                }

                return createResponse(config('httpResponse.SUCCESS'),
                "The unit listing.",
                ['message' => 'The unit listing', 'data' => $unit]);

        } catch (\Exception $e) {
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not list unit.",
                ['error' => 'Something went wrong. Could not list unit.'.$e->getMessage()]);
        }



    }

    public function unitIsDisplayedChange(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'unitNo'             => 'required',
                'dataFor'           => 'required',
                'isDisplayed'           => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            $user = getLoggedinUser();
            $unitId = $request->get('unitNo');
            $isDisplayed = $request->get('isDisplayed');

             // Get unit details
             $unit = Unit::where('id', $unitId)->first();

             if($unit === null)
                 return createResponse(config('httpResponse.SUCCESS'),
                     "Either selected unit has already been deleted or you do not have privileges to delete the unit.",
                     ['message' => 'Either selected unit has already been deleted or you do not have privileges to delete the unit.']);


            if($request->get('dataFor') == 'floorPlan'){
                Unit::where('id', $unitId)->update(['isDisplayFloor' => $request->get('isDisplayed') == 'true' ? 1 : 0]);
            }

            if($request->get('dataFor') == 'price'){
                Unit::where('id', $unitId)->update(['isDisplayPrice' => $request->get('isDisplayed') == 'true' ? 1 : 0]);
            }


            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The unit has been activated successfully.",
                ['message' => 'The unit has been activated successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update unit",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }
}
