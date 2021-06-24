<?php

namespace App\Http\Controllers;

use App\Product;
use App\Unit;
use App\User;
use App\GuestUser;
use App\Project;
use App\UnitAgent;
use App\UserInvite;
use App\ProjectAgent;
use App\AssignProject;
use App\ContactFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{    

    public function indextest(){
        return view('index');
    }
    public function createAdmin()
    {
        try
        {
            // Check if admin already exist with given email address
            $user = \App\User::where('email', 'admin@metroengine.com.au')->get();
            if(count($user))
                return createResponse(422, "Email already exists.", ['error' => "It seems like entered email address already exists in the system."]);

             // Create user
             $user = User::create([
                'title'            => 'Mr',
                'firstName'        => 'admin',
                'lastName'         => 'admin',
                'email'            => 'admin@metroengine.com.au',
                'password'         => app('hash')->make('12345678'),
                'role'             => 'Admin'
            ]);

            $contactFolder = ContactFolder::create([
                'folderName'  => 'All Contacts',
                'userId'    => $user->id,
                'isDelete' => 0
            ]);


            return createResponse(200,  "Admin Sign up successful.", ['message' => "Admin have signed up successfully."]);

        } catch (\Exception $e) {

            \Log::error("Admin registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!'.$e->getMessage()]);
        }
    }

    public function registerByAdmin(Request $request)
    {
        try
        {
            $Loggeduser = getLoggedInUser();

            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'title'         => 'required',
                'firstName'     => 'required',
                'lastName'      => 'required',

                'email'         => 'required|email|unique:user',
                'url'           => 'required',
                'role'          => 'required',
                'address'       => 'required',

                //'state'         => 'required',
                'postalCode'         => 'required',
                'country'         => 'required',

                'mobile'        => 'required',

            ],[]);

            if ($validator->fails())
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);

            if ($Loggeduser['role'] == 'User Manager') {
                if (!in_array($request->get('role'), ['Agent', 'Affiliate'])) {
                    return createResponse(422,
                        "The selected role is invalid.",
                        ['error' => 'The selected role is invalid.']);
                }
            }
            // Check if user already exist with given email address
            // Check if user already exist with given email address
            // $user = \App\User::where('email', $request->get('email'))->first();
            // if($user)
            //     return createResponse(422, "Email already exists.", ['error' => "It seems like entered email address already exists in the system."]);


            $code = str_random(20);
            // Create user
            $user = User::create([
                'title'            => $request->get('title'),
                'firstName'        => $request->get('firstName'),
                'lastName'         => $request->get('lastName'),
                'email'            => $request->get('email'),
                'role'             => $request->get('role'),
                'companyName'      => $request->get('companyName'),
                'address'          => $request->get('address'),

                'state'          => $request->get('state'),
                'postalCode'          => $request->get('postalCode'),
                'country'          => $request->get('country'),

                'isEnable'         => 0,
                'phone'            => $request->get('phone'),
                'mobile'           => $request->get('mobile'),
                'createdBy'        => $Loggeduser['id']
            ]);
            if($user->id) {
            UserInvite::create([
                'userId'            => $user->id,
                'inviteCode'        => $code
            ]);
            }

            $email = $request->get('email');
            $link = app('encrypter')->encrypt($email.'||'.$code);
            $link = $request->get('url').'/invite/'.$link;

//            $mail = app('mailer')->send('emails.invite', ['link' => $link, 'firstName' => $request->get('firstName'), 'role' => $request->get('role')], function($message) use ($email) {
//                $message->from('info@metroengine.com', 'Metroengine')
//                    ->subject('Welcome to Metroengine - Lets Setup Your Account')
//                    ->to($email);
//                    //\Log::info($link);
//            });

            $html = view('emails.invite', ['link' => $link, 'firstName' => $request->get('firstName'), 'role' => $request->get('role')])->render();
            $response = sendMail($email, $html, 'Welcome to Metroengine - Lets Setup Your Account');

            //return createResponse(200,  "Sign up successful.", ['message' => "User signed up successfully."]);
            return createResponse(200,  "Sign up successful.", ['message' => $link]);

        }catch (\Exception $e) {

            \Log::error("User registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!'.$e->getMessage()]);
        }
    }

    public function verifyInviteCode(Request $request)
    {
        try
        {
            // Validate details
            $validator = app('validator')->make($request->all(),['code' => 'required'],[]);

            if ($validator->fails())
                return createResponse(422,
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $code = app('encrypter')->decrypt($request->get('code'));
            $email = explode('||', $code)[0];
            $code = explode('||', $code)[1];

            $user = User::join('userInvite', 'userInvite.userId', '=', 'user.id')
            ->where('user.email', $email)
            ->where('userInvite.inviteCode', $code)
            ->first(['user.*','userInvite.inviteCode']);


            if($user) {
                UserInvite::where('userId', $user->id)->update(['inviteCode' => NULL]);
                return createResponse(config('httpResponse.SUCCESS'),
                "user list.",
                ['data' => $user]
                );
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Code already used.",
                ['error' => "Code already used."]);
            }


        }catch(\Exception $e)
        {
            \Log::error("Invite code failed : ".$e->getMessage());
            // Invalid invite code
            return createResponse(422,
                    "Invalid invite code!",
                    ['error' => 'Invalid invite code!']);
        }
    }
    public function register(Request $request)
    {
        try
        {
            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'firstName'     => 'required',
                'lastName'      => 'required',
                'email'         => 'required|email|unique:user',
                'mobile'        => 'required',
                'title'         => 'required',
                'role'          => 'required|in:Agent,Affiliate',
                'password'      => 'required|min:6',
                'url'           => 'required',
            ],[]);

            if ($validator->fails()) {
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            // Check if user already exist with given email address
            //  $user = \App\User::where('email', $request->get('email'))->first();
            // if($user)
            //     return createResponse(422, "Email already exists.", ['error' => "It seems like entered email address already exists in the system."]);

            $code = str_random(5);
            // Create user
            User::create([
                'title'            => $request->get('title'),
                'firstName'        => $request->get('firstName'),
                'lastName'         => $request->get('lastName'),
                'email'            => $request->get('email'),
                'mobile'           => $request->get('mobile'),
                'password'         => app('hash')->make($request->get('password')),
                'role'             => $request->get('role'),
                'confirmationCode' => $code,
                'isEnable'         => 0,
                'projectId'        => $request->get('projectId'),
                ]);

            $email = $request->get('email');
            $link = app('encrypter')->encrypt($email.'||'.$code);
//            $link = route('get.user.verifyEmail', ['code' => $link]);
            $link = $request->get('url').'/?code='.$link;

//            app('mailer')->send('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName')], function($message) use ($email) {
//                $message->from('info@metroengine.com', 'Metroengine')
//                    ->subject('Welcome to Metroengine - Lets Setup Your Account')
//                    ->to($email);
//            });

            $html = view('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName')])->render();

            $response = sendMail($email, $html, 'Welcome to Metroengine - Lets Setup Your Account');
            return createResponse(200,  "Sign up successful.", ['message' => "You have singed up successfully. Please check your inbox to verify the email address."]);

        } catch (\Exception $e) {

            \Log::error("User registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!']);
        }
    }

    public function registerInviteUser(Request $request)
    {
        try
        {
            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'id'            => 'required',
                'firstName'     => 'required',
                'lastName'      => 'required',

                'email'         => 'required|email',
                'password'      => 'required|min:6',
                'role'          => 'required',
                'address'       => 'required',

                'postalCode'    => 'required',
//                'state'         => 'required',
                'country'       => 'required',

                'mobile'        => 'required',
                'url'           => 'required',
            ],[]);

            if ($validator->fails())
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);


            // Check if user already exist with given email address and id
            $user = \App\User::where('id', $request->get('id'))->where('email',  $request->get('email'))->first();
            if( $user) {
            $code = str_random(5);
            // Update user
            $updateData = ([
                'firstName'        => $request->get('firstName'),
                'lastName'         => $request->get('lastName'),
                'title'            => $request->get('title'),
                'password'         => app('hash')->make($request->get('password')),
                'companyName'      => $request->get('companyName'),
                'phone'            => $request->get('phone'),
                'role'             => $request->get('role'),
                'mobile'           => $request->get('mobile'),
                'address'          => $request->get('address'),
                'postalCode'          => $request->get('postalCode'),
                'state'          => $request->get('state'),
                'country'          => $request->get('country'),
                'fax'              => $request->get('fax'),
                'about'            => $request->get('about'),
                'confirmationCode' => $code,
                'isEnable'         => 0
            ]);


            $user->update($updateData);

            $email = $request->get('email');
            $link = app('encrypter')->encrypt($email.'||'.$code);
            $link = $request->get('url').'/?code='.$link;

//            app('mailer')->send('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName')], function($message) use ($email) {
//                $message->from('info@metroengine.com', 'Metroengine')
//                    ->subject()
//                    ->to($email);
//            });
//
            $html = view('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName')])->render();
            sendMail($email, $html, 'Welcome to Metroengine - Lets Setup Your Account');
            return createResponse(200,  "Sign up successful.", ['message' => "You have singed up successfully. Please check your inbox to verify the email address."]);
          } else {
            return createResponse(config('httpResponse.UNPROCESSED'),
            "User not found.",
            ['error' => "User not found."]);
          }
        }catch (\Exception $e) {

            \Log::error("User registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!']);
        }
    }

    // Register Guest User
    public function registerGuest(Request $request)
    {
        try
        {
            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'userProjectId' => 'required',
                'firstName'     => 'required',
                'lastName'      => 'required',
                'email'         => 'required|email',
                'phoneNumber'   => 'required',
            ],[]);

            if ($validator->fails()) {
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            // Check if user already exist with given email address
            $token = Str::random(60);
            $user = \App\GuestUser::where('email', $request->get('email'))->first();

            if($user) {
                $token = $user->remember_token;
                $user->firstname = $request->get('firstName');
                $user->lastname = $request->get('lastName');
                $user->phone = $request->get('phoneNumber');
                $user->userProjectId = $request->get('userProjectId');
                $user->update();

            } else {
                $token = Str::random(60);
                // Create Guest User
                $user = \App\GuestUser::create([
                    'firstname'        => $request->get('firstName'),
                    'lastname'         => $request->get('lastName'),
                    'email'            => $request->get('email'),
                    'phone'            => $request->get('phoneNumber'),
                    'userProjectId'    => $request->get('userProjectId'), 
                    'remember_token'   => $token, 
                    ]);
            }

            return createResponse(200,  "Register successful.", ['message' => "You have registred successfully", "token" => $token]);

        } catch (\Exception $e) {
            \Log::error("Guest registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!']);
        }
    }

    // Register Guest Company
    public function registerCompany(Request $request)
    {
        try
        {
            // Validate company details
            $validator = app('validator')->make($request->all(),[
                'companyName'       => 'required',
                'yourName'          => 'required',
                'email'             => 'required|email',
                'phoneNumber'       => 'required',
                'userProjectId'     => 'userProjectId'
            ],[]);

            if ($validator->fails()) {
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            // Check if company already exist with given email address
            $token = Str::random(60);
            $company = \App\GuestCompany::where('email', $request->get('email'))->first();

            if(isset($company)) {
                $token = $company->remember_token;
                $company->name          = $request->get('companyName');
                $company->yourname      = $request->get('yourName');
                $company->phone         = $request->get('phoneNumber');
                $company->userProjectId = $request->get('userProjectId');
                $company->update();

            } else {
                $token = Str::random(60);
                // Create Guest Company
                $newCompany = \App\GuestCompany::create([
                    'name'             => $request->get('companyName'),
                    'yourname'         => $request->get('yourName'),
                    'email'            => $request->get('email'),
                    'phone'            => $request->get('phoneNumber'),
                    'userProjectId'    => $request->get('userProjectId'),
                    'remember_token'   => $token, 
                    ]);
            }

            return createResponse(200,  "Register successful.", ['message' => "Company has registred successfully", "token" => $token]);

        } catch (\Exception $e) {
            \Log::error("Guest registration failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!']);
        }
    }

    // Verify email address for sign up process
    public function verifyEmail(Request $request)
    {
        try
        {
            // Validate details
            $validator = app('validator')->make($request->all(),['code' => 'required'],[]);

            if ($validator->fails())
                return createResponse(422,
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $code = app('encrypter')->decrypt($request->get('code'));
            $email = explode('||', $code)[0];
            $code = explode('||', $code)[1];

            $user = User::where('email', $email)->where('confirmationCode', $code)->get()->first();

            if($user === null)
                throw new \Exception("Email verification link of user ".$email." has been expired.");

            User::where('email', $email)
                ->update(['isEmailVerified' => 1, 'isEnable' => 1, 'confirmationCode' => NULL]);

            return "Yaay! Your email address has been verified successfully. Please login into your account for more detail. <br> To login, please visit https://metro-engine.web.app/login";

        }catch(\Exception $e)
        {
            \Log::error("Email verification failed : ".$e->getMessage());
            return createResponse(config('httpResponse.UNPROCESSED'),
            "User not found.",
            ['error' => "User not found."]);
        }
    }

    public function store(Request $request)
    {
        try
        {
            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'title'         => 'required',
                'firstName'     => 'required',
                'lastName'      => 'required',
                'email'         => 'required|email',
                'password'      => 'required|min:6',
                'image'         => 'image|max:2048|mimes:jpeg,bmp,png,jpg,JPG,JPEG,PNG,PDF,PPT',
                'mobile'        => 'min:10|max:14',
                'phone'         => 'numeric',
                'role'          => 'required|in:User Manager,Project Manager,Master Agent,Agent,Affiliate'
            ],[]);

            if ($validator->fails())
            {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            // Check if user already exist with given email address
            $user = User::where('email', $request->get('email'))->get();
            if(count($user))
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Email already exists.",
                    ['error' => 'It seems like entered email address already exists in the system.']);

            $code = str_random(5);

            // Image Upload
            $userImage = "";
            if($request->hasFile('image'))
            {
                $errors = [];
                $userImage = 'user'.time().str_random(10).'_'.$request->file('image')->getClientOriginalExtension();
                $imagePath = base_path()."/public/images/users/".$userImage;

                // Move and resize uploaded file
                if(!$request->file('image')->move(base_path()."/public/images/users/", $userImage))
                    $errors[] = ['Could not upload user image! You may try again.'];

                if(count($errors))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "User image could not be processed. Please try to upload valid image.",
                        ['error' => "User image could not be processed. Please try to upload valid image."]);
            }

            // Create user
            User::create([
                'title'            => $request->get('title'),
                'firstName'        => $request->get('firstName'),
                'lastName'         => $request->get('lastName'),
                'email'            => $request->get('email'),
                'role'             => $request->get('role'),
                'password'         => app('hash')->make($request->get('password')),
                'mobile'           => ($request->has('mobile')) ? $request->get('mobile') : "",
                'phone'            => ($request->has('phone')) ? $request->get('phone') : "",
                'fax'              => ($request->has('fax')) ? $request->get('fax') : "",
                'address'          => ($request->has('address')) ? $request->get('address') : "",
                'confirmationCode' => $code,
                'companyName'      => ($request->has('companyName')) ? $request->get('companyName') : "",
                'office'           => ($request->has('office')) ? $request->get('office') : "",
                'isEnable'         => 0 ,
                'about'            => $request->get('about'),
                'image'            => $userImage
                ]);

            $email = $request->get('email');
            $link = app('encrypter')->encrypt($email.'||'.$code);
            $link = route('get.user.verifyEmail', ['code' => $link]);

            $html = view('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName'), 'email' => $request->get('email'), 'password' => $request->get('password')])->render();
            sendMail($email, $html, 'Welcome to Metroengine - Lets Setup Your Account');
//            app('mailer')->send('emails.verification', ['link' => $link, 'firstName' => $request->get('firstName')], function($message) use ($email) {
//	            $message->from('info@metroengine.com', 'Metroengine')
//	                ->subject('Welcome to Metroengine - Lets Setup Your Account')
//	                ->to($email);
//	        });

            return createResponse(config('httpResponse.SUCCESS'),
                "User created successfully.",
                ['message' => "User created successfully."]);

        }catch (\Exception $e) {
            \Log::error("User creation failed : ".$e->getMessage());
            // something went wrong
            return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Could not add user!",
                    ['error' => 'Could not add user!']);
        }
    }

    public function userLists(Request $request)
    {
        try
        {
            //get loggedin user
            $userId = getLoggedInUser('id');
            $Loggeduser = getLoggedInUser();

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
                
                switch ($Loggeduser['role']) {
                    case 'Admin':
                        $users = new User;
                        $users = $users->where('id','!=',$userId);
                    break;
                    case 'User Manager':
                        $users = new User;
                        $users = $users->where('createdBy','=',$userId);
                    break;
                    case 'Project Manager':
                    break;
                }
                
                switch ($Loggeduser['role']) {
                    case 'Admin':
                        $users = new User;
                        $users = $users->where('id','!=',$userId)->with(['assigned' => function($query) {
                            $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                        }]);                        
                    break;
                    case 'User Manager':
                        $assigned = AssignProject::where('userId', '=', $userId)->select('projectId')->get()->toArray();
                        $assigned = array_map(function($n) { return $n['projectId']; }, $assigned);
                        $users = User::whereIn('role', array('Agent', 'Affiliate'))
                            ->leftJoin('assignProjects', 'user.id', '=', 'assignProjects.userId')
                            ->select(['user.*', 'assignProjects.projectId'])
                            ->where(function ($q) use ($userId, $assigned) {
                                $q->where('createdBy','=',$userId)->orWhereIn('assignProjects.projectId', $assigned);
                            })
                            ->groupBy('user.id')
                            ->with(['assigned' => function($query) {
                                $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                            }]);
                    break;
                    case 'Project Manager':
                        $users = User::whereIn('role', array('Agent', 'Affiliate', 'Master Agent'));
                    break;
                    case 'Master Agent':
                        $users = User::whereIn('role', array('Agent', 'Affiliate'));
                    break;
                }


                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    //dd($search);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'title' || $k == 'firstName' || $k == 'lastName' || $k == 'companyName'  || $k == 'office' || $k == 'address' || $k == 'isEnable' || $k == 'role' || $k == 'name';
                    }, ARRAY_FILTER_USE_KEY);

                    // Search users by name
                    if(isset($search['name']))
                    {
                       $users = $users->where(function($query) use ($search) {
                            $query->where(app('db')->raw("CONCAT(firstName,' ',lastName)"), 'like', '%'.$search['name'].'%');
                        });
                        unset($search['name']);
                    }

                    foreach ($search as $field => $value) {
                        if(is_array($value)){
                            $users =  $users->where(function($q) use ($field, $value) {
                                foreach($value as $v) {
                                    $q->orwhere($field,'=',$v);
                                }
                            });
                        } else {
                            $users =  $users->where($field, 'like', '%'.$value.'%');
                        }
                    }
                    //var_dump($users->toSql());
                }
                $users = $users->orderBy($sortBy, $sortOrder)->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;

                $take = $recordsPerPage;
                // Get user list
                switch ($Loggeduser['role']) {
                    case 'Admin':
                        $users = new User;
                        $users = $users->where('id','!=',$userId)->with(['assigned' => function($query) {
                            $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                        }]);
                    break;
                    case 'User Manager':
                        $assigned = AssignProject::where('userId', '=', $userId)->select('projectId')->get()->toArray();
                        $assigned = array_map(function($n) { return $n['projectId']; }, $assigned);
                        $users = User::whereIn('role', array('Agent', 'Affiliate'))
                            ->leftJoin('assignProjects', 'user.id', '=', 'assignProjects.userId')
                            ->select(['user.*', 'assignProjects.projectId'])
                            ->where(function ($q) use ($userId, $assigned) {
                                $q->where('createdBy','=',$userId)->orWhereIn('assignProjects.projectId', $assigned);
                            })
                            ->groupBy('user.id')
                            ->with(['assigned' => function($query) {
                                $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                            }]);
                    break;
                    case 'Project Manager':
                        $users = User::whereIn('role', array('Agent', 'Affiliate', 'Master Agent'));
                    break;
                    case 'Master Agent':
                        $users = User::whereIn('role', array('Agent', 'Affiliate'));
                    break;
                }

                if ($request->has('search')) {
                    // Decode json in to php array
                    $search = json_decode($request->get('search'),true);
                    // Get only required params
                    $search = array_filter($search, function($k){
                        return $k == 'id' || $k == 'title' || $k == 'firstName' || $k == 'lastName' || $k == 'companyName'  || $k == 'office' || $k == 'address' || $k == 'isEnable' || $k == 'role' || $k == 'name';
                    }, ARRAY_FILTER_USE_KEY);

                    // Search users by name
                    if(isset($search['name']))
                    {
                       $users = $users->where(function($query) use ($search) {
                            $query->where(app('db')->raw("CONCAT(firstName,' ',lastName)"), 'like', '%'.$search['name'].'%');
                        });
                        unset($search['name']);
                    }

                    foreach ($search as $field => $value) {
                        if(is_array($value)){
                            $users =  $users->where(function($q) use ($field, $value) {
                                foreach($value as $v) {
                                    $q->orwhere($field,'=',$v);
                                }
                            });
                        } else {
                            $users =  $users->where($field, 'like', '%'.$value.'%');
                        }
                    }

                    //dd($search['role']);
                    // if(isset($search['role']))
                    // {
                    //     $users = $users->whereIn('role', $search['role']);
                    //     unset($search['role']);
                    // }

                    // foreach ($search as $field => $value) {
                    //    $users = $users->where($field, 'like', '%'.$value.'%');
                    // }
                    // var_dump($users->toSql());
                }

                //total records
                $totalRecords = $users->count();

                $users = $users->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $users = $users->get();
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
                ['error' => "Error while listing user".$e->getMessage()]);
        }
    }

    public function guestUserLists(Request $request) {
        try
        {
            $guestUsers = GuestUser::get();            

            return createResponse(config('httpResponse.SUCCESS'),
                "guestUser list.",
                ['data' => $guestUsers]
                );

        } catch (\Exception $e) {
            \Log::error("guestUser index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing guestuser",
                ['error' => "Error while listing guestuser".$e->getMessage()]);
        }
    }

    public function userListsByUnit(Request $request)
    {
        try
        {
            //get loggedin user
            $userId = getLoggedInUser('id');
            $Loggeduser = getLoggedInUser();

            //check validation
            $validator = app('validator')->make($request->all(),[
                'sortOrder'      => 'in:asc,desc',
                'unitId'         => 'required'
            ],[]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }
            //sorting
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : config('pager.sortBy');
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : config('pager.sortOrder');
            $role = $request->get('role');
            $unitId = $request->get('unitId');

            $users = new User;
            if ($role) {
                $users = $users->where('role', $role);
            } else {
                $users = $users->whereIn('role', ['Agent', 'Affiliate']);
            }
            $users = $users->where('isEnable', 1)
                        ->with(['assignedUnit' => function($query) use($unitId) {
                            $query->select('unitAgents.id','unitAgents.unitId', 'unitAgents.userId')
                                ->where('unitAgents.unitId', $unitId);
                        }]);

            $users = $users->orderBy($sortBy, $sortOrder)->select(['user.id','user.firstName','user.lastName','user.role'])->get();
            return createResponse(config('httpResponse.SUCCESS'),
                "user list.",
                ['data' => $users]
                );

        } catch (\Exception $e) {
            \Log::error("user index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing user",
                ['error' => "Error while listing user".$e->getMessage()]);
        }
    }

    public function userDetail($id)
    {
        try
        {
            $user = User::find($id);
            if(!$user)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "User doesn't exist.",
                    ['error' => 'User does not exist.']);

            switch ($user->role) {
                case 'Admin':
                    $projects = \App\Project::where('createdBy', $user->id)->get(['project.id', 'name']);
                    break;
                case 'User Manager':
                    $projects = \App\Project::where('createdBy', $user->id)->get(['project.id', 'name']);
                    break;
                case 'Master Agent':
                    $projects = \App\Project::join('projectAgents', 'projectAgents.projectId', '=', 'project.id')
                        ->groupBy('projectId')
                        ->where('projectAgents.userId', $user->id)->get(['project.id', 'name']);
                    break;
                case 'Agent':
                case 'Affiliate':
                    $projects = \App\Project::join('unitAgents', 'unitAgents.projectId', '=', 'project.id')
                        ->where('unitAgents.userId', $user->id)
                        ->groupBy('projectId')
                        ->get(['project.id', 'name']);
                    break;

                default:
                    $projects = [];
                    break;
            }
            $user->projects = $projects;

            //send user information
            return createResponse(config('httpResponse.SUCCESS'),
                "user details",
                ['data' => $user]);

        } catch (\Exception $e) {
            \Log::error("user details api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get user details",
                ['error' => 'Could not get user details.']);
        }
    }

    public function update(Request $request, $id)
    {
        try
        {
            $userId = getLoggedInUser('id');

            $validator = app('validator')->make($request->all(),[
                'phone'         => 'numeric',
                'image'         => 'image|max:2048|mimes:jpeg,bmp,png,jpg,JPG,JPEG,PNG,PDF,PPT',
                'email'         => 'unique:user,email,'.$id,
                'mobile'        => 'min:10|max:14',
                'removeImage'   => 'in:0,1',
                'password'      => 'min:6',
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $user = User::find($id);

            $updateData = array();
            $updateData = filterFields(['title','firstName', 'lastName', 'phone',
                'mobile', 'address', 'postalCode', 'state', 'country', 'fax', 'office', 'companyName', 'role','email','about', 'isEnable'], $request);

            $updateData['updatedBy'] = $userId;

            // Image Upload
            $userImage = "";
            if($request->hasFile('image'))
            {
                $existingImage = $user->image;
                $errors = [];
                $userImage = 'user'.time().str_random(10).'_'.$request->file('image')->getClientOriginalExtension();
                $imagePath = base_path()."/public/images/users/".$userImage;

                // Move and resize uploaded file
                if(!$request->file('image')->move(base_path()."/public/images/users/", $userImage))
                    $errors[] = ['Could not upload user image! You may try again.'];

                if($existingImage != "" && file_exists(base_path()."/public/images/users/".$existingImage))
                    unlink(base_path()."/public/images/users/".$existingImage);

                if(count($errors))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "User image could not be processed. Please try to upload valid image.",
                        ['error' => "User image could not be processed. Please try to upload valid image."]);

                $updateData['image'] = $userImage;
            }

            if($request->has('removeImage') && $request->get('removeImage'))
            {
                if($user->image != "" && file_exists(base_path()."/public/images/users/".$user->image))
                    unlink(base_path()."/public/images/users/".$user->image);

                $updateData['image'] = "";
            }

            // Check if user trying to update the role which has already been assinged to some project
            if($request->has('role') && $request->get('role') != $user->role)
            {
                if(\App\ProjectAgent::where('userId', $user->id)->count() || \App\Project::where('createdBy', $user->id)->count())
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "The role can not be updated because the user has already been assigned to another project.",
                        ['error' => "The role can not be updated because the user has already been assigned to another project."]);
            }

            if($request->has('password'))
                $updateData['password'] = app('hash')->make($request->get('password'));

            $user->update($updateData);

            if($request->has('password'))
                App('mailer')->send('emails.NewPassword', ['password' => $request->get('password'), 'user' => $user], function ($mail) use ($user) {
                    $mail->from('info@metroengine.com', 'Metroengine')
                        ->to($user->email)
                        ->subject('New password | Metroengine');
                });

            //check if user updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "user has been updated successfully.",
                ['message' => 'user has been updated successfully.']);

        } catch (\Exception $e) {
            \Log::error("user update ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update user",
                ['error' => 'Server error.']);
        }
    }

    public function editProfile(Request $request)
    {
        try
        {
            $user = User::find(getLoggedInUser('id'));

            $validator = app('validator')->make($request->all(),[
                'phone'              => 'numeric',
                'image'              => 'image|max:2048|mimes:jpeg,bmp,png',
                'mobile'             => 'min:10|max:14',
                'removeImage'        => 'in:0,1',
                'notificationStatus' => 'in:0,1',
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $updateData = array();
            $updateData = filterFields(['title','firstName', 'lastName', 'phone',
                'mobile', 'address', 'postalCode', 'state', 'country', 'fax', 'about' , 'office', 'companyName','notificationStatus'], $request);

            $updateData['updatedBy'] = $user->id;

            // Image Upload
            $userImage = "";
            if($request->hasFile('image'))
            {
                $existingImage = $user->image;
                $errors = [];
                $userImage = 'user'.time().str_random(10).'_'.$request->file('image')->getClientOriginalExtension();
                $imagePath = base_path()."/public/images/users/".$userImage;

                // Move and resize uploaded file
                if(!$request->file('image')->move(base_path()."/public/images/users/", $userImage))
                    $errors[] = ['Could not upload user image! You may try again.'];

                if($existingImage != "" && file_exists(base_path()."/public/images/users/".$existingImage))
                    unlink(base_path()."/public/images/users/".$existingImage);

                if(count($errors))
                    return createResponse(config('httpResponse.UNPROCESSED'),
                        "User image could not be processed. Please try to upload valid image.",
                        ['error' => "User image could not be processed. Please try to upload valid image."]);

                $updateData['image'] = $userImage;
            }

            if($request->has('removeImage') && $request->get('removeImage'))
            {
                if($user->image != "" && file_exists(base_path()."/public/images/users/".$user->image))
                    unlink(base_path()."/public/images/users/".$user->image);

                $updateData['image'] = "";
            }

            $user->update($updateData);

            //check if user updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "user has been updated successfully.",
                ['message' => 'user has been updated successfully.']);

        } catch (\Exception $e) {
            \Log::error("user update ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update user",
                ['error' => 'Server error.']);
        }
    }

    public function addUnits(Request $request,$id)
    {
        try
        {
            $userDetail= getLoggedInUser();

            $user = User::whereIn('role',['Agent','Affiliate'])->find($id);
            if(!$user)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "User doesn't exist.",
                    ['error' => 'User does not exist.']);

            if($user['role'] == 'Agent' && $userDetail['role'] == 'Agent')
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "You are not allowed to add units to this user.",
                    ['error' => 'You are not allowed to add units to this user.']);

            $message = [
                'units.json'            =>  'Incorrect units request',
                'units.array'           =>  'Invalid array format for Units',
                'units.*.distinct'      =>  'Unit value must be unique',
                'units.*.integer'       =>  'Unit value is invalid',
                'units.*.exists'        =>  'No such Unit exist',
            ];

            $validator = app('validator')->make($request->all(),[
                'units'         => 'required|json'
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            // To check if logged in user is allowed to assign unit
            switch ($userDetail['role']) {
                case 'Project Manager':
                    $unitList = Project::select('unit.*')->join('unit','unit.projectId','=','project.id')->where('project.createdBy',$userDetail['id'])->pluck('unit.id')->toArray();
                    break;

                case 'Master Agent' :
                    $unitList = ProjectAgent::select('unit.*')->join('unit','unit.projectId','=','projectAgents.projectId')->where(['projectAgents.userId' => $userDetail['id'], 'projectAgents.role' => 'Master Agent'])->pluck('unit.id')->toArray();
                    break;

                case 'Agent' :
                    $unitList = ProjectAgent::select('unit.*')->join('unit','unit.projectId','=','projectAgents.projectId')->where(['projectAgents.userId' => $userDetail['id'], 'projectAgents.role' => 'Agent'])->pluck('unit.id')->toArray();
                    break;

                default:
                    # code...
                    break;
            }

            $data  = json_decode($request->get('units'),true);

            $validator = app('validator')->make(['units'=>$data], [
                        'units'   => 'array',
                        'units.*' => 'numeric|distinct|exists:unit,id'
                    ], $message);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            if(empty(array_intersect($data, $unitList)))
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Unit does not exist.",
                    ['error' => 'Unit does not exist.']);

            // To check if the given user has been already assigned or not for given project , if exists update else create new one
            $unitAgent = UnitAgent::where(['userId' => $id])->whereIn('unitId',$data)->get();
            if(count($unitAgent) > 0)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "This user is already assigned to one of the given units.",
                    ['error' => 'This user is already assigned to one of the given units.']);

            $projectDetails = Unit::whereIn('id',$data)->pluck('projectId','id');
            $agentList = array();
            // To add new agent only if not exist
            foreach($data as $key=>$unitId)
            {
                if(isset($projectDetails[$unitId]))
                {
                    $projectId   = $projectDetails[$unitId];
                    $agentDetail = array();
                    $agentDetail['unitId']      = $unitId;
                    $agentDetail['projectId']   = $projectId;
                    $agentDetail['role']        = $user->role;
                    $agentDetail['userId']      = $id;
                    $agentDetail['created_at']  = \Carbon\Carbon::now();
                    array_push($agentList,$agentDetail);
                }
                else
                    return createResponse(config('httpResponse.SERVER_ERROR'),
                        "One of the unit does not exist.",
                        ['error' => 'One of the unit does not exist.']);

            }
            if(count($agentList) > 0)
                UnitAgent::insert($agentList);

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "unit has been assigned successfully.",
                ['message' => 'unit has been assigned successfully.']);

        } catch (\Exception $e) {
            \Log::error("assign unit ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not assign unit",
                ['error' => 'Server error.']);
        }
    }

    public function verifyPassword(Request $request)
    {
        try
        {
             $validator = app('validator')->make($request->all(),[
                'eventId'       => 'required|numeric',
                'password'      => 'required'
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),
                "Request parameter missing.",
                ['error' => $validator->errors()->first()]);

            $user = \App\User::find(getLoggedInUser('id'));

            // Compare password
            if(!app('hash')->check($request->get('password'), $user->password))
               return createResponse(config('httpResponse.UNPROCESSED'),
                    "Please enter valid password.",
                    ['error' => 'Please enter valid password.']);

            // Delete existing tokens if any
            if(\App\Token::where('userId', $user->id)->where('eventId', $request->get('eventId'))->count())
                \App\Token::where('userId', $user->id)->where('eventId', $request->get('eventId'))->delete();
            // Generate new token
            $token = str_random(100);
            \App\Token::create(['userId' => $user->id,
                'eventId'   => $request->get('eventId'),
                'token'     => $token]);

            //check if project updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "Token deails.",
                ['token' => $token]);

        } catch (\Exception $e)
        {
            \Log::error("Verify password failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not verify password.",
                ['error' => 'Could not verify password.']);
        }
    }

    public function userDestroy(Request $request,$id)
    {
        try
        {
            //get loggedin user
            $userId = getLoggedInUser('id');

            // Check if user has verified password or not
            // if(! verifyPasswordToken(array_merge($request->all(), ['userId' => $userId])))
            //     return createResponse(config('httpResponse.UNPROCESSED'),
            //         "Password verification failed. Please try again.",
            //         ['error' => 'Password verification failed. Please try again.']);

            $userDetail = User::find($id);
            if(!$userDetail)
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "User doesn't exist.",
                    ['error' => 'User does not exist.']);

            if($id == $userId)
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Sorry, you are not allowed to delete your own account.",
                    ['error' => 'Sorry, you are not allowed to delete your own account.']);

            app('db')->beginTransaction();
            $projects = Project::where('createdBy',$id)->pluck('id');
                // Detele unit,project and staff details
            Unit::whereIn('projectId',$projects)->delete();
            ProjectAgent::whereIn('projectId',$projects)->delete();
            AssignProject::whereIn('projectId',$projects)->delete();
            Project::where('createdBy',$id)->delete();


            $userDetail->delete();
            app('db')->commit();

            //check if user deleted successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "User has been deleted successfully.",
                ['message' => 'User has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("User deletion failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete User",
                ['error' => 'Server error.']);
        }
    }

    public function userDestroys(Request $request)
    {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'user_id'                 => 'required'
            ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            //post user id
            $userId = $request->get('user_id');
            foreach ($userId as $key => $id) {

                $userDetail = User::find($id);
                if($userDetail)
                    app('db')->beginTransaction();
                    if($userDetail->role == 'Admin' || $userDetail->role == 'User Manager')
                    {
                        $projects = Project::where('createdBy',$id)->pluck('id');
                        // Detele unit,project and staff details
                        Unit::whereIn('projectId',$projects)->delete();
                        ProjectAgent::whereIn('projectId',$projects)->delete();
                        AssignProject::whereIn('projectId',$projects)->delete();
                        Project::where('createdBy',$id)->delete();
                    }

                    $userDetail->delete();
                    app('db')->commit();
            }
            //check if user deleted successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "User has been deleted successfully.",
                ['message' => 'User has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("User deletion failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete User",
                ['error' => 'User does not exist.']);
        }
    }


    public function userDeactives(Request $request)
    {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'user_ids'                 => 'required'
            ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            //post user id
            $userId = $request->get('user_ids');
            foreach ($userId as $key => $id) {

                $userDetail = User::find($id);
                if($userDetail)
                    app('db')->beginTransaction();
                    $userDetail->update(['isEnable' => 0]);
                    app('db')->commit();
            }
            //check if user deactived successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "User has been deactived successfully.",
                ['message' => 'User has been deactived successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("User deletion failed :  ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete User",
                ['error' => 'User does not exist.']);
        }
    }

    public function userSearchByCriteria(Request $request) {
        try
        {
            $userId = getLoggedInUser('id');
            $Loggeduser = getLoggedInUser();

            $users = new User;
            if ($Loggeduser['role'] == 'Admin') {
                $users = $users->select('user.*')->leftJoin('assignProjects','user.id','=','assignProjects.userId');
            } else {
                $assigned = AssignProject::where('userId', '=', $userId)->select('projectId')->get()->toArray();
                $assigned = array_map(function($n) { return $n['projectId']; }, $assigned);

                $users = $users->whereIn('role', array('Agent', 'Affiliate'))
                    ->leftJoin('assignProjects', 'user.id', '=', 'assignProjects.userId')
                    ->select(['user.*', 'assignProjects.projectId'])
                    ->where(function ($q) use ($userId, $assigned) {
                        $q->where('createdBy','=',$userId)->orWhereIn('assignProjects.projectId', $assigned);
                    })
                    ->groupBy('user.id');
            }

            if($request->has('criteria') && $request->has('criteria_value') && $request->has('project_id')) {
                $users = $users->where('assignProjects.projectId',$request->get('project_id'));

                if($request->get('flag') === 1 || $request->get('flag') === 0){
                    $users = $users->where('isEnable', $request->get('flag'))
                                ->where($request->get('criteria'),$request->get('criteria_value'))
                                ->with(['assigned' => function($query) {
                                    $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                                }])
                                ->get();
                } else if($request->get('flag') == 'free') {
                    $users = [];
                } else {
                    $users = $users->where($request->get('criteria'),$request->get('criteria_value'))
                                ->with(['assigned' => function($query) {
                                    $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                                }])
                                ->get();
                }
            } else if($request->has('criteria') && $request->has('criteria_value')) {
                if($request->get('flag') === 1 || $request->get('flag') === 0){
                    $users = $users->where('isEnable', $request->get('flag'))->where($request->get('criteria'),$request->get('criteria_value'))
                        ->with(['assigned' => function($query) {
                            $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                        }])
                        ->get();
                } else if($request->get('flag') == 'free') {
                    $users = [];
                } else {
                    $users = $users->where($request->get('criteria'),$request->get('criteria_value'))
                        ->with(['assigned' => function($query) {
                            $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                        }])
                        ->get();
                }
            } else if($request->has('project_id')) {
                $users = $users->where('assignProjects.projectId',$request->get('project_id'))
                        ->with(['assigned' => function($query) {
                            $query->select('assignProjects.id','assignProjects.projectId', 'assignProjects.userId', 'assignProjects.indirect');
                        }])
                        ->get();
            }

            return createResponse(config('httpResponse.SUCCESS'),
            "user list.",
            ['data' => $users]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing user",
                ['error' => 'Error while listing user.'.$e->getMessage()]);
        }
    }

    /*
    public function getMembershipInfo(Request $request) {
        try {
            $membership_list = Product::where('type', 'membership')->get();
            $membership_data = [];
            foreach($membership_list as $membership) {
                $price_info = \Stripe\Price::retrieve($membership->price_id);
                $membership_data[] = ["id" => $membership->id, "name" => $membership->name, "price" => $price_info];
            }

            return createResponse(config('httpResponse.SUCCESS'),
                "Membership",
                ['data' => $membership_data]
            );
        } catch (\Exception $e) {
            \Log::error("Failed to get membership info. ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
            "Failed to get membership info.",
            ['error' => "Failed to get membership info. ".$e->getMessage()]);
        }
    }
    */

    public function startUpgrade(Request $request) {
        try {
            // Validate user details
            $validator = app('validator')->make($request->all(), [
                'plan' => 'required',
            ], []);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }
//
//            $amount = 0;
//            $items = $request->get('items');
            $product_id = $request->get('plan');
            $product = Product::find($product_id);

            if (!$product) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Plan not found.",
                    ['error' => 'Plan not found.']);
            }

            $user = User::find(getLoggedInUser('id'));
//
//            foreach ($items as $key => $val) {
//                if ($key == 'plan') {
//                    $amount += $val == 1 ? 100 : 200;
//                }
//            }
//
//            $paymentIntent = \Stripe\PaymentIntent::create([
//                'amount' => $amount,
//                'currency' => 'aud',
//                'receipt_email' => $user->email
//            ]);
//
//            $output = [
//                'clientSecret' => $paymentIntent->client_secret,
//                'amount' => $amount
//            ];
//            return createResponse(config('httpResponse.SUCCESS'),
//                "Client Secret",
//                ['data' => $output]
//            );

            // $customer = $user->createOrGetStripeCustomer();
            // $app_url = getenv('APP_FRONT_URL');
            // $checkout_session = \Stripe\Checkout\Session::create([
            //     'customer' => $customer->id,
            //     'mode' => 'subscription',
            //     'success_url' => $app_url.'?session_id={CHECKOUT_SESSION_ID}',
            //     'cancel_url' => $app_url,
            //     'payment_method_types' => ['card'],
            //     'line_items' => [[
            //         'price' => $product->price_id,
            //         // For metered billing, do not pass quantity
            //         'quantity' => 1,
            //     ]],
            // ]);

            // return createResponse(config('httpResponse.SUCCESS'),
            //     "Client Secret",
            //     ['sessionId' => $checkout_session['id']]
            // );
        } catch (\Exception $e) {
            \Log::error("Failed to create payment intent.".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Failed to create payment intent.",
                ['error' => "Failed to create payment intent. ".$e->getMessage()]);
        }
    }

    public function confirmUpgrade(Request $request) {
        try {
            // Validate User Plan
            $validator = app('validator')->make($request->all(), [
                'plan' => 'required|in:1,2',
                'invoice_id'   =>'required',
            ], []);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $userId = getLoggedInUser('id');
            $user = User::find($userId);
            $user->update(['plan' => $request->get('plan')]);

            $invoice_id = $request->get('invoice_id');
            \Stripe\Stripe::setApiKey(env('STRIPE_API_KEY'));
            $stripe = new \Stripe\StripeClient(env('STRIPE_API_KEY'));
            $stripe->invoices->sendInvoice($invoice_id);

            return createResponse(config('httpResponse.SUCCESS'),
                "Upgrade success!",
                []
            );
        } catch (\Exception $e) {
            \Log::error("Upgrading user plan failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing user plan",
                ['error' => "Error while listing user plan".$e->getMessage()]);
        }
    }

    public function confirmDowngrade(Request $request) {
        try {
            // Validate User Plan
            $validator = app('validator')->make($request->all(), [
                'plan' => 'required|in:0,1',
            ], []);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $userId = getLoggedInUser('id');
            $user = User::find($userId);
            $user->update(['plan' => $request->get('plan')]);

            return createResponse(config('httpResponse.SUCCESS'),
                "Downgrade success!",
                []
            );
        } catch (\Exception $e) {
            \Log::error("Downgrading user plan failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing user plan",
                ['error' => "Error while listing user plan".$e->getMessage()]);
        }
    }
}
