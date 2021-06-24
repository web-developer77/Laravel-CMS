<?php

namespace App\Http\Controllers;

use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;

use GenTux\Jwt\GetsJwtToken;

class AuthController extends Controller
{
    use GetsJwtToken;

    public function login(Request $request, JwtToken $jwt)
    {
        try {
            // Validate user details
            $validator = app('validator')->make($request->all(),[
                'username'  => 'required',
                'password'  => 'required'
            ],[]);

            if ($validator->fails()) {
                return createResponse(422,
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            // GEt user details
            $user = \App\User::where('email', $request->get('username'))->get();
            if(! count($user))
                return createResponse(422,  "Request parameter missing.", ['error' => "Please enter valid credentials."]);

            $user = $user->first();

            if(! app('hash')->check($request->get('password'),$user->password))
                return createResponse(422,  "Request parameter missing.", ['error' => "Invalid credentials."]);

            if (!$user->isEmailVerified)
                return createResponse(422,  "Request parameter missing.", ['error' => "Please verify your email in order to login!"]);
            if(! $user->isEnable)
                return createResponse(422,  "Request parameter missing.", ['error' => "You account is inactive. Please contact admin for more details."]);

            $token = $jwt->createToken($user);

            return createResponse(200,  "User logged in successfully.", ['token' => $token, 'user' => $user]);

        }catch (\Exception $e) {

            \Log::error("Login failed : ".$e->getMessage());
            // something went wrong
            return createResponse(500,
                    "Something went wrong!",
                    ['error' => 'Something went wrong!']);
        }
    }

    // get details of logged in user from access token
    public function getLoggedInUserDetails()
    {
        try
        {
            return $user = $this->jwtToken()->payload('context');

        }catch(\Exception $e)
        {
            \Log::error("Get user details API failed: ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Cound not fetch detail!",
                ['error' => 'Cound not fetch detail!']);
        }
    }

    // get all user details
    public function getUserDetails()
    {
        try
        {
            $user = \App\User::find(getLoggedInUser('id'));

            return createResponse(200,  "User Details.",  ['user' => $user]);

        }catch(\Exception $e)
        {
            \Log::error("Get user details API failed: ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Cound not fetch detail!",
                ['error' => 'Cound not fetch detail!']);
        }
    }

    public function changePassword(Request $request)
    {
        try
        {
            // validate details
            $validator = app('validator')->make($request->all(),[
                'oldPassword'       => 'required',
                'newPassword'       => 'required|min:6',
                'confirmPassword'   => 'required_with:newPassword|same:newPassword',
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            //get loggedin user
            $user = \App\User::find(getLoggedInUser('id'));;

            //check input password with has password of logged in user
            if (app('hash')->check($request->get('oldPassword'), $user->password)) {

                \App\User::where('id', $user->id)
                    ->update(['password' => app('hash')->make($request->get('newPassword'))]);

                return createResponse(config('httpResponse.SUCCESS'), "Password has been updated successfully.",['message' => "Password has been updated successfully."]);
            }

            return createResponse(config('httpResponse.UNPROCESSED'),
                    "You have entered wrong password. Please enter correct password!",
                    ['error' => "You have entered wrong password. Please enter correct password!"]);

        }catch(\Exception $e)
        {
            \Log::error("Change Password failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Cound not update password!",
                ['error' => 'Cound not update password!']);
        }
    }

    // Send OTP to registered email address and mobile number
    public function requestResetPassword(Request $request)
    {
        try
        {
            // validate details
            $validator = app('validator')->make($request->all(),['email'     => 'required|email' ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $user = \App\User::where('email', $request->get('email'))->get(['id', 'email', 'firstName'])->first();

            if($user === null )
           // if(!count($user))
                return createResponse(config('httpResponse.SERVER_ERROR'),  "It seems like entered email adress does not belongs to the system! Please check if there is any spelling mistake.", ['error' => "It seems like entered email adress does not belongs to the system! Please check if there is any spelling mistake."]);

            $otp = rand(100000, 999999);
            \App\User::where('id', $user->id)->update(['otp' => $otp, 'otpCreatedAt' => date('Y:m:d H:i:s')]);

//            App('mailer')->send('emails.ResetPassword', ['otp' => $otp, 'user' => $user], function ($mail) use ($user) {
//                $mail->from('info@metroengine.com', 'Metroengine')
//                    ->to($user->email)
//                    ->subject('Reset password | Metroengine');
//            });

            $html = view('emails.ResetPassword', ['otp' => $otp, 'user' => $user])->render();
            $response = sendMail($user->email, $html, 'Reset password | Metroengine');

            return createResponse(config('httpResponse.SUCCESS'),
                "The code has been sent successfully.",
                ['data' => $user]);

        }catch(\Exception $e)
        {
            \Log::error("Password reset OTP sending failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Cound not send OTP!",
                ['error' => 'Cound not send OTP!']);
        }
    }

    // Vefiry the code send in email to change password
    public function updatePassword(Request $request)
    {
        try
        {
            // validate details
            $validator = app('validator')->make($request->all(),[
                'email'             => 'required|email',
                'code'              => 'required',
                'password'          => 'required|min:6',
                'repeatPassword'    => 'required_with:password|same:password',
                ],[]);

            // If validation fails then return error response
            if($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), $validator->errors()->first(), ['error' => $validator->errors()->first()]);

            $user = \App\User::where('email', $request->get('email'))
                    ->where('otp', $request->get('code'))
                    ->get(['id', 'otpCreatedAt'])->first();

            if($user === null)
                return createResponse(config('httpResponse.UNPROCESSED'),  "Entered code is invalid.", ['error' => 'Entered code is invalid.']);

            $otpCreatedAt = strtotime($user->otpCreatedAt);
            // Time difference in minutes
            $timeDiff = (time() - $otpCreatedAt)/60;

            if( $timeDiff > 15 )
                return createResponse(config('httpResponse.UNPROCESSED'),  "The code has been expired.", ['error' => 'The code has been expired.']);

            \App\User::where('id', $user->id)->update(['password' => app('hash')->make($request->get('password'))]);

            return createResponse(config('httpResponse.SUCCESS'),
                "The password has been updated successfully.",
                ['message' => "The password has been updated successfully."]);

        }catch(\Exception $e)
        {
            \Log::error("Password reset failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Cound not update password!",
                ['error' => 'Cound not update password!']);
        }
    }

    // Invalidate jwt token
    public function logout()
    {

    }

}
