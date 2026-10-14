<?php

namespace App\Http\Controllers\finance;

use App\Services\{AccountingService, PerhitunganIncludeService};
use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JournalCheckingController extends Controller
{
    protected $serviceAcc;
    protected $includeService;

    public function __construct(AccountingService $serviceAcc, PerhitunganIncludeService $includeService)
    {
        $this->serviceAcc = $serviceAcc;
        $this->includeService = $includeService;
    }
    public function GetDataJournal(Request $request)
    {
        $tr_type = strtoupper(trim($request->type));
        $year_id = $request->year_id;
        try {
            switch ($tr_type) {
                case 'JOURNAL KE TRANSAKSI':
                    $result = $this->JournalToTrans($year_id);
                    $message = 'Successfully retrieved journal data';
                    break;
                case 'TRANSAKSI KE JOURNAL':
                    $result = $this->TransToJournal($year_id);
                    $message = 'Successfully retrieved journal data';
                    break;
                case 'BALANCE JOURNAL':
                    $result = $this->BalanceJournal1($year_id);
                    $message = 'Successfully retrieved journal data';
                    break;
                case 'YEARLY JOURNAL':
                    $result = $this->BalanceJournal($year_id);
                    $message = 'Successfully retrieved journal data';
                    break;
                case 'JOURNAL TO TRAN AMOUNT':
                    $result = $this->JournalToTransValue($year_id);
                    $message = 'Successfully retrieved journal data';
                    break;
                default:
                    return $this->sendError("Invalid type parameter", 'Error', 500);
            }
            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    function JournalToTrans($currYear)
    {
        $connection = DB::connection("sqlSrvFPI");
        $OtherJurnal = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('jv_trans_hdr as b', 'a.source_num', '=', 'b.tr_id')
            ->where('a.source', 'Other Journal Voucher')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.tr_id');
        $InventoryIssue = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('inv_trans_hdr as b', 'a.source_num', '=', 'b.tr_id')
            ->where('a.source', 'Inventory Issue')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.tr_id');
        $OtherDeposit = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('cash_tran_hdr as b', 'a.source_num', '=', 'b.tr_id')
            ->whereIn('a.source', ['Other Deposit', 'Other Payment'])
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.tr_id');
        $PurcDeposit = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('purc_down_payment as b', 'a.source_num', '=', 'b.dp_id')
            ->where('a.source', 'Purchase Down Payment')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.dp_id');
        $PurcInvoice = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('purc_inv_mast as b', 'a.source_num', '=', 'b.tr_id')
            ->where('a.source', 'Purchase Invoice')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.tr_id');
        $PurcPayment = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('purc_pay_mast as b', 'a.source_num', '=', 'b.pay_id')
            ->where('a.source', 'Purchase Payment')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.pay_id');
        $PurcRcv = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('purc_rcv_hdr as b', 'a.source_num', '=', 'b.purc_rcv_id')
            ->where('a.source', 'Purchase Receipt')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.purc_rcv_id');
        $Purcreturn = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('purc_rtn_hdr as b', 'a.source_num', '=', 'b.purc_rtn_id')
            ->where('a.source', 'Purchase Return')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.purc_rtn_id');
        $CustPayment = $connection->table('journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->leftjoin('payment_hdr as b', 'a.source_num', '=', 'b.pay_id')
            ->where('a.source', 'Payment Posting')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.pay_id');
        $final = $OtherJurnal
            ->union($InventoryIssue)
            ->union($OtherDeposit)
            ->union($PurcDeposit)
            ->union($PurcInvoice)
            ->union($PurcPayment)
            ->union($PurcRcv)
            ->union($Purcreturn)
            ->union($CustPayment)
            ->orderBy('source', 'desc')
            ->get();
        return $final;
    }
    function TransToJournal($currYear)
    {
        $connection = DB::connection("sqlSrvFPI");
        $OtherJurnal = $connection->table('jv_trans_hdr as a')
            ->selectRaw("distinct trim(a.tr_id) tr_id, 'Other Journal Voucher' source, trim(a.tr_id) source_num, a.dt_jv tr_date, trim(a.descr) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.tr_id')
            ->whereYear('a.dt_jv',  $currYear)
            ->whereNull('b.source_num');
        $InventoryIssue =  $connection->table('inv_trans_hdr as a')
            ->selectRaw("DISTINCT a.tr_id as tr_id,'Inventory Issue' as source, trim(a.tr_id) source_num, a.tr_date as tr_date,trim(a.remark) remark ")
            ->leftJoin('journal_voucher as b', 'a.tr_id', '=', 'b.source_num')
            ->join('inv_trans_dtl as c', function ($join) {
                $join->on('a.office_id', '=', 'c.office_id')
                    ->on('a.tr_type', '=', 'c.tr_type')
                    ->on('a.tr_id', '=', 'c.tr_id');
            })
            ->whereYear('a.tr_date', $currYear)
            ->whereIn('a.tr_type', ['IP', 'IS'])
            ->where('c.amt_item', '<>', 0)
            ->whereNull('b.source_num');
        $OtherDeposit = $connection->table('cash_tran_hdr as a')
            ->selectRaw("distinct a.tr_id as tr_id, case when a.tr_type='OD' then 'Other Deposit' else 'Other Payment' end as source,
             trim(a.tr_id) source_num, a.tr_date, trim(a.memo_txt) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.tr_id')
            ->whereYear('a.tr_date',  $currYear)
            ->whereNull('b.source_num');
        $PurcDeposit = $connection->table('purc_down_payment as a')
            ->selectRaw("DISTINCT a.dp_id as tr_id,'Purchase Down Payment' as source, trim(a.dp_id) source_num, a.dt_dp as tr_date,trim(a.remark) remark ")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.dp_id')
            ->whereYear('a.dt_dp',  $currYear)
            ->whereNull('b.source_num');
        $PurcInvoice = $connection->table('purc_inv_mast as a')
            ->selectRaw("DISTINCT a.tr_id as tr_id,'Purchase Invoice' as source, trim(a.tr_id) source_num, a.dt_inv as tr_date,trim(a.remark) remark ")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.tr_id')
            ->whereYear('a.dt_inv',  $currYear)
            ->whereNull('b.source_num');
        $PurcPayment = $connection->table('purc_pay_mast as a')
            ->selectRaw("DISTINCT a.pay_id as tr_id,'Purchase Payment' as source, trim(a.pay_id) source_num, a.dt_pay as tr_date,trim(a.remark) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.pay_id')
            ->whereYear('a.dt_pay',  $currYear)
            ->whereNull('b.source_num');
        $PurcRcv = $connection->table('purc_rcv_hdr as a')
            ->selectRaw("DISTINCT a.purc_rcv_id as tr_id,'Purchase Receipt' as source, trim(a.purc_rcv_id) source_num, a.dt_rcv as tr_date,trim(a.memo_txt) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.purc_rcv_id')
            ->whereYear('a.dt_rcv',  $currYear)
            ->whereNull('b.source_num');
        $Purcreturn = $connection->table('purc_rtn_hdr as a')
            ->selectRaw("DISTINCT a.purc_rtn_id as tr_id,'Purchase Return' as source, trim(a.purc_rtn_id) source_num, a.dt_rtn as tr_date,trim(a.remark) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.purc_rtn_id')
            ->whereYear('a.dt_rtn',  $currYear)
            ->whereNull('b.source_num');
        $CustPayment = $connection->table('payment_hdr as a')
            ->selectRaw("DISTINCT a.pay_id as tr_id,'Payment Posting' as source, trim(a.pay_id) source_num, a.tr_date as tr_date,trim(a.remark) remark")
            ->leftjoin('journal_voucher as b', 'b.source_num', '=', 'a.pay_id')
            ->whereYear('a.tr_date',  $currYear)
            ->where('a.bank_id', '<>', 'SAWAL')
            ->whereNull('b.source_num');
        $final = $OtherJurnal
            ->union($InventoryIssue)
            ->union($OtherDeposit)
            ->union($PurcDeposit)
            ->union($PurcInvoice)
            ->union($PurcPayment)
            ->union($PurcRcv)
            ->union($Purcreturn)
            ->union($CustPayment)
            ->orderBy('source', 'desc')
            ->get();
        return $final;
    }
    function BalanceJournal($currYear)
    {
        $connection = DB::connection("sqlSrvFPI");
        $result01 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_01 dr_bal,a.cr_01 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 1)
            ->where(function ($query) {
                $query->whereColumn('a.dr_01', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_01', '<>', 'b.amt_cr');
            });

        $result02 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_02 dr_bal,a.cr_02 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 2)
            ->where(function ($query) {
                $query->whereColumn('a.dr_02', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_02', '<>', 'b.amt_cr');
            });

        $result03 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_03 dr_bal,a.cr_03 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 3)
            ->where(function ($query) {
                $query->whereColumn('a.dr_03', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_03', '<>', 'b.amt_cr');
            });

        $result04 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_04 dr_bal,a.cr_04 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 4)
            ->where(function ($query) {
                $query->whereColumn('a.dr_04', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_04', '<>', 'b.amt_cr');
            });

        $result05 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_05 dr_bal,a.cr_05 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 5)
            ->where(function ($query) {
                $query->whereColumn('a.dr_05', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_05', '<>', 'b.amt_cr');
            });

        $result06 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_06 dr_bal,a.cr_06 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 6)
            ->where(function ($query) {
                $query->whereColumn('a.dr_06', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_06', '<>', 'b.amt_cr');
            });

        $result07 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_07 dr_bal,a.cr_07 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 7)
            ->where(function ($query) {
                $query->whereColumn('a.dr_07', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_07', '<>', 'b.amt_cr');
            });

        $result08 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_08 dr_bal,a.cr_08 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 8)
            ->where(function ($query) {
                $query->whereColumn('a.dr_08', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_08', '<>', 'b.amt_cr');
            });

        $result09 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_09 dr_bal,a.cr_09 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 9)
            ->where(function ($query) {
                $query->whereColumn('a.dr_09', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_09', '<>', 'b.amt_cr');
            });

        $result10 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_10 dr_bal,a.cr_10 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 10)
            ->where(function ($query) {
                $query->whereColumn('a.dr_10', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_10', '<>', 'b.amt_cr');
            });

        $result11 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_11 dr_bal,a.cr_11 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 11)
            ->where(function ($query) {
                $query->whereColumn('a.dr_11', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_11', '<>', 'b.amt_cr');
            });

        $result12 = $connection->table('g_ledger_year as a')
            ->selectRaw(
                "a.office_id,trim(a.year_id) year_id,b.month_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,a.dr_12 dr_bal,a.cr_12 cr_bal,b.amt_dr,b.amt_cr"
            )
            ->leftJoin('v_journal_monthly as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.year_id', '=', 'b.year_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.year_id', $currYear)
            ->where('b.month_id', 12)
            ->where(function ($query) {
                $query->whereColumn('a.dr_12', '<>', 'b.amt_dr')
                    ->orWhereColumn('a.cr_12', '<>', 'b.amt_cr');
            });


        $final = $result01
            ->union($result02)
            ->union($result03)
            ->union($result04)
            ->union($result05)
            ->union($result06)
            ->union($result07)
            ->union($result08)
            ->union($result09)
            ->union($result10)
            ->union($result11)
            ->union($result12)
            ->orderBy('month_id', 'asc')
            ->get();
        return $final;
    }
    function JournalToTransValue($currYear)
    {
        $connection = DB::connection("sqlSrvFPI");
        // ############# subquery  Purchase Invoice #############
        $subPI = $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Purchase Invoice')
            ->whereRaw('YEAR(tr_date) = ?', [$currYear])
            ->groupBy('jv_num', 'source', 'source_num', 'tr_date', 'remark');

        // subquery 
        $subPI2 = $connection->query()
            ->fromSub($subPI, 'x')
            ->whereRaw('ABS(amt_cr - amt_dr) < 1');

        // subquery join ke purc_inv_mast
        $subPI3 = $connection->query()
            ->fromSub($subPI2, 'y')
            ->join('purc_inv_mast as a', 'y.source_num', '=', 'a.tr_id')
            ->selectRaw(
                "y.*,
                a.rate,
                (a.amt_total * a.rate) as amt_total,
                a.amt_pph+a.amt_pph21+a.amt_pph4 as amt_pph,
                a.amt_disc,
                (a.amt_subtotal * a.rate) as amt_subtotal,
                (a.amt_ppn * a.rate) as amt_ppn,0 as rate_diff_amt,
                0 as cek_amt,
                0 as pay_amt,
                0 as disc_amt,
                0 as selisih_pembelian"
            );

        // query utama
        $resultPI = $connection->query()
            ->fromSub($subPI3, 'z')
            ->whereRaw('ABS(amt_dr - (amt_total +  amt_pph)) > 1');

        // ########## subquery Purchase Down Payment #############
        $subPdp = $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Purchase Down Payment')
            ->where('year_id', $currYear)
            ->groupBy('jv_num', 'source', 'source_num', 'tr_date', 'remark');

        // Subquery y
        $subPdp1 = $connection->query()
            ->fromSub($subPdp, 'x')
            ->whereColumn('amt_dr', '=', 'amt_cr');

        // Subquery z (join ke purc_down_payment)
        $subPdp2 = $connection->query()
            ->fromSub($subPdp1, 'y')
            ->join('purc_down_payment as a', 'a.dp_id', '=', 'y.source_num')
            ->selectRaw(
                "y.*,1 as rate,(a.dp_amt * a.rate) as amt_total, 0 as amt_pph, 0 as amt_disc, 0 as amt_subtotal, 0 as amt_ppn,0 as rate_diff_amt,
                0 as cek_amt,
                0 as pay_amt,
                0 as disc_amt,
                0 as selisih_pembelian"
            );

        // Query utama
        $resultPdp = $connection->query()
            ->fromSub($subPdp2, 'z')
            ->whereRaw('amt_dr - amt_total > 1');


        ########## subquery Purchase Payment #############
        $subPp = $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Purchase Payment')
            ->where('year_id', $currYear)
            ->groupBy('jv_num', 'source', 'source_num', 'tr_date', 'remark');

        // Subquery y
        $subPp1 = $connection->query()
            ->fromSub($subPp, 'x')
            ->whereColumn('amt_dr', '=', 'amt_cr');

        // Subquery join ke purc_pay_mast
        $subPp2 = $connection->query()
            ->fromSub($subPp1, 'y')
            ->join('purc_pay_mast as a', 'a.pay_id', '=', 'y.source_num')
            ->selectRaw(
                "y.*,
                1 as rate,
                0 as amt_total,
                0 amt_pph,
                a.disc_amt as amt_disc,
                0 amt_subtotal,
                0 amt_ppn,
                a.rate_diff_amt,
                (a.cek_amt * a.rate) as cek_amt,
                (a.pay_amt * a.rate) as pay_amt,
                (((SELECT SUM(disc_amt )
                    FROM purc_pay_item
                    WHERE pay_id = a.pay_id) * a.rate)) as disc_amt,
                ((a.cek_amt - (select sum(pay_amt) from purc_pay_item where pay_id=a.pay_id)) * a.rate) as selisih_pembelian"
            );


        // Query utama
        $resultPp = $connection->query()
            ->fromSub($subPp2, 'z')
            ->whereRaw(
                'amt_cr - pay_amt - selisih_pembelian - disc_amt - CASE WHEN rate_diff_amt > 0 THEN rate_diff_amt ELSE 0 END > 1'
            );

        // ########## subquery Customer Invoice #############
        $subCi =  $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Customer Invoice')
            ->where('year_id', $currYear)
            ->groupBy('jv_num', 'source', 'source_num', 'tr_date', 'remark');

        // Subquery y
        $subCi1 = $connection->query()
            ->fromSub($subCi, 'x')
            ->whereColumn('amt_dr', '=', 'amt_cr');

        // Join ke penjualan
        $subCi2 = $connection->query()
            ->fromSub($subCi1, 'y')
            ->join('penjualan as a', 'a.penjualanno', '=', 'y.source_num')
            ->selectRaw(
                "y.*,
                1 as rate,
                a.totaljual as amt_total,
                0 amt_pph,
                a.discount as amt_disc,
                0 amt_subtotal,
                0 amt_ppn,
                0 as rate_diff_amt,
                0 as cek_amt,
                0 as pay_amt,
                0 as disc_amt,
                0 as selisih_pembelian"
            );

        // Query utama
        $resultCi = $connection->query()
            ->fromSub($subCi2, 'z')
            ->whereRaw('(amt_dr - amt_total - amt_disc) > 1');

        // ########## subquery Customer Deposit #############
        $subCd =  $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Customer Deposit')
            ->where('year_id', $currYear)
            ->groupBy('jv_num', 'source', 'source_num', 'tr_date', 'remark');

        // Subquery y
        $subCd1 = $connection->query()
            ->fromSub($subCd, 'x')
            ->whereColumn('amt_dr', '=', 'amt_cr');

        // Subquery join dp_sales
        $subCd2 = $connection->query()
            ->fromSub($subCd1, 'y')
            ->join('dp_sales as a', 'a.dp_id', '=', 'y.source_num')
            ->selectRaw(
                "y.*,
                1 as rate,
                (a.dp_amt * a.rate) as amt_total, 0 amt_pph, 0 amt_disc, 0 amt_subtotal, 0 amt_ppn,0 as rate_diff_amt,
                0 as cek_amt,
                0 as pay_amt,
                0 as disc_amt,
                0 as selisih_pembelian"
            );

        // Query utama
        $resultCd = $connection->query()
            ->fromSub($subCd2, 'z')
            ->whereRaw('(amt_dr - amt_total) > 1');

        // ########## subquery Payment Posting #############
        $subPo =  $connection->table('journal_voucher')
            ->selectRaw(
                "jv_num,trim(source) source,trim(source_num) source_num,tr_date,remark,SUM(amt_cr) as amt_cr,SUM(amt_dr) as amt_dr"
            )
            ->where('source', 'Payment Posting')
            ->whereRaw('YEAR(tr_date) = ?', [$currYear])
            ->whereNotIn('ac_num', ['130-010-003', '217-010-002'])
            ->groupBy('jv_num', 'source_num', 'source', 'tr_date', 'remark');

        // Subquery y
        $subPo1 = $connection->query()
            ->fromSub($subPo, 'x')
            ->whereColumn('amt_dr', '=', 'amt_cr');

        // Join ke payment_hdr
        $subPo2 = $connection->query()
            ->fromSub($subPo1, 'y')
            ->join('payment_hdr as a', 'y.source_num', '=', 'a.pay_id')
            ->selectRaw(
                "y.*,
                (a.pay_amt * a.rate) as pay_amt,
                a.disc_amt,
                a.selisih_penj"
            );

        // Tambahkan perhitungan amt_total
        $subPo3 = $connection->query()
            ->fromSub($subPo2, 'z')
            ->selectRaw(
                "z.jv_num,
                z.source,
                z.source_num,
                z.tr_date,
                z.remark,
                z.amt_cr,
                z.amt_dr,
                1 rate,
                ((select sum(pay_amt) from payment_dtl where pay_id=z.source_num) + z.disc_amt + z.selisih_penj) as amt_total,
                0 amt_pph,
                0 as amt_disc,
                0 amt_subtotal,
                0 amt_ppn,0 as rate_diff_amt,
                0 as cek_amt,
                0 as pay_amt,
                0 as disc_amt,
                0 as selisih_pembelian"
            );

        // Query utama
        $resultPo = $connection->query()
            ->fromSub($subPo3, 'a')
            ->whereColumn('amt_dr', '<>', 'amt_total');
        $ResultFinal = $resultPI
            ->union($resultCd)
            ->union($resultCi)
            ->union($resultPdp)
            ->union($resultPo)
            ->union($resultPp)
            ->get();
        return $ResultFinal;
    }
    function BalanceJournal1($currYear)
    {
        $connection = DB::connection("sqlSrvFPI");
        $ResultBalance = $connection->table('v_journal_voucher as a')
            ->selectRaw("distinct a.jv_num, trim(source) source, trim(source_num) source_num, a.tr_date, trim(a.remark) remark")
            ->whereRaw('round(amt_dr,0) <> round(amt_cr,0)')
            ->whereYear('a.tr_date',  $currYear)
            ->orderBy('Source', 'asc')
            ->orderBy('tr_date', 'asc')
            ->get();
        return $ResultBalance;
    }
    public function DeleteJurnalNoTransaction(Request $request)
    {
        try {
            $year_id = $request->year_id;
            $result = $this->JournalToTrans($year_id);
            if ($result) {
                foreach ($result as $key => $detail) {
                    $this->serviceAcc->DeleteJournalByJvNum(1, $detail->jv_num, $year_id);
                }
            }
            return $this->sendResponse(null, "Successfully deleted journal data");
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function UpdateJournalYear(Request $request)
    {
        try {
            $year_id = $request->year_id;
            $result = $this->BalanceJournal($year_id);
            if ($result) {
                collect($result)->chunk(100)->each(function ($chunk) {
                    foreach ($chunk as $detail) {
                        $formattedNumber = str_pad($detail->month_id, 2, '0', STR_PAD_LEFT);

                        DB::connection('sqlSrvFPI')->table('g_ledger_year')
                            ->where('office_id', $detail->office_id)
                            ->where('year_id', $detail->year_id)
                            ->where('ac_num', $detail->ac_num)
                            ->update([
                                'dr_' . $formattedNumber => $detail->amt_dr,
                                'cr_' . $formattedNumber => $detail->amt_cr,
                            ]);
                    }
                });
                // $payload = [];

                // foreach ($result as $detail) {
                //     $formattedNumber = str_pad($detail->month_id, 2, '0', STR_PAD_LEFT);

                //     $payload[] = [
                //         'office_id' => $detail->office_id,
                //         'year_id'   => $detail->year_id,
                //         'ac_num'    => $detail->ac_num,
                //         'dr_' . $formattedNumber => $detail->amt_dr,
                //         'cr_' . $formattedNumber => $detail->amt_cr,
                //     ];
                // }

                // DB::connection('sqlSrvFPI')->table('g_ledger_year')->upsert(
                //     $payload,
                //     ['office_id', 'year_id', 'ac_num'],  // composite key
                //     collect($payload)->flatMap(fn($row) => array_keys($row))->unique()
                //         ->reject(fn($col) => in_array($col, ['office_id', 'year_id', 'ac_num']))
                //         ->values()
                //         ->toArray()
                // );

            }
            return $this->sendResponse(null, "Successfully updated journal data");
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function createJournal(Request $request)
    {
        try {
            // $datas = [];
            $jurnalData = $request->input('items');
            // dd($jurnalData);
            $userId = getAuth($request)->user_id2;
            $officeId = $request->input('office_id');



            foreach ($jurnalData as $key => $item) {

                $tanggal = Carbon::parse($item['tr_date']);
                $tahun4digit = $tanggal->format('Y');

                $source = $item['source'];
                $TrID = $item['source_num'];
                // dd($source);
                if ($source == 'Other Deposit') {
                    $this->serviceAcc->JrnlOtherDeposit($officeId, $TrID, $tahun4digit, $userId); //- Other Deposit
                } else if ($source == 'Other Payment') {
                    $this->serviceAcc->JrnlOtherPayment($officeId, $TrID, $tahun4digit, $userId); //- Other Payment
                } else if ($source == 'Kas Bon Internal') {
                    $this->serviceAcc->JrnlkasBonInternal($officeId, $TrID, $tahun4digit, $userId); //- Kas Bon Internal
                } else if ($source == 'Other Journal Voucher') {
                    $this->serviceAcc->JrnlOtherJournal($officeId, $TrID, $tahun4digit, $userId); //- Other Journal Voucher
                } else if ($source == 'Customer Deposit') {
                    $this->serviceAcc->JrnlCustomerDeposit($officeId, $TrID, $tahun4digit, $userId); //- Customer Deposit
                } else if ($source == 'Purchase Down Payment') {
                    $this->serviceAcc->JrnlPurcDP($officeId, $TrID, $tahun4digit, $userId); //- Purchase Down Payment
                } else if ($source == 'Purchase Payment') {
                    $this->serviceAcc->JrnlPurcPayment($officeId, $TrID, $tahun4digit, $userId); //- Purchase Payment
                } else if ($source == 'Purchase Receipt') {
                    $this->serviceAcc->JrnlPurcReceive($officeId, $TrID, $tahun4digit, $userId); //- Purchase Receipt
                } else if ($source == 'Payment Posting') {
                    $this->serviceAcc->JrnlCustPayment($officeId, $TrID, $tahun4digit, $userId); //- Payment Posting
                } else if ($source == 'Purchase Invoice') {
                    //ambil inv_type dari tabel purc_rcv_mast, jika kosong pakai purcinv jika ada N pakai purcinvnonpo
                    $invType = DB::connection('sqlSrvFPI')->table('purc_inv_mast')->where('tr_id', $TrID)->value('inv_type');
                    if ($invType == 'N') {
                        $inv = DB::connection('sqlSrvFPI')->selectOne("
                                SELECT qc_opname.*, 
                                vendor.tax1,
                                vendor.tax2,
                                vendor.tax3,
                                vendor.tax4,
                                vendor.ppn_21,
                                vendor.ppn_23,
                                purc_inv_mast.reff_id,
                                purc_inv_mast.inv_id,
                                spk_hdr.id_site,
                                spk_hdr.id_cluster,
                                spk_hdr.id_house_unit
                                FROM purc_inv_mast as purc_inv_mast WITH (NOLOCK)
                                INNER JOIN qc_opname AS qc_opname ON qc_opname.tr_id = purc_inv_mast.reff_id
                                INNER JOIN spk_hdr AS spk_hdr ON spk_hdr.id_spk = qc_opname.spk_id
                                INNER JOIN vendor AS vendor ON vendor.vendor_id = purc_inv_mast.vendor_id 
                                WHERE purc_inv_mast.tr_id = ?
                            ", [trim($TrID)]);

                        if ($inv) {
                            DB::connection('sqlSrvFPI')->beginTransaction();

                            try {
                                // $ppn = DB::connection('sqlSrvFPI')
                                //     ->table('appl_constant')
                                //     ->where('key_code', 'PPN')
                                //     ->first();

                                // $pct21 = DB::connection('sqlSrvFPI')
                                //     ->table('appl_constant')
                                //     ->where('key_code', 'PPH21')
                                //     ->first();

                                // $pct23 = DB::connection('sqlSrvFPI')
                                //     ->table('appl_constant')
                                //     ->where('key_code', 'PPH23')
                                //     ->first();

                                // $percent_ppn = $ppn ? $ppn->int1 : 11;
                                // $percent_ppn_21 = $pct21 ? (float) $pct21->str3 : 0;
                                // $percent_ppn_23 = $pct23 ? (float) $pct23->str3 : 0;
                                // // $percent_ppn_21 = $inv->ppn_21; 
                                // // $percent_ppn_23 = $inv->ppn_23; 
                                // $type_ppn = $inv->tax1;

                                // $amt_ppn = $percent_ppn / 100;
                                // $amt_pph21 = $percent_ppn_21 / 100;
                                // $amt_pph23 = $percent_ppn_23 / 100;

                                DB::connection('sqlSrvFPI')->table('purc_inv_mast')->where('tr_id', $TrID)->update([
                                    // 'tax1' => trim($inv->tax1), // type PPN
                                    // 'tax2' => trim($inv->tax2), // PPH 23 type
                                    // 'pct_pph' => $percent_ppn_23, // PCT PPH 23
                                    // 'tax3' => trim($inv->tax3), // PPH 4 type
                                    // 'tax4' => trim($inv->tax4), // PPH 21
                                    // 'pct_pph21' => $percent_ppn_21, // PCT PPH 21
                                    'tax1' => 'E',
                                    'tax2' => 'E',
                                    'tax3' => 'N',
                                    'tax4' => 'E',
                                    'pct_pph21' => 2.5,
                                    'pct_pph' => 2,

                                ]);
                                DB::connection('sqlSrvFPI')->table('purc_inv_item')->where('tr_id', $TrID)->delete();

                                $qc_opname_dtls = DB::connection('sqlSrvFPI')->select("
                                    SELECT
                                        qc_opname_dtl.*,
                                        commodity.com_desc
                                    FROM qc_opname_dtl
                                    INNER JOIN qc_item_commodity commodity
                                        ON commodity.com_id = qc_opname_dtl.com_id
                                    WHERE qc_opname_dtl.tr_id = ?
                                    ORDER BY qc_opname_dtl.item_num
                                ", [$inv->reff_id]);

                                foreach ($qc_opname_dtls as $key => $value) {
                                    // $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                                    //     $value->amt_final,
                                    //     0.11,
                                    //     0.2,
                                    //     0.25,
                                    //     ['E', 'E', 'N', 'T'],
                                    //     0
                                    // );
                                    DB::connection('sqlSrvFPI')
                                        ->table('purc_inv_item')
                                        ->insert([
                                            'office_id' => 1,
                                            'tr_id' => $TrID,
                                            'seq_num' => $value->item_num,
                                            'inv_id' => $inv->inv_id,
                                            'inv_item' => $value->item_num,
                                            'article_id' => '03000145',
                                            'qty' => 1,
                                            'unit_meas' => 'PCS',
                                            'unit_price' => $value->amt_final,
                                            'amt_item' => $value->amt_final,
                                            'id_site' => $inv->id_site,
                                            'id_cluster' => $inv->id_cluster,
                                            'id_house' => $inv->id_house_unit,
                                            'dt_created' => now(),
                                            'user_id' => getAuth($request)->user_id2 ?? null,
                                            'ac_num_bbt' => '211-010-002',
                                            'ac_num_diff_rate' => '510-010-004',
                                            'remark' => $value->com_desc . ' ' .  $value->description,
                                            'wgt_unit' => 0,
                                            'um_price' => 0,
                                        ]);
                                }

                                if ($inv->amt_adj != 0) {
                                    $seq_num = DB::connection('sqlSrvFPI')->table('purc_inv_item')
                                        ->select('seq_num')
                                        ->where('tr_id', $TrID)
                                        ->max('seq_num');
                                    $item_num = $seq_num + 1;

                                    // $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
                                    //     $inv->amt_adj,
                                    //     0.11,
                                    //     0.2,
                                    //     0.25,
                                    //     ['E', 'E', 'E', 'N'],
                                    //     0
                                    // );
                                    
                                    DB::connection('sqlSrvFPI')
                                    ->table('purc_inv_item')
                                    ->insert([
                                        'office_id' => 1,
                                        'tr_id' => $TrID,
                                        'seq_num' => $item_num,
                                        'inv_id' => $inv->inv_id,
                                        'inv_item' => $item_num,
                                        'article_id' => '03000314', //article adjust
                                        'qty' => 1,
                                        'unit_meas' => 'PCS',
                                        'unit_price' => $inv->amt_adj,
                                        'amt_item' => $inv->amt_adj,
                                        'id_site' => $inv->id_site,
                                        'id_cluster' => $inv->id_cluster,
                                        'id_house' => $inv->id_house_unit,
                                        'dt_created' => now(),
                                        'user_id' => $request->user_id2 ? $request->user_id2 : ' ',
                                        'ac_num_bbt' => '211-010-002',
                                        'ac_num_diff_rate' => '510-010-004',
                                        'remark' => 'ADJUSTMENT CLOSING SHEET',
                                        'wgt_unit' => 0,
                                        'um_price' => 0,
                                    ]);
                                }

                                DB::connection('sqlSrvFPI')->commit();

                                $this->updateInvMaster($TrID, $inv->inv_id, 0.11, 0.025, 0.02, $inv);
                            } catch (\Exception $e) {
                                DB::connection('sqlSrvFPI')->rollBack();
                                return $this->sendError($e->getMessage(), 'Error', 500);
                            }
                        }

                        $this->serviceAcc->JrnlPurcInvNonPO($officeId, $TrID, $tahun4digit, $userId); //- Purchase Invoice Non PO
                    } else {
                        $this->serviceAcc->JrnlPurcInv($officeId, $TrID, $tahun4digit, $userId); //- Purchase Invoice
                    }
                }
                //JrnlPurcInvNonPO($officeId, $TrID, $tahun4digit, $userId) - Purchase Invoice
                //JrnlPurcInv($officeId, $TrID, $tahun4digit, $userId) - Purchase Invoice

            }
            // return response()->json(['message' => 'Order not exists'], 500);
            return $this->sendResponse('success', 'Successfully create journal data');
        } catch (\Exception $e) {
            // return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function updateInvMaster($TrID, $invId, $amt_ppn, $amt_pph21, $amt_pph23, $inv)
    {
        $sum_amt = DB::connection('sqlSrvFPI')->selectOne("
                        SELECT 
                            SUM(amt_item) as amt
                        FROM purc_inv_item WITH (NOLOCK)
                        WHERE office_id = 1 AND tr_id = ?  AND inv_id = ?
                    ", [trim($TrID), trim($invId)]);

        $amt = $sum_amt ? ($sum_amt->amt ? $sum_amt->amt : 0) : 0;

        $calcHargaInEx = $this->includeService->hitungHargaDasarIncludeExclude(
            $amt,
            ((float) $amt_ppn),
            ((float) $amt_pph23),
            ((float) $amt_pph21),
            [trim($inv->tax1), trim($inv->tax2), trim($inv->tax4), trim($inv->tax3)],
            0
        );

        $subtotal = $calcHargaInEx[0];
        $total_ppn = $calcHargaInEx[1];
        $total_pph23 = $calcHargaInEx[2];
        $total_pph21 = $calcHargaInEx[3];
        $total = $calcHargaInEx[5];

        $terbilang = terbilang($total);

        DB::connection('sqlSrvFPI')->update(
            "UPDATE purc_inv_mast
             SET amt_subtotal = ?, inv_said = ? , amt_ppn = ? , amt_pph = ? , amt_pph21 = ?, amt_total = ?
             WHERE office_id = 1  AND tr_id = ?",
            [$subtotal, $terbilang, $total_ppn, $total_pph23, $total_pph21, $total, $TrID]
        );
    }
}
