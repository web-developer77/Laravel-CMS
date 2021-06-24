<?php

namespace App\Http\Middleware;

use Closure;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $role)
    {
        // explode roles and store into array
        $roles = explode("&",$role);

        // Get role of logged in user
        $role = getLoggedInUser('role');

        // Check if role exists in array
        if(!in_array($role, $roles))
        {
            return createResponse(500,
                    "You do not have enough privileges to access the data.",
                    ['error' => 'You do not have enough privileges to access the data.']);
        }
        return $next($request);
    }
}
