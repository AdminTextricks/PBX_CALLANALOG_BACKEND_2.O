<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{
    MainPlan,
    User,
    Role,
    Permission
};

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::insert([
            [
                'company_id' => 0,
                'name'=>'Super Admin',
                'email'=>'superadmin@textricks.com',
                'mobile'=>'8218098735',
				'account_code' => '82180',
                'address'=>'f7 Sector 3 Noida Uttar Pradesh',
                'country_id'=>'108',
                'state_id'=>'1673',
                'city'=>'Noida',
                'zip'=>'201301',
                'role_id'=>'1',
                'status'=>'1',
                'is_verified'=>1,
                'password'=>Hash::make('adminpassword'),
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s')
            ],
            [
                'company_id' => 0, 
                'name'=>'Support',
                'email'=>'support@textricks.com',
                'mobile'=>'8218098736',
				'account_code' => '82181',
                'address'=>'f7 Sector 3 Noida Uttar Pradesh',
                'country_id'=>'108',
                'state_id'=>'1673',
                'city'=>'Noida',
                'zip'=>'201301',
                'role_id'=>'2',
                'status'=>'1',
                'is_verified'=>1,
                'password'=>Hash::make('supportpassword'),
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s')
            ],
            [
                'company_id' => 0,
                'name'=>'NOC',
                'email'=>'noc@textricks.com',
                'mobile'=>'8218098737',
				'account_code' => '82182',
                'address'=>'f7 Sector 3 Noida Uttar Pradesh',
                'country_id'=>'108',
                'state_id'=>'1673',
                'city'=>'Noida',
                'zip'=>'201301',
                'role_id'=>'3',
                'status'=>'1',
                'is_verified'=>1,
                'password'=>Hash::make('nocpassword'),
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s')
            ],
        ]);

        Role::insert([
            ['name'=>'Super Admin','slug'=>'super-admin','description'=>'', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
            ['name'=>'Support','slug'=>'support', 'description'=>'', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'NOC','slug'=>'noc', 'description'=>'', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
            ['name'=>'Admin','slug'=>'admin', 'description'=>'Company admin', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
            ['name'=>'Reseller','slug'=>'reseller', 'description'=>'Reseller', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
            ['name'=>'User','slug'=>'user', 'description'=>'Company User', 'status'=>'1', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
        ]);

        Permission::insert([
            ['name'=>'Add User', 'slug'=>'add-user', 'permission_group' =>'User', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Edit User', 'slug'=>'edit-user','permission_group' =>'User', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Delete User', 'slug'=>'delete-user', 'permission_group' =>'User', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Get All User', 'slug'=>'get-all-user', 'permission_group' =>'User', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Add Role', 'slug'=>'add-role', 'permission_group' =>'Role', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Edit Role', 'slug'=>'edit-role', 'permission_group' =>'Role', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Delete Role', 'slug'=>'delete-role', 'permission_group' =>'Role', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
			['name'=>'Get All Role', 'slug'=>'get-all-role', 'permission_group' =>'Role', 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')],
        ]);

        MainPlan::insert([
            ['name' => 'Fix per month', 'status' => '1', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Pay as you Go', 'status' => '1', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);

        DB::table('users_roles')
			->insert(array(
				array('user_id' => 1, 'role_id' => 1),
				array('user_id' => 2, 'role_id' => 2),
				array('user_id' => 3, 'role_id' => 3),
			));

    }
}
