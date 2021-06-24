<?php

namespace App\Listeners;

use App\Events\SendMessageEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\ProjectAgent;


class SendMessageEventListener
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
    public function handle(SendMessageEvent $event)
    {
        $userId     = $event->userId;
        $sender     = \App\User::find($event->userId);
        $message    = $event->message;
        $receivers =  \App\User::whereIn('id',$event->receiver)->get();

        foreach($receivers as $receiver)
        {
            if($receiver->notificationStatus == 1)
            {   
                App('mailer')->send('emails.sendMessage', ['sender' => $sender, 'receiver' => $receiver, 'msg' => $message], function ($mail) use ($sender, $receiver) {
                    $mail->from('info@metroengine.com', 'Metroengine')
                        ->to($receiver->email)
                        ->subject('New Message From '.$sender->firstName." ".$sender->lastName.' | Metroengine');
                });
            }
        }
   
    }
}   
