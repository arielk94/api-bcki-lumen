<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

class PurchaseAprvMobile extends Controller
{   
    public function getList(Request $request)
    {
        try {
            $tr_type = $request->tr_type;
            $sqlWherePO = "1=1 ";
            $sqlWherePR = "1=1 ";
            
            if (empty($tr_type)){
                return $this->sendError("tr_type kosong", 'Error', 500);   
            }

            $cekAprvPO1 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','bcki')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALPO1')
                                ->where('active_flag','Y')
                                ->value('var_value');

            $cekAprvPO2 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','bcki')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALPO2')
                                ->where('active_flag','Y')
                                ->value('var_value');

            $cekAprvPR1 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','bcki')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALPR1')
                                ->where('active_flag','Y')
                                ->value('var_value');

            $cekAprvPR2 = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->selectRaw("var_value")
                                ->where('user_id2',getAuth($request)->user_id2)
                                ->where('entity_id','bcki')
                                ->where('appl_id','MOBILE')
                                ->where('var_id','APPROVALPR2')
                                ->where('active_flag','Y')
                                ->value('var_value');

            if($cekAprvPO1=='Y' && $cekAprvPO2=='Y'){
               $sqlWherePO =  $sqlWherePO." AND (FORMAT(po_hdr.dt_aprv, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(po_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01')";
            }elseif($cekAprvPO1=='Y'){
               $sqlWherePO =  $sqlWherePO." AND (FORMAT(po_hdr.dt_aprv, 'yyyy-MM-dd') = '1900-01-01' )";
            }elseif($cekAprvPO2=='Y'){
                $sqlWherePO = $sqlWherePO." AND (FORMAT(po_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' )";
            }

            if($cekAprvPR1=='Y' && $cekAprvPR2=='Y'){
               $sqlWherePR =  $sqlWherePR." AND (FORMAT(pr_hdr.dt_aprv, 'yyyy-MM-dd') = '1900-01-01' OR FORMAT(pr_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01')";
            }elseif($cekAprvPR1=='Y'){
               $sqlWherePR =  $sqlWherePR." AND (FORMAT(pr_hdr.dt_aprv, 'yyyy-MM-dd') = '1900-01-01' )";
            }elseif($cekAprvPR2=='Y'){
                $sqlWherePR = $sqlWherePR." AND (FORMAT(pr_hdr.dt_aprv2, 'yyyy-MM-dd') = '1900-01-01' )";
            }

            if($tr_type == 'PO'){
                $datas = DB::connection("sqlSrvBCKI")
                                ->table('po_hdr')
                                ->selectRaw("
                                            TRIM(po_hdr.po_id) AS po_id, 
                                            po_hdr.dt_po, 
                                            po_hdr.stat, 
                                            TRIM(po_hdr.pic_id) AS pic_id, 
                                            TRIM(po_hdr.vendor_id) AS vendor_id, 
                                            TRIM(vendor.vendor_name) AS vendor_name, 
                                            po_hdr.memo_txt, 
                                            po_hdr.qty, 
                                            po_hdr.amt_subtotal,
                                            po_hdr.amt_disc, 
                                            po_hdr.amt_net, 
                                            po_hdr.amt_ppn, 
                                            po_hdr.amt_pph, 
                                            CONCAT(ROUND(po_hdr.ppn,1),' %') as ppn_persen, 
                                            po_hdr.ppn, 
                                            CASE
                                                WHEN po_hdr.dt_modified > po_hdr.dt_created 
                                                THEN po_hdr.dt_modified
                                                ELSE po_hdr.dt_created
                                            END AS dt_created,
                                            po_hdr.dt_aprv, 
                                            po_hdr.dt_aprv2, 
                                            po_hdr.dt_rjc, 
                                            po_hdr.dt_rjc2, 
                                            TRIM(po_hdr.aprv_by) AS aprv_by, 
                                            TRIM(po_hdr.aprv2_by) AS aprv2_by, 
                                            TRIM(po_hdr.rjc_by) AS rjc_by, 
                                            TRIM(po_hdr.rjc2_by) AS rjc2_by, 
                                            TRIM(po_hdr.pay_term_id) AS pay_term_id, 
                                            TRIM(po_hdr.pr_id) AS pr_id, 
                                            department.dept_name,
                                            po_hdr.office_id, 
                                            TRIM(branch_office.office) as office,
                                            CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                                            CASE 
                                                WHEN FORMAT(po_hdr.dt_aprv, 'yyyy-MM-dd') <> '1900-01-01'
                                                THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                                ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND var_id = 'APPROVALBON1'),'-'))
                                            END AS w_aprv1_by,
                                            
                                            CASE 
                                                WHEN FORMAT(po_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                                THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                                ELSE CONCAT('Menunggu persetujuan oleh ' , ISNULL((SELECT STRING_AGG(name, '/ ') FROM env_conf_belong WHERE appl_id= 'MOBILE' AND var_id = 'APPROVALBON2'),'-'))
                                            END AS w_aprv2_by
                                            ")
                                ->leftjoin('vendor', 'po_hdr.vendor_id', '=', 'vendor.vendor_id')
                                ->leftjoin('branch_office', 'po_hdr.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('department', 'po_hdr.dept_id', '=', 'department.dept_id')
                                // ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrded'), 'hrded.idemployee', '=', 'po_hdr.idemployee')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'po_hdr.user_id')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'po_hdr.aprv_by')
                                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'po_hdr.aprv2_by')
                                // ->whereDate('po_hdr.dt_aprv', '=', '1900-01-01')
                                // ->whereDate('po_hdr.dt_aprv2', '=', '1900-01-01')
                                ->whereDate('po_hdr.dt_rjc', '=', '1900-01-01')
                                ->whereDate('po_hdr.dt_rjc2', '=', '1900-01-01')
                                ->where('po_hdr.stat','=','O')
                                ->whereRaw($sqlWherePO)
                                // ->whereIn('po_hdr.dept_id', $getRightWH)
                                ->orderBy('po_hdr.dt_po', 'asc')
                                ->get();
                    
                foreach ($datas as $item) {
                    $resArticle = DB::connection('sqlSrvBCKI')
                                        ->table('po_item')
                                        ->selectRaw("
                                                ROW_NUMBER() OVER (ORDER BY po_item.po_id) AS no,
                                                TRIM(branch_office.office) AS office, 
                                                po_item.po_item, 
                                                TRIM(po_item.po_id) AS po_id, 
                                                article.description, 
                                                po_item.qty, 
                                                po_item.article_id, 
                                                po_item.stat, 
                                                po_item.amt_net, 
                                                TRIM(po_item.unit_meas) AS unit_meas, 
                                                po_item.unit_price, 
                                                po_item.remark
                                                ")
                                        ->leftJoin('branch_office', 'po_item.office_id', '=', 'branch_office.office_id')
                                        ->leftJoin('article', 'po_item.article_id', '=', 'article.article_id')
                                        ->where('po_item.po_id', $item->po_id)
                                        ->get();

                    $item->article = $resArticle;
                }

                return $this->sendResponse($datas, 'Successfully Get PO');
            }elseif($tr_type == 'PR'){
                $rawWh = DB::connection("sqlsrv")
                            ->table('sec_env_conf')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'MOBILE')
                            ->where('var_id', '=', 'APPROVALPR_DEPT')
                            ->where('user_id2', '=', getAuth($request)->user_id2)
                            ->where('active_flag', '=', 'Y')
                            ->value(DB::raw('TRIM(var_value)'));
                $getRightWH = explode(',', $rawWh);
                
                $datas = DB::connection("sqlSrvBCKI")
                                    ->table('pr_hdr')
                                    ->selectRaw("
                                                TRIM(pr_hdr.pr_id) as pr_id,
                                                pr_hdr.dt_pr,
                                                TRIM(pr_hdr.dept_id) AS dept_id,
                                                branch_office.address,
                                                TRIM(branch_office.city) AS city,
                                                TRIM(department.dept_name) AS dept_name,
                                                pr_hdr.qty,
                                                TRIM(pr_hdr.pic_id) AS pic_id,
                                                TRIM(vendor.vendor_id) AS vendor_id,
                                                trim(vendor.vendor_name) as vendor_name,
                                                pr_hdr.wgt,
                                                pr_hdr.pr_num,
                                                pr_hdr.stat,
                                                pr_hdr.memo_txt,
                                                pr_hdr.tr_type,
                                                CASE
                                                    WHEN pr_hdr.dt_modified > pr_hdr.dt_created 
                                                    THEN pr_hdr.dt_modified
                                                    ELSE pr_hdr.dt_created
                                                END AS dt_created,
                                                pr_hdr.dt_aprv,
                                                pr_hdr.dt_aprv2,
                                                pr_hdr.dt_rjc,
                                                pr_hdr.dt_rjc2,
                                                TRIM(pr_hdr.aprv_by) AS aprv_by,
                                                TRIM(pr_hdr.aprv2_by) AS aprv2_by,
                                                TRIM(pr_hdr.rjc_by) AS rjc_by,
                                                TRIM(pr_hdr.rjc2_by) AS rjc2_by,
                                                TRIM(site.site_name) as site_name,
                                                TRIM(site_cluster.cluster_name) as cluster_name,
                                                TRIM(house_unit.house_name) as house_name,
                                                pr_hdr.office_id,
                                                TRIM(branch_office.office) as office,
                                                CASE 
                                                    WHEN type_pr = 'Y' THEN 'Purchase Request'
                                                    WHEN type_pr = 'N' THEN 'Payment Request'
                                                    ELSE '-'
                                                END AS type_pr,
                                                CONCAT('Dibuat oleh ', hrdec.name) AS w_created_by,
                                                CASE 
                                                    WHEN FORMAT(pr_hdr.dt_aprv, 'yyyy-MM-dd') <> '1900-01-01'
                                                    THEN CONCAT('Disetujui oleh ', hrdea1.name)
                                                    ELSE CONCAT('Menunggu persetujuan ', ISNULL(approval1.approval_names, ''))
                                                END AS w_aprv1_by,

                                                CASE 
                                                    WHEN FORMAT(pr_hdr.dt_aprv2, 'yyyy-MM-dd') <> '1900-01-01'
                                                    THEN CONCAT('Disetujui oleh ', hrdea2.name)
                                                    ELSE CONCAT('Menunggu persetujuan ', ISNULL(approval2.approval_names, ''))
                                                END AS w_aprv2_by
                                                ")
                                    ->leftjoin('branch_office', 'pr_hdr.office_id', '=', 'branch_office.office_id')
                                    ->leftjoin('department', 'pr_hdr.dept_id', '=', 'department.dept_id')
                                    ->leftjoin('site_cluster', 'pr_hdr.id_cluster', '=', 'site_cluster.id_cluster')
                                    ->leftjoin('house_unit', 'pr_hdr.id_house', '=', 'house_unit.id_house')
                                    ->leftjoin('site', 'pr_hdr.id_site', '=', 'site.id_site')
                                    ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')
                                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdec'), 'hrdec.idemployee', '=', 'pr_hdr.user_id')
                                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea1'), 'hrdea1.idemployee', '=', 'pr_hdr.aprv_by')
                                    ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdea2'), 'hrdea2.idemployee', '=', 'pr_hdr.aprv2_by')
                                    // LEFT JOIN subquery untuk approval 1
                                    ->leftJoin(DB::raw("
                                                            (
                                                            SELECT 
                                                                ecb_dept.var_value AS dept_id,
                                                                STRING_AGG(ecb1.name, '/ ') AS approval_names
                                                            FROM env_conf_belong ecb1
                                                            JOIN env_conf_belong ecb_dept 
                                                                ON ecb1.user_id2 = ecb_dept.user_id2
                                                            WHERE ecb1.var_id = 'APPROVALPR1'
                                                                AND ecb_dept.appl_id = 'MOBILE'
                                                                AND ecb_dept.var_id = 'APPROVALPR_DEPT'
                                                            GROUP BY ecb_dept.var_value
                                                            ) AS approval1
                                                        "), 'approval1.dept_id', '=', 'pr_hdr.dept_id')
                                    // LEFT JOIN subquery untuk approval 2
                                    ->leftJoin(DB::raw("
                                                        (
                                                        SELECT 
                                                            ecb_dept.var_value AS dept_id,
                                                            STRING_AGG(ecb2.name, '/ ') AS approval_names
                                                        FROM env_conf_belong ecb2
                                                        JOIN env_conf_belong ecb_dept 
                                                            ON ecb2.user_id2 = ecb_dept.user_id2
                                                        WHERE ecb2.var_id = 'APPROVALPR2'
                                                            AND ecb_dept.appl_id = 'MOBILE'
                                                            AND ecb_dept.var_id = 'APPROVALPR_DEPT'
                                                        GROUP BY ecb_dept.var_value
                                                        ) AS approval2
                                                    "), 'approval2.dept_id', '=', 'pr_hdr.dept_id')
                                    // ->whereDate('pr_hdr.dt_aprv', '=', '1900-01-01')
                                    // ->whereDate('pr_hdr.dt_aprv2', '=', '1900-01-01')
                                    // ->whereDate('pr_hdr.dt_rjc', '=', '1900-01-01')
                                    // ->whereDate('pr_hdr.dt_rjc2', '=', '1900-01-01')
                                    ->whereIn('pr_hdr.dept_id', $getRightWH)
                                    ->whereRaw($sqlWherePR)
                                    ->where('pr_hdr.stat','=','O')
                                    ->orderBy('pr_hdr.dt_created', 'asc')
                                    ->get();
                    
                foreach ($datas as $item) {
                    $resArticle = DB::connection('sqlSrvBCKI')
                                        ->table('pr_item')
                                        ->selectRaw("
                                        ROW_NUMBER() OVER (ORDER BY pr_item.pr_id) AS no,
                                        pr_item.pr_item,
                                        TRIM(pr_item.pr_id) AS pr_id, 	
                                        pr_item.article_id, 
                                        article.description, 
                                        pr_item.qty, 
                                        pr_item.unit_meas, 	
                                        TRIM(pr_item.remark) AS remark, 
                                        pr_item.stat, 
                                        pr_item.office_id, 
                                        pr_item.wgt, 
                                        pr_item.dt_created, 
                                        pr_item.dt_modified,
                                        pr_item.amount,
                                        pr_item.unit_price,
                                        isnull(trim(site_cluster.cluster_name), '') as cluster_name,
                                        isnull(trim(house_unit.house_name), '') as house_name,
                                        isnull(trim(site.site_name), '') as site_name
                                        ")
                                        ->leftJoin('branch_office', 'pr_item.office_id', '=', 'branch_office.office_id')
                                        ->leftJoin('article', 'pr_item.article_id', '=', 'article.article_id')
                                        ->leftjoin('site_cluster', 'pr_item.id_cluster', '=', 'site_cluster.id_cluster')
                                        ->leftjoin('house_unit', 'pr_item.id_house', '=', 'house_unit.id_house')
                                        ->leftjoin('site', 'pr_item.id_site', '=', 'site.id_site')
                                        ->where('pr_item.pr_id', $item->pr_id)
                                        ->get();

                    $item->article = $resArticle;
                }

                return $this->sendResponse($datas, 'Successfully Get PR');
            }else{
                return $this->sendError("Ada gangguan, hubungi IT", 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }

    public function approve(Request $request)
    {
        try {
            $tr_type = $request->tr_type;
            $tr_id = $request->tr_id;
            $type_aprv = $request->type_aprv;
            
            if (empty($tr_type)){
                return $this->sendError("tr_type kosong", 'Error', 500);   
            }
            if (empty($tr_id)){
                return $this->sendError("tr_id kosong", 'Error', 500);   
            }
            if (empty($type_aprv)){
                return $this->sendError("type_aprv kosong", 'Error', 500);   
            }

            if($tr_type == 'PO'){
                if($type_aprv=='approve1'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $tr_id)
                            ->first();
                    $dept_id = '';

                    if ($order) {
                        if ($order->dt_aprv == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('po_hdr')
                                    ->where('po_id', $tr_id)
                                    ->update([
                                        'dt_aprv' => now(),
                                        'dt_modified' => now(),
                                        'aprv_by' => getAuth($request)->user_id2
                                    ]);
                            return $this->sendResponse('Success Approved', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }  
                }elseif($type_aprv=='approve2'){    
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $tr_id)
                            ->first();

                    if ($order) {
                        if($order->dt_aprv == '1900-01-01 00:00:00.000'){
                            return $this->sendError('Belum Approve 1, Silahkan Approve 1 Dahulu', 'Error', 500);
                        }
                        if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('po_hdr')
                                    ->where('po_id', $tr_id)
                                    ->update([
                                        'dt_aprv2' => now(),
                                        'dt_modified' => now(),
                                        'aprv2_by' => getAuth($request)->user_id2
                                    ]);
                            return $this->sendResponse('Success Approved', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }             
                }else{
                    return $this->sendError("Salah type_aprv", 'Error', 500);    
                }
            }elseif($tr_type == 'PR'){
                if($type_aprv=='approve1'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->selectRaw('pr_hdr.*, ISNULL(vendor.apprv, 0) as apprv')
                            ->where('pr_id', $tr_id)
                            ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')                    
                            ->first();
                    $dept_id = trim($order->dept_id);
                    $vendor_apprv = trim($order->apprv);

                    if ($order) {
                        if ($order->dt_aprv == '1900-01-01 00:00:00.000') {
                            if($vendor_apprv == 0){
                                $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_aprv' => now(),
                                        'dt_modified' => now(),
                                        'aprv_by' => getAuth($request)->user_id2
                                    ]);
                                $whatsapp = $this->sendNotifPurchase('PR',$tr_id,$dept_id);
                            }
                            if($vendor_apprv == 1){
                                $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_aprv' => now(),
                                        'aprv_by' => getAuth($request)->user_id2,
                                        'dt_aprv2' => now(),
                                        'aprv2_by' => getAuth($request)->user_id2,
                                        'dt_modified' => now()
                                    ]);
                            }
                            if($vendor_apprv == 2){
                                $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_aprv' => now(),
                                        'dt_modified' => now(),
                                        'aprv_by' => getAuth($request)->user_id2
                                    ]);
                                $whatsapp = $this->sendNotifPurchase('PR',$tr_id,$dept_id);
                            }
                            return $this->sendResponse('Success Approved 1', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }elseif($type_aprv=='approve2'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->selectRaw('pr_hdr.*, ISNULL(vendor.apprv, 0) as apprv')
                            ->where('pr_id', $tr_id)
                            ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')                    
                            ->first();
                    $vendor_apprv = trim($order->apprv);

                    if ($order) {
                        if($order->dt_aprv == '1900-01-01 00:00:00.000'){
                            return $this->sendError('Belum Approve 1, Silahkan Approve 1 Dahulu', 'Error', 500);
                        }
                        if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                            if($vendor_apprv == 0){
                                $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_aprv2' => now(),
                                        'dt_modified' => now(),
                                        'aprv2_by' => getAuth($request)->user_id2
                                    ]);
                            }
                            if($vendor_apprv == 2){
                                $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_aprv2' => now(),
                                        'dt_modified' => now(),
                                        'aprv2_by' => getAuth($request)->user_id2
                                    ]);
                            }
                            return $this->sendResponse('Success Approved', 'Successfully saved');

                        } else {
                            return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                        }
                        
                        
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }else{
                    return $this->sendError("Salah type_aprv", 'Error', 500);    
                }
            }else{
                return $this->sendError("Ada gangguan, hubungi IT", 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }

    public function sendNotifPurchase($type,$po_id,$dept_id)
    {
        try {
            if($type=='PO'){
                $type_name = "*_Purchase Order_*";
            }elseif($type=='PR'){
                $type_name = "*_Purchase Request_*";
            }

            $getEmp = ''; 
            if($type=='PR'){
                $getEmp = DB::connection('sqlSrvBCKI')
                    ->table('department')
                    ->selectRaw('nik_pr2')
                    ->whereRaw("dept_id = '" . $dept_id . "'")
                    ->get(); 
                $getEmp = $getEmp[0]->nik_pr2;
            }

            if($type=='PR'){
                $getPhone = DB::connection('sqlSrvBCKI')
                            ->table('department')
                            ->selectRaw('pr2')
                            // ->whereRaw($sqlWhere)
                            ->whereRaw("dept_id = '" . $dept_id . "'")
                            ->get(); 
                // dd($getPhone);
                $getPhone = $getPhone[0]->pr2;
            }

                $getLink = "bcki" . Carbon::now()->format('ymdH') . rand(10000, 99999);
                DB::connection('sqlsrv')
                ->table('sec_reset_password')
                ->insert([
                    'user_id2' => $getEmp ? $getEmp : '',
                    'link' => $getLink,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $message = "*FASINDO PROPERTI INDONESIA*\n\n" .
                "Silahkan lakukan Approval untuk {$type_name} Nomor: {$po_id}\n";
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
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $tr_type = $request->tr_type;
            $tr_id = $request->tr_id;
            $type_aprv = $request->type_aprv;
            
            if (empty($tr_type)){
                return $this->sendError("tr_type kosong", 'Error', 500);   
            }
            if (empty($tr_id)){
                return $this->sendError("tr_id kosong", 'Error', 500);   
            }
            if (empty($type_aprv)){
                return $this->sendError("type_aprv kosong", 'Error', 500);   
            }

            if($tr_type == 'PO'){
                if($type_aprv=='approve1'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $tr_id)
                            ->first();
                    
                    if ($order) {
                        if ($order->dt_rjc == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('po_hdr')
                                    ->where('po_id', $tr_id)
                                    ->update([
                                        'dt_rjc' => now(),
                                        'dt_rjc2' => now(),
                                        'dt_modified' => now(),
                                        'rjc_by' => getAuth($request)->user_id2,
                                        'rjc2_by' => getAuth($request)->user_id2,
                                        'stat' => 'X'
                                    ]);
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('po_item')
                                    ->where('po_id', $tr_id)
                                    ->update([
                                        'dt_modified' => Carbon::now(),
                                        'stat' => 'X'
                                    ]);

                            return $this->sendResponse('Success UnApproved 1', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }elseif($type_aprv=='approve2'){    
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $tr_id)
                            ->first();
                    
                    if ($order) {
                        if ($order->dt_rjc2 == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('po_hdr')
                                    ->where('po_id', $tr_id)
                                    ->update([
                                        'dt_rjc2' => now(),
                                        'dt_modified' => now(),
                                        'rjc2_by' => getAuth($request)->user_id2
                                    ]);

                            return $this->sendResponse('Success UnApproved 1', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }else{
                    return $this->sendError("Salah type_aprv", 'Error', 500);    
                }
            }elseif($tr_type == 'PR'){
                if($type_aprv=='approve1'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $tr_id)
                            ->first();
                    
                    if ($order) {
                        if ($order->dt_rjc == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_rjc' => now(),
                                        'dt_rjc2' => now(),
                                        'dt_modified' => now(),
                                        'rjc_by' => getAuth($request)->user_id2,
                                        'rjc2_by' => getAuth($request)->user_id2,
                                        'stat' => 'X'
                                    ]);
                            $save2 = DB::connection('sqlSrvBCKI')
                                    ->table('pr_item')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_modified' => Carbon::now(),
                                        'stat' => 'X'
                                    ]);

                            // DB::connection('sqlSrvBCKI')->commit();

                            return $this->sendResponse('Success UnApproved 1', 'Successfully saved');

                        } else {
                            return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }elseif($type_aprv=='approve2'){
                    $order = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $tr_id)
                            ->first();
                    
                    if ($order) {
                        if ($order->dt_rjc2 == '1900-01-01 00:00:00.000') {
                            $save = DB::connection('sqlSrvBCKI')
                                    ->table('pr_hdr')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_rjc' => now(),
                                        'dt_rjc2' => now(),
                                        'dt_modified' => now(),
                                        'rjc_by' => getAuth($request)->user_id2,
                                        'rjc2_by' => getAuth($request)->user_id2,
                                        'stat' => 'X'
                                    ]);

                            $save2 = DB::connection('sqlSrvBCKI')
                                    ->table('pr_item')
                                    ->where('pr_id', $tr_id)
                                    ->update([
                                        'dt_modified' => Carbon::now(),
                                        'stat' => 'X'
                                    ]);

                            return $this->sendResponse('Success UnApproved 1', 'Successfully saved');
                        } else {
                            return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                        }
                    } else {
                        return $this->sendError('No Order tidak ditemukan', 'Error', 500);
                    }           
                }else{
                    return $this->sendError("Salah type_aprv", 'Error', 500);    
                }
            }else{
                return $this->sendError("Ada gangguan, hubungi IT", 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }
}
