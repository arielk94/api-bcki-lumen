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

class NoUrutNotaController extends Controller
{
    public function getNoUrut(Request $request){
        // $sqlWhere = "1=1 ";
        $id = trim($request->id);
        $type = trim($request->type);
        // if(!$id){
        //     $sqlWhere .= " and id = '$id'";
        // }
        // 1. Sub-query untuk purc_pay_mast
        $query1 = DB::connection("sqlSrvBCKI")->table('purc_pay_mast as a')
            ->select('a.pay_id as id', 'a.no_urut_dokumen', 'e.id_site')
            ->leftJoin('purc_pay_item as c', 'a.pay_id', '=', 'c.pay_id')
            ->leftJoin('purc_inv_mast as d', 'd.inv_id', '=', 'c.inv_id')
            ->leftJoin('purc_inv_item as e', 'd.inv_id', '=', 'e.inv_id')
            ->leftJoin('site as s', 's.id_site', '=', 'e.id_site');

        // 2. Sub-query untuk cash_tran_hdr
        $query2 = DB::connection("sqlSrvBCKI")->table('cash_tran_hdr as a')
            ->select('a.tr_id as id', 'a.no_urut_dokumen', 'a.id_site')
            ->where('a.tr_type', $type);

        // 3. Sub-query untuk payment_hdr
        $query3 = DB::connection("sqlSrvBCKI")->table('payment_hdr as a')
            ->select('a.pay_id as id', 'a.no_urut_dokumen', 'e.id_site')
            ->leftJoin('payment_dtl as c', 'c.pay_id', '=', 'a.pay_id')
            ->leftJoin('penjualan as d', 'c.penjualanno', '=', 'd.penjualanno')
            ->leftJoin('customerorder as e', 'd.customerorderno', '=', 'e.order_id')
            ->leftJoin('site as s', 's.id_site', '=', 'e.id_site');

        // 4. Sub-query untuk dp_sales
        $query4 = DB::connection("sqlSrvBCKI")->table('dp_sales as a')
            ->select('a.dp_id as id', 'a.no_urut_dokumen', 'e.id_site')
            ->leftJoin('payment_hdr as b', 'a.dp_id', '=', 'b.bukti')
            ->leftJoin('payment_dtl as c', 'c.pay_id', '=', 'b.pay_id')
            ->leftJoin('penjualan as d', 'c.penjualanno', '=', 'd.penjualanno')
            ->leftJoin('customerorder as e', 'd.customerorderno', '=', 'e.order_id')
            ->leftJoin('site as s', 's.id_site', '=', 'e.id_site');

        // --- 2. CARI ID_SITE BERDASARKAN ID INPUT (FILTER PADA KOLOM ASLI) ---
        // SQL Server tidak suka filter alias 'id' di dalam Union, jadi kita filter kolom fisiknya
        $findId = (clone $query1)->where('a.pay_id', $id)
            ->unionAll((clone $query2)->where('a.tr_id', $id))
            ->unionAll((clone $query3)->where('a.pay_id', $id))
            ->unionAll((clone $query4)->where('a.dp_id', $id))
            ->first();

        // MODIFIKASI: id_site sekarang fleksibel (bisa terisi atau kosong)
        $id_site = ($findId && !empty(trim($findId->id_site))) ? trim($findId->id_site) : null;

        // --- 3. TENTUKAN ACTIVE QUERY BERDASARKAN TYPE ---
        if ($type == 'PP') {
            $activeQuery = $query1;
            $columnName = 'a.pay_id';
            $siteAlias = 'e.id_site';
        } if ($type == 'OP' || $type == 'OD') {
            $activeQuery = $query2;
            $columnName = 'a.tr_id';
            $siteAlias = 'a.id_site';
        } if ($type == 'CP') {
            $activeQuery = $query3;
            $columnName = 'a.pay_id';
            $siteAlias = 'e.id_site';
        } if ($type == 'CD') {
            $activeQuery = $query4;
            $columnName = 'a.dp_id';
            $siteAlias = 'e.id_site';
        }
        // $subQuerySql = "(
        //     SELECT pay_id AS id, no_urut_dokumen FROM purc_pay_mast
        //     UNION ALL
        //     SELECT tr_id AS id, no_urut_dokumen FROM cash_tran_hdr
        //     UNION ALL
        //     SELECT pay_id AS id, no_urut_dokumen FROM payment_hdr
        //     UNION ALL
        //     SELECT dp_id AS id, no_urut_dokumen FROM dp_sales
        // ) AS gabungan_data";

        // --- LANGKAH 1: Cari berdasarkan ID ---
        // $currentDoc = DB::connection("sqlSrvBCKI")
        //     ->table(DB::raw($subQuerySql))
        //     ->where('id', $id)
        //     ->first();
        // dd($currentDoc);
        // Jika ID ditemukan DAN no_urut_dokumen tidak null/kosong
        // --- 4. CEK DOKUMEN SAAT INI ---
        $currentDoc = (clone $activeQuery)->where($columnName, $id)->first();

        if ($currentDoc && !empty($currentDoc->no_urut_dokumen)) {
            return $this->sendResponse($currentDoc->no_urut_dokumen, 'No urut ditemukan berdasarkan ID');
        }

        // --- 5. CARI NO URUT TERAKHIR (HIGHEST) ---
        $lastNoDoc = (clone $activeQuery)
        ->whereNotNull('a.no_urut_dokumen')
        ->where(DB::raw("trim(a.no_urut_dokumen)"), '<>', '')
        ->where($siteAlias, $id_site) // Filter wajib site
        ->orderBy(DB::raw('LEN(a.no_urut_dokumen)'), 'desc') 
        ->orderBy('a.no_urut_dokumen', 'desc')
        ->first();

        if ($lastNoDoc) {
            // Jalur "Ya" (Ditemukan) -> Retur No urutnya + 1
            // $nextNoUrut = (int)$lastNoDoc->no_urut_dokumen + 1;
            $rawVal = trim($lastNoDoc->no_urut_dokumen);
            // Regex untuk memisahkan prefix (grup 1) dan angka terakhir (grup 2)
            if (preg_match('/(.*?)([0-9]+)$/', $rawVal, $matches)) {
                $prefix = $matches[1];     // Contoh: "A."
                $numberPart = $matches[2];  // Contoh: "123123"
                
                $length = strlen($numberPart);
                $nextValue = (int)$numberPart + 1;
                
                // Pad kembali dengan nol sesuai panjang aslinya
                $nextNumber = str_pad($nextValue, $length, "0", STR_PAD_LEFT);
                $nextNoUrut = $prefix . $nextNumber;
                
                return $this->sendResponse($nextNoUrut, 'No urut berikutnya digenerate');
            } else {
                // Fallback jika ternyata format tidak diakhiri angka
                $nextNoUrut = $rawVal . "1";
                return $this->sendResponse($nextNoUrut, 'No urut berikutnya (suffix 1)');
            }
            // // Simpan panjang karakter asli (misal "0008" panjangnya 4)
            // $length = strlen($rawVal);
            
            // // Lakukan increment secara integer
            // $nextValue = (int)$rawVal + 1;
            
            // // Balikkan angka nol di depan menggunakan str_pad
            // // STR_PAD_LEFT akan menambah "0" di kiri sampai panjangnya sesuai $length
            // $nextNoUrut = str_pad($nextValue, $length, "0", STR_PAD_LEFT);
            // return $this->sendResponse($nextNoUrut, 'No urut terakhir ditemukan, generate nomor berikutnya');
        } else {
            // Jalur "Tidak" (Tidak ditemukan sama sekali di semua tabel)
            return $this->sendResponse('', 'No urut tidak ditemukan, retur string kosong');
        }

    }

    public function updateNoUrut(Request $request){
        $type = $request->type;
        //PP(Purch Payment Posting (purc_pay_mast)),
        // OP (Other Payment (cash_trans_hdr)),
        // OD (Other Deposit (cash_trans_hdr)),
        // CP( Customer Payment Posting (payment_hdr)),
        // CD(Customer Deposit (dp_sales))
        $id = $request->id;
        $no_urut = $request->no_urut;

        // --- 1. DEFINISI BASE QUERY (Gunakan alias 'a' secara konsisten) ---
    $query1 = DB::connection("sqlSrvBCKI")->table('purc_pay_mast as a')
        ->leftJoin('purc_pay_item as c', 'a.pay_id', '=', 'c.pay_id')
        ->leftJoin('purc_inv_mast as d', 'd.inv_id', '=', 'c.inv_id')
        ->leftJoin('purc_inv_item as e', 'd.inv_id', '=', 'e.inv_id');

    $query2 = DB::connection("sqlSrvBCKI")->table('cash_tran_hdr as a');

    $query3 = DB::connection("sqlSrvBCKI")->table('payment_hdr as a')
        ->leftJoin('payment_dtl as c', 'c.pay_id', '=', 'a.pay_id')
        ->leftJoin('penjualan as d', 'c.penjualanno', '=', 'd.penjualanno')
        ->leftJoin('customerorder as e', 'd.customerorderno', '=', 'e.order_id');

    $query4 = DB::connection("sqlSrvBCKI")->table('dp_sales as a')
        ->leftJoin('payment_hdr as b', 'a.dp_id', '=', 'b.bukti')
        ->leftJoin('payment_dtl as c', 'c.pay_id', '=', 'b.pay_id')
        ->leftJoin('penjualan as d', 'c.penjualanno', '=', 'd.penjualanno')
        ->leftJoin('customerorder as e', 'd.customerorderno', '=', 'e.order_id');

        // --- 2. CARI DATA TRANSAKSI (UNTUK ID_SITE) ---
        $currentData = null;
        if ($type == 'PP') $currentData = (clone $query1)->select('e.id_site')->where('a.pay_id', $id)->first();
        elseif ($type == 'OP' || $type == 'OD') $currentData = (clone $query2)->select('a.id_site')->where('a.tr_id', $id)->first();
        elseif ($type == 'CP') $currentData = (clone $query3)->select('e.id_site')->where('a.pay_id', $id)->first();
        elseif ($type == 'CD') $currentData = (clone $query4)->select('e.id_site')->where('a.dp_id', $id)->first();

        $id_site = ($currentData && !empty(trim($currentData->id_site))) ? trim($currentData->id_site) : null;

        // --- 3. PENGECEKAN DUPLIKAT ---
        // Gunakan alias 'a.' pada no_urut_dokumen untuk menghindari Ambiguous Column
        switch ($type) {
            case 'PP':
                $checkQuery = (clone $query1)
                ->select('a.pay_id as conflict_id', 's.site_name as conflict_site_name') // Tambahkan site_name (sesuaikan nama kolomnya)
                ->leftJoin('site as s', 's.id_site', '=', 'e.id_site') // Pastikan join ke site ada
                ->where('a.no_urut_dokumen', $no_urut)
                ->where('a.pay_id', '!=', $id);
                $siteAlias = 'e.id_site';
                break;
            case 'OP':
            case 'OD':
                $checkQuery = (clone $query2)
                ->select('a.tr_id as conflict_id', 's.site_name as conflict_site_name')
                ->leftJoin('site as s', 's.id_site', '=', 'a.id_site')
                ->where('a.no_urut_dokumen', $no_urut)
                ->where('a.tr_id', '!=', $id)
                ->where('a.tr_type', $type);
                $siteAlias = 'a.id_site';
                break;
            case 'CP':
                $checkQuery = (clone $query3)
                ->select('a.pay_id as conflict_id', 's.site_name as conflict_site_name')
                ->leftJoin('site as s', 's.id_site', '=', 'e.id_site')
                ->where('a.no_urut_dokumen', $no_urut)
                ->where('a.pay_id', '!=', $id);
                $siteAlias = 'e.id_site';
                break;
            case 'CD':
                $checkQuery = (clone $query4)
                ->select('a.dp_id as conflict_id', 's.site_name as conflict_site_name')
                ->leftJoin('site as s', 's.id_site', '=', 'e.id_site')
                ->where('a.no_urut_dokumen', $no_urut)
                ->where('a.dp_id', '!=', $id);
                $siteAlias = 'e.id_site';
                break;
            default:
                return $this->sendError("Tipe dokumen tidak valid.");
        }

        // Eksekusi Pengecekan
        $isDuplicate = $checkQuery->when($id_site, function ($q) use ($siteAlias, $id_site) {
            return $q->where($siteAlias, $id_site);
        })->first();
        // dd($isDuplicate);
        if ($isDuplicate) {
            // $msg = $id_site ? "di site [$id_site]" : "secara global";
            // return $this->sendError("Gagal! Nomor urut [$no_urut] sudah digunakan $msg.");
            $conflictId = $isDuplicate->conflict_id;
            // Ambil nama site, jika null (karena pencarian global/site tidak ada), fallback ke 'Global'
            $siteName = !empty($isDuplicate->conflict_site_name) ? trim($isDuplicate->conflict_site_name) : "General";
            
            return $this->sendError("Gagal! Nomor urut [$no_urut] sudah digunakan oleh ID [$conflictId] di site [$siteName].");
        }

        try{
            if($type == 'PP'){
                // dd('masuk purchase payment posting - purc_pay_mast');
                DB::connection('sqlSrvBCKI')->table('purc_pay_mast')
                ->where('pay_id', $id)
                ->update([
                    'no_urut_dokumen' => $no_urut
                ]);
            }
            if($type == 'OP' || $type == 'OD'){
                // dd('masuk other payment, other deposit - cash_tran_hdr');
                DB::connection('sqlSrvBCKI')->table('cash_tran_hdr')
                ->where('tr_id', $id)
                ->where('tr_type', $type)
                ->update([
                    'no_urut_dokumen' => $no_urut
                ]);
            }
            if($type == 'CP'){
                // dd("masuk customer payment posting - payment_hdr");
                DB::connection('sqlSrvBCKI')->table('payment_hdr')
                ->where('pay_id', $id)
                ->update([
                    'no_urut_dokumen' => $no_urut
                ]);
            }
            if($type == 'CD'){
                // dd('masuk customer deposit - dp_sales');
                DB::connection('sqlSrvBCKI')->table('dp_sales')
                ->where('dp_id', $id)
                ->update([
                    'no_urut_dokumen' => $no_urut
                ]);
            } 
            return $this->sendResponse($no_urut, 'update success');
        }
        catch (\Exception $e) {
            return $this->sendError('Terjadi kesalahan saat update: ' . $e->getMessage());
        }       
    }
}
