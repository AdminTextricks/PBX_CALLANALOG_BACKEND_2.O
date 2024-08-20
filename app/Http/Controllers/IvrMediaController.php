<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\LOG;
use Illuminate\Validation\Rules\File;
use App\Models\IvrMedia;
use Validator;

class IvrMediaController extends Controller
{
    public function __construct(){

    }
    public function addIvrMedia(Request $request)
    {
        try {
			$validator = Validator::make($request->all(), [
                'company_id'=> 'required|numeric',
                'name'      => 'required|string|unique:ivr_media',
                'type'      => 'required|string|in:file,text',
                'media_file'=> [
                    'required_if:type,file',
                    File::types(['mp3', 'wav'])
                ],
                'input_text' => 'required_if:type,text',
            ]);
            if($validator->fails()) {
                return $this->output(false, $validator->errors()->first(), [], 409);
            }
            if($request->type == 'file' && !$request->hasFile('media_file')) {
                return $this->output(false, 'Uploaded file not found.', [], 409);
            }

            if($request->type == 'file'){
                $allowedfileExtension = ['mp3', 'wav'];
                $file = $request->file('media_file');
                $errors = [];
                $extension = $file->getClientOriginalExtension();
                $check = in_array($extension, $allowedfileExtension);

                if ($check) {
                    $mediaName = IvrMedia::where('name', $request->name)->first();
                    if (!$mediaName) {
                        $mediaFiles = $request->media_file;
                        $path = public_path('mediaFile');
                        $name = $mediaFiles->getClientOriginalName();
                        $getfilenamewitoutext = pathinfo($name, PATHINFO_FILENAME); // get the file name without extension
                        $createnewFileName = time() . '_' . str_replace(' ', '_', $getfilenamewitoutext) . '.' . $extension; // create new random file name
                        $createNewFileNameWitoutEXT = time().'_'.str_replace(' ','_', $getfilenamewitoutext); 
                        $upload = $file->move($path, $createnewFileName);
                        
                        if ($upload) {
                            $IvrMedia = IvrMedia::create([
                                'company_id'=> $request->company_id,
                                'name'      => $request->name,
                                'media_file'=> $createNewFileNameWitoutEXT,
                                'file_ext'  => $extension,
                                //'status'    => isset($request->status) ? $request->status : 1,
                            ]);
                            $response = $IvrMedia->toArray();
                            return $this->output(true, 'Media added successfully.', $response, 200);
                        } else {
                            return $this->output(false, 'Error occurred in file uploading.', [], 409);
                        }
                    } else {
                        return $this->output(false, 'Media Name is already exist.', [], 409);
                    }
                } else {
                    return $this->output(false, 'Invalid file format.', [], 409);
                }
            }else{
                $txt2 = !empty($request->input_text) ? $request->input_text : '';
                $txt=preg_replace('/\s+/', '', $txt2);           
                $lang = 'en-US';
                
                $dbfilename = strtolower($request->name).date("h:i:s"); // use for store value in database.//
                $createNewFileNameWitoutEXT = time().'_'.str_replace(' ','_', $request->name); 
                $txt=htmlspecialchars($txt);
                $txt=rawurlencode($txt);
                $path = public_path('mediaFile');
                $file = $path.'/'.$createNewFileNameWitoutEXT.".mp3";
                $filename   = $txt.".mp3"; // use for store value in database.//
                $extension  = "mp3";
                
                $audio=file_get_contents('https://translate.google.com/translate_tts?ie=UTF-8&client=gtx&q='.$txt.'&tl='.$lang.'');
                $test="<source src='data:audio/mpeg;base64,".base64_encode($audio)."'>";
                $picture="<audio controls='controls' autoplay> ".$test."</audio>";
                $newaudiofile= file_put_contents($file, $audio);
                if($newaudiofile){
                    $IvrMedia = IvrMedia::create([
                        'company_id'=> $request->company_id,
                        'name'      => $request->name,
                        'media_file'=> $createNewFileNameWitoutEXT,
                        'file_ext'  => $extension,
                        'status'    => isset($request->status) ? $request->status : 1,
                    ]);
                    $response = $IvrMedia->toArray();
                    return $this->output(true, 'Media added successfully.', $response, 200);
                }else{
                    return $this->output(false, 'Error occurred in file genrating.', [], 409);
                }
            }
        } catch (\Exception $e) {
			Log::error('Error in IVR Media Inserting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
    }

    public function updateIvrMedia(Request $request, $id)
    {
        $ivrMedia = IvrMedia::find($id);

        if (is_null($ivrMedia)) {
            return $this->output(false, 'This IvrMedia does not exist. Please try again!', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:ivr_media,name,' . $ivrMedia->id,
            'media_file' => [
                'nullable',
                File::types(['mp3', 'wav']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        if ($request->hasFile('media_file')) {
            $allowedfileExtension = ['mp3', 'wav'];
            $file = $request->file('media_file');
            $errors = [];
            $extension = $file->getClientOriginalExtension();

            if (!in_array($extension, $allowedfileExtension)) {
                return $this->output(false, 'File format is not valid!', [], 409);
            } else {
                $mediaFiles = $request->media_file;
                $path = public_path('mediaFile');

                if(\File::exists(public_path('mediaFile/'.$ivrMedia->media_file.'.'.$ivrMedia->file_ext))){
                    \File::delete(public_path('mediaFile/'.$ivrMedia->media_file.'.'.$ivrMedia->file_ext));
                }

                $name = $mediaFiles->getClientOriginalName();
                $getFileNameWithoutExt = pathinfo($name, PATHINFO_FILENAME);
                $createNewFileName = time() . '_' . str_replace(' ', '_', $getFileNameWithoutExt) . '.' . $extension;
                $createNewFileNameWitoutEXT = time().'_'.str_replace(' ','_', $getFileNameWithoutExt); 
                if (!$file->move($path, $createNewFileName)) {
                    return $this->output(false, 'Error occurred in file uploading.', [], 409);
                } else {
                    $ivrMedia->media_file = $createNewFileNameWitoutEXT;
                    $ivrMedia->file_ext = $extension;
                }
            }
        }

        $ivrMedia->name = $request->name;

        if ($ivrMedia->save()) {
            $updatedIvrMedia = IvrMedia::find($id);
            $response = $updatedIvrMedia->toArray();
            return $this->output(true, 'IvrMedia updated successfully.', $response, 200);
        } else {
            return $this->output(false, 'Error occurred in IvrMedia updating. Please try again!', [], 500);
        }
    }


    public function changeIVRMediaStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->output(false, $validator->errors()->first(), [], 409);
        }

        $IvrMedia = IvrMedia::find($id);
        if (is_null($IvrMedia)) {
            return $this->output(false, 'This Ivr Media not exist with us. Please try again!.', [], 200);
        } else {
            if ($IvrMedia->company_id == $request->user()->company_id || $request->user()->hasRole('super-admin')) {
                $IvrMedia->status = $request->status;
                $IvrMediaRes = $IvrMedia->save();
                if ($IvrMediaRes) {
                    $IvrMedia = IvrMedia::where('id', $id)->first();
                    $response = $IvrMedia->toArray();
                    return $this->output(true, 'IvrMedia updated successfully.', $response, 200);
                } else {
                    return $this->output(false, 'Error occurred in IvrMedia Updating. Please try again!.', [], 409);
                }
            } else {
                return $this->output(false, 'Sorry! You are not authorized to change status.', [], 209);
            }
        }
    }

    public function getAllActiveIvrMediaList(Request $request, $company_id)
    {
        $user = \Auth::user();
        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $IvrMedia = IvrMedia::where('status',   1)->where('company_id', $company_id)->get();
        } else {
            $IvrMedia = IvrMedia::where('status',   1)->where('company_id', $request->user()->company_id)->get();
        }
        if (is_null($IvrMedia)) {
            return $this->output(false, 'No Recode found', [], 200);
        } else {
            $IvrMediaRes = $IvrMedia->toArray();
            return $this->output(true, 'Success',   $IvrMediaRes, 200);
        }
    }

    public function getAllIvrMedia(Request $request)
    {
        $user = \Auth::user();
        $perPageNo = isset($request->perpage) ? $request->perpage : 10;
        $params = $request->params ?? "";
        $IvrMedia_id = $request->id ?? NULL;

        if (in_array($user->roles->first()->slug, array('super-admin', 'support','noc'))) {
            $IvrMedia_id = $request->id ?? NULL;
            if ($IvrMedia_id) {
                $data = IvrMedia::with('company:id,company_name,email,mobile')
                    ->select()->where('id', $IvrMedia_id)->get();
            } else {
                if ($params != "") {
                    $data = IvrMedia::with('company:id,company_name,email,mobile')
                        ->where('name', 'LIKE', "%$params%")
                        ->orWhere('media_file', 'LIKE', "%$params%")
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('company_name', 'like', "%{$params}%");
                        })
                        ->orWhereHas('company', function ($query) use ($params) {
                            $query->where('email', 'like', "%{$params}%");
                        })
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                } else {
                    $data = IvrMedia::with('company:id,company_name,email,mobile')
                        ->select()->paginate(
                            $perPage = $perPageNo,
                            $columns = ['*'],
                            $pageName = 'page'
                        );
                }
            }
        } else {

            $IvrMedia_id = $request->id ?? NULL;
            if ($IvrMedia_id) {
                $data = IvrMedia::with('company:id,company_name,email,mobile')
                    ->select()->where('id', $IvrMedia_id)->get();
            } else {
                if ($params != "") {
                    $data = IvrMedia::with('company:id,company_name,email,mobile')
                        ->where('company_id', '=',  $user->company_id)
                        ->where(function($query) use($params) {
							$query->where('name', 'like', "%{$params}%")
                            ->orWhere('media_file', 'LIKE', "%$params%");							
						})
                        ->paginate($perPage = $perPageNo, $columns = ['*'], $pageName = 'page');
                } else {
                    $data = IvrMedia::with('company:id,company_name,email,mobile')
                        ->where('company_id', '=',  $user->company_id)
                        ->select()->paginate(
                            $perPage = $perPageNo,
                            $columns = ['*'],
                            $pageName = 'page'
                        );
                }
            }
        }

        if ($data->isNotEmpty()) {
            $dd = $data->toArray();
            unset($dd['links']);
            return $this->output(true, 'Success', $dd, 200);
        } else {
            return $this->output(true, 'No Record Found', []);
        }
    }

    public function getAllIvrMediaByCompany(Request $request, $id)
    {
        $IvrMedia = IvrMedia::where('status',   1)->where('company_id', $id)->get();        
        if (is_null($IvrMedia)) {
            return $this->output(false, 'No Recode found', [], 200);
        } else {
            $IvrMediaRes = $IvrMedia->toArray();
            return $this->output(true, 'Success',   $IvrMediaRes, 200);
        }
    }

    public function deleteIvrMedia(Request $request, $id)
    {
        try {
            $IvrMedia = IvrMedia::where('id', $id)->first();
            if($IvrMedia){
				$resdelete = $IvrMedia->delete();
                if ($resdelete) {
                    return $this->output(true,'Success',200);
                } else {
                    return $this->output(false, 'Error occurred in Ivr-Media deleting. Please try again!.', [], 209);                    
                }
            }else{
                return $this->output(false,'Ivr-Media not exist with us.', [], 409);
            }
        } catch (\Exception $e) {
            Log::error('Error occurred in Ivr-Media Deleting : ' . $e->getMessage() .' In file: ' . $e->getFile() . ' On line: ' . $e->getLine());
            return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
        }
    }
}
