<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordResetTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Validator;
use Mail;

class PasswordResetTokensController extends Controller
{
    public function sendForgotPasswordOTP(Request $request){
        
		$validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|exists:users',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
		
        $email = $request->email;
        $user = User::where('email', $email)->first();
        if(!$user){
			return $this->output(false, 'Email Id dose not exist!');            
        }
        // Generate Token
		$this->sendOtp($user);//OTP SEND
		//$response = $user->toArray(); 
		return $this->output(true, 'OTP has been sent on your registered email successfully.', []);		
    }
	
	public function sendOtp($user){
        $otp = rand(100000,999999);
        $time = time();
		$newOTP = PasswordResetTokens::updateOrCreate(
				['email' => $user->email],
				['email' => $user->email,
				 'otp' => $otp,
				 'created_at' => Carbon::now()
				]);
		if($newOTP){
			$data['email'] = $user->email;
			$data['title'] = 'Reset Password';
			$data['body'] = 'Hi, Your OTP is:- '.$otp;
			/* Mail::send('mailVerification',['data'=>$data],function($message) use ($data){
				$message->to($data['email'])->subject($data['title']);
			}); */
            dispatch(new \App\Jobs\SendEmailJob($data));
		}else{
			return $this->output(false, 'Error occurred in OTP creation. Try after some time.');
		}
    }

    public function reset(Request $request, $otp){
        // Delete OTP older than 2 minute
        $formatted = Carbon::now()->subMinutes(2)->toDateTimeString();
        PasswordResetTokens::where('created_at', '<=', $formatted)->delete();
		
		$validator = Validator::make($request->all(), [
            'password' => 'required|confirmed',
        ]);
        if ($validator->fails()){
            return $this->output(false, $validator->errors()->first(), [], 409);
        }
		
        $passwordreset = PasswordResetTokens::where('otp', $otp)->first();
        if(!$passwordreset){
			return $this->output(false, 'OTP is Invalid or Expired.', [], 404);           
        }
        $user = User::where('email', $passwordreset->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();
        // Delete the token after resetting password
        PasswordResetTokens::where('email', $user->email)->delete();
		return $this->output(true, 'Password Reset Success.', [], 200);         
    }
}
