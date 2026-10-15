<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\MasterFPI;
use App\Services\PerhitunganIncludeService;

class PaymentRequestController extends Controller
{
    
    use MasterFPI;

    protected $ServiceInclude;

    public function __construct(PerhitunganIncludeService $ServiceInclude)
    {
        $this->ServiceInclude = $ServiceInclude;
    }
    
    public function initPaymentRequest(Request $request)
    {
        $user_id2 = getAuth($request)->user_id2;

        $Permit1 = $this->fetchEnvConf('FINANCE','PERMIT1','Y',$user_id2);
        $Permit2 = $this->fetchEnvConf('FINANCE','PERMIT2','Y',$user_id2);
        $GOfficeId = $this->fetchEnvConf('FINANCE','OFFICEID','Y',$user_id2);
        $GOffice = $this->getOffice("");
        $GVendor = $this->getVendor("");

        return response()->json([
            'message' => 'ok', 
            'permit1' => $Permit1,
            'permit2' => $Permit2,
            'office_id' => $GOfficeId,
            'office' => $GOffice,
            'vendor' => $GVendor,
            'status_code' => 200
        ], 200);
    }

    public function getListInv(Request $request)
    {
        try
        {
            $sqlWhere = "(a.amt_total-a.amt_paid) > 0 AND a.pay_req_flag = 'N' ";
        
            if ($request->office_id) {
                $sqlWhere .= "and a.office_id = '$request->office_id'";
            }
            if ($request->vendor_id) {
                $sqlWhere .= "and a.vendor_id = '$request->vendor_id'";
            }
            if ($request->start_date) {
                if($request->end_date){
                    $sqlWhere .= "and a.dt_inv between '" . $request->start_date . " 00:00:00' and '" . $request->end_date . " 23:59:59' ";
                }else{
                    $sqlWhere .= "and a.dt_inv = '$request->start_date' ";
                }
            }
            if($request->permit1=='A') {
                $sqlWhere .= "and a.dt_permit1 != '1900-01-01 00:00:00.000'";
            }else if($request->permit1=='N') {
                $sqlWhere .= "and a.dt_permit1 = '1900-01-01 00:00:00.000'";
            }
            if($request->permit2=='A') {
                $sqlWhere .= "and a.dt_permit2 != '1900-01-01 00:00:00.000'";
            }else if($request->permit2=='N') {
                $sqlWhere .= "and a.dt_permit2 = '1900-01-01 00:00:00.000'";
            }
            
            $inv_list = DB::connection("sqlSrvBCKI")
                ->table('purc_inv_mast as a')
                ->selectRaw("
                            a.office_id,
                            TRIM(a.tr_id) AS tr_id,
                            TRIM(a.inv_id) AS inv_id,
                            TRIM(a.vendor_id) AS vendor_id,
                            a.dt_inv,
                            a.dt_ship,
                            TRIM(a.pay_term_id) AS pay_term_id,
                            TRIM(a.curr_id) AS curr_id,
                            a.rate,
                            a.lpb_rate,
                            a.amt_subtotal,
                            a.pct_disc,
                            a.amt_disc,
                            a.amt_ppn,
                            a.amt_pph,
                            a.amt_total,
                            TRIM(a.inv_said) AS inv_said,
                            a.amt_retur,
                            a.amt_paid,
                            a.amt_paid_disc,
                            a.amt_refund,
                            a.ppn,
                            a.amt_pph4,
                            a.amt_pph21,
                            a.inv_type,
                            a.remark,
                            TRIM(b.office) as office,
                            TRIM(c.vendor_name) as vendor_name,
                            TRIM(d.pay_term_desc) as pay_term_desc,
                            a.dt_permit1,
                            a.permit1_by,
                            a.dt_permit2,
                            a.permit2_by,
                            hrdp1.name as permit1_name,
                            hrdp2.name as permit2_name,
                            CASE 
                                when a.dt_permit1 != '1900-01-01 00:00:00.000' then CONCAT(FORMAT(a.dt_permit1,'dd-MM-yyyy HH:MM'), ' by ', hrdp1.name) 
                                else '-' 
                            END as w_permit1,
                            CASE 
                                when a.dt_permit2 != '1900-01-01 00:00:00.000' then CONCAT(FORMAT(a.dt_permit2,'dd-MM-yyyy HH:MM'), ' by ', hrdp2.name) 
                                else '-' 
                            END as w_permit2
                            ")
                ->join('branch_office as b', 'a.office_id', '=', 'b.office_id')
                ->leftJoin('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
                ->leftJoin('pay_term as d', 'a.pay_term_id', '=', 'd.pay_term_id')
                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdp1'), 'hrdp1.idemployee', '=', 'a.permit1_by')
                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrdp2'), 'hrdp2.idemployee', '=', 'a.permit2_by')
                ->whereRaw($sqlWhere)
                ->orderBy('a.dt_inv', 'asc')
                ->get();

            return response()->json([
                'data' => $inv_list,
                'status_code' => 200
            ], 200);
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getListInvPrint(Request $request)
    {
        try
        {
            $sqlWhere = "a.print_flag = 'Y' ";
        
            if ($request->PoId) {
                $sqlWhere .= "and a.po_id = '$request->PoId'";
            }
            if ($request->OfficeId) {
                $sqlWhere .= "and a.office_id = '$request->OfficeId'";
            }
            if ($request->VendorId) {
                $sqlWhere .= "and a.vendor_id = '$request->VendorId'";
            }
            if ($request->Stat) {
                $sqlWhere .= "and a.stat = '$request->Stat'";
            }
            if ($request->dtawal) {
                if($request->dtakhir){
                    $sqlWhere .= "and a.dt_po between '" . $request->dtawal . " 00:00:00' and '" . $request->dtakhir . " 23:59:59' ";
                }else{
                    $sqlWhere .= "and a.dt_po = '$request->dtawal' ";
                }
            }
            
            $inv_list = DB::connection("sqlSrvBCKI")
                ->table('payment_request_print as a')
                ->selectRaw("
                            a.tr_id,
                            STRING_AGG(a.inv_id, ' , ') AS inv_ids,
                            a.print_by,
                            FORMAT(dt_print,'dd-MM-yyyy HH:MM') as dt_print,
                            hrded.name as name
                            ")
                ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrded'), 'hrded.idemployee', '=', 'a.print_by')
                ->whereRaw($sqlWhere)
                ->groupByRaw("a.tr_id, a.print_by,FORMAT(dt_print,'dd-MM-yyyy HH:MM'), hrded.name")
                ->get();

            return response()->json([
                'data' => $inv_list,
                'status_code' => 200
            ], 200);
     
        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }

    public function getDetailListInv(Request $request)
    {
        try
        {
            $inv_list = collect();

            foreach($request->inv_list as $inv){
                $data_detil = DB::connection("sqlSrvBCKI")
                            ->table('purc_inv_item as a')
                            ->selectRaw("
                                        a.office_id,
                                        TRIM(a.tr_id) AS tr_id,
                                        a.seq_num,
                                        TRIM(a.inv_id) AS inv_id,
                                        a.inv_item,
                                        TRIM(a.sj_no) AS sj_no,
                                        a.sj_item,
                                        TRIM(a.po_id) AS po_id,
                                        a.po_item,
                                        TRIM(a.pr_id) AS pr_id,
                                        a.pr_item,
                                        TRIM(a.contr_id) AS contr_id,
                                        a.contr_item,
                                        a.article_id,
                                        a.qty,
                                        TRIM(a.unit_meas) AS unit_meas,
                                        a.wgt_unit,
                                        a.wgt,
                                        a.um_price,
                                        a.unit_price,
                                        a.amt_item,
                                        a.inv_rate,
                                        a.lpb_rate,
                                        TRIM(a.ac_num_bbt) AS ac_num_bbt,
                                        TRIM(a.ac_num_diff_rate) AS ac_num_diff_rate,
                                        a.consume_able,
                                        TRIM(a.ac_num_exp) AS ac_num_exp,
                                        b.memo_txt as 'pr_note',
                                        c.memo_txt as 'po_note',
                                        d.description as 'article_desc'
                                        ")
                            ->join('pr_hdr as b', function($join) {
                                $join->on('a.pr_id', '=', 'b.pr_id');
                            })
                            ->leftjoin('po_hdr as c', function($join) {
                                $join->on('a.po_id', '=', 'c.po_id');
                            })
                            ->join('article as d', function($join) {
                                $join->on('a.article_id', '=', 'd.article_id');
                            })
                            ->where('inv_id', $inv)
                            ->get();

                $inv_list = $inv_list->merge($data_detil); // gabungkan hasil tiap loop
            }
                    
            return response()->json([
                'data' => $inv_list,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }
    
    private function generatePrintId()
    {
        $dateMonth = date("m");
        $dateNow = date("y").date("m");

        $gen_cluster_id = '';

        
       $getLastTrID = DB::connection('sqlSrvBCKI')
                            ->table('payment_request_print')
                            ->select(DB::raw('TRIM(tr_id) as tr_id'))
                            ->where('tr_id', 'like', '%' . $dateNow . '%')
                            ->orderBy('tr_id', 'desc')
                            ->value('tr_id');
        
        if ($getLastTrID) {
            $month = substr(trim($getLastTrID), 3, 2);

            if ($month != $dateMonth) {
                $gen_cluster_id = 'P' . $dateNow . '001';
            } else {
                $seq = substr(trim($getLastTrID), -3) + 1;
                $gen_cluster_id = 'P' . $dateNow . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        } else {
            $gen_cluster_id = 'P' . $dateNow . '001';
        }
        return $gen_cluster_id;
    }

    public function getDokumenCetakInvoice(Request $request)
    {
        try
        {
            $inv_list = collect();
            $total_amt_total = 0;
            $total_amt_paid = 0;
            $total_amt_ppn = 0;
            $total_amt_pph = 0;

            foreach ($request->inv_list as $inv) {
                $data_office = DB::connection("sqlSrvBCKI")
                    ->table('purc_inv_mast as a')
                    ->selectRaw("
                        a.office_id,
                        TRIM(b.office) as office,
                        TRIM(b.address) as address,
                        TRIM(b.city) as city,
                        TRIM(b.zip_code) as zip_code,
                        TRIM(b.tlp) as tlp,
                        TRIM(b.e_mail) as e_mail
                    ")
                    ->join('branch_office as b', 'a.office_id', '=', 'b.office_id')
                    ->where('inv_id', $inv)
                    ->first();

                $data_header = DB::connection("sqlSrvBCKI")
                    ->table('purc_inv_mast as a')
                    ->selectRaw("
                        a.office_id,
                        TRIM(a.tr_id) AS tr_id,
                        TRIM(a.inv_id) AS inv_id,
                        TRIM(a.vendor_id) AS vendor_id,
                        a.dt_inv,
                        a.dt_ship,
                        TRIM(a.pay_term_id) AS pay_term_id,
                        TRIM(a.curr_id) AS curr_id,
                        a.rate,
                        a.lpb_rate,
                        a.amt_subtotal,
                        a.pct_disc,
                        a.amt_disc,
                        a.amt_ppn,
                        a.amt_pph,
                        a.amt_total,
                        TRIM(a.inv_said) AS inv_said,
                        a.amt_retur,
                        a.amt_paid,
                        a.amt_paid_disc,
                        a.amt_refund,
                        a.ppn,
                        a.amt_pph4,
                        a.amt_pph21,
                        a.inv_type,
                        b.office,
                        c.vendor_name,
                        d.pay_term_desc
                    ")
                    ->join('branch_office as b', 'a.office_id', '=', 'b.office_id')
                    ->leftJoin('vendor as c', 'a.vendor_id', '=', 'c.vendor_id')
                    ->leftJoin('pay_term as d', 'a.pay_term_id', '=', 'd.pay_term_id')
                    ->where('inv_id', $inv)
                    ->first();

                $data_detil = DB::connection("sqlSrvBCKI")
                    ->table('purc_inv_item as a')
                    ->selectRaw("
                        a.office_id,
                        TRIM(a.tr_id) AS tr_id,
                        a.seq_num,
                        TRIM(a.inv_id) AS inv_id,
                        a.inv_item,
                        TRIM(a.sj_no) AS sj_no,
                        a.sj_item,
                        TRIM(a.po_id) AS po_id,
                        a.po_item,
                        TRIM(a.pr_id) AS pr_id,
                        a.pr_item,
                        TRIM(a.contr_id) AS contr_id,
                        a.contr_item,
                        a.article_id,
                        a.qty,
                        TRIM(a.unit_meas) AS unit_meas,
                        a.wgt_unit,
                        a.wgt,
                        a.um_price,
                        a.unit_price,
                        a.amt_item,
                        a.inv_rate,
                        a.lpb_rate,
                        TRIM(a.ac_num_bbt) AS ac_num_bbt,
                        TRIM(a.ac_num_diff_rate) AS ac_num_diff_rate,
                        a.consume_able,
                        TRIM(a.ac_num_exp) AS ac_num_exp,
                        b.memo_txt as pr_note,
                        c.memo_txt as po_note,
                        d.description as article_desc
                    ")
                    ->join('pr_hdr as b', function($join) {
                        $join->on('a.pr_id', '=', 'b.pr_id');
                    })
                    ->leftjoin('po_hdr as c', function($join) {
                        $join->on('a.po_id', '=', 'c.po_id');
                    })
                    ->join('article as d', function($join) {
                        $join->on('a.article_id', '=', 'd.article_id');
                    })
                    ->where('a.inv_id', $inv)
                    ->get();

                    // Gabungkan jadi satu objek
                if ($data_header) {
                    $total_amt_total += floatval($data_header->amt_total);
                    $total_amt_paid  += floatval($data_header->amt_paid);
                    $total_amt_pph += floatval($data_header->amt_ppn);
                    $total_amt_ppn  += floatval($data_header->amt_pph);

                    $data_header->details = $data_detil;
                    $inv_list->push($data_header);
                }
                
            }
                    
            return response()->json([
                'data' => $inv_list,
                'office' => $data_office,
                'total_amt_total' => $total_amt_total,
                'total_amt_paid' => $total_amt_paid,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            return $this->sendError('Validation Error', $e->getMessage());
        }   
    }
    
    public function kunciCetakInvoice(Request $request)
    {
        DB::beginTransaction();

        try {            
            $all_success1 = true;
            $all_success2 = true;
            $all_success3 = true;
            $noPrint = $this->generatePrintId();
            // $last3 = intval(substr($noPo, -3));
            // dd($noPrint);
            foreach ($request->inv_list as $detail) {
                
                $updateLock = $this->lockUnlockInvoice($detail, getAuth($request)->user_id2,'Y');
                $insertLock = $this->storePrintLock($noPrint, $detail, getAuth($request)->user_id2);
                $insertLog = $this->storeLogLock($noPrint, $detail, getAuth($request)->user_id2,'P');
                
                if (!$updateLock) {
                    $all_success1 = false; // kalau ada gagal, set false
                }
                if (!$insertLock) {
                    $all_success2 = false; // kalau ada gagal, set false
                }
                if (!$insertLog) {
                    $all_success3 = false; // kalau ada gagal, set false
                }
            }

            DB::commit();

            return response()->json([
                'po_id' => $noPrint,
                'lock' => $updateLock,
                'insert' => $insertLock,
                'log' => $insertLog,
                'status_code' => 200,
                'message' => 'Berhasil buat payment request'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function lockUnlockInvoice($noInv, $user_id2, $flag)
    {        
        try {
            $PoHdr = DB::connection("sqlSrvBCKI")
                ->table('purc_inv_mast')
                ->where('inv_id', $noInv)
                ->update([
                            'pay_req_flag' => $flag,
                            'pay_req_user' => $user_id2,
                            'dt_print' => now('Asia/Jakarta')->format('Y-m-d H:i:s')
                        ]);
            if ($PoHdr) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Gagal update print',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function storeLogLock($noPrint, $noInv, $user_id2, $action)
    {        
        try {
           
            $save_item = DB::connection('sqlSrvBCKI')
                    ->table('payment_request_print_log')
                    ->insert([
                        'tr_id' => $noPrint,
                        'inv_id' => $noInv,
                        'tr_date' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        'dt_print' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        'print_by' => $user_id2,
                        'action' => $action
                    ]);
      
            
            if ($save_item) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Ada log yang gagal disimpan',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function storePrintLock($noPrint, $noInv, $user_id2)
    {        
        try {
           
            $save_item = DB::connection('sqlSrvBCKI')
                    ->table('payment_request_print')
                    ->insert([
                        'tr_id' => $noPrint,
                        'inv_id' => $noInv,
                        'tr_date' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        'dt_print' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        'print_by' => $user_id2
                    ]);
      
            
            if ($save_item) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Ada log yang gagal disimpan',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function historyCetakInvoice(Request $request)
    {
        try {
            $tr_id = $request->tr_id ?? '';

            $query = DB::connection("sqlSrvBCKI")
                            ->table('payment_request_print as prp')
                            ->selectRaw("
                                        pii.office_id,
                                        TRIM(pii.tr_id) AS tr_id,
                                        pii.seq_num,
                                        TRIM(pii.inv_id) AS inv_id,
                                        pii.inv_item,
                                        TRIM(pii.sj_no) AS sj_no,
                                        pii.sj_item,
                                        TRIM(pii.po_id) AS po_id,
                                        pii.po_item,
                                        TRIM(pii.pr_id) AS pr_id,
                                        pii.pr_item,
                                        TRIM(pii.contr_id) AS contr_id,
                                        pii.contr_item,
                                        pii.article_id,
                                        pii.qty,
                                        TRIM(pii.unit_meas) AS unit_meas,
                                        pii.wgt_unit,
                                        pii.wgt,
                                        pii.um_price,
                                        pii.unit_price,
                                        pii.amt_item,
                                        pii.inv_rate,
                                        pii.lpb_rate,
                                        TRIM(pii.ac_num_bbt) AS ac_num_bbt,
                                        TRIM(pii.ac_num_diff_rate) AS ac_num_diff_rate,
                                        pii.consume_able,
                                        TRIM(pii.ac_num_exp) AS ac_num_exp,
                                        b.memo_txt as 'pr_note',
                                        c.memo_txt as 'po_note',
                                        d.description as 'article_desc'
                                        ")
                            ->leftJoin('purc_inv_item as pii', function ($join) {
                                $join->on('prp.inv_id', '=', 'pii.inv_id');
                            })
                            ->join('pr_hdr as b', function($join) {
                                $join->on('pii.pr_id', '=', 'b.pr_id');
                            })
                            ->leftjoin('po_hdr as c', function($join) {
                                $join->on('pii.po_id', '=', 'c.po_id');
                            })
                            ->join('article as d', function($join) {
                                $join->on('pii.article_id', '=', 'd.article_id');
                            });

            if (!empty($tr_id)) {
                $query->where('prp.tr_id', $tr_id);
            }
            $datas = $query->get();
            
            $total = DB::connection("sqlSrvBCKI")
                            ->table('payment_request_print as prp')
                            ->selectRaw("
                                        SUM(amt_subtotal) as amt_subtotal,
                                        SUM(amt_ppn) as amt_ppn,
                                        SUM(amt_pph) as amt_pph,
                                        SUM(amt_total) as amt_total,
                                        SUM(amt_paid) as amt_paid
                                        ")
                            ->leftJoin('purc_inv_mast as pim', function ($join) {
                                $join->on('prp.inv_id', '=', 'pim.inv_id');
                            });

            if (!empty($tr_id)) {
                $total->where('prp.tr_id', $tr_id);
            }
            $data_total = $total->get();

            $invIds = $datas->pluck('inv_id')->unique()->toArray();

            $logs = DB::connection("sqlSrvBCKI")
                            ->table('payment_request_print_log as a')
                            ->selectRaw("
                                        a.tr_id,
                                        a.inv_id,
                                        a.dt_print,
                                        a.action,
                                        CASE 
                                            WHEN a.action = 'P' THEN CONCAT('Invoice ', a.inv_id, ' amount ', pim.amt_total, ' Printed at ' , FORMAT(a.dt_print,'dd-MM-yyyy') , ' by ' , hrded.name )
                                            WHEN a.action = 'R' THEN CONCAT('Invoice ', a.inv_id, ' amount ', pim.amt_total, ' Re-Printed at ' , FORMAT(a.dt_print,'dd-MM-yyyy') , ' by ' , hrded.name )
                                            WHEN a.action = 'D' THEN CONCAT('Invoice ', a.inv_id, ' amount ', pim.amt_total, ' Delete at ' , FORMAT(a.dt_print,'dd-MM-yyyy') , ' by ' , hrded.name )
                                            ELSE a.action 
                                        END AS action_desc
                                        ")
                            ->leftjoin(DB::raw('hrd_ol.dbo.employee as hrded'), 'hrded.idemployee', '=', 'a.print_by')
                            ->join('purc_inv_mast as pim', 'a.inv_id', '=', 'pim.inv_id')
                            ->whereIn('a.inv_id', $invIds)
                            ->orderBy('dt_print', 'desc') // opsional
                            ->get();

            return response()->json([
                'message'     => 'ok',
                'status_code' => 200,
                'data'        => $datas,
                'data_total'  => $data_total,
                'history'     => $logs,
            ], 200);
        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }
    
    public function hapusCetakInvoice(Request $request)
    {
        DB::beginTransaction();

        try {            
            $all_success1 = true;
            $all_success2 = true;
            $all_success3 = true;
            
            foreach ($request->tr_id as $detail) {
                $query = DB::connection("sqlSrvBCKI")
                                ->table('payment_request_print as prp')
                                ->selectRaw("prp.inv_id")
                                ->where('prp.tr_id', $detail)
                                ->get();
                
                foreach ($query as $detail2) {
                    $invId = $detail2->inv_id;
                    
                    $updateLock = $this->lockUnlockInvoice($invId, getAuth($request)->user_id2,'N');
                    $insertLock = $this->deletePrintLock($detail, getAuth($request)->user_id2, 'N');
                    $insertLog = $this->storeLogLock($detail, $invId, getAuth($request)->user_id2,'D');
                    
                    if (!$updateLock) {
                        $all_success1 = false; // kalau ada gagal, set false
                    }
                    if (!$insertLock) {
                        $all_success2 = false; // kalau ada gagal, set false
                    }
                    if (!$insertLog) {
                        $all_success3 = false; // kalau ada gagal, set false
                    }
                }
            }

            DB::commit();

            return response()->json([
                'lock' => $updateLock,
                'insert' => $insertLock,
                'log' => $insertLog,
                'status_code' => 200,
                'message' => 'Berhasil hapus payment request'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function deletePrintLock($noInv, $user_id2, $flag)
    {        
        try {
            $PoHdr = DB::connection("sqlSrvBCKI")
                ->table('payment_request_print')
                ->where('tr_id', $noInv)
                ->update([
                            'print_flag' => $flag,
                            'release_by' => $user_id2,
                            'dt_release' => now('Asia/Jakarta')->format('Y-m-d H:i:s')
                        ]);
            if ($PoHdr) {
                return response()->json([
                    'success' => true,
                    'status_code' => 200
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Gagal update print',
                    'status_code' => 403
                ], 403);
            }
        }catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function approvePermit(Request $request)
    {
        try {            
            if($request->permit_no == 1){
                $updatePermit = DB::connection("sqlSrvBCKI")
                    ->table('purc_inv_mast')
                    ->where('inv_id', $request->inv_id)
                    ->update([
                                'permit1_by' => getAuth($request)->user_id2,
                                'dt_permit1' => now('Asia/Jakarta')->format('Y-m-d H:i:s')
                            ]);
            }else if($request->permit_no == 2){
                $updatePermit = DB::connection("sqlSrvBCKI")
                    ->table('purc_inv_mast')
                    ->where('inv_id', $request->inv_id)
                    ->update([
                                'permit2_by' => getAuth($request)->user_id2,
                                'dt_permit2' => now('Asia/Jakarta')->format('Y-m-d H:i:s')
                            ]);
            }else{
                return response()->json([
                    'approve' => false,
                    'status_code' => 403,
                    'message' => 'Nomor permit tidak valid'
                ], 403);
            }

            return response()->json([
                'approve' => $updatePermit,
                'status_code' => 200,
                'message' => 'Berhasil approve permit payment request'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}