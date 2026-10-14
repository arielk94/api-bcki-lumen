<?php

namespace App\Services;

use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpParser\Node\Expr\Cast\Object_;

class AccountingService
{
    use getFilterTrait;
    // public function ShowJurnal($source_num)
    // {
    //     try {
    //         $connection = DB::connection("sqlSrvFPI");
    //         $result = $connection->table('journal_voucher as a')
    //             ->select('a.tr_date', 'a.source_num', 'a.source', 'a.ac_num', 'b.ac_name as description', 'a.amt_dr', 'a.amt_cr', 'a.remark')
    //             ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
    //             ->where('source_num', $source_num)
    //             ->get();
    //     } catch (\Throwable $e) {
    //         return false;
    //     }
    // }
    function saveJournal(
        $sMill,
        $JvNum,
        $AcNum,
        $src,
        $srcnum,
        $DtTrx,
        $Amt,
        $DrCr,
        $CustID,
        $VendorID,
        $InvID,
        $Remark,
        $NoCek,
        $exch_rate = 1,
        $CurrID,
        $userID,
        $acNumSourceInfo = 'ac_num'
    ) {
        $connection = DB::connection("sqlSrvFPI");
        $year = Carbon::parse($DtTrx)->year;
        //dd($JvNum);
        // cari record
        $journal = $connection->table('journal_voucher')
            ->where('office_id', $sMill)
            ->where('jv_num', $JvNum)
            ->where('ac_num', $AcNum)
            ->where('year_id', $year)
            ->first();

        if (!$journal) {
            // Insert new journal

            DB::connection("sqlSrvFPI")->table('journal_voucher')->insert([
                'office_id'   => $sMill,
                'jv_num'      => $JvNum,
                'ac_num'      => $AcNum,
                'year_id'     => $year,
                'dt_created'  => Carbon::now('Asia/Jakarta'),
                'source'      => $src,
                'source_num'  => $srcnum,
                'tr_date'     => $DtTrx,
                'amt_dr'      => strtoupper(trim($DrCr)) === 'DR' ? $Amt : 0,
                'amt_cr'      => strtoupper(trim($DrCr)) === 'CR' ? $Amt : 0,
                'DrCr'        => strtoupper(trim($DrCr)),
                'cust_id'     => $CustID,
                'vendor_id'   => $VendorID,
                'curr_id'     => $CurrID,
                'Rate'        => $exch_rate ?: 1,
                'cek_id'      => $NoCek,
                'inv_id'      => $InvID,
                'Remark'      => $Remark,
                'dt_modified' => Carbon::now('Asia/Jakarta'),
                'user_id'     => $userID

            ]);
            // var_dump(
            //     $journal,
            //     $sMill,
            //     $JvNum,
            //     $AcNum,
            //     $src,
            //     $srcnum,
            //     $DtTrx,
            //     $Amt,
            //     $DrCr,
            //     $CustID,
            //     $VendorID,
            //     $InvID,
            //     $Remark,
            //     $NoCek,
            //     $exch_rate = 1,
            //     $CurrID,
            //     $userID
            // );
            // die;
        } else {
            // Update existing journal
            $amt_dr = $journal->amt_dr ?? 0;
            $amt_cr = $journal->amt_cr ?? 0;

            if (strtoupper(trim($DrCr)) === 'DR') {
                $amt_dr += $Amt;
            } else {
                $amt_cr += $Amt;
            }

            $connection->table('journal_voucher')
                ->where('office_id', $sMill)
                ->where('jv_num', $JvNum)
                ->where('ac_num', $AcNum)
                ->where('year_id', $year)
                ->update([
                    'source'      => $src,
                    'source_num'  => $srcnum,
                    'tr_date'     => $DtTrx,
                    'amt_dr'      => $amt_dr,
                    'amt_cr'      => $amt_cr,
                    'DrCr'        => $amt_dr == 0 ? 'cr' : 'dr',
                    'cust_id'     => $CustID,
                    'vendor_id'   => $VendorID,
                    'curr_id'     => $CurrID,
                    'Rate'        => $exch_rate ?: 1,
                    'cek_id'      => $NoCek,
                    'inv_id'      => $InvID,
                    'Remark'      => $Remark,
                    'dt_modified' => Carbon::now('Asia/Jakarta'),
                    'user_id'     => $userID
                ]);
        }
    }
    function getJvNum(int $MillID, string $Src, string $SrcNum, string $CurrYear)
    {
        //try {
        $connection = DB::connection("sqlSrvFPI");
        $result = $connection->table('journal_voucher')
            ->select('jv_num')
            ->where('office_id', $MillID)
            ->where('source',  $Src)
            ->where('source_num', $SrcNum)
            ->where('year_id', $CurrYear)
            ->first();
        if ($result) {
            return $result->jv_num;
        } else {
            return 0;
        }
        //} catch (\Throwable $e) {
        //    return false;
        //}
    }
    function getNextJvNum(int $MillID,  string $CurrYear)
    {
        $keyId = 'journal_voucher_' . trim($CurrYear);
        //$nextNum = 0;

        DB::connection("sqlSrvFPI")->beginTransaction();


        $record = DB::connection("sqlSrvFPI")->table('serial_num')
            ->where('office_id', $MillID)
            ->where('key_id', $keyId)
            ->first();
        //dd($record);
        if (!$record) {
            // Insert record baru jika belum ada
            DB::connection("sqlSrvFPI")->table('serial_num')->insert([
                'office_id' => $MillID,
                'key_id' => $keyId,
                'serial_max' => 9999999,
                'serial_last' => 1, // langsung ke 1
                'serial_min' => 1,
            ]);
            $nextNum = 1;
            //return 1;
        } else {

            $nextNum = $record->serial_last + 1;
            //dd($nextNum);
            DB::connection("sqlSrvFPI")->table('serial_num')
                ->where('office_id', $MillID)
                ->where('key_id', $keyId)
                ->update([
                    'serial_last' => $nextNum
                ]);
            //dd($nextNum);
            //return $nextNum;
        }
        DB::connection("sqlSrvFPI")->commit();

        // DB::connection("sqlSrvFPI")->transaction(function () use ($MillID, $keyId, $nextNum) {
        // });
        //dd($nextNum);
        return $nextNum;
    }
    public function DeleteJournalByJvNum(int $MillID, float $JvNum, string $CurrYear)
    {
        //DB::connection("sqlSrvFPI")->beginTransaction();
        //try {

        DB::connection('sqlSrvFPI')->table('journal_voucher')
            ->where('office_id', $MillID)
            ->where('jv_num',  $JvNum)
            ->where('year_id', $CurrYear)
            ->update([
                'amt_dr' => 0,
                'amt_cr' => 0
            ]);
        $getJv = DB::connection('sqlSrvFPI')->table('journal_voucher')
            ->select('ac_num', 'tr_date')
            ->where('office_id', $MillID)
            ->where('jv_num',  $JvNum)
            ->where('year_id', $CurrYear)
            ->get();

        foreach ($getJv as $key => $detail) {
            $bulan2digit = date('m', strtotime($detail->tr_date));
            $this->updateGL($MillID, $detail->ac_num, $CurrYear, $bulan2digit);
        }
        DB::connection('sqlSrvFPI')->table('journal_voucher')
            ->where('office_id', $MillID)
            ->where('jv_num', $JvNum)
            ->where('year_id', $CurrYear)
            ->delete();
        //   DB::connection("sqlSrvFPI")->commit();
        //} catch (\Throwable $e) {
        //    DB::connection("sqlSrvFPI")->rollBack();
        //    return false;
        // }
    }
    function updateGL($sMill, $acNo, $tahun, $bulan)
    {
        //try {

        // Step 1: get total debit dan credit untuk coa di tahun dan bulan
        $journal = DB::connection("sqlSrvFPI")->table('journal_voucher as a')
            ->select(DB::raw('SUM(amt_dr) as amt_dr'), DB::raw('SUM(amt_cr) as amt_cr'), 'b.drcr')
            ->join('g_ledger as b', 'b.ac_num', '=', 'a.ac_num')
            ->where('a.office_id', $sMill)
            ->where('a.ac_num', $acNo)
            ->whereYear('a.tr_date', $tahun)
            ->whereMonth('a.tr_date', $bulan)
            ->groupBy('b.drcr')
            ->first();
        //dd($sMill, $acNo, $bulan, $tahun);
        if (!$journal || ($journal->amt_dr === null && $journal->amt_cr === null)) {
            return false;
        } else {
            $sdrcr = $journal ? trim($journal->drcr) : null;
        }

        // Step 2: Insert or update g_ledger_year
        $DataExist = DB::connection("sqlSrvFPI")->table('g_ledger_year')
            ->where('office_id', $sMill)
            ->where('year_id', $tahun)
            ->where('ac_num', $acNo)
            ->first();

        if (!$DataExist) {
            DB::connection("sqlSrvFPI")->table('g_ledger_year')->insert([
                'office_id' => $sMill,
                'year_id' => $tahun,
                'ac_num' => $acNo,
                'dt_created' => now('Asia/Jakarta'),
                'amt_type' => $sdrcr,
                'dt_modified' => now('Asia/Jakarta') //,
                //'user_id' => ''
            ]);
        } else {
            DB::connection("sqlSrvFPI")->table('g_ledger_year')
                ->where('office_id', $sMill)
                ->where('year_id', $tahun)
                ->where('ac_num', $acNo)
                ->update([
                    'amt_type' => $sdrcr,
                    'dt_modified' => now('Asia/Jakarta') //,
                    //'user_id' => auth()->user()->id ?? 'system'
                ]);
        }

        // Step 3: Update dr cr di bulan itu pada g ledger year
        $bulanFormatted = str_pad($bulan, 2, '0', STR_PAD_LEFT);
        DB::connection("sqlSrvFPI")->table('g_ledger_year')
            ->where('office_id', $sMill)
            ->where('year_id', $tahun)
            ->where('ac_num', $acNo)
            ->update([
                "dr_$bulanFormatted" => $journal->amt_dr,
                "cr_$bulanFormatted" => $journal->amt_cr
            ]);

        // Step 4: Update dr_bal and cr_bal
        $fieldsDr = collect(range(1, 12))->map(function ($i) {
            return "COALESCE(dr_" . str_pad($i, 2, '0', STR_PAD_LEFT) . ", 0)";
        })->implode(' + ');

        $fieldsCr = collect(range(1, 12))->map(function ($i) {
            return "COALESCE(cr_" . str_pad($i, 2, '0', STR_PAD_LEFT) . ", 0)";
        })->implode(' + ');

        DB::connection("sqlSrvFPI")->update("
            UPDATE g_ledger_year 
            SET dr_bal = COALESCE(amt_begin_dr, 0) + $fieldsDr,
                cr_bal = COALESCE(amt_begin_cr, 0) + $fieldsCr
            WHERE office_id = ? AND year_id = ? AND ac_num = ?
        ", [$sMill, $tahun, $acNo]);


        return true;
        // } catch (\Exception $e) {
        //     return false;
        // }
    }

    public function updateGeneralLedger(
        int $sMill,
        string $AcNo,
        string $DrCr,
        string $PrdID,
        float $Amt,
        string $Tahun,
        float $AmtFrg = 0.0,
        string $UserId
    ) {
        $connection = DB::connection("sqlSrvFPI");
        //dd("ok");
        // Cek apakah account ada di g_ledger
        $GlExist = $connection->table('g_ledger')
            ->where('office_id', $sMill)
            ->where('ac_num', trim($AcNo))
            ->first();
        //dd($GlExist);
        if (!$GlExist) {
            // Data account tidak ditemukan
            return false;
        }

        // Cari atau buat data di g_ledger_year
        $glYearExist =  $connection->table('g_ledger_year')
            ->where('office_id', $sMill)
            ->where('year_id', $Tahun)
            ->where('ac_num', $AcNo)
            ->first();
        //var_dump(!$glYearExist);
        //die;
        //$glYear = $glYearExist;
        if (!$glYearExist) {
            //die;
            $connection->table('g_ledger_year')->insert([
                'office_id' => $sMill,
                'year_id' => $Tahun,
                'ac_num' => $AcNo,
                'dt_created' => Carbon::now('Asia/Jakarta'),
                'amt_type' => $GlExist->drcr,
            ]);
            //echo "insert";
            //die;
        }
        // $glYear =  $connection->table('g_ledger_year')
        //     ->where('office_id', $sMill)
        //     ->where('year_id', $Tahun)
        //     ->where('ac_num', $AcNo)
        //     ->first();
        $glYear = DB::connection("sqlSrvFPI")->table('journal_voucher as a')
            ->select(DB::raw('SUM(amt_dr) as amt_dr'), DB::raw('SUM(amt_cr) as amt_cr'), 'b.drcr')
            ->join('g_ledger as b', 'b.ac_num', '=', 'a.ac_num')
            ->where('a.office_id', $sMill)
            ->where('a.ac_num', $AcNo)
            ->whereYear('a.tr_date', $Tahun)
            ->whereMonth('a.tr_date', $PrdID)
            ->groupBy('b.drcr')
            ->first();
        $glYearDrBal = DB::connection("sqlSrvFPI")->table('g_ledger_year as a')
            ->selectRaw("amt_begin_dr+dr_01 +  dr_02 +  dr_03 +  dr_04 +  dr_05 +  dr_06 + dr_07+dr_08 + dr_09 + dr_10 + dr_11 + dr_12 as amt_dr, 
            amt_begin_cr+cr_01 +  cr_02 +  cr_03 +  cr_04 +  cr_05 +  cr_06 + cr_07+cr_08 + cr_09 + cr_10 + cr_11 + cr_12  as amt_cr")
            ->join('g_ledger as b', 'b.ac_num', '=', 'a.ac_num')
            ->where('a.office_id', $sMill)
            ->where('a.ac_num', $AcNo)
            ->where('a.year_id', $Tahun)
            ->first();

        //dd($glYearDrBal);
        $update = [];

        $prefix = strtolower(trim($DrCr)) == 'dr' ? 'dr' : 'cr';
        //$prefixamt = strtolower(trim($DrCr)) == 'dr' ? $glYear->amt_dr : $glYear->amt_cr;

        // Field bulanan
        $monthField = "{$prefix}_" . str_pad($PrdID, 2, '0', STR_PAD_LEFT);
        $monthFrgField = "{$prefix}_" . str_pad($PrdID, 2, '0', STR_PAD_LEFT) . '_frg';

        // Update nilai bulan dan saldo
        $update[$monthField] = strtolower(trim($DrCr)) == 'dr' ? $glYear->amt_dr : $glYear->amt_cr; //($glYear->$monthField ?? 0) + $Amt;
        $update["{$prefix}_bal"] = strtolower(trim($DrCr)) == 'dr' ? $glYearDrBal->amt_dr + $Amt : $glYearDrBal->amt_cr + $Amt; // ($glYear->{$prefix . '_bal'} ?? 0) + $Amt;

        if ($AmtFrg != 0) {
            $update[$monthFrgField] = ($glYear->$monthFrgField ?? 0) + $AmtFrg;
            $update["{$prefix}_bal_frg"] = ($glYear->{$prefix . '_bal_frg'} ?? 0) + $AmtFrg;
        }

        $update['dt_modified'] = Carbon::now('Asia/Jakarta');
        $update['user_id'] = $UserId;
        //dd($update);
        //$update['glyear'] = $glYear;
        //return $update;
        $connection->table('g_ledger_year')
            ->where('office_id', $sMill)
            ->where('year_id', $Tahun)
            ->where('ac_num', $AcNo)
            ->update($update);


        return true;
    }


    function GetStdAccountCode($AccountName, $CurrID, $Import, $Office, $DivID)
    {

        $query = DB::connection("sqlSrvFPI")->table('g_ledger_std')
            ->where('ac_name', trim($AccountName));

        if (!empty($CurrID)) {
            $query->where('curr_id', trim($CurrID));
        }
        if (!empty($Import)) {
            $query->where('import', trim($Import));
        }
        if (!empty($Office)) {
            $query->where('office_id', trim($Office));
        }
        if (!empty($DivID)) {
            $query->where('divisi', trim($DivID));
        }

        $result = $query->value('ac_num');

        if ($result) {
            return trim($result);
        }
    }

    public function JrnlOtherDeposit($Mill, $TrID, $CurrYear, $userId)
    {
        //     echo "sini";
        //     die;
        $connection = DB::connection("sqlSrvFPI");
        //$connection->beginTransaction();
        //try {
        $JvNum = $this->GetJvNum($Mill, "Other Deposit", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        //dd($JvNum);
        $result = $connection->table('cash_tran_hdr as a')
            ->selectRaw("a.*, b.ac_par")
            ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->where('a.tr_id', $TrID)
            ->where('a.tr_type', 'OD')
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->tr_date));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Other Deposit",
                $result->tr_id,
                $result->tr_date,
                $result->amount * $result->exchange_rate,
                "dr",
                "",
                "",
                "",
                $result->memo_txt,
                "",
                1,
                "IDR",
                $userId
            );
            //dd($JvNum);
            //echo "save jurnal ok";
            //die;

            $this->updateGeneralLedger($Mill, $result->ac_num, "dr", $bulan2digit, $result->amount * $result->exchange_rate, $CurrYear, (trim($result->curr_id) !== "IDR") ? $result->amount : 0, $userId);
        }
        $result = $connection->table('cash_tran_dtl as a')
            ->selectRaw("a.tr_id,a.ac_num,a.amount,a.item_notes, b.ac_par,c.tr_date,c.curr_id,c.exchange_rate")
            ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->join('cash_tran_hdr as c', function ($join) {
                $join->on('c.tr_id', '=', 'a.tr_id')
                    ->on('c.office_id', '=', 'a.office_id')
                    ->on('c.tr_type', '=', 'a.tr_type');
            })
            ->where('a.tr_id', $TrID)
            ->where('a.tr_type', 'OD')
            ->get()
            ->groupBy("ac_num")
            ->map(function ($group) {
                // $totalAmount = $group->sum('amount');
                return (object) [
                    'ac_num' => $group->first()->ac_num,
                    'curr_id' => $group->first()->curr_id,
                    'tr_id' => $group->first()->tr_id,
                    'tr_date' => $group->first()->tr_date,
                    'exchange_rate' => $group->first()->exchange_rate,
                    'total_amount' => $group->sum('amount'),
                    'item_notes' => $group->pluck('item_notes')->filter()->implode(', ')
                ];
            })
            ->values();
        // var_dump($result);
        // die;
        if ($result) {
            //$tanpung = [];
            foreach ($result as $key => $detail) {
                $bulan2digit = date('m', strtotime($detail->tr_date));
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $detail->ac_num,
                    "Other Deposit",
                    $detail->tr_id,
                    $detail->tr_date,
                    $detail->total_amount * $detail->exchange_rate,
                    "cr",
                    "",
                    "",
                    "",
                    $detail->item_notes,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $detail->ac_num, "cr", $bulan2digit, $detail->total_amount * $detail->exchange_rate, $CurrYear, (trim($detail->curr_id) !== "IDR") ? $detail->total_amount : 0, $userId);
            }
            //dd($tanpung);
        }


        //$connection->commit();
        //return $this->sendResponse($result, 'Jurnal created successfully.');

        //} catch (\Exception $e) {
        //$connection->rollBack();
        //    return ($e->getMessage());
        //die;
        //return $this->sendError($e->getMessage(), 'Error', 500);
        //}
    }

    public function JrnlOtherPayment($Mill, $TrID, $CurrYear, $userId)
    {
        //     echo "sini";
        //     die;
        $connection = DB::connection("sqlSrvFPI");
        //$connection->beginTransaction();
        //try {
        $JvNum = $this->GetJvNum($Mill, "Other Payment", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('cash_tran_hdr as a')
            ->selectRaw("a.*, b.ac_par")
            ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->where('a.tr_id', $TrID)
            ->where('a.tr_type', 'OP')
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->tr_date));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Other Payment",
                $result->tr_id,
                $result->tr_date,
                $result->amount * $result->exchange_rate,
                "cr",
                "",
                "",
                "",
                $result->memo_txt,
                "",
                1,
                "IDR",
                $userId
            );
            //dd($JvNum);
            //echo "save jurnal ok";
            //die;

            $this->updateGeneralLedger($Mill, $result->ac_num, "cr", $bulan2digit, $result->amount * $result->exchange_rate, $CurrYear, (trim($result->curr_id) !== "IDR") ? $result->amount : 0, $userId);
        }
        $result = $connection->table('cash_tran_dtl as a')
            ->selectRaw("a.tr_id,a.ac_num,a.amount,a.item_notes, b.ac_par,c.tr_date,c.curr_id,c.exchange_rate")
            ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->join('cash_tran_hdr as c', function ($join) {
                $join->on('c.tr_id', '=', 'a.tr_id')
                    ->on('c.office_id', '=', 'a.office_id')
                    ->on('c.tr_type', '=', 'a.tr_type');
            })
            ->where('a.tr_id', $TrID)
            ->where('a.tr_type', 'OP')
            ->get()
            ->groupBy("ac_num")
            ->map(function ($group) {
                // $totalAmount = $group->sum('amount');
                return (object) [
                    'ac_num' => $group->first()->ac_num,
                    'curr_id' => $group->first()->curr_id,
                    'tr_id' => $group->first()->tr_id,
                    'tr_date' => $group->first()->tr_date,
                    'exchange_rate' => $group->first()->exchange_rate,
                    'total_amount' => $group->sum('amount'),
                    'item_notes' => $group->pluck('item_notes')->filter()->implode(', ')
                ];
            })
            ->values();
        // var_dump($result);
        // die;
        if ($result) {
            //$tanpung = [];
            foreach ($result as $key => $detail) {
                $bulan2digit = date('m', strtotime($detail->tr_date));
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $detail->ac_num,
                    "Other Payment",
                    $detail->tr_id,
                    $detail->tr_date,
                    $detail->total_amount * $detail->exchange_rate,
                    "dr",
                    "",
                    "",
                    "",
                    $detail->item_notes,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $detail->ac_num, "dr", $bulan2digit, $detail->total_amount * $detail->exchange_rate, $CurrYear, (trim($detail->curr_id) !== "IDR") ? $detail->total_amount : 0, $userId);
            }
            //dd($tanpung);
        }


        // $connection->commit();
        //return $this->sendResponse($result, 'Jurnal created successfully.');

        //} catch (\Exception $e) {
        // $connection->rollBack();
        //    dd($e->getMessage());
        //     return ($e->getMessage());
        //die;
        //return $this->sendError($e->getMessage(), 'Error', 500);
        // }
    }

    public function JrnlkasBonInternal($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        //GetStdAccountCode($AccountName, $CurrID, $Import, $Office, $DivID)
        $acNumDr = $this->GetStdAccountCode("BON SEMENTARA", "IDR", "N", 1, "");
        $JvNum = $this->GetJvNum($Mill, "Kas Bon Internal", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('kb_hdr as a')
            ->selectRaw("a.*")
            ->join('kb_dtl as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.tr_id', '=', 'b.tr_id');
            })
            ->where('a.tr_type', 'IN')
            ->where('a.tr_id', $TrID)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_kb));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Kas Bon Internal",
                $result->tr_id,
                $result->dt_kb,
                $result->kb_amt,
                "cr",
                $result->idemployee,
                "",
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $result->ac_num, "cr", $bulan2digit, $result->kb_amt, $CurrYear, $result->kb_amt, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $acNumDr,
                "Kas Bon Internal",
                $result->tr_id,
                $result->dt_kb,
                $result->kb_amt,
                "dr",
                $result->idemployee,
                "",
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $acNumDr, "dr", $bulan2digit, $result->kb_amt, $CurrYear, $result->kb_amt, $userId);
        }
    }
    public function JrnlPurcInvNonPO($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $AcNumPPN = $this->GetStdAccountCode("PPN MASUKAN", "IDR", "N", $Mill, "");
        $AcNumPPH = $this->GetStdAccountCode("PPH23", "IDR", "N", $Mill, "");
        $AcNumPPH21 = $this->GetStdAccountCode("PPH21", "IDR", "N", $Mill, "L");
        $AcNumPPH4 = $this->GetStdAccountCode("PPH4", "IDR", "N", $Mill, "");

        $JvNum = $this->GetJvNum($Mill, "Purchase Invoice", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('purc_inv_mast as a')
            ->selectRaw("a.*,b.ac_num_bbt as ac_num")
            ->join('purc_inv_item as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.tr_id', '=', 'b.tr_id');
            })
            ->where('a.tr_id', $TrID)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Purchase Invoice",
                $result->tr_id,
                $result->dt_inv,
                $result->amt_total,
                "cr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $result->ac_num, "cr", $bulan2digit, $result->amt_total, $CurrYear, 0, $userId);
        }

        $detail = $connection->table('purc_inv_mast as a')
            ->selectRaw("
                a.tr_id,
                a.dt_inv,
                b.ac_num_diff_rate,
                b.remark,
                b.amt_item,
                a.tax1,
                a.tax2,
                a.tax3,
                a.tax4,
                a.ppn as ppn_val,
                a.pct_pph as pph_val,
                a.pct_pph21 as pph21_val,
                a.amt_pph4 as pph4_val
            ")
            ->join('purc_inv_item as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.tr_id', '=', 'b.tr_id');
            })
            ->where('a.tr_id', $TrID)
            ->get()
            ->groupBy("ac_num_diff_rate")
            ->map(function ($group) {
                // $totalAmount = $group->sum('amount');
                return (object) [
                    'tr_id' => $group->first()->tr_id,
                    'dt_inv' => $group->first()->dt_inv,
                    'ac_num_diff_rate' => $group->first()->ac_num_diff_rate,
                    'total_amount' => $group->sum('amt_item'),
                    'remark' => $group->pluck('remark')->filter()->implode(', '),
                    'tax1' => $group->first()->tax1,
                    'tax2' => $group->first()->tax2,
                    'tax3' => $group->first()->tax3,
                    'tax4' => $group->first()->tax4,
                    'ppn_val' => $group->first()->ppn_val,
                    'pph_val' => $group->first()->pph_val,
                    'pph21_val' => $group->first()->pph21_val,
                    'pph4_val' => $group->first()->pph4_val
                ];
            })
            ->values();

        if ($detail) {
            // class loader
            $CalcIncludeService = new PerhitunganIncludeService();
            $detailCount = count($detail);

            foreach ($detail as $key => $item) {
                $bulan2digit = date('m', strtotime($item->dt_inv));

                $calcHargaInEx = $CalcIncludeService->hitungHargaDasarIncludeExclude(
                    $item->total_amount,
                    ((float) $item->ppn_val / 100),
                    ((float) $item->pph_val / 100),
                    ((float) $item->pph21_val / 100),
                    [trim($item->tax1), trim($item->tax2), trim($item->tax4), trim($item->tax3)],
                    (float) $item->pph4_val / $detailCount
                );

                // -- harga dasar 
                $AmtSubTotal = $calcHargaInEx[0];

                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $item->ac_num_diff_rate,
                    "Purchase Invoice",
                    $item->tr_id,
                    $item->dt_inv,
                    $AmtSubTotal,
                    "dr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $item->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_diff_rate'
                );
                $this->updateGeneralLedger($Mill, $item->ac_num_diff_rate, "dr", $bulan2digit, $AmtSubTotal, $CurrYear, 0, $userId);
            }
        }

        if ($result->amt_ppn > 0) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $AcNumPPN,
                "Purchase Invoice",
                $result->tr_id,
                $result->dt_inv,
                $result->amt_ppn,
                "dr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_ppn'
            );
            $this->updateGeneralLedger($Mill,  $AcNumPPN, "dr", $bulan2digit, $result->amt_ppn, $CurrYear, 0, $userId);
        }

        if ($result->amt_pph > 0) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $AcNumPPH,
                "Purchase Invoice",
                $result->tr_id,
                $result->dt_inv,
                $result->amt_pph,
                "cr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_pph23'
            );
            $this->updateGeneralLedger($Mill,  $AcNumPPH, "cr", $bulan2digit, $result->amt_pph, $CurrYear, 0, $userId);
        }

        if ($result->amt_pph21 > 0) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $AcNumPPH21,
                "Purchase Invoice",
                $result->tr_id,
                $result->dt_inv,
                $result->amt_pph21,
                "cr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_pph21'
            );
            $this->updateGeneralLedger($Mill,  $AcNumPPH, "cr", $bulan2digit, $result->amt_pph, $CurrYear, 0, $userId);
        }

        if ($result->amt_pph4 > 0) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $AcNumPPH4,
                "Purchase Invoice",
                $result->tr_id,
                $result->dt_inv,
                $result->amt_pph4,
                "cr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_pph4'
            );
            $this->updateGeneralLedger($Mill,  $AcNumPPH4, "cr", $bulan2digit, $result->amt_pph4, $CurrYear, 0, $userId);
        }
    }

    public function JrnlOtherJournal($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $JvNum = $this->GetJvNum($Mill, "Other Journal Voucher", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('jv_trans_dtl as a')
            ->selectRaw("a.tr_id,a.ac_num,a.amt_dr,a.amt_cr,a.item_memo,c.dt_jv")
            ->join('g_ledger as b', 'a.ac_num', '=', 'b.ac_num')
            ->join('jv_trans_hdr as c', function ($join) {
                $join->on('c.tr_id', '=', 'a.tr_id')
                    ->on('c.office_id', '=', 'a.office_id');
            })
            ->where('a.tr_id', $TrID)
            ->where('a.office_id', 1)
            ->get()
            ->groupBy("ac_num")
            ->map(function ($group) {
                return (object) [
                    'ac_num' => $group->first()->ac_num,
                    'tr_id' => $group->first()->tr_id,
                    'dt_jv' => $group->first()->dt_jv,
                    'total_amount_dr' => $group->sum('amt_dr'),
                    'total_amount_cr' => $group->sum('amt_cr'),
                    'item_memo' => $group->pluck('item_memo')->filter()->implode(', ')
                ];
            })
            ->values();
        if ($result) {
            foreach ($result as $key => $detail) {
                $bulan2digit = date('m', strtotime($detail->dt_jv));
                $CrDr = ($detail->total_amount_dr != 0) ? 'dr' : 'cr';
                $AmtJrnl = ($detail->total_amount_dr != 0 ? $detail->total_amount_dr : $detail->total_amount_cr);
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $detail->ac_num,
                    "Other Journal Voucher",
                    $detail->tr_id,
                    $detail->dt_jv,
                    $AmtJrnl,
                    $CrDr,
                    "",
                    "",
                    "",
                    $detail->item_memo,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $detail->ac_num, $CrDr, $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);
            }
        }
    }

    public function JrnlCustomerDeposit($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $result = $connection->table('dp_sales as a')
            ->selectRaw("a.*")
            ->where('a.dp_id', $TrID)
            ->where('a.office_id', 1)
            ->first();
        $TrType = $result->tr_type ?? 'DP';
        $JvNum = $this->GetJvNum($Mill, $TrType == 'DP' ? "Customer Deposit" : "Customer Refund", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_dp));
            $AmtJrnl = $result->dp_amt;
            if ($TrType == 'DP') {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num,
                    "Customer Deposit",
                    $result->dp_id,
                    $result->dt_dp,
                    $AmtJrnl,
                    'dr',
                    $result->cust_id,
                    "",
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num, 'dr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num_dp,
                    "Customer Deposit",
                    $result->dp_id,
                    $result->dt_dp,
                    $AmtJrnl,
                    'cr',
                    $result->cust_id,
                    "",
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num_dp, 'cr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);
            } else {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num_DP,
                    "Customer Refund",
                    $result->dp_id,
                    $result->dt_dp,
                    $AmtJrnl,
                    'dr',
                    $result->cust_id,
                    "",
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num_DP, 'dr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num,
                    "Customer Refund",
                    $result->dp_id,
                    $result->dt_dp,
                    $AmtJrnl,
                    'cr',
                    $result->cust_id,
                    "",
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num, 'cr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);
            }
        }
    }


    public function JrnlPurcDP($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");

        $JvNum = $this->GetJvNum($Mill, "Purchase Down Payment", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('purc_down_payment as a')
            ->selectRaw("a.*")
            ->where('a.dp_id', $TrID)
            ->where('a.office_id', 1)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_dp));
            $AmtJrnl = $result->dp_amt;

            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Purchase Down Payment",
                $result->dp_id,
                $result->dt_dp,
                $AmtJrnl,
                'cr',
                "",
                $result->vendor_id,
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $result->ac_num, 'cr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num_dp,
                "Purchase Down Payment",
                $result->dp_id,
                $result->dt_dp,
                $AmtJrnl,
                'dr',
                "",
                $result->vendor_id,
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $result->ac_num_dp, 'dr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);
        }
    }

    public function JrnlPurcPayment($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $AcNumDisc = $this->GetStdAccountCode("DISKON PURC PAYMENT", "IDR", "N", 1, "");
        $JvNum = $this->GetJvNum($Mill, "Purchase Payment", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('purc_pay_mast as a')
            ->selectRaw("a.*")
            ->join('purc_pay_item as b', 'a.pay_id', '=', 'b.pay_id')
            ->where('a.pay_id', trim($TrID))
            ->where('a.office_id', 1)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_pay));
            $AmtJrnl = $result->pay_amt;
            $dt_pay = $result->dt_pay;
            $remark = $result->remark;
            $vendor_id = $result->vendor_id;
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Purchase Payment",
                $result->pay_id,
                $result->dt_pay,
                $AmtJrnl,
                'cr',
                "",
                $result->vendor_id,
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $result->ac_num, 'cr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

            if (abs((float)$result->selisih_bayar > 0)) {

                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num_diff,
                    "Purchase Payment",
                    $result->pay_id,
                    $result->dt_pay,
                    abs($result->selisih_bayar),
                    (float)$result->selisih_bayar > 0 ? "dr" : "cr",
                    "",
                    $result->vendor_id,
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num_diff, (float)$result->selisih_bayar  > 0 ? "dr" : "cr", $bulan2digit, abs($result->selisih_bayar), $CurrYear, 0, $userId);
            }
        }
        $data = $connection->table('purc_pay_item as a')
            ->select(
                'a.*',
                'b.rate',
                'b.curr_id',
                'b.inv_type',
                'b.ac_num_hut',
                $connection->raw("(SELECT TOP 1 ac_num_bbt FROM purc_inv_item WHERE inv_id = a.inv_id) as ac_hutang")
            )
            ->join('purc_inv_mast as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.inv_id', '=', 'b.inv_id');
            })
            ->join('purc_pay_mast as c', 'a.pay_id', '=', 'c.pay_id')
            ->where('a.office_id', 1)
            ->where('a.pay_id', $TrID)
            ->get();

        foreach ($data as $key => $detail) {
            $ac_hutang = trim($detail->inv_type) === "" ? trim($detail->ac_num_hut) : trim($detail->ac_hutang);
            $this->saveJournal(
                $Mill,
                $JvNum,
                $ac_hutang,
                "Purchase Payment",
                $TrID,
                $dt_pay,
                (float)$detail->pay_amt + (float)$detail->disc_amt,
                "dr",
                "",
                $vendor_id,
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $ac_hutang,  "dr", $bulan2digit, (float)$detail->pay_amt + (float)$detail->disc_amt, $CurrYear, 0, $userId);

            if ((float)$detail->disc_amt > 0) {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumDisc,
                    "Purchase Payment",
                    $TrID,
                    $dt_pay,
                    $detail->disc_amt,
                    "cr",
                    "",
                    $vendor_id,
                    "",
                    $remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $AcNumDisc,  "cr", $bulan2digit, $detail->disc_amt, $CurrYear, 0, $userId);
            }
            if ((float)$detail->rate_diff_amt > 0) {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $detail->ac_num_diff_rate,
                    "Purchase Payment",
                    $TrID,
                    $dt_pay,
                    $detail->rate_diff_amt,
                    "dr",
                    "",
                    $vendor_id,
                    "",
                    $remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $detail->ac_num_diff_rate,  "dr", $bulan2digit, $detail->rate_diff_amt, $CurrYear, 0, $userId);
            }
        }
    }

    public function JrnlPurcReceive($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");

        $JvNum = $this->GetJvNum($Mill, "Purchase Receipt", $TrID, $CurrYear);
        // dd($JvNum);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('purc_rcv_dtl as pd')
            ->selectRaw("pd.ac_num_dr,pd.ac_num_cr,sum(pd.amt_item) amt_item,pd.rate,pd.curr_id,
            ph.dt_rcv,ph.vendor_id,ph.memo_txt")
            ->join('purc_rcv_hdr as ph', function ($join) {
                $join->on('pd.office_id', '=', 'ph.office_id')
                    ->on('pd.purc_rcv_id', '=', 'ph.purc_rcv_id');
            })
            ->where('pd.purc_rcv_id', trim($TrID))
            ->where('pd.office_id', $Mill)
            ->groupBy(
                'pd.ac_num_dr',
                'pd.ac_num_cr',
                'pd.rate',
                'pd.curr_id',
                'ph.dt_rcv',
                'ph.vendor_id',
                'ph.memo_txt'
            )
            ->get();
        // dd($result);
        // dd($result);
        foreach ($result as $key => $detail) {
            $bulan2digit = date('m', strtotime($detail->dt_rcv));
            // dd($detail);
            $this->saveJournal(
                $Mill,
                $JvNum,
                $detail->ac_num_dr,
                "Purchase Receipt",
                $TrID,
                $detail->dt_rcv,
                $detail->amt_item,
                "dr",
                "",
                $detail->vendor_id,
                "",
                $detail->memo_txt,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $detail->ac_num_dr,  "dr", $bulan2digit, $detail->amt_item, $CurrYear, 0, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $detail->ac_num_cr,
                "Purchase Receipt",
                $TrID,
                $detail->dt_rcv,
                $detail->amt_item,
                "cr",
                "",
                $detail->vendor_id,
                "",
                $detail->memo_txt,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $detail->ac_num_cr,  "cr", $bulan2digit, $detail->amt_item, $CurrYear, 0, $userId);
        }
    }

    public function JrnlPurcInv($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");

        $AcNumHut = '';
        $AcNumPPN = $this->GetStdAccountCode("PPN MASUKAN", "IDR", "N", 1, "");
        $AcNumPPH = $this->GetStdAccountCode("PPH23", "IDR", "N", 1, "");
        $AcNumPPH4 = $this->GetStdAccountCode("PPH4", "IDR", "N", 1, "");
        $AcNumDisc = $this->GetStdAccountCode("DISKON PURC INV", "IDR", "N", 1, "");
        $AcNumPPH21 = $this->GetStdAccountCode("PPH21", "IDR", "N", 1, "L");
        $bulan2digit = '';

        $JvNum = $this->GetJvNum($Mill, "Purchase Invoice", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }

        $result = $connection->table('purc_inv_mast as a')
            ->selectRaw("a.*")
            ->where('a.tr_id', $TrID)
            ->where('a.office_id', $Mill)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_inv));
            $AcNumHut = $result->ac_num_hut;

            $this->saveJournal(
                $Mill,
                $JvNum,
                $AcNumHut,
                "Purchase Invoice",
                $TrID,
                $result->dt_inv,
                $result->amt_total,
                "cr",
                "",
                $result->vendor_id,
                $result->inv_id,
                $result->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_bbt'
            );

            $this->updateGeneralLedger($Mill, $AcNumHut,  "cr", $bulan2digit, $result->amt_total, $CurrYear, 0, $userId);

            if ((float)$result->amt_disc > 0) {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumDisc,
                    "Purchase Invoice",
                    $TrID,
                    $result->dt_inv,
                    $result->amt_disc,
                    "cr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_disc'
                );
                $this->updateGeneralLedger($Mill, $AcNumDisc,  "cr", $bulan2digit, $result->amt_disc, $CurrYear, 0, $userId);
            }

            if ((float)$result->amt_ppn > 0) {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumPPN,
                    "Purchase Invoice",
                    $TrID,
                    $result->dt_inv,
                    $result->amt_ppn,
                    "dr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_ppn'
                );
                $this->updateGeneralLedger($Mill, $AcNumPPN,  "dr", $bulan2digit, $result->amt_ppn, $CurrYear, 0, $userId);
            }

            if ((float)$result->amt_pph > 0) {
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumPPH,
                    "Purchase Invoice",
                    $TrID,
                    $result->dt_inv,
                    $result->amt_pph,
                    "cr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_pph23'
                );
                $this->updateGeneralLedger($Mill, $AcNumPPH,  "cr", $bulan2digit, $result->amt_pph, $CurrYear, 0, $userId);
            }

            if ($result->amt_pph21 > 0) {
                $bulan2digit = date('m', strtotime($result->dt_inv));
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumPPH21,
                    "Purchase Invoice",
                    $result->tr_id,
                    $result->dt_inv,
                    $result->amt_pph21,
                    "cr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_pph21'
                );
                $this->updateGeneralLedger($Mill,  $AcNumPPH21, "cr", $bulan2digit, $result->amt_pph21, $CurrYear, 0, $userId);
            }

            if ($result->amt_pph4 > 0) {
                $bulan2digit = date('m', strtotime($result->dt_inv));
                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $AcNumPPH4,
                    "Purchase Invoice",
                    $result->tr_id,
                    $result->dt_inv,
                    $result->amt_pph4,
                    "cr",
                    "",
                    $result->vendor_id,
                    $result->inv_id,
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId,
                    'ac_num_pph4'
                );
                $this->updateGeneralLedger($Mill,  $AcNumPPH4, "cr", $bulan2digit, $result->amt_pph4, $CurrYear, 0, $userId);
            }
        }

        $resultItem = $connection->table('purc_inv_item as ii')
            ->selectRaw('
                        SUM(ii.amt_item) AS amt_item,
                        ii.lpb_rate,
                        ar.ac_num_exp,
                        ca.consume_able,
                        ii.inv_rate,
                        ph.dt_inv,
                        ph.vendor_id,
                        ph.remark,
                        ph.inv_id,
                        ph.tax1,
                        ph.tax2,
                        ph.tax3,
                        ph.tax4,
                        ph.ppn as ppn_val,
                        ph.pct_pph as pph_val,
                        ph.pct_pph21 as pph21_val,
                        ph.amt_pph4 as pph4_val
                    ')
            ->join('article as ar', 'ii.article_id', '=', 'ar.article_id')
            ->join('category as ca', "ar.cat_id", '=', "ca.cat_id")
            ->join('purc_inv_mast as ph', function ($join) {
                $join->on('ph.office_id', '=', 'ii.office_id')
                    ->on('ph.inv_id', '=', 'ii.inv_id');
            })
            ->where('ii.office_id', $Mill)
            ->where('ii.tr_id', $TrID)
            ->groupBy(
                'ii.lpb_rate',
                'ar.ac_num_exp',
                'ca.consume_able',
                'ii.inv_rate',
                'ph.dt_inv',
                'ph.vendor_id',
                'ph.remark',
                'ph.inv_id',
                'ph.tax1',
                'ph.tax2',
                'ph.tax3',
                'ph.tax4',
                'ph.ppn',
                'ph.pct_pph',
                'ph.pct_pph21',
                'ph.amt_pph4'
            )
            ->get();

        // class loader
        $CalcIncludeService = new PerhitunganIncludeService();
        $detailCount = count($resultItem);

        // -- detail
        foreach ($resultItem as $key => $detail) {
            $rcvBiaya = $this->getEnvConf("FINANCE", "RCVBIAYA");
            $subTotal = round($detail->amt_item, 0);

            $calcHargaInEx = $CalcIncludeService->hitungHargaDasarIncludeExclude(
                $subTotal,
                ((float) $detail->ppn_val / 100),
                ((float) $detail->pph_val / 100),
                ((float) $detail->pph21_val / 100),
                [trim($detail->tax1), trim($detail->tax2), trim($detail->tax4), trim($detail->tax3)],
                (float) $detail->pph4_val / $detailCount
            );

            // -- harga dasar 
            $AmtSubTotal = $calcHargaInEx[0];

            // if (trim($rcvBiaya) === "N") {
            //     if (trim($detail->consume_able) === "Y") {
            //         $this->saveJournal(
            //             $Mill,
            //             $JvNum,
            //             $detail->ac_num_exp,
            //             "Purchase Invoice",
            //             $TrID,
            //             $detail->dt_inv,
            //             $AmtSubTotal,
            //             "dr",
            //             "",
            //             $detail->vendor_id,
            //             $detail->inv_id,
            //             $detail->remark,
            //             "",
            //             1,
            //             "IDR",
            //             $userId,
            //             'ac_num_exp'
            //         );

            //         $this->updateGeneralLedger($Mill, $detail->ac_num_exp,  "dr", $bulan2digit, $AmtSubTotal, $CurrYear, 0, $userId);
            //     } else {
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num_bbt,
                "Purchase Invoice",
                $TrID,
                $detail->dt_inv,
                $AmtSubTotal,
                "dr",
                "",
                $detail->vendor_id,
                $detail->inv_id,
                $detail->remark,
                "",
                1,
                "IDR",
                $userId,
                'ac_num_hut'
            );
            $this->updateGeneralLedger($Mill, $result->ac_num_bbt,  "dr", $bulan2digit, $AmtSubTotal, $CurrYear, 0, $userId);
            //     }
            // } else {
            //     $this->saveJournal(
            //         $Mill,
            //         $JvNum,
            //         $detail->ac_num_exp,
            //         "Purchase Invoice",
            //         $TrID,
            //         $detail->dt_inv,
            //         $AmtSubTotal,
            //         "dr",
            //         "",
            //         $detail->vendor_id,
            //         $detail->inv_id,
            //         $detail->remark,
            //         "",
            //         1,
            //         "IDR",
            //         $userId,
            //         'ac_num_exp'
            //     );
            //     $this->updateGeneralLedger($Mill, $detail->ac_num_exp,  "dr", $bulan2digit, $AmtSubTotal, $CurrYear, 0, $userId);
            // }
        }
    }

    public function JrnlCustPayment($Mill, $TrID, $CurrYear, $userId)
    {
        $bulan2digit = "";
        $AmtJrnl = 0;
        $dt_pay = "";
        $remark = "";
        $customer_id = "";
        $connection = DB::connection("sqlSrvFPI");
        $SPPN = $this->GetStdAccountCode("PPN KELUARAN", "IDR", "N", 1, "");
        $sUangMuka = $this->GetStdAccountCode("UANG MUKA PENJUALAN", "IDR", "N", 1, "");
        $DanaDitahan = $this->GetStdAccountCode("DANA DITAHAN", "IDR", "N", 1, "");
        // $sLebihBayar = $this->GetStdAccountCode("LEBIH BAYAR", "IDR", "N", 1, "");
        $DiscPayment = $this->GetStdAccountCode("DISKON PAYMENT", "IDR", "N", 1, "");
        $JvNum = $this->GetJvNum($Mill, "Payment Posting", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);
        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }
        $result = $connection->table('payment_hdr as a')
            ->selectRaw("a.*")
            ->join('payment_dtl as b', 'a.pay_id', '=', 'b.pay_id')
            ->where('a.pay_id', trim($TrID))
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->tr_date));
            $AmtJrnl = $result->pay_amt;
            $dt_pay = $result->tr_date;
            $remark = $result->remark;
            $customer_id = $result->customerid;
            $sBank = ($result->tr_type == 'D') ? $result->ac_num : $this->GetStdAccountCode("PAYMENT", "IDR", "N", 1, $result->bank_acc);
            $this->saveJournal(
                $Mill,
                $JvNum,
                $sBank,
                "Payment Posting",
                $result->pay_id,
                $result->tr_date,
                $AmtJrnl,
                'dr',
                $customer_id,
                "",
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $sBank, 'dr', $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

            if (abs((float)$result->selisih_penj > 0)) {

                $this->saveJournal(
                    $Mill,
                    $JvNum,
                    $result->ac_num_selisih_penj,
                    "Payment Posting",
                    $result->pay_id,
                    $result->tr_date,
                    $result->selisih_penj,
                    'cr',
                    $customer_id,
                    "",
                    "",
                    $result->remark,
                    "",
                    1,
                    "IDR",
                    $userId
                );
                $this->updateGeneralLedger($Mill, $result->ac_num_selisih_penj, 'cr', $bulan2digit, $result->selisih_penj, $CurrYear, 0, $userId);
            }
        }

        $result =  DB::connection('sqlSrvFPI')->table('payment_dtl as a')
            ->join('penjualan as b', 'a.penjualanno', '=', 'b.penjualanno')
            ->selectRaw('SUM(a.pay_amt - a.disc_amt) AS total_pay, b.inv_type')
            ->where('a.pay_id', $TrID)
            ->groupBy('b.inv_type')
            ->get();

        foreach ($result as $row) {
            $invType  = $row->inv_type;
            $totalPay = $row->total_pay;
            $sBayar = Round((float)$totalPay / 1.11, 0);
            $sPpnVal = (float)$totalPay - (float)$sBayar;
            $crAcc = $invType === "KPR" ? $DanaDitahan : $sUangMuka;

            $this->saveJournal(
                $Mill,
                $JvNum,
                $crAcc,
                "Payment Posting",
                $TrID,
                $dt_pay,
                $sBayar,
                "cr",
                $customer_id,
                "",
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $crAcc,  "cr", $bulan2digit, $sBayar, $CurrYear, 0, $userId);
        }

        $sumPay = DB::connection('sqlSrvFPI')->table('payment_dtl as a')
            ->join('penjualan as b', 'a.penjualanno', '=', 'b.penjualanno')
            ->where('a.pay_id', $TrID)
            ->sum(DB::raw('a.pay_amt - a.disc_amt')) ?? 0;
        $sBayar = Round((float)$sumPay / 1.11, 0);
        $sPpnVal = (float)$sumPay - (float)$sBayar;
        if ((float)$sumPay > 0) {
            // $this->saveJournal(
            //     $Mill,
            //     $JvNum,
            //     $sUangMuka,
            //     "Payment Posting",
            //     $TrID,
            //     $dt_pay,
            //     $sBayar,
            //     "cr",
            //     $customer_id,
            //     "",
            //     "",
            //     $remark,
            //     "",
            //     1,
            //     "IDR",
            //     $userId
            // );
            // $this->updateGeneralLedger($Mill, $sUangMuka,  "cr", $bulan2digit, $sBayar, $CurrYear, 0, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $SPPN,
                "Payment Posting",
                $TrID,
                $dt_pay,
                $sPpnVal,
                "cr",
                $customer_id,
                "",
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $sUangMuka,  "cr", $bulan2digit, $sBayar, $CurrYear, 0, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                "217-010-002",
                "Payment Posting",
                $TrID,
                $dt_pay,
                $AmtJrnl,
                "cr",
                $customer_id,
                "",
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, "217-010-002",  "cr", $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                "130-010-003",
                "Payment Posting",
                $TrID,
                $dt_pay,
                $AmtJrnl,
                "dr",
                $customer_id,
                "",
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, "130-010-003",  "dr", $bulan2digit, $AmtJrnl, $CurrYear, 0, $userId);
        }
        $sumPay = DB::connection('sqlSrvFPI')->table('payment_dtl as a')
            ->where('a.pay_id', $TrID)
            ->sum('a.disc_amt') ?? 0;
        if ((float)$sumPay > 0) {
            $this->saveJournal(
                $Mill,
                $JvNum,
                $DiscPayment,
                "Payment Posting",
                $TrID,
                $dt_pay,
                $sumPay,
                "cr",
                $customer_id,
                "",
                "",
                $remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill, $DiscPayment,  "cr", $bulan2digit, $sumPay, $CurrYear, 0, $userId);
        }
    }
    public function JournalRevise($OfficeId, $CurrYear, $JvNum, $AcNumOld, $AcNumNew, $UserId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $result = $connection->table('journal_voucher as a')
            ->selectRaw("a.*")
            ->where('a.office_id', $OfficeId)
            ->where('a.year_id', $CurrYear)
            ->where('a.jv_num', $JvNum)
            ->where('a.ac_num', $AcNumOld)
            ->first();
        $bulan2digit = date('m', strtotime($result->tr_date));
        if (!$result) {
            return false;
        }
        $oldValues = (array) $result;
        // dd(trim(strtoupper($oldValues['source'])));
        // return $oldValues;
        // dd($oldValues);
        $checkDuplicate = $connection->table('journal_voucher')
            ->where('office_id', $OfficeId)
            ->where('year_id', $CurrYear)
            ->where('jv_num', $JvNum)
            ->where('ac_num', $AcNumNew)
            ->first();
        // dd($checkDuplicate,$oldValues);
        if ($checkDuplicate) {
            DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->where('office_id', $OfficeId)
                ->where('year_id',  $CurrYear)
                ->where('jv_num', $JvNum)
                ->where('ac_num', $AcNumOld)
                ->update([
                    'amt_dr' => 0,
                    'amt_cr' => 0
                ]);
            $this->updateGeneralLedger($OfficeId, $AcNumOld, $oldValues['drcr'], $bulan2digit, 0, $CurrYear, 0, $UserId);
            $connection->table('journal_voucher')
                ->where('office_id', $OfficeId)
                ->where('year_id', $CurrYear)
                ->where('jv_num', $JvNum)
                ->where('ac_num', $AcNumNew)
                ->update([
                    'amt_dr' => $checkDuplicate->amt_dr + $oldValues['amt_dr'],
                    'amt_cr' => $checkDuplicate->amt_cr + $oldValues['amt_cr']
                ]);
        } else {
            DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->where('office_id', $OfficeId)
                ->where('year_id',  $CurrYear)
                ->where('jv_num', $JvNum)
                ->where('ac_num', $AcNumOld)
                ->update([
                    'amt_dr' => 0,
                    'amt_cr' => 0
                ]);

            $this->updateGeneralLedger($OfficeId, $AcNumOld, $oldValues['drcr'], $bulan2digit, 0, $CurrYear, 0, $UserId);

            DB::connection('sqlSrvFPI')->table('journal_voucher')
                ->where('office_id', $OfficeId)
                ->where('year_id',  $CurrYear)
                ->where('jv_num', $JvNum)
                ->where('ac_num',  $AcNumOld)
                ->update([
                    'ac_num' => $AcNumNew,
                    'amt_dr' => $oldValues['amt_dr'],
                    'amt_cr' => $oldValues['amt_cr']
                ]);
        }
        //update coa
        switch (trim(strtoupper($oldValues['source']))) {
            case 'OTHER PAYMENT':
                DB::connection('sqlSrvFPI')->table('cash_tran_dtl')
                    ->where('office_id', $OfficeId)
                    ->where('tr_type',  'OP')
                    ->where('tr_id', $oldValues['source_num'])
                    ->where('ac_num',  $AcNumOld)
                    ->update([
                        'ac_num' => $AcNumNew
                    ]);
                break;
            case 'OTHER DEPOSIT':
                DB::connection('sqlSrvFPI')->table('cash_tran_dtl')
                    ->where('office_id', $OfficeId)
                    ->where('tr_type',  'OD')
                    ->where('tr_id',  $oldValues['source_num'])
                    ->where('ac_num',  $AcNumOld)
                    ->update([
                        'ac_num' => $AcNumNew
                    ]);
                break;
            case 'OTHER JOURNAL VOUCHER':
                DB::connection('sqlSrvFPI')->table('jv_trans_dtl')
                    ->where('office_id', $OfficeId)
                    ->where('tr_id', $oldValues['source_num'])
                    ->where('ac_num',  $AcNumOld)
                    ->update([
                        'ac_num' => $AcNumNew
                    ]);
                break;
            default:
                break;
        }
        $resultNew = $connection->table('journal_voucher as a')
            ->selectRaw("a.*")
            ->where('a.office_id', $OfficeId)
            ->where('a.year_id', $CurrYear)
            ->where('a.jv_num', $JvNum)
            ->where('a.ac_num', $AcNumNew)
            ->first();
        $this->updateGeneralLedger($OfficeId, $resultNew->ac_num, $resultNew->drcr, $bulan2digit, (float)$resultNew->amt_cr > 0 ? $resultNew->amt_cr : $resultNew->amt_dr, $CurrYear, 0, $UserId);
    }

    public function JrnlkasBonExternal($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        //GetStdAccountCode($AccountName, $CurrID, $Import, $Office, $DivID)
        $acNumDr = $this->GetStdAccountCode("UANG MUKA PEMBELIAN", "IDR", "N", 1, "");
        $JvNum = $this->GetJvNum($Mill, "Kas Bon External", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }

        $result = $connection->table('kb_hdr as a')
            ->selectRaw("a.*")
            ->join('kb_dtl as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.tr_id', '=', 'b.tr_id');
            })
            ->where('a.tr_type', 'EX')
            ->where('a.tr_id', $TrID)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_kb));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num,
                "Kas Bon External",
                $result->tr_id,
                $result->dt_kb,
                $result->kb_amt,
                "cr",
                "",
                $result->vendor_id,
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $result->ac_num, "cr", $bulan2digit, $result->kb_amt, $CurrYear, $result->kb_amt, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $acNumDr,
                "Kas Bon External",
                $result->tr_id,
                $result->dt_kb,
                $result->kb_amt,
                "dr",
                "",
                $result->vendor_id,
                "",
                $result->remark,
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $acNumDr, "dr", $bulan2digit, $result->kb_amt, $CurrYear, $result->kb_amt, $userId);
        }
    }
    public function JrnlPurcRetur($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        //GetStdAccountCode($AccountName, $CurrID, $Import, $Office, $DivID)
        $acNumCr = $this->GetStdAccountCode("RETUR PEMBELIAN ", "IDR", "N", 1, "");
        $JvNum = $this->GetJvNum($Mill, "Purchase Retur", $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }

        $result = $connection->table('purc_rtn_hdr as a')
            ->selectRaw("a.amt_total,a.ac_num_hut,dt_rtn,a.purc_rtn_id,a.vendor_id")
            ->join('purc_rtn_dtl as b', function ($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.purc_rtn_id', '=', 'b.purc_rtn_id');
            })
            ->where('a.purc_rtn_id', $TrID)
            ->first();

        if ($result) {
            $bulan2digit = date('m', strtotime($result->dt_rtn));
            $this->saveJournal(
                $Mill,
                $JvNum,
                $result->ac_num_hut,
                "Purchase Retur",
                $result->purc_rtn_id,
                $result->dt_rtn,
                $result->amt_total,
                "dr",
                "",
                $result->vendor_id,
                "",
                $result->remark ?? '',
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $result->ac_num_hut, "dr", $bulan2digit, $result->amt_total, $CurrYear,  $result->amt_total, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                $acNumCr,
                "Purchase Retur",
                $result->purc_rtn_id,
                $result->dt_rtn,
                $result->amt_total,
                "cr",
                "",
                $result->vendor_id,
                "",
                $result->remark ?? '',
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  $acNumCr, "cr", $bulan2digit, $result->amt_total, $CurrYear, $result->amt_total, $userId);
        }
    }
    public function ifAcctClosed($tr_date, $request)
    {
        $userId = getAuth($request)->user_id2 ?? null;
        $tanggal = Carbon::parse($tr_date);
        $formatTgl = $tanggal->format('Ym');
        $cekUserFinal = DB::table('sec_env_conf')->where('entity_id', 'FDPI')->where('appl_id', 'FINANCE')->where('var_id', 'FINAL_CLOSING')->where('user_id2', $userId)->first();
        if ($cekUserFinal) {
            $result = DB::connection("sqlSrvFPI")
                ->table('acct_close_period')
                ->selectRaw("final_close_period")
                ->where('final_close_period', '>=', $formatTgl)
                ->first();
            if ($result) {
                return true;
            } else {
                return false;
            }
        } else {
            $result = DB::connection("sqlSrvFPI")
                ->table('acct_close_period')
                ->selectRaw("close_period")
                ->where('close_period', '>=', $formatTgl)
                ->first();
            if ($result) {
                return true;
            } else {
                return false;
            }
        }
    }
    public function JrnlCustomerInvoice($Mill, $TrID, $CurrYear, $userId)
    {
        $connection = DB::connection("sqlSrvFPI");
        $SrcName = "Customer Invoice";
        $JvNum = $this->GetJvNum($Mill, $SrcName, $TrID, $CurrYear);
        $this->DeleteJournalByJvNum($Mill, $JvNum, $CurrYear);

        if ($JvNum == 0) {
            $JvNum = $this->getNextJvNum($Mill, $CurrYear);
        }

        $result = $connection->table('penjualan as a')
            ->selectRaw("a.*")
            ->where('a.penjualanno', $TrID)
            ->first();
        //dd($result);
        if ($result) {
            $bulan2digit = date('m', strtotime($result->TglJual));
            $this->saveJournal(
                $Mill,
                $JvNum,
                "130-010-003",
                $SrcName,
                $result->PenjualanNO,
                $result->TglJual,
                $result->TotalJual,
                "cr",
                $result->CustomerId,
                "",
                "",
                "",
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  "130-010-003", "cr", $bulan2digit, $result->TotalJual, $CurrYear, $result->TotalJual, $userId);

            $this->saveJournal(
                $Mill,
                $JvNum,
                "217-010-002",
                $SrcName,
                $result->PenjualanNO,
                $result->TglJual,
                $result->TotalJual,
                "dr",
                $result->CustomerId,
                "",
                "",
                "",
                "",
                1,
                "IDR",
                $userId
            );
            $this->updateGeneralLedger($Mill,  "217-010-002", "dr", $bulan2digit, $result->TotalJual, $CurrYear, $result->TotalJual, $userId);
        }
    }
}
