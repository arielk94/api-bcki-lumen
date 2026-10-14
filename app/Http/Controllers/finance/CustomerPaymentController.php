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

class CustomerPaymentController extends Controller
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
            $result = DB::connection("sqlSrvFPI")
                ->table('bank_note as bn')
                ->selectRaw("
                        bn.Descr,bn.cabang,bn.db_amt,bn.cr_amt,bn.saldo,bn.tr_id,bn.voucher,bn.run_number,bn.dt_trans,
                        bn.tr_type,bn.trx_mode,bn.tr_type,trim(bn.pay_id) pay_id,
                        trim(ac.bank_name) bank_name,br.branch_descr as cabang1,br.city,trim(ac.ac_num) ac_num,
                        case when pm.pay_id is not null then
                        'Y'
                        else
                        'N'
                        end as belongToTr
                    ")
                ->join('account as ac', 'bn.bank_acc', '=', 'ac.bank_acc')
                ->leftjoin('bank_branch as br', 'br.bank_branch_code', '=', 'bn.cabang')
                ->leftjoin('payment_hdr as pm', 'pm.pay_id', '=', 'bn.pay_id')
                ->whereRaw($sqlWhere)
                ->orderBy('bn.run_number', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetCustPaymentList(Request $request)
    {
        $sqlWhere = "1=1 ";
        $customer_id = $request->customer_id;
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

        if (!empty($customer_id)) {
            $sqlWhere = $sqlWhere . " and a.customerid = " . "'" . $customer_id . "'";
        }
        if ($tr_date_from) {
            $sqlWhere =  $sqlWhere . " and a.tr_date  >= CONVERT(DATE, '" . $tr_date_from . "')";
        }
        if ($tr_date_to) {
            $sqlWhere = $sqlWhere . " and a.tr_date <= CONVERT(DATE, '" . $tr_date_to . "')";
        }
        if (!empty($site_id)) {
            $sqlWhere .= " AND e.id_site = '$site_id'";
        }
        if (!empty($cluster_id)) {
            $sqlWhere .= " AND g.id_cluster ='$cluster_id'";
        }
        if (!empty($house_id)) {
            $sqlWhere .= " AND g.id_house ='$house_id'";
        }
        try {
            $query1 = DB::connection("sqlSrvFPI")
                ->table('payment_hdr as a')
                ->selectRaw(
                    " distinct trim(a.pay_id) pay_id, trim(b.namacustomer) customer_name,a.tr_date, a.pay_amt as total_pay_amt,  trim(a.remark) remark,
                 '' inv_id, '' dt_inv, '' pay_amt, '' site_name,'' cluster_name,'' house_name,'MOTHER' mother_child,trim(a.pay_id) mother_id,'' account, a.ac_num_selisih_penj
                "
                )
                ->join('customer as b', 'a.customerid', '=', 'b.customerid')
                ->join('payment_dtl as c', 'c.pay_id', 'a.pay_id')
                ->join('penjualan as d', 'c.penjualanno', 'd.penjualanno')
                ->join('customerorder as e', 'd.customerorderno', 'e.order_id')
                ->join('customerorderdetil as g', 'd.customerorderno', 'g.order_id')
                ->leftjoin('g_ledger as i', 'a.ac_num', 'i.ac_num')
                ->leftjoin('site as s', 's.id_site', 'e.id_site')
                ->leftjoin('site_cluster as t', function ($join) {
                    $join->on('t.id_site', 'e.id_site')
                        ->on('t.id_cluster', 'g.id_cluster');
                })
                ->leftjoin('house_unit as h', function ($join) {
                    $join->on('h.id_site', 'e.id_site')
                        ->on('h.id_cluster', 'g.id_cluster')
                        ->on('h.id_house', 'g.id_house');
                })
                ->whereRaw($sqlWhere);
            $query2 = DB::connection("sqlSrvFPI")
                ->table('payment_hdr as a')
                ->selectRaw(
                    " trim(a.pay_id) pay_id,trim(b.namacustomer) customer_name,a.tr_date, a.pay_amt as total_pay_amt,  trim(a.remark) remark,
                 trim(c.penjualanno) inv_id, d.tgljual dt_inv, c.pay_amt  pay_amt, 
                 trim(site_name) site_name,trim(cluster_name) cluster_name, trim(house_name) house_name,
                 'CHILD' mother_child,trim(a.pay_id) mother_id,trim(k.ac_name) account, a.ac_num_selisih_penj
                "
                )
                ->join('customer as b', 'a.customerid', '=', 'b.customerid')
                ->join('payment_dtl as c', 'c.pay_id', 'a.pay_id')
                ->join('penjualan as d', 'c.penjualanno', 'd.penjualanno')
                ->join('customerorder as e', 'd.customerorderno', 'e.order_id')
                ->join('customerorderdetil as g', 'e.order_id', 'g.order_id')
                ->join('account as j', 'a.bank_acc', 'j.bank_acc')
                ->join('g_ledger as k', 'j.ac_num', 'k.ac_num')
                ->leftjoin('g_ledger as i', 'a.ac_num', 'i.ac_num')
                ->leftjoin('site as s', 's.id_site', 'e.id_site')
                ->leftjoin('site_cluster as t', function ($join) {
                    $join->on('t.id_site', 'e.id_site')
                        ->on('t.id_cluster', 'g.id_cluster');
                })
                ->leftjoin('house_unit as h', function ($join) {
                    $join->on('h.id_site', 'e.id_site')
                        ->on('h.id_cluster', 'g.id_cluster')
                        ->on('h.id_house', 'g.id_house');
                })
                ->whereRaw($sqlWhere)
                //->orderByDesc('a.dt_pay', 'a.pay_id')
                //->get();
                ->unionAll($query1);
            $result =  DB::connection("sqlSrvFPI")
                ->table(DB::raw("({$query2->toSql()}) as x"))
                ->mergeBindings($query2)
                ->get();
            return $this->sendResponse($result, 'Successfully get bank statement data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetInvByCustomer(Request $request)
    {
        $customer_id = $request->customer_id;
        if (!$customer_id) {
            return $this->sendError("Empty customer", 'Error', 500);
        }
        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('penjualan as a')
                ->selectRaw("
                        trim(a.penjualanno) invoice,totaljual total_inv,discount retur_disc,b.pay_amt amt_paid
                        ,totaljual-discount-pay_amt amt_owing ,1 rate,'IDR' currid,tgljual inv_date,
                        trim(a.penjualanno) tr_id,'' ac_num_hut,'' remark,0 amt_freight
                    ")
                ->leftjoin('payment_outstanding as b', 'a.penjualanno', '=', 'b.penjualanno')
                ->where('a.pelunasan', '=', 'BELUM')
                ->where('a.customerid', '=', $customer_id)
                ->orderBy('tgljual', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get outstanding invoice data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function GetCustPayByIdSplit(Request $request)
    {
        $pay_id = $request->pay_id;
        $type = $request->type;
        if (!$pay_id) {
            return $this->sendError("Empty pay id", 'Error', 500);
        }
        try {
            switch ($type) {
                case 'header':
                    $result = DB::connection("sqlSrvFPI")
                        ->table('payment_hdr as a')
                        ->selectRaw("
                        trim(a.pay_id) pay_id,trim(a.customerid) customer_id,trim(namacustomer) customer_name,a.tr_date dt_pay,a.pay_amt,
                        trim(a.remark) remark,trim(bukti) bkm, trim(a.curr_id) curr_id,a.rate,case when a.tr_type='D' then trim(a.ac_num) else trim(g.ac_num) end as ac_num,
                        case when a.tr_type='D' then trim(i.ac_name) else trim(h.ac_name) end as  ac_name,
                        trim(a.tr_id_ref) dp_id,isnull(f.dp_amt,0) dp_amt
                    ")
                        ->join('customer as d', 'a.customerid', '=', 'd.customerid')
                        ->leftJoin('g_ledger as e', 'a.ac_num', '=', 'e.ac_num')
                        ->leftJoin('dp_sales as f', 'a.tr_id_ref', '=', 'f.dp_id')
                        ->leftJoin('account as g', 'a.bank_acc', 'g.bank_acc')
                        ->leftJoin('g_ledger as h', 'g.ac_num', 'h.ac_num')
                        ->leftjoin('g_ledger as i', 'a.ac_num', 'i.ac_num')
                        ->where('a.pay_id', '=', $pay_id)
                        ->orderBy('a.tr_date', 'desc')
                        ->first();
                    $message = 'Successfully retrieved purc payment data';
                    break;
                case 'detail':
                    $result = DB::connection("sqlSrvFPI")
                        ->table('payment_hdr as a')
                        ->selectRaw("
                        trim(b.penjualanno) inv_id,c.tgljual dt_inv,totaljual inv_amt,b.pay_amt
                    ")
                        ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                        ->join('penjualan as c', 'b.penjualanno', '=', 'c.penjualanno')
                        ->where('a.pay_id', '=', $pay_id)
                        ->orderBy('a.tr_date', 'desc')
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
    public function GetCustPayById(Request $request)
    {
        $pay_id = $request->pay_id;
        if (!$pay_id) {
            return $this->sendError("Empty pay id", 'Error', 500);
        }
        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('payment_hdr as a')
                ->selectRaw(
                    " trim(a.pay_id) pay_id,trim(a.customerid) customer_id ,trim(b.namacustomer) customer_name,trim(b.alamat) alamat1,trim(b.kota) kota,a.tr_date dt_pay, 
                    trim(a.ac_num) ac_num,trim(e.ac_name) ac_name,a.pay_amt as total_pay_amt,  trim(a.remark) remark,trim(a.giro_id) giro_id,trim(a.kwitansi) kwitansi,
                    trim(bukti) bkm, trim(a.curr_id) curr_id,a.rate,a.voucher,trim(a.bank_id) bank_id,trim(a.bank_acc) bank_acc,trim(g.ac_name) bank_desc,
                    trim(c.penjualanno) inv_id, d.tgljual dt_inv, d.totaljual amt_total,c.pay_amt,pelunasan lunas
                    ,(select sum(pay_amt) from payment_dtl where pay_id='$pay_id') used_amt,c.disc_amt,a.selisih_penj selisih_bayar"
                )
                ->selectSub(function ($query) {
                    $query->from('customerorder as aa')
                        ->selectRaw('top 1 site_name')
                        ->join('site as ab', 'aa.id_site', '=', 'ab.id_site')
                        ->whereColumn('aa.order_id', 'c.penjualanno');
                }, 'site_name')
                ->join('customer as b', 'a.customerid', '=', 'b.customerid')
                ->join('payment_dtl as c', 'a.pay_id', '=', 'c.pay_id')
                ->join('penjualan as d', 'd.penjualanno', '=', 'c.penjualanno')
                ->leftJoin('account as f', 'a.bank_acc', '=', 'f.bank_acc')
                ->leftJoin('g_ledger as g', 'f.ac_num', '=', 'g.ac_num')
                ->leftJoin('g_ledger as e', 'e.ac_num', '=', 'a.ac_num')
                ->where('a.pay_id', '=', $pay_id)
                ->get();

            $data = $result->groupBy('pay_id')->map(function ($items) {
                $first = $items->first();
                return [
                    'pay_id' => $first->pay_id,
                    'voucher' => $first->voucher,
                    'bank_id' => $first->bank_id,
                    'bank_account' => $first->bank_acc,
                    'bank_desc' => $first->bank_desc,
                    'customer_name' => $first->customer_name,
                    'customer_id' => $first->customer_id,
                    'alamat1' => $first->alamat1,
                    'kota' => $first->kota,
                    'dt_pay' => $first->dt_pay,
                    'ac_num' => $first->ac_num,
                    'ac_name' => $first->ac_name,
                    'bkm' => $first->bkm,
                    'curr_id' => $first->curr_id,
                    'rate' => $first->rate,
                    'total_pay_amt' => $first->total_pay_amt,
                    'total_tagihan_dibayar' => $first->used_amt,
                    'remark' => $first->remark,
                    'proyek' => $first->site_name,
                    'selisih_bayar' => $first->selisih_bayar,
                    'sisa' => (float)$first->total_pay_amt - (float)$first->used_amt,
                    'detail' => $items->map(function ($item) {
                        return [
                            'inv_id' => $item->inv_id,
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
    public function GetListDepositByCustomer(Request $request)
    {
        $customer_id = $request->customer_id;
        if (!$customer_id) {
            return $this->sendError("Empty customer", 'Error', 500);
        }
        try {
            $sqlWhere = " dp_amt - used_amt > 0 ";
            $result = DB::connection("sqlSrvFPI")
                ->table('dp_sales as a')
                ->selectRaw("
                        trim(dp_id) dp_id,dt_dp, dp_amt, used_amt, (dp_amt - used_amt) as dp_available_amt,ac_num_dp ac_num
                    ")
                ->where('a.cust_id', '=', $customer_id)
                ->whereRaw($sqlWhere)
                ->orderBy('dp_id', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get customer deposit data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetListKwitansiByCustomer(Request $request)
    {
        $customer_id = $request->customer_id;
        if (!$customer_id) {
            return $this->sendError("Empty customer", 'Error', 500);
        }
        try {
            //     sSQL = "select a.tr_id,b.site_name,c.house_name,format(a.tr_amt,'#,0'),a.tr_date,a.remark " & _
            // " from kwitansi a left outer join  site b on a.id_site=b.id_site    " & _
            // " left outer join house_unit c on a.id_site=c.id_site and a.id_cluster=c.id_cluster and a.id_house=c.id_house" & _
            // " where a.tr_id_ref='' and customerid='" & Trim(FromCustomer) & "'"
            $result = DB::connection("sqlSrvFPI")
                ->table('kwitansi as a')
                ->selectRaw("
                        trim(a.tr_id) tr_id,trim(b.site_name) site_name,trim(d.house_name) house_name,format(a.tr_amt,'#,0') amt,a.tr_date,trim(a.remark) remark
                    ")
                ->leftjoin('site as b', 'b.id_site', 'a.id_site')
                ->leftjoin('site_cluster as c', function ($join) {
                    $join->on('c.id_site', 'a.id_site')
                        ->on('c.id_cluster', 'a.id_cluster');
                })
                ->leftjoin('house_unit as d', function ($join) {
                    $join->on('d.id_site', 'a.id_site')
                        ->on('d.id_cluster', 'a.id_cluster')
                        ->on('d.id_house', 'a.id_house');
                })
                ->where('a.customerid', '=', $customer_id)
                ->where('a.tr_id_ref', '=', '')
                ->get();
            return $this->sendResponse($result, 'Successfully get customer deposit data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function GetListGiroByCustomer(Request $request)
    {
        $customer_id = $request->customer_id;
        $bank_id = $request->bank_id;
        $giro_amt = $request->giro_amt;
        if (!$customer_id) {
            return $this->sendError("Empty customer", 'Error', 500);
        }
        try {
            $sqlWhere = " a.stat='B' and a.tr_id='' and tr_type='RG' ";
            $result = DB::connection("sqlSrvFPI")
                ->table('giro_trans as a')
                ->selectRaw("
                        trim(a.giro_num) giro_num,trim(a.bank_id) bank_id,trim(bank_cust_name) bank_cust_name,giro_amt,dt_giro,dt_due
                    ")
                ->join('bank_mast as b', 'a.bank_id', '=', 'b.bank_id')
                ->where('a.cust_id', '=', $customer_id)
                ->where('a.bank_id', '=', $bank_id)
                ->where('a.giro_amt', '=', $giro_amt)
                ->where('a.stat', '<>', 'C')
                ->where('a.tr_type', '=', 'RG')
                ->whereRaw($sqlWhere)
                ->orderBy('giro_num', 'desc')
                ->get();
            return $this->sendResponse($result, 'Successfully get giro data');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function StoreCustPayment(Request $request)
    {
        $data = $request->all();
        //dd($data);
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->serviceAcc->ifAcctClosed($data['tgl_bayar'], $request)) {
            return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
        }
        //$trid = $this->generateTrId($request->tgl_bayar);
        //dd($trid);
        $exist = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
            ->selectRaw("a.pay_id,b.penjualanno,a.voucher")
            ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
            ->when(!empty($data['tr_id']), function ($query) use ($data) {
                return $query->where('bukti', $data['tr_id']);
            })
            ->when(!empty($data['deposit_id']), function ($query) use ($data) {
                return $query->where('a.tr_id_ref', $data['deposit_id']);
            })
            ->orderBy('penjualanno', 'desc')
            ->first();
        $hasTrId = $exist; //&& trim($data['tr_id'] ?? '') !== '';
        $trId    = $hasTrId ? $exist->pay_id : $this->generateTrId($request->tgl_bayar);
        $type    = $hasTrId ? 'M' : 'A';
        $voucher = $hasTrId ? $exist->voucher : $this->generateVoucher();
        //$payItem = $hasTrId ? $exist->pay_item  : 0;

        $invExist = DB::connection('sqlSrvFPI')->table('penjualan as a')
            ->selectRaw("a.totaljual amt_total,a.penjualanno")
            ->where('penjualanno', $data['detail']['invoice_id'])
            ->first();
        $invAmt  = $invExist->amt_total;
        $trInv   = trim($invExist->penjualanno);
        try {
            $ifDataExist = DB::connection('sqlSrvFPI')->table('payment_dtl as a')
                ->select('a.penjualanno')
                ->where('a.pay_id', $trId)
                ->where('a.penjualanno', $data['detail']['invoice_id'])
                ->first();
            if ($ifDataExist) {
                return $this->sendError("Data exist, delete payment detail " . $data['detail']['invoice_id'] . " first", 'Error', 500);
            }
            DB::connection('sqlSrvFPI')->transaction(function () use ($data, $userId, $trId, $type, $trInv, $invAmt, $voucher) {
                if ($type == 'A') {
                    DB::connection('sqlSrvFPI')->table('payment_hdr')->insert([
                        'voucher' => $voucher,
                        'pay_id' => $trId,
                        'tr_date' => $data['tgl_bayar'],
                        'customerid' => $data['customer_id'],
                        'giro_id' => $data['giro_id'],
                        'pay_amt' => (float)$data['total_bayar'],
                        'bank_id' => $data['bank_id'],
                        'bank_acc' => $data['bank_acc'],
                        'ac_num' => !empty($data['deposit_id'])  ? $this->getAcnumDP($data['deposit_id']) : '',
                        'stat' => 'P',
                        'remark' => $data['memo'],
                        'rate' => 1,
                        'curr_id' => 'IDR',
                        'tr_id_ref' =>  empty($data['deposit_id'])  ? '' : $data['deposit_id'],
                        'tr_type' =>  !empty($data['deposit_id'])  ? 'D' : 'T',
                        'mv_type' => '100',
                        'dt_entry' => now(),
                        'dt_modified' => now(),
                        'bukti' => empty($data['tr_id'])  ? '' : $data['tr_id'],
                        'kwitansi' => empty($data['kwitansi'])  ? '' : $data['kwitansi'],
                        'user_id' => $userId
                    ]);
                } else {
                    DB::connection('sqlSrvFPI')->table('payment_hdr')
                        ->where('pay_id', $trId)
                        ->update([
                            'voucher' => $voucher,
                            'pay_id' => $trId,
                            'tr_date' => $data['tgl_bayar'],
                            'customerid' => $data['customer_id'],
                            'giro_id' => $data['giro_id'],
                            'tr_date' => $data['tgl_bayar'],
                            'pay_amt' => (float) $data['total_bayar'],
                            'bank_id' => $data['bank_id'],
                            'bank_acc' => $data['bank_acc'],
                            'ac_num' => !empty($data['deposit_id'])  ? $this->getAcnumDP($data['deposit_id']) : '',
                            'stat' => 'P',
                            'remark' => $data['memo'],
                            'rate' => 1,
                            'curr_id' => 'IDR',
                            'tr_id_ref' =>  empty($data['deposit_id'])  ? '' : $data['deposit_id'],
                            'tr_type' =>  !empty($data['deposit_id'])  ? 'D' : 'T',
                            'mv_type' => '100',
                            'dt_modified' => now(),
                            'bukti' => empty($data['tr_id'])  ? '' : $data['tr_id'],
                            'kwitansi' => empty($data['kwitansi'])  ? '' : $data['kwitansi'],
                            'user_id' => $userId
                        ]);
                }

                DB::connection('sqlSrvFPI')->table('payment_dtl')->insert([
                    'voucher' => $voucher,
                    'pay_id' => $trId,
                    'penjualanno' => $data['detail']['invoice_id'],
                    'tr_date' => $data['tgl_bayar'],
                    'pay_amt' => (float) $data['detail']['amt_pembayaran'],
                    'mv_type' => '100',
                    'disc_amt' => $data['detail']['amt_diskon'],
                    'user_id' => $userId,

                ]);
                //}
                if (!empty($data['tr_id'])) {
                    DB::connection('sqlSrvFPI')->table('bank_note')
                        ->where('tr_id', $data['tr_id'])
                        ->update([
                            'voucher' => $voucher,
                            'pay_id' => $trId
                        ]);
                }
                if (!empty($data['kwitansi'])) {
                    DB::connection('sqlSrvFPI')->table('kwitansi')
                        ->where('tr_id', $data['kwitansi'])
                        ->update([
                            'tr_id_ref' => $trId
                        ]);
                }
                if (!empty($data['giro_id'])) {
                    DB::connection('sqlSrvFPI')->table('giro_trans')
                        ->where('giro_num', $data['giro_id'])
                        ->update([
                            'stat' => 'C',
                            'clear_flag' => 'C',
                            'tr_id' => $trId
                        ]);
                }
            });

            $sumBayar = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $trId)
                ->sum('b.pay_amt') ?? 0;
            DB::connection('sqlSrvFPI')->table('payment_hdr')->where('pay_id', $trId)
                ->update([
                    'selisih_penj' => DB::raw('pay_amt - ' . (float)$sumBayar),
                    'ac_num_selisih_penj' => $request->ac_num_selisih_penj ? $request->ac_num_selisih_penj : ' '
                ]);

            $sumBayarInv = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('b.penjualanno', $data['detail']['invoice_id'])
                ->sum('b.pay_amt') ?? 0;
            DB::connection('sqlSrvFPI')->table('payment_outstanding')
                ->where('penjualanno', $data['detail']['invoice_id'])
                ->update([
                    'pay_amt' => (float) $sumBayarInv

                ]);
            DB::connection('sqlSrvFPI')->table('penjualan')
                ->where('penjualanno', $data['detail']['invoice_id'])
                ->update([
                    'pelunasan' => ((float)$invAmt - (float)($data['detail']['amt_diskon'] ?? 0) - (float)$sumBayarInv) <= 0 ? 'LUNAS' : 'BELUM'

                ]);

            if (!empty($data['deposit_id'])) {
                //foreach ($data['detail'] as $key => $detail) {
                $sumDp = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                    ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                    ->where('a.tr_id_ref', $data['deposit_id'])
                    ->sum('b.pay_amt') ?? 0;

                DB::connection('sqlSrvFPI')->table('dp_sales')
                    ->where('dp_id', $data['deposit_id'])
                    ->update([
                        'used_amt' => (float)$sumDp,
                        'bal_amt' => DB::raw('dp_amt - ' . (float)$sumDp)
                    ]);
            }
            $tanggal = Carbon::parse($data['tgl_bayar']);
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlCustPayment(1, $trId, $tahun4digit, $userId);
            return $this->sendResponse(trim($trId), 'Transaction created successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function destroyCustPayment(Request $request)
    {
        $pay_id = trim($request->pay_id);
        $inv_id = trim($request->inv_id);
        $userId = getAuth($request)->user_id2 ?? null;

        if (empty($pay_id)) {
            return $this->sendError("Empty transaction id", 'Error', 500);
        }
        try {
            $ifDataExist = DB::connection('sqlSrvFPI')->table('payment_dtl as a')
                ->select('a.penjualanno')
                ->where('a.pay_id', $pay_id)
                ->where('a.penjualanno', $inv_id)
                ->first();
            if (!$ifDataExist) {
                return $this->sendError("Data not found", 'Error', 500);
            }
            $result = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                ->select('a.tr_date', 'bukti', 'giro_id', 'a.tr_id_ref', 'a.kwitansi')
                ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('a.pay_id', $pay_id)
                ->first();
            $bkk = trim($result->bukti);
            $noGiro = trim($result->giro_id);
            $dp_id = trim($result->tr_id_ref);
            $kwitansi = trim($result->kwitansi);
            $tanggal = Carbon::parse($result->tr_date);
            if ($this->serviceAcc->ifAcctClosed($result->tr_date, $request)) {
                return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
            }

            DB::connection('sqlSrvFPI')->table('payment_dtl')
                ->where('pay_id', $pay_id)
                ->where('penjualanno', $inv_id)
                ->delete();
            $sumBayar = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                ->where('b.penjualanno', $inv_id)
                ->sum('b.pay_amt') ?? 0;

            DB::connection('sqlSrvFPI')->table('penjualan')
                ->where('penjualanno', $inv_id)
                ->update([
                    'pelunasan' =>   'BELUM'
                ]);
            DB::connection('sqlSrvFPI')->table('payment_outstanding')
                ->where('penjualanno', $inv_id)
                ->update([
                    'pay_amt' => (float)$sumBayar
                ]);
            $itemExist = DB::connection('sqlSrvFPI')->table('payment_dtl')
                ->selectRaw("pay_id")
                ->where('pay_id', $pay_id)
                ->orderBy('penjualanno', 'desc')
                ->first();
            if (!$itemExist) {
                DB::connection('sqlSrvFPI')->table('payment_hdr')
                    ->where('pay_id', $pay_id)
                    ->delete();
                if (!empty($bkk)) {
                    DB::connection('sqlSrvFPI')->table('bank_note')
                        ->where('tr_id', $bkk)
                        ->update([
                            'voucher' => 0,
                            'pay_id' => ''
                        ]);
                }
                if (!empty($kwitansi)) {
                    DB::connection('sqlSrvFPI')->table('kwitansi')
                        ->where('tr_id', $kwitansi)
                        ->update([
                            'tr_id_ref' => ''
                        ]);
                }
                if (!empty($noGiro)) {
                    DB::connection('sqlSrvFPI')->table('giro_trans')
                        ->where('giro_num', $noGiro)
                        ->update([
                            'stat' => 'O',
                            'clear_flag' => '',
                            'tr_id' => ''
                        ]);
                }
            }
            if (!empty($dp_id)) {
                $sumDp = DB::connection('sqlSrvFPI')->table('payment_hdr as a')
                    ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
                    ->where('a.tr_id_ref', $dp_id)
                    ->sum('b.pay_amt') ?? 0;

                DB::connection('sqlSrvFPI')->table('dp_sales')
                    ->where('dp_id', $dp_id)
                    ->update([
                        'used_amt' => (float)$sumDp
                    ]);
            }
            $tahun4digit = $tanggal->format('Y');
            $this->serviceAcc->JrnlCustPayment(1, $pay_id, $tahun4digit, $userId);
            return $this->sendResponse(null, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function generateTrId($tanggal)
    {
        $prefix = 'PF';
        $tahun2digit = date('y', strtotime($tanggal));
        $bulan2digit = date('m', strtotime($tanggal));
        $tgl2digit = date('d', strtotime($tanggal));
        $last = DB::connection("sqlSrvFPI")
            ->table('payment_hdr')
            ->selectRaw('RIGHT(trim(pay_id), 3) as kode_akhir')
            ->where('pay_id', 'like', "%{$prefix}{$tahun2digit}{$bulan2digit}{$tgl2digit}%")
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
        return "{$prefix}{$tahun2digit}{$bulan2digit}{$tgl2digit}{$formattedNumber}";
    }
    function generateVoucher()
    {
        $maxAmount = DB::connection("sqlSrvFPI")->table('payment_hdr')
            ->max('voucher');
        // dd($last);
        if ($maxAmount) {
            $newNumber = (float) $maxAmount + 1;
        } else {
            $newNumber = 1;
        }
        return $newNumber;
    }
    function getAcnumDP($dp_id)
    {
        $result = DB::connection("sqlSrvFPI")
            ->table('dp_sales')
            ->selectRaw('ac_num_dp')
            ->where('dp_id',  $dp_id)
            ->where('tr_type', 'DP')
            ->first();
        if ($result) {
            $acNumDp = $result->ac_num_dp;
        } else {
            $acNumDp = "";
        }
        return $acNumDp;
    }
    public function revisiJurnal(Request $request)
    {
        $userId = getAuth($request)->user_id2 ?? null;
        $office_id = $request->office_id;
        $year_id = $request->year_id;
        $ac_num_old = $request->ac_num_old;
        $ac_num_new = $request->ac_num_new;
        $jv_num = $request->jv_num;
        $this->serviceAcc->JournalRevise($office_id, $year_id, $jv_num, $ac_num_old, $ac_num_new, $userId);
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
}
