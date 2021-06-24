<?php

namespace App\Http\Controllers;

use App\User;
use App\Contact;
use App\Project;
use App\ContactFolder;
use \Mailjet\Resources;
use App\ProjectTemplate;
use Illuminate\Http\Request;


class ContactController extends Controller
{

    public function contactFolderLists(Request $request)
    {
        try
        {
            //get loggedin user
            $user = getLoggedInUser();
            $contactFolder = new ContactFolder;
            $contactFolder = $contactFolder->with(['contacts'=> function($query) use ($user) {
                    $query->where('contacts.userId', '=',$user['id']);
                }])->where('userId',$user['id'])->orWhere('isDelete', 0)->get();

            return createResponse(config('httpResponse.SUCCESS'),
                "contactFolder list.",
                ['data' => $contactFolder]
            );

        } catch (\Exception $e) {
            \Log::error("contactFolder index ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while listing contactFolder",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }


    public function contactFolderCreate(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'folder_name' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            $folder_name = $request->get('folder_name');
            //get loggedin user
            $user = getLoggedInUser();

             // store ContactFolder details
             $contactFolder = ContactFolder::create([
                'folderName'  => $folder_name,
                'userId'    => $user['id']
            ]);

            app('db')->commit();
            //check if ContactFolder deployed successfully
            return createResponse(config('httpResponse.SUCCESS'),
            "The ContactFolder has been added successfully.",
            ['message' => 'The ContactFolder has been added successfully', 'data' => $contactFolder]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create ContactFolder.",
                ['error' => 'Something went wrong. Could not create ContactFolder.'.$e->getMessage()]);
        }

    }


    public function contactFolderDestroy(Request $request, $id)
    {
        try
        {
            $user = getLoggedinUser();

            app('db')->beginTransaction();

            // Get contactFolder details
            $contactFolder = ContactFolder::where('id',$id)->where('isDelete',1)->first();

            if($contactFolder === null)
                return createResponse(config('httpResponse.SUCCESS'),
                    "Either selected contactFolder has already been deleted or you do not have privileges to delete the contactFolder.",
                    ['message' => 'Either selected contactFolder has already been deleted or you do not have privileges to delete the contactFolder.']);


            // Delete contactFolder data
            $contactFolder->delete();
            // Detele Contact
            Contact::where('folder_id',$id)->delete();

            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The contactFolder has been deleted successfully.",
                ['message' => 'The contactFolder has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("contactFolder deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete contactFolder",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function contactCreate(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'folder_id' => 'required|exists:contactFolders,id',
                'firstName' => 'required',
                'lastName' => 'required',
                'email' => 'required|email',
                'tags' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            //get loggedin user
            $user = getLoggedInUser();

             // store ContactFolder details
             $contact = Contact::create([
                'folder_id'      => $request->get('folder_id'),
                'firstName'      => $request->get('firstName'),
                'lastName'       => $request->get('lastName'),
                'phoneNumber'    => $request->get('phoneNumber'),
                'email'          => $request->get('email'),
                'description'    => $request->get('description'),
                'leadValue'      => $request->get('leadValue'),
                'postalCode'     => $request->get('postalCode'),
                'address'        => $request->get('address'),
                'state'          => $request->get('state'),
                'city'           => $request->get('city'),
                'country'        => $request->get('country'),
                'website'        => $request->get('website'),
                'tags'           => $request->get('tags'),
                'userId'         => $user['id']
            ]);

            app('db')->commit();
            //check if Contact deployed successfully
            return createResponse(config('httpResponse.SUCCESS'),
            "The Contact has been added successfully.",
            ['message' => 'The Contact has been added successfully', 'data' => $contact]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create Contact.",
                ['error' => 'Something went wrong. Could not create Contact.'.$e->getMessage()]);
        }

    }


    public function contactDestroy(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'contact_ids'                 => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);


            $user = getLoggedinUser();
            $contact_ids = $request->get('contact_ids');

            foreach ($contact_ids as $key => $id) {
                // Get contact details
                $contact = Contact::where('id',$id)->first();
                if($contact) {
                     // Delete Contact data
                     $contact->delete();
                }
            }
            app('db')->commit();
            return createResponse(config('httpResponse.SUCCESS'),
                "The contact has been deleted successfully.",
                ['message' => 'The Contact has been deleted successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("Contact deletion failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not delete Contact",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function contactUpdate(Request $request, $id)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'folder_id' => 'required|exists:contactFolders,id',
                'firstName' => 'required',
                'lastName' => 'required',
                'email' => 'required|email',
                'tags' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }


            //get loggedin user
            $user = getLoggedInUser();

            $contact = Contact::find($id);

            if($contact === null)
                return createResponse(config('httpResponse.SERVER_ERROR'),
                    "Contact doesn't exist.",
                    ['error' => 'Contact does not exist.']);

            $updateData = array();
            $updateData = filterFields([
                'folder_id', 'firstName', 'lastName', 'phoneNumber', 'email', 'description', 'leadValue',
                'postalCode', 'address', 'state', 'city', 'country','website','tags'
            ], $request);

            $contact->update($updateData);
            app('db')->commit();
            //check if Contact updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "The Contact has been updated successfully.",
                ['data' => $contact, 'message' => 'The Contact has been updated successfully.']);

        } catch (\Exception $e) {
            app('db')->rollback();
            \Log::error("Contact update ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not update Contact",
                ['error' => 'Server error.'.$e->getMessage()]);
        }
    }

    public function contactChangeLead(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'contact_ids'    => 'required',
                'leadValue'     => 'required|integer|between:1,10',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            $contact_ids = $request->get('contact_ids');
            $leadValue = $request->get('leadValue');

            $is_lead = false;
            foreach ($contact_ids as $key => $id) {
                $contact = Contact::where('id', $id)->first();
                if($contact) {
                    $is_lead = true;
                    $contact = $contact->update(['leadValue' => $leadValue]);
                }
            }

            if( $is_lead) {
                return createResponse(config('httpResponse.SUCCESS'),
                "The contact lead has been update successfully.",
                ['message' => 'The contact lead has been update successfully']);
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'),
                "The contact not found.",
                ['error' => "The contact not found."]);
            }

        } catch (\Exception $e) {
            \Log::error("contact lead update failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not update contact lead.",
                ['error' => 'Something went wrong. Could not update contact lead.']);
        }

    }


    public function contactChangeTag(Request $request) {
        try
        {
            $validator = app('validator')->make($request->all(), [
                'contact_ids'   => 'required',
                'tag_value'          => 'required',
                ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'),  "Request parameter missing.",  ['error' => $validator->errors()->first()]);

            $contact_ids = $request->get('contact_ids');
            $tags = $request->get('tag_value');

            $is_tag = false;
            foreach ($contact_ids as $key => $id) {
                $contact = Contact::where('id', $id)->first();
                if($contact) {
                    $is_tag = true;
                    $contact = $contact->update(['tags' => $tags]);
                }
            }

            if( $is_tag) {
                return createResponse(config('httpResponse.SUCCESS'),
                "The contact lead has been update successfully.",
                ['message' => 'The contact lead has been update successfully']);
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'),
                "The contact not found.",
                ['error' => "The contact not found."]);
            }

        } catch (\Exception $e) {
            \Log::error("contact lead update failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not update contact lead.",
                ['error' => 'Something went wrong. Could not update contact lead.']);
        }

    }

    public function contactImport(Request $request)
    {
        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'folder_id' => 'required|exists:contactFolders,id',
                'contacts' => 'required',
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

            //get loggedin user
            $user = getLoggedInUser();
            $folder_id = $request->get('folder_id');
            $contacts = $request->get('contacts');

            foreach ($contacts as $key => $data) {

                // store ContactFolder details
                $contact[] = Contact::create([
                    'folder_id'      => $folder_id,
                    'firstName'      => $data['firstName'],
                    'lastName'       => $data['lastName'],
                    'phoneNumber'    => $data['phoneNumber'],
                    'email'          => $data['email'],
                    'description'    => $data['description'],
                    'leadValue'      => $data['leadValue'],
                    'postalCode'     => $data['postalCode'],
                    'address'        => $data['address'],
                    'state'          => $data['state'],
                    'city'           => $data['city'],
                    'country'        => $data['country'],
                    'website'        => $data['website'],
                    'tags'           => $data['tags'],
                    'userId'         => $user['id']
                ]);
            }


            app('db')->commit();
            //check if Contact deployed successfully
            return createResponse(config('httpResponse.SUCCESS'),
            "The Contacts has been added successfully.",
            ['message' => 'The Contacts has been added successfully', 'data' => $contact]);

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Something went wrong. Could not create Contacts.",
                ['error' => 'Something went wrong. Could not create Contacts.'.$e->getMessage()]);
        }

    }

    public function testMail() {

        // Use your saved credentials, specify that you are using Send API v3.1
        $SENDER_EMAIL = "ritu.s@xcitech.in";
        $RECIPIENT_EMAIL = "ritu.s@xcitech.in";
        $mj = new \Mailjet\Client(
            "9cf671d9f572580966972e537988b690",
            "f4d469847ca21951c52ef7569147731a",
            true,
            ['version' => 'v3.1']
        );

        // Define your request body

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => "$SENDER_EMAIL",
                        'Name' => "Me"
                    ],
                    'To' => [
                        [
                            'Email' => "$RECIPIENT_EMAIL",
                            'Name' => "You"
                        ]
                    ],
                    'Subject' => "My first Mailjet Email ritu!",
                    'TextPart' => "Greetings from Mailjet!",
                    'HTMLPart' => "<h3>Dear passenger ritu, welcome to <a href=\"https://www.mailjet.com/\">Mailjet</a>!</h3>
                    <br />May the delivery force be with you!"
                ]
            ]
        ];

        // All resources are located in the Resources class

        $response = $mj->post(Resources::$Email, ['body' => $body]);

        // Read the response

        dd($response->getData() );
    }


    public function getTemplate() {
        $mj = new \Mailjet\Client(
            getenv('MJ_APIKEY_PUBLIC'),
            getenv('MJ_APIKEY_PRIVATE'),
            true,
            ['version' => 'v3']
        );

        $response = $mj->get(Resources::$Template);
        //$response->success() && var_dump($response->getData());
        return createResponse(config('httpResponse.SUCCESS'),
        "The email template list.",
        ['message' => 'The email template list', 'data' =>$response->getData() ]);

    }

    public function getTemplateDetail($id) {

        $mj = new \Mailjet\Client(
            getenv('MJ_APIKEY_PUBLIC'),
            getenv('MJ_APIKEY_PRIVATE'),
            true,
            ['version' => 'v3']
        );

        $response = $mj->get(Resources::$TemplateDetailcontent, ['id' => $id]);
        return createResponse(config('httpResponse.SUCCESS'),
        "The email template details list.",
        ['message' => 'The email template details list', 'data' =>$response->getData() ]);

    }


    public function sendEmailByTemplate(Request $request) {

        try
        {
            app('db')->beginTransaction();
            $validator = app('validator')->make($request->all(), [
                'contact_ids' => 'required',
                'template_id' => 'required'
            ]);

            if ($validator->fails()) {
                return createResponse(config('httpResponse.UNPROCESSED'),
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
            }

             // Use your saved credentials, specify that you are using Send API v3.1
            $SENDER_EMAIL = "alen.malenica@gmail.com";

            $mj = new \Mailjet\Client(
                getenv('MJ_APIKEY_PUBLIC'),
                getenv('MJ_APIKEY_PRIVATE'),
                true,
                ['version' => 'v3.1']
            );

            //get loggedin user
            $user = getLoggedInUser();
            $template_id = $request->get('template_id');
            $contact_ids = $request->get('contact_ids');

            $email=[];
            foreach ($contact_ids as $key => $id) {
                $contact = Contact::find($id);
                $email[$key]['Email'] = $contact->email;
                $email[$key]['Name'] = $contact->firstName .' '. $contact->lastName;
            }

            // Define your request body
            $body = [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => "$SENDER_EMAIL",
                            'Name' => "Metro Engine"
                        ],
                        'To' => $email,
                        'TemplateID' => $template_id,
                        'TemplateLanguage' => true

                    ]
                ]
            ];

            // All resources are located in the Resources class
            $response = $mj->post(Resources::$Email, ['body' => $body]);

            // Read the response
            //dd($response->getData() );

            app('db')->commit();
            //check if Contact updated successfully
            return createResponse(config('httpResponse.SUCCESS'),
                "email list.",
                ['data' => $response->getData()]
            );

        } catch (\Exception $e) {
            app('db')->rollback();
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Could not get email list",
                ['error' => 'Server error.'.$e->getMessage()]);
        }







    }

    public function getTemplateByUser() {
        $user = getLoggedInUser();
        //$projects = Project::where('createdBy', $user['id'])->pluck('id');

        $projects = Project::select('project.id')->join('assignProjects','project.id','=','assignProjects.projectId')
        ->where('assignProjects.userId',$user['id'])->pluck('id');


        $ProjectTemplate=[];
        if($projects) {
            foreach($projects as $project) {
                if(ProjectTemplate::where('projectId',$project)->count() > 0) {
                    $ProjectTemplate[] = ProjectTemplate::where('projectId',$project)
                    ->with(['project' => function($query)  {
                        $query->select('project.id', 'project.name');
                    }])->get();

                }
            }
        }

        // foreach($ProjectTemplate as $t) {
        //     foreach($t as $o) {

        //         $mj = new \Mailjet\Client(
        //             getenv('MJ_APIKEY_PUBLIC'),
        //             getenv('MJ_APIKEY_PRIVATE'),
        //             true,
        //             ['version' => 'v3']
        //         );

        //         $response = $mj->get(Resources::$TemplateDetailcontent, ['id' => $o]);

        //     }
        // }

        return createResponse(config('httpResponse.SUCCESS'),
        "The template list.",
        ['message' => 'The template list', 'data' =>$ProjectTemplate]);

    }
}