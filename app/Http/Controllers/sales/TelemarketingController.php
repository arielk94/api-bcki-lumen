<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;


class TelemarketingController extends Controller
{
    public function getTelemarketing(Request $request)
    {
        $sqlWhere = "1=1";
        $CustomerId = $request->CustomerId;
        $active_flag = $request->active_flag;
        $SalesId = $request->SalesId;
        $user_id = $request->user_id;
        $customer_respon = $request->customer_respon;
        $need_response = $request->need_response;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        $dept_id = $request->dept_id;
        
        if ($CustomerId) {    
            $sqlWhere.= " and telemarketing_status.CustomerId = '$CustomerId'";
        }       
        if ($active_flag) {    
            $sqlWhere.= " and telemarketing_status.active_flag = '$active_flag'";
        }
        // else{
        //     $sqlWhere.= " and telemarketing_status.active_flag = 'Y'";
        // }
        if ($SalesId) {    
            $sqlWhere.= " and telemarketing_status.SalesId = '$SalesId'";
        } 
        if ($user_id) {    
            $sqlWhere.= " and telemarketing_status.user_id = '$user_id'";
        }   
        if ($customer_respon) {    
            $sqlWhere.= " and telemarketing_status.customer_respon = '$customer_respon'";
        }   
        if ($need_response) {    
            $sqlWhere.= " and telemarketing_status.need_response = '$need_response'";
        }
        if($id_site)
        {
            $sqlWhere.= " and telemarketing_status.id_site = '$id_site'";
        }
        if($id_cluster)
        {
            $sqlWhere.= " and telemarketing_status.id_cluster = '$id_cluster'";
        }
        if($id_house)
        {
            $sqlWhere.= " and telemarketing_status.id_house = '$id_house'";
        }
        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and telemarketing_status.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and telemarketing_status.tr_date BETWEEN CONVERT(datetime, '" . $start_date . " 00:00:00') AND GETDATE()";
            }
        }
        if ($dept_id) {    
            if($dept_id == 'Teknik')
            {
                $sqlWhere.= " and telemarketing_status.customer_respon in ('c1', 'c2', 'c4')";
            }
            else if($dept_id == 'Legal')
            {
                $sqlWhere.= " and telemarketing_status.customer_respon in ('c3', 'c5')";
            }
        }
        $datas = DB::connection('sqlSrvFPI')
                    ->table('telemarketing_status')
                    ->selectraw("
                        telemarketing_status.id, 
                        telemarketing_status.CustomerId, 
                        telemarketing_status.tr_date, 
                        telemarketing_status.remark1, 
                        telemarketing_status.remark2, 
                        telemarketing_status.user_id, 
                        telemarketing_status.customer_respon, 
                        telemarketing_status.need_response, 
                        telemarketing_status.SalesId, 
                        telemarketing_status.active_flag, 
                        telemarketing_status.user_response,
                        telemarketing_status.response_date,
                        isnull(telemarketing_status.id_site,'') as id_site,
                        isnull(site.site_name,'') as site_name,
                        isnull(telemarketing_status.id_cluster,'') as id_cluster,
                        isnull(site_cluster.cluster_name,'') as cluster_name,
                        isnull(telemarketing_status.id_house,'') as id_house, 
                        isnull(house_unit.house_name,'') as house_name,
                        appl_constant.str1, 
                        appl_constant.str2,
                        isnull(Sales.NamaSales,'') as NamaSales,
                        Customer.NamaCustomer,
                        isnull(Customer.Telepon,'') as Telepon
                    ")
                    ->leftjoin('appl_constant', function($join) {
                        $join->on('telemarketing_status.customer_respon', '=', 'appl_constant.str1')
                             ->where('appl_constant.key_code', '=', 'cust_resp');
                    })
                    ->leftjoin('Sales', function($join) {
                        $join->on('telemarketing_status.SalesId', '=', 'Sales.SalesId');
                    })
                    ->leftjoin('Customer', function($join) {
                        $join->on('telemarketing_status.CustomerId', '=', 'Customer.CustomerId');
                    })
                    ->leftJoin('site', 'site.id_site', '=', 'telemarketing_status.id_site')
                    ->leftJoin('site_cluster', function($join) {
                        $join->on('site_cluster.id_cluster', '=', 'telemarketing_status.id_cluster')
                            ->on('site_cluster.id_site', '=', 'telemarketing_status.id_site');
                    })
                    ->leftJoin('house_unit', function($join) {
                        $join->on('house_unit.id_house', '=', 'telemarketing_status.id_house')
                            ->on('house_unit.id_cluster', '=', 'telemarketing_status.id_cluster');
                    })
                    ->whereRaw($sqlWhere)
                    ->get();
                    
        $datas->map(function ($data) {
            // Ambil file berdasarkan ID dari data utama (id_telemarketing_ref)
            $data->Files = DB::connection('sqlSrvFPI')
                ->table('telemarketing_file')
                ->selectRaw("id, nama_document, doc_path, doc_file, type_file, dt_created, created_by")
                ->where('id_telemarketing_ref', $data->id) // $data->id adalah telemarketing_status.id
                ->orderBy("item_num")
                ->get()
                ->map(function ($file) {
                    $file->file_hash = $file->doc_file;
                    $file->doc_file = url("/storage/{$file->doc_path}/{$file->doc_file}");
                    return $file;
                });
            
            return $data;
        });

        return $this->sendResponse($datas, 'Successfully get tele data');
    }

    public function showCustGroup(Request $request){
        $sqlWhere = "1=1";
        $NamaCustomer = $request->NamaCustomer;
           
        if ($NamaCustomer) {    
            $sqlWhere.= " and Customer.NamaCustomer like '%$NamaCustomer%'";
        }

        $result = DB::connection('sqlSrvFPI')
                ->table('Customer')
                ->selectRaw('DISTINCT CustomerId,NamaCustomer,Alamat')
                ->whereRaw($sqlWhere)
                ->get();

        return $this->sendResponse($result, 'Successfully get Customer');
    }

    private function insCustType($cust_type){
        
        if ($cust_type=='OTHER')
        {
            $cust_type = 'O';
        }
        else if ($cust_type=='PROJECT')
        {            
            $cust_type = 'P';
        }
        else if ($cust_type=='RETAIL')
        {            
            $cust_type = 'R';
        }
        else
        {
            $cust_type = ' ';
        }
        return $cust_type;
    }

    private function genCustID($CustomerId) //6digit
    {
        $dateYear = date("Y");
        $dateMonth = date("m");
        $gen_cluster_id = '';

        $getLastTrID =  DB::connection('sqlSrvFPI')
                        ->table('Customer')
                        ->max('CustomerId');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -6) + 1;
            $gen_cluster_id = str_pad($seq, 6, '0', STR_PAD_LEFT);
        } else {
            $gen_cluster_id = '000001';
        }

        return $gen_cluster_id;
    }

    public function saveCustomer(Request $request)
    {
        try
        {
            $data = DB::connection('sqlSrvFPI')
            ->table('Customer')
            ->where('CustomerId', $request->CustomerId)
            ->first(); //cek ada data
            
            if($data)
            {
                $cust_type = $this->insCustType($request->cust_type);
                $update = DB::connection('sqlSrvFPI')
                ->table('Customer')
                ->where('CustomerId', $request->CustomerId)
                ->update([
                    'SalesId' => $request->SalesId ? $request->SalesId : ' ',
                    'NamaCustomer' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                    'Person' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                    'id_card' => $request->id_card ? $request->id_card : '', 
                    'id_card_no' => $request->id_card_no ? $request->id_card_no : ' ',
                    'Telepon' => $request->Telepon ? $request->Telepon : ' ',
                    'owner_phone' => $request->Telepon ? $request->Telepon : ' ',
                    'Fax' => $request->Fax ? $request->Fax : ' ',
                    'email' => $request->email ? $request->email : ' ',
                    'credit_limit' => $request->credit_limit ? $request->credit_limit : ' ',
                    
                    'Alamat' => $request->Alamat ? $request->Alamat : ' ',
                    'business_start_date' => $request->business_start_date ? $request->business_start_date : '',
                    'no_nib' => $request->no_nib ? $request->no_nib : ' ',
                    'no_skd' => $request->no_skd ? $request->no_skd : ' ',
                    'Propinsi' => $request->province_code ? $request->province_code : ' ',
                    'owner_province' => $request->province_code ? $request->province_code : ' ',
                    'Kota' => $request->city_code ? $request->city_code : ' ',
                    'Real_City' => $request->city_code ? $request->city_code : ' ',
                    'owner_city' => $request->city_code ? $request->city_code : ' ',
                    'district' => $request->district_code ? $request->district_code : ' ',
                    'vilage' => $request->village_code ? $request->village_code : ' ',
                    'NPWP' => $request->NPWP ? $request->NPWP : ' ',
                    'skbdn' => $request->skbdn ? $request->skbdn : ' ',
                    
                    'business_status' => $request->business_status ? $request->business_status : ' ',
                    'business_entities' => $request->business_entities ? $request->business_entities : ' ',
                    'tax_status' => $request->tax_status ? $request->tax_status : ' ',
                    'office_tipe' => $request->office_tipe ? $request->office_tipe : ' ',
                    'kind_of_business' => $request->kind_of_business ? $request->kind_of_business : ' ',
                    'ownership' => $request->ownership ? $request->ownership : ' ',
                    'building' => $request->building ? $request->building : ' ',
                    'employee_num' => $request->employee_num ? $request->employee_num : ' ',
                    'omzet' => $request->omzet ? $request->omzet : ' ',

                    'dt_modified' => now()
                ]);
                return $this->sendResponse('Success Edit', 'Successfully edit Customer Data');
            }
            else{
                $CustID = $this->genCustID($request->CustomerId);
                // $cust_type = $this->insCustType($request->cust_type);

                $insert = DB::connection('sqlSrvFPI')
                ->table('Customer')
                ->insert([
                    'CustomerId' => $CustID,
                    'SalesId' => $request->SalesId ? $request->SalesId : ' ',
                    'NamaCustomer' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                    'Person' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                    'id_card' => $request->id_card ? $request->id_card : '', 
                    'id_card_no' => $request->id_card_no ? $request->id_card_no : ' ',
                    'Telepon' => $request->Telepon ? $request->Telepon : ' ',
                    'owner_phone' => $request->Telepon ? $request->Telepon : ' ',
                    'Fax' => $request->Fax ? $request->Fax : ' ',
                    'email' => $request->email ? $request->email : ' ',
                    'credit_limit' => $request->credit_limit ? $request->credit_limit : ' ',
                    
                    'Alamat' => $request->Alamat ? $request->Alamat : ' ',
                    'business_start_date' => $request->business_start_date ? $request->business_start_date : '',
                    'no_nib' => $request->no_nib ? $request->no_nib : ' ',
                    'no_skd' => $request->no_skd ? $request->no_skd : ' ',
                    'Propinsi' => $request->province_code ? $request->province_code : ' ',
                    'owner_province' => $request->province_code ? $request->province_code : ' ',
                    'Kota' => $request->city_code ? $request->city_code : ' ',
                    'Real_City' => $request->city_code ? $request->city_code : ' ',
                    'owner_city' => $request->city_code ? $request->city_code : ' ',
                    'district' => $request->district_code ? $request->district_code : ' ',
                    'vilage' => $request->village_code ? $request->village_code : ' ',
                    'NPWP' => $request->NPWP ? $request->NPWP : ' ',
                    'skbdn' => $request->skbdn ? $request->skbdn : ' ',
                    
                    'business_status' => $request->business_status ? $request->business_status : ' ',
                    'business_entities' => $request->business_entities ? $request->business_entities : ' ',
                    'tax_status' => $request->tax_status ? $request->tax_status : ' ',
                    'office_tipe' => $request->office_tipe ? $request->office_tipe : ' ',
                    'kind_of_business' => $request->kind_of_business ? $request->kind_of_business : ' ',
                    'ownership' => $request->ownership ? $request->ownership : ' ',
                    'building' => $request->building ? $request->building : ' ',
                    'employee_num' => $request->employee_num ? $request->employee_num : ' ',
                    'omzet' => $request->omzet ? $request->omzet : ' ',
                    
                    'PerTanggal' => $request->PerTanggal ? $request->PerTanggal : now()
                ]);
                return $this->sendResponse($CustID, 'Successfully insert Customer Data');
            }
        }        
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function deleteCustomer(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        
        try {
            
            $sales = DB::connection('sqlSrvFPI')
            ->table('Customer')
            ->where('CustomerId', $request->CustomerId)
            ->first();
            
            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                ->table('Customer')
                ->where('CustomerId', $request->CustomerId)
                ->update([
                    'active_flag' => 'N',
                    'dt_modified' => now(),
                ]);
                DB::connection('sqlSrvFPI')->commit();
                
                return $this->sendResponse('Success Update', 'Successfully Delete Customer');
            } else {
                return $this->sendError('Customer tidak ditemukan', 'Error', 500);
            }           
            
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }      
    }

    public function activateCustomer(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        
        try {
            
            $sales = DB::connection('sqlSrvFPI')
            ->table('Customer')
            ->where('CustomerId', $request->CustomerId)
            ->first();
            
            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                ->table('Customer')
                ->where('CustomerId', $request->CustomerId)
                ->update([
                    'active_flag' => 'Y',
                    'dt_modified' => now(),
                ]);
                DB::connection('sqlSrvFPI')->commit();
                
                return $this->sendResponse('Success Update', 'Successfully Activate Customer');
            } else {
                return $this->sendError('Customer tidak ditemukan', 'Error', 500);
            }           
            
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }      
    }
    
    public function saveTelemarketingWithoutCreateCustomer(Request $request)
    {
        try
        {
            $main_id = DB::connection('sqlSrvFPI')
                ->table('telemarketing_status')
                ->insertGetId([
                    'CustomerId' => $request->CustomerId ? $request->CustomerId : ' ',
                    'tr_date' => now(),
                    'remark1' => $request->remark1 ? $request->remark1 : ' ',
                    'remark2' => $request->remark2 ? $request->remark2 : ' ',
                    'user_id' => getAuth($request)->user_id2 ?? '',
                    'customer_respon' => $request->customer_respon ? $request->customer_respon : '',
                    'need_response' => $request->need_response ? $request->need_response : '',
                    'SalesId' => $request->SalesId ? $request->SalesId : '',
                    'user_response' => $request->user_response ? $request->user_response : '',
                    'active_flag' => $request->active_flag ? $request->active_flag : '',
                    'id_site' => $request->id_site ? $request->id_site : '',
                    'id_cluster' => $request->id_cluster ? $request->id_cluster : '',
                    'id_house' => $request->id_house ? $request->id_house : ''
            ]);
            $this->handleFileAttachments($request, $main_id);         
            $this->sendNotifWaTelemarketing($main_id);
            return $this->sendResponse($main_id, 'Successfully insert Telemarketing Data');            
        }        
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveTelemarketingWithCreateCustomer(Request $request)
    {
        try
        {   
            $CustID = $this->genCustID($request->CustomerId);
                // $cust_type = $this->insCustType($request->cust_type);
            DB::connection('sqlSrvFPI')
            ->table('Customer')
            ->insert([
                'CustomerId' => $CustID,
                'SalesId' => $request->SalesId ? $request->SalesId : ' ',
                'NamaCustomer' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                'Person' => $request->NamaCustomer ? $request->NamaCustomer : ' ',
                'id_card' => $request->id_card ? $request->id_card : '', 
                'id_card_no' => $request->id_card_no ? $request->id_card_no : ' ',
                'Telepon' => $request->Telepon ? $request->Telepon : ' ',
                'owner_phone' => $request->Telepon ? $request->Telepon : ' ',
                'Fax' => $request->Fax ? $request->Fax : ' ',
                'email' => $request->email ? $request->email : ' ',
                'credit_limit' => $request->credit_limit ? $request->credit_limit : ' ',
                
                'Alamat' => $request->Alamat ? $request->Alamat : ' ',
                'business_start_date' => $request->business_start_date ? $request->business_start_date : '',
                'no_nib' => $request->no_nib ? $request->no_nib : ' ',
                'no_skd' => $request->no_skd ? $request->no_skd : ' ',
                'Propinsi' => $request->province_code ? $request->province_code : ' ',
                'owner_province' => $request->province_code ? $request->province_code : ' ',
                'Kota' => $request->city_code ? $request->city_code : ' ',
                'Real_City' => $request->city_code ? $request->city_code : ' ',
                'owner_city' => $request->city_code ? $request->city_code : ' ',
                'district' => $request->district_code ? $request->district_code : ' ',
                'vilage' => $request->village_code ? $request->village_code : ' ',
                'NPWP' => $request->NPWP ? $request->NPWP : ' ',
                'skbdn' => $request->skbdn ? $request->skbdn : ' ',
                
                'business_status' => $request->business_status ? $request->business_status : ' ',
                'business_entities' => $request->business_entities ? $request->business_entities : ' ',
                'tax_status' => $request->tax_status ? $request->tax_status : ' ',
                'office_tipe' => $request->office_tipe ? $request->office_tipe : ' ',
                'kind_of_business' => $request->kind_of_business ? $request->kind_of_business : ' ',
                'ownership' => $request->ownership ? $request->ownership : ' ',
                'building' => $request->building ? $request->building : ' ',
                'employee_num' => $request->employee_num ? $request->employee_num : ' ',
                'omzet' => $request->omzet ? $request->omzet : ' ',
                
                'PerTanggal' => $request->PerTanggal ? $request->PerTanggal : now()
            ]);
            
            $main_id = DB::connection('sqlSrvFPI')
            ->table('telemarketing_status')
            ->insertGetId([
                'CustomerId' => $CustID,
                'tr_date' => now(),
                'remark1' => $request->remark1 ? $request->remark1 : ' ',
                'remark2' => $request->remark2 ? $request->remark2 : ' ',
                'user_id' => getAuth($request)->user_id2 ?? '',
                'customer_respon' => $request->customer_respon ? $request->customer_respon : '',
                'need_response' => $request->need_response ? $request->need_response : '',
                'SalesId' => $request->SalesId ? $request->SalesId : '',
                'user_response' => $request->user_response ? $request->user_response : '',
                'active_flag' => $request->active_flag ? $request->active_flag : '',
                'id_site' => $request->id_site ? $request->id_site : '',
                'id_cluster' => $request->id_cluster ? $request->id_cluster : '',
                'id_house' => $request->id_house ? $request->id_house : ''
            ]);
            $this->handleFileAttachments($request, $main_id);
            $this->sendNotifWaTelemarketing($main_id);
            return $this->sendResponse($main_id, 'Successfully insert Telemarketing Data');
        }        
        catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function saveFeedbackTelemarketing(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        
        try {
            
            $tele = DB::connection('sqlSrvFPI')
            ->table('telemarketing_status')
            ->where('id', $request->id)
            ->first();
            
            if ($tele) {
                $save = DB::connection('sqlSrvFPI')
                ->table('telemarketing_status')
                ->where('id', $request->id)
                ->update([
                    'user_response' => $request->user_response ? $request->user_response : '',
                    'user_id' => getAuth($request)->user_id2 ?? '',
                    'response_date' => now('Asia/Jakarta'),
                ]);

                $this->handleFileAttachments($request, $request->id);
                // dd($request->id);   
                $this->sendNotifWaFeedbackTelemarketing($request->id);      

                DB::connection('sqlSrvFPI')->commit();
                
                return $this->sendResponse('Success Update', 'Successfully Update Feedback Telemarketing');
            } else {
                return $this->sendError('Telemarketing tidak ditemukan', 'Error', 500);
            }           
            
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }      
    }

    public function deactivateTelemarketing(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        
        try {
            
            $tele = DB::connection('sqlSrvFPI')
            ->table('telemarketing_status')
            ->where('id', $request->id)
            ->first();
            
            if ($tele) {
                $save = DB::connection('sqlSrvFPI')
                ->table('telemarketing_status')
                ->where('id', $request->id)
                ->update([
                    'active_flag' => 'N',
                    'tr_date' => now(),
                ]);
                DB::connection('sqlSrvFPI')->commit();
                
                return $this->sendResponse('Success Update', 'Successfully Deactivate Telemarketing');
            } else {
                return $this->sendError('Telemarketing tidak ditemukan', 'Error', 500);
            }           
            
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }      
    }

    public function activateTelemarketing(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();
        
        try {
            
            $tele = DB::connection('sqlSrvFPI')
            ->table('telemarketing_status')
            ->where('id', $request->id)
            ->first();
            
            if ($tele) {
                $save = DB::connection('sqlSrvFPI')
                ->table('telemarketing_status')
                ->where('id', $request->id)
                ->update([
                    'active_flag' => 'Y',
                    'tr_date' => now(),
                ]);
                DB::connection('sqlSrvFPI')->commit();
                
                return $this->sendResponse('Success Update', 'Successfully Activate Telemarketing');
            } else {
                return $this->sendError('Telemarketing tidak ditemukan', 'Error', 500);
            }           
            
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }      
    }

    private function handleFileAttachments($request, $main_id)
    {
        // 1. Ekstrak daftar nama file yang dipertahankan (untuk update)
        $kept_files = $request->input('old_attachments', []);
        if (!is_array($kept_files)) {
            $kept_files = [];
        }

        $max_item_num = 0;
        if (!empty($kept_files)) {
            $max_item_num = DB::connection('sqlSrvFPI')
                ->table('telemarketing_file')
                ->where('id_telemarketing_ref', $main_id)
                ->whereIn('doc_file', $kept_files)
                ->max('item_num');
        }

        // 2. Dapatkan file lama dan hapus yang tidak ada di kept_files
        $listAttch = DB::connection('sqlSrvFPI')
            ->table('telemarketing_file')
            ->where('id_telemarketing_ref', $main_id)
            ->get();
        
        $files_to_delete_db = [];
        foreach ($listAttch as $item) {
            if (!in_array($item->doc_file, $kept_files)) {
                $filePath = $item->doc_path . '/' . $item->doc_file;
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
                $files_to_delete_db[] = $item->id;
            }
        }

        if (!empty($files_to_delete_db)) {
            DB::connection('sqlSrvFPI')
                ->table('telemarketing_file')
                ->whereIn('id', $files_to_delete_db)
                ->delete();
        }

        // 3. Proses Insert File Baru
        $fileAttachments = [];
        $start_item_num = $max_item_num ? $max_item_num + 1 : 1;
        
        // Ambil ID terakhir untuk handle manual increment jika Identity OFF
        $lastId = DB::connection('sqlSrvFPI')->table('telemarketing_file')->max('id') ?? 0;

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $key => $file) {
                if ($file && $file->isValid()) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(40) . '.' . $extension;
                    $path = 'fpis/telemarketing-file';

                    Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                    
                    $fileAttachments[] = [
                        "nama_document" => $originalName,
                        "doc_path" => $path,
                        "item_num" => $start_item_num + $key,
                        "dt_created" => now(),
                        "created_by" => getAuth($request)->user_id2 ?? '',
                        "type_file" => $extension,
                        "doc_file" => $filename,
                        "id_telemarketing_ref" => $main_id,
                    ];
                }
            }
        }

        if ($fileAttachments) {
            DB::connection('sqlSrvFPI')->table('telemarketing_file')->insert($fileAttachments);
        }
    }

    public function getHistoryTelemarketing(Request $request)
    {
        $Telepon = $request->Telepon;

        $CustomerId = DB::connection('sqlSrvFPI')
                    ->table('Customer')
                    ->where('Telepon', $Telepon)
                    ->pluck('CustomerId');
        // dd($CustomerId);
        // $telemarketing_ref = DB::connection('sqlSrvFPI')
        //             ->table('telemarketing_status')
        //             ->whereIn('CustomerId', $CustomerId)
        //             ->pluck('id');
        // // dd($telemarketing_ref);
        // $telemarketing_file = DB::connection('sqlSrvFPI')
        //             ->table('telemarketing_file')
        //             ->whereIn('id_telemarketing_ref', $telemarketing_ref)
        //             ->get();
        // dd($telemarketing_file);

        $datas = DB::connection('sqlSrvFPI')
                    ->table('telemarketing_status')
                    ->selectraw("
                        telemarketing_status.id, 
                        telemarketing_status.CustomerId, 
                        telemarketing_status.tr_date, 
                        telemarketing_status.remark1, 
                        telemarketing_status.remark2, 
                        telemarketing_status.user_id, 
                        telemarketing_status.customer_respon, 
                        telemarketing_status.need_response, 
                        telemarketing_status.SalesId, 
                        telemarketing_status.active_flag, 
                        telemarketing_status.user_response,
                        isnull(telemarketing_status.id_site,'') as id_site,
                        isnull(site.site_name,'') as site_name,
                        isnull(telemarketing_status.id_cluster,'') as id_cluster,
                        isnull(site_cluster.cluster_name,'') as cluster_name,
                        isnull(telemarketing_status.id_house,'') as id_house, 
                        isnull(house_unit.house_name,'') as house_name,
                        appl_constant.str1, 
                        appl_constant.str2,
                        isnull(Sales.NamaSales,'') as NamaSales,
                        Customer.NamaCustomer,
                        isnull(Customer.Telepon,'') as Telepon
                    ")
                    ->leftjoin('appl_constant', function($join) {
                        $join->on('telemarketing_status.customer_respon', '=', 'appl_constant.str1')
                             ->where('appl_constant.key_code', '=', 'cust_resp');
                    })
                    ->leftjoin('Sales', function($join) {
                        $join->on('telemarketing_status.SalesId', '=', 'Sales.SalesId');
                    })
                    ->leftjoin('Customer', function($join) {
                        $join->on('telemarketing_status.CustomerId', '=', 'Customer.CustomerId');
                    })
                    ->leftJoin('site', 'site.id_site', '=', 'telemarketing_status.id_site')
                    ->leftJoin('site_cluster', function($join) {
                        $join->on('site_cluster.id_cluster', '=', 'telemarketing_status.id_cluster')
                            ->on('site_cluster.id_site', '=', 'telemarketing_status.id_site');
                    })
                    ->leftJoin('house_unit', function($join) {
                        $join->on('house_unit.id_house', '=', 'telemarketing_status.id_house')
                            ->on('house_unit.id_cluster', '=', 'telemarketing_status.id_cluster');
                    })
                    // ->whereRaw($sqlWhere)
                    ->whereIn('telemarketing_status.CustomerId', $CustomerId)
                    ->get();
                    
        $datas->map(function ($data) {
            // Ambil file berdasarkan ID dari data utama (id_telemarketing_ref)
            $data->Files = DB::connection('sqlSrvFPI')
                ->table('telemarketing_file')
                ->selectRaw("id, nama_document, doc_path, doc_file, type_file, dt_created, created_by")
                ->where('id_telemarketing_ref', $data->id) // $data->id adalah telemarketing_status.id
                ->orderBy("item_num")
                ->get()
                ->map(function ($file) {
                    $file->file_hash = $file->doc_file;
                    $file->doc_file = url("/storage/{$file->doc_path}/{$file->doc_file}");
                    return $file;
                });
            
            return $data;
        });
        return $this->sendResponse($datas, 'Successfully get tele data');
    }

    public function sendNotifWaTelemarketing($main_id)
    {
        try {
            $phone = DB::connection('sqlSrvFPI')
                    ->table('appl_constant')
                    ->where('key_code', 'CUST_CARE')
                    ->pluck('str2');

            $arrPhone = $phone->toArray();
            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Notifikasi Komplain Baru\n\n".
            "Telah diterima komplain baru dari customer dengan detail sebagai berikut:\n\n" .
            "No. Tiket : " . $main_id . "\n" .
            "Mohon untuk segera mengecek detail kendala dan melakukan follow-up melalui tautan berikut:\n\n" .
            "https://viva.kencana.org/";
            // dd($message);
            $url = 'https://notifapi.com/send_message';

            $results = [];

            foreach ($arrPhone as $getPhone) {
            $data = array(
            "phone_no" => $getPhone,
            "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
            "message"  => $message
            );

            $data_string = json_encode($data);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 360);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
            ));

            $res = curl_exec($ch);
            curl_close($ch);

            if($res == 'Success' || $res == 'success' ){
            $stat = 1;
            $msg = $res;
            } else {
            $stat = 0;
            $msg = $res;
            }
            
            $results[$getPhone] = [
                'stat' => $stat,
                'msg'  => $msg
            ];
            // $dataRes = ['stat' => $stat, 'msg' => $msg];
            }
            return response()->json([
                'status'  => 'success',
                'message' => 'Proses pengiriman selesai',
                'data'    => $results
            ], 200);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function sendNotifWaFeedbackTelemarketing($id){        
        try {
            // $phone = DB::connection('sqlSrvFPI')
            //         ->table('appl_constant')
            //         ->where('key_code', 'CUST_CARE')
            //         ->pluck('str2');

            // $arrPhone = $phone->toArray();
            // dd($id);
            $data = DB::connection('sqlSrvFPI')
                ->table('telemarketing_status')
                ->leftJoin('customer', 'telemarketing_status.customerid', '=', 'customer.customerid')
                ->select('telemarketing_status.*', 'customer.NamaCustomer', 'customer.telepon')
                ->where('telemarketing_status.id', $id) // Disarankan spesifik menyebutkan tabelnya untuk menghindari klausa ambigu
                ->first();

            // dd($data);
            $arrPhone = [$data->telepon]; // Menggunakan nomor telepon dari data yang diambil

            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Halo Bpk/Ibu " . $data->NamaCustomer . "\n\n".
            "Terima kasih telah menghubungi kami. Laporan kendala/keluhan Anda dengan No. Tiket : " . $data->id . " saat ini telah direspon.\n\n" .
            "Silakan cek di https://fasindo-customer-care.kencana.org untuk melihat respon lengkapnya.\n\n" .
            "Terima kasih atas kesabaran dan kerja samanya.";

            /*Halo Bpk/Ibu [Nama Customer],

            Terima kasih telah menghubungi kami. Laporan kendala/keluhan Anda dengan No. Tiket: [Nomor Tiket] saat ini telah direspon.

            Silakan cek di fasindo-customer-care.kencana.org untuk melihat respon lengkapnya.

            Terima kasih atas kesabaran dan kerja samanya. */
            // dd($message);
            
            $url = 'https://notifapi.com/send_message';

            $results = [];

            foreach ($arrPhone as $getPhone) {
            $data = array(
            "phone_no" => $getPhone,
            "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
            "message"  => $message
            );

            $data_string = json_encode($data);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 360);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
            ));

            $res = curl_exec($ch);
            curl_close($ch);

            if($res == 'Success' || $res == 'success' ){
            $stat = 1;
            $msg = $res;
            } else {
            $stat = 0;
            $msg = $res;
            }
            
            $results[$getPhone] = [
                'stat' => $stat,
                'msg'  => $msg
            ];
            // $dataRes = ['stat' => $stat, 'msg' => $msg];
            }
            return response()->json([
                'status'  => 'success',
                'message' => 'Proses pengiriman selesai',
                'data'    => $results
            ], 200);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}

