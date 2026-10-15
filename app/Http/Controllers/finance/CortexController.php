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

class CortexController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bcki/cortex/getListFakturCortex",
     *     tags={"Faktur Cortex"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_cdate", type="date", example="2023-01-01"),
     *             @OA\Property(property="end_cdate", type="date", example="2023-12-31"),
     *             @OA\Property(property="start_fdate", type="date", example="2023-01-01"),
     *             @OA\Property(property="end_fdate", type="date", example="2023-12-31"),
     *             @OA\Property(property="csv", type="string", example="Y"),
     *             @OA\Property(property="pay", type="string", example="N"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Faktur Cortex yang akan di export ke CSV",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function getListFakturCortex(Request $request){
        try {
            
            $sqlWhere = '1=1 ';
            //$sqlWhere .= "and a.office_id = '$request->office_id'";

            if($request->start_cdate){
                $sqlWhere .= "and tglcreate  >= '$request->start_cdate'";
            }
            if($request->end_cdate){
                $sqlWhere .= "and tglcreate  <= '$request->end_cdate'";
            }            
            if($request->start_fdate){
                $sqlWhere .= "and tgljual  >= '$request->start_fdate'";
            }
            if($request->end_fdate){
                $sqlWhere .= "and tgljual  <= '$request->end_fdate'";
            }
            if($request->csv == 'Y'){
                $sqlWhere .= "and b.csv is not null ";
            }else if($request->csv == 'N'){
                $sqlWhere .= "and b.csv is null ";
            }
            if($request->pay == 'Y'){
                $sqlWhere .= "and pay_amt > 0 ";
            }else if($request->pay == 'N'){
                $sqlWhere .= "and pay_amt <= 0  ";
            }

            $getListFakturCortex = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select distinct ROW_NUMBER() OVER (ORDER BY referensi)  AS Baris, * from 
                                (select distinct tanggal_faktur 'Tanggal Faktur', jenis_faktur 'Jenis Faktur',kode_transaksi 'Kode Transaksi' 
                                ,keterangan_tambahan 'Keterangan Tambahan',dokumen_pendukung 'Dokumen Pendukung',Period_Dok_Pendukung 'Period Dok Pendukung'
                                ,referensi Referensi,cap_fasilitas 'Cap Fasilitas',id_tku_penjual 'ID TKU Penjual' ,npwp_nik_pembeli 'NPWP/NIK Pembeli'
                                ,jenis_id_pembeli 'Jenis ID Pembeli',negara_pembeli 'Negara Pembeli' ,nomor_dokumen_pembeli 'Nomor Dokumen Pembeli'
                                ,nama_pembeli 'Nama Pembeli' ,alamat_pembeli 'Alamat Pembeli',email_pembeli 'Email Pembeli',id_tku_pembeli 'ID TKU Pembeli'
                                ,isnull((select top 1 dp_id from dp_sales_dtl where order_id=customerorderno),'') as adadp ,'' as TidakDiPajak
                                ,case when b.csv is null then 'N' else 'Y' end as csv_flag, pay_amt
                                from v_coretax a
                                left outer join tax_slot_number b on a.no_faktur=b.penjualanno and b.tax_type='CRX'
                                where $sqlWhere ) x order by referensi "));  

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListFakturCortex, 'Successfully get List Faktur Cortex');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }

    }

    /**
     * @OA\Post(
     *     path="/api/bcki/cortex/getListFakturCortexDtl",
     *     tags={"Faktur Cortex"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="faktur", type="array", @OA\Items(
     *                 @OA\Property(property="baris", type="integer", example=1),
     *                 @OA\Property(property="penjualanno", type="string", example="ABC123"),
     *                 @OA\Property(property="f_date", type="date", example="2023-01-01"),
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List Faktur Cortex Detail yang akan di export ke CSV",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getListFakturCortexDtl(Request $request)
    {
        $data = $request->all();       
            
        try {            
            // insert into temp_coretax
            $cekDoc = DB::connection("sqlSrvBCKI")
                ->table('temp_coretax')
                ->where('global_id', getAuth($request)->user_id2)
                ->first();

            // Jika ada dokumen sebelumnya, hapus dulu
            if ($cekDoc) {
                DB::connection("sqlSrvBCKI")
                    ->table('temp_coretax')
                    ->where('global_id', getAuth($request)->user_id2)
                    ->delete();
            }
            foreach ($data['faktur'] as $key => $faktur) {
                DB::connection('sqlSrvBCKI')->table('temp_coretax')->insert([
                        'global_id' => getAuth($request)->user_id2,
                        'baris' =>  $faktur['baris'],
                        'penjualanno' => $faktur['penjualanno'],
                ]);   

                $cektaxslot = DB::connection("sqlSrvBCKI")
                    ->table('tax_slot_number')
                    ->where('tax_no',  $faktur['penjualanno'])
                    ->first();

                $datataxslot = [
                    'tax_no' => $faktur['penjualanno'],
                    'f_date' => $faktur['f_date'],
                    'penjualanno' => $faktur['penjualanno'],
                    'csv' => 'Y' ,
                    'tax_type' => 'CRX' ,
                    'dt_csv' => now()
                ];
                if ($cektaxslot) {                    
                    DB::connection("sqlSrvBCKI")
                        ->table('tax_slot_number')
                        ->where('tax_no',  $faktur['penjualanno'])
                        ->update($datataxslot);
                }else {
                    DB::connection("sqlSrvBCKI")
                        ->table('tax_slot_number')
                        ->insert($datataxslot);
                }
            }

            // Get List Faktur detail
            $getListFakturCortexDtl = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select baris Baris,b.barang_jasa 'Barang/Jasa',b.kode_barang_jasa 'Kode Barang Jasa', b.nama_barang_jasa 'Nama Barang/Jasa'
                                    ,b.nama_satuan_ukur 'Nama Satuan Ukur', b.harga_satuan 'Harga Satuan', b.jumlah_barang_jasa 'Jumlah Barang Jasa'
                                    ,b.total_diskon 'Total Diskon' ,b.dpp DPP,b.dpp_nilai_lain 'DPP Nilai Lain'
                                    ,case when year(b.TglJual)>=2025 then 12  else b.tarif_ppn end as 'Tarif PPN'
                                    ,round(b.dpp*b.tarif_ppn/100,2) PPN, b.tarif_ppnbm 'Tarif PPnBM', b.ppnbm PPnBM 
                                    from [temp_coretax] a inner join v_coretax b on a.penjualanno =b.no_faktur
                                    where global_id = '" . getAuth($request)->user_id2 . "' 
                                    order by baris "));  
            // $getListFakturCortexDtl = DB::connection("sqlSrvBCKI")
            //     ->select(DB::raw("select baris Baris,b.barang_jasa 'Barang/Jasa',b.kode_barang_jasa 'Kode Barang Jasa', b.nama_barang_jasa 'Nama Barang/Jasa'
            //                         ,b.nama_satuan_ukur 'Nama Satuan Ukur', b.harga_satuan 'Harga Satuan', b.jumlah_barang_jasa 'Jumlah Barang Jasa'
            //                         ,b.total_diskon 'Total Diskon' ,b.dpp DPP,b.dpp_nilai_lain 'DPP Nilai Lain'
            //                         ,case when year(b.TglJual)>=2025 then 12  else b.tarif_ppn end as 'Tarif PPN'
            //                         ,round(b.dpp*b.tarif_ppn/100,2) PPN, b.tarif_ppnbm 'Tarif PPnBM', b.ppnbm PPnBM,year(b.TglJual) tahunjual
            //                         from [temp_coretax] a inner join v_coretax b on a.penjualanno =b.no_faktur
            //                         where global_id = '" . getAuth($request)->user_id2 . "' 
            //                         order by baris "));  

            //return response()->json(['getListKBHdr' => $getListKBHdr]);
            return $this->sendResponse($getListFakturCortexDtl, 'Successfully get List Faktur Cortex');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

}