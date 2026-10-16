<?php

namespace App\Http\Controllers\master;

use App\Traits\getFilterTrait;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class gLedgerYearController extends Controller
{
    use getFilterTrait;

    public function index(Request $request)
    {
        $year_id = $request->year_id;
        $ac_type = $request->ac_type ?: '';
        $ac_num = $request->ac_num ?: '';
        $sqlWhere = "1=1";
        if (!$year_id) {
            return $this->sendError("Fill year id", 'Error', 500);
        }
        if ($year_id) {
            $sqlWhere .= " AND  a.year_id = '$year_id' ";
        }
        if ($ac_type) {
            $sqlWhere .= " AND b.ac_type = '$ac_type' ";
        }
        if ($ac_num) {
            $sqlWhere .= " AND b.ac_num = '$ac_num' ";
        }
        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            SELECT trim(year_id) year_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,trim(b.drcr) drcr,amt_type,amt_begin_dr,amt_begin_cr,dr_bal,cr_bal,case when b.drcr='dr' then amt_begin_dr-amt_begin_cr+dr_bal-cr_bal
            else amt_begin_cr-amt_begin_dr+cr_bal-dr_bal end as amt_balance,frg_amt_begin_dr,frg_amt_begin_cr,dr_bal_frg,cr_bal_frg
            ,case when b.drcr='dr' then frg_amt_begin_dr-frg_amt_begin_cr+dr_bal_frg-cr_bal_frg
            else frg_amt_begin_cr-frg_amt_begin_dr+cr_bal_frg-dr_bal_frg end as amt_balance_frg
            FROM g_ledger_year a inner join g_ledger b on a.ac_num=b.ac_num 
            WHERE $sqlWhere
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }
    public function DisplayDetailLedgerbyId(Request $request)
    {
        $year_id = $request->year_id;
        $ac_num = $request->ac_num ?: '';
        $sqlWhere = "1=1";

        if ($year_id) {
            $sqlWhere .= " AND  a.year_id = '$year_id' ";
        }
        if ($ac_num) {
            $sqlWhere .= " AND b.ac_num = '$ac_num' ";
        }
        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            SELECT trim(year_id) year_id,trim(a.ac_num) ac_num,trim(b.ac_name) ac_name,trim(b.ac_type) ac_type,trim(b.curr_id) curr_id,trim(b.drcr) drcr
            ,amt_begin_dr,amt_begin_cr,dr_01,cr_01,dr_02
            ,cr_02,dr_03,cr_03,dr_04,cr_04,dr_05,cr_05,dr_06,cr_06,dr_07,cr_07
            ,dr_08,cr_08,dr_09,cr_09,dr_10,cr_10,dr_11,cr_11,dr_12,cr_12,dr_bal
            ,cr_bal,frg_amt_begin_dr,frg_amt_begin_cr,dr_01_frg,cr_01_frg,dr_02_frg,cr_02_frg
            ,dr_03_frg,cr_03_frg,dr_04_frg,cr_04_frg,dr_05_frg,cr_05_frg,dr_06_frg,cr_06_frg
            ,dr_07_frg,cr_07_frg,dr_08_frg,cr_08_frg,dr_09_frg,cr_09_frg,dr_10_frg,cr_10_frg
            ,dr_11_frg,cr_11_frg,dr_12_frg,cr_12_frg,dr_bal_frg,cr_bal_frg
            FROM bcki_ol.dbo.g_ledger_year a inner join g_ledger b on a.ac_num=b.ac_num
            WHERE $sqlWhere
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }
    public function DisplayTransactionDetailbyAcNum(Request $request)
    {
        $year_id = $request->year_id;
        $ac_num = $request->ac_num ?: '';
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $period = $request->period;
        $sqlWhere = "  a.office_id = 1 ";

        if ($year_id) {
            $sqlWhere .= " AND  a.year_id = '$year_id' ";
        }
        if ($ac_num) {
            $sqlWhere .= " AND a.ac_num = '$ac_num' ";
        }
        if ($period) {
            $sqlWhere .= " AND format( a.tr_date, 'yyyyMM') = '$period' ";
        }
        if ($start_date) {
            $sqlWhere .= " AND a.tr_date >=  CONVERT(datetime, '$start_date 00:00:00.001') ";
        }
        if ($end_date) {
            $sqlWhere .= " AND a.tr_date <=  CONVERT(datetime, '$end_date 23:59:59.001') ";
        }
        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            SELECT a.jv_num,trim(a.ac_num) ac_num, trim(c.ac_name) ac_name,trim(a.source) source,trim(a.source_num) source_num,
            a.tr_date,a.amt_dr,a.amt_cr,trim(a.remark) remark,isnull(d.namacustomer,'') nama,
            isnull((select  STUFF((SELECT  ','+cast(ltrim(rtrim(inv_id)) AS VARCHAR ) FROM  payment_dtl where pay_id=a.source_num  FOR XML PATH('')),1,1,'') 
            from payment_dtl  where pay_id=a.source_num  group by pay_id),'') invID ,format( a.tr_date, 'yyyyMM') periodtr 
            FROM journal_voucher a   
            JOIN g_ledger c ON a.office_id = c.office_id  AND a.ac_num = c.ac_num  
            left outer join payment_hdr b on a.source_num=b.pay_id  
            left outer join customer d on b.customerid=d.customerid  
            WHERE $sqlWhere
            ORDER BY Source,a.tr_date 
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }
    public function store(Request $request)
    {
        $data = [
            'office_id'   => $request->office_id,
            'year_id'       => $request->year_id,
            'ac_num'    => $request->ac_num,
            'amt_type'   => $request->amt_type,
            'amt_begin_dr'       => $request->amt_begin_dr ?: 0,
            'amt_begin_cr'    => $request->amt_begin_cr ?: 0,
            'dr_01'   => $request->dr_01 ?: 0,
            'cr_01'       => $request->cr_01 ?: 0,
            'dr_02'    => $request->dr_02 ?: 0,
            'cr_02'   => $request->cr_02 ?: 0,
            'dr_03'       => $request->dr_03 ?: 0,
            'cr_03'    => $request->cr_03 ?: 0,
            'dr_04'   => $request->dr_04 ?: 0,
            'cr_04'       => $request->cr_04 ?: 0,
            'dr_05'    => $request->dr_05 ?: 0,
            'cr_05'   => $request->cr_05 ?: 0,
            'dr_06'       => $request->dr_06 ?: 0,
            'cr_06'    => $request->cr_06 ?: 0,
            'dr_07'   => $request->dr_07 ?: 0,
            'cr_07'       => $request->cr_07 ?: 0,
            'dr_08'    => $request->dr_08 ?: 0,
            'cr_08'       => $request->cr_08 ?: 0,
            'dr_09'    => $request->dr_09 ?: 0,
            'cr_09'   => $request->cr_09 ?: 0,
            'dr_10'       => $request->dr_10 ?: 0,
            'cr_10'    => $request->cr_10 ?: 0,
            'dr_11'   => $request->dr_11 ?: 0,
            'cr_11'       => $request->cr_11 ?: 0,
            'dr_12'    => $request->dr_12 ?: 0,
            'cr_12'       => $request->cr_12 ?: 0,
            'dr_bal'    => $request->dr_bal ?: 0,
            'cr_bal'       => $request->cr_bal ?: 0,
            'frg_amt_begin_dr'       => $request->frg_amt_begin_dr ?: 0,
            'frg_amt_begin_cr'    => $request->frg_amt_begin_cr ?: 0,
            'dr_01_frg'   => $request->dr_01_frg ?: 0,
            'cr_01_frg'       => $request->cr_01_frg ?: 0,
            'dr_02_frg'   => $request->dr_02_frg ?: 0,
            'cr_02_frg'       => $request->cr_02_frg ?: 0,
            'dr_03_frg'   => $request->dr_03_frg ?: 0,
            'cr_03_frg'       => $request->cr_03_frg ?: 0,
            'dr_04_frg'   => $request->dr_04_frg ?: 0,
            'cr_04_frg'       => $request->cr_04_frg ?: 0,
            'dr_05_frg'   => $request->dr_05_frg ?: 0,
            'cr_05_frg'       => $request->cr_05_frg ?: 0,
            'dr_06_frg'   => $request->dr_06_frg ?: 0,
            'cr_06_frg'       => $request->cr_06_frg ?: 0,
            'dr_07_frg'   => $request->dr_07_frg ?: 0,
            'cr_07_frg'       => $request->cr_07_frg ?: 0,
            'dr_08_frg'   => $request->dr_08_frg ?: 0,
            'cr_08_frg'       => $request->cr_08_frg ?: 0,
            'dr_09_frg'   => $request->dr_09_frg ?: 0,
            'cr_09_frg'       => $request->cr_09_frg ?: 0,
            'dr_10_frg'   => $request->dr_10_frg ?: 0,
            'cr_10_frg'       => $request->cr_10_frg ?: 0,
            'dr_11_frg'   => $request->dr_11_frg ?: 0,
            'cr_11_frg'       => $request->cr_11_frg ?: 0,
            'dr_12_frg'   => $request->dr_12_frg ?: 0,
            'cr_12_frg'       => $request->cr_12_frg ?: 0,
            'dr_bal_frg'   => $request->dr_bal_frg ?: 0,
            'cr_bal_frg'       => $request->cr_bal_frg ?: 0,
            'user_id'     => getAuth($request)->user_id2,
            'dt_modified' => now(),
            'dt_created' => now(),
        ];
        try {
            DB::connection("sqlSrvBCKI")->table('g_ledger_year')->upsert(
                [$data],
                ['office_id', 'year_id', 'ac_num'],
                [
                    'amt_type',
                    'amt_begin_dr',
                    'amt_begin_cr',
                    'dr_01',
                    'cr_01',
                    'dr_02',
                    'cr_02',
                    'dr_03',
                    'cr_03',
                    'dr_04',
                    'cr_04',
                    'dr_05',
                    'cr_05',
                    'dr_06',
                    'cr_06',
                    'dr_07',
                    'cr_07',
                    'dr_08',
                    'cr_08',
                    'dr_09',
                    'cr_09',
                    'dr_10',
                    'cr_10',
                    'dr_11',
                    'cr_11',
                    'dr_12',
                    'cr_12',
                    'dr_bal',
                    'cr_bal',
                    'frg_amt_begin_dr',
                    'frg_amt_begin_cr',
                    'dr_01_frg',
                    'cr_01_frg',
                    'dr_02_frg',
                    'cr_02_frg',
                    'dr_03_frg',
                    'cr_03_frg',
                    'dr_04_frg',
                    'cr_04_frg',
                    'dr_05_frg',
                    'cr_05_frg',
                    'dr_06_frg',
                    'cr_06_frg',
                    'dr_07_frg',
                    'cr_07_frg',
                    'dr_08_frg',
                    'cr_08_frg',
                    'dr_09_frg',
                    'cr_09_frg',
                    'dr_10_frg',
                    'cr_10_frg',
                    'dr_11_frg',
                    'cr_11_frg',
                    'dr_12_frg',
                    'cr_12_frg',
                    'dr_bal_frg',
                    'cr_bal_frg',
                    'dt_created',
                    'dt_modified',
                    'user_id'
                ]
            );

            return $this->sendResponse(null, 'Successfully update g ledger year');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function displayCoa(Request $request)
    {
        $ac_type = $request->ac_type;
        $sqlWhere = " 1 = 1 ";

        if ($ac_type) {
            $sqlWhere .= " AND  ac_type = '$ac_type' ";
        }
        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            SELECT trim(ac_num) ac_num,trim(ac_name) ac_name 
            from g_ledger
            WHERE $sqlWhere
            ORDER BY ac_num 
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }
}
