<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KasbonAprvController extends Controller
{
    //
    public function getKasbon(Request $request){
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $vendor_id = $request->vendor_id;
            $aprv = $request->aprv;
            $statusOP = $request->statusOP;

            $start_date = $request->start_date;
            if(!$start_date)
            {
                $start_date = '';
            }

            $end_date = $request->end_date;
            if(!$end_date)
            {
                $end_date = '';
            }

            if ($start_date)
            {
                if ($end_date)
                {
                    $sqlWhere .= " and kb_hdr.dt_created BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and kb_hdr.dt_created BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
                }
            }


            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($vendor_id))
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.vendor_id = ". "'" . $vendor_id . "'";
            }

            if ($aprv=='All')
            {
                $sqlWhere = $sqlWhere;
            }
            if ($aprv=='Approve 1')
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.dt_aprv != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Approve 2')
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.dt_aprv2 != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Not Approve')
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.dt_aprv = '1900-01-01 00:00:00.000' and kb_hdr.dt_aprv2 = '1900-01-01 00:00:00.000' and kb_hdr.dt_rjc = '1900-01-01 00:00:00.000' and kb_hdr.dt_rjc2 = '1900-01-01 00:00:00.000'";
            }
            // if ($aprv=='Rejected')
            // {
            //     $sqlWhere = $sqlWhere . "and kb_hdr.dt_rjc != '1900-01-01 00:00:00.000' or kb_hdr.dt_rjc2 != '1900-01-01 00:00:00.000'";
            // }

            if ($statusOP=='Open')
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.stat = 'O'";
            }
            if ($statusOP=='Close')
            {
                $sqlWhere = $sqlWhere . "and kb_hdr.stat = 'C'";
            }

            $datas = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->selectRaw("
                                    kb_hdr.office_id,
                                    trim(branch_office.office) as office, 
                                    trim(branch_office.address) as address, 
                                    trim(branch_office.city) as city, 
                                    trim(kb_hdr.tr_id) as tr_id,
                                    kb_hdr.tr_type,
                                    kb_hdr.kb_num,
                                    trim(kb_hdr.vendor_id) as vendor_id,
                                    kb_hdr.idemployee,
                                    kb_hdr.dt_kb,
                                    kb_hdr.kb_amt,
                                    kb_hdr.remark,
                                    kb_hdr.stat,
                                    kb_hdr.dt_created,
                                    kb_hdr.dt_modified,
                                    kb_hdr.user_id,
                                    trim(kb_hdr.tr_id_ref) as tr_id_ref,
                                    kb_hdr.dt_aprv1,
                                    kb_hdr.user_aprv1,
                                    kb_hdr.dt_aprv2,
                                    kb_hdr.user_aprv2,
                                    trim(kb_hdr.ac_num) as ac_num,
                                    trim(vendor.vendor_name) as vendor_name
                                ")
                                ->leftjoin('branch_office', 'kb_hdr.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('vendor', 'kb_hdr.vendor_id', '=', 'vendor.vendor_id')
                                ->whereRaw($sqlWhere)
                                ->orderBy('kb_hdr.dt_created', 'desc')
                                ->get();

            return $this->sendResponse($datas, 'Successfully get kasbon data');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        } 
    }

    public function getKasbonDetail(Request $request){
        $datas = DB::connection("sqlSrvFPI")
                ->table('kb_dtl')
                ->selectRaw("
                    ROW_NUMBER() OVER (ORDER BY kb_dtl.tr_id) AS no,
                    trim(kb_dtl.office_id) as office_id,
                    trim(branch_office.office) as office, 
                    trim(branch_office.address) as address, 
                    trim(branch_office.city) as city, 
                    trim(kb_dtl.tr_id) as tr_id,
                    trim(kb_dtl.article_id) as article_id, 
                    trim(article.description) as description,                
                    kb_dtl.seq_num,
                    kb_dtl.qty,
                    kb_dtl.price,
                    kb_dtl.amt,
                    kb_dtl.stat,
                    trim(kb_dtl.remark) as remark,
                    kb_dtl.dt_created,
                    kb_dtl.dt_modified,
                    trim(kb_dtl.user_id) as user_id,
                    trim(kb_dtl.ac_num) as ac_num
                ")
                ->leftJoin('branch_office', 'kb_dtl.office_id', '=', 'branch_office.office_id')
                ->leftJoin('article', 'kb_dtl.article_id', '=', 'article.article_id')
                ->where('kb_dtl.tr_id', $request->tr_id)
                ->get();

        return $this->sendResponse($datas, 'Successfully get PR Detail');
    }

    public function aprv1Kasbon(Request $request){
        $order = DB::connection('sqlSrvFPI')
                ->table('kb_hdr')
                ->where('tr_id', $request->tr_id)
                ->first();
            // $dept_id = trim($order->dept_id);
            // dd($dept_id);

        if ($order) {

            if ($order->dt_aprv1 == '1900-01-01 00:00:00.000') {
                $save = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', $request->tr_id)
                        ->update([
                            'dt_aprv1' => now(),
                            'dt_modified' => now(),
                            'user_aprv1' => getAuth($request)->user_id2
                        ]);
                // $whatsapp = $this->sendNotifPurchase('BON',$request->tr_id,$dept_id);
                return $this->sendResponse('Success Approved', 'Successfully saved');                
            } else {
                return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
            }   
        } else {
            return $this->sendError('No Order tidak ditemukan', 'Error', 500);
        }    
    }

    public function aprv2Kasbon(Request $request){
        $order = DB::connection('sqlSrvFPI')
                    ->table('kb_hdr')
                    ->where('tr_id', $request->tr_id)
                    ->first();

        if ($order) {
            if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                $save = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', $request->tr_id)
                        ->update([
                            'dt_aprv2' => now(),
                            'dt_modified' => now(),
                            'user_aprv2' => getAuth($request)->user_id2
                        ]);
                return $this->sendResponse('Success Approved', 'Successfully saved');

            } else {
                return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
            }
        } else {
            return $this->sendError('No Order tidak ditemukan', 'Error', 500);
        }   
    }

    public function sendNotifPurchase($type,$po_id,$dept_id)
    {
        try {
            
            if($type=='BON'){
                $type_name = "*_Kasbon_*";
            }

    /* ============================================= get id emp ============================================= */
            // $getEmp = DB::table('sec_env_conf')
            //         ->selectRaw('user_id')
            //         ->whereRaw("entity_id = 'KRI' AND appl_id = 'PURCHASING' AND var_id = 'APPROVAL2'")
            //         ->get();
            $getEmp = ''; 
            if($type=='BON'){
                $getEmp = DB::connection('sqlSrvFPI')
                    ->table('department')
                    ->selectRaw('nik_pr2')
                    ->whereRaw("dept_id = '" . $dept_id . "'")
                    ->get(); 
                $getEmp = $getEmp[0]->nik_pr2;
            }
    /* ============================================= get phone ============================================= */
            // foreach ($getEmp as $data1) {
            //     $idemployee = $data1->user_id;

            //     if (!empty($idemployee)){
            //         $sqlWhere = "idemployee = '" . $idemployee . "'";
            //     }
            if($type=='BON'){
                $getPhone = DB::connection('sqlSrvFPI')
                            ->table('department')
                            ->selectRaw('pr2')
                            // ->whereRaw($sqlWhere)
                            ->whereRaw("dept_id = '" . $dept_id . "'")
                            ->get(); 
                // dd($getPhone);
                $getPhone = $getPhone[0]->pr2;
            }
    /* ============================================= get from reset pass ============================================= */
            // if($type=='PR'){
                // $getLink = DB::connection('sqlsrv')
                //         ->table('sec_reset_password')
                //         ->selectRaw('link')
                //         // ->whereRaw($sqlWhere)
                //         ->whereRaw("user_id2 = '" . $getEmp . "'")
                //         ->get(); 
                // $getLink = $getLink[0]->link;
                // $getLink = str_replace(['{', '}'], '', $getLink);
                //         ->selectRaw('link')
            // }
            
    /* ============================================= send WA ============================================= */
                // foreach ($getPhone as $data2) {
                //     $phone = $data2->phone;
                // }

                // $message = '*FASINDO PROPERTI INDONESIA*'.'\n\n'.'Silahkan lakukan Approval 2 untuk '.$type_name.' Nomor : ' . $po_id . '\n' . 'Silahkan login pada https://viva.kencana.org/approve untuk Approval 2. Terima kasih '; 
                // /https://viva.kencana.org/approve/09210216/VIVA23081509mardian4673958770904091
                $getLink = "FDPI" . now()->format('ymdH') . rand(10000, 99999);
                DB::connection('sqlsrv')
                ->table('sec_reset_password')
                ->insert([
                    'user_id2' => $getEmp ? $getEmp : '',
                    'link' => $getLink,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
                "Silahkan lakukan Approval 2 untuk {$type_name} Nomor: {$po_id}\n" .
                "Silahkan login pada https://viva.kencana.org/approve/{$getEmp}/{$getLink} untuk Approval 2.\nTerima kasih.";
                // dd($message);
                $url = 'https://notifapi.com/send_message';

                $data = array(
                "phone_no" => $getPhone,
                "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
                "message"  => $message
                );

                $data_string = json_encode($data,1);

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

                $dataRes = ['stat' => $stat, 'msg' => $msg];

            // }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getKasbonVendorAprv(Request $request)
    {
        $sqlWhere = '1=1';
        $vendor_id = $request->vendor_id;
        $active_flag = $request->active_flag;

        
        if(empty($active_flag)){
            // $sqlWhere = $sqlWhere . " and v.active_flag = 'Y'";
        }else{
            $sqlWhere = $sqlWhere . " and v.active_flag = " . "'" . $active_flag . "'";
        }
        if(empty($vendor_id)){
            
        }else{
            $sqlWhere = $sqlWhere . " and v.vendor_id = " . "'" . $vendor_id . "'";
        }

        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('vendor as v')
                ->selectRaw("
                        DISTINCT
                        TRIM(v.vendor_id) as vendor_id, 
                        TRIM(v.vendor_name) as vendor_name
                    ")
                ->join('kb_hdr as k', 'k.vendor_id', '=', 'v.vendor_id')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($result, 'Successfully get Vendor Only Those Who Have Debt');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getKasbonMobile(Request $request){
        try {
            $sqlWhere = "kb_hdr.office_id = 1 AND kb_hdr.stat = 'O' AND kb_hdr.tr_type = 'EX'";

            $cekAprv1 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','FDPI')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALBON1')
                                ->where('active_flag','Y')
                                ->value('var_value');

            $cekAprv2 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','FDPI')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALBON2')
                                ->where('active_flag','Y')
                                ->value('var_value');

            $cekAprv3 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','FDPI')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALBON3')
                                ->where('active_flag','Y')
                                ->value('var_value');
            
            $cekAprv4 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','FDPI')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALBON4')
                                ->where('active_flag','Y')
                                ->value('var_value');

            // dd($cekAprv1, $cekAprv2, $cekAprv3);

            // if($cekAprv1=='Y' && $cekAprv2=='Y' && $cekAprv3=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(kb_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(kb_hdr.dt_aprv3, 'yyyy-MM-dd') = '1900-01-01' )"; 
            
            // }elseif($cekAprv1=='Y' && $cekAprv2=='Y'){
            //    $sqlWhere =  $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(kb_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01')";
            // }elseif($cekAprv1=='Y' && $cekAprv3=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(kb_hdr.dt_aprv3, 'yyyy-MM-dd') = '1900-01-01')";
            // }elseif($cekAprv2=='Y' && $cekAprv3=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(kb_hdr.dt_aprv3, 'yyyy-MM-dd') = '1900-01-01')";

            // }elseif($cekAprv1=='Y'){
            //    $sqlWhere =  $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' )";
            // }elseif($cekAprv2=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' )";
            // }elseif($cekAprv3=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv3, 'yyyy-MM-dd') = '1900-01-01' )";
            // }elseif($cekAprv4=='Y'){
            //     $sqlWhere = $sqlWhere." AND (FORMAT(kb_hdr.dt_aprv4, 'yyyy-MM-dd') = '1900-01-01' )";
            // }
            $approvals = [
                1 => $cekAprv1,
                2 => $cekAprv2,
                3 => $cekAprv3,
                4 => $cekAprv4,
            ];

            $approvalConditions = [];
            foreach ($approvals as $num => $status) {
                if ($status === 'Y') {
                    $approvalConditions[] = "FORMAT(kb_hdr.dt_aprv{$num}, 'yyyy-MM-dd') = '1900-01-01'";
                }
            }

            if (!empty($approvalConditions)) {
                $sqlWhere .= ' AND (' . implode(' OR ', $approvalConditions) . ')';
            }

            // dd($sqlWhere);
            $datas = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->selectRaw("
                                    kb_hdr.office_id,
                                    trim(branch_office.office) as office, 
                                    trim(branch_office.address) as address, 
                                    trim(branch_office.city) as city, 
                                    trim(kb_hdr.tr_id) as tr_id,
                                    kb_hdr.tr_type,
                                    CASE
                                        WHEN kb_hdr.tr_type = 'EX' THEN 'EKSTERNAL'
                                        WHEN kb_hdr.tr_type = 'IN' THEN 'INTERNAL'
                                        ELSE '-'
                                    END as w_tr_type,
                                    kb_hdr.kb_num,
                                    trim(kb_hdr.vendor_id) as vendor_id,
                                    kb_hdr.idemployee,
                                    kb_hdr.dt_kb,
                                    kb_hdr.kb_amt,
                                    spk_hdr.amt,
                                    spk_hdr.spk_type,
                                    appl_constant.str2 as w_spk_type,
                                    kb_hdr.remark,
                                    kb_hdr.stat,
                                    kb_hdr.dt_created,
                                    kb_hdr.dt_modified,
                                    kb_hdr.user_id,
                                    trim(kb_hdr.tr_id_ref) as tr_id_ref,
                                    kb_hdr.dt_aprv1,
                                    kb_hdr.user_aprv1,
                                    kb_hdr.dt_aprv2,
                                    kb_hdr.user_aprv2,
                                    kb_hdr.dt_aprv3,
                                    kb_hdr.user_aprv3,
                                    kb_hdr.dt_aprv4,
                                    kb_hdr.user_aprv4,
                                    trim(kb_hdr.ac_num) as ac_num,
                                    trim(hrded.name) as employee_name,
                                    trim(vendor.vendor_name) as vendor_name,
                                    site.site_name,
                                    site_cluster.cluster_name,
                                    house_unit.house_name,
                                    CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                                    CASE 
                                        WHEN FORMAT(kb_hdr.dt_aprv1, 'yyyy-MM-dd') <> '1900-01-01'
                                        THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                        ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND var_id = 'APPROVALBON1'),'-'))
                                    END AS w_aprv1_by,
                                    
                                    CASE 
                                        WHEN FORMAT(kb_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                        THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                        ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND var_id = 'APPROVALBON2'),'-'))
                                    END AS w_aprv2_by,

                                    CASE 
                                        WHEN FORMAT(kb_hdr.dt_aprv3, 'yyyy-MM-dd') <> '1900-01-01'
                                        THEN CONCAT('Disetujui oleh ', hrdea3.name)
                                        ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND  var_id = 'APPROVALBON3'),'-'))
                                    END AS w_aprv3_by,

                                    CASE 
                                        WHEN FORMAT(kb_hdr.dt_aprv4, 'yyyy-MM-dd') <> '1900-01-01'
                                        THEN CONCAT('Disetujui oleh ', hrdea4.name)
                                        ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND  var_id = 'APPROVALBON4'),'-'))
                                    END AS w_aprv4_by,
                                    (select TOP 1 vvcl.credit_limit from view_vendor_credit_limit_spk vvcl where vvcl.id_spk = kb_hdr.tr_id_ref AND vvcl.status_closing = 'N' ORDER BY vvcl.com_id ASC) AS credit_limit
                                ")
                                ->leftjoin('branch_office', 'kb_hdr.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('vendor', 'kb_hdr.vendor_id', '=', 'vendor.vendor_id')
                                ->leftjoin('spk_hdr', 'kb_hdr.tr_id_ref', '=', 'spk_hdr.id_spk')
                                ->leftjoin('site', 'site.id_site', '=', 'spk_hdr.id_site')
                                ->leftjoin('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
                                ->leftjoin('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
                                ->leftjoin('appl_constant', function ($join) {
                                            $join->on('appl_constant.str1', '=', 'spk_hdr.spk_type')
                                                ->where('appl_constant.key_code', '=', 'SPK_TYPE');
                                        })
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrded'), 'hrded.idemployee', '=', 'kb_hdr.idemployee')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'kb_hdr.user_id')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'kb_hdr.user_aprv1')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'kb_hdr.user_aprv2')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea3'), 'hrdea3.idemployee', '=', 'kb_hdr.user_aprv3')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea4'), 'hrdea4.idemployee', '=', 'kb_hdr.user_aprv4')
                                ->whereRaw($sqlWhere)
                                ->orderBy('kb_hdr.dt_created', 'asc')
                                ->get();

            return $this->sendResponse($datas, 'Successfully get kasbon data for mobile');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        } 
    }

    public function aprvKasbon(Request $request)
    {
        try {
            $tr_id = $request->tr_id;
            $type_aprv = $request->type_aprv;

            if (empty($tr_id)) {
                return $this->sendError("tr_id Kosong", 'Error', 500);
            }
            if (empty($type_aprv)) {
                return $this->sendError("Type Aprv Kosong", 'Error', 500);
            }

            if($type_aprv == 'approve1'){
                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv1, 'yyyy-MM-dd') as dt_aprv1")
                                ->value('dt_aprv1');
                
                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 1", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv1' => now(),
                            'user_aprv1' => getAuth($request)->user_id2
                        ]);
                    
                    if($response){
                        $user = DB::connection('sqlSrvFPI')
                                        ->table('spk_aprv_env')
                                        ->selectRaw('aprv_2, no_hp_2, nama_employee_2')
                                        ->where('doc_type', '=', 'KB')
                                        ->where('active_flag', '=', 'Y')
                                        ->get();
                        
                        if($user){
                            $countSuccess = 0;
                            $countError = 0;
                            $totalJumlah = 0;
                            foreach($user as $key => $value){
                                $res = $this->sendNotifApproval($tr_id, $value->aprv_2, $value->no_hp_2);
    
                                if($res['stat'] == 1){
                                    $countSuccess++;
                                }else{
                                    $countError++;
                                }
                                $totalJumlah++;
                            }
                            
                            if($countError == 0 && $countSuccess == $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 1 Kasbon  & Send All Notifikasi'
                                ], 200);
                            }else if($countError != 0 && $countSuccess != $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 1 Kasbon & Send Notifikasi'
                                ], 200);
                            }else{
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 1 Kasbon'
                                ], 200);
                            }
                        }
                    }
                }
            }else if($type_aprv == 'approve2'){
                $cekAprv2 = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv2, 'yyyy-MM-dd') as dt_aprv2")
                                ->value('dt_aprv2');
                
                if ($cekAprv2 !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 2", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv2' => now(),
                            'user_aprv2' => getAuth($request)->user_id2
                        ]);
                    
                    if($response){
                        $user = DB::connection('sqlSrvFPI')
                                        ->table('spk_aprv_env')
                                        ->selectRaw('aprv_3, no_hp_3, nama_employee_3')
                                        ->where('doc_type', '=', 'KB')
                                        ->where('active_flag', '=', 'Y')
                                        ->get();
                        
                        if($user){
                            $countSuccess = 0;
                            $countError = 0;
                            $totalJumlah = 0;
                            foreach($user as $key => $value){
                                $res = $this->sendNotifApproval($tr_id, $value->aprv_3, $value->no_hp_3);
    
                                if($res['stat'] == 1){
                                    $countSuccess++;
                                }else{
                                    $countError++;
                                }
                                $totalJumlah++;
                            }
                            
                            if($countError == 0 && $countSuccess == $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 2 Kasbon  & Send All Notifikasi'
                                ], 200);
                            }else if($countError != 0 && $countSuccess != $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 2 Kasbon & Send Notifikasi'
                                ], 200);
                            }else{
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 2 Kasbon'
                                ], 200);
                            }
                        }
                    }
                }
            }else if($type_aprv == 'approve3'){
                $cekAprv3 = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv3, 'yyyy-MM-dd') as dt_aprv3")
                                ->value('dt_aprv3');
                
                if ($cekAprv3 !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 3", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv3' => now(),
                            'user_aprv3' => getAuth($request)->user_id2
                        ]);
                    
                    if($response){
                        $user = DB::connection('sqlSrvFPI')
                                        ->table('spk_aprv_env')
                                        ->selectRaw('aprv_4, no_hp_4, nama_employee_4')
                                        ->where('doc_type', '=', 'KB')
                                        ->where('active_flag', '=', 'Y')
                                        ->get();
                        
                        if($user){
                            $countSuccess = 0;
                            $countError = 0;
                            $totalJumlah = 0;
                            foreach($user as $key => $value){
                                $res = $this->sendNotifApproval($tr_id, $value->aprv_3, $value->no_hp_3);
    
                                if($res['stat'] == 1){
                                    $countSuccess++;
                                }else{
                                    $countError++;
                                }
                                $totalJumlah++;
                            }
                            
                            if($countError == 0 && $countSuccess == $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 3 Kasbon  & Send All Notifikasi'
                                ], 200);
                            }else if($countError != 0 && $countSuccess != $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 3 Kasbon & Send Notifikasi'
                                ], 200);
                            }else{
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully Approve 3 Kasbon'
                                ], 200);
                            }
                        }
                    }
                }
            }else if($type_aprv == 'approve4'){
                $cekAprv4 = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv4, 'yyyy-MM-dd') as dt_aprv4")
                                ->value('dt_aprv4');
                
                if ($cekAprv4 !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 4", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv4' => now(),
                            'user_aprv4' => getAuth($request)->user_id2
                        ]);
                }
            }

            return $this->sendResponse($response, 'Successfully Approve 3 Kasbon');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function rjctKasbon(Request $request)
    {
        try {
            $tr_id = $request->tr_id;
            $type_aprv = $request->type_aprv;

            if($type_aprv == 'approve1'){
                $cekStatus = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("stat as stat")
                                ->value('stat');

                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv1, 'yyyy-MM-dd') as dt_reject")
                                ->value('dt_reject');

                if($cekStatus == 'O' && $cekAprv == '1900-01-01'){
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv1' => now(),
                            'stat' => "X",
                            'user_aprv1' => getAuth($request)->user_id2
                        ]);
                }elseif($cekStatus == 'X' && $cekAprv == '1900-01-01'){
                    return $this->sendError('Terjadi kesalahan sistem, hubungi admin IT', 'Error', 500);
                }elseif($cekStatus == 'O' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di approve 1', 'Error', 500);
                }elseif($cekStatus == 'X' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di tolak, kok masih berharap (-_-")', 'Error', 500);
                }
                return $this->sendResponse($response, 'Successfully Reject 1 Kasbon');
            }elseif($type_aprv == 'approve2'){
                $cekStatus = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("stat as stat")
                                ->value('stat');

                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv2, 'yyyy-MM-dd') as dt_reject")
                                ->value('dt_reject');

                if($cekStatus == 'O' && $cekAprv == '1900-01-01'){
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv2' => now(),
                            'stat' => "X",
                            'user_aprv2' => getAuth($request)->user_id2
                        ]);
                }elseif($cekStatus == 'X' && $cekAprv == '1900-01-01'){
                    return $this->sendError('Sudah di reject', 'Error', 500);
                }elseif($cekStatus == 'O' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di reject 1', 'Error', 500);
                }elseif($cekStatus == 'X' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di tolak, kok masih berharap (-_-")', 'Error', 500);
                }
                return $this->sendResponse($response, 'Successfully Reject 2 Kasbon');
            }elseif($type_aprv == 'approve3'){
                $cekStatus = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("stat as stat")
                                ->value('stat');

                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv3, 'yyyy-MM-dd') as dt_reject")
                                ->value('dt_reject');

                if($cekStatus == 'O' && $cekAprv == '1900-01-01'){
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv3' => now(),
                            'stat' => "X",
                            'user_aprv3' => getAuth($request)->user_id2
                        ]);
                }elseif($cekStatus == 'X' && $cekAprv == '1900-01-01'){
                    return $this->sendError('Sudah di reject', 'Error', 500);
                }elseif($cekStatus == 'O' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di reject 1', 'Error', 500);
                }elseif($cekStatus == 'X' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di tolak, kok masih berharap (-_-")', 'Error', 500);
                }
                return $this->sendResponse($response, 'Successfully Reject 3 Kasbon');
            }elseif($type_aprv == 'approve4'){
                $cekStatus = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("stat as stat")
                                ->value('stat');

                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('kb_hdr')
                                ->where('tr_id', '=', $tr_id)
                                ->selectRaw("FORMAT(dt_aprv4, 'yyyy-MM-dd') as dt_reject")
                                ->value('dt_reject');

                if($cekStatus == 'O' && $cekAprv == '1900-01-01'){
                    $response = DB::connection('sqlSrvFPI')
                        ->table('kb_hdr')
                        ->where('tr_id', '=', $tr_id)
                        ->update([
                            'dt_aprv4' => now(),
                            'stat' => "X",
                            'user_aprv4' => getAuth($request)->user_id2
                        ]);
                }elseif($cekStatus == 'X' && $cekAprv == '1900-01-01'){
                    return $this->sendError('Sudah di reject', 'Error', 500);
                }elseif($cekStatus == 'O' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di reject 1', 'Error', 500);
                }elseif($cekStatus == 'X' && $cekAprv != '1900-01-01'){
                    return $this->sendError('Sudah di tolak, kok masih berharap (-_-")', 'Error', 500);
                }
                return $this->sendResponse($response, 'Successfully Reject 4 Kasbon');
            }
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    private function sendNotifApproval($spkId, $getEmp, $getPhone)
    {
        try {
            $getLink = "FDPI" . now()->format('ymdH') . rand(10000, 99999);

            DB::connection('sqlsrv')
            ->table('sec_reset_password')
            ->insert([
                'user_id2' => $getEmp ? $getEmp : '',
                'link' => $getLink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            // "Silahkan lakukan Approval 2 untuk Kasbon ID: {$spkId}\n" .
            // "Silahkan login pada https://viva.kencana.org/approve/{$getEmp}/{$getLink} untuk Approval 2.\nTerima kasih.";
            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Silahkan lakukan Approval untuk Kasbon ID: {$spkId}\n";
            
            $url = 'https://notifapi.com/send_message';
            
            $data = array(
            "phone_no" => $getPhone,
            "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
            "message"  => $message
            );

            $data_string = json_encode($data,1);

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

            $dataRes = ['stat' => $stat, 'msg' => $msg];   
            
            return $dataRes;

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
