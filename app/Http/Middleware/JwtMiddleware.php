<?php

namespace App\Http\Middleware;

use Closure;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\Exceptions\NoTokenException;

class JwtMiddleware
{

    use GetsJwtToken;

    /** @var JwtToken */
    private $jwt;

    /**
     * @param JwtToken $jwt
     */
    public function __construct(JwtToken $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * Validate JWT token before passing on to the next middleware
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure                  $next
     */
    public function handle($request, Closure $next)
    {
        try
        {
            $token = $this->getTokenFromRequest($request);
            $this->jwt->setToken($token)->validateOrFail();

            return $next($request);

        }catch(NoTokenException $e)
        {
            return createResponse(401,  "Plese provide access token.", ['error' => 'You must be logged in.'.$e->getMessage()]);
        }catch(InvalidTokenException $e)
        {
            return createResponse(401,  "The token has been expired.", ['error' => 'It seems like your session has been timed out. Please login to continue.'.$e->getMessage()]);
        }catch(\Exception $e)
        {
            return createResponse(401,  "Invalid access token.", ['error' => 'You must be logged in.'.$e->getMessage()]);
        }
    }

    /**
     * Get the token from the request
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     *
     * @throws NoTokenException
     */
    private function getTokenFromRequest($request)
    {
        $token = $this->getToken($request);

        if( ! $token) {
            throw new NoTokenException('JWT token is required.');
        }

        return $token;
    }
}
