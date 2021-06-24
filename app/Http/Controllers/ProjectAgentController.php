<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\ProjectAgent;
use App\UnitAgent;
use App\Project;


class ProjectAgentController extends Controller
{

    /***** To Add project Agent/Affiliate  *****/

    public function store(Request $request)
    {
        try 
        {
            //check validation for the request
            $message = [
                'data.json'        => 'Invalid request',
                'users.required'   => 'Users value must be required',
                'users.array'      => 'Incorrect request',
                'users.*.distinct' => 'Users value must be unique',
                'users.*.integer'  => 'Users value is invalid'
            ];

            $validator = app('validator')->make($request->all(), [
                    'projectId' => 'required|integer',
                    'data'      => 'required|json'
                ], $message);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.", 
                    ['error' => $validator->errors()->first()]);
            }
            
            $data = json_decode($request->get('data'),true);
            
            $validator = app('validator')->make($data, [
                    'users'   => 'required|array',
                    'users.*' => 'numeric|distinct'
                ], $message);
            
            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.", 
                    ['error' => $validator->errors()->first()]);
            }

            $projectId = $request->get('projectId');
            //get loggedin user
            $user = getLoggedInUser();

            $project = Project::where('createdBy',$user['id'])->find($projectId);
            if(!$project) 
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Project does not exist.", 
                    ['error' => 'Project does not exist.']);

            // To delete the extra staff from table
            $staff = ProjectAgent::where(['projectId' => $projectId])->whereIn('role', ['Agent', 'Affiliate'])->pluck('userId')->toArray();

            $removedData = array_diff($staff, $data['users']);
            ProjectAgent::whereIn('userId',$removedData)->where('projectId' , $projectId)->forceDelete();

            // To remove all units assigned to given user
            UnitAgent::whereIn('userId',$removedData)->where('projectId' , $projectId)->delete();

            $userDetails = User::whereIn('id',$data['users'])->pluck('role','id');
            
            $users = array();
            foreach($data['users'] as $userId)
            {
                $staff = array();
                $role = $userDetails[$userId];
                
                // To reject the process if given user is other than Master Agent, Agent and Affiliate
                if(! in_array($role, ['Agent','Affiliate']))
                    return createResponse(config('httpResponse.UNPROCESSED'), 
                        "Only Agent/Affiliates are allowed to assign.", 
                        ['error' => 'Only Agent/Affiliates are allowed to assign.']);
            
                // To check if the given user has been already assigned or not for given project , if exists update else create new one 
                $projectDetails = ProjectAgent::where(['userId' => $userId,'projectId' => $projectId])->first();
                
                if(!$projectDetails) 
                {
                    $staff['userId']     =  $userId;
                    $staff['role']       =  $role;
                    $staff['projectId']  =  $projectId;
                    $staff['createdBy']  =  $user['id'];
                    $staff['created_at'] =  \Carbon\Carbon::now();
                    array_push($users,$staff);
                }
            }

            if(count($users) > 0)
                ProjectAgent::insert($users);
            //check if project deployed successfully
            return createResponse(config('httpResponse.SUCCESS'), 
                "Staff has been assigned successfully.",
                ['message' => 'Staff has been assigned successfully.']);

        } catch (\Exception $e) {
            \Log::error("Staff assignment failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Could not assign staff.",
                ['error' => 'Could not assign staff.']);
        }
        
    }
    
}	