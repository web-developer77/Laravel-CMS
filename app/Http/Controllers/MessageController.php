<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Message;
use App\Events\SendMessageEvent;


class MessageController extends Controller
{   
    /***** To send message *****/

    public function store(Request $request)
    {
        try 
        {
            //check validation for the request
            $message = [
                'receiver.json'            =>  'Incorrect receiver request.',
                'receiver.array'           =>  'Invalid array format of receivers.',
                'receiver.*.distinct'      =>  'Receivers value must be unique.',
                'receiver.*.integer'       =>  'Receivers value is invalid.',
                'receiver.*.exists'        =>  'No such Receiver exist.',
            ];

            $validator = app('validator')->make($request->all(), [
                    'content'       => 'required',
                    'receiver'      => 'required|json'
                ], $message);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.", 
                    ['error' => $validator->errors()->first()]);
            }
            
            $data = json_decode($request->get('receiver'),true);
           
            $validator = app('validator')->make(['receiver'=>$data], [
                    'receiver'   => 'required|array',
                    'receiver.*' => 'numeric|distinct|exists:user,id'
                ], $message);
            
            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.", 
                    ['error' => $validator->errors()->first()]);
            }

            //get loggedin user
            $user = getLoggedInUser();

            $data  = json_decode($request->get('receiver'),true);
            
            $messages = array();
            foreach($data as $userId)
            {
                $messageDetail = array();
                $messageDetail['senderId']      =  $user['id'];
                $messageDetail['receiverId']    =  $userId;
                $messageDetail['content']       =  $request->get('content');
                $messageDetail['created_at']    =  date('Y-m-d H:i:s');

                array_push($messages,$messageDetail);
            }
            
            if(count($messages) > 0)
            {
                Message::insert($messages);
                event(new SendMessageEvent(['receiver'=>$data , 'message' => $request->get('content') ,'userId' => $user['id'] ]));
            }

            //check if message sent successfully
            return createResponse(config('httpResponse.SUCCESS'), 
                "Message has been sent successfully.",
                ['message' => 'Message has been sent successfully.']);

        } catch (\Exception $e) {
            \Log::error("Message sending failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Could not send message.",
                ['error' => 'Could not send message.']);
        }
        
    }

    public function markRead($messages, $userId = NULL)
    {
        $messageIds = array();
        foreach ($messages as $message) 
        {
            $messageIds[] = $message->id;
        }
        if($userId)
            Message::where('status', 0)->where('receiverId', $userId)->update(['status' => 1]);
        else
            Message::where('status', 0)->update(['status' => 1]);
    }

    public function getTotalUnread(Request $request)
    {
        try 
        {
            //get loggedin user
            $userId = getLoggedInUser('id');
            $count = Message::select(\DB::raw("count(*) as count"))
                ->where('receiverId', $userId)
                ->where('status',0)->value('count');
            
            return createResponse(config('httpResponse.SUCCESS'), 
                "total unread messages.",
                ['data' => $count]);

        } catch (\Exception $e) {
            \Log::error("Message sending failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Unable to fetch total unread messages.",
                ['error' => 'Unable to fetch total unread messages.']);
        }
    }

    public function index(Request $request)
    {
        try 
        {
            //get loggedin user
            $userId = getLoggedInUser('id');

            $pager = [];
             //check validation
            $validator = app('validator')->make($request->all(),[
                'previous'       => 'numeric|exists:message,id',
                'next'           => 'numeric|exists:message,id',
                'recordsPerPage' => 'numeric|min:0',
            ],[]);   
            
            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }
            
            $messages = new Message;
            $messages = $messages->select('message.*');

            $messages = $messages->where(function($q) use ($userId) {
                  $q->where('senderId', $userId)
                    ->orWhere('receiverId', $userId);
            });

            // Check if all records are requested 
            if(!$request->has('records') && $request->get('records') != 'all') 
            {
               // Define pager parameters
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $previous = ($request->has('previous')) ? $request->get('previous') : 1;
                $next = ($request->has('next')) ? $request->get('next') : 1;

                $take = $recordsPerPage;

                //total records
                $totalRecords = $messages->count();

                if($request->has('previous'))
                    $messages = $messages->where('id','<',$previous)->take($take);
                else if($request->has('next'))
                    $messages = $messages->where('id','>',$next);
                else
                    $messages = $messages->take($take);
            }

            $messageList = $messages->get();
            $this->markRead($messageList, $userId);

            $messages = $messages->orderBy('id', 'DESC')->get();

            $filteredRecords = count($messages);

            if(!$request->has('records') && $request->get('records') != 'all')
                $pager = [
                    'previous'          => $previous,
                    'next'              => $next,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];

            $userIds = array();
            $totalMessage = count($messages);

            for($i=0;$i < $totalMessage; $i++)
            {
                $userIds[] = $messages[$i]->senderId;
                $userIds[] = $messages[$i]->receiverId;
            }
            
            $users = User::select('user.*')->whereIn('id',$userIds)->get();
        
            $totalUser = count($users);

            for($j=0;$j < $totalUser; $j++)
            {
                for($i=0;$i < $totalMessage; $i++)
                {
                    if($messages[$i]->senderId != $userId && $messages[$i]->senderId == $users[$j]->id)
                    {
                        $messages[$i]->name      = $users[$j]->title.'. '.$users[$j]->firstName.' '.$users[$j]->lastName;
                    }   
                    else if($messages[$i]->receiverId != $userId && $messages[$i]->receiverId == $users[$j]->id)
                    {
                        $messages[$i]->name      = $users[$j]->title.'. '.$users[$j]->firstName.' '.$users[$j]->lastName; 
                    }
                }
            }
    
            return createResponse(config('httpResponse.SUCCESS'), 
                "message list.",
                ['data' => $messages],
                $pager);

        } catch (\Exception $e) {
            \Log::error("message index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing message",
                ['error' => 'Server error.']);
        }
    }

    public function userMessages(Request $request,$id)
    {
        try 
        {
            //check validation
            $validator = app('validator')->make($request->all(),[
                'previous'       => 'numeric|exists:message,id',
                'next'           => 'numeric|exists:message,id',
                'recordsPerPage' => 'numeric|min:0',
            ],[]);    
            
            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $pager = [];

            $user = User::find($id);
            $userId = $user->id;
            if(!$user) 
                return createResponse(config('httpResponse.SERVER_ERROR'), 
                    "User doesn't exist.",
                    ['error' => 'User does not exist.']);
            
            $messages = new Message;
            $messages = $messages->select('message.*');

            $messages = $messages->where(function($q) use ($userId) {
                  $q->where('senderId', $userId)
                    ->orWhere('receiverId', $userId);
            });

            // Check if all records are requested 
            if(!$request->has('records') && $request->get('records') != 'all') 
            {
               // Define pager parameters
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $previous = ($request->has('previous')) ? $request->get('previous') : 1;
                $next = ($request->has('next')) ? $request->get('next') : 1;

                $take = $recordsPerPage;

                //total records
                $totalRecords = $messages->count();

                if($request->has('previous'))
                    $messages = $messages->where('id','<',$previous)->take($take);
                else if($request->has('next'))
                    $messages = $messages->where('id','>',$next);
                else
                    $messages = $messages->take($take);
            }

            $messageList = $messages->get();
            //$this->markRead($messageList);

            $messages = $messages->orderBy('id', 'DESC')->get();

            $filteredRecords = count($messages);

            if(!$request->has('records') && $request->get('records') != 'all')
                $pager = [
                    'previous'          => $previous,
                    'next'              => $next,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];

            $userIds = array();
            $totalMessage = count($messages);

            for($i=0;$i < $totalMessage; $i++)
            {
                $userIds[] = $messages[$i]->senderId;
                $userIds[] = $messages[$i]->receiverId;
            }
            
            $users = User::select('user.*')->whereIn('id',$userIds)->get();
        
            $totalUser = count($users);

            for($j=0;$j < $totalUser; $j++)
            {
                for($i=0;$i < $totalMessage; $i++)
                {
                    if($messages[$i]->senderId != $userId && $messages[$i]->senderId == $users[$j]->id)
                    {
                        $messages[$i]->name      = $users[$j]->title.'. '.$users[$j]->firstName.' '.$users[$j]->lastName;
                    }   
                    else if($messages[$i]->receiverId != $userId && $messages[$i]->receiverId == $users[$j]->id)
                    {
                        $messages[$i]->name      = $users[$j]->title.'. '.$users[$j]->firstName.' '.$users[$j]->lastName; 
                    }
                }
            }
        
            return createResponse(config('httpResponse.SUCCESS'), 
                "message list.",
                ['data' => $messages],
                $pager);

        } catch (\Exception $e) {
            \Log::error("message index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing message",
                ['error' => 'Server error.']);
        }
    }

    public function destroy($id)
    {
        try
        {
            //get loggedin user
            $userId = getLoggedInUser('id');
            
            $message = Message::where(function($q) use ($userId) {
                  $q->where('senderId', $userId)
                    ->orWhere('receiverId', $userId);
            })->find($id);

            if(!$message) 
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Message doesn't exist.",
                    ['error' => 'Message does not exist.']);

            $message->delete();

            //check if Message deleted successfully
            return createResponse(config('httpResponse.SUCCESS'), 
                "Message has been deleted successfully.",
                ['message' => 'Message has been deleted successfully.']);

        } catch (\Exception $e) {
            \Log::error("Message deletion failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Could not delete Message",
                ['error' => 'Server error.']);
        }
    }

    /*public function index(Request $request,$id)
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

                $messages = new Message;

                $messages = $messages->where(function($q) use ($userId) {
                      $q->where('senderId', $userId)
                        ->orWhere('receiverId', $userId);
                });

                $messages = $messages->where(function($q) use ($id) {
                      $q->where('senderId', $id)
                        ->orWhere('receiverId', $id);
                });

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'content' || $k == 'status' || $k == "created_at";
                    }, ARRAY_FILTER_USE_KEY);

                    if(isset($search['status']))
                    {   
                        $messages = $messages->where('status', $search['status']);
                        unset($search['status']);
                    }

                    foreach ($search as $field => $value)
                       $messages = $messages->where($field, 'like', '%'.$value.'%');
                }
                $messages =  $messages->orderBy($sortBy, $sortOrder)->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;

                $take = $recordsPerPage;
               
                // Get message list
                $messages = new Message;
                $messages = $messages->where(function($q) use ($userId) {
                      $q->where('senderId', $userId)
                        ->orWhere('receiverId', $userId);
                });

                $messages = $messages->where(function($q) use ($id) {
                      $q->where('senderId', $id)
                        ->orWhere('receiverId', $id);
                });

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'content' || $k == 'status' || $k == "created_at";
                    }, ARRAY_FILTER_USE_KEY);

                    if(isset($search['status']))
                    {   
                        $messages = $messages->where('status',$search['status']);
                        unset($search['status']);
                    }

                    foreach ($search as $field => $value) {
                       $messages = $messages->where($field, 'like', '%'.$value.'%');
                    }
                }

                //total records
                $totalRecords = $messages->count();
               
                $messages = $messages->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $messageList = $messages->get();
                $this->markRead($messageList);

                $messages = $messages->get();

                $filteredRecords = count($messages);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 
                "message list.",
                ['data' => $messages], 
                $pager);

        } catch (\Exception $e) {
            \Log::error("message index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing message",
                ['error' => 'Server error.']);
        }
    }
*/
   /* public function users(Request $request)
    {
        try 
        {
            //get loggedin user
            $userId = getLoggedInUser('id');

            $userIds = array();

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

            $query = 'select m.* from
                message m
                inner join (
                select max(id) as maxid
                from message
                where (message.senderId = "'.$userId.'"  OR message.receiverId = "'.$userId.'")
                group By (if(senderId > receiverId,  senderId, receiverId)), 
                (if(senderId > receiverId,  receiverId, senderId))
               ) t1 on m.id=t1.maxid order by '.$sortBy.' '.$sortOrder.' ';

             // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $messages = \DB::select($query);

                $pager = [];
            }   
            else 
            { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;
                $take = $recordsPerPage;

                $messageList = \DB::select($query);
                //total records
                $totalRecords = count($messageList);
                $messages = \DB::select($query." LIMIT $take OFFSET $skip");
                $filteredRecords = count($messages);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            $totalMessage = count($messages);

            for($i=0;$i < $totalMessage; $i++)
            {
                if($messages[$i]->senderId != $userId)
                    $userIds[] = $messages[$i]->senderId;
                else if($messages[$i]->receiverId != $userId)
                    $userIds[] = $messages[$i]->receiverId;
            }

            $users = User::select('user.*',\DB::raw("(SELECT COUNT(message.id) FROM message WHERE (message.senderId = user.id OR message.senderId = ".$userId.") and (message.receiverId = user.id OR message.receiverId = ".$userId.") and message.status=0 GROUP BY message.senderId,message.receiverId) as totalUnread"))->whereIn('id',$userIds)->get();

            $totalUser = count($users);
                
            for($j=0;$j < $totalUser; $j++)
            {
                for($i=0;$i < $totalMessage; $i++)
                {
                    if($messages[$i]->senderId != $userId && $messages[$i]->senderId == $users[$j]->id)
                    {
                        $messages[$i]->userId       = $users[$j]->id; 
                        $messages[$i]->title    = $users[$j]->title;
                        $messages[$i]->name     = $users[$j]->firstName.' '.$users[$j]->lastName;
                        $messages[$i]->mobile   = $users[$j]->mobile;
                        $messages[$i]->totalUnread    = $users[$j]->totalUnread;
                        $messages[$i]->email    = $users[$j]->email;
                        $messages[$i]->image    = $users[$j]->image;

                    }
                    else if($messages[$i]->receiverId != $userId && $messages[$i]->receiverId == $users[$j]->id)
                    {
                        $messages[$i]->userId        = $users[$j]->id;
                        $messages[$i]->title     = $users[$j]->title;
                        $messages[$i]->name      = $users[$j]->firstName.' '.$users[$j]->lastName; 
                        $messages[$i]->mobile    = $users[$j]->mobile;
                        $messages[$i]->totalUnread    = $users[$j]->totalUnread;
                        $messages[$i]->email     = $users[$j]->email;
                        $messages[$i]->image     = $users[$j]->image;
                    }
                }
            }
           
            // search logic    
            if ($request->has('search')) {
                // Decode json in to php array
                $search = json_decode($request->get('search'),true);
                // Get only required params
                $search = array_filter($search, function($k){
                    return  $k == 'title' || $k == 'name' || $k == 'email' || $k == 'mobile' || $k = 'content';
                }, ARRAY_FILTER_USE_KEY);

                $result = array();
                foreach ($search as $field => $value)
                {
                    foreach ($messages as $message){
                        $filter = strtoupper($value);
                        if (strpos(strtoupper($message->$field), $filter) !== FALSE){
                            $result[] = $message;
                        }
                    }
                }    
            }
            else
                $result = $messages;

            return createResponse(config('httpResponse.SUCCESS'), 
                    "user list.",
                    ['data' => $result], 
                    $pager);

        } catch (\Exception $e) {
            \Log::error("user index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing user",
                ['error' => 'Server error.']);
        }
    }*/

   
}