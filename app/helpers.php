<?php
use Mailjet\Resources;

// Function to create response
function createResponse($status, $message, $payload, $pager = "") 
{
    return response()
        ->json(['status'    => $status, 
            'message'       => $message, 
            'payload'       => $payload,
            'pager'         => $pager
        ], $status);
}

function getLoggedInUser($param = NULL)
{
    $auth = new \App\Http\Controllers\AuthController;
    $user = $auth->getLoggedInUserDetails();

    if($param && isset($user[$param]))
        return $user[$param];

    return $user;
}

function filterFields($fields, $request)
{
    $data = [];
    if(! is_array($request))
    {
        foreach ($fields as $field)
            if($request->has($field) || $request->get($field) === "") $data[$field] = $request->get($field);
    }else
    {
        foreach ($fields as $field) 
            if(isset($request[$field])) $data[$field] = $request[$field];
    }
    return $data;
}

function verifyPasswordToken($data)
{
    // Validate required details
    $validator = app('validator')->make($data,[
        'eventId'   => 'required|numeric',
        'token'     => 'required',
        'userId'    => 'required'
        ],[]); 

    if($validator->fails()) 
        return false;

    // GEt token if exists
    $token = \App\Token::where('userId', $data['userId'])->where('eventId', $data['eventId'])->get();
    if(!count($token))
        return false;
    // compare token
    $token = $token->first();

    // Time difference in minutes
    $timeDiff = (time() - strtotime($token->created_at))/60;
    if( $timeDiff > 15 )
    {
        \App\Token::where('userId', $data['userId'])->where('eventId', $data['eventId'])->delete();
        return false;
    }

    if($token->token != $data['token'])
        return false;

    \App\Token::where('userId', $data['userId'])->where('eventId', $data['eventId'])->delete();

    return true;
}

function sendMail($email, $html, $subject)
{
    $mj_api_key = getenv('MJ_APIKEY_PUBLIC');
    $mj_api_secret = getenv('MJ_APIKEY_PRIVATE');
    $mj = new \Mailjet\Client($mj_api_key, $mj_api_secret, true, ['version' => 'v3']);

    $body = [
        'FromEmail' => getenv('MJ_SENDER_EMAIL'),
        'To' => $email,
        'Subject' => $subject,
        'Html-part' => $html
    ];

    return $mj->post(Resources::$Email, ['body' => $body]);
}

