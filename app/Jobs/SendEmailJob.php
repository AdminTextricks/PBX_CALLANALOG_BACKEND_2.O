<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
	public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
		$data = $this->data;
        if(isset($data['email_template'])){
            $mail_template = $data['email_template'];
        }else{
            $mail_template = 'mailVerification';
        }
		Mail::send($mail_template,['data'=>$data],function($message) use ($data){
			$message->to($data['email'])->subject($data['title']);
		});
    }
}
