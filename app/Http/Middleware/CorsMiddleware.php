<?php

namespace App\Http\Middleware;

class CorsMiddleware {

  	public function handle($request, \Closure $next)
  	{

         //Intercepts OPTIONS requests
         if($request->isMethod('OPTIONS')) {
            $response = response('', 200);
         } else {
            // Pass the request to the next middleware
            $response = $next($request);
         }

         header('Access-Control-Allow-Origin: *');
         header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description, Authorization');
         header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, DELETE');

         /*	$response->header('Access-Control-Allow-Methods', 'HEAD, GET, POST, PUT, PATCH, DELETE');
      	 $response->header('Access-Control-Allow-Headers', $request->header('Access-Control-Request-Headers'));
      	 $response->header('Access-Control-Allow-Origin', '*');*/
      	 return $response;

         //   return $next($request)
         //   ->header('Access-Control-Allow-Origin', '*')
         //   ->header('Access-Control-Allow-Methods','GET, POST, PUT, PATCH, DELETE, OPTIONS')
         //   ->header('Access-Control-Allow-Headers', '*');
  	}
}