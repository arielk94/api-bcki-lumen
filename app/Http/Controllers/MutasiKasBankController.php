<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Str;
use Image, Storage;

class MutasiKasBankController extends Controller
{
    public function getDataMutasi(Request $request)
    {   
        $sqlWhere = "1=1";
        $acNum = $request->acNum; // contoh: '120-010-001'
        $start_date = date('Y-m-d', strtotime($request->start_date)) ; // contoh: '20250101'
        $end_date = date('Y-m-d', strtotime($request->end_date)); // contoh: '20250531'
        $office_id = $request->office_id; // contoh: '1'

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
                $sqlWhere .= " and a.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and a.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        if ($acNum) {
            $sqlWhere .= " AND a.ac_num = '$acNum'";
        }
        if ($office_id) {
            $sqlWhere .= " AND a.office_id = '$office_id'";
        }
        

        // 1. Ambil saldo awal
        $saldoAwal = $this->calcGLedgerBegin($office_id, $acNum, $start_date);

        // 2. Buat sqlWhere
        // $sqlWhere = "a.tr_date BETWEEN '$start_date' AND '$end_date' AND a.ac_num = '$acNum'";

        // 3. Ambil data mutasi
        $mutasi = DB::connection('sqlSrvBCKI')
        ->table('journal_voucher as a')
        ->selectRaw("
            a.ac_num,
            a.tr_date,
            a.source,
            a.source_num,
            a.cek_id,
            a.remark,
            a.amt_dr,
            a.amt_cr,
            ISNULL(c.namacustomer, '') AS nama,
            ISNULL((
                SELECT STUFF((
                    SELECT ',' + LTRIM(RTRIM(CAST(inv_id AS VARCHAR)))
                    FROM payment_dtl
                    WHERE pay_id = a.source_num
                    FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '')
            ), '') AS invID,
            ISNULL((
                SELECT STUFF((
                    SELECT ', ' + LTRIM(RTRIM(CAST(ac_num AS VARCHAR)))
                    FROM journal_voucher z
                    WHERE z.source_num = a.source_num AND z.ac_num <> a.ac_num
                    FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '')
            ), '') AS Lawan
        ")
        ->leftJoin('payment_hdr as b', 'a.source_num', '=', 'b.pay_id')
        ->leftJoin('customer as c', 'b.customerid', '=', 'c.customerid')
        ->whereRaw($sqlWhere)
        ->orderBy('a.tr_date')
        ->orderBy('a.source')
        ->get();

        // 4. Proses data + saldo berjalan
        $saldo = $saldoAwal;
        $totalDr = 0;
        $totalCr = 0;
        $dataFinal = [];

        // Tambahkan baris saldo awal
        $dataFinal[] = [
            'tr_date' => date('Y-m-d', strtotime($start_date . ' -1 day')),
            'source' => '',
            'source_num' => '',
            'cek_id' => '',
            'remark' => 'As of ' . date('d-m-Y', strtotime($start_date . ' -1 day')),
            'amt_dr' => null,
            'amt_cr' => null,
            'saldo' => number_format($saldoAwal, 2),
            'invID' => '',
            'Lawan' => '',
            'nama' => '',
        ];

        foreach ($mutasi as $row) {
            $saldo += ($row->amt_dr - $row->amt_cr);
            $totalDr += $row->amt_dr;
            $totalCr += $row->amt_cr;

            $dataFinal[] = [
                'ac_num' => $row->ac_num,
                'tr_date' => $row->tr_date,
                'source' => $row->source,
                'source_num' => $row->source_num,
                'cek_id' => $row->cek_id,
                'remark' => $row->remark,
                'amt_dr' => $row->amt_dr,
                'amt_cr' => $row->amt_cr,
                'saldo' => number_format($saldo, 2),
                'invID' => $row->invID,
                'Lawan' => $row->Lawan,
                'nama' => $row->nama,
            ];
        }

        // return response()->json(['data' => $dataFinal,'total_debit' => $totalDr,'total_credit' => $totalCr,'saldo_awal' => $saldoAwal,'saldo_akhir' => $saldo], 200);

        // return response()->json(['GenPrId' => $GenPrId], 200);  
        
        return $this->sendResponse(['data' => $dataFinal,'total_debit' => $totalDr,'total_credit' => $totalCr,'saldo_awal' => $saldoAwal,'saldo_akhir' => $saldo], 'Successfully get Data');
        

    }

    private function calcGLedgerBegin($office_id, $acNum, $dtAw)
    {
        $bulan = (int) date('m', strtotime($dtAw));
        $tahun = date('Y', strtotime($dtAw));
        $amtDr = 0;
        $amtCr = 0;

        // Ambil data dari g_ledger_year dan g_ledger
        $record = DB::connection('sqlSrvBCKI')
            ->table('g_ledger_year as a')
            ->join('g_ledger as b', function($join) {
                $join->on('a.office_id', '=', 'b.office_id')
                    ->on('a.ac_num', '=', 'b.ac_num');
            })
            ->where('a.office_id', $office_id)
            ->where('a.year_id', $tahun)
            ->where('a.ac_num', $acNum)
            ->first();

        if ($record) {
            for ($i = 1; $i < $bulan; $i++) {
                $drField = 'dr_' . str_pad($i, 2, '0', STR_PAD_LEFT);
                $crField = 'cr_' . str_pad($i, 2, '0', STR_PAD_LEFT);

                $amtDr += $record->$drField ?? 0;
                $amtCr += $record->$crField ?? 0;
            }

            $amtDr += $record->amt_begin_dr ?? 0;
            $amtCr += $record->amt_begin_cr ?? 0;
        }

        // Jika dtAw bukan awal bulan, cari tambahan dari journal_voucher
        $bom = date('Y-m-01', strtotime($dtAw));
        $oneDayBefore = date('Y-m-d', strtotime($dtAw . ' -1 day'));
        $isSame = $bom === date('Y-m-d', strtotime($dtAw));

        if (!$isSame) {
            $data = DB::connection('sqlSrvBCKI')
                ->table('journal_voucher')
                ->selectRaw('rate, SUM(amt_dr) as amt_dr, SUM(amt_cr) as amt_cr')
                ->where('office_id', $office_id)
                ->where('ac_num', $acNum)
                ->whereBetween('tr_date', [$bom, $oneDayBefore])
                ->groupBy('rate')
                ->first();

            if ($data && $data->rate != 0) {
                $amtDr += $data->amt_dr / $data->rate;
                $amtCr += $data->amt_cr / $data->rate;
            }
        }

        return $amtDr - $amtCr;
    }

    public function summaryMutasi(Request $request){
        $sqlWhere = "1=1";
        $acNum = $request->acNum; // contoh: '120-010-001'
        $start_date = date('Y-m-d', strtotime($request->start_date)) ; // contoh: '20250101'
        $end_date = date('Y-m-d', strtotime($request->end_date)); // contoh: '20250531'
        $office_id = $request->office_id; // contoh: '1'

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
                $sqlWhere .= " and a.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and a.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        // ac_num IN (...)
        if (is_array($acNum) && count($acNum)) {
            $inList = "'" . implode("','", $acNum) . "'";
            $sqlWhere .= " AND a.ac_num IN ($inList)";
        }

        if ($office_id) {
            $sqlWhere .= " AND a.office_id = '$office_id'";
        }

        $results = DB::connection('sqlSrvBCKI')
            ->table('journal_voucher as a')
            ->leftJoin('journal_voucher as z', function ($join) {
                $join->on('a.source_num', '=', 'z.source_num')
                    ->whereRaw('z.ac_num <> a.ac_num');
            })
            ->leftJoin('g_ledger as b','a.ac_num', '=', 'b.ac_num')
            ->leftJoin('g_ledger as c','z.ac_num', '=', 'c.ac_num')
            ->selectRaw('
                a.ac_num,
                b.ac_name,
                z.ac_num AS lawan,
                c.ac_name as ac_lawan,
                SUM(z.amt_dr) AS dr_lawan,
                SUM(z.amt_cr) AS cr_lawan,
                SUM(SUM(z.amt_dr)) OVER () AS total_dr_lawan,
                SUM(SUM(z.amt_cr)) OVER () AS total_cr_lawan
            ')
            ->whereRaw($sqlWhere)
            ->groupBy('a.ac_num', 'z.ac_num', 'b.ac_name', 'c.ac_name')
            ->orderBy('a.ac_num')
            ->orderBy('z.ac_num')
            ->orderBy('b.ac_name')
            ->orderBy('c.ac_name')
            ->get();

        return $this->sendResponse(['results' => $results], 'Successfully get Data');
        
    }

}
