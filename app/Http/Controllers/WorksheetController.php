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

class WorksheetController extends Controller
{
    // public function getData(Request $request)
    // {
    //     $startPeriodRaw = str_replace('-', '', $request->start_period);
    //     $endPeriodRaw   = str_replace('-', '', $request->end_period);
    //     $ac_type        = $request->ac_type;
    //     $ac_num         = $request->ac_num;

    //     if (strtoupper(trim($ac_type)) === 'IN') {
    //         $ac_type = 'IC';
    //     }

    //     $startYear  = (int) substr($startPeriodRaw, 0, 4);
    //     $endYear    = (int) substr($endPeriodRaw, 0, 4);
    //     $startMonth = (int) substr($startPeriodRaw, 4, 2);
    //     $endMonth   = (int) substr($endPeriodRaw, 4, 2);

    //     // Formulasi Mutasi 12 Bulan Penuh untuk Tahun-Tahun Sebelumnya (year_id < $year)
    //     $dr_full_cols = ['ISNULL(amt_begin_dr, 0)'];
    //     $cr_full_cols = ['ISNULL(amt_begin_cr, 0)'];
    //     for ($i = 1; $i <= 12; $i++) {
    //         $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
    //         $dr_full_cols[] = "ISNULL(dr_{$pad}, 0)";
    //         $cr_full_cols[] = "ISNULL(cr_{$pad}, 0)";
    //     }
    //     $dr_full_expr = implode(' + ', $dr_full_cols);
    //     $cr_full_expr = implode(' + ', $cr_full_cols);

    //     $results = [];

    //     for ($year = $startYear; $year <= $endYear; $year++) {
    //         $mStart = ($year == $startYear) ? $startMonth : 1;
    //         $mEnd   = ($year == $endYear) ? $endMonth : 12;

    //         for ($m = $mStart; $m <= $mEnd; $m++) {
    //             $monthStr  = str_pad($m, 2, '0', STR_PAD_LEFT);
    //             $periodKey = "{$year}{$monthStr}";

    //             $prevMonth = $m - 1;

    //             // Formulasi Mutasi Bulan 1 s/d (m-1) pada Tahun Berjalan ($year) untuk Neraca
    //             $dr_curr_cols = ['ISNULL(amt_begin_dr, 0)'];
    //             $cr_curr_cols = ['ISNULL(amt_begin_cr, 0)'];
    //             for ($i = 1; $i <= $prevMonth; $i++) {
    //                 $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
    //                 $dr_curr_cols[] = "ISNULL(dr_{$pad}, 0)";
    //                 $cr_curr_cols[] = "ISNULL(cr_{$pad}, 0)";
    //             }
    //             $dr_curr_expr = implode(' + ', $dr_curr_cols);
    //             $cr_curr_expr = implode(' + ', $cr_curr_cols);

    //             // Formulasi Mutasi Laba Rugi (Tanpa amt_begin) - [DI-FIX NAMANYA]
    //             $lr_dr_cols = [];
    //             $lr_cr_cols = [];
    //             for ($i = 1; $i <= $prevMonth; $i++) {
    //                 $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
    //                 $lr_dr_cols[] = "ISNULL(dr_{$pad}, 0)";
    //                 $lr_cr_cols[] = "ISNULL(cr_{$pad}, 0)";
    //             }
    //             $lr_dr_expr = count($lr_dr_cols) > 0 ? implode(' + ', $lr_dr_cols) : '0';
    //             $lr_cr_expr = count($lr_cr_cols) > 0 ? implode(' + ', $lr_cr_cols) : '0';

    //             /*
    //             * FORMULA SALDO AWAL MANDIRI FILTER:
    //             * - Jika year_id < $year  : Hitung Full (amt_begin + 12 bulan mutasi) -> Mengakomodasi histori tahun lalu
    //             * - Jika year_id == $year : Hitung amt_begin + mutasi 1..(m-1)
    //             */
    //             $saldo_awal_expr = "CASE 
    //                 -- Neraca Debit (AS)
    //                 WHEN UPPER(RTRIM(ac_type)) = 'AS' THEN 
    //                     CASE 
    //                         WHEN CAST(year_id AS INT) < {$year} THEN ({$dr_full_expr}) - ({$cr_full_expr})
    //                         WHEN CAST(year_id AS INT) = {$year} THEN ({$dr_curr_expr}) - ({$cr_curr_expr})
    //                         ELSE 0 
    //                     END

    //                 -- Neraca Kredit (LI, EQ)
    //                 WHEN UPPER(RTRIM(ac_type)) IN ('LI', 'EQ') THEN 
    //                     CASE 
    //                         WHEN CAST(year_id AS INT) < {$year} THEN ({$cr_full_expr}) - ({$dr_full_expr})
    //                         WHEN CAST(year_id AS INT) = {$year} THEN ({$cr_curr_expr}) - ({$dr_curr_expr})
    //                         ELSE 0 
    //                     END

    //                 -- Laba Rugi Debit (CS, EX)
    //                 WHEN UPPER(RTRIM(ac_type)) IN ('CS', 'EX') THEN 
    //                     CASE 
    //                         WHEN CAST(year_id AS INT) = {$year} THEN (ISNULL(amt_begin_dr, 0) - ISNULL(amt_begin_cr, 0)) + ({$lr_dr_expr}) - ({$lr_cr_expr})
    //                         ELSE 0 
    //                     END

    //                 -- Laba Rugi Kredit (IC, IN, OT)
    //                 WHEN UPPER(RTRIM(ac_type)) IN ('IC', 'IN', 'OT') THEN 
    //                     CASE 
    //                         WHEN CAST(year_id AS INT) = {$year} THEN (ISNULL(amt_begin_cr, 0) - ISNULL(amt_begin_dr, 0)) + ({$lr_cr_expr}) - ({$lr_dr_expr})
    //                         ELSE 0 
    //                     END

    //                 ELSE 0
    //             END";

    //             // Ambil semua data histori (year_id <= $year) agar nilai aset/hutang/modal selalu terbawa dari awal
    //             $periodData = DB::connection('sqlSrvFPI')
    //                 ->table('v_g_ledger_year')
    //                 ->select([
    //                     DB::raw("CASE WHEN UPPER(RTRIM(ac_type)) = 'IN' THEN 'IC' ELSE ac_type END AS ac_type"),
    //                     DB::raw("TRIM(ac_num) AS ac_num"),
    //                     DB::raw("TRIM(ac_name) AS ac_name"),
    //                     DB::raw("SUM({$saldo_awal_expr}) AS saldo_awal"),
    //                     DB::raw("SUM(CASE WHEN CAST(year_id AS INT) = {$year} THEN ISNULL(dr_{$monthStr}, 0) ELSE 0 END) AS debit"),
    //                     DB::raw("SUM(CASE WHEN CAST(year_id AS INT) = {$year} THEN ISNULL(cr_{$monthStr}, 0) ELSE 0 END) AS credit"),
    //                 ])
    //                 ->where('year_id', '<=', $year)
    //                 ->when($ac_type, fn($q) => $q->whereIn('ac_type', $ac_type === 'IC' ? ['IC', 'IN'] : [$ac_type]))
    //                 ->when($ac_num, fn($q) => $q->where('ac_num', $ac_num))
    //                 ->groupBy(DB::raw("CASE WHEN UPPER(RTRIM(ac_type)) = 'IN' THEN 'IC' ELSE ac_type END"), 'ac_num', 'ac_name')
    //                 ->get();

    //             $mappedData = $periodData->map(function ($row) {
    //                 $saldoAwal = (float) $row->saldo_awal;
    //                 $debit     = (float) $row->debit;
    //                 $credit    = (float) $row->credit;
    //                 $acType    = strtoupper(trim($row->ac_type));

    //                 if (in_array($acType, ['AS', 'CS', 'EX'])) {
    //                     $perubahan = $debit - $credit;
    //                 } else {
    //                     $perubahan = $credit - $debit;
    //                 }

    //                 $saldoAkhir = $saldoAwal + $perubahan;

    //                 return [
    //                     'ac_type'     => $row->ac_type,
    //                     'ac_num'      => $row->ac_num,
    //                     'ac_name'     => $row->ac_name,
    //                     'saldo_awal'  => $saldoAwal,
    //                     'debit'       => $debit,
    //                     'credit'      => $credit,
    //                     'perubahan'   => $perubahan,
    //                     'saldo_akhir' => $saldoAkhir,
    //                 ];
    //             });

    //             $results[$periodKey] = $mappedData->toArray();
    //         }
    //     }

    //     return $this->sendResponse($results, 'Data saldo periode berhasil diambil.');
    // }
    public function getData(Request $request)
    {
        $startPeriodRaw = str_replace('-', '', $request->start_period);
        $endPeriodRaw   = str_replace('-', '', $request->end_period);
        $ac_type        = $request->ac_type;
        $ac_num         = $request->ac_num;

        if (strtoupper(trim($ac_type)) === 'IN') {
            $ac_type = 'IC';
        }

        $startYear  = (int) substr($startPeriodRaw, 0, 4);
        $endYear    = (int) substr($endPeriodRaw, 0, 4);
        $startMonth = (int) substr($startPeriodRaw, 4, 2);
        $endMonth   = (int) substr($endPeriodRaw, 4, 2);

        $results = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $mStart = ($year == $startYear) ? $startMonth : 1;
            $mEnd   = ($year == $endYear) ? $endMonth : 12;

            for ($m = $mStart; $m <= $mEnd; $m++) {
                $monthStr  = str_pad($m, 2, '0', STR_PAD_LEFT);
                $periodKey = "{$year}{$monthStr}";

                // -------------------------------------------------------------
                // 1. Mutasi Penuh 12 Bulan untuk Tahun di Antara ($startYear < year_id < $year)
                // -------------------------------------------------------------
                $dr_full_cols = ['ISNULL(amt_begin_dr, 0)'];
                $cr_full_cols = ['ISNULL(amt_begin_cr, 0)'];
                for ($i = 1; $i <= 12; $i++) {
                    $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $dr_full_cols[] = "ISNULL(dr_{$pad}, 0)";
                    $cr_full_cols[] = "ISNULL(cr_{$pad}, 0)";
                }
                $dr_full_expr = implode(' + ', $dr_full_cols);
                $cr_full_expr = implode(' + ', $cr_full_cols);

                // -------------------------------------------------------------
                // 2. Mutasi Tahun Start ($startYear)
                // Jika $year == $startYear, hitung mutasi s/d bulan (m-1)
                // Jika $year > $startYear, hitung mutasi full 12 bulan untuk $startYear
                // -------------------------------------------------------------
                $limitMStart = ($year == $startYear) ? ($m - 1) : 12;
                $dr_start_year_cols = ['ISNULL(amt_begin_dr, 0)'];
                $cr_start_year_cols = ['ISNULL(amt_begin_cr, 0)'];
                for ($i = 1; $i <= $limitMStart; $i++) {
                    $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $dr_start_year_cols[] = "ISNULL(dr_{$pad}, 0)";
                    $cr_start_year_cols[] = "ISNULL(cr_{$pad}, 0)";
                }
                $dr_start_year_expr = implode(' + ', $dr_start_year_cols);
                $cr_start_year_expr = implode(' + ', $cr_start_year_cols);

                // -------------------------------------------------------------
                // 3. Mutasi Tahun Berjalan ($year) jika $year > $startYear
                // -------------------------------------------------------------
                // $dr_curr_year_cols = ['ISNULL(amt_begin_dr, 0)'];
                // $cr_curr_year_cols = ['ISNULL(amt_begin_cr, 0)'];
                $dr_curr_year_cols = [];
                $cr_curr_year_cols = [];
                for ($i = 1; $i <= ($m - 1); $i++) {
                    $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $dr_curr_year_cols[] = "ISNULL(dr_{$pad}, 0)";
                    $cr_curr_year_cols[] = "ISNULL(cr_{$pad}, 0)";
                }
                // $dr_curr_year_expr = implode(' + ', $dr_curr_year_cols);
                // $cr_curr_year_expr = implode(' + ', $cr_curr_year_cols);
                $dr_curr_year_expr = count($dr_curr_year_cols) > 0 ? implode(' + ', $dr_curr_year_cols) : '0';
                $cr_curr_year_expr = count($cr_curr_year_cols) > 0 ? implode(' + ', $cr_curr_year_cols) : '0';
                // -------------------------------------------------------------
                // 4. Mutasi Laba Rugi (Tanpa amt_begin)
                // -------------------------------------------------------------
                $lr_dr_cols = [];
                $lr_cr_cols = [];
                for ($i = 1; $i <= ($m - 1); $i++) {
                    $pad = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $lr_dr_cols[] = "ISNULL(dr_{$pad}, 0)";
                    $lr_cr_cols[] = "ISNULL(cr_{$pad}, 0)";
                }
                $lr_dr_expr = count($lr_dr_cols) > 0 ? implode(' + ', $lr_dr_cols) : '0';
                $lr_cr_expr = count($lr_cr_cols) > 0 ? implode(' + ', $lr_cr_cols) : '0';

                // -------------------------------------------------------------
                // 5. Case Expression Saldo Awal Murni Baseline $startYear
                // -------------------------------------------------------------
                $saldo_awal_expr = "CASE 
                    -- Neraca Debit (AS)
                    WHEN UPPER(RTRIM(ac_type)) = 'AS' THEN 
                        CASE 
                            WHEN CAST(year_id AS INT) = {$startYear} THEN ({$dr_start_year_expr}) - ({$cr_start_year_expr})
                            WHEN CAST(year_id AS INT) > {$startYear} AND CAST(year_id AS INT) < {$year} THEN ({$dr_full_expr}) - ({$cr_full_expr})
                            WHEN CAST(year_id AS INT) = {$year} AND {$year} > {$startYear} THEN ({$dr_curr_year_expr}) - ({$cr_curr_year_expr})
                            ELSE 0 
                        END

                    -- Neraca Kredit (LI, EQ)
                    WHEN UPPER(RTRIM(ac_type)) IN ('LI', 'EQ') THEN 
                        CASE 
                            WHEN CAST(year_id AS INT) = {$startYear} THEN ({$cr_start_year_expr}) - ({$dr_start_year_expr})
                            WHEN CAST(year_id AS INT) > {$startYear} AND CAST(year_id AS INT) < {$year} THEN ({$cr_full_expr}) - ({$dr_full_expr})
                            WHEN CAST(year_id AS INT) = {$year} AND {$year} > {$startYear} THEN ({$cr_curr_year_expr}) - ({$dr_curr_year_expr})
                            ELSE 0 
                        END

                    -- Laba Rugi Debit (CS, EX)
                    WHEN UPPER(RTRIM(ac_type)) IN ('CS', 'EX') THEN 
                        CASE 
                            WHEN CAST(year_id AS INT) = {$year} THEN (ISNULL(amt_begin_dr, 0) - ISNULL(amt_begin_cr, 0)) + ({$lr_dr_expr}) - ({$lr_cr_expr})
                            ELSE 0 
                        END

                    -- Laba Rugi Kredit (IC, IN, OT)
                    WHEN UPPER(RTRIM(ac_type)) IN ('IC', 'IN', 'OT') THEN 
                        CASE 
                            WHEN CAST(year_id AS INT) = {$year} THEN (ISNULL(amt_begin_cr, 0) - ISNULL(amt_begin_dr, 0)) + ({$lr_cr_expr}) - ({$lr_dr_expr})
                            ELSE 0 
                        END

                    ELSE 0
                END";

                $periodData = DB::connection('sqlSrvFPI')
                    ->table('v_g_ledger_year')
                    ->select([
                        DB::raw("CASE WHEN UPPER(RTRIM(ac_type)) = 'IN' THEN 'IC' ELSE ac_type END AS ac_type"),
                        DB::raw("TRIM(ac_num) AS ac_num"),
                        DB::raw("TRIM(ac_name) AS ac_name"),
                        DB::raw("SUM({$saldo_awal_expr}) AS saldo_awal"),
                        DB::raw("SUM(CASE WHEN CAST(year_id AS INT) = {$year} THEN ISNULL(dr_{$monthStr}, 0) ELSE 0 END) AS debit"),
                        DB::raw("SUM(CASE WHEN CAST(year_id AS INT) = {$year} THEN ISNULL(cr_{$monthStr}, 0) ELSE 0 END) AS credit"),
                    ])
                    // MEMBATASI QUERY: Hanya tarik data dari $startYear s/d $year
                    ->where('year_id', '>=', $startYear)
                    ->where('year_id', '<=', $year)
                    ->when($ac_type, fn($q) => $q->whereIn('ac_type', $ac_type === 'IC' ? ['IC', 'IN'] : [$ac_type]))
                    ->when($ac_num, fn($q) => $q->where('ac_num', $ac_num))
                    ->groupBy(DB::raw("CASE WHEN UPPER(RTRIM(ac_type)) = 'IN' THEN 'IC' ELSE ac_type END"), 'ac_num', 'ac_name')
                    ->get();

                $mappedData = $periodData->map(function ($row) {
                    $saldoAwal = (float) $row->saldo_awal;
                    $debit     = (float) $row->debit;
                    $credit    = (float) $row->credit;
                    $acType    = strtoupper(trim($row->ac_type));

                    if (in_array($acType, ['AS', 'CS', 'EX'])) {
                        $perubahan = $debit - $credit;
                    } else {
                        $perubahan = $credit - $debit;
                    }

                    $saldoAkhir = $saldoAwal + $perubahan;

                    return [
                        'ac_type'     => $row->ac_type,
                        'ac_num'      => $row->ac_num,
                        'ac_name'     => $row->ac_name,
                        'saldo_awal'  => $saldoAwal,
                        'debit'       => $debit,
                        'credit'      => $credit,
                        'perubahan'   => $perubahan,
                        'saldo_akhir' => $saldoAkhir,
                    ];
                });

                $results[$periodKey] = $mappedData->toArray();
            }
        }

        return $this->sendResponse($results, 'Data saldo periode berhasil diambil.');
    }
}
