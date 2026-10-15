<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ApprovalSPKController extends Controller
{
    public function getListSPK(Request $request)
    {
        try {
            $id_site = $request->id_site ?? '';
            $id_cluster = $request->id_cluster ?? '';
            $id_house = $request->id_house ?? '';
            $status = $request->status ?? '';
            $aprv = $request->aprv ?? '';

            
            $sqlWhere = "1=1";
            
            if ($id_site) {
                
                $sqlWhere .= " and spk_hdr.id_site = '$id_site'";
            }
            if ($id_cluster) {

                $sqlWhere .= " and spk_hdr.id_cluster = '$id_cluster'";
            }
            if ($id_house) {
                
                $sqlWhere .= " and spk_hdr.id_house_unit = '$id_house'";
            }
            if ($status) {
                $sqlWhere .= " and spk_hdr.status = '$status'";
            }
            if ($aprv) {
                if($aprv == 'AP1'){
                    $sqlWhere .= " and spk_hdr.aprv1_by != ''";
                } else if($aprv == 'AP2'){
                    $sqlWhere .= " and spk_hdr.aprv2_by != ''";
                } else if($aprv == 'N'){
                    $sqlWhere .= " and spk_hdr.aprv1_by = '' or spk_hdr.aprv2_by = ''";
                } else if($aprv == 'A'){
                    // $sqlWhere .= " and spk_hdr.aprv1_by != '' or spk_hdr.aprv2_by != ''";
                } 
            }
            
            $response = DB::connection('sqlSrvBCKI')
                ->table('spk_hdr')
                ->selectRaw("
                                spk_hdr.*,
                                site.site_name,
                                site.remark,
                                site_cluster.cluster_name,
                                site_cluster.remark as remark_cluster,
                                house_unit.house_name,
                                house_type.common_name,
                                house_type.sbk_name,
                                house_type.category,
                                TRIM(vendor.vendor_name) as nama_contractor,
                                TRIM(vendor.contact_name) as contact_name,
                                TRIM(vendor.address1) as alamat_contractor,
                                employee.name as nama_employee,
                                employee.jabatan as jabatan_employee,
                                employee.address1 as alamat_employee,
                                spb_hdr.id_spb,
                                qc_trans_hdr.id_qc_form,
                                qc_trans_hdr.qc_trans_id,
                                (SELECT SUM(spk_article.qty * spk_article.harga_satuan) FROM spk_article WHERE spk_article.id_spk = spk_hdr.id_spk) as harga,
                                CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                    ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPK1'),'-'))
                                END AS w_aprv1_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                    ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPK2'),'-'))
                                END AS w_aprv2_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_reject, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Ditolak oleh ', hrder1.name)
                                    ELSE '-' 
                                END AS w_reject1_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_reject2, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Ditolak 2 oleh ', hrder2.name)
                                    ELSE '-' 
                                END AS w_reject2_by
                            ")
                    ->join('site', 'site.id_site', '=', 'spk_hdr.id_site')
                    ->join('spb_hdr', 'spb_hdr.id_spb', '=', 'spk_hdr.id_spb')
                    ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
                    ->join('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
                    ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
                    ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                    ->join(DB::raw("(
                        SELECT *, ROW_NUMBER() OVER (PARTITION BY id_house ORDER BY qc_trans_id DESC) as rownum
                        FROM qc_trans_hdr
                    ) as qc_trans_hdr"), function ($join) {
                        $join->on('spk_hdr.id_house_unit', '=', 'qc_trans_hdr.id_house')
                            ->where('qc_trans_hdr.rownum', '=', 1);
                    })
                    ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spk_hdr.pihak_1') // ini join ke database HRD_OL
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'spk_hdr.create_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'spk_hdr.aprv1_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'spk_hdr.aprv2_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder1'), 'hrder1.idemployee', '=', 'spk_hdr.reject_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder2'), 'hrder2.idemployee', '=', 'spk_hdr.reject2_by')
                ->whereRaw($sqlWhere)
                ->orderBy('spk_hdr.dt_created', 'asc')
                ->get();
                
            foreach ($response as $item) {
                $resArticle = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'A')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article = $resArticle;

                $resArticleIn = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AI')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_in = $resArticleIn;

                // Ambil hanya field 'description' dari hasil query
                $descriptionsIn = $resArticleIn->pluck('description')->toArray();

                // Gabungkan deskripsi menjadi satu string
                $stringArticleIn = implode(', ', $descriptionsIn);

                // Tambahkan ke properti item dengan titik di akhir
                $item->article_in_desc = 'Pekerjaan sudah termasuk ' . $stringArticleIn . '.';

                $resArticleEx = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AE')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_ex = $resArticleEx;

                $descriptionsEx = $resArticleEx->pluck('description')->toArray();

                $stringArticleEx = implode(', ', $descriptionsEx);

                $item->article_ex_desc = 'Pekerjaan tidak termasuk ' . $stringArticleEx . '.';

                $resRemarkQC = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_QC')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                if (isset($resRemarkQC)) {
                    $item->remark_qc = $resRemarkQC->remarks;
                }

                $resRemarkLain = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_L')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                $item->remarks = $resRemarkLain->remarks;
            }

            return $this->sendResponse($response, 'Successfully Get SPK');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function approvalSPK(Request $request)
    {
        try {
            $id_spk = $request->id_spk;
            $type_aprv = $request->type_aprv;
            $type_spk = $request->type_spk;

            if($type_aprv == 'approve1'){
                $response = DB::connection('sqlSrvBCKI')
                    ->table('spk_hdr')
                    ->where('id_spk', '=', $id_spk)
                    ->update([
                        'dt_aprv1' => now(),
                        'aprv1_by' => getAuth($request)->user_id2
                    ]);
                if($response){
                    $user = DB::connection('sqlSrvBCKI')
                    ->table('spk_aprv_env')
                    ->selectRaw('aprv_2, no_hp_2, nama_employee_2')
                    ->where('doc_type', '=', 'SPK')
                    ->where('spk_type', '=', $type_spk)
                    ->where('active_flag', '=', 'Y')
                    ->get();

                    if($user){
                        $countSuccess = 0;
                        $countError = 0;
                        $totalJumlah = 0;
                        foreach($user as $key => $value){
                            $res = $this->sendNotifSPKApproval($id_spk, $value->aprv_2, $value->no_hp_2);

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
                                'message' => 'Successfully saved Approve & Send All Notifikasi'
                            ], 200);
                        }else if($countError != 0 && $countSuccess != $totalJumlah){
                            return response()->json([
                                'status' => true,
                                'message' => 'Successfully saved Approve & Send Notifikasi'
                            ], 200);
                        }else{
                            return response()->json([
                                'status' => true,
                                'message' => 'Successfully saved Approve'
                            ], 200);
                        }
                    }
                }
            }else if($type_aprv == 'approve2'){
                $response = DB::connection('sqlSrvBCKI')
                    ->table('spk_hdr')
                    ->where('id_spk', '=', $id_spk)
                    ->update([
                        'dt_aprv2' => now(),
                        'aprv2_by' => getAuth($request)->user_id2
                    ]);
            }

            return $this->sendResponse($response, 'Successfully Update Approval SPK');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function rejectApprove(Request $request)
    {
        try {
            $id_spk = $request->id_spk;
            $type_aprv = $request->type_aprv;

            if($type_aprv == 'approve1'){
                $response = DB::connection('sqlSrvBCKI')
                    ->table('spk_hdr')
                    ->where('id_spk', '=', $id_spk)
                    ->update([
                        'dt_reject' => now(),
                        'status' => "X",
                        'reject_by' => getAuth($request)->user_id2
                    ]);
            }else if($type_aprv == 'approve2'){
                $response = DB::connection('sqlSrvBCKI')
                    ->table('spk_hdr')
                    ->where('id_spk', '=', $id_spk)
                    ->update([
                        'dt_reject2' => now(),
                        'status' => "X",
                        'reject2_by' => getAuth($request)->user_id2
                    ]);
            }

            return $this->sendResponse($response, 'Successfully Update Reject SPK');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }
    
    private function sendNotifSPKApproval($spkId, $getEmp, $getPhone)
    {
        try {
            $getLink = "bcki" . now()->format('ymdH') . rand(10000, 99999);

            DB::connection('sqlsrv')
            ->table('sec_reset_password')
            ->insert([
                'user_id2' => $getEmp ? $getEmp : '',
                'link' => $getLink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Silahkan lakukan Approval untuk SPK ID: {$spkId}\n";
            // dd($message);
            $url = 'https://notifapi.com/send_message';
            // dd($message);
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

    public function getStatusSPK(Request $request)
    {
        try {
            $id_site = $request->id_site ?? '';
            $id_cluster = $request->id_cluster ?? '';
            $id_house = $request->id_house ?? '';
            $status = $request->status ?? '';
            $aprv = $request->aprv ?? '';

            
            $sqlWhere = "1=1";
            
            if ($id_site) {
                
                $sqlWhere .= " and spk_hdr.id_site = '$id_site'";
            }
            if ($id_cluster) {

                $sqlWhere .= " and spk_hdr.id_cluster = '$id_cluster'";
            }
            if ($id_house) {
                
                $sqlWhere .= " and spk_hdr.id_house_unit = '$id_house'";
            }
            if ($status) {
                $sqlWhere .= " and spk_hdr.status = '$status'";
            }
            if ($aprv) {
                if($aprv == 'AP1'){
                    $sqlWhere .= " and spk_hdr.aprv1_by != ''";
                } else if($aprv == 'AP2'){
                    $sqlWhere .= " and spk_hdr.aprv2_by != ''";
                } else if($aprv == 'N'){
                    $sqlWhere .= " and spk_hdr.aprv1_by = '' or spk_hdr.aprv2_by = ''";
                } else if($aprv == 'A'){
                    $sqlWhere .= " and spk_hdr.aprv1_by != '' or spk_hdr.aprv2_by != ''";
                } 
                
            }
            
            $response = DB::connection('sqlSrvBCKI')
                ->table('spk_hdr')
                ->select([
                    'spk_hdr.*',
                    'site.site_name',
                    'site.remark',
                    'site_cluster.cluster_name',
                    'site_cluster.remark as remark_cluster',
                    'house_unit.house_name',
                    'house_type.common_name',
                    'house_type.sbk_name',
                    'house_type.category',
                    'vendor.vendor_name as nama_contractor',
                    'vendor.contact_name as contact_name',
                    'vendor.address1 as alamat_contractor',
                    'employee.name as nama_employee',
                    'employee.jabatan as jabatan_employee',
                    'employee.address1 as alamat_employee',
                    'spb_hdr.id_spb',
                    'qc_trans_hdr.id_qc_form',
                    'qc_trans_hdr.qc_trans_id',
                    DB::raw('(Select SUM(spk_article.qty* spk_article.harga_satuan) from spk_article where spk_article.id_spk = spk_hdr.id_spk) as harga'),
                    ])
                    ->join('site', 'site.id_site', '=', 'spk_hdr.id_site')
                    ->join('spb_hdr', 'spb_hdr.id_spb', '=', 'spk_hdr.id_spb')
                    ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
                    ->join('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
                ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
                ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                ->join(DB::raw("(
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY id_house ORDER BY qc_trans_id DESC) as rownum
                    FROM qc_trans_hdr
                ) as qc_trans_hdr"), function ($join) {
                    $join->on('spk_hdr.id_house_unit', '=', 'qc_trans_hdr.id_house')
                         ->where('qc_trans_hdr.rownum', '=', 1);
                })
                ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spk_hdr.pihak_1') // ini join ke database HRD_OL
                ->whereRaw($sqlWhere)
                ->get();
                
            foreach ($response as $item) {
                $resArticle = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'A')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article = $resArticle;

                $resArticleIn = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AI')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_in = $resArticleIn;

                // Ambil hanya field 'description' dari hasil query
                $descriptionsIn = $resArticleIn->pluck('description')->toArray();

                // Gabungkan deskripsi menjadi satu string
                $stringArticleIn = implode(', ', $descriptionsIn);

                // Tambahkan ke properti item dengan titik di akhir
                $item->article_in_desc = 'Pekerjaan sudah termasuk ' . $stringArticleIn . '.';

                $resArticleEx = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AE')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_ex = $resArticleEx;

                $descriptionsEx = $resArticleEx->pluck('description')->toArray();

                $stringArticleEx = implode(', ', $descriptionsEx);

                $item->article_ex_desc = 'Pekerjaan tidak termasuk ' . $stringArticleEx . '.';

                $resRemarkQC = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_QC')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                if (isset($resRemarkQC)) {
                    $item->remark_qc = $resRemarkQC->remarks;
                }

                $resRemarkLain = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_L')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                $item->remarks = $resRemarkLain->remarks;
            }

            return $this->sendResponse($response, 'Successfully Get SPK');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function getListSPKMobile(Request $request)
    {
        try {
            $sqlWhere = "spk_hdr.active_flag = 'Y'";
            $cekAprv1 = DB::connection('sqlsrv')
                ->table('sec_env_conf')
                ->selectRaw("var_value")
                ->where('user_id2',getAuth($request)->user_id2)
                ->where('entity_id','bcki')
                ->where('appl_id','MOBILE')
                ->where('var_id','APPROVALSPK1')
                ->where('active_flag','Y')
                ->value('var_value');

            $cekAprv2 = DB::connection('sqlsrv')
                ->table('sec_env_conf')
                ->selectRaw("var_value")
                ->where('user_id2',getAuth($request)->user_id2)
                ->where('entity_id','bcki')
                ->where('appl_id','MOBILE')
                ->where('var_id','APPROVALSPK2')
                ->where('active_flag','Y')
                ->value('var_value');

            if($cekAprv1=='Y' && $cekAprv2=='Y'){
                $sqlWhere = $sqlWhere." AND (FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' AND FORMAT(spk_hdr.dt_reject, 'yyyy-MM-dd') = '1900-01-01')
                                        OR 
                                        (FORMAT(spk_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' AND FORMAT(spk_hdr.dt_reject2, 'yyyy-MM-dd') = '1900-01-01')"; 
            }elseif($cekAprv1=='Y'){
               $sqlWhere =  $sqlWhere." AND (FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' AND FORMAT(spk_hdr.dt_reject, 'yyyy-MM-dd') = '1900-01-01')";
            }elseif($cekAprv2=='Y'){
                $sqlWhere = $sqlWhere." AND FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') != '1900-01-01' AND FORMAT(spk_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01'";
            }

            // dd($sqlWhere);
            // $sqlWhere = "(FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') = '1900-01-01' AND FORMAT(spk_hdr.dt_reject, 'yyyy-MM-dd') = '1900-01-01')
            //              OR 
            //              (FORMAT(spk_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' AND FORMAT(spk_hdr.dt_reject2, 'yyyy-MM-dd') = '1900-01-01')
            //              AND spk_hdr.active_flag = 'Y'";
            
            $response = DB::connection('sqlSrvBCKI')
                ->table('spk_hdr')
                ->selectRaw("
                                spk_hdr.*,
                                appl_constant.str2 as w_spk_type,
                                site.site_name,
                                site.remark,
                                site_cluster.cluster_name,
                                site_cluster.remark as remark_cluster,
                                house_unit.house_name,
                                house_type.common_name,
                                house_type.sbk_name,
                                house_type.category,
                                TRIM(vendor.vendor_name) as nama_contractor,
                                TRIM(vendor.contact_name) as contact_name,
                                TRIM(vendor.address1) as alamat_contractor,
                                employee.name as nama_employee,
                                employee.jabatan as jabatan_employee,
                                employee.address1 as alamat_employee,
                                spb_hdr.id_spb,
                                qc_trans_hdr.id_qc_form,
                                qc_trans_hdr.qc_trans_id,
                                (SELECT SUM(spk_article.qty * spk_article.harga_satuan) FROM spk_article WHERE spk_article.id_spk = spk_hdr.id_spk) as harga,
                                CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_aprv1, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                    ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPK1'),'-'))
                                END AS w_aprv1_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                    ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPK2'),'-'))
                                END AS w_aprv2_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_reject, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Ditolak oleh ', hrder1.name)
                                    ELSE '-' 
                                END AS w_reject1_by,
                                
                                CASE 
                                    WHEN FORMAT(spk_hdr.dt_reject2, 'yyyy-MM-dd') <> '1900-01-01'
                                    THEN CONCAT('Ditolak 2 oleh ', hrder2.name)
                                    ELSE '-' 
                                END AS w_reject2_by
                            ")
                    ->join('site', 'site.id_site', '=', 'spk_hdr.id_site')
                    ->join('spb_hdr', 'spb_hdr.id_spb', '=', 'spk_hdr.id_spb')
                    ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
                    ->join('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
                    ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
                    ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                    ->join(DB::raw("(
                        SELECT *, ROW_NUMBER() OVER (PARTITION BY id_house ORDER BY qc_trans_id DESC) as rownum
                        FROM qc_trans_hdr
                    ) as qc_trans_hdr"), function ($join) {
                        $join->on('spk_hdr.id_house_unit', '=', 'qc_trans_hdr.id_house')
                            ->where('qc_trans_hdr.rownum', '=', 1);
                    })
                    ->leftjoin('appl_constant', function ($join) {
                        $join->on('spk_hdr.spk_type', '=', 'appl_constant.str1')
                             ->where('appl_constant.key_code', '=', 'SPK_TYPE');
                    })
                    ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spk_hdr.pihak_1') // ini join ke database HRD_OL
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'spk_hdr.create_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'spk_hdr.aprv1_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'spk_hdr.aprv2_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder1'), 'hrder1.idemployee', '=', 'spk_hdr.reject_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder2'), 'hrder2.idemployee', '=', 'spk_hdr.reject2_by')
                ->whereRaw($sqlWhere)
                ->orderBy('spk_hdr.dt_created', 'asc')
                ->get();
                
            foreach ($response as $item) {
                $resArticle = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'A')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article = $resArticle;

                $resArticleIn = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AI')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_in = $resArticleIn;

                // Ambil hanya field 'description' dari hasil query
                $descriptionsIn = $resArticleIn->pluck('description')->toArray();

                // Gabungkan deskripsi menjadi satu string
                $stringArticleIn = implode(', ', $descriptionsIn);

                // Tambahkan ke properti item dengan titik di akhir
                $item->article_in_desc = 'Pekerjaan sudah termasuk ' . $stringArticleIn . '.';

                $resArticleEx = DB::connection('sqlSrvBCKI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AE')
                    ->where('id_spk', $item->id_spk)
                    ->get();

                $item->article_ex = $resArticleEx;

                $descriptionsEx = $resArticleEx->pluck('description')->toArray();

                $stringArticleEx = implode(', ', $descriptionsEx);

                $item->article_ex_desc = 'Pekerjaan tidak termasuk ' . $stringArticleEx . '.';

                $resRemarkQC = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_QC')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                if (isset($resRemarkQC)) {
                    $item->remark_qc = $resRemarkQC->remarks;
                }

                $resRemarkLain = DB::connection('sqlSrvBCKI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPK_L')
                    ->where('doc_id', $item->id_spk)
                    ->first();

                $item->remarks = $resRemarkLain->remarks;
            }

            return $this->sendResponse($response, 'Successfully Get SPK');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function getListSPKByVendor(Request $request){
        $sqlWhere = "1=1";
        $vendor_id = $request->vendor_id ?? '';
        
        if($vendor_id){
            $sqlWhere .= " and spk_hdr.pihak_2 = '$vendor_id'";
        }

        $data = DB::connection('sqlSrvBCKI')
                ->table('spk_hdr')
                ->select([
                    'spk_hdr.id_spk',
                    'spk_hdr.pihak_1',
                    'spk_hdr.pihak_2',
                    'spk_hdr.id_site',
                    'spk_hdr.id_cluster',
                    'spk_hdr.id_house_unit',
                    'site.site_name',
                    'site_cluster.cluster_name',
                    'house_unit.house_name',
                    'house_type.common_name',
                    DB::raw('TRIM(vendor.vendor_name) as vendor_name'), 
                    'appl_constant.str2 as tipe_spk'
                    ])
                ->join('site', 'site.id_site', '=', 'spk_hdr.id_site')
                ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
                ->join('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
                ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
                ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                ->join('appl_constant', function ($join) {
                    $join->on('spk_hdr.spk_type', '=', 'appl_constant.str1')
                        ->where('appl_constant.key_code', '=', 'SPK_TYPE');
                })
                ->whereRaw($sqlWhere)
                ->where('spk_hdr.active_flag', 'Y')
                ->where('spk_hdr.status', 'O')
                ->get();
        return $this->sendResponse($data, 'Successfully Get SPK by Vendor');
    }
}