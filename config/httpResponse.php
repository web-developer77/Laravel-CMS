<?php

return [
	//The request is OK (this is the standard response for successful HTTP requests)
	'SUCCESS' 		=> 200,

	//The request has been accepted for processing, but the processing has not been completed
	'ACCEPTED'		=> 202,

	// The request cannot be fulfilled due to bad syntax
	"BAD_REQUEST"	=> 400,

	//The request was a legal request, but the server is refusing to respond to it. For use when authentication is possible but has failed or not yet been provided
	'UNAUTHORIZED'	=> 401,

	//The request was a legal request, but the server is refusing to respond to it
	'FORBIDDEN'		=> 403,

	//The requested page could not be found but may be available again in the future
	'NOT_FOUND'		=> 404,

	// A request was made of a page using a request method not supported by that page
	'METHOD_NOT_ALLOWED' => 405,

	//A generic error message, given when no more specific message is suitable
	'SERVER_ERROR'	=> 500,

	// The request was well-formed but was unable to be followed due to semantic errors
	'UNPROCESSED'	=> 422,

];