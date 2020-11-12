<?php

namespace App\Http\Controllers\Settings;

use App\Branch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Customer;
use App\Setting;
use FFI\Exception;
use Throwable;

class CustomersController extends Controller
{
    public function index()
    {
        return Customer::all();
    }

    public function customerSync()
    {
        $max_cus_id = Customer::max('last_id');
        if (!$max_cus_id) {
            $max_cus_id = 0;
        }


        $customers = Http::post('http://onimtait.dyndns.info:9000/api/AndroidApi/CommonExecute', [
            'SpName' => 'API_sp_CommonExecute',
            'HasReturnData' => 'T',
            'Parameters' => [
                [
                    'Para_Name' => '@Iid',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => '5',
                ], [
                    'Para_Name' => '@MaxId',
                    'Para_Type' => 'Int',
                    'Para_Lenth' => '0',
                    'Para_Direction' => 'Input',
                    'Para_Data' => $max_cus_id, //$max_cus_id
                ]
            ]
        ])->json()['CommonResult']['Table'];

        DB::beginTransaction();

        try {
            foreach ($customers as $key => $customer) {
                Customer::updateOrCreate([
                    'customer_code' => $customer['Cust_Code']
                ], [
                    'customer_code' => str_replace(' ', '', $customer['Cust_Code']),
                    'customer_name' => $customer['Cust_Name'],
                    'last_id' => $customer['Id_No'],
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'Success'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => 'Sync Error'], 500);
        }
    }

    public function store(Request $request)
    {
        $customer_settings = Setting::where('id_type', 4)->get()->first();
        $customer_old_id = $customer_settings->last_id;
        $customer_new_id = $customer_old_id + 1;
        $new_customer = false;
        $new_branch = false;


        DB::beginTransaction();

        try {

            if (!$request->customer_code) {
                $request->customer_code = "CUS-" . sprintf("%04d", $customer_new_id);
                Setting::where('id_type', 4)->update(['last_id' => $customer_new_id]);
                $new_customer = true;
            }

            $customer = Customer::updateOrCreate(
                [
                    'customer_code' => $request->customer_code,
                ],
                [
                    'customer_code' => $request->customer_code,
                    'customer_name' => $request->customer_name,
                    'owner' => $request->owner,
                    'address' => $request->address,
                    'contact_person' => $request->contact_person,
                    'mobile_no' => $request->mobile_no,
                    'email' => $request->email,
                    'website' => $request->website,
                    'software_coordinator' => $request->software_coordinator,
                    'marketing_coordinator' => $request->marketing_coordinator,
                    'account_coordinator' => $request->account_coordinator,
                ]
            );

            $updated_branches = [];

            foreach ($request['branches'] as $key => $branch) {
                $branch_settings = Setting::where('id_type', 5)->get()->first();
                $branch_old_id = $branch_settings->last_id;
                $branch_new_id = $branch_old_id + 1;

                array_push($updated_branches, $branch['branch_code']);

                if ($branch['branch_code'] == null) {
                    $branch['branch_code'] = "BR-" . sprintf("%04d", $branch_new_id);
                    Setting::where('id_type', 5)->update(['last_id' => $branch_new_id]);
                    $new_branch = true;
                }

                Branch::updateOrCreate([
                    'branch_code' => $branch['branch_code'],
                ], [
                    'customer_id' => $customer->id,
                    'agreement_number' => $branch['agreement_number'],
                    'agreement_type' => $branch['agreement_type'],
                    'warranty_period' => $branch['warranty_period'],
                    'valid_from' => $branch['valid_from'],
                    'valid_to' => $branch['valid_to'],
                    'branch_code' => $branch['branch_code'],
                    'branch_name' => $branch['branch_name'],
                    'branch_address' => $branch['branch_address'],
                    'branch_email' => $branch['branch_email'],
                    'branch_contact_person' => $branch['branch_contact_person'],
                    'branch_mobile_no' => $branch['branch_mobile_no'],
                    'branch_phone_no' => $branch['branch_phone_no'],
                    'pos_count' => $branch['pos_count'],
                    'server_count' => $branch['server_count'],
                    'terminal_count' => $branch['terminal_count'],
                    'status' => 1,
                ]);
            }

            if (!$new_customer && !$new_branch) {
                $branches = Branch::where('customer_id', $customer->id)->get();
                foreach ($branches as $key => $branch_) {
                    if (!in_array($branch_->branch_code, $updated_branches)) {
                        $branch_->update(['status' => 0]);
                    }
                }
            }



            DB::commit();
            return response()->json(['message' => 'Success'], 201);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e], 400);
        }
    }

    public function loadSelectedCustomer(Customer $customer)
    {
        return $customer->load('branches');
    }

    public function loadSelectedBranchAgreements($id)
    {
        $agreement_numbers = Branch::where('id', $id)->get();

        return $agreement_numbers;
    }
}
