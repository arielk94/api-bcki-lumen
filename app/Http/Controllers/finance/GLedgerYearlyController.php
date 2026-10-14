<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GLedgerYearlyController extends Controller
{
    public function getGenLedgerDetail(Request $request){
        // --- 1. Definisikan Variabel (Sesuaikan dengan data input Anda) ---
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $AcNum = trim($request->input('ac_num')); 
        $AcType = trim($request->input('ac_type'));
        
        // Parameter Filter Tambahan
        $OfficeId = trim($request->input('office_id'));
        $CustomerId = trim($request->input('customer_id'));
        $VendorId = trim($request->input('vendor_id'));
        $Source = trim($request->input('source'));

        // Tentukan OfficeId Default jika tidak diinput (berdasarkan kode VBA awal: office_id = 1)
        $DefaultOfficeId = 1;
        $OfficeId = !empty($OfficeId) ? $OfficeId : $DefaultOfficeId;
        
        // Hitung variabel tanggal & bulan
        $YearID = date('Y', strtotime($startDate));
        $bulan = date('n', strtotime($startDate)); // Month number (1-12)
        
        // Format tanggal ke 'yymmdd' untuk SQL Server
        $startDateFormatted = date('ymd', strtotime($startDate));
        $endDateFormatted = date('ymd', strtotime($endDate));
        
        // Variabel untuk perhitungan saldo awal bulanan
        $startDateBOMFormatted = date('ymd', strtotime(date('Y-m-01', strtotime($startDate))));
        $startDatePrevDayFormatted = date('ymd', strtotime('-1 day', strtotime($startDate)));


        // --- 2. Query untuk Mendapatkan Saldo Awal Dasar dan Data Akun ---
        // Ini sama dengan query pertama Anda (res.Open sSQL, gcnDB1...)
        $sqlWhereCoaData = "";
        $bindingsCoaData = [$YearID];

        // Tambahkan filter AcNum/AcType secara kondisional
        if (!empty($AcNum)) {
            $sqlWhereCoaData .= " AND a.ac_num = ?";
            $bindingsCoaData[] = $AcNum;
        } elseif (!empty($AcType)) {
            $sqlWhereCoaData .= " AND a.ac_type = ?";
            $bindingsCoaData[] = $AcType;
        }
        
        // Eksekusi Query 1
        $coaData = DB::connection('sqlSrvFPI')
            ->select("
                SELECT
                    a.ac_num, a.drcr, a.ac_name, a.curr_id, b.*
                FROM
                    g_ledger a
                JOIN
                    g_ledger_year b
                    ON a.office_id = b.office_id AND a.ac_num = b.ac_num
                WHERE
                    a.office_id = 1 AND b.year_id = ?
                    {$sqlWhereCoaData}
                ORDER BY a.ac_num
            ", $bindingsCoaData);

        // --- 3. Proses dan Hitung Saldo Awal di PHP/Laravel ---
        $openingBalances = [];
        foreach ($coaData as $coa) {
            $AmtBalDr = $coa->amt_begin_dr;
            $AmtBalCr = $coa->amt_begin_cr;

            // Tambahkan DR/CR dari bulan 1 sampai bulan-1
            for ($i = 1; $i < $bulan; $i++) {
                $monthStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                $AmtBalDr += $coa->{"dr_$monthStr"} ?? 0;
                $AmtBalCr += $coa->{"cr_$monthStr"} ?? 0;
            }

            // Tambahkan transaksi harian dari awal bulan hingga startDate-1
            if (strtotime($startDate) != strtotime(date('Y-m-01', strtotime($startDate)))) {
                $prevMonthTrans = DB::connection('sqlSrvFPI')
                ->select("
                    SELECT
                        SUM(ISNULL(amt_dr, 0)) as amt_dr, SUM(ISNULL(amt_cr, 0)) as amt_cr
                    FROM
                        journal_voucher
                    WHERE
                        office_id = ? AND ac_num = ?
                        AND tr_date BETWEEN ? AND ?
                ", [$OfficeId, $coa->ac_num, $startDateBOMFormatted, $startDatePrevDayFormatted]);
                
                if (!empty($prevMonthTrans)) {
                    $AmtBalDr += $prevMonthTrans[0]->amt_dr ?? 0;
                    $AmtBalCr += $prevMonthTrans[0]->amt_cr ?? 0;
                }
            }

            $openingBalances[$coa->ac_num] = [
                'balance' => $AmtBalDr - $AmtBalCr, // Saldo Awal Net
                'drcr' => $coa->drcr,
                'ac_name' => $coa->ac_name,
                'curr_id' => $coa->curr_id,
            ];
        }

        // --- 4. Query Gabungan Saldo Awal + Detail dengan Saldo Berjalan (Final) ---

        $results = [];

        // Ulangi untuk setiap COA yang sudah dihitung Saldo Awalnya
        foreach ($openingBalances as $ac_num => $data) {
            $currentOpeningBalance = round($data['balance'], 4);
            $accountDrCr = $data['drcr'];

            // 4a. Tambahkan filter ke query detail transaksi
            $sqlWhereDetail = "";
            $bindingsDetail = [];

            if (!empty($CustomerId)) {
                $sqlWhereDetail .= " AND a.cust_id = ?";
                $bindingsDetail[] = $CustomerId;
            }
            if (!empty($VendorId)) {
                $sqlWhereDetail .= " AND a.vendor_id = ?";
                $bindingsDetail[] = $VendorId;
            }
            if (!empty($Source)) {
                $sqlWhereDetail .= " AND a.source = ?";
                $bindingsDetail[] = $Source;
            }

            // Query Detail Transaksi menggunakan Window Function
            $detailQuery = "
                SELECT
                    a.office_id,
                    a.tr_date,
                    a.source,
                    a.source_num,
                    a.ac_num,
                    a.ac_name,
                    a.remark,
                    ISNULL(a.amt_dr, 0) AS amt_dr,
                    ISNULL(a.amt_cr, 0) AS amt_cr,
                    ISNULL(a.vendor_name, '') AS vendor_name,
                    ISNULL(a.customer, '') AS customer_name,
                    ? + SUM(ISNULL(a.amt_dr, 0) - ISNULL(a.amt_cr, 0)) OVER (
                        ORDER BY a.tr_date, a.source, a.source_num
                        ROWS UNBOUNDED PRECEDING
                    ) AS net_balance,
                    ? AS drcr_type
                FROM
                    v_general_ledger_detail a
                WHERE
                    a.office_id = ?
                    AND a.tr_date BETWEEN ? AND ?
                    AND a.ac_num = ?
                    {$sqlWhereDetail}
                ORDER BY a.tr_date, a.source, a.source_num
            ";
            
            // Urutan binding untuk detailQuery
            $bindings = array_merge(
                [
                    $currentOpeningBalance,
                    $accountDrCr,
                    $OfficeId,
                    $startDateFormatted,
                    $endDateFormatted,
                    $ac_num
                ],
                $bindingsDetail // Tambahkan binding kondisional di akhir
            );

            
            // Ambil detail transaksi dengan saldo berjalan
            $transactionDetails = DB::connection('sqlSrvFPI')->select($detailQuery, $bindings);

            // --- Pembentukan Hasil Akhir ---

            // 4a. Baris Saldo Awal
            $openingRow = [
                'tr_date' => 'AWAL',
                'Source' => '',
                'source_num' => '',
                'ac_num' => $ac_num,
                'ac_name' => $data['ac_name'],
                'Remark' => 'AWAL:',
                'amt_dr' => number_format(0.00, 2, '.', ','),
                'amt_cr' => number_format(0.00, 2, '.', ','),
                'vendor_name' => '',
                'customer_name' => '',
                // 'curr_id' => $data['curr_id'],
                // 'Rate' => number_format(0.00, 2, '.', ','),
                'BALANCE' => number_format($currentOpeningBalance, 2, '.', ',')
            ];
            
            // $combinedData = [];
            // $combinedData[] = $openingRow; 
            $results[] = $openingRow; 

            // 4b. Baris Detail Transaksi
            foreach ($transactionDetails as $trans) {
                // Gunakan net_balance dari Window Function untuk BALANCE
                $finalBalance = $trans->net_balance;
                
                $results[] = [
                    'tr_date' => date('d-M-Y', strtotime($trans->tr_date)),
                    'source' => $trans->source,
                    'source_num' => $trans->source_num,
                    'ac_num' => $trans->ac_num,
                    'ac_name' => $trans->ac_name,
                    'remark' => $trans->remark,
                    'amt_dr' => number_format($trans->amt_dr, 2, '.', ','),
                    'amt_cr' => number_format($trans->amt_cr, 2, '.', ','),
                    'BALANCE' => number_format($finalBalance, 2, '.', ','),
                    // 'vendor_customer_name' => $trans->vendor_customer_name ?? '',
                    'vendor_name' => $trans->vendor_name ?? '',
                    'customer_name' => $trans->customer_name ?? '',
                    // 'curr_id' => $trans->curr_id,
                    // 'Rate' => number_format($trans->Rate, 2, '.', ','),
                ];
            }
        }

        return $this->sendResponse($results, 'Successfully get Report General Ledger Detail');
    }

    /**
     * Ledger Year Controller
     *
     * Menampilkan data ledger per tahun dengan kalkulasi saldo awal,
     * mutasi, dan saldo akhir — termasuk mata uang asing (foreign).
     */

    public function neracaReport(Request $request)
    {
        // 1. Validasi & siapkan parameter
        $params = $this->resolveParams($request);
        if (!$params['year_id']) {
            return $this->sendError("Fill year id", 'Error', 500);
        }

        // 2. Ambil data ledger dari database
        $datas = $this->fetchLedgerData($params);

        // Ambil data historis untuk perhitungan saldo awal dinamis
        $historicalDatas = $this->fetchHistoricalLedgerData($params);
        $historyByAcNum = [];
        foreach ($historicalDatas as $hRow) {
            $historyByAcNum[$hRow->ac_num][] = $hRow;
        }

        // 3. Hitung cutoff & mutasi per akun (jika ada start_date)
        if ($params['start_date']) {
            $this->applyDateRangeCutoff($datas, $params['start_date'], $params['end_date'], $historyByAcNum);
        } else {
            $this->applyYearlyCutoff($datas, $historyByAcNum);
        }

        // 4. Tentukan mana akun induk dan mana akun daun (leaf)
        $leafMap = $this->buildLeafMap($datas);

        // 5. Rollup nilai dari akun daun ke akun induk
        $this->rollupParentValues($datas, $leafMap);

        // 6. Hitung & format saldo per baris
        $asOfDate = $this->resolveAsOfDate($params['start_date'], $params['year_id']);
        $this->formatRowBalances($datas, $asOfDate, $params);

        // 7. Buat baris total "As Of"
        $asOfRow = $this->buildAsOfRow($datas, $leafMap, $params['year_id'], $asOfDate);
        array_unshift($datas, $asOfRow);

        return $this->sendResponse($datas, 'Successfully get List');
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────

    /**
     * Normalisasi & validasi parameter request.
     */
    private function resolveParams(Request $request): array
    {
        $start_date = $request->start_date;
        $year_id    = $request->year_id;

        // Jika year_id tidak diinput, turunkan dari start_date atau gunakan tahun berjalan saat ini
        if (!$year_id) {
            if ($start_date) {
                $year_id = date('Y', strtotime($start_date));
            } else {
                $year_id = date('Y');
            }
        }

        return [
            'year_id'    => $year_id,
            'start_date' => $start_date,
            'end_date'   => $request->end_date ?: $start_date,
            'ac_type'    => $request->ac_type ?: '',
            'ac_num'     => $request->ac_num  ?: '',
        ];
    }

    /**
     * Ambil data ledger dari tabel g_ledger_year + g_ledger.
     */
    private function fetchLedgerData(array $params): array
    {
        $where = "1=1";

        if ($params['ac_type']) {
            $where .= " AND b.ac_type = '{$params['ac_type']}'";
        }
        if ($params['ac_num']) {
            $where .= " AND b.ac_num = '{$params['ac_num']}'";
        }

        // Buat kolom dr/cr bulanan (01–12) beserta versi foreign-nya
        $monthlyColumns = '';
        for ($m = 1; $m <= 12; $m++) {
            $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthlyColumns .= "
                ISNULL(a.dr_{$mm}, 0) dr_{$mm}, ISNULL(a.cr_{$mm}, 0) cr_{$mm},
                ISNULL(a.dr_{$mm}_frg, 0) dr_{$mm}_frg, ISNULL(a.cr_{$mm}_frg, 0) cr_{$mm}_frg,";
        }
        $monthlyColumns = rtrim($monthlyColumns, ',');

        $sql = "
            SELECT
                COALESCE(LTRIM(RTRIM(a.year_id)), '{$params['year_id']}')   year_id,
                LTRIM(RTRIM(b.ac_num))  ac_num,
                LTRIM(RTRIM(b.ac_name)) ac_name,
                LTRIM(RTRIM(b.drcr))    drcr,
                LTRIM(RTRIM(b.ac_type)) ac_type,
                COALESCE(amt_type, b.drcr) amt_type,
                ISNULL(amt_begin_dr, 0)     amt_begin_dr,
                ISNULL(amt_begin_cr, 0)     amt_begin_cr,
                ISNULL(dr_bal, 0)           dr_bal,
                ISNULL(cr_bal, 0)           cr_bal,
                CASE WHEN b.drcr = 'dr'
                    THEN ISNULL(amt_begin_dr,0) - ISNULL(amt_begin_cr,0) + ISNULL(dr_bal,0) - ISNULL(cr_bal,0)
                    ELSE ISNULL(amt_begin_cr,0) - ISNULL(amt_begin_dr,0) + ISNULL(cr_bal,0) - ISNULL(dr_bal,0)
                END AS amt_balance,
                ISNULL(frg_amt_begin_dr, 0) frg_amt_begin_dr,
                ISNULL(frg_amt_begin_cr, 0) frg_amt_begin_cr,
                ISNULL(dr_bal_frg, 0)       dr_bal_frg,
                ISNULL(cr_bal_frg, 0)       cr_bal_frg,
                CASE WHEN b.drcr = 'dr'
                    THEN ISNULL(frg_amt_begin_dr - frg_amt_begin_cr + dr_bal_frg - cr_bal_frg, 0)
                    ELSE ISNULL(frg_amt_begin_cr - frg_amt_begin_dr + cr_bal_frg - dr_bal_frg, 0)
                END AS amt_balance_frg,
                {$monthlyColumns}
            FROM g_ledger b
            LEFT JOIN g_ledger_year a ON a.ac_num = b.ac_num AND a.year_id = '{$params['year_id']}'
            WHERE {$where} AND (b.active_flag = 'Y' OR a.ac_num IS NOT NULL)
            ORDER BY b.ac_num ASC
        ";

        return DB::connection('sqlSrvFPI')->select(DB::raw($sql));
    }

    /**
     * Ambil data ledger historis dari tabel g_ledger_year + g_ledger.
     */
    private function fetchHistoricalLedgerData(array $params): array
    {
        $where = "1=1 AND a.year_id <= '{$params['year_id']}'";

        if ($params['ac_type']) {
            $where .= " AND b.ac_type = '{$params['ac_type']}'";
        }
        if ($params['ac_num']) {
            $where .= " AND b.ac_num = '{$params['ac_num']}'";
        }

        // Buat kolom dr/cr bulanan (01–12) beserta versi foreign-nya
        $monthlyColumns = '';
        for ($m = 1; $m <= 12; $m++) {
            $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthlyColumns .= "
                ISNULL(a.dr_{$mm}, 0) dr_{$mm}, ISNULL(a.cr_{$mm}, 0) cr_{$mm},
                ISNULL(a.dr_{$mm}_frg, 0) dr_{$mm}_frg, ISNULL(a.cr_{$mm}_frg, 0) cr_{$mm}_frg,";
        }
        $monthlyColumns = rtrim($monthlyColumns, ',');

        $sql = "
            SELECT
                LTRIM(RTRIM(year_id))   year_id,
                LTRIM(RTRIM(a.ac_num))  ac_num,
                ISNULL(amt_begin_dr, 0)     amt_begin_dr,
                ISNULL(amt_begin_cr, 0)     amt_begin_cr,
                ISNULL(frg_amt_begin_dr, 0) frg_amt_begin_dr,
                ISNULL(frg_amt_begin_cr, 0) frg_amt_begin_cr,
                {$monthlyColumns}
            FROM  g_ledger_year a
            INNER JOIN g_ledger b ON a.ac_num = b.ac_num
            WHERE {$where}
            ORDER BY a.ac_num, a.year_id ASC
        ";

        return DB::connection('sqlSrvFPI')->select(DB::raw($sql));
    }

    /**
     * Hitung saldo awal dan mutasi saat start_date diberikan.
     * Membagi periode menjadi:
     *   - jvBefore  : mutasi dari awal bulan sampai sehari sebelum start_date
     *   - jvPeriod  : mutasi dalam rentang start_date s/d end_date
     */
    private function applyDateRangeCutoff(array &$datas, string $start_date, string $end_date, array $historyByAcNum): void
    {
        $bulan        = (int) date('m', strtotime($start_date));
        $office_id    = 1;
        $bom          = date('Y-m-01', strtotime($start_date));   // beginning of month
        $oneDayBefore = date('Y-m-d', strtotime($start_date . ' -1 day'));
        $isSameAsBom  = $bom === date('Y-m-d', strtotime($start_date));

        // Mutasi dari awal bulan hingga sehari sebelum start_date (jika start_date bukan hari pertama)
        $jvBefore = [];
        if (!$isSameAsBom) {
            $jvBefore = $this->getJournalSummary(
                $office_id,
                $bom . ' 00:00:00.000',
                $oneDayBefore . ' 23:59:59.999'
            );
        }

        // Mutasi dalam rentang start_date s/d end_date
        $jvPeriod = $this->getJournalSummary(
            $office_id,
            $start_date . ' 00:00:00.000',
            $end_date   . ' 23:59:59.000'
        );

        foreach ($datas as &$row) {
            $this->computeRowCutoff($row, $bulan, $jvBefore, $jvPeriod, $historyByAcNum);
        }
        unset($row);
    }

    /**
     * Ambil ringkasan jurnal (dr/cr & foreign) dari journal_voucher
     * dalam rentang tanggal tertentu, dikelompokkan per ac_num & rate.
     *
     * @return array ['ac_num' => ['amt_dr' => x, 'amt_cr' => x, 'amt_dr_frg' => x, 'amt_cr_frg' => x]]
     */
    private function getJournalSummary(int $office_id, string $from, string $to): array
    {
        $rows = DB::connection('sqlSrvFPI')
            ->table('journal_voucher')
            ->selectRaw('LTRIM(RTRIM(ac_num)) as ac_num, rate, SUM(amt_dr) as amt_dr, SUM(amt_cr) as amt_cr')
            ->where('office_id', $office_id)
            ->whereBetween('tr_date', [$from, $to])
            ->groupBy('ac_num', 'rate')
            ->get();

        $summary = [];
        foreach ($rows as $row) {
            $ac = $row->ac_num;
            $summary[$ac] ??= ['amt_dr' => 0.0, 'amt_cr' => 0.0, 'amt_dr_frg' => 0.0, 'amt_cr_frg' => 0.0];
            $summary[$ac]['amt_dr'] += $row->amt_dr;
            $summary[$ac]['amt_cr'] += $row->amt_cr;

            if ($row->rate != 0) {
                $summary[$ac]['amt_dr_frg'] += $row->amt_dr / $row->rate;
                $summary[$ac]['amt_cr_frg'] += $row->amt_cr / $row->rate;
            }
        }

        return $summary;
    }

    /**
     * Hitung nilai cutoff (saldo awal kumulatif) dan mutasi periode untuk satu baris.
     */
    private function computeRowCutoff(object &$row, int $bulan, array $jvBefore, array $jvPeriod, array $historyByAcNum): void
    {
        $ac = $row->ac_num;
        $acType = strtoupper($row->ac_type ?? '');
        $isNeraca = in_array($acType, ['AS', 'LI', 'EQ']);
        $requestedYear = $row->year_id;

        $amtDr = 0.0;
        $amtCr = 0.0;
        $amtDrFrg = 0.0;
        $amtCrFrg = 0.0;

        if ($isNeraca && isset($historyByAcNum[$ac]) && count($historyByAcNum[$ac]) > 0) {
            // Menggunakan data tahun terlama sebagai saldo awal dasar
            $earliestRow = $historyByAcNum[$ac][0];
            $amtDr = (float) $earliestRow->amt_begin_dr;
            $amtCr = (float) $earliestRow->amt_begin_cr;
            $amtDrFrg = (float) $earliestRow->frg_amt_begin_dr;
            $amtCrFrg = (float) $earliestRow->frg_amt_begin_cr;

            // Tambahkan semua bulan untuk tahun-tahun sebelum requestedYear
            foreach ($historyByAcNum[$ac] as $histRow) {
                if ($histRow->year_id < $requestedYear) {
                    for ($m = 1; $m <= 12; $m++) {
                        $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
                        $amtDr    += (float) ($histRow->{"dr_{$mm}"}     ?? 0);
                        $amtCr    += (float) ($histRow->{"cr_{$mm}"}     ?? 0);
                        $amtDrFrg += (float) ($histRow->{"dr_{$mm}_frg"} ?? 0);
                        $amtCrFrg += (float) ($histRow->{"cr_{$mm}_frg"} ?? 0);
                    }
                }
            }
        } else {
            // Laba Rugi (IC, IN, CS, EX, OT)
            $amtDr = (float) $row->amt_begin_dr;
            $amtCr = (float) $row->amt_begin_cr;
            $amtDrFrg = (float) $row->frg_amt_begin_dr;
            $amtCrFrg = (float) $row->frg_amt_begin_cr;
        }

        // Tambahkan mutasi bulanan tahun berjalan sebelum $bulan
        for ($i = 1; $i < $bulan; $i++) {
            $mm = str_pad($i, 2, '0', STR_PAD_LEFT);
            $amtDr    += (float) ($row->{"dr_{$mm}"}     ?? 0);
            $amtCr    += (float) ($row->{"cr_{$mm}"}     ?? 0);
            $amtDrFrg += (float) ($row->{"dr_{$mm}_frg"} ?? 0);
            $amtCrFrg += (float) ($row->{"cr_{$mm}_frg"} ?? 0);
        }

        // Tambahkan mutasi dari awal bulan s/d sehari sebelum start_date
        if (isset($jvBefore[$ac])) {
            $amtDr    += $jvBefore[$ac]['amt_dr'];
            $amtCr    += $jvBefore[$ac]['amt_cr'];
            $amtDrFrg += $jvBefore[$ac]['amt_dr_frg'];
            $amtCrFrg += $jvBefore[$ac]['amt_cr_frg'];
        }

        // Mutasi dalam periode
        $row->dr_bal_val     = $jvPeriod[$ac]['amt_dr']     ?? 0.0;
        $row->cr_bal_val     = $jvPeriod[$ac]['amt_cr']     ?? 0.0;
        $row->dr_bal_frg_val = $jvPeriod[$ac]['amt_dr_frg'] ?? 0.0;
        $row->cr_bal_frg_val = $jvPeriod[$ac]['amt_cr_frg'] ?? 0.0;

        // Simpan cutoff
        $row->amt_dr_cutoff     = $amtDr;
        $row->amt_cr_cutoff     = $amtCr;
        $row->frg_amt_dr_cutoff = $amtDrFrg;
        $row->frg_amt_cr_cutoff = $amtCrFrg;

        // Cast begin balance ke float
        $row->amt_begin_dr     = (float) $row->amt_begin_dr;
        $row->amt_begin_cr     = (float) $row->amt_begin_cr;
        $row->frg_amt_begin_dr = (float) $row->frg_amt_begin_dr;
        $row->frg_amt_begin_cr = (float) $row->frg_amt_begin_cr;
    }

    /**
     * Jika tidak ada start_date, gunakan saldo tahunan langsung dari DB.
     */
    private function applyYearlyCutoff(array &$datas, array $historyByAcNum): void
    {
        foreach ($datas as &$row) {
            $ac = $row->ac_num;
            $acType = strtoupper($row->ac_type ?? '');
            $isNeraca = in_array($acType, ['AS', 'LI', 'EQ']);
            $requestedYear = $row->year_id;

            $amtDr = 0.0;
            $amtCr = 0.0;
            $amtDrFrg = 0.0;
            $amtCrFrg = 0.0;

            if ($isNeraca && isset($historyByAcNum[$ac]) && count($historyByAcNum[$ac]) > 0) {
                // Menggunakan data tahun terlama sebagai saldo awal dasar
                $earliestRow = $historyByAcNum[$ac][0];
                $amtDr = (float) $earliestRow->amt_begin_dr;
                $amtCr = (float) $earliestRow->amt_begin_cr;
                $amtDrFrg = (float) $earliestRow->frg_amt_begin_dr;
                $amtCrFrg = (float) $earliestRow->frg_amt_begin_cr;

                // Tambahkan semua bulan untuk tahun-tahun sebelum requestedYear
                foreach ($historyByAcNum[$ac] as $histRow) {
                    if ($histRow->year_id < $requestedYear) {
                        for ($m = 1; $m <= 12; $m++) {
                            $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $amtDr    += (float) ($histRow->{"dr_{$mm}"}     ?? 0);
                            $amtCr    += (float) ($histRow->{"cr_{$mm}"}     ?? 0);
                            $amtDrFrg += (float) ($histRow->{"dr_{$mm}_frg"} ?? 0);
                            $amtCrFrg += (float) ($histRow->{"cr_{$mm}_frg"} ?? 0);
                        }
                    }
                }
            } else {
                // Laba Rugi (IC, IN, CS, EX, OT)
                $amtDr = (float) $row->amt_begin_dr;
                $amtCr = (float) $row->amt_begin_cr;
                $amtDrFrg = (float) $row->frg_amt_begin_dr;
                $amtCrFrg = (float) $row->frg_amt_begin_cr;
            }

            $row->amt_dr_cutoff     = $amtDr;
            $row->amt_cr_cutoff     = $amtCr;
            $row->frg_amt_dr_cutoff = $amtDrFrg;
            $row->frg_amt_cr_cutoff = $amtCrFrg;
            
            $row->dr_bal_val        = (float) $row->dr_bal;
            $row->cr_bal_val        = (float) $row->cr_bal;
            $row->dr_bal_frg_val    = (float) $row->dr_bal_frg;
            $row->cr_bal_frg_val    = (float) $row->cr_bal_frg;

            $row->amt_begin_dr     = (float) $row->amt_begin_dr;
            $row->amt_begin_cr     = (float) $row->amt_begin_cr;
            $row->frg_amt_begin_dr = (float) $row->frg_amt_begin_dr;
            $row->frg_amt_begin_cr = (float) $row->frg_amt_begin_cr;
        }
        unset($row);
    }

    /**
     * Identifikasi akun daun (leaf): akun yang tidak menjadi prefix akun lain.
     *
     * @return array ['ac_num' => bool] — true jika leaf
     */
    private function buildLeafMap(array $datas): array
    {
        $leafMap = array_fill_keys(array_column((array) $datas, 'ac_num'), true);

        foreach ($datas as $parent) {
            foreach ($datas as $child) {
                if ($child->ac_num !== $parent->ac_num
                    && str_starts_with($child->ac_num, $parent->ac_num . '.')) {
                    $leafMap[$parent->ac_num] = false;
                    break;
                }
            }
        }

        return $leafMap;
    }

    /**
     * Akumulasikan nilai leaf ke akun induknya.
     */
    private function rollupParentValues(array &$datas, array $leafMap): void
    {
        $numericFields = [
            'amt_begin_dr', 'amt_begin_cr',
            'frg_amt_begin_dr', 'frg_amt_begin_cr',
            'amt_dr_cutoff', 'amt_cr_cutoff',
            'frg_amt_dr_cutoff', 'frg_amt_cr_cutoff',
            'dr_bal_val', 'cr_bal_val',
            'dr_bal_frg_val', 'cr_bal_frg_val',
        ];

        foreach ($datas as &$parent) {
            if ($leafMap[$parent->ac_num]) {
                continue; // skip akun daun
            }

            // Reset nilai parent sebelum rollup
            foreach ($numericFields as $field) {
                $parent->$field = 0.0;
            }

            // Jumlahkan dari semua leaf turunannya
            foreach ($datas as $leaf) {
                if (!$leafMap[$leaf->ac_num]) {
                    continue;
                }
                if (!str_starts_with($leaf->ac_num, $parent->ac_num . '.')) {
                    continue;
                }

                foreach ($numericFields as $field) {
                    $parent->$field += $leaf->$field;
                }
            }
        }
        unset($parent);
    }

    /**
     * Tentukan tanggal "As Of" berdasarkan start_date atau tahun sebelumnya.
     */
    private function resolveAsOfDate(?string $start_date, string $year_id): string
    {
        return $start_date
            ? date('Y-m-d', strtotime($start_date . ' -1 day'))
            : ($year_id - 1) . '-12-31';
    }

    /**
     * Hitung saldo awal/akhir dan format semua kolom numerik per baris.
     * Hapus kolom bulanan yang tidak diperlukan di output.
     */
    private function formatRowBalances(array &$datas, string $asOfDate, array $params): void
    {
        $isSameYear = true;
        if ($params['start_date'] && $params['end_date']) {
            $startYear = date('Y', strtotime($params['start_date']));
            $endYear = date('Y', strtotime($params['end_date']));
            $isSameYear = ($startYear === $endYear);
        }

        foreach ($datas as &$row) {
            $acType = strtoupper($row->ac_type ?? '');

            // Tentukan posisi normal (isDr) berdasarkan tipe akun
            if (in_array($acType, ['AS', 'CS', 'EX', 'OT'])) {
                $isDr = true;
            } elseif (in_array($acType, ['LI', 'EQ', 'IC', 'IN'])) {
                $isDr = false;
            } else {
                $isDr = strtolower($row->drcr ?? 'dr') === 'dr';
            }

            // Saldo awal (IDR & foreign)
            $saldoAwal    = $isDr
                ? $row->amt_dr_cutoff     - $row->amt_cr_cutoff
                : $row->amt_cr_cutoff     - $row->amt_dr_cutoff;
            $saldoAwalFrg = $isDr
                ? $row->frg_amt_dr_cutoff - $row->frg_amt_cr_cutoff
                : $row->frg_amt_cr_cutoff - $row->frg_amt_dr_cutoff;

            // Perubahan periode
            $perubahanDebit  = $row->dr_bal_val;
            $perubahanKredit = $row->cr_bal_val;
            $perubahanDebitFrg  = $row->dr_bal_frg_val;
            $perubahanKreditFrg = $row->cr_bal_frg_val;

            // Perubahan bersih (net change) berdasarkan posisi normal akun
            if ($isDr) {
                $perubahan = $perubahanDebit - $perubahanKredit;
                $perubahanFrg = $perubahanDebitFrg - $perubahanKreditFrg;
            } else {
                $perubahan = $perubahanKredit - $perubahanDebit;
                $perubahanFrg = $perubahanKreditFrg - $perubahanDebitFrg;
            }

            // Hitung Saldo Akhir berdasarkan jenis akun & kondisi tahun
            $isNeraca = in_array($acType, ['AS', 'LI', 'EQ']);
            if ($isNeraca || $isSameYear) {
                // Neraca (AS, LI, EQ) atau Laba Rugi dengan tahun yang sama
                $saldoAkhir = $saldoAwal + $perubahan;
                $saldoAkhirFrg = $saldoAwalFrg + $perubahanFrg;
            } else {
                // Laba Rugi (IC, IN, CS, EX, OT) beda tahun -> = Perubahan
                $saldoAkhir = $perubahan;
                $saldoAkhirFrg = $perubahanFrg;
            }

            // Pisah saldo awal menjadi kolom debit / kredit untuk kompatibilitas
            [$saldoAwalDebit, $saldoAwalKredit] = $this->splitBalance($saldoAwal, $isDr);

            // Pisah saldo akhir menjadi kolom debit / kredit untuk kompatibilitas
            [$saldoAkhirDebit, $saldoAkhirKredit] = $this->splitBalance($saldoAkhir, $isDr);

            // Format angka ke 4 desimal
            $fmt = fn($v) => number_format($v, 4, '.', '');

            $row->amt_begin_dr      = $fmt($row->amt_begin_dr);
            $row->amt_begin_cr      = $fmt($row->amt_begin_cr);
            $row->dr_bal            = $fmt($row->dr_bal_val);
            $row->cr_bal            = $fmt($row->cr_bal_val);
            $row->amt_balance       = $fmt($saldoAkhir);
            $row->frg_amt_begin_dr  = $fmt($row->frg_amt_begin_dr);
            $row->frg_amt_begin_cr  = $fmt($row->frg_amt_begin_cr);
            $row->dr_bal_frg        = $fmt($row->dr_bal_frg_val);
            $row->cr_bal_frg        = $fmt($row->cr_bal_frg_val);
            $row->amt_balance_frg   = $fmt($saldoAkhirFrg);
            
            $row->saldo_awal        = $fmt($saldoAwal);
            $row->saldo_awal_frg    = $fmt($saldoAwalFrg);
            $row->saldo_awal_debit  = $fmt($saldoAwalDebit);
            $row->saldo_awal_kredit = $fmt($saldoAwalKredit);
            $row->saldo_akhir_debit  = $fmt($saldoAkhirDebit);
            $row->saldo_akhir_kredit = $fmt($saldoAkhirKredit);

            // Kolom-kolom baru sesuai spesifikasi
            $row->perubahan_debit   = $fmt($perubahanDebit);
            $row->perubahan_kredit  = $fmt($perubahanKredit);
            $row->perubahan         = $fmt($perubahan);
            $row->saldo_akhir       = $fmt($saldoAkhir);
            $row->saldo_akhir_frg   = $fmt($saldoAkhirFrg);
            
            $row->as_of             = $asOfDate;

            $this->removeMonthlyFields($row);
        }
        unset($row);
    }

    /**
     * Pisahkan nilai saldo menjadi sisi debit dan kredit.
     * Mengembalikan [debit, kredit].
     */
    private function splitBalance(float $value, bool $isDr): array
    {
        if ($isDr) {
            return $value >= 0 ? [$value, 0.0] : [0.0, -$value];
        }
        return $value >= 0 ? [0.0, $value] : [-$value, 0.0];
    }

    /**
     * Hapus kolom bulanan (dr_01..cr_12 dan versi _frg) dari baris output.
     */
    private function removeMonthlyFields(object &$row): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $mm = str_pad($m, 2, '0', STR_PAD_LEFT);
            unset($row->{"dr_{$mm}"}, $row->{"cr_{$mm}"}, $row->{"dr_{$mm}_frg"}, $row->{"cr_{$mm}_frg"});
        }

        unset(
            $row->amt_dr_cutoff, $row->amt_cr_cutoff,
            $row->frg_amt_dr_cutoff, $row->frg_amt_cr_cutoff,
            $row->dr_bal_val, $row->cr_bal_val,
            $row->dr_bal_frg_val, $row->cr_bal_frg_val
        );
    }

    /**
     * Buat baris ringkasan "As Of" (grand total dari semua akun daun).
     */
    private function buildAsOfRow(array $datas, array $leafMap, string $year_id, string $asOfDate): object
    {
        $totals = $this->sumLeafTotals($datas, $leafMap);
        $fmt    = fn($v) => number_format($v, 4, '.', '');

        return (object) [
            'year_id'          => $year_id,
            'ac_num'           => '',
            'ac_name'          => 'As of ' . date('d-m-Y', strtotime($asOfDate)),
            'drcr'             => 'DR',
            'amt_type'         => 'DR',
            'amt_begin_dr'     => $fmt($totals['amt_begin_dr']),
            'amt_begin_cr'     => $fmt($totals['amt_begin_cr']),
            'dr_bal'           => $fmt($totals['saldo_awal_debit']),
            'cr_bal'           => $fmt($totals['saldo_awal_kredit']),
            'amt_balance'      => $fmt($totals['saldo_akhir']),
            'frg_amt_begin_dr' => $fmt($totals['frg_amt_begin_dr']),
            'frg_amt_begin_cr' => $fmt($totals['frg_amt_begin_cr']),
            'dr_bal_frg'       => $fmt(0),
            'cr_bal_frg'       => $fmt(0),
            'amt_balance_frg'  => $fmt($totals['saldo_akhir_frg']),
            'saldo_awal'       => $fmt($totals['saldo_awal']),
            'saldo_awal_frg'   => $fmt($totals['saldo_awal_frg']),
            'saldo_awal_debit'  => $fmt($totals['saldo_awal_debit']),
            'saldo_awal_kredit' => $fmt($totals['saldo_awal_kredit']),
            'saldo_akhir_debit'  => $fmt($totals['saldo_akhir_debit']),
            'saldo_akhir_kredit' => $fmt($totals['saldo_akhir_kredit']),
            
            // Kolom total baru
            'perubahan_debit'   => $fmt($totals['perubahan_debit']),
            'perubahan_kredit'  => $fmt($totals['perubahan_kredit']),
            'perubahan'         => $fmt($totals['perubahan']),
            'saldo_akhir'       => $fmt($totals['saldo_akhir']),
            'saldo_akhir_frg'   => $fmt($totals['saldo_akhir_frg']),
            
            'as_of'            => $asOfDate,
        ];
    }

    /**
     * Jumlahkan kolom-kolom penting dari semua akun daun.
     */
    private function sumLeafTotals(array $datas, array $leafMap): array
    {
        $totals = array_fill_keys([
            'amt_begin_dr', 'amt_begin_cr',
            'frg_amt_begin_dr', 'frg_amt_begin_cr',
            'saldo_awal', 'saldo_awal_frg',
            'saldo_awal_debit', 'saldo_awal_kredit',
            'saldo_akhir_debit', 'saldo_akhir_kredit',
            // Total kolom baru
            'perubahan_debit', 'perubahan_kredit', 'perubahan',
            'saldo_akhir', 'saldo_akhir_frg',
        ], 0.0);

        foreach ($datas as $row) {
            if (!$leafMap[$row->ac_num]) {
                continue;
            }

            foreach (array_keys($totals) as $field) {
                $totals[$field] += (float) ($row->$field ?? 0);
            }
        }

        return $totals;
    }
}