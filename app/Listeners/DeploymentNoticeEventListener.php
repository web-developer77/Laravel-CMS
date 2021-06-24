<?php

namespace App\Listeners;

use App\Events\DeploymentNoticeEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\ProjectAgent;


class DeploymentNoticeEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  AddNewsLettersEvent  $event
     * @return void
     */
    public function handle(DeploymentNoticeEvent $event)
    {
        $project = $event->project;

        $receivers = [];

        // Get master agent associated with the project
        $masterAgents = ProjectAgent::where('projectId', $project->id)->where('role', 'Master Agent')->with('user')->get();
        foreach ($masterAgents as $masterAgent) 
            $receivers[] = $masterAgent->user;

        // Get agents/affiliates associated with the project
        $agents = \App\UnitAgent::where('projectId', $project->id)->with('user')->groupBy('userId')->get();

        foreach ($agents as $agent) 
            $receivers[] = $agent->user;
       
        $messages = [];
        foreach($receivers as $receiver)
        {  
            App('mailer')->send('emails.DeploymentNotice', ['receiver' => $receiver ,'project' => $project ] , function ($mail) use ($receiver) {
                        $mail->from('info@metroengine.com', 'Metroengine')
                            ->to($receiver->email)
                            ->subject('Project Deployment Notice | Metroengine');
                            \Log::info($receiver->email);
                });

            $messages[] = ['senderId' => $project->createdBy, 'receiverId' => $receiver->id, 'content' => $project->name." has been deployed.", 'created_at' => date('Y-m-d H:i:s')];            

        }
        if(count($messages) > 0)
            \App\Message::insert($messages);
    }
}   
