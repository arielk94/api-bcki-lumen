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

class COAMappingReportController extends Controller
{

    public function getDistinctRep(Request $request)
    {
        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('g_ledger_map')
                ->selectRaw("DISTINCT TRIM(rep_id) as rep_id")
                ->whereNotNull('rep_id')
                ->where('rep_id', '<>', '')
                ->orderBy('rep_id', 'ASC')
                ->pluck('rep_id');

            return $this->sendResponse($data, 'Successfully retrieved distinct Report IDs');
        } catch (\Exception $e) {
            // return $this->sendError('Internal Error', $e->getMessage(), 500);
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }

    public function getData(Request $request)
    {
        try {
            // 1. Validation
            $rep_id       = trim($request->rep_id);
            $start_period = trim($request->start_period); // ex: 202511
            $end_period   = trim($request->end_period);   // ex: 202608
            $rep_type     = trim($request->rep_type ?? 'saldo_total');

            if (empty($rep_id)) {
                return $this->sendError('Validation Error', 'Report ID (rep_id) is required', 400);
            }
            if (empty($start_period) || empty($end_period)) {
                return $this->sendError('Validation Error', 'Start Period and End Period are required', 400);
            }

            // 2. Generate Daftar Bulan (YYYYMM) dalam Rentang Start & End
            $periods = [];
            try {
                $start_dt = Carbon::createFromFormat('Ym', $start_period)->startOfMonth();
                $end_dt   = Carbon::createFromFormat('Ym', $end_period)->startOfMonth();

                if ($start_dt->greaterThan($end_dt)) {
                    return $this->sendError('Validation Error', 'Start period must be less than or equal to End period', 400);
                }

                $curr = clone $start_dt;
                while ($curr->lessThanOrEqualTo($end_dt)) {
                    $periods[] = [
                        'year'   => (int)$curr->year,
                        'month'  => (int)$curr->month,
                        'period' => $curr->format('Ym'),
                    ];
                    $curr->addMonth();
                }
            } catch (\Exception $e) {
                return $this->sendError('Validation Error', 'Invalid period format. Expected YYYYMM.', 400);
            }

            $minYear = min(array_column($periods, 'year'));
            $maxYear = max(array_column($periods, 'year'));

            // 3. Query Raw Data dari DB (Menggabungkan Akun & Baris Formula via UNION ALL)
            // Query 3A: Data Akun Berelasi
            $queryAccounts = DB::connection('sqlSrvBCKI')
                ->table('v_gledger_report_map')
                ->join('g_ledger_map', function ($join) {
                    $join->on('v_gledger_report_map.rep_id', '=', 'g_ledger_map.rep_id')
                        ->on('v_gledger_report_map.acc_num', '=', 'g_ledger_map.acc_num');
                })
                ->leftJoin('g_ledger', function ($join) {
                    $join->on('v_gledger_report_map.acc_num', '=', 'g_ledger.ac_num');
                })
                ->select([
                    'v_gledger_report_map.rep_id',
                    'v_gledger_report_map.cat',
                    'v_gledger_report_map.cat_seq',
                    'v_gledger_report_map.sub_cat',
                    'v_gledger_report_map.sub_cat_seq',
                    'v_gledger_report_map.show_on_report',
                    'v_gledger_report_map.acc_par',
                    'v_gledger_report_map.acc_num',
                    DB::raw("CASE WHEN UPPER(RTRIM(g_ledger.ac_type)) = 'IN' THEN 'IC' ELSE UPPER(RTRIM(g_ledger.ac_type)) END as ac_type"),
                    DB::raw("COALESCE(NULLIF(TRIM(g_ledger_map.acc_name), ''), TRIM(v_gledger_report_map.ac_name)) as ac_name"),
                    'v_gledger_report_map.year_id',
                    'v_gledger_report_map.amt_begin_dr',
                    'v_gledger_report_map.amt_begin_cr',
                    'v_gledger_report_map.dr_01', 'v_gledger_report_map.cr_01',
                    'v_gledger_report_map.dr_02', 'v_gledger_report_map.cr_02',
                    'v_gledger_report_map.dr_03', 'v_gledger_report_map.cr_03',
                    'v_gledger_report_map.dr_04', 'v_gledger_report_map.cr_04',
                    'v_gledger_report_map.dr_05', 'v_gledger_report_map.cr_05',
                    'v_gledger_report_map.dr_06', 'v_gledger_report_map.cr_06',
                    'v_gledger_report_map.dr_07', 'v_gledger_report_map.cr_07',
                    'v_gledger_report_map.dr_08', 'v_gledger_report_map.cr_08',
                    'v_gledger_report_map.dr_09', 'v_gledger_report_map.cr_09',
                    'v_gledger_report_map.dr_10', 'v_gledger_report_map.cr_10',
                    'v_gledger_report_map.dr_11', 'v_gledger_report_map.cr_11',
                    'v_gledger_report_map.dr_12', 'v_gledger_report_map.cr_12',
                ])
                ->where('v_gledger_report_map.rep_id', $rep_id)
                ->where(function ($query) use ($maxYear) {
                    $query->where(DB::raw("CAST(v_gledger_report_map.year_id AS INT)"), '<=', $maxYear)
                        ->orWhereNull('v_gledger_report_map.year_id');
                });

            // Query 3B: Khusus Baris Formula (sub_cat LIKE '=%')
            $queryFormulas = DB::connection('sqlSrvBCKI')
                ->table('v_gledger_report_map')
                ->select([
                    'rep_id',
                    'cat',
                    'cat_seq',
                    'sub_cat',
                    'sub_cat_seq',
                    'show_on_report',
                    'acc_par',
                    'acc_num',
                    DB::raw("NULL as ac_type"),
                    DB::raw("ac_name"),
                    'year_id',
                    DB::raw("0 as amt_begin_dr"),
                    DB::raw("0 as amt_begin_cr"),
                    DB::raw("0 as dr_01"), DB::raw("0 as cr_01"),
                    DB::raw("0 as dr_02"), DB::raw("0 as cr_02"),
                    DB::raw("0 as dr_03"), DB::raw("0 as cr_03"),
                    DB::raw("0 as dr_04"), DB::raw("0 as cr_04"),
                    DB::raw("0 as dr_05"), DB::raw("0 as cr_05"),
                    DB::raw("0 as dr_06"), DB::raw("0 as cr_06"),
                    DB::raw("0 as dr_07"), DB::raw("0 as cr_07"),
                    DB::raw("0 as dr_08"), DB::raw("0 as cr_08"),
                    DB::raw("0 as dr_09"), DB::raw("0 as cr_09"),
                    DB::raw("0 as dr_10"), DB::raw("0 as cr_10"),
                    DB::raw("0 as dr_11"), DB::raw("0 as cr_11"),
                    DB::raw("0 as dr_12"), DB::raw("0 as cr_12"),
                ])
                ->where('rep_id', $rep_id)
                ->where('sub_cat', 'LIKE', '=%');

            // Eksekusi Union All & Sorting
            $raw_data = $queryAccounts
                ->unionAll($queryFormulas)
                ->orderBy('cat_seq', 'ASC')
                ->orderBy('sub_cat_seq', 'ASC')
                ->orderBy('acc_par', 'ASC')
                ->orderBy('acc_num', 'ASC')
                ->get();

            $groupedByAccount = $raw_data->groupBy(function ($item) {
                $accNum = trim($item->acc_num ?? '');
                if ($accNum === '') {
                    return 'FORMULA_' . $item->cat_seq . '_' . $item->sub_cat_seq;
                }
                return 'ACC_' . $accNum;
            });

            // 4. Kalkulasi Data Berdasarkan Mode (period vs total)
            if ($rep_type === 'period') {
                $processed = [];

                foreach ($periods as $p) {
                    $targetYear  = $p['year'];
                    $targetMonth = $p['month'];

                    foreach ($groupedByAccount as $accNum => $rows) {
                        $firstRow      = $rows->first();
                        $acType        = strtoupper(trim($firstRow->ac_type ?? ''));
                        $isNeraca      = in_array($acType, ['AS', 'LI', 'EQ']);
                        $isNormalDebit = in_array($acType, ['AS', 'CS', 'EX']);

                        // Group data per tahun
                        $rowsByYear = $rows->groupBy(function ($item) {
                            return $item->year_id !== null ? (int)$item->year_id : null;
                        });

                        $saldo_awal_dr = 0;
                        $saldo_awal_cr = 0;
                        $debit         = 0;
                        $credit        = 0;

                        // --- 1. HITUNG SALDO AWAL (HANYA MENGGUNAKAN TARGET YEAR) ---
                        if (isset($rowsByYear[$targetYear])) {
                            $targetYearRows = $rowsByYear[$targetYear];
                            $firstYearRow   = $targetYearRows->first();

                            if ($isNeraca) {
                                // Saldo awal tahun berjalan (Cukup 1x dari record pertama)
                                $saldo_awal_dr += (float)($firstYearRow->amt_begin_dr ?? 0);
                                $saldo_awal_cr += (float)($firstYearRow->amt_begin_cr ?? 0);

                                // Akumulasi mutasi dari bulan 1 s/d (targetMonth - 1)
                                if ($targetMonth > 1) {
                                    foreach ($targetYearRows as $r) {
                                        for ($m = 1; $m < $targetMonth; $m++) {
                                            $saldo_awal_dr += (float)($r->{"dr_" . sprintf('%02d', $m)} ?? 0);
                                            $saldo_awal_cr += (float)($r->{"cr_" . sprintf('%02d', $m)} ?? 0);
                                        }
                                    }
                                }
                            } else {
                                // Laba Rugi: Mulai dari 0 untuk bulan berjalan atau sesuai kebutuhan mutasi awal tahun
                                if ($targetMonth > 1) {
                                    foreach ($targetYearRows as $r) {
                                        for ($m = 1; $m < $targetMonth; $m++) {
                                            $saldo_awal_dr += (float)($r->{"dr_" . sprintf('%02d', $m)} ?? 0);
                                            $saldo_awal_cr += (float)($r->{"cr_" . sprintf('%02d', $m)} ?? 0);
                                        }
                                    }
                                }
                            }

                            // --- 2. HITUNG MUTASI BULAN TARGET ---
                            foreach ($targetYearRows as $r) {
                                $debit  += (float)($r->{"dr_" . sprintf('%02d', $targetMonth)} ?? 0);
                                $credit += (float)($r->{"cr_" . sprintf('%02d', $targetMonth)} ?? 0);
                            }
                        }

                        // --- 3. KALKULASI NET VALUES ---
                        $saldo_awal_net = $isNormalDebit 
                            ? ($saldo_awal_dr - $saldo_awal_cr) 
                            : ($saldo_awal_cr - $saldo_awal_dr);

                        $perubahan_net = $isNormalDebit 
                            ? ($debit - $credit) 
                            : ($credit - $debit);

                        $saldo_akhir_net = $saldo_awal_net + $perubahan_net;

                        $processed[] = [
                            'rep_id'         => $firstRow->rep_id,
                            'cat'            => $firstRow->cat,
                            'cat_seq'        => $firstRow->cat_seq,
                            'sub_cat'        => $firstRow->sub_cat,
                            'sub_cat_seq'    => $firstRow->sub_cat_seq,
                            'show_on_report' => $firstRow->show_on_report,
                            'acc_par'        => $firstRow->acc_par,
                            'acc_num'        => $firstRow->acc_num,
                            'ac_name'        => $firstRow->ac_name,
                            'ac_type'        => $acType,
                            'year_id'        => $targetYear,
                            'period'         => $p['period'],
                            'debit'          => round($debit, 2),
                            'credit'         => round($credit, 2),
                            'perubahan'      => round($perubahan_net, 2),
                            'saldo_awal'     => round($saldo_awal_net, 2),
                            'saldo_akhir'    => round($saldo_akhir_net, 2),
                        ];
                    }
                }

                return $this->sendResponse($processed, 'Successfully generated mapping report data per period from view');
            } 
            
            else {
                $processed = [];
                
                $startYear  = $periods[0]['year'];
                $startMonth = $periods[0]['month'];
                
                $endPeriodStruct = end($periods);
                $endYear         = $endPeriodStruct['year'];
                $endMonth        = $endPeriodStruct['month'];

                $isCrossYear = ($startYear !== $endYear);

                foreach ($groupedByAccount as $accNum => $rows) {
                    $firstRow      = $rows->first();
                    $acType        = strtoupper(trim($firstRow->ac_type ?? ''));
                    $isNeraca      = in_array($acType, ['AS', 'LI', 'EQ']);
                    $isNormalDebit = in_array($acType, ['AS', 'CS', 'EX']);

                    $saldo_awal_dr = 0;
                    $saldo_awal_cr = 0;
                    $total_debit   = 0;
                    $total_credit  = 0;

                    // Group data per tahun
                    $rowsByYear = $rows->groupBy(function($item) {
                        return $item->year_id !== null ? (int)$item->year_id : null;
                    });

                    // --- 1. HITUNG SALDO AWAL (Sebelum start_period) ---
                    if (isset($rowsByYear[$startYear])) {
                        $startYearRows = $rowsByYear[$startYear];
                        $firstYearRow  = $startYearRows->first();

                        if ($isNeraca) {
                            // Ambil amt_begin tahun start HANYA 1x dari record pertama
                            $saldo_awal_dr += (float)($firstYearRow->amt_begin_dr ?? 0);
                            $saldo_awal_cr += (float)($firstYearRow->amt_begin_cr ?? 0);

                            // Tambahkan mutasi bulan 1 s/d (startMonth - 1) pada tahun start
                            if ($startMonth > 1) {
                                foreach ($startYearRows as $r) {
                                    for ($m = 1; $m < $startMonth; $m++) {
                                        $saldo_awal_dr += (float)($r->{"dr_" . sprintf('%02d', $m)} ?? 0);
                                        $saldo_awal_cr += (float)($r->{"cr_" . sprintf('%02d', $m)} ?? 0);
                                    }
                                }
                            }
                        } else {
                            // Laba Rugi: Tambahkan mutasi bulan 1 s/d (startMonth - 1) di tahun yang sama
                            if ($startMonth > 1) {
                                foreach ($startYearRows as $r) {
                                    for ($m = 1; $m < $startMonth; $m++) {
                                        $saldo_awal_dr += (float)($r->{"dr_" . sprintf('%02d', $m)} ?? 0);
                                        $saldo_awal_cr += (float)($r->{"cr_" . sprintf('%02d', $m)} ?? 0);
                                    }
                                }
                            }
                        }
                    }

                    // --- 2. HITUNG MUTASI SELAMA RENTANG PERIODE ---
                    foreach ($periods as $p) {
                        $currYear  = $p['year'];
                        $currMonth = $p['month'];

                        if (isset($rowsByYear[$currYear])) {
                            foreach ($rowsByYear[$currYear] as $row) {
                                $total_debit  += (float)($row->{"dr_" . sprintf('%02d', $currMonth)} ?? 0);
                                $total_credit += (float)($row->{"cr_" . sprintf('%02d', $currMonth)} ?? 0);
                            }
                        }
                    }

                    // --- 3. KALKULASI NET VALUES ---
                    $saldo_awal_net = $isNormalDebit 
                        ? ($saldo_awal_dr - $saldo_awal_cr) 
                        : ($saldo_awal_cr - $saldo_awal_dr);

                    $perubahan_net = $isNormalDebit 
                        ? ($total_debit - $total_credit) 
                        : ($total_credit - $total_debit);

                    if ($isNeraca) {
                        $saldo_akhir_net = $saldo_awal_net + $perubahan_net;
                    } else {
                        if ($isCrossYear) {
                            $mutasi_end_year_dr = 0;
                            $mutasi_end_year_cr = 0;

                            if (isset($rowsByYear[$endYear])) {
                                $firstEndYearRow = $rowsByYear[$endYear]->first();
                                $mutasi_end_year_dr += (float)($firstEndYearRow->amt_begin_dr ?? 0);
                                $mutasi_end_year_cr += (float)($firstEndYearRow->amt_begin_cr ?? 0);

                                foreach ($rowsByYear[$endYear] as $row) {
                                    for ($m = 1; $m <= $endMonth; $m++) {
                                        $mutasi_end_year_dr += (float)($row->{"dr_" . sprintf('%02d', $m)} ?? 0);
                                        $mutasi_end_year_cr += (float)($row->{"cr_" . sprintf('%02d', $m)} ?? 0);
                                    }
                                }
                            }

                            $saldo_akhir_net = $isNormalDebit 
                                ? ($mutasi_end_year_dr - $mutasi_end_year_cr) 
                                : ($mutasi_end_year_cr - $mutasi_end_year_dr);
                        } else {
                            $saldo_akhir_net = $saldo_awal_net + $perubahan_net;
                        }
                    }

                    $processed[] = [
                        'rep_id'         => $firstRow->rep_id,
                        'cat'            => $firstRow->cat,
                        'cat_seq'        => $firstRow->cat_seq,
                        'sub_cat'        => $firstRow->sub_cat,
                        'sub_cat_seq'    => $firstRow->sub_cat_seq,
                        'show_on_report' => $firstRow->show_on_report,
                        'acc_par'        => $firstRow->acc_par,
                        'acc_num'        => $firstRow->acc_num,
                        'ac_name'        => $firstRow->ac_name,
                        'ac_type'        => $acType,
                        'debit'          => round($total_debit, 2),
                        'credit'         => round($total_credit, 2),
                        'perubahan'      => round($perubahan_net, 2),
                        'saldo_awal'     => round($saldo_awal_net, 2),
                        'saldo_akhir'    => round($saldo_akhir_net, 2),
                    ];
                }

                return $this->sendResponse($processed, 'Successfully generated mapping report data from view');
            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Internal Error', 500);
        }
    }
}