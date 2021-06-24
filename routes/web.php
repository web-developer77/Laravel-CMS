<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$app->get('/', function () use ($app) {
    return $app->version();
});

$app->get('/payment/subscription', [
     'uses' => 'WebhookController@subscribe'
]);


$app->group(['prefix' => 'v1.0'], function () use ($app)
{
	$app->get('panos/{id}', [
		'uses' 			=> 'ProjectController@getPanos'
	]);

	$app->post('panos', [
		'uses' 			=> 'ProjectController@createPanos'
	]);

	$app->put('panos', [
		'uses' 			=> 'ProjectController@updatePanos'
	]);

	$app->delete('panos/{id}', [
		'uses' 			=> 'ProjectController@deletePanos'
	]);

	$app->get('panoLinks/{id}', [
		'uses' 			=> 'ProjectController@getPanoLink'
	]);

	$app->post('panoLinks', [
		'uses' 			=> 'ProjectController@createPanoLink'
	]);

	$app->put('panoLinks', [
		'uses' 			=> 'ProjectController@updatePanoLink'
	]);

	$app->delete('panoLinks/{id}', [
		'uses' 			=> 'ProjectController@deletePanoLink'
	]);

	$app->post('getPanosForUnit', [
		'uses' 			=> 'ProjectController@getPanosForUnit'
	]);

	$app->get('/working', function (){
		return "Working";
	});

	$app->get('testmail', [
		'uses' 			=> 'ContactController@testMail'
	]);

	// Admin Create
	$app->post('/createAdmin',[
		'uses'			=> 'UserController@createAdmin'
	]);

	// Login user / admin
	$app->post('/login',[
		'uses'			=> 'AuthController@login'
	]);


	// invite code verify and send user detail
	$app->get('user/verifyInviteCode',[
		'as'			=> 'get.user.verifyInviteCode',
		'uses'			=> 'UserController@verifyInviteCode'
		]);

	// User registration created by admin
	$app->post('/registerInviteUser',[
		'uses'			=> 'UserController@registerInviteUser'
	]);


	// User registration himself
	$app->post('/register',[
		'uses'			=> 'UserController@register'
	]);

	//get project list in register page
	$app->get('/projectList',[
		'uses'			=> 'ProjectController@getProjects'
	]);


	// Register guest user
	$app->post('registerGuest', [
		'uses' 			=> 'UserController@registerGuest'
	]);

	// Register guest company
	$app->post('registerCompany', [
		'uses' 			=> 'UserController@registerCompany'
	]);

	// Verify user email address after registration
	$app->get('/user/verifyEmail',[
		'as'			=> 'get.user.verifyEmail',
		'uses'			=> 'UserController@verifyEmail'
		]);


	// Request to reset password (if user forgets password)
	$app->post('user/requestResetPassword', [
		'uses' 			=> 'AuthController@requestResetPassword']);

	// Verify code to reset password
	$app->post('user/updatePassword', [
		'uses' 			=> 'AuthController@updatePassword']);

	$app->post('/stripe-checkout', [
			'uses' => 'StripeController@checkout'
		]);

	$app->post('/stripe-getprice', [
		'uses' => 'StripeController@getPrice'
	]);

    $app->get('projectShowForClients/{id}', [
        'uses' 			=> 'ProjectController@projectShowForClients'
    ]);

	$app->group(['middleware' => 'jwt'], function () use ($app) {

		// User registration By admin
		$app->post('/registerByAdmin',[
			'middleware'	=> 'role:User Manager&Admin',
			'uses'			=> 'UserController@registerByAdmin'
		]);

        $app->post('/user/getMembershipInfo', [
            'middleware'	=> 'role:Admin&Agent&Affiliate',
            'uses'			=> 'UserController@getMembershipInfo'
        ]);

        $app->post('/user/startUpgrade', [
            'middleware'	=> 'role:Agent&Affiliate',
            'uses'			=> 'UserController@startUpgrade'
        ]);

        $app->post('/user/confirmUpgrade', [
            'middleware'	=> 'role:Agent&Affiliate',
            'uses'			=> 'UserController@confirmUpgrade'
        ]);

		$app->post('/user/confirmDowngrade', [
            'middleware'	=> 'role:Agent&Affiliate',
            'uses'			=> 'UserController@confirmDowngrade'
        ]);
////////////////////////////////COMPLETE ABOVE///////////////////////////////////

		// Change user's password
		$app->post('user/changePassword', [
		'uses' 			=> 'AuthController@changePassword']);

		// Verify user's password ==> remain
		$app->post('user/verifyPassword', [
			'uses' 			=> 'UserController@verifyPassword'
		]);

		// Logout user
		$app->post('/logout',[
			'uses'			=> 'AuthController@logout'
		]);

		// Get user details by id
		$app->get('/me', [
			'uses'			=> 'AuthController@getUserDetails'
		]);

		// user list by sorting
		$app->post('/userLists',[
			'middleware'	=> 'role:Admin&User Manager&Project Manager&Master Agent',
			'uses'			=> 'UserController@userLists'
		]);

		// user list by sorting
		$app->post('/GuestUserLists',[
			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses'			=> 'UserController@guestUserLists'
		]);

        // user list by sorting
        $app->post('/userListsByUnit',[
            'middleware'	=> 'role:Admin&Project Manager&Master Agent',
            'uses'			=> 'UserController@userListsByUnit'
        ]);

		//Only User Manager login create user
		$app->post('user', [
			'middleware'	=> 'role:User Manager',
			'uses' 			=> 'UserController@store'
		]);

		//Session User update
		$app->post('user/editProfile/', [
			'uses' => 'UserController@editProfile'
		]);

		// show user detail by id with project details
		$app->get('userDetail/{id}', [
			'middleware'	=> 'role:Admin&User Manager&Project Manager',
			'uses' 			=> 'UserController@userDetail'
		]);

		//udpated user details by session id
		$app->post('user/{id}', [
			'middleware'	=> 'role:Admin&User Manager',
			'uses' 			=> 'UserController@update'
		]);

		//remain
		$app->post('user/{id}/units', [
			'middleware'	=> 'role:Project Manager&Master Agent&Agent',
			'uses' 			=> 'UserController@addUnits'
		]);

		// soft delete, user cannot delete own account
		$app->delete('userDestroy/{id}', [
			'middleware'	=> 'role:Admin&User Manager',
			'uses' 			=> 'UserController@userDestroy'
		]);

		// soft delete, user cannot delete own account
		$app->post('userDestroys', [
			'middleware'	=> 'role:Admin&User Manager',
			'uses' 			=> 'UserController@userDestroys'
		]);

		// User deactivate
		$app->post('userDeactives', [
			'middleware'	=> 'role:Admin&User Manager',
			'uses' 			=> 'UserController@userDeactives'
		]);

		// User deactivate
		$app->post('userSearchByCriteria', [
			'middleware'	=> 'role:Admin&User Manager',
			'uses' 			=> 'UserController@userSearchByCriteria'
		]);

        /*
        |------------------------------------------------------------------
        | Dashboard routes
        |------------------------------------------------------------------
        */
        //List of google analytics data	=> array
        $app->get('analytics', [
            'middleware'	=> 'role:Admin&Project Manager&User Manager&Master Agent&Agent',
            'uses' 			=> 'ProjectController@googleAnalytics'
        ]);

		/*
		|------------------------------------------------------------------
		| Project routes
		|------------------------------------------------------------------
		*/
		//List of the projects	=> sortOrder, pagenumber, recordperpage, record = all, search
		$app->get('projectLists', [
			'middleware'	=> 'role:Admin&Project Manager&User Manager&Master Agent&Agent&Affiliate',
			'uses' 			=> 'ProjectController@projectLists'
		]);

		// Only Admin can create projects and units
		$app->post('projectCreate', [
			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ProjectController@projectCreate'
		]);

		// show single project details by id
		// Project Manager - units, agents, affiliates, masterAgents, marketingFiles Detail
		// Master Agent - agents, affiliates, units, marketingFiles Detail
		// Agent - affiliates, marketingFiles, units, unitAgents Detail
		// Affiliate - units, unitAgents, marketingFiles
		$app->get('projectShow/{id}', [
			'middleware'	=> 'role:Admin&Master Agent&Project Manager&Agent&Affiliate',
			'uses' 			=> 'ProjectController@projectShow'
		]);

		//only Project Manager and Admin update project data and other detail also added.
		$app->post('projectUpdate/{id}', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'ProjectController@projectUpdate'
		]);

        //only Admin soft delete project by id
        $app->post('projectDeploy/{id}', [
            'middleware'	=> 'role:Admin&Project Manager',
            'uses' 			=> 'ProjectController@projectDeploy'
        ]);

		//only Admin soft delete project by id
		$app->delete('projectDestroy/{id}', [
			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ProjectController@projectDestroy'
		]);

		//only Project Manager and Admin assign.
		$app->post('projectAssign', [
			'middleware'	=> 'role:Admin&User Manager&Project Manager',
			'uses' 			=> 'ProjectController@projectAssign'
		]);

		$app->post('/confirmStorageUpgrade', [
            'middleware'	=> 'role:Admin&User Manager&Project Manager',
            'uses'			=> 'ProjectController@confirmStorageUpgrade'
        ]);

		// list of user with => sortOrder, pagenumber, recordperpage, record = all, search
		// Project Manager => user list with project
		// Master Agent => user list with projectAgent where = role
		// Agent => user list with projectAgent where = Agent
		// Affiliate => user list with projectAgent where = Affiliate
		$app->get('project/{id}/staff', [
			'uses' 			=> 'ProjectController@staff'
		]);

		//only Project Manager delete project images by project id
		$app->delete('project/{projectId}/file/{id}', [
			'middleware'	=> 'role:Project Manager',
			'uses' 			=> 'ProjectController@deleteFile'
		]);

		$app->post('projectAgent', [
			'middleware'	=> 'role:Project Manager',
			'uses' 			=> 'ProjectAgentController@store'
		]);

        $app->post('uploadDocumentFiles', [
            'middleware'	=> 'role:Admin&Project Manager&Master Agent',
            'uses' 			=> 'ProjectController@uploadDocumentFiles'
        ]);

        $app->post('deleteDocumentFile', [
            'middleware'	=> 'role:Admin&Project Manager',
            'uses' 			=> 'ProjectController@deleteDocumentFile'
        ]);

        $app->get('downloadDocumentFile/{id}', [
            'uses' 			=> 'ProjectController@downloadDocumentFile'
        ]);

        $app->get('getProjectDocuments/{projectId}', [
            'uses' 			=> 'ProjectController@getProjectDocuments'
        ]);

		$app->post('projectPanoFilesUpload', [
			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ProjectController@projectPanoFilesUpload'
		]);

		$app->post('projectPanoFilesDelete', [
			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ProjectController@projectPanoFilesDelete'
		]);

		$app->get('projectPanoFilesList/{projectId}', [
			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ProjectController@projectPanoFilesList'
		]);

		/*
		|------------------------------------------------------------------
		| Unit routes
		|------------------------------------------------------------------
		*/

		// Only Admin can create projects and units
		$app->post('unitCreate', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitCreate'
		]);

        $app->post('unitDelete', [
            'middleware'	=> 'role:Admin&Project Manager',
            'uses' 			=> 'UnitController@unitDelete'
        ]);

		$app->post('unitFieldChange', [
			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses' 			=> 'UnitController@unitFieldChange'
		]);

		$app->get('unitByProjectId/{id}', [
//			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses' 			=> 'UnitController@unitByProjectId'
		]);

		$app->get('unit/{id}', [
			'uses' 			=> 'UnitController@show'
		]);

		$app->post('unit/{id}', [
			'middleware'	=> 'role:Project Manager&Master Agent&Agent',
			'uses' 			=> 'UnitController@update'
		]);

		$app->put('unit/multiple', [
			'middleware'	=> 'role:Project Manager&Master Agent&Agent',
			'uses' 			=> 'UnitController@updateMultiple'
		]);

		$app->post('unit/{unitId}/image/{id}', [
			'middleware'	=> 'role:Project Manager',
			'uses' 			=> 'UnitController@updateImage'
		]);

		$app->post('unit/{id}/requestStatusChange', [
			'middleware'	=> 'role:Affiliate',
			'uses' 			=> 'UnitController@requestStatusChange'
		]);

		$app->delete('unit/{unitId}/image/{id}', [
			'middleware'	=> 'role:Project Manager',
			'uses' 			=> 'UnitController@deleteImage'
		]);

		$app->get('unit/{id}/file/{fileId}', [
			'uses' 			=> 'UnitController@downloadFile'
		]);

		$app->get('project/{id}/file/{fileId}', [
			'uses' 			=> 'ProjectController@downloadFile'
		]);

		/************************************* */
			/* Unit price module */
		/************************************* */

		$app->post('unitPriceCreate', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitPriceCreate'
		]);

		$app->delete('unitPriceDelete/{id}', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitPriceDelete'
		]);

		$app->post('unitPriceActivated/{id}', [
			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses' 			=> 'UnitController@unitPriceActivated'
		]);

		$app->post('unitUpdateDisplayPrice', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitUpdateDisplayPrice'
		]);

		/************************************* */
			/* Unit floor module */
		/************************************* */

		$app->post('unitFloorCreate', [
			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses' 			=> 'UnitController@unitFloorCreate'
		]);

		$app->delete('unitFloorDelete/{id}', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitFloorDelete'
		]);

		$app->post('unitFloorActivated/{id}', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitFloorActivated'
		]);

		$app->post('unitUpdateDisplayFloor', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitUpdateDisplayFloor'
		]);

		$app->post('unitDisplay', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitDisplay'
		]);

		$app->post('unitIsDisplayedChange', [
			'middleware'	=> 'role:Admin&Project Manager',
			'uses' 			=> 'UnitController@unitIsDisplayedChange'
		]);

		/*
		|------------------------------------------------------------------
		| Message routes
		|------------------------------------------------------------------
		*/

		$app->post('message', [
			'uses' 			=> 'MessageController@store'
		]);

		/*$app->get('message', [
			'uses' 			=> 'MessageController@users'
		]);

		$app->get('message/{id}', [
			'uses' 			=> 'MessageController@index'
		]);
*/
		$app->get('message', [
			'uses' 			=> 'MessageController@index'
		]);

		$app->get('user/{id}/message', [
			'middleware'	=> 'role:User Manager',
			'uses' 			=> 'MessageController@userMessages'
		]);

		$app->delete('message/{id}', [
			'uses' 			=> 'MessageController@destroy'
		]);

		$app->post('units/assign', [
			'middleware'	=> 'role:Admin&Project Manager&Master Agent',
			'uses' 			=> 'UnitController@assignAgentsAffiliates'
		]);

		$app->get('message/unread', [
			'uses' 			=> 'MessageController@getTotalUnread'
		]);


		/*
		|------------------------------------------------------------------
		| Contact routes
		|------------------------------------------------------------------
		*/

		// Only Admin can create contact folder
		$app->post('contactFolderCreate', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactFolderCreate'
		]);

		//only Admin soft delete project by id
		$app->delete('contactFolderDestroy/{id}', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactFolderDestroy'
		]);

		$app->get('contactFolderLists', [
//			'middleware'	=> 'role:Admin&Project Manager&User Manager&Master Agent',
			'uses' 			=> 'ContactController@contactFolderLists'
		]);

		// Only Admin can create contact folder
		$app->post('contactCreate', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactCreate'
		]);

		//only Admin soft delete project by id
		$app->post('contactDestroy', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactDestroy'
		]);

		$app->post('contactUpdate/{id}', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactUpdate'
		]);

		$app->post('contactChangeLead', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactChangeLead'
		]);

		$app->post('contactChangeTag', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactChangeTag'
		]);

		$app->post('contactImport', [
//			'middleware'	=> 'role:Admin',
			'uses' 			=> 'ContactController@contactImport'
		]);

		$app->get('getTemplate', [
			'uses' 			=> 'ContactController@getTemplate'
		]);

		$app->get('getTemplateDetail/{id}', [
			'uses' 			=> 'ContactController@getTemplateDetail'
		]);

		$app->post('sendEmailByTemplate', [
			'uses' 			=> 'ContactController@sendEmailByTemplate'
		]);

		$app->post('getTemplateByUser', [
			'uses' 			=> 'ContactController@getTemplateByUser'
		]);

	}); // end of jwt middleware

}); // End of prefix v1.0 group

//$app->get('/{any}', 'UserController@indextest');