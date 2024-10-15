<?php 
namespace App\Traits;
use App\Models\Notification;
use App\Models\NotificationRecipients;
use App\Models\Company;
trait ManageNotifications{
	// get  Notification
    public function addNotification($user, $subject, $message, $type, $notifyUserType, $notifyUser=[])
	{
		//return $test = array($user->id, $user->company_id, $user->company->parent_id);		
		//$Company = Company::select()->where('id',$company_id)->first();
			

		$Notification = Notification::create( [
				'subject' 	=> $subject,
				'message' 	=> $message,
				'type'		=> $type,
				'created_by'=> $user->id,
				'ip_address'=> request()->ip(),
			]);
		$this->addForSupperAdminNocSupport($Notification, $user, $notifyUserType,$notifyUser);
		return true;
	}

	public function addForSupperAdminNocSupport($Notification, $user, $notifyUserType,$notifyUser)
	{
		foreach($notifyUserType as $userType){
			if(!empty($notifyUser) && isset($notifyUser[$userType])){
				$user_id = $notifyUser[$userType];
			}else{
				$user_id = NULL;
			}
			NotificationRecipients::create( [
				'notification_id' 	=> $Notification->id,
				'user_id' 			=> $user_id,
				'user_type'			=> $userType,
			]);
		}
	}
}