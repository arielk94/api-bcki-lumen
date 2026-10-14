<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class ApprovalSPBController extends Controller
{
    public function getListSPB(Request $request)
    {
        
        try {
            $id_site = $request->id_site ?? '';
            $id_cluster = $request->id_cluster ?? '';
            $id_house = $request->id_house ?? '';
            $status = $request->status ?? '';
            $aprv = $request->aprv ?? '';
            
            $sqlWhere = "spb_hdr.active_flag = 'Y'";
            
            if ($id_site) {
                
                $sqlWhere .= " and spb_hdr.id_site = '$id_site'";
            }
            if ($id_cluster) {

                $sqlWhere .= " and spb_hdr.id_cluster = '$id_cluster'";
            }
            if ($id_house) {
                
                $sqlWhere .= " and spb_hdr.id_house_unit = '$id_house'";
            }
            if ($status) {
                $sqlWhere .= " and spb_hdr.status = '$status'";
            }
            if ($aprv) {
                // if($aprv == 'AP1'){
                //     $sqlWhere .= " and spb_hdr.aprv1_by != ''";
                // } else if($aprv == 'AP2'){
                //     $sqlWhere .= " and spb_hdr.aprv2_by != ''";
                // } else if($aprv == 'N'){
                //     $sqlWhere .= " and spb_hdr.aprv1_by = '' or spb_hdr.aprv2_by = ''";
                // } else if($aprv == 'A'){
                //     // $sqlWhere .= " and spb_hdr.aprv1_by != '' or spb_hdr.aprv2_by != ''";
                // } 
            }else{
                $sqlWhere = $sqlWhere . "and spb_hdr.dt_aprv1 = '1900-01-01 00:00:00.000'";
            }
            
            $response = DB::connection('sqlSrvFPI')
                ->table('spb_hdr')
                ->selectRaw("
                            spb_hdr.*,
                            site.site_name,
                            site.remark,
                            site_cluster.cluster_name,
                            site_cluster.remark as remark_cluster,
                            house_unit.house_name,
                            house_type.common_name,
                            house_type.sbk_name,
                            house_type.category,
                            CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                            CASE 
                                WHEN FORMAT(spb_hdr.dt_aprv1, 'yyyy-MM-dd') <> '1900-01-01'
                                THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPB1'),'-'))
                            END AS w_aprv1_by,
                            
                            CASE 
                                WHEN FORMAT(spb_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE var_id = 'APPROVALSPB2'),'-'))
                            END AS w_aprv2_by,
                            
                            CASE 
                                WHEN FORMAT(spb_hdr.dt_reject, 'yyyy-MM-dd') <> '1900-01-01'
                                THEN CONCAT('Ditolak oleh ', hrder1.name)
                                ELSE '-' 
                            END AS w_reject1_by,
                            
                            CASE 
                                WHEN FORMAT(spb_hdr.dt_reject2, 'yyyy-MM-dd') <> '1900-01-01'
                                THEN CONCAT('Ditolak 2 oleh ', hrder2.name)
                                ELSE '-' 
                            END AS w_reject2_by
                            ")
                    ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                    ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                    ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                    ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'spb_hdr.created_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'spb_hdr.aprv1_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'spb_hdr.aprv2_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder1'), 'hrder1.idemployee', '=', 'spb_hdr.reject_by')
                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrder2'), 'hrder2.idemployee', '=', 'spb_hdr.reject2_by')
                    // ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spb_hdr.pihak_1') // ini join ke database HRD_OL
                ->whereRaw($sqlWhere)
                ->orderBy('spb_hdr.dt_created', 'asc')
                ->get();

            return $this->sendResponse($response, 'Successfully Get SPB');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function approvalSPB(Request $request)
    {
        try {
            $id_spb = $request->id_spb;
            $type_aprv = $request->type_aprv;

            if (empty($id_spb)) {
                return $this->sendError("ID SPB Kosong", 'Error', 500);
            }
            if (empty($type_aprv)) {
                return $this->sendError("Type Aprv Kosong", 'Error', 500);
            }

            if($type_aprv == 'approve1'){
                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('spb_hdr')
                                ->where('id_spb', $id_spb)
                                ->selectRaw("FORMAT(dt_aprv1, 'yyyy-MM-dd') as dt_aprv1")
                                ->value('dt_aprv1');

                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 1", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('spb_hdr')
                        ->where('id_spb', '=', $id_spb)
                        ->update([
                            'dt_aprv1' => now(),
                            'aprv1_by' => getAuth($request)->user_id2
                        ]);
                    
                    if($response){
                        $user = DB::connection('sqlSrvFPI')
                        ->table('spk_aprv_env')
                        ->selectRaw('aprv_2, no_hp_2, nama_employee_2')
                        ->where('doc_type', '=', 'SPB')
                        ->where('active_flag', '=', 'Y')
                        ->get();
    
                        if($user){
                            $countSuccess = 0;
                            $countError = 0;
                            $totalJumlah = 0;
                            foreach($user as $key => $value){
                                $res = $this->sendNotifSPBApproval($id_spb, $value->aprv_2, $value->no_hp_2);
    
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
                                    'message' => 'Successfully saved SPB & Send All Notifikasi'
                                ], 200);
                            }else if($countError != 0 && $countSuccess != $totalJumlah){
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully saved SPB & Send Notifikasi'
                                ], 200);
                            }else{
                                return response()->json([
                                    'status' => true,
                                    'message' => 'Successfully saved SPB'
                                ], 200);
                            }
                        }
                    }
                }

            }else if($type_aprv == 'approve2'){
                $cekAprv2 = DB::connection("sqlSrvFPI")
                                ->table('spb_hdr')
                                ->where('id_spb', $id_spb)
                                ->selectRaw("FORMAT(dt_aprv2, 'yyyy-MM-dd') as dt_aprv2")
                                ->value('dt_aprv2');
                
                if ($cekAprv2 !== '1900-01-01') {
                    return $this->sendError("Sudah di approve 2", 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('spb_hdr')
                        ->where('id_spb', '=', $id_spb)
                        ->update([
                            'dt_aprv2' => now(),
                            'aprv2_by' => getAuth($request)->user_id2
                        ]);
                }

            }

            return $this->sendResponse($response, 'Successfully Update Approval SPB');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }

    public function rejectApprove(Request $request)
    {
        try {
            $id_spb = $request->id_spb;
            $type_aprv = $request->type_aprv;

            if($type_aprv == 'approve1'){
                $cekAprv = DB::connection("sqlSrvFPI")
                                ->table('spb_hdr')
                                ->where('id_spb', $id_spb)
                                ->selectRaw("FORMAT(dt_reject, 'yyyy-MM-dd') as dt_reject")
                                ->value('dt_reject');

                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError('Sudah di tolak, kok masih berharap (-_-")', 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('spb_hdr')
                        ->where('id_spb', '=', $id_spb)
                        ->update([
                            'dt_reject' => now(),
                            'status' => "X",
                            'reject_by' => getAuth($request)->user_id2
                        ]);
                }

            }else if($type_aprv == 'approve2'){
                $cekAprv2 = DB::connection("sqlSrvFPI")
                                ->table('spb_hdr')
                                ->where('id_spb', $id_spb)
                                ->selectRaw("FORMAT(dt_reject2, 'yyyy-MM-dd') as dt_reject2")
                                ->value('dt_reject2');

                if ($cekAprv2 !== '1900-01-01') {
                    return $this->sendError('Sudah di tolak 2, kok masih berharap (-_-")', 'Error', 500);
                }else{
                    $response = DB::connection('sqlSrvFPI')
                        ->table('spb_hdr')
                        ->where('id_spb', '=', $id_spb)
                        ->update([
                            'dt_reject2' => now(),
                            'status' => "X",
                            'reject2_by' => getAuth($request)->user_id2
                        ]);
                }
            }

            return $this->sendResponse($response, 'Successfully Update Reject SPB');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }
    
    private function sendNotifSPBApproval($spkId, $getEmp, $getPhone)
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
            
            $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
            "Silahkan lakukan Approval untuk SPB ID: {$spkId}\n";
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

    public function getStatusSPB(Request $request)
    {
        try {
            $id_site = $request->id_site ?? '';
            $id_cluster = $request->id_cluster ?? '';
            $id_house = $request->id_house ?? '';
            $status = $request->status ?? '';
            $aprv = $request->aprv ?? '';

            
            $sqlWhere = "1=1";
            
            if ($id_site) {
                
                $sqlWhere .= " and spb_hdr.id_site = '$id_site'";
            }
            if ($id_cluster) {

                $sqlWhere .= " and spb_hdr.id_cluster = '$id_cluster'";
            }
            if ($id_house) {
                
                $sqlWhere .= " and spb_hdr.id_house_unit = '$id_house'";
            }
            if ($status) {
                $sqlWhere .= " and spb_hdr.status = '$status'";
            }
            if ($aprv) {
                if($aprv == 'AP1'){
                    $sqlWhere .= " and spb_hdr.aprv1_by != ''";
                } else if($aprv == 'AP2'){
                    $sqlWhere .= " and spb_hdr.aprv2_by != ''";
                } else if($aprv == 'N'){
                    $sqlWhere .= " and spb_hdr.aprv1_by = '' or spb_hdr.aprv2_by = ''";
                } else if($aprv == 'A'){
                    $sqlWhere .= " and spb_hdr.aprv1_by != '' or spb_hdr.aprv2_by != ''";
                } 
                
            }
            
            $response = DB::connection('sqlSrvFPI')
                ->table('spb_hdr')
                ->select([
                    'spb_hdr.*',
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
                    DB::raw('(Select SUM(spk_article.qty* spk_article.harga_satuan) from spk_article where spk_article.id_spb = spb_hdr.id_spb) as harga'),
                    ])
                    ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                    ->join('spb_hdr', 'spb_hdr.id_spb', '=', 'spb_hdr.id_spb')
                    ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                    ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                ->leftJoin('vendor', 'spb_hdr.pihak_2', '=', 'vendor.vendor_id')
                ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
                ->join(DB::raw("(
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY id_house ORDER BY qc_trans_id DESC) as rownum
                    FROM qc_trans_hdr
                ) as qc_trans_hdr"), function ($join) {
                    $join->on('spb_hdr.id_house_unit', '=', 'qc_trans_hdr.id_house')
                         ->where('qc_trans_hdr.rownum', '=', 1);
                })
                ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spb_hdr.pihak_1') // ini join ke database HRD_OL
                ->whereRaw($sqlWhere)
                ->get();
                
            foreach ($response as $item) {
                $resArticle = DB::connection('sqlSrvFPI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'A')
                    ->where('id_spb', $item->id_spb)
                    ->get();

                $item->article = $resArticle;

                $resArticleIn = DB::connection('sqlSrvFPI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AI')
                    ->where('id_spb', $item->id_spb)
                    ->get();

                $item->article_in = $resArticleIn;

                // Ambil hanya field 'description' dari hasil query
                $descriptionsIn = $resArticleIn->pluck('description')->toArray();

                // Gabungkan deskripsi menjadi satu string
                $stringArticleIn = implode(', ', $descriptionsIn);

                // Tambahkan ke properti item dengan titik di akhir
                $item->article_in_desc = 'Pekerjaan sudah termasuk ' . $stringArticleIn . '.';

                $resArticleEx = DB::connection('sqlSrvFPI')
                    ->table('spk_article')
                    ->select([
                        'spk_article.*',
                        'article.description'
                    ])
                    ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                    ->where('type_article', 'AE')
                    ->where('id_spb', $item->id_spb)
                    ->get();

                $item->article_ex = $resArticleEx;

                $descriptionsEx = $resArticleEx->pluck('description')->toArray();

                $stringArticleEx = implode(', ', $descriptionsEx);

                $item->article_ex_desc = 'Pekerjaan tidak termasuk ' . $stringArticleEx . '.';

                $resRemarkQC = DB::connection('sqlSrvFPI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPB_QC')
                    ->where('doc_id', $item->id_spb)
                    ->first();

                if (isset($resRemarkQC)) {
                    $item->remark_qc = $resRemarkQC->remarks;
                }

                $resRemarkLain = DB::connection('sqlSrvFPI')
                    ->table('appl_remark')
                    ->select([
                        'appl_remark.remarks'
                    ])
                    ->where('type_code', 'SPB_L')
                    ->where('doc_id', $item->id_spb)
                    ->first();

                $item->remarks = $resRemarkLain->remarks;
            }

            return $this->sendResponse($response, 'Successfully Get SPB');
        } catch (\Throwable $th) {
            return $this->sendResponse("Gagal memuat server " . $th, 500);
        }
    }
}