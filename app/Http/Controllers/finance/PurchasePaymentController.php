<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PurchasePaymentController extends Controller
{
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }
    public function getBankStatement(Request $request)
    {
        $sqlWhere = "1=1";

        $bank_acc = $request->bank_acc;
        $if_Posting = $request->if_posting;
        $tr_date_to = $request->tr_date_to;
        $tr_date_from = $request->tr_date_from;
        $tr_type = $request->tr_type;
        if (!$tr_date_to) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if (!$tr_date_from) {
            return $this->sendError("Empty transaction date", 'Error', 500);
        }
        if (trim($tr_type != 'X')) {
            if (empty($bank_acc)) {
                return $this->sendError("Empty bank account", 'Error', 500);
            }
            if (!empty($tr_type)) {
                $sqlWhere = $sqlWhere . "and bn.tr_type = " . "'" . $tr_type . "'";
            }
        }

        if (!empty($bank_acc)) {
            $sqlWhere = $sqlWhere . "and bn.bank_acc = " . "'" . $bank_acc . "'";
        }
        if ($tr_date_to) {
            $sqlWhere .= " AND CAST(bn.dt_trans AS DATE) <= CONVERT(DATE, '" . $tr_date_to . "')";
        }
        if ($tr_date_from) {
            $sqlWhere .= " AND CAST(bn.dt_trans AS DATE) >= CONVERT(DATE, '" . $tr_date_from . "')";
        }
        if (!empty($if_Posting)) {
            if (strtoupper($if_Posting) == 'Y') {
                $sqlWhere = $sqlWhere . "and bn.voucher >0";
            } else {
                $sqlWhere = $sqlWhere . "and bn.voucher = 0";
            }
        }

        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('bank_note as bn')
                ->selectRaw("
                    bn.Descr+'-'+bn.doc_id as Descr ,bn.cabang,bn.db_amt,bn.cr_amt,bn.saldo,bn.tr_id,bn.voucher,bn.run_number,bn.dt_trans,
                    bn.tr_type,bn.trx_mode,bn.tr_type,trim(bn.pay_id) pay_id,
                    trim(ac.bank_name) bank_name,br.branch_descr as cabang1,br.city,trim(ac.ac_num) ac_num,
                    case when pm.pay_id is not null then
                    'Y'
                    else
                    'N'
                    end as belongToTr,
                    bn.giro_num,
                    bn.kb_id
                ")
                ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                ->leftjoin('bank_branch as br', 'br.bank_branch_code', '=', 'bn.cabang')
                ->leftjoin('purc_pay_mast as pm', 'pm.pay_id', '=', 'bn.pay_id')
                ->whereRaw($sqlWhere)
                ->orderBy('bn.run_number', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetPurcPaymentList(Request $request)
    {
        $sqlWhere = "1=1 ";
        $sqlWhere1 = "1=1 ";
        $vendor_id = $request->vendor_id;
        $site_id = $request->site_id;
        $cluster_id = $request->cluster_id;
        $house_id = $request->house_id;
        $tr_date_from = $request->tr_date_from;
        $tr_date_to = $request->tr_date_to;
        if (!$this->isValidDate($tr_date_from)) {
            return $this->sendError("invalid transaction date", 'Error', 500);
        }
        if (!$this->isValidDate($tr_date_from)) {
            return $this->sendError("invalid transaction date", 'Error', 500);
        }

        if (!empty($vendor_id)) {
            $sqlWhere = $sqlWhere . " and a.vendor_id = " . "'" . $vendor_id . "'";
            $sqlWhere1 = $sqlWhere1 . " and a.vendor_id = " . "'" . $vendor_id . "'";
        }
        if ($tr_date_from) {
            $sqlWhere =  $sqlWhere . " and a.dt_pay  >= CONVERT(DATE, '" . $tr_date_from . "')";
            $sqlWhere1 =  $sqlWhere1 . " and a.dt_pay  >= CONVERT(DATE, '" . $tr_date_from . "')";
        }
        if ($tr_date_to) {
            $sqlWhere = $sqlWhere . " and a.dt_pay  <= CONVERT(DATE, '" . $tr_date_to . "')";
            $sqlWhere1 = $sqlWhere1 . " and a.dt_pay  <= CONVERT(DATE, '" . $tr_date_to . "')";
        }
        if (!empty($site_id)) {
            $sqlWhere .= " AND e.id_site = '$site_id'";
        }
        if (!empty($cluster_id)) {
            $sqlWhere .= " AND e.id_cluster ='$cluster_id'";
        }
        if (!empty($house_id)) {
            $sqlWhere .= " AND e.id_house ='$house_id'";
        }
        try {
            $query1 = DB::connection("sqlSrvBCKI")
                ->table('purc_pay_mast as a')
                ->selectRaw(
                    " trim(a.pay_id) pay_id, trim(b.vendor_name) vendor_name,a.dt_pay, a.pay_amt as total_pay_amt,  trim(a.remark) remark,
                 '' inv_id, '' dt_inv, a.pay_amt pay_amt, '' site_name,'' cluster_name,'' house_name,'MOTHER' mother_child,trim(a.pay_id) mother_id, a.ac_num_diff
                "
                )
                ->join('vendor as b', 'a.vendor_id', '=', 'b.vendor_id')
                ->whereRaw($sqlWhere1)
                ->whereIn('pay_id', function ($sub) use ($site_id, $cluster_id, $house_id) {
                    $sub = $sub->select('aa.pay_id')
                        ->from('purc_pay_mast as aa')
                        ->join('purc_pay_item as ac', 'aa.pay_id', '=', 'ac.pay_id')
                        ->join('purc_inv_item as ae', 'ac.inv_id', '=', 'ae.inv_id');
                    if ($site_id) {
                        $sub->where('ae.id_site', $site_id);
                    }
                    if ($cluster_id) {
                        $sub->where('ae.id_cluster', $cluster_id);
                    }
                    if ($house_id) {
                        $sub->where('ae.id_house', $house_id);
                    }
                });

            // ->when(!empty($site_id) || !empty($cluster_id) || !empty($house_id), function ($q) use ($site_id) {
            //     $q->whereIn('pay_id', function ($sub) use ($site_id) {
            //         $sub->select('aa.pay_id')
            //             ->from('purc_pay_mast as aa')
            //             ->join('purc_pay_item as ac', 'aa.pay_id', '=', 'ac.pay_id')
            //             ->join('purc_inv_item as ae', 'ac.inv_id', '=', 'ae.inv_id')
            //             ->where('ae.id_site', $site_id);
            //     });
            // });
            // ->when(!empty($cluster_id), function ($q) use ($cluster_id) {
            //     $q->where('ae.id_cluster', $cluster_id);
            // })
            // ->when(!empty($house_id), function ($q) use ($house_id) {
            //     $q->where('ae.id_house', $house_id);
            // });
            //->get();
            $query2 = DB::connection("sqlSrvBCKI")
                ->table('purc_pay_mast as a')
                ->selectRaw(
                    " trim(a.pay_id) pay_id, trim(b.vendor_name) vendor_name,a.dt_pay, a.pay_amt as total_pay_amt,  trim(a.remark) remark,
                 trim(c.inv_id) inv_id, d.dt_inv, round(cast(e.amt_item as BIGINT)*cast(c.pay_amt as BIGINT)/cast(d.amt_total as BIGINT),0)  pay_amt, 
                 trim(site_name) site_name,trim(cluster_name) cluster_name, trim(house_name) house_name,
                 'CHILD' mother_child,trim(a.pay_id) mother_id, a.ac_num_diff
                "
                )
                ->join('vendor as b', 'a.vendor_id', '=', 'b.vendor_id')
                ->join('purc_pay_item as c', 'a.pay_id', '=', 'c.pay_id')
                ->join('purc_inv_mast as d', 'd.inv_id', '=', 'c.inv_id')
                ->join('purc_inv_item as e', 'd.inv_id', '=', 'e.inv_id')
                ->leftjoin('site as s', 's.id_site', '=', 'e.id_site')
                ->leftjoin('site_cluster as t', function ($join) {
                    $join->on('t.id_site', '=', 'e.id_site')
                        ->on('t.id_cluster', '=', 'e.id_cluster');
                })
                ->leftjoin('house_unit as h', function ($join) {
                    $join->on('h.id_site', '=', 'e.id_site')
                        ->on('h.id_house', '=', 'e.id_house')
                        ->on('h.id_cluster', '=', 'e.id_cluster');
                })
                ->whereRaw($sqlWhere)
                //->orderByDesc('a.dt_pay', 'a.pay_id')
                //->get();
                ->unionAll($query1);
            $result =  DB::connection("sqlSrvBCKI")
                ->table(DB::raw("({$query2->toSql()}) as x"))
                ->mergeBindings($query2)
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetInvByVendor(Request $request)
    {
        $vendor_id = $request->vendor_id;
        if (!$vendor_id) {
            return $this->sendError("Empty vendor", 'Error', 500);
        }
        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('purc_inv_mast as a')
                ->selectRaw("
                        trim(a.inv_id) invoice,amt_total total_inv,amt_retur + amt_paid_disc retur_disc,amt_paid amt_paid
                        ,amt_total - amt_retur - amt_paid - amt_paid_disc  + amt_refund + a.amt_freight amt_owing ,a.rate,trim(a.curr_id) currid,dt_inv inv_date,
                        trim(tr_id) tr_id,trim(ac_num_hut) ac_num_hut,trim(memo_txt) remark,a.amt_freight, a.pr_id
                    ")
                ->leftjoin('purc_rcv_hdr as b', 'a.inv_id', '=', 'b.inv_id')
                ->where('a.office_id', '=', 1)
                ->where('a.paid_flag', '<>', 'P')
                ->where('a.vendor_id', '=', $vendor_id)
                ->orderBy('dt_inv', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get outstanding invoice data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function GetPurcPayByIdSplit(Request $request)
    {
        $pay_id = $request->pay_id;
        $type = $request->type;
        if (!$pay_id) {
            return $this->sendError("Empty pay id", 'Error', 500);
        }
        try {
            switch ($type) {
                case 'header':
                    $result = DB::connection("sqlSrvBCKI")
                        ->table('purc_pay_mast as a')
                        ->selectRaw("
                        trim(a.pay_id) pay_id,trim(a.vendor_id) vendor_id,trim(vendor_name) vendor_name,a.dt_pay,a.dt_cek,a.pay_amt,
                        trim(a.remark) remark,trim(bkk) bkk, trim(a.curr_id) curr_id,a.rate,trim(a.cek_id) giro,trim(a.ac_num) ac_num,trim(ac_name) ac_name,
                        trim(a.dp_id) dp_id,isnull(f.dp_amt,0) dp_amt, bank.bank_name as bank_id
                    ")
                        ->join('vendor as d', 'a.vendor_id', '=', 'd.vendor_id')
                        ->join('g_ledger as e', 'a.ac_num', '=', 'e.ac_num')
                        ->leftjoin('purc_down_payment as f', 'a.dp_id', '=', 'f.dp_id')
                        ->leftjoin('bank_note as bn', function ($join) {
                            $join->on('bn.tr_id', '=', 'a.bkk')
                                ->on('bn.pay_id', '=', 'a.pay_id');
                        })
                        ->leftjoin('account as bank', 'bank.bank_acc', '=', 'bn.bank_acc')
                        ->where('a.pay_id', '=', $pay_id)
                        ->orderBy('a.dt_pay', 'desc')
                        ->first();
                    $message = 'Successfully retrieved purc payment data';
                    break;
                case 'detail':
                    $result = DB::connection("sqlSrvBCKI")
                        ->table('purc_pay_mast as a')
                        ->selectRaw("
                        trim(b.inv_id) inv_id,c.dt_inv,inv_amt,b.pay_amt,trim(b.tr_id) tr_id
                    ")
                        ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                        ->join('purc_inv_mast as c', 'b.inv_id', '=', 'c.inv_id')
                        ->where('a.pay_id', '=', $pay_id)
                        ->orderBy('a.dt_pay', 'desc')
                        ->get();
                    $message = 'Successfully retrieved purc payment item data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }
            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetPurcPayById(Request $request)
    {
        $pay_id = $request->pay_id;
        if (!$pay_id) {
            return $this->sendError("Empty pay id", 'Error', 500);
        }
        try {
            $result = DB::connection("sqlSrvBCKI")
                ->table('purc_pay_mast as a')
                ->selectRaw(
                    " trim(a.pay_id) pay_id,trim(a.vendor_id) vendor_id ,trim(b.vendor_name) vendor_name,trim(b.address1) alamat1,trim(b.address2) alamat2,trim(b.city) kota,a.dt_pay, 
                    trim(a.ac_num) ac_num,trim(e.ac_name) ac_name,a.pay_amt as total_pay_amt,  trim(a.remark) remark,
                    trim(bkk) bkk, trim(a.curr_id) curr_id,a.rate,trim(a.cek_id) giro,
                    trim(c.inv_id) inv_id, d.dt_inv, d.amt_total,c.pay_amt,case when d.paid_flag ='P' then 'LUNAS' else 'BELUM LUNAS' end lunas
                    ,trim(d.tr_id) tr_id,a.dt_cek tgl_giro,(select sum(pay_amt) from purc_pay_item where pay_id='$pay_id') used_amt,c.disc_amt,a.selisih_bayar, bank.bank_name as bank_id, a.kb_id, a.dp_id, purc_dp.rate as deposit_rate , purc_dp.dp_amt as dp_amt"
                )
                ->selectSub(function ($query) {
                    $query->from('purc_inv_item as aa')
                        ->selectRaw('top 1 site_name')
                        ->join('site as ab', 'aa.id_site', '=', 'ab.id_site')
                        ->whereColumn('aa.inv_id', 'c.inv_id');
                }, 'site_name')
                ->join('vendor as b', 'a.vendor_id', '=', 'b.vendor_id')
                ->join('purc_pay_item as c', 'a.pay_id', '=', 'c.pay_id')
                ->join('purc_inv_mast as d', 'd.inv_id', '=', 'c.inv_id')
                ->join('g_ledger as e', 'e.ac_num', '=', 'a.ac_num')
                ->leftjoin('bank_note as bn', function ($join) {
                    $join->on('bn.tr_id', '=', 'a.bkk')
                        ->on('bn.pay_id', '=', 'a.pay_id');
                })
                ->leftjoin('purc_down_payment as purc_dp', function ($join) {
                    $join->on('purc_dp.dp_id', '=', 'a.dp_id');
                })
                ->leftjoin('account as bank', 'bank.bank_acc', '=', 'bn.bank_acc')
                ->where('a.pay_id', '=', $pay_id)
                ->get();

            $data = $result->groupBy('pay_id')->map(function ($items) {
                $first = $items->first();
                return [
                    'pay_id' => $first->pay_id,
                    'bank_id' => $first->bank_id,
                    'vendor_name' => $first->vendor_name,
                    'vendor_id' => $first->vendor_id,
                    'alamat1' => $first->alamat1,
                    'alamat2' => $first->alamat2,
                    'kota' => $first->kota,
                    'dt_pay' => $first->dt_pay,
                    'ac_num' => $first->ac_num,
                    'ac_name' => $first->ac_name,
                    'bkk' => $first->bkk,
                    'kb_id' => $first->kb_id,
                    'dp_id' => $first->dp_id,
                    'deposit_rate' => $first->deposit_rate,
                    'dp_amt' => $first->dp_amt,
                    'curr_id' => $first->curr_id,
                    'rate' => $first->rate,
                    'giro' => $first->giro,
                    'tgl_giro' => $first->tgl_giro,
                    'total_pay_amt' => $first->total_pay_amt,
                    'total_tagihan_dibayar' => $first->used_amt,
                    'remark' => $first->remark,
                    'proyek' => $first->site_name,
                    'selisih_bayar' => $first->selisih_bayar,
                    'sisa' => (float)$first->total_pay_amt - (float)$first->used_amt,
                    'detail' => $items->map(function ($item) {
                        return [
                            'inv_id' => $item->inv_id,
                            'tr_id' => $item->tr_id,
                            'dt_inv' => $item->dt_inv,
                            'amt_total' => $item->amt_total,
                            'pay_amt' => $item->pay_amt,
                            'disc_amt' => $item->disc_amt,
                            'lunas' => $item->lunas,
                        ];
                    })->values()
                ];
            })->first();
            return $this->sendResponse($data, 'Successfully get outstanding invoice data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetListDepositByVendor(Request $request)
    {
        $vendor_id = $request->vendor_id;
        if (!$vendor_id) {
            return $this->sendError("Empty vendor", 'Error', 500);
        }
        try {
            $sqlWhere = " dp_amt - dp_used > 0 ";
            $result = DB::connection("sqlSrvBCKI")
                ->table('purc_down_payment as a')
                ->selectRaw("
                        trim(dp_id) dp_id,dt_dp, dp_amt, dp_used, (dp_amt - dp_used) as dp_available_amt,ac_num_dp ac_num
                    ")
                ->where('a.office_id', '=', 1)
                ->where('a.vendor_id', '=', $vendor_id)
                ->whereRaw($sqlWhere)
                ->orderBy('dp_id', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get purchase deposit data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetListGiroByVendor(Request $request)
    {
        $vendor_id = $request->vendor_id;
        $bank_id = $request->bank_id;
        $giro_amt = $request->giro_amt;
        if (!$vendor_id) {
            return $this->sendError("Empty vendor", 'Error', 500);
        }
        try {
            $sqlWhere = " a.stat='B' and a.tr_id='' and tr_type='IG' ";
            $result = DB::connection("sqlSrvBCKI")
                ->table('giro_trans as a')
                ->selectRaw("
                        trim(a.giro_num) giro_num,trim(a.bank_id) bank_id,trim(bank_cust_name) bank_cust_name,giro_amt,dt_giro,dt_due
                    ")
                ->join('bank_mast as b', 'a.bank_id', '=', 'b.bank_id')
                ->where('a.cust_id', '=', $vendor_id)
                ->where('a.bank_id', '=', $bank_id)
                ->where('a.giro_amt', '=', $giro_amt)
                ->where('a.stat', '<>', 'C')
                ->where('a.tr_type', '=', 'IG')
                ->whereRaw($sqlWhere)
                ->orderBy('giro_num', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get purchase deposit data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function StorePurcPayment(Request $request)
    {
        $data = $request->all();
        //dd($data);
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tgl_bayar'], $request)) {
            return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
        }
        // $hasTrId = "";
        // if ($data['deposit_id']) {
        //     $exist = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
        //         ->selectRaw("a.pay_id,b.pay_item,c.tr_id,c.amt_total")
        //         ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
        //         ->join('purc_inv_mast as c', 'b.inv_id', '=', 'c.inv_id')
        //         ->where('dp_id', trim($data['deposit_id']))
        //         ->orderBy('pay_item', 'desc')
        //         ->first();
        //     $hasTrId = $exist && trim($data['deposit_id'] ?? '') !== '';
        // } else {
        //     $exist = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
        //         ->selectRaw("a.pay_id,b.pay_item,c.tr_id,c.amt_total")
        //         ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
        //         ->join('purc_inv_mast as c', 'b.inv_id', '=', 'c.inv_id')
        //         ->where('bkk', $data['tr_id'])
        //         ->orderBy('pay_item', 'desc')
        //         ->first();
        //     $hasTrId = $exist && trim($data['tr_id'] ?? '') !== '';
        // }
        $exist = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
            ->selectRaw("a.pay_id,b.pay_item,c.tr_id,c.amt_total")
            ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
            ->join('purc_inv_mast as c', 'b.inv_id', '=', 'c.inv_id')
            ->when(!empty($data['tr_id']), function ($query) use ($data) {
                return $query->where('bkk', $data['tr_id']);
            })
            ->when(!empty($data['deposit_id']), function ($query) use ($data) {
                return $query->where('a.dp_id', $data['deposit_id']);
            })
            ->orderBy('pay_item', 'desc')
            ->first();
        $hasTrId = $exist; //&& trim($data['tr_id'] ?? '') !== '';
        $trId    = $hasTrId ? $exist->pay_id : $this->generateTrId($request->tgl_bayar);
        $type    = $hasTrId ? 'M' : 'A';
        $payItem = $hasTrId ? $exist->pay_item  : 0;

        $invExist = DB::connection('sqlSrvBCKI')->table('purc_inv_mast as a')
            ->selectRaw("a.amt_total,a.tr_id")
            ->where('inv_id', $data['detail']['invoice_id'])
            ->first();
        $invAmt  = $invExist->amt_total;
        $trInv   = trim($invExist->tr_id);
        try {
            DB::connection('sqlSrvBCKI')->transaction(function () use ($data, $userId, $trId, $type, $payItem, $trInv, $invAmt, $request) {
                if ($type == 'A') {
                    DB::connection('sqlSrvBCKI')->table('purc_pay_mast')->insert([
                        'office_id' => 1,
                        'pay_id' => $trId,
                        'dt_pay' => $data['tgl_bayar'],
                        'vendor_id' => $data['vendor_id'],
                        'cek_id' => !empty($data['giro_id']) ? $data['giro_id'] : '',
                        'dt_cek' => $data['tgl_bayar'],
                        'cek_amt' => (int) preg_replace('/[^0-9]/', '', $data['total_bayar']),
                        'pay_amt' => (int) preg_replace('/[^0-9]/', '', $data['total_bayar']),
                        'ac_num' => $data['acc_num'],
                        'ac_num_diff' => $request->ac_num_diff ? $request->ac_num_diff : '610-010-026',
                        'remark' => $data['memo'],
                        'rate' => 1,
                        'curr_id' => 'IDR',
                        'dp_flag' => empty($data['deposit_id'])  ? 'N' : 'Y',
                        'dp_id' =>  empty($data['deposit_id'])  ? '' : $data['deposit_id'],
                        'dt_created' => now(),
                        'dt_modified' => now(),
                        'bkk' => empty($data['tr_id'])  ? '' : $data['tr_id'],
                        'kb_id' => empty($data['kb_id'])  ? '' : $data['kb_id'],
                        'user_id' => $userId
                    ]);
                } else {
                    DB::connection('sqlSrvBCKI')->table('purc_pay_mast')
                        ->where('pay_id', $trId)
                        ->update([
                            'office_id' => 1,
                            'pay_id' => $trId,
                            'dt_pay' => $data['tgl_bayar'],
                            'vendor_id' => $data['vendor_id'],
                            'cek_id' => !empty($data['giro_id']) ? $data['giro_id'] : '',
                            'dt_cek' => $data['tgl_bayar'],
                            'cek_amt' => (float) preg_replace('/[^0-9]/', '', $data['total_bayar']),
                            'pay_amt' => (int) preg_replace('/[^0-9]/', '', $data['total_bayar']),
                            'ac_num' => $data['acc_num'],
                            'ac_num_diff' => $request->ac_num_diff ? $request->ac_num_diff : '610-010-026',
                            'remark' => $data['memo'],
                            'rate' => 1,
                            'curr_id' => 'IDR',
                            'dp_flag' => empty($data['deposit_id'])  ? 'N' : 'Y',
                            'dp_id' =>  empty($data['deposit_id'])  ? '' : $data['deposit_id'],
                            'dt_created' => now(),
                            'dt_modified' => now(),
                            'bkk' => empty($data['tr_id'])  ? '' : $data['tr_id'],
                            'kb_id' => empty($data['kb_id'])  ? '' : $data['kb_id'],
                            'user_id' => $userId
                        ]);
                }

                DB::connection('sqlSrvBCKI')->table('purc_pay_item')->insert([
                    'office_id' => 1,
                    'pay_id' => $trId,
                    'pay_item' => (int)$payItem + 1,
                    'inv_id' => $data['detail']['invoice_id'],
                    'tr_id' => $trInv,
                    'dt_inv' => $data['detail']['tgl_inv'],
                    'inv_amt' => (float) preg_replace('/[^0-9]/', '', $invAmt),
                    'pay_amt' => (float) preg_replace('/[^0-9]/', '', $data['detail']['amt_pembayaran']),
                    'rate_diff_amt' => 0,
                    'disc_amt' => $data['detail']['amt_diskon'],
                    'user_id' => $userId,

                ]);
                //}
                if (!empty($data['tr_id'])) {
                    DB::connection('sqlSrvBCKI')->table('bank_note')
                        ->where('tr_id', $data['tr_id'])
                        ->update([
                            'voucher' => 1,
                            'pay_id' => $trId
                        ]);
                }

                if (!empty($data['giro_id'])) {
                    DB::connection('sqlSrvBCKI')->table('giro_trans')
                        ->where('giro_num', $data['giro_id'])
                        ->update([
                            'stat' => 'C',
                            'clear_flag' => 'C',
                            'tr_id' => $trId
                        ]);
                }

                if (!empty($data['kb_id'])) {
                    DB::connection('sqlSrvBCKI')->table('kb_hdr')
                        ->where('tr_id', $data['kb_id'])
                        ->update([
                            'pay_id' => $trId
                        ]);
                }
            });

            $sumBayar = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $trId)
                ->sum('b.pay_amt') ?? 0;
            DB::connection('sqlSrvBCKI')->table('purc_pay_mast')->where('pay_id', $trId)
                ->update([
                    'selisih_bayar' => DB::raw('pay_amt - ' . (float)$sumBayar)
                ]);
            // DB::connection('sqlSrvBCKI')->table('purc_pay_mast')
            //     ->where('pay_id',  $trId)
            //     ->update([
            //         'selisih_bayar' => DB::raw("
            //             CASE 
            //                 WHEN (pay_amt - " . (float)$sumBayar . " - " . (float)$data['detail']['amt_diskon'] . ") < 0 
            //                 THEN 0 
            //                 ELSE (pay_amt - " . (float)$sumBayar . " - " . (float)$data['detail']['amt_diskon'] . ") 
            //             END
            //         "),
            //     ]);
            $sumBayarInv = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('b.inv_id', $data['detail']['invoice_id'])
                ->sum('b.pay_amt') ?? 0;
            DB::connection('sqlSrvBCKI')->table('purc_inv_mast')
                ->where('inv_id', $data['detail']['invoice_id'])
                ->update([
                    'amt_paid' => (float) $sumBayarInv,
                    'paid_flag' => ((float)$invAmt - (float)$data['detail']['amt_diskon'] - (float)$sumBayarInv) <= 0 ? 'P' : 'Y'

                ]);

            if (!empty($data['deposit_id'])) {
                //foreach ($data['detail'] as $key => $detail) {
                $sumDp = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                    ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                    ->where('dp_id', $data['deposit_id'])
                    ->sum('b.pay_amt') ?? 0;

                $dpInvoiceExist = DB::connection('sqlSrvBCKI')->table('purc_dp_inv')
                    ->select('inv_id')
                    ->where('dp_id', $data['deposit_id'])
                    ->where('pay_id', $trId)
                    ->where('inv_id', $data['detail']['invoice_id']);
                if ($dpInvoiceExist) {
                    DB::connection('sqlSrvBCKI')->table('purc_dp_inv')
                        ->where('dp_id', $data['deposit_id'])
                        ->where('pay_id', $trId)
                        ->where('inv_id', $data['detail']['invoice_id'])
                        ->update([
                            'dt_modified' => now(),
                            'pay_amt' => (float)$sumDp,
                            'user_id' => $userId
                        ]);
                } else {
                    DB::connection('sqlSrvBCKI')->table('purc_dp_inv')->insert([
                        'office_id' => 1,
                        'dp_id' => $data['deposit_id'],
                        'pay_id' => $trId,
                        'inv_id' => $data['detail']['invoice_id'],
                        'dt_pay' => $data['tgl_bayar'],
                        'rate_inv' => 1,
                        'pay_amt' => (float) preg_replace('/[^0-9]/', '', $data['detail']['amt_pembayaran']),
                        'dt_modified' => now(),
                        'user_id' => $userId
                    ]);
                }
                //}

                DB::connection('sqlSrvBCKI')->table('purc_down_payment')
                    ->where('dp_id', $data['deposit_id'])
                    ->update([
                        'dp_used' => (float)$sumDp
                    ]);
            }
            $tanggal = Carbon::parse($data['tgl_bayar']);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlPurcPayment(1, $trId, $tahun4digit, $userId);
            return $this->sendResponse(trim($trId), 'Transaction created successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function destroyPurcPayment(Request $request)
    {
        $pay_id = trim($request->pay_id);
        $inv_id = trim($request->inv_id);
        $userId = getAuth($request)->user_id2 ?? null;

        if (empty($pay_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        try {
            $result = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->select('dt_pay', 'bkk', 'cek_id', 'dp_id')
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $pay_id)
                ->first();
            $bkk = trim($result->bkk);
            $noGiro = trim($result->cek_id);
            $dp_id = trim($result->dp_id);
            if ($this->serviceAcc->ifAcctClosed($result->dt_pay, $request)) {
                return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
            }

            DB::connection('sqlSrvBCKI')->table('purc_pay_item')
                ->where('pay_id', $pay_id)
                ->where('inv_id', $inv_id)
                ->delete();
            $sumBayar = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('b.inv_id', $inv_id)
                ->sum('b.pay_amt') ?? 0;

            DB::connection('sqlSrvBCKI')->table('purc_inv_mast')
                ->where('inv_id', $inv_id)
                ->update([
                    'amt_paid' => (float)$sumBayar,
                    'paid_flag' =>  (float)$sumBayar > 0 ? 'Y' : 'N'
                ]);
            $sumPay = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $pay_id)
                ->sum('b.pay_amt') ?? 0;
            DB::connection('sqlSrvBCKI')->table('purc_pay_mast')->where('pay_id', $pay_id)
                ->update([
                    'selisih_bayar' => DB::raw('pay_amt - ' . (float)$sumPay)
                ]);
            $itemExist = DB::connection('sqlSrvBCKI')->table('purc_pay_item')
                ->selectRaw("pay_id")
                ->where('pay_id', $pay_id)
                ->orderBy('pay_item', 'desc')
                ->first();
            if (!$itemExist) {
                DB::connection('sqlSrvBCKI')->table('purc_pay_mast')
                    ->where('pay_id', $pay_id)
                    ->delete();
                if (!empty($bkk)) {
                    DB::connection('sqlSrvBCKI')->table('bank_note')
                        ->where('tr_id', $bkk)
                        ->update([
                            'voucher' => 0,
                            'pay_id' => ''
                        ]);
                }
                if (!empty($noGiro)) {
                    DB::connection('sqlSrvBCKI')->table('giro_trans')
                        ->where('giro_num', $noGiro)
                        ->update([
                            'stat' => 'O',
                            'clear_flag' => '',
                            'tr_id' => ''
                        ]);
                }
            }
            if (!empty($dp_id)) {
                $sumDp = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                    ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                    ->where('a.dp_id', $dp_id)
                    ->sum('b.pay_amt') ?? 0;
                $dpInvoiceExist = DB::connection('sqlSrvBCKI')->table('purc_dp_inv')
                    ->select('pay_amt')
                    ->where('dp_id', $dp_id)
                    ->where('pay_id', $pay_id)
                    ->where('inv_id', $inv_id)
                    ->first();
                if ($dpInvoiceExist) {
                    DB::connection('sqlSrvBCKI')->table('purc_dp_inv')
                        ->where('dp_id', $dp_id)
                        ->where('pay_id', $pay_id)
                        ->update([
                            'pay_amt' => (float)$sumDp
                        ]);
                }
                if ((float)$sumDp <= 0) {
                    DB::connection('sqlSrvBCKI')->table('purc_dp_inv')
                        ->where('dp_id', $dp_id)
                        ->where('inv_id', $inv_id)
                        ->delete();
                }
                DB::connection('sqlSrvBCKI')->table('purc_down_payment')
                    ->where('dp_id', $dp_id)
                    ->update([
                        'dp_used' => (float)$sumDp
                    ]);
            }

            $tanggal = Carbon::parse($result->dt_pay);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlPurcPayment(1, $pay_id, $tahun4digit, $userId);
            return $this->sendResponse(null, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function updateHeaderPurcPayment(Request $request)
    {
        $pay_id = trim($request->pay_id);
        $userId = getAuth($request)->user_id2 ?? null;
        if (empty($pay_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        try {

            $result = DB::connection('sqlSrvBCKI')->table('purc_pay_mast as a')
                ->selectRaw("a.*")
                ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $pay_id)
                ->first();
            if ($result) {
                DB::connection('sqlSrvBCKI')->table('purc_pay_mast')->where('office_id', 1)->where('pay_id', $pay_id)->update([
                    'cek_id' => $request->giro_id ?? '',
                    'dt_modified' => now(),
                    'user_id' => $userId
                ]);

                if (!empty($request->giro_id)) {
                    DB::connection('sqlSrvBCKI')->table('giro_trans')
                        ->where('giro_num', $request->giro_id)
                        ->update([
                            'stat' => 'C',
                            'clear_flag' => 'C',
                            'tr_id' => $pay_id
                        ]);
                }

                return $this->sendResponse(trim($pay_id), 'Transaction update header successfully.');
            } else {
                return $this->sendError('Purc tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function generateTrId($tanggal)
    {
        $prefix = 'PP01';
        $tahun2digit = date('y', strtotime($tanggal));
        $bulan2digit = date('m', strtotime($tanggal));
        $last = DB::connection("sqlSrvBCKI")
            ->table('purc_pay_mast')
            ->selectRaw('RIGHT(trim(pay_id), 3) as kode_akhir')
            ->where('pay_id', 'like', "%{$prefix}{$tahun2digit}{$bulan2digit}%")
            ->orderByDesc('pay_id')
            ->first('pay_id');
        // dd($last);
        if ($last) {
            $lastParts =  (int) $last->kode_akhir;    // contoh: 25
            $newNumber = $lastParts + 1;
        } else {
            $newNumber = 1;
        }
        // Format ke 3 digit
        $formattedNumber = str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        return "{$prefix}{$tahun2digit}{$bulan2digit}{$formattedNumber}";
    }
    function isValidDate($date)
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function jurnalulangpurcpay(Request $request)
    {
        $pay_id = $request->pay_id ?? '';
        $dataPurcPay = DB::connection('sqlSrvBCKI')
            ->table('purc_pay_mast as a')
            ->select('dt_pay', 'bkk', 'cek_id', 'dp_id')
            ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
            ->where('a.pay_id', $pay_id)
            ->first();
        $user_id = getAuth($request)->user_id2;
        $tahun = Carbon::parse($dataPurcPay->dt_pay)->format('Y');
        $this->serviceAcc->JrnlPurcPayment(1, $pay_id, $tahun, $user_id);
        return $this->sendResponse('success', 'Successfully revise payment journal');
    }
}
