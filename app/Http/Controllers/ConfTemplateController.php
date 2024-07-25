<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ConfTemplate;
use Validator;

class ConfTemplateController extends Controller
{
	public function __construct()
	{
	}

	public function addConfTemplate(Request $request)
	{
		try {
			DB::beginTransaction();
			$validator = Validator::make($request->all(), [
				'template_id'	    => 'required|unique:conf_templates',
				'template_name'	    => 'required|string',
				'template_contents' => 'required',
				'user_group'        => 'required',
			]);
			if ($validator->fails()) {
				return $this->output(false, $validator->errors()->first(), [], 409);
			}

			$user = \Auth::user();
			if (!is_null($user)) {
				$ConfTemplate = ConfTemplate::where('template_id', $request->template_id)
					->where('template_name', $request->template_name)
					->first();

				if (!$ConfTemplate) {
					$ConfTemplate = ConfTemplate::create([
						'template_id'	    => $request->template_id,
						'template_name'     => $request->template_name,
						'template_contents'	=> $request->template_contents,
						'user_group'	    => $request->user_group,
					]);
					$response = $ConfTemplate->toArray();
					DB::commit();
					return $this->output(true, 'Conf Template added successfully.', $response);
				} else {
					DB::commit();
					return $this->output(false, 'This Conf Template already exist with us.');
				}
			} else {
				DB::commit();
				return $this->output(false, 'You are not authorized user.');
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
	}


	public function getAllConfTemplate(Request $request)
	{
		$user  = \Auth::user();
		$perPageNo = isset($request->perpage) ? $request->perpage : 25;
		$params = $request->params ?? "";

		if (in_array($user->roles->first()->slug, array('super-admin', 'support', 'noc'))) {
			$confTemplate_id = $request->id ?? NULL;
			if ($confTemplate_id) {
				$data = ConfTemplate::select()->where('id', $confTemplate_id)->get();
			} else {
				$data = ConfTemplate::select()->paginate(
					$perPage = $perPageNo,
					$columns = ['*'],
					$pageName = 'page'
				);
			}
		} else {
			return $this->output(false, 'You are not authorized user.');
		}

		if ($data->isNotEmpty()) {
			$dd = $data->toArray();
			unset($dd['links']);
			return $this->output(true, 'Success', $dd, 200);
		} else {
			return $this->output(true, 'No Record Found', []);
		}
	}


	public function updateConfTemplate(Request $request, $id)
	{
		try {
			DB::beginTransaction();
			$ConfTemplate = ConfTemplate::find($id);
			if (is_null($ConfTemplate)) {
				DB::commit();
				return $this->output(false, 'This Conf Template not exist with us. Please try again!.', [], 409);
			} else {
				$validator = Validator::make($request->all(), [
					'template_id'	    => 'required|unique:conf_templates,template_id,' . $ConfTemplate->id,
					'template_name'	    => 'required|string',
					'template_contents' => 'required',
					'user_group'        => 'required',
				], [
					'template_id' => 'This template is already exist with us.',
				]);
				if ($validator->fails()) {
					return $this->output(false, $validator->errors()->first(), [], 409);
				}

				$ConfTemplateOld = ConfTemplate::where('template_id', $request->template_id)
					->where('template_name', $request->template_name)
					->where('id', '!=', $id)->first();
				if (!$ConfTemplateOld) {
					$ConfTemplate->template_id   	= $request->template_id;
					$ConfTemplate->template_name    = $request->template_name;
					$ConfTemplate->template_contents = $request->template_contents;
					$ConfTemplate->user_group 		= $request->user_group;
					$ConfTemplatesRes     			= $ConfTemplate->save();
					if ($ConfTemplatesRes) {
						$ConfTemplate = ConfTemplate::where('id', $id)->first();
						$response = $ConfTemplate->toArray();
						DB::commit();
						return $this->output(true, 'Conf Template updated successfully.', $response, 200);
					} else {
						DB::commit();
						return $this->output(false, 'Error occurred in Conf Template Updating. Please try again!.', [], 200);
					}
				} else {
					DB::commit();
					return $this->output(false, 'This Conf Template already exist with us.', [], 409);
				}
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
	}

	public function deleteConfTemplate(Request $request, $id)
	{
		try {
			DB::beginTransaction();
			$ConfTemplate = ConfTemplate::where('id', $id)->first();
			if ($ConfTemplate) {
				$resdelete = $ConfTemplate->delete();
				if ($resdelete) {
					DB::commit();
					return $this->output(true, 'Success', 200);
				} else {
					DB::commit();
					return $this->output(false, 'Error occurred in Conf Template removing. Please try again!.', [], 209);
				}
			} else {
				DB::commit();
				return $this->output(false, 'Conf Template not exist with us.', [], 409);
			}
		} catch (\Exception $e) {
			DB::rollback();
			//return $this->output(false, $e->getMessage());
			return $this->output(false, 'Something went wrong, Please try after some time.', [], 409);
		}
	}
}
