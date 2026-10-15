<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AccountingController extends Controller
{

    public function ShowJurnal(Request $request)
    {
        $source_num = $request->source_num;
        $source = $request->source;
        if (empty($source_num)) {
            return $this->sendError("Empty source number", 'Error', 500);
        }
        try {
            $connection = DB::connection("sqlSrvBCKI");
            $result = $connection->table('journal_voucher as a')
                ->select('a.tr_date', 'a.source_num', 'a.source', 'a.ac_num', 'b.ac_name as description', 'a.amt_dr', 'a.amt_cr', 'a.remark')
                ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
                ->where('source_num', $source_num)
                ->where('source', $source)
                ->get();
            $message = 'Successfully retrieved journal data';

            return $this->sendResponse($result, $message);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    // function saveJournal(
    //     int $sMill,
    //     float $JvNum,
    //     string $AcNum,
    //     string $src,
    //     string $srcnum,
    //     string $DtTrx,
    //     float $Amt,
    //     string $DrCr,
    //     string $CustID,
    //     string $VendorID,
    //     string $InvID,
    //     string $Remark,
    //     string $NoCek,
    //     float $exch_rate = 1,
    //     string $CurrID,
    //     string $userID
    // ) {
    //     $connection = DB::connection("sqlSrvBCKI");
    //     $year = Carbon::parse($DtTrx)->year;
    //     // cari record
    //     $journal = $connection->table('journal_voucher')
    //         ->where('office_id', $sMill)
    //         ->where('jv_num', $JvNum)
    //         ->where('ac_num', $AcNum)
    //         ->where('year_id', $year)
    //         ->first();

    //     if (!$journal) {
    //         // Insert new journal
    //         $connection->table('journal_voucher')->insert([
    //             'office_id'   => $sMill,
    //             'jv_num'      => $JvNum,
    //             'ac_num'      => $AcNum,
    //             'year_id'     => $year,
    //             'dt_created'  => Carbon::now(),
    //             'source'      => $src,
    //             'source_num'  => $srcnum,
    //             'tr_date'     => $DtTrx,
    //             'amt_dr'      => strtoupper(trim($DrCr)) === 'DR' ? $Amt : 0,
    //             'amt_cr'      => strtoupper(trim($DrCr)) === 'CR' ? $Amt : 0,
    //             'DrCr'        => strtoupper(trim($DrCr)),
    //             'cust_id'     => $CustID,
    //             'vendor_id'   => $VendorID,
    //             'curr_id'     => $CurrID,
    //             'Rate'        => $exch_rate ?: 1,
    //             'cek_id'      => $NoCek,
    //             'inv_id'      => $InvID,
    //             'Remark'      => $Remark,
    //             'dt_modified' => Carbon::now(),
    //             'user_id'     => $userID
    //         ]);
    //     } else {
    //         // Update existing journal
    //         $amt_dr = $journal->amt_dr ?? 0;
    //         $amt_cr = $journal->amt_cr ?? 0;

    //         if (strtoupper(trim($DrCr)) === 'DR') {
    //             $amt_dr += $Amt;
    //         } else {
    //             $amt_cr += $Amt;
    //         }

    //         $connection->table('journal_voucher')
    //             ->where('office_id', $sMill)
    //             ->where('jv_num', $JvNum)
    //             ->where('ac_num', $AcNum)
    //             ->where('year_id', $year)
    //             ->update([
    //                 'source'      => $src,
    //                 'source_num'  => $srcnum,
    //                 'tr_date'     => $DtTrx,
    //                 'amt_dr'      => $amt_dr,
    //                 'amt_cr'      => $amt_cr,
    //                 'DrCr'        => $amt_dr == 0 ? 'cr' : 'dr',
    //                 'cust_id'     => $CustID,
    //                 'vendor_id'   => $VendorID,
    //                 'curr_id'     => $CurrID,
    //                 'Rate'        => $exch_rate ?: 1,
    //                 'cek_id'      => $NoCek,
    //                 'inv_id'      => $InvID,
    //                 'Remark'      => $Remark,
    //                 'dt_modified' => Carbon::now(),
    //                 'user_id'     => $userID
    //             ]);
    //     }
    // }
    // function getJvNum(int $MillID, string $Src, string $SrcNum, string $CurrYear)
    // {
    //     try {
    //         $connection = DB::connection("sqlSrvBCKI");
    //         $result = $connection->table('journal_voucher')
    //             ->select('jv_num')
    //             ->where('office_id', $MillID)
    //             ->where('source',  $Src)
    //             ->where('source_num', $SrcNum)
    //             ->where('year_id', $CurrYear)
    //             ->first();
    //         if ($result) {
    //             return $this->$result->jv_num;
    //         } else {
    //             return 0;
    //         }
    //     } catch (\Throwable $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }
    // function getNextJvNum(int $MillID,  string $CurrYear)
    // {
    //     try {
    //         $connection = DB::connection("sqlSrvBCKI");
    //         $result = $connection->table('journal_voucher')
    //             ->selectRaw("max(jv_num)+1 as lastId")
    //             ->where('office_id', $MillID)
    //             ->where('year_id', $CurrYear)
    //             ->first();
    //         if ($result) {
    //             return $this->$result->lastId + 1;
    //         } else {
    //             return "1";
    //         }
    //     } catch (\Throwable $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }
    // function DeleteJournalByJvNum(int $MillID, float $JvNum, string $CurrYear)
    // {
    //     DB::connection("sqlSrvBCKI")->beginTransaction();
    //     try {

    //         DB::connection('sqlSrvBCKI')->table('journal_voucher')
    //             ->where('office_id', $MillID)
    //             ->where('jv_num',  $JvNum)
    //             ->where('year_id', $CurrYear)
    //             ->update([
    //                 'amt_dr' => 0,
    //                 'amt_cr' => 0
    //             ]);
    //         $getJv = DB::connection('sqlSrvBCKI')->table('journal_voucher')
    //             ->select('ac_num', 'tr_date')
    //             ->where('office_id', $MillID)
    //             ->where('jv_num',  $JvNum)
    //             ->where('year_id', $CurrYear)
    //             ->get();

    //         foreach ($getJv as $key => $detail) {
    //             $bulan2digit = date('m', strtotime($detail['tr_date']));
    //             $this->updateGL($MillID, $detail['ac_num'], $CurrYear, $bulan2digit);
    //         }
    //         DB::connection('sqlSrvBCKI')->table('journal_voucher')
    //             ->where('office_id', $MillID)
    //             ->where('jv_num', $JvNum)
    //             ->where('year_id', $CurrYear)
    //             ->delete();
    //         DB::connection("sqlSrvBCKI")->commit();
    //     } catch (\Throwable $e) {
    //         DB::connection("sqlSrvBCKI")->rollBack();
    //         return false;
    //     }
    // }
    // function updateGL($sMill, $acNo, $bulan, $tahun)
    // {
    //     try {

    //         // Step 1: get total debit dan credit untuk coa di tahun dan bulan
    //         $journal = DB::connection("sqlSrvBCKI")->table('journal_voucher as a')
    //             ->select(DB::raw('SUM(amt_dr) as amt_dr'), DB::raw('SUM(amt_cr) as amt_cr'), 'b.drcr')
    //             ->join('g_ledget as b', 'b.ac_num', '=', 'a.ac_num')
    //             ->where('a.office_id', $sMill)
    //             ->where('a.ac_num', $acNo)
    //             ->whereYear('a.tr_date', $tahun)
    //             ->whereMonth('a.tr_date', $bulan)
    //             ->groupBy('d.drcr')
    //             ->first();

    //         if (!$journal || ($journal->amt_dr === null && $journal->amt_cr === null)) {
    //             return false;
    //         } else {
    //             $sdrcr = $journal ? trim($journal->drcr) : null;
    //         }

    //         // Step 2: Insert or update g_ledger_year
    //         $DataExist = DB::connection("sqlSrvBCKI")->table('g_ledger_year')
    //             ->where('office_id', $sMill)
    //             ->where('year_id', $tahun)
    //             ->where('ac_num', $acNo)
    //             ->first();

    //         if (!$DataExist) {
    //             DB::connection("sqlSrvBCKI")->table('g_ledger_year')->insert([
    //                 'office_id' => $sMill,
    //                 'year_id' => $tahun,
    //                 'ac_num' => $acNo,
    //                 'dt_created' => now(),
    //                 'amt_type' => $sdrcr,
    //                 'dt_modified' => now() //,
    //                 //'user_id' => ''
    //             ]);
    //         } else {
    //             DB::connection("sqlSrvBCKI")->table('g_ledger_year')
    //                 ->where('office_id', $sMill)
    //                 ->where('year_id', $tahun)
    //                 ->where('ac_num', $acNo)
    //                 ->update([
    //                     'amt_type' => $sdrcr,
    //                     'dt_modified' => now() //,
    //                     //'user_id' => auth()->user()->id ?? 'system'
    //                 ]);
    //         }

    //         // Step 3: Update dr cr di bulan itu pada g ledger year
    //         $bulanFormatted = str_pad($bulan, 2, '0', STR_PAD_LEFT);
    //         DB::connection("sqlSrvBCKI")->table('g_ledger_year')
    //             ->where('office_id', $sMill)
    //             ->where('year_id', $tahun)
    //             ->where('ac_num', $acNo)
    //             ->update([
    //                 "dr_$bulanFormatted" => $journal->amt_dr,
    //                 "cr_$bulanFormatted" => $journal->amt_cr
    //             ]);

    //         // Step 4: Update dr_bal and cr_bal
    //         $fieldsDr = collect(range(1, 12))->map(function ($i) {
    //             return "COALESCE(dr_" . str_pad($i, 2, '0', STR_PAD_LEFT) . ", 0)";
    //         })->implode(' + ');

    //         $fieldsCr = collect(range(1, 12))->map(function ($i) {
    //             return "COALESCE(cr_" . str_pad($i, 2, '0', STR_PAD_LEFT) . ", 0)";
    //         })->implode(' + ');

    //         DB::connection("sqlSrvBCKI")->update("
    //         UPDATE g_ledger_year 
    //         SET dr_bal = COALESCE(amt_begin_dr, 0) + $fieldsDr,
    //             cr_bal = COALESCE(amt_begin_cr, 0) + $fieldsCr
    //         WHERE office_id = ? AND year_id = ? AND ac_num = ?
    //     ", [$sMill, $tahun, $acNo]);


    //         return true;
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }

    // public function updateGeneralLedger(
    //     int $sMill,
    //     string $AcNo,
    //     string $DrCr,
    //     string $PrdID,
    //     float $Amt,
    //     string $Tahun,
    //     float $AmtFrg = 0.0,
    //     string $UserId
    // ) {
    //     $connection = DB::connection("sqlSrvBCKI");
    //     try {
    //         // Cek apakah account ada di g_ledger
    //         $GlExist = $connection->table('g_ledger')
    //             ->where('office_id', $sMill)
    //             ->where('ac_num', trim($AcNo))
    //             ->first();

    //         if (!$GlExist) {
    //             // Data account tidak ditemukan
    //             return false;
    //         }

    //         // Cari atau buat data di g_ledger_year
    //         $glYearExist =  $connection->table('g_ledger_year')
    //             ->where('office_id', $sMill)
    //             ->where('year_id', $Tahun)
    //             ->where('ac_num', $AcNo)
    //             ->first();

    //         if (!$glYearExist) {
    //             $connection->table('g_ledger_year')->insert([
    //                 'office_id' => $sMill,
    //                 'year_id' => $Tahun,
    //                 'ac_num' => $AcNo,
    //                 'dt_created' => Carbon::now(),
    //                 'amt_type' => $GlExist->DrCr,
    //             ]);

    //             $glYear =  $connection->table('g_ledger_year')
    //                 ->where('office_id', $sMill)
    //                 ->where('year_id', $Tahun)
    //                 ->where('ac_num', $AcNo)
    //                 ->first();
    //         }

    //         $update = [];

    //         $prefix = strtolower(trim($DrCr)) == 'dr' ? 'dr' : 'cr';

    //         // Field bulanan
    //         $monthField = "{$prefix}_" . str_pad($PrdID, 2, '0', STR_PAD_LEFT);
    //         $monthFrgField = "{$prefix}_" . str_pad($PrdID, 2, '0', STR_PAD_LEFT) . '_frg';

    //         // Update nilai bulan dan saldo
    //         $update[$monthField] = ($glYear->$monthField ?? 0) + $Amt;
    //         $update["{$prefix}_bal"] = ($glYear->{$prefix . '_bal'} ?? 0) + $Amt;

    //         if ($AmtFrg != 0) {
    //             $update[$monthFrgField] = ($glYear->$monthFrgField ?? 0) + $AmtFrg;
    //             $update["{$prefix}_bal_frg"] = ($glYear->{$prefix . '_bal_frg'} ?? 0) + $AmtFrg;
    //         }

    //         $update['dt_modified'] = Carbon::now();
    //         $update['user_id'] = $UserId;

    //         $connection->table('g_ledger_year')
    //             ->where('office_id', $sMill)
    //             ->where('year_id', $Tahun)
    //             ->where('ac_num', $AcNo)
    //             ->update($update);


    //         return true;
    //     } catch (\Exception $e) {
    //         return false;
    //     }
    // }
    // public function JrnlOtherDeposit(Request $request)
    // {
    //     if (!$request->Mill) {
    //         return $this->sendError("Empty office", 'Error', 500);
    //     }
    //     if (!$request->TrID) {
    //         return $this->sendError("Empty transaction id", 'Error', 500);
    //     }
    //     if (!$request->CurrYear) {
    //         return $this->sendError("Empty transaction year", 'Error', 500);
    //     }
    //     $connection = DB::connection("sqlSrvBCKI");
    //     $connection->beginTransaction();
    //     try {
    //         $userId = getAuth($request)->user_id2 ?? null;
    //         $JvNum = $this->GetJvNum($request->Mill, "Other Deposit", $request->TrID, $request->CurrYear);
    //         $this->DeleteJournalByJvNum($request->Mill, $JvNum, $request->CurrYear);

    //         if ($JvNum == 0) {
    //             $JvNum = $this->getNextJvNum($request->Mill, $request->CurrYear);
    //         }
    //         $result = $connection->table('cash_tran_hdr as a')
    //             ->selectRaw("a.*, b.ac_par")
    //             ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
    //             ->where('a.tr_id', $request->TrID)
    //             ->where('a.tr_type', 'OD')
    //             ->first();
    //         if ($result) {
    //             $bulan2digit = date('m', strtotime($result['tr_date']));
    //             $this->saveJournal(
    //                 $request->Mill,
    //                 $JvNum,
    //                 $result['ac_num'],
    //                 "Other Deposit",
    //                 $result['tr_id'],
    //                 $result['tr_date'],
    //                 $result['amount'],
    //                 "dr",
    //                 "",
    //                 "",
    //                 "",
    //                 $result['memo_txt'],
    //                 "",
    //                 1,
    //                 "IDR",
    //                 $userId
    //             );
    //             $this->updateGeneralLedger($request->Mill, $result['ac_num'], "dr", $bulan2digit, $result['amount'], $request->CurrYear, (trim($result['curr_id']) !== "IDR") ? $result['amount'] : 0, $userId);
    //         }
    //         $result = $connection->table('cash_tran_dtl as a')
    //             ->selectRaw("a.*, b.ac_par,c.tr_date,c.curr_id")
    //             ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
    //             ->join('cash_tran_hdr as c', function ($join) {
    //                 $join->on('c.tr_id', '=', 'a.tr_id')
    //                     ->on('c.office_id', '=', 'a.office_id')
    //                     ->on('c.tr_type', '=', 'a.tr_type');
    //             })
    //             ->where('a.tr_id', $request->TrID)
    //             ->where('a.tr_type', 'OD')
    //             ->get();
    //         if ($result) {
    //             foreach ($result as $key => $detail) {
    //                 $bulan2digit = date('m', strtotime($result['tr_date']));
    //                 $this->saveJournal(
    //                     $request->Mill,
    //                     $JvNum,
    //                     $detail['ac_num'],
    //                     "Other Deposit",
    //                     $detail['tr_id'],
    //                     $detail['tr_date'],
    //                     $detail['amount'],
    //                     "cr",
    //                     "",
    //                     "",
    //                     "",
    //                     $detail['item_notes'],
    //                     "",
    //                     1,
    //                     "IDR",
    //                     $userId
    //                 );
    //                 $this->updateGeneralLedger($request->Mill, $detail['ac_num'], "cr", $bulan2digit, $detail['amount'], $request->CurrYear, (trim($detail['curr_id']) !== "IDR") ? $detail['amount'] : 0, $userId);
    //             }
    //         }

    //         $connection->commit();
    //         return $this->sendResponse($result, 'Jurnal created successfully.');
    //     } catch (\Exception $e) {
    //         $connection->rollBack();
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

    // public function JrnlOtherPayment(Request $request)
    // {
    //     if (!$request->Mill) {
    //         return $this->sendError("Empty office", 'Error', 500);
    //     }
    //     if (!$request->TrID) {
    //         return $this->sendError("Empty transaction id", 'Error', 500);
    //     }
    //     if (!$request->CurrYear) {
    //         return $this->sendError("Empty transaction year", 'Error', 500);
    //     }
    //     $connection = DB::connection("sqlSrvBCKI");
    //     $connection->beginTransaction();
    //     try {
    //         $userId = getAuth($request)->user_id2 ?? null;
    //         $JvNum = $this->GetJvNum($request->Mill, "Other Payment", $request->TrID, $request->CurrYear);
    //         $this->DeleteJournalByJvNum($request->Mill, $JvNum, $request->CurrYear);

    //         if ($JvNum == 0) {
    //             $JvNum = $this->getNextJvNum($request->Mill, $request->CurrYear);
    //         }
    //         $result = $connection->table('cash_tran_hdr as a')
    //             ->selectRaw("a.*, b.ac_par")
    //             ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
    //             ->where('a.tr_id', $request->TrID)
    //             ->where('a.tr_type', 'OP')
    //             ->first();
    //         if ($result) {
    //             $bulan2digit = date('m', strtotime($result['tr_date']));
    //             $this->saveJournal(
    //                 $request->Mill,
    //                 $JvNum,
    //                 $result['ac_num'],
    //                 "Other Payment",
    //                 $result['tr_id'],
    //                 $result['tr_date'],
    //                 $result['amount'],
    //                 "cr",
    //                 "",
    //                 "",
    //                 "",
    //                 $result['memo_txt'],
    //                 "",
    //                 1,
    //                 "IDR",
    //                 $userId
    //             );
    //             $this->updateGeneralLedger($request->Mill, $result['ac_num'], "cr", $bulan2digit, $result['amount'], $request->CurrYear, (trim($result['curr_id']) !== "IDR") ? $result['amount'] : 0, $userId);
    //         }
    //         $result = $connection->table('cash_tran_dtl as a')
    //             ->selectRaw("a.*, b.ac_par,c.tr_date,c.curr_id")
    //             ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
    //             ->join('cash_tran_hdr as c', function ($join) {
    //                 $join->on('c.tr_id', '=', 'a.tr_id')
    //                     ->on('c.office_id', '=', 'a.office_id')
    //                     ->on('c.tr_type', '=', 'a.tr_type');
    //             })
    //             ->where('a.tr_id', $request->TrID)
    //             ->where('a.tr_type', 'OP')
    //             ->get();
    //         if ($result) {
    //             foreach ($result as $key => $detail) {
    //                 $bulan2digit = date('m', strtotime($result['tr_date']));
    //                 $this->saveJournal(
    //                     $request->Mill,
    //                     $JvNum,
    //                     $detail['ac_num'],
    //                     "Other Payment",
    //                     $detail['tr_id'],
    //                     $detail['tr_date'],
    //                     $detail['amount'],
    //                     "dr",
    //                     "",
    //                     "",
    //                     "",
    //                     $detail['item_notes'],
    //                     "",
    //                     1,
    //                     "IDR",
    //                     $userId
    //                 );
    //                 $this->updateGeneralLedger($request->Mill, $detail['ac_num'], "dr", $bulan2digit, $detail['amount'], $request->CurrYear, (trim($detail['curr_id']) !== "IDR") ? $detail['amount'] : 0, $userId);
    //             }
    //         }

    //         $connection->commit();
    //         return $this->sendResponse($result, 'Jurnal created successfully.');
    //     } catch (\Exception $e) {
    //         $connection->rollBack();
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

    public function check_acctmonthlyclosing(Request $request){
        $sqlWhere = '1=1';
        $office_id = $request->office_id;
        $currPeriod = $request->currPeriod;
        $year = substr($currPeriod, 0, 4);     // contoh: 2025
        $month = substr($currPeriod, 4, 2);    // contoh: 07
        $period = $year . $month;              // 202507
        // dd($office_id, $currPeriod, $year, $month, $period);
        if ($office_id) {
            $sqlWhere .= " AND a.office_id = '$office_id'";
        }
        if ($currPeriod) {
            $sqlWhere .= " AND a.year_id = '$year'";
        }
              
        $ledgerRows = DB::connection('sqlSrvBCKI')
            ->table('g_ledger_year as a')
            ->select('a.ac_num', 
                    DB::raw("a.dr_$month as dr_amt"), 
                    DB::raw("a.cr_$month as cr_amt"),
                    'b.ac_name as ac_name'
                    )
            ->leftjoin('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->whereRaw($sqlWhere)
            ->get();


        $results = [];
        // dd($ledgerRows);
        // foreach ($ledgerRows as $row) {
        //     // dd($row);
        //     $amtDr = $this->getSumAcct($office_id, 'dr', $period, $row->ac_num);
        //     $amtCr = $this->getSumAcct($office_id, 'cr', $period, $row->ac_num);

        //     // if ((float)$row->dr_amt !== (float)$amtDr) {
        //     //     $results[] = $this->addToGrid($row->ac_num, $month, 'dr', $row->dr_amt, $amtDr, '');
        //     // }

        //     // if ((float)$row->cr_amt !== (float)$amtCr) {
        //     //     $results[] = $this->addToGrid($row->ac_num, $month, 'cr', $row->cr_amt, $amtCr, '');
        //     // }
        // }

        foreach ($ledgerRows as $row) {
            $amtDr = $this->getSumAcct($office_id, 'dr', $period, $row->ac_num);
            $amtCr = $this->getSumAcct($office_id, 'cr', $period, $row->ac_num);

            $acNum = trim($row->ac_num);
            $acName = trim($row->ac_name ?? '');
            $acName = $acName !== '' ? $acName : '';

            if ((float) $row->dr_amt !== (float) $amtDr) {
                $results[] = [
                    'ac_num'    => $acNum,
                    'ac_name'   => $acName,
                    'month'     => $month,
                    'type'      => 'dr',
                    'Master'    => $row->dr_amt,
                    'Trans'     => $amtDr,
                    'Selisih'   => round($amtDr - (float) $row->dr_amt, 2),
                ];
            }

            if ((float) $row->cr_amt !== (float) $amtCr) {
                $results[] = [
                    'ac_num'    => $acNum,
                    'ac_name'   => $acName,
                    'month'     => $month,
                    'type'      => 'cr',
                    'Master'    => $row->cr_amt,
                    'Trans'     => $amtCr,
                    'Selisih'   => round($amtCr - (float) $row->cr_amt, 2),
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Proses checking selesai.',
            'data'    => $results,
        ]);

        // return response()->json([
        //     'status' => 'success',
        //     'data' => $ledgerRows,
        //     'message' => 'Proses checking selesai.'
        // ]);
    }

    public function close_acctmonthlyclosing(Request $request){
        $office_id = $request->office_id;
        $currPeriod = $request->currPeriod;
        $userId = getAuth($request)->user_id2;

        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            $row = DB::connection('sqlSrvBCKI')
                ->table('acct_close_period')
                ->where('office_id', $office_id)
                ->first();

            if ($row) {
                DB::connection('sqlSrvBCKI')
                    ->table('acct_close_period')
                    ->where('office_id', $office_id)
                    ->update([
                        'close_period'     => $currPeriod,
                        'close_period_by'  => $userId,
                        'dt_close_period'  => now(),
                    ]);

                DB::connection('sqlSrvBCKI')->commit();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'CLOSING SUCCESS ....'
                ]);
            } else {
                DB::connection('sqlSrvBCKI')->rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data office tidak ditemukan.'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat proses closing.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function final_close_acctmonthlyclosing(Request $request){
        $office_id = $request->office_id;
        $currPeriod = $request->currPeriod;
        $userId = getAuth($request)->user_id2;

        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            $row = DB::connection('sqlSrvBCKI')
                ->table('acct_close_period')
                ->where('office_id', $office_id)
                ->first();
                /*                       
                'close_period'     => $currPeriod,
                'close_period_by'  => $userId,
                'dt_close_period'  => now(),
                */
            if ($row) {
                $updateData = [
                    'final_close_period'    => $currPeriod,
                    'final_close_period_by' => $userId,
                    'dt_final_close_period' => now(),
                ];
                $isClosePeriodEmpty = empty($row->close_period) || empty($row->close_period_by) ||
                                    str_contains($row->dt_close_period, '1900-01-01 00:00:00.000');

                if ($isClosePeriodEmpty) {
                    $updateData['close_period']    = $currPeriod;
                    $updateData['close_period_by'] = $userId;
                    $updateData['dt_close_period'] = now();
                }

                DB::connection('sqlSrvBCKI')
                ->table('acct_close_period')
                ->where('office_id', $office_id)
                ->update($updateData);
                DB::connection('sqlSrvBCKI')->commit();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'CLOSING SUCCESS ....'
                ]);
            } else {
                DB::connection('sqlSrvBCKI')->rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data office tidak ditemukan.'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat proses closing.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function getSumAcct($millId, $drCr, $periodId, $acNum, $currId = null)
    {
        $drCr = strtolower($drCr);
        if (!in_array($drCr, ['dr', 'cr'])) {
            return 0;
        }

        // Hitung tanggal awal dan akhir bulan dari periodId (format: YYYYMM)
        $year = substr($periodId, 0, 4);
        $month = substr($periodId, 4, 2);
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        // Pilih kolom debit/kredit dan apakah pakai rate
        $column = $drCr === 'cr' ? 'amt_cr' : 'amt_dr';

        $query = DB::connection('sqlSrvBCKI')
            ->table('journal_voucher')
            ->where('office_id', $millId)
            ->where('ac_num', $acNum)
            ->whereBetween('tr_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        if ($currId) {
            $query->selectRaw("SUM($column / rate) as amt");
        } else {
            $query->selectRaw("SUM($column) as amt");
        }

        $result = $query->first();

        return (float) ($result->amt ?? 0);
        
    }

    public function cekClosing(Request $request) // jika butuh return kapan terakhir closing
    {
        $tanggal = Carbon::now();
        $formatTgl = $tanggal->format('Ym');
        $user_id = getAuth($request)->user_id2;
        // dd($user_id);
        $isclose = DB::connection("sqlsrv")
            ->table('sec_env_conf')
            ->where('appl_id','FINANCE')
            ->where('entity_id','bcki')
            ->where('var_id','CLOSING')
            ->where('user_id2', $user_id)
            ->first();
            
        $isfinalclose = DB::connection("sqlsrv")
            ->table('sec_env_conf')
            ->where('appl_id','FINANCE')
            ->where('entity_id','bcki')
            ->where('var_id','FINAL_CLOSING')
            ->where('user_id2', $user_id)
            ->first();
        // dd($isclose, $isfinalclose);
        // Ambil periode terakhir yang pernah ditutup
        $result = DB::connection("sqlSrvBCKI")
            ->table('acct_close_period')
            ->selectRaw("trim(MAX(close_period)) as close_period, trim(max(final_close_period)) as final_close_period")
            ->first();
            
        // Bandingkan apakah tanggal input lebih kecil/sama dari periode terakhir closing
        $isClosed = false;
        if ($result && $result->close_period >= $formatTgl) {
            $isClosed = true;
        }
        
        return $this->sendResponse ([
            'is_closed' => $isClosed,
            'tr_period' => $formatTgl,
            'close_period' => $result ? $result->close_period : null,
            'final_close_period' => $result ? $result->final_close_period : null,
            'close' => $isclose ? $isclose->var_value : 'N',
            'final_close' => $isfinalclose ? $isfinalclose->var_value : 'N',
        ], 200);
    }

    public function update_acctmonthlyclosing(Request $request){
        try {
            // Ambil array data dari properti 'items'
            $closingData = $request->input('items');

            // Lakukan validasi dasar (opsional, tapi sangat disarankan)
            if (empty($closingData) || !is_array($closingData)) {
                return response()->json(['error' => 'Data yang dikirim tidak valid.'], 400);
            }

            // Untuk sementara, kita kembalikan data yang diterima
            // sesuai permintaan Anda.
            // return response()->json($closingData, 200);
            foreach ($closingData as $key => $item) {
                // Tentukan nama kolom berdasarkan 'type' (dr atau cr)
                $columnPrefix = ($item['type'] === 'dr') ? 'dr' : 'cr';

                // Ambil bulan dari data dan format menjadi dua digit
                $month = str_pad($item['month'], 2, '0', STR_PAD_LEFT);
                
                // Gabungkan untuk membuat nama kolom lengkap (contoh: dr_07)
                $columnToUpdate = $columnPrefix . '_' . $month;


                DB::connection("sqlSrvBCKI")
                ->table("g_ledger_year")
                ->where("ac_num", $item['ac_num'])
                ->update([$columnToUpdate => $item['trans']]);

                // ->where

                // $key = index array
                // $item = isi per baris
                // dd($item); // langsung berhenti di iterasi pertama
                // Kembalikan respons berhasil jika semua update berhasil
            }
            // return response()->json(['message' => 'Data berhasil diperbarui.'], 200);
            return $this->sendResponse('message', 'Data berhasil diperbarui.');

        } catch (\Exception $e) {
            // Catat kesalahan untuk debugging
            Log::error('Error in update_acctmonthlyclosing: ' . $e->getMessage());

            // Kembalikan respons error
            // return response()->json(['error' => 'Terjadi kesalahan pada server.'], 500);
            return $this->sendError($e->getMessage(), 'Error', 500);

        }
    }
}
