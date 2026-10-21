<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

class PurchaseRequestAprv extends Controller
{
    public function getPR(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $dept_id = $request->dept_id;
            $aprv = $request->aprv;
            $statusOP = $request->statusOP;
            $typePR = $request->typePR; //Y = purchase request, N = payment request

            if ($typePR === 'Y') {
                $sqlWhere = $sqlWhere . "and pr_hdr.type_pr = 'Y'";

            } elseif ($typePR === 'N') {
                $sqlWhere = $sqlWhere . "and pr_hdr.type_pr = 'N'";

            }

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
                    $sqlWhere .= " and pr_hdr.dt_pr BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and pr_hdr.dt_pr BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
                }
            }


            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($dept_id))
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.dept_id = ". "'" . $dept_id . "'";
            }

            if ($aprv=='All')
            {
                $sqlWhere = $sqlWhere;
            }
            if ($aprv=='Approve 1')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.dt_aprv != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Approve 2')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.dt_aprv2 != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Not Approve')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.dt_aprv = '1900-01-01 00:00:00.000' and pr_hdr.dt_aprv2 = '1900-01-01 00:00:00.000' and pr_hdr.dt_rjc = '1900-01-01 00:00:00.000' and pr_hdr.dt_rjc2 = '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Rejected')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.dt_rjc != '1900-01-01 00:00:00.000' or pr_hdr.dt_rjc2 != '1900-01-01 00:00:00.000'";
            }

            if ($statusOP=='Open')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.stat = 'O'";
            }
            if ($statusOP=='Close')
            {
                $sqlWhere = $sqlWhere . "and pr_hdr.stat = 'C'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('pr_hdr')
                                ->selectRaw("
                                pr_hdr.office_id, 
                                pr_hdr.pr_id, 
                                pr_hdr.dt_pr, 
                                pr_hdr.dept_id, 
                                branch_office.office, 
                                branch_office.address, 
                                branch_office.city, 
                                department.dept_name, 
                                pr_hdr.qty, 
                                pr_hdr.pic_id, 
                                pr_hdr.wgt, 
                                pr_hdr.pr_num, 
                                pr_hdr.stat, 
                                pr_hdr.memo_txt, 
                                pr_hdr.dt_created, 
                                pr_hdr.user_id, 
                                pr_hdr.dt_aprv, 
                                pr_hdr.dt_aprv2, 
                                pr_hdr.aprv_by, 
                                pr_hdr.aprv2_by, 
                                pr_hdr.tr_type, 
                                pr_hdr.rjc_by, 
                                pr_hdr.dt_rjc,
                                pr_hdr.rjc2_by, 
                                pr_hdr.dt_rjc2,
                                trim(site_cluster.cluster_name) as cluster_name,
                                trim(house_unit.house_name) as house_name,
                                trim(site.site_name) as site_name,
                                vendor.vendor_id,
                                trim(vendor.vendor_name) as vendor_name,
                                pr_hdr.type_pr,
                                ISNULL(vendor.apprv,0) as apprv
                                ")
                                ->leftjoin('branch_office', 'pr_hdr.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('department', 'pr_hdr.dept_id', '=', 'department.dept_id')
                                ->leftjoin('site_cluster', 'pr_hdr.id_cluster', '=', 'site_cluster.id_cluster')
                                ->leftjoin('house_unit', 'pr_hdr.id_house', '=', 'house_unit.id_house')
                                ->leftjoin('site', 'pr_hdr.id_site', '=', 'site.id_site')
                                ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')
                                ->whereRaw($sqlWhere)
                                ->orderBy('pr_hdr.dt_created', 'desc')
                                ->get();

            // foreach ($datas as $item) {
            //     $apprvVendor = (int) $item->apprv;

            //     if ($apprvVendor === 0) {
            //         $confValue = DB::connection("sqlSrvBCKI")
            //                         ->table('sec_env_conf')
            //                         ->where('var_id', 'APRVPRNUM') // karena ini PR
            //                         ->value('var_value');

            //         $item->apprv = (int) $confValue;
            //     }
            // }
            return $this->sendResponse($datas, 'Successfully get purchase request');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }

    public function getDetailPR(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
                ->table('pr_item')
                ->selectRaw("
                ROW_NUMBER() OVER (ORDER BY pr_item.pr_id) AS no,
                pr_item.pr_item,
                pr_item.pr_id, 	
                pr_item.article_id, 
                article.description, 
                pr_item.qty, 
                pr_item.unit_meas, 	
                pr_item.remark, 
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
                ->where('pr_item.pr_id', $request->pr_id)
                ->get();

        return $this->sendResponse($datas, 'Successfully get PR Detail');

    }
    
    public function getDepartment(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                                ->table('department')
                                ->selectRaw("
                                TRIM(dept_id) as dept_id, TRIM(dept_name) as dept_name, office_id
                                ")
                                ->get();

            return $this->sendResponse($datas, 'Successfully get Department');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }

    public function approvePR1(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {

            $order = DB::connection('sqlSrvBCKI')
                    ->table('pr_hdr')
                    ->selectRaw('pr_hdr.*, ISNULL(vendor.apprv, 0) as apprv')
                    ->where('pr_id', $request->pr_id)
                    ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')                    
                    ->first();
            $dept_id = trim($order->dept_id);
            $vendor_apprv = trim($order->apprv);
            // dd($vendor_apprv);

            if ($order) {
                if ($order->dt_aprv == '1900-01-01 00:00:00.000') {
                    if($vendor_apprv == 0){
                        $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
                            ->update([
                                'dt_aprv' => now(),
                                'dt_modified' => now(),
                                'aprv_by' => getAuth($request)->user_id2
                            ]);
                        $whatsapp = $this->sendNotifPurchase('PR',$request->pr_id,$dept_id);
                    }
                    if($vendor_apprv == 1){
                        $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
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
                            ->where('pr_id', $request->pr_id)
                            ->update([
                                'dt_aprv' => now(),
                                'dt_modified' => now(),
                                'aprv_by' => getAuth($request)->user_id2
                            ]);
                        $whatsapp = $this->sendNotifPurchase('PR',$request->pr_id,$dept_id);
                    }

                                        
                    return $this->sendResponse('Success Approved', 'Successfully saved');
                    // DB::connection('sqlSrvBCKI')->commit();
                    
                    
/* ============================================= get id emp ============================================= */
                // $getEmp = DB::connection('sqlSrvBCKI')
                // ->table('department')
                // ->selectRaw('nik_pr2')
                // ->whereRaw("dept_id = '" . $dept_id . "'")
                // ->get(); 
                // dd($getEmp);
/* ============================================= get phone ============================================= */
                    // foreach ($getEmp as $data1) {
                    //     $idemployee = $data1->user_id;

                    //     if (!empty($idemployee))
                    //     {
                    //         $sqlWhere = "idemployee = '" . $idemployee . "'";
                    //     }
                    //     $getPhone = DB::connection('sqlsrvHrd')
                    //                     ->table('employee')
                    //                     ->selectRaw('phone')
                    //                     ->whereRaw($sqlWhere)
                    //                     ->get(); 

/* ============================================= send WA ============================================= */
                    //     foreach ($getPhone as $data2) {
                    //         $phone = $data2->phone;
                    //     }

                    //     $message = 'Silahkan lakukan Approval 2 untuk Purchase Request Nomor : ' . $request->pr_id . '\n' . 'Silahkan login pada https://viva.kencana.org/ untuk Approval 2. Terima kasih '; 
                    //     $url = 'https://notifapi.com/send_message';

                    //     $data = array(
                    //         "phone_no" => $phone,
                    //         "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
                    //         "message"  => $message
                    //     );

                    //     $data_string = json_encode($data,1);

                    //     $ch = curl_init($url);
                    //         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    //         curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    //         curl_setopt($ch, CURLOPT_VERBOSE, 0);
                    //         curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                    //         curl_setopt($ch, CURLOPT_TIMEOUT, 360);
                    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    //         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    //         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    //             'Content-Type: application/json',
                    //             'Content-Length: ' . strlen($data_string),
                    //             'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
                    //         ));

                    //     $res = curl_exec($ch);
                    //     curl_close($ch);

                    //     if($res == 'Success' || $res == 'success' ){
                    //         $stat = 1;
                    //         $msg = $res;
                    //     } else {
                    //         $stat = 0;
                    //         $msg = $res;
                    //     }

                    //     $dataRes = ['stat' => $stat, 'msg' => $msg];

                    // }

                    
                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
                
                
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
        
    }

    public function approvePR2(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {

            $order = DB::connection('sqlSrvBCKI')
                    ->table('pr_hdr')
                    ->selectRaw('pr_hdr.*, ISNULL(vendor.apprv, 0) as apprv')
                    ->where('pr_id', $request->pr_id)
                    ->leftjoin('vendor', 'pr_hdr.vendor_id', '=', 'vendor.vendor_id')                    
                    ->first();
            $vendor_apprv = trim($order->apprv);

            if ($order) {

                if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                    if($vendor_apprv == 0){
                        $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
                            ->update([
                                'dt_aprv2' => now(),
                                'dt_modified' => now(),
                                'aprv2_by' => getAuth($request)->user_id2
                            ]);
                    }
                    if($vendor_apprv == 2){
                        $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
                            ->update([
                                'dt_aprv2' => now(),
                                'dt_modified' => now(),
                                'aprv2_by' => getAuth($request)->user_id2
                            ]);
                    }
                    // DB::connection('sqlSrvBCKI')->commit();

                    return $this->sendResponse('Success Approved', 'Successfully saved');

                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
                
                
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }        
    }

    public function unApprovePR1(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {
                        
            $order = DB::connection('sqlSrvBCKI')
                    ->table('pr_hdr')
                    ->where('pr_id', $request->pr_id)
                    ->first();
            
            if ($order) {
                if ($order->dt_rjc == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
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
                            ->where('pr_id', $request->pr_id)
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
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function unApprovePR2(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {
                        
            $order = DB::connection('sqlSrvBCKI')
                    ->table('pr_hdr')
                    ->where('pr_id', $request->pr_id)
                    ->first();
            
            if ($order) {
                if ($order->dt_rjc2 == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('pr_hdr')
                            ->where('pr_id', $request->pr_id)
                            ->update([
                                'dt_rjc' => now(),
                                'dt_rjc2' => now(),
                                'dt_modified' => now(),
                                'rjc_by' => getAuth($request)->user_id2,
                                'rjc2_by' => getAuth($request)->user_id2,
                                'stat' => 'X'
                            ]);

                    // DB::connection('sqlSrvBCKI')->commit();
                    $save2 = DB::connection('sqlSrvBCKI')
                            ->table('pr_item')
                            ->where('pr_id', $request->pr_id)
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
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function getPO(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $dept_id = $request->dept_id;
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
                    $sqlWhere .= " and po_hdr.dt_po BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                }
                else
                {
                    $sqlWhere .= " and po_hdr.dt_po BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
                }
            }


            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and po_hdr.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($dept_id))
            {
                $sqlWhere = $sqlWhere . "and po_hdr.dept_id = ". "'" . $dept_id . "'";
            }

            if ($aprv=='All')
            {
                $sqlWhere = $sqlWhere;
            }
            if ($aprv=='Approve 1')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.dt_aprv != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Approve 2')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.dt_aprv2 != '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Not Approve')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.dt_aprv = '1900-01-01 00:00:00.000' and po_hdr.dt_aprv2 = '1900-01-01 00:00:00.000'";
            }
            if ($aprv=='Rejected')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.dt_rjc != '1900-01-01 00:00:00.000'";
            }

            if ($statusOP=='Open')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.stat = 'O'";
            }
            if ($statusOP=='Close')
            {
                $sqlWhere = $sqlWhere . "and po_hdr.stat = 'C'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('po_hdr')
                                ->selectRaw("
                                branch_office.office, 
                                po_hdr.po_id, 
                                po_hdr.pic_id, 
                                po_hdr.vendor_id, 
                                po_hdr.office_id, 
                                po_hdr.dt_po, 
                                vendor.vendor_name, 
                                po_hdr.amt_subtotal,
                                po_hdr.amt_disc, 
                                po_hdr.amt_net, 
                                po_hdr.amt_ppn, 
                                CONCAT(ROUND(po_hdr.ppn,1),' %') as ppn_persen, 
                                po_hdr.ppn, 
                                po_hdr.stat, 
                                po_hdr.memo_txt, 
                                po_hdr.rjc2_by, 
                                po_hdr.dt_rjc2, 
                                po_hdr.aprv2_by, 
                                po_hdr.dt_aprv2, 
                                po_hdr.dt_modified, 
                                po_hdr.dt_created, 
                                po_hdr.aprv_by, 
                                po_hdr.dt_aprv, 
                                po_hdr.rjc_by, 
                                po_hdr.dt_rjc, 
                                po_hdr.qty, 
                                po_hdr.pay_term_id, 
                                po_hdr.pr_id, 
                                department.dept_name
                                ")
                                ->leftjoin('vendor', 'po_hdr.vendor_id', '=', 'vendor.vendor_id')
                                ->leftjoin('branch_office', 'po_hdr.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('department', 'po_hdr.dept_id', '=', 'department.dept_id')
                                ->whereRaw($sqlWhere)
                                ->orderBy('po_hdr.dt_po', 'desc')
                                ->get();

            return $this->sendResponse($datas, 'Successfully get purchase request');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }

    public function getDetailPO(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
                ->table('po_item')
                ->selectRaw("
                ROW_NUMBER() OVER (ORDER BY po_item.po_id) AS no,
                branch_office.office, 
                po_item.po_item, 
                po_item.po_id, 
                article.description, 
                po_item.qty, 
                po_item.qty as qty_oh,
                po_item.qty - po_item.qty_rcv as qty_max,
                po_item.qty - po_item.qty_rcv as qty_req,
                po_item.qty_rcv,
                po_item.article_id, 
                po_item.stat, 
                po_item.amt_net, 
                po_item.unit_meas, 
                po_item.unit_price, 
                po_item.remark,
                po_item.remark2,
                po_hdr.dt_aprv,
                po_hdr.dt_aprv2
                ")
                ->leftJoin('branch_office', 'po_item.office_id', '=', 'branch_office.office_id')
                ->leftJoin('article', 'po_item.article_id', '=', 'article.article_id')
                ->leftJoin('po_hdr', 'po_hdr.po_id', '=', 'po_item.po_id')
                // ->leftJoin('pr_item', function($join) {
                //     $join->on('po_item.pr_id', '=', 'pr_item.pr_id')
                //     ->on('po_item.article_id', '=', 'pr_item.article_id');
                // })
                ->where('po_item.po_id', $request->po_id)
                ->where('po_item.stat', '!=', 'C')
                ->get();

        return $this->sendResponse($datas, 'Successfully get PR Detail');

    }

    public function approvePO1(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {

            $order = DB::connection('sqlSrvBCKI')
                    ->table('po_hdr')
                    ->where('po_id', $request->po_id)
                    ->first();
            // $dept_id = trim($order->dept_id);
            $dept_id = '';

            if ($order) {

                if ($order->dt_aprv == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $request->po_id)
                            ->update([
                                'dt_aprv' => now(),
                                'dt_modified' => now(),
                                'aprv_by' => getAuth($request)->user_id2
                                // 'dt_aprv2' => now(),
                                // 'aprv2_by' => getAuth($request)->user_id2
                            ]);
                    // DB::connection('sqlSrvBCKI')->commit();

                    // $whatsapp = $this->sendNotifPurchase('PO',$request->po_id,$dept_id); //jika po pakai wa blast di uncomment
                    
/* ============================================= get id emp ============================================= */
                    // $getEmp = DB::table('sec_env_conf')
                    //             ->selectRaw('user_id')
                    //             ->whereRaw("entity_id = 'KRI' AND appl_id = 'PURCHASING' AND var_id = 'APPROVAL2'")
                    //             ->get(); 

/* ============================================= get phone ============================================= */
                    // foreach ($getEmp as $data1) {
                    //     $idemployee = $data1->user_id;

                    //     if (!empty($idemployee)){
                    //         $sqlWhere = "idemployee = '" . $idemployee . "'";
                    //     }

                    //     $getPhone = DB::connection('sqlsrvHrd')
                    //                 ->table('employee')
                    //                 ->selectRaw('phone')
                    //                 ->whereRaw($sqlWhere)
                    //                 ->get(); 

/* ============================================= send WA ============================================= */
                    //     foreach ($getPhone as $data2) {
                    //         $phone = $data2->phone;
                    //     }

                    //     $message = 'Silahkan lakukan Approval 2 untuk Purchase Order Nomor : ' . $request->po_id . '\n' . 'Silahkan login pada https://viva.kencana.org/ untuk Approval 2. Terima kasih '; 
                    //     $url = 'https://notifapi.com/send_message';

                    //     $data = array(
                    //     "phone_no" => $phone,
                    //     "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
                    //     "message"  => $message
                    //     );

                    //     $data_string = json_encode($data,1);

                    //     $ch = curl_init($url);
                    //     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    //     curl_setopt($ch, CURLOPT_VERBOSE, 0);
                    //     curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                    //     curl_setopt($ch, CURLOPT_TIMEOUT, 360);
                    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    //         'Content-Type: application/json',
                    //         'Content-Length: ' . strlen($data_string),
                    //         'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
                    //     ));

                    //     $res = curl_exec($ch);
                    //     curl_close($ch);

                    //     if($res == 'Success' || $res == 'success' ){
                    //         $stat = 1;
                    //         $msg = $res;
                    //     } else {
                    //         $stat = 0;
                    //         $msg = $res;
                    //     }
                    //     $dataRes = ['stat' => $stat, 'msg' => $msg];
                    // }

                    return $this->sendResponse('Success Approved', 'Successfully saved');

                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
                
                
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
        
    }

    public function approvePO2(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {

            $order = DB::connection('sqlSrvBCKI')
                    ->table('po_hdr')
                    ->where('po_id', $request->po_id)
                    ->first();

            if ($order) {

                if ($order->dt_aprv2 == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $request->po_id)
                            ->update([
                                'dt_aprv2' => now(),
                                'dt_modified' => now(),
                                'aprv2_by' => getAuth($request)->user_id2
                            ]);

                    // DB::connection('sqlSrvBCKI')->commit();

                    return $this->sendResponse('Success Approved', 'Successfully saved');

                } else {
                    return $this->sendError('No Order sudah di setujui, please reload page', 'Error', 500);
                }
                
                
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }        
    }

    public function unApprovePO1(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {
            $order = DB::connection('sqlSrvBCKI')
                    ->table('po_hdr')
                    ->where('po_id', $request->po_id)
                    ->first();
            
            if ($order) {
                if ($order->dt_rjc == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $request->po_id)
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
                            ->where('po_id', $request->po_id)
                            ->update([
                                'dt_modified' => Carbon::now(),
                                'stat' => 'X',
                                'remark2' => $request->remark2 ?? ''
                            ]);

                    // DB::connection('sqlSrvBCKI')->commit();

                    return $this->sendResponse('Success UnApproved 1', 'Successfully saved');

                } else {
                    return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                }
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function unApprovePO2(Request $request)
    {
        // DB::connection('sqlSrvBCKI')->beginTransaction();

        // try {
            $order = DB::connection('sqlSrvBCKI')
                    ->table('po_hdr')
                    ->where('po_id', $request->po_id)
                    ->first();
            
            if ($order) {
                if ($order->dt_rjc2 == '1900-01-01 00:00:00.000') {
                    $save = DB::connection('sqlSrvBCKI')
                            ->table('po_hdr')
                            ->where('po_id', $request->po_id)
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
                            ->where('po_id', $request->po_id)
                            ->update([
                                'dt_modified' => Carbon::now(),
                                'stat' => 'X',
                                'remark2' => $request->remark2 ?? ''
                            ]);                    
                    // DB::connection('sqlSrvBCKI')->commit();

                    return $this->sendResponse('Success UnApproved 1', 'Successfully saved');

                } else {
                    return $this->sendError('No Order sudah di unapprove 1, please reload page', 'Error', 500);
                }
            } else {
                return $this->sendError('No Order tidak ditemukan', 'Error', 500);
            }           
            
        // }
        // catch (\Exception $e) {
        //     DB::connection('sqlSrvBCKI')->rollBack();
        //     return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function sendNotifPurchase($type,$po_id,$dept_id)
    {
        try {
            if($type=='PO'){
                $type_name = "*_Purchase Order_*";
            }elseif($type=='PR'){
                $type_name = "*_Purchase Request_*";
            }
            

/* ============================================= get id emp ============================================= */
            // $getEmp = DB::table('sec_env_conf')
            //         ->selectRaw('user_id')
            //         ->whereRaw("entity_id = 'KRI' AND appl_id = 'PURCHASING' AND var_id = 'APPROVAL2'")
            //         ->get();
            $getEmp = ''; 
            if($type=='PR'){
                $getEmp = DB::connection('sqlSrvBCKI')
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

                // $message = '*BANGUN CIPTA KARYA INDONESIA*'.'\n\n'.'Silahkan lakukan Approval 2 untuk '.$type_name.' Nomor : ' . $po_id . '\n' . 'Silahkan login pada https://viva.kencana.org/approve untuk Approval 2. Terima kasih '; 
                // /https://viva.kencana.org/approve/09210216/VIVA23081509mardian4673958770904091
                $getLink = "bcki" . Carbon::now()->format('ymdH') . rand(10000, 99999);
                DB::connection('sqlsrv')
                ->table('sec_reset_password')
                ->insert([
                    'user_id2' => $getEmp ? $getEmp : '',
                    'link' => $getLink,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $message = "*BANGUN CIPTA KARYA INDONESIA*\n\n" .
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

            // }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getConfAprv(Request $request)
    {
        try {
            $sqlWhere = "1=1";
            $appAprv = $request->appAprv;
            if($appAprv)
            {
                if ($request->appAprv=='PR')
                {
                    $sqlWhere = " sec_env_conf.var_id = 'APRVPRNUM' ";
                }
                if ($request->appAprv=='PO')
                {
                    $sqlWhere = " sec_env_conf.var_id = 'APRVPONUM' ";
                }
                if( $request->appAprv=='BON')
                {
                    $sqlWhere = " sec_env_conf.var_id = 'BON' ";
                }
            }
            $datas = DB::connection("sqlSrvBCKI")
                                ->table('sec_env_conf')
                                ->selectRaw("
                                    sec_env_conf.var_value
                                ")
                                ->whereRaw($sqlWhere)
                                ->Value('sec_env_conf.var_value');

            return $this->sendResponse($datas, 'sec_env_conf');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }   
    }

}
