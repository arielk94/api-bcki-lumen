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

class ReportGenLedgerDetail extends Controller
{
    public function getGenLedgerDetail_baru(Request $request){
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $sqlWhere = "1=1";
        // $start_date = date('Y-m-d', strtotime($request->start_date)) ; // contoh: '20250101'
        // $end_date = date('Y-m-d', strtotime($request->end_date)); // contoh: '20250531'
        $customer_id = $request->customer_id;
        $vendor_id = $request->vendor_id;
        $source = $request->source;
        $office_id = $request->office_id;        

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        if($customer_id){
            $sqlWhere .= " AND customer_id = '$customer_id'";
        }
        if($vendor_id){
            $sqlWhere .= " AND vendor_id = '$vendor_id'";
        }
        if($source){
            $sqlWhere .= " AND source = '$source'";
        }
        if($office_id){
            $sqlWhere .= " AND office_id = '$office_id'";
        }
        // dd($sqlWhere);
        $data = DB::connection('sqlSrvBCKI')
            ->table('v_general_ledger_detail')
            ->whereRaw($sqlWhere)
            // ->orderBy('tr_date', 'asc')
            ->get();

        return $this->sendResponse($data, 'Successfully get Report General Ledger Detail');
        
    }

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

        $id_site = trim($request->input('id_site'));
        $id_cluster = trim($request->input('id_cluster'));
        $id_house = trim($request->input('id_house'));

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
        $coaData = DB::connection('sqlSrvBCKI')
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
                $prevMonthTrans = DB::connection('sqlSrvBCKI')
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
            if (!empty($id_site)) {
                $sqlWhereDetail .= " AND a.id_site = ?";
                $bindingsDetail[] = $id_site;
            }
            if (!empty($id_cluster)) {
                $sqlWhereDetail .= " AND a.id_cluster = ?";
                $bindingsDetail[] = $id_cluster;
            }
            if (!empty($id_house)) {
                $sqlWhereDetail .= " AND a.id_house = ?";
                $bindingsDetail[] = $id_house;
            }

            // Query Detail Transaksi menggunakan Window Function
            $detailQuery = "
                SELECT
                    a.office_id,
                    a.tr_date,
                    a.Source,
                    a.source_num,
                    a.ac_num,
                    a.ac_name AS ac_name,
                    a.Remark,
                    ISNULL(a.amt_dr, 0) AS amt_dr,
                    ISNULL(a.amt_cr, 0) AS amt_cr,
                    ISNULL(a.vendor_name, '') AS vendor_name,
                    ISNULL(a.customer, '') AS customer_name,
                    a.site_name,
                    a.cluster_name,
                    a.house_name,
                    -- Window Function untuk menghitung running balance
                    ? + SUM(ISNULL(a.amt_dr, 0) - ISNULL(a.amt_cr, 0)) OVER (
                        ORDER BY a.tr_date, a.source, a.source_num
                        ROWS UNBOUNDED PRECEDING
                    ) AS net_balance,
                    ? AS drcr_type,
                    a.article_description,
                    a.pr_id,
                    a.pic_name
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
            $transactionDetails = DB::connection('sqlSrvBCKI')->select($detailQuery, $bindings);

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
                'id_site' => '',
                'site_name' => '',
                'id_cluster' => '',
                'cluster_name' => '',
                'id_house' => '',
                'house_name' => '',
                'article_description' => '',
                'pr_id' => '',
                'pic_name' => '',
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
                    'Source' => $trans->Source,
                    'source_num' => $trans->source_num,
                    'ac_num' => $trans->ac_num,
                    'ac_name' => $trans->ac_name,
                    'Remark' => $trans->Remark,
                    'amt_dr' => number_format($trans->amt_dr, 2, '.', ','),
                    'amt_cr' => number_format($trans->amt_cr, 2, '.', ','),
                    'BALANCE' => number_format($finalBalance, 2, '.', ','),
                    // 'vendor_customer_name' => $trans->vendor_customer_name ?? '',
                    'vendor_name' => $trans->vendor_name ?? '',
                    'customer_name' => $trans->customer_name ?? '',
                    'site_name' => $trans->site_name ?? '',
                    'cluster_name' => $trans->cluster_name ?? '',
                    'house_name' => $trans->house_name ?? '',
                    'article_description' => $trans->article_description ?? '',
                    'pr_id' => $trans->pr_id ?? '',
                    'pic_name' => $trans->pic_name ?? '',
                    // 'curr_id' => $trans->curr_id,
                    // 'Rate' => number_format($trans->Rate, 2, '.', ','),
                ];
            }
            
            // $results[] = [
            //     'ac_num' => $ac_num,
            //     'ac_name' => $data['ac_name'],
            //     'data' => $combinedData
            // ];

            // $results[] = $combinedData;
        }

        return $this->sendResponse($results, 'Successfully get Report General Ledger Detail');
    }
}
