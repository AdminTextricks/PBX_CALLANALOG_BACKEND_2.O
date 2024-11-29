<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TwilioService;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $to;
    public $message;

    /**
     * Create a new job instance.
     *
     * @param string $to
     * @param string $message
     */
    /**
     * Number of attempts.
     *
     * @var int
     */
    public $tries = 3; // Retry the job 3 times

    /**
     * Delay before retrying the job.
     *
     * @var int
     */
    public $backoff = 60; // Retry after 60 seconds

    public function __construct($to, $message)
    {
        $this->to = $to;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(TwilioService $twilioService): void
    {
        //$twilioService->sendSms($this->to, $this->message);
        if (!$twilioService->sendSms($this->to, $this->message)) {
            throw new \Exception("Failed to send SMS to {$this->to}");
        }
    }

    /**
     * Handle a failed job.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        // Log the failure
        \Log::error("SendSmsJob failed: " . $exception->getMessage());

        // Optionally, notify developers (e.g., via email or Slack)
        // \Notification::send(...);
    }
}
