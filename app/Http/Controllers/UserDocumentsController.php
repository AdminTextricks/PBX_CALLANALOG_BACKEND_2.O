<?php
//this is only for teting
namespace App\Http\Controllers;

use App\Models\UserDocuments;
use App\Models\User;
use Illuminate\Validation\Rules\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\ManageNotifications;
use Validator;

class UserDocumentsController extends Controller
{
    use ManageNotifications;
    public function __construct(){

    }
    public function addUserDocuments(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'doc_images' => 'required|array',
                'doc_images.*' => 'image|mimes:jpeg,png,jpg,pdf',	
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            if ($request->type == 'file' && !$request->hasFile('doc_images')) {
                return $this->output(false, 'Uploaded file not found.', [], 409);
            }
            $response = array();
            $error_flag = 0; 
            foreach ($request->file('doc_images') as $key => $file) {
                $allowedfileExtension = ['jpg', 'jpeg','png','pdf','JPG', 'JPEG','PNG','PDF'];
                $userDocuments = $request->file('doc_images');
                $extension = $file->getClientOriginalExtension();
               
                $check = in_array($extension, $allowedfileExtension);
                if ($check) {                    
                    $path = public_path('userDocuments');
                    $name = $file->getClientOriginalName();
                    $getfilenamewitoutext = pathinfo($name, PATHINFO_FILENAME); // get the file name without extension
                    $createnewFileName = time() . '_' . str_replace(' ', '_', $getfilenamewitoutext) . '.' . $extension; // create new random file name
                    $createNewFileNameWitoutEXT = time().'_'.str_replace(' ','_', $getfilenamewitoutext); 
                    $upload = $file->move($path, $createnewFileName);                    
                    if ($upload) {
                        $UserDocuments = UserDocuments::create([
                            'user_id'   => $request->user()->id,
                            'company_id'=> $request->user()->company_id,
                            'file_name' => $createNewFileNameWitoutEXT,
                            'file_ext'  => $extension,
                            'status'    => 0,
                        ]);
                        $User = User::find($request->user()->id);   
                        $User->is_verified_doc = 2;
                        $User_result = $User->save();
                        $response[$key][] = $UserDocuments->toArray();
                        $response[$key][] = array('status'=>'true', 'messange'=>'User Document uploaded successfully.');
                        //return $this->output(true, 'User Document uploaded successfully.', $response, 200);
                    } else {
                        $error_flag = 1;
                        $response[$key][] = array('status'=>'false', 'messange'=>'Error occurred in document uploading.');
                        //return $this->output(false, 'Error occurred in document uploading.', [], 409);
                    }
                } else {
                    $error_flag = 1;
                    $response[$key][] = array('status'=>'false', 'messange'=>'Invalid file format.');
                    //return $this->output(false, 'Invalid file format.', [], 409);
                }
            }
            if($error_flag == 1){
                return $this->output(false, 'Error occurred in User Document uploading.', $response, 200);
            }else{
                $User = User::find($request->user()->id); 
                /**
                 *  Notification code
                 */
                $subject = 'Upload Documents';
                $message = 'A new user has been uploaded documents: ' . $User->company->company_name . ' / ' . $User->company->email;
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $notifyUser = array();
                $res = $this->addNotification($User, $subject, $message, $type, $notifyUserType, $notifyUser);
                if (!$res) {
                    Log::error('Notification not created when user role: ' . $User->role_id . '  in paymentWithWallet method.');
                }
                /**
                 * End of Notification code
                 */
                return $this->output(true, 'User Document uploaded successfully.', $response, 200);
            }
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while Uploading documents: ' . $e->getMessage()], 400);
        }
    }

    public function getUserDocuments(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";
        if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
            $user_id = $request->id ?? NULL;
            if ($user_id) {
                $UserDocuments_data = UserDocuments::with('user:id,name,email')
                                ->with('company:id,company_name,email')
                                ->select('*')
                                ->where('user_id', $user_id)
                                ->get();
            } else {                
                if ($params != "") {
                    $UserDocuments_data = UserDocuments::select()                    
                                ->with('user:id,name,email')
                                ->with('company:id,company_name,email')
                                ->orWhere('file_name', 'like', "%$params%")
                                ->orWhereHas('company', function ($query) use ($params) {
                                    $query->where('company_name', 'like', "%{$params}%");
                                })
                                ->orWhereHas('company', function ($query) use ($params) {
                                    $query->where('email', 'like', "%{$params}%");
                                })
                                ->orWhereHas('user', function ($query) use ($params) {
                                    $query->where('name', 'like', "%{$params}%");
                                })
                                ->orderBy('id', 'DESC')
                                ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
                }else{                
                    $UserDocuments_data = UserDocuments::select()
                        ->with('user:id,name,email')
                        ->with('company:id,company_name,email')
                        ->orderBy('id', 'DESC')
                        ->paginate(
                        $perPage = $perPageNo,
                        $columns = ['*'],
                        $pageName = 'page'
                    );
                }                
            }
        } 
        
        if ($request->user()->hasRole('admin')) {        
            $user_id = $request->id ?? NULL;
            if ($user_id) {
                $UserDocuments_data = UserDocuments::with('user:id,name,email')->select('*')
                                ->with('company:id,company_name,email')
                                ->where('company_id', '=',  $user->company_id)
                                ->where('user_id', $user_id)->get();
            } else {              
                if ($params != "") {
                    $UserDocuments_data = UserDocuments::select()                    
                                ->with('user:id,name,email')
                                ->with('company:id,company_name,email')
                                ->where('extensions.company_id', '=', $user->company_id) 
                                ->orWhere('file_name', 'like', "%$params%")                                    
                                ->orderBy('id', 'DESC')
                                ->paginate(
                                $perPage = $perPageNo,
                                $columns = ['*'],
                                $pageName = 'page'
                            );
                }else{ 
                    $UserDocuments_data = UserDocuments::select()
                                ->with('company:id,company_name,email')
                                ->with('user:id,name,email')
                                ->where('user_id', '=',  $user->id)
                                ->orderBy('id', 'DESC')
                                ->paginate(
                                    $perPage = $perPageNo,
                                    $columns = ['*'],
                                    $pageName = 'page'
                                ); 
                }               
            }
        }
        if ($UserDocuments_data->isNotEmpty()) {
            $UserDocuments_dd = $UserDocuments_data->toArray();
            unset($UserDocuments_dd['links']);
            return $this->output(true, 'Success', $UserDocuments_dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }


    public function changeDocumentStatus(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}

		$UserDocuments = UserDocuments::find($id);
		if (is_null($UserDocuments)) {
			return $this->output(false, 'This User Documents details not exist with us. Please try again!.', [], 200);
		} else {
            $UserDocuments->status = $request->status;
            $UserDocumentsRes = $UserDocuments->save();
            if ($UserDocumentsRes) {
                $user_id = $UserDocuments->user_id;
                $UserDocuments = UserDocuments::where('user_id', $user_id)->get();
                //dd($UserDocuments[0]->user_id);
                
                $UserDetails = User::find($user_id);
                $docStatusPending   = 0;
                $docStatusRejected  = 0;
                $docStatusApproved  = 0;
                foreach($UserDocuments as $key => $value){
                    if($value->status == 2){
                        $docStatusRejected  = 1;
                    }
                    if($value->status == 1){
                        $docStatusApproved  = 1;                       
                    }
                    if($value->status == 0){
                        $docStatusPending   = 1;                      
                    }
                }
                if($docStatusRejected == 1){
                    $UserDetails->is_verified_doc = 3;
                    $UserDetails->save();
                }elseif($docStatusRejected == 0 && $docStatusPending == 0 && $docStatusApproved == 1 ){
                    $UserDetails->is_verified_doc = 1;
                    $UserDetails->save();
                }else{
                    $UserDetails->is_verified_doc = 2;
                    $UserDetails->save();
                }                
                $response = $UserDocuments->toArray();
                return $this->output(true, 'User Documents details updated successfully.', $response, 200);
            } else {
                return $this->output(false, 'Error occurred in User Documents details updating. Please try again!.', [], 200);
            }
		} 
	}

    public function updateUserDocument(Request $request, $id)
    {
        
        try {               
            $validator = Validator::make($request->all(), [
                'doc_images' => 'required',
                'doc_images.*' => 'image|mimes:jpeg,png,jpg,pdf',	
            ]);
            if ($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }

            if ($request->type == 'file' && !$request->hasFile('doc_images')) {
                return $this->output(false, 'Uploaded file not found.', [], 409);
            }
            $response = array();
            //$UserDocuments = UserDocuments::find($id);
            $UserDocuments = UserDocuments::where('id', $id)
                            //->where('user_id', $request->user()->id)
                            ->first();
            if ($UserDocuments) {
                $file = $request->file('doc_images');
                $allowedfileExtension = ['jpg', 'jpeg','png','pdf','JPG', 'JPEG','PNG','PDF'];
                $userDocuments_file = $request->file('doc_images');
                $extension = $file->getClientOriginalExtension();
                
                $check = in_array($extension, $allowedfileExtension);
                if ($check) {                    
                    $path = public_path('userDocuments');
                    $name = $file->getClientOriginalName();
                    $getfilenamewitoutext = pathinfo($name, PATHINFO_FILENAME); // get the file name without extension
                    $createnewFileName = time() . '_' . str_replace(' ', '_', $getfilenamewitoutext) . '.' . $extension; // create new random file name
                    $createNewFileNameWitoutEXT = time().'_'.str_replace(' ','_', $getfilenamewitoutext); 
                    $upload = $file->move($path, $createnewFileName);                    
                    if ($upload) {
                        $UserDocuments->file_name   = $createNewFileNameWitoutEXT;
                        $UserDocuments->file_ext    = $extension;
                        $UserDocuments->status      = '0';
                        $UserDocumentsRes = $UserDocuments->save();
                        if($UserDocumentsRes){
                            $User = User::find($request->user()->id);   
                            $User->is_verified_doc = 2;
                            $User_result = $User->save();
                            $UserDocuments = UserDocuments::where('id', $id)->first();
                            $response[] = $UserDocuments->toArray();
                            $response[] = array('status'=>'true', 'messange'=>'User Document uploaded successfully.');
                            //return $this->output(true, 'User Document uploaded successfully.', $response, 200);
                        }else{
                            return $this->output(false, 'Error occurred in User Documents details updating. Please try again!.', [], 200);
                        }
                    } else {
                        $response[] = array('status'=>'false', 'messange'=>'Error occurred in document uploading.');
                        //return $this->output(false, 'Error occurred in document uploading.', [], 409);
                    }                    
                } else {
                    $response[] = array('status'=>'false', 'messange'=>'Invalid file format.');
                    //return $this->output(false, 'Invalid file format.', [], 409);
                }
                $User = User::find($request->user()->id); 
                /**
                 *  Notification code
                 */
                $subject = 'Upload Documents';
                $message = 'A new user has been uploaded a documents: ' . $User->company->company_name . ' / ' . $User->company->email;
                $type = 'info';
                $notifyUserType = ['super-admin', 'support', 'noc'];
                $notifyUser = array();
                $res = $this->addNotification($User, $subject, $message, $type, $notifyUserType, $notifyUser);
                if (!$res) {
                    Log::error('Notification not created when user role: ' . $User->role_id . '  in paymentWithWallet method.');
                }
                /**
                 * End of Notification code
                 */
                return $this->output(true, 'User Document uploaded successfully.', $response, 200);
                
            } else {
                return $this->output(false, 'This User Documents details not exist with us. Please try again!.', [], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while creating product: ' . $e->getMessage()], 400);
        }
    }


    public function changeMultipleDocumentStatus(Request $request)
	{
		$validator = Validator::make($request->all(), [
            'id_array' => 'required|array',
			'status' => 'required',
		]);
		if ($validator->fails()) {
			return $this->output(false, $validator->errors()->first(), [], 409);
		}
        $id_array = $request->input('id_array');
        $response = array();
        foreach($id_array as $key => $id){
            $userDocument = UserDocuments::find($id);
            if (is_null($userDocument)) {
                return $this->output(false, 'This User Documents details not exist with us. Please try again!.', [], 200);
            } else {
                $userDocument->status = $request->status;
                $UserDocumentsRes = $userDocument->save();
                if ($UserDocumentsRes) {
                    $user_id = $userDocument->user_id;
                    $UserDocuments = UserDocuments::where('user_id', $user_id)->get();
                    //dd($UserDocuments[0]->user_id);
                    
                    $UserDetails = User::find($user_id);
                    $docStatusPending   = 0;
                    $docStatusRejected  = 0;
                    $docStatusApproved  = 0;
                    foreach($UserDocuments as $key => $value){
                        if($value->status == 2){
                            $docStatusRejected  = 1;
                        }
                        if($value->status == 1){
                            $docStatusApproved  = 1;                       
                        }
                        if($value->status == 0){
                            $docStatusPending   = 1;                      
                        }
                    }
                    if($docStatusRejected == 1){
                        $UserDetails->is_verified_doc = 3;
                        $UserDetails->save();
                    }elseif($docStatusRejected == 0 && $docStatusPending == 0 && $docStatusApproved == 1 ){
                        $UserDetails->is_verified_doc = 1;
                        $UserDetails->save();
                    }else{
                        $UserDetails->is_verified_doc = 2;
                        $UserDetails->save();
                    }                
                    $response[] = $userDocument->toArray();
                    //return $this->output(true, 'User Documents details updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in User Documents details updating. Please try again!.', [], 200);
                }
            } 
        }
        return $this->output(true, 'User Documents details updated successfully.', $response, 200);
	}
}
