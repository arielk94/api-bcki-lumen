<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class COAMappingController extends Controller
{
    public function getDistinctRep(Request $request)
    {
        try {
            $data = DB::connection('sqlSrvFPI')
                ->table('g_ledger_map')
                ->selectRaw("DISTINCT LTRIM(RTRIM(rep_id)) as rep_id")
                ->whereNotNull('rep_id')
                ->where('rep_id', '<>', '')
                ->orderBy('rep_id', 'ASC')
                ->pluck('rep_id');

            return $this->sendResponse($data, 'Successfully retrieved distinct Report IDs');
        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }

    public function getReport(Request $request)
    {
        try {
            $rep_id      = trim($request->input('rep_id', ''));
            $start_period = trim($request->input('start_period', '')); // format: YYYYMM e.g. 202601
            $end_period   = trim($request->input('end_period', ''));   // format: YYYYMM e.g. 202606

            if (empty($rep_id)) {
                return $this->sendError('Validation Error', 'Report ID (rep_id) is required', 400);
            }
            if (empty($start_period) || empty($end_period)) {
                return $this->sendError('Validation Error', 'Start Period and End Period are required', 400);
            }

            $year_id     = substr($start_period, 0, 4);
            $start_month = (int) substr($start_period, 4, 2);
            $end_month   = (int) substr($end_period, 4, 2);

            if ($start_month < 1 || $start_month > 12 || $end_month < 1 || $end_month > 12) {
                return $this->sendError('Validation Error', 'Invalid month range in period', 400);
            }

            // Build Saldo Awal expressions
            $dr_awal_cols = ['ISNULL(gy.amt_begin_dr, 0)'];
            $cr_awal_cols = ['ISNULL(gy.amt_begin_cr, 0)'];
            for ($i = 1; $i < $start_month; $i++) {
                $m = str_pad($i, 2, '0', STR_PAD_LEFT);
                $dr_awal_cols[] = "ISNULL(gy.dr_{$m}, 0)";
                $cr_awal_cols[] = "ISNULL(gy.cr_{$m}, 0)";
            }
            $dr_awal_expr = implode(' + ', $dr_awal_cols);
            $cr_awal_expr = implode(' + ', $cr_awal_cols);

            // Build Period (movement) expressions
            $dr_period_cols = [];
            $cr_period_cols = [];
            for ($i = $start_month; $i <= $end_month; $i++) {
                $m = str_pad($i, 2, '0', STR_PAD_LEFT);
                $dr_period_cols[] = "ISNULL(gy.dr_{$m}, 0)";
                $cr_period_cols[] = "ISNULL(gy.cr_{$m}, 0)";
            }
            $dr_period_expr = implode(' + ', $dr_period_cols);
            $cr_period_expr = implode(' + ', $cr_period_cols);

            // Saldo Akhir
            $dr_akhir_expr = "({$dr_awal_expr}) + ({$dr_period_expr})";
            $cr_akhir_expr = "({$cr_awal_expr}) + ({$cr_period_expr})";

            $sql = "
                SELECT
                    gm.rep_id,
                    gm.cat,
                    gm.cat_seq,
                    gm.sub_cat,
                    gy.year_id,
                    CAST(SUM({$dr_awal_expr}) AS DECIMAL(18,2)) AS saldo_awal_dr,
                    CAST(SUM({$cr_awal_expr}) AS DECIMAL(18,2)) AS saldo_awal_cr,
                    CAST(SUM({$dr_period_expr}) AS DECIMAL(18,2)) AS debit,
                    CAST(SUM({$cr_period_expr}) AS DECIMAL(18,2)) AS credit,
                    CAST(SUM({$dr_akhir_expr}) AS DECIMAL(18,2)) AS saldo_akhir_dr,
                    CAST(SUM({$cr_akhir_expr}) AS DECIMAL(18,2)) AS saldo_akhir_cr
                FROM g_ledger_map gm
                JOIN g_ledger gl ON gl.ac_num LIKE CONCAT(LTRIM(RTRIM(gm.acc_num)), '%')
                JOIN g_ledger_year gy ON gy.ac_num = gl.ac_num
                WHERE LTRIM(RTRIM(gm.rep_id)) = ?
                  AND gy.year_id = ?
                GROUP BY gm.rep_id, gm.cat, gm.cat_seq, gm.sub_cat, gy.year_id
                ORDER BY gm.cat_seq ASC, gm.sub_cat ASC
            ";

            $data = DB::connection('sqlSrvFPI')->select($sql, [$rep_id, $year_id]);

            return $this->sendResponse($data, 'Successfully generated COA mapping report');
        } catch (\Exception $e) {
            return $this->sendError('Internal Error', $e->getMessage(), 500);
        }
    }
}
