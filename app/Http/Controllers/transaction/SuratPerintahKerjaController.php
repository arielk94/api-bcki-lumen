<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use App\Traits\transaction\SuratPerintahKerjaTrait;
use App\Traits\SendNotificationAppBarTrait;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use DateTime;


class SuratPerintahKerjaController extends Controller
{
    use SuratPerintahKerjaTrait;
    use SendNotificationAppBarTrait;

    public function getEmployee()
    {
        $employe = $this->queryGetEmployeUser();

        return $this->sendResponse($employe, 'Successfully get Employee');
    }

    public function getKontraktor()
    {
        $datas = DB::connection("sqlSrvBCKI")
            ->table('vendor')
            ->selectRaw("TRIM (vendor_id) AS vendor_id, TRIM (vendor_name) AS vendor_name")
            ->get();

        return $this->sendResponse($datas, 'Successfully get Contractor');
    }

    public function getQCForm(Request $request)
    {
        $spk_type = $request->spk_type;
        if($spk_type == 'T'){
            $datas = DB::connection("sqlSrvBCKI")
                ->table('qc_form')
                ->where('qc_type', '=', 'SK')
                ->where('spk_type', $spk_type)
                ->get();  
        }else{
            $datas = DB::connection("sqlSrvBCKI")
                ->table('qc_form')
                ->where('qc_type', '=', 'SK')
                ->get();
        }

        return $this->sendResponse($datas, 'Successfully get Contractor');
    }
    public function getQCItem(Request $request)
    {
        $id_form = $request->id_form;
        // $spk_type = $request->spk_type;


        DB::connection('sqlSrvBCKI')->beginTransaction();
        $category = DB::connection("sqlSrvBCKI")
            ->table('qc_item_category')
            ->where('active_flag', '=', 'Y')
            ->get();

        foreach ($category as $key => $item) {
            $qc_item = DB::connection("sqlSrvBCKI")
                ->table('qc_item')
                ->selectRaw("qc_item.qc_item, qc_item.id_qc_item")
                ->where('qc_item.id_qc_form', '=', $id_form)
                ->where('qc_item.cat_id', '=', $item->cat_id)
                ->get();

            if ($qc_item->isEmpty()) {
                unset($category[$key]); // Hapus jika kosong
            } else {
                $item->qc_item = $qc_item;
            }
            
            $qc_item_html = DB::connection("sqlSrvBCKI")
                ->table('qc_item')
                ->selectRaw("qc_item.qc_item_html, qc_item.id_qc_item")
                ->where('qc_item.id_qc_form', '=', $id_form)
                ->where('qc_item.cat_id', '=', $item->cat_id)
                ->get();

            if ($qc_item_html->isEmpty()) {
                unset($category[$key]); // Hapus jika kosong
            } else {
                $item->qc_item_html = $qc_item_html;
            }
        }


        DB::connection('sqlSrvBCKI')->commit();

        return $this->sendResponse($category, 'Successfully get Contractor');
    }

    public function storeSPK(Request $request)
    {
        $gen_spk = $this->generateIdSPK($request->id_site);
        $article_data = $request->article_data;
        $qc_item = $request->qc_item;

        try {
            DB::connection('sqlSrvBCKI')->beginTransaction();

            $user = DB::connection("sqlsrvHrd")
                ->table("employee")
                ->select("office_id")
                ->where("user_id2", getAuth($request)->user_id2)
                ->first();

            $datas = DB::connection("sqlSrvBCKI")
                ->table('spk_hdr')
                ->insert([
                    'id_spk' => $gen_spk['id_spk'],
                    'pihak_1' => $request->pihak_1?? '',
                    'pihak_2' => $request->pihak_2 ?? '',
                    'id_site' => $request->id_site,
                    'id_cluster' => $request->id_cluster,
                    'id_house_unit' => $request->id_house,
                    'id_cetak' => $gen_spk['id_cetak'],
                    'id_urut' => $gen_spk['id_urut'],
                    'id_spb' => $request->id_spb,
                    'active_flag' => 'Y',
                    'status' => 'O',
                    'date_start' => $request->date_start ?? '',
                    'date_target' => $request->date_target ?? '',
                    'date_finish' => $request->date_finish ?? '',
                    'dt_created' => now(),
                    'dt_modified' => '',
                    'create_by' => getAuth($request)->user_id2,
                    'modified_by' => '',
                    'dt_aprv1' => '',
                    'aprv1_by' => '',
                    'dt_aprv2' => '',
                    'aprv2_by' => '',
                    'amt' => $request->amt ?? '',
                    'amt_include' => $request->amt_include ?? '',
                    'amt_exclude' => $request->amt_exclude ?? '',
                    'spk_type' => $request->spk_type,
                    'luas_bangunan' => $request->custom_lb ?? '',
                    'remark' => $request->remark ?? ''
                ]);

            if ($datas) {
                if (isset($request->note_article)) {
                    DB::connection("sqlSrvBCKI")
                        ->table('appl_remark')
                        ->insert([
                            'office_id' => $user->office_id,
                            'doc_id' => $gen_spk['id_spk'],
                            'type_code' => "SPK_A",
                            'seq_num' => 1,
                            'remarks' => $request->note_article,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);
                }
                
                if (isset($request->note_qc)) {
                    DB::connection("sqlSrvBCKI")
                        ->table('appl_remark')
                        ->insert([
                            'office_id' => $user->office_id,
                            'doc_id' => $gen_spk['id_spk'],
                            'type_code' => "SPK_QC",
                            'seq_num' => 1,
                            'remarks' => $request->note_qc,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);
                }

                DB::connection("sqlSrvBCKI")
                    ->table('appl_remark')
                    ->insert([
                        'office_id' => $user->office_id,
                        'doc_id' => $gen_spk['id_spk'],
                        'type_code' => "SPK_L",
                        'seq_num' => 1,
                        'remarks' => $request->notes,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);

                DB::connection("sqlSrvBCKI")
                    ->table('qc_trans_hdr')
                    ->insert([
                        'qc_trans_id' => $gen_spk['qc_trans_id'],
                        'doc_id' => $gen_spk['id_spk'],
                        'id_qc_form' => $request->id_qc_form,
                        'id_house' => $request->id_house,
                        'dt_rencana' => $request->date_rencana ?? '',
                        'dt_target' => $request->date_target ?? '',
                        'dt_finish' => $request->date_finish ?? '',
                        'doc_type' => "SPK",
                        'dt_created' => now(),
                        'created_by' => getAuth($request)->user_id2
                    ]);

                $qc = [];

                foreach ($qc_item as $key => $item) {
                    foreach($item['qc_item'] as $key2 => $value)
                    $qc[] = [
                        'qc_trans_id' => $gen_spk['qc_trans_id'],
                        'id_qc_item' => $value['id_qc_item'],
                        'stat' => 'N',
                        'dt_created' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'dt_aprv' => '',
                        'aprv_by' => '',
                        'remark' => ''
                    ];
                }

                DB::connection("sqlSrvBCKI")
                    ->table('qc_trans_dtl')
                    ->insert($qc);

                // Simpan article_data
                $e = [];
                foreach ($article_data as $key => $item) {
                    $e[] = [
                        'id_spk' => $gen_spk['id_spk'],
                        'article_id' => $item['article_id'],
                        'type_article' => $item['type_article'],
                        'qc_group' => '',
                        'harga_satuan' => (int) str_replace(',', '', $item['harga_satuan']),
                        'qty' => (string) floatval($item['qty']),
                        'uom' => $item['uom'],
                        'select_article' => $item['select_article'] ?? '',
                        'select_pengurangan' => $item['select_pengurangan'] ?? '',
                        'item_num' => $key + 1,
                        'active_flag' => 'Y',
                        'dt_created' => now(),
                        'dt_modified' => '',
                        'create_by' => getAuth($request)->user_id2,
                        'modified_by' => ''
                    ];
                }

                DB::connection("sqlSrvBCKI")
                    ->table('spk_article')
                    ->insert($e);

                foreach ($request->file as $key => $item) {
                    $file = $item['file']; // base64
                    // $name = $item['name'];
                    $typeFile = $item['type_file'];

                    if (preg_match('/^data:(.*?);base64,(.*)$/', $file, $matches)) {
                        $mimeType = $matches[1];
                        $base64Content = base64_decode($matches[2]);
                        $nama_file = Str::random(40) . '.' . $typeFile;

                        $path = 'fpis/spk/' . $nama_file;

                        if (str_starts_with($mimeType, 'image/')) {
                            // Proses dengan Intervention Image
                            $image = Image::make($base64Content)->encode($typeFile, 100);
                            Storage::disk('public')->put($path, (string) $image);
                        } elseif ($mimeType === 'application/pdf') {
                            // Simpan langsung
                            Storage::disk('public')->put($path, $base64Content);
                        }
                    }
                    $data = DB::connection("sqlSrvBCKI")
                        ->table('spk_file')
                        ->insert([
                            'id_spk' => $gen_spk['id_spk'],
                            'nama_document' => $nama_file,
                            'doc_path' => $path,
                            'item_num' => $key + 1,
                            'type_file' => $typeFile,
                            'dt_created' => now(),
                            'dt_modified' => '',
                            'created_by' => getAuth($request)->user_id2,
                            'modified_by' => ''
                        ]);
                }

                DB::connection('sqlSrvBCKI')->commit();
            }

            $user = DB::connection('sqlSrvBCKI')
                ->table('spk_aprv_env')
                ->selectRaw('aprv_1, no_hp_1, nama_employee_1')
                ->where('doc_type', '=', 'SPK')
                ->where('spk_type', '=', $request->spk_type)
                ->where('active_flag', '=', 'Y')
                ->get();

                if($user){
                    $countSuccess = 0;
                    $countError = 0;
                    $totalJumlah = 0;
                    foreach($user as $key => $value){
                        $res = $this->sendNotifSPKApproval($gen_spk['id_spk'], $value->aprv_1, $value->no_hp_1, );

                        if($res['stat'] == 1){
                            $countSuccess++;
                        }else{
                            $countError++;
                        }
                        $totalJumlah++;
                    }
                    
                    $notifMassage = 'Ada SPK Tambahan Baru, Silahkan untuk membuat Customer Invoice di Aplikasi Dekstop';
                    $this->sendNotifLonceng('','SPK Tambahan Baru',$notifMassage,'TRANSACTION','mnuSPK');
                    
                    if($countError == 0 && $countSuccess == $totalJumlah){
                        return response()->json([
                            'status' => false,
                            'message' => 'Successfully Save SPK & Send All Notifikasi'
                        ], 200);
                    }else if($countError != 0 && $countSuccess != $totalJumlah){
                        return response()->json([
                            'status' => false,
                            'message' => 'Successfully Save SPK & Send Notifikasi'
                        ], 200);
                    }else{
                        return response()->json([
                            'status' => false,
                            'message' => 'Successfully Save SPK'
                        ], 200);
                    }
                }
        } catch (\Throwable $th) {
            // Mengembalikan response dengan pesan error yang lebih informatif
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan dalam sistem. Silakan coba lagi atau hubungi administrator.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    
    public function updateSPK(Request $request)
    {
        $id_spk = $request->id_spk;
        $article_data = $request->article_data;
        $qc_item = $request->qc_item;
        $id_house = $request->id_house;
        $id_qc_form = $request->id_qc_form;
        $updateQcForm = $request->updateQcForm;
        $qc_trans_id = $request->qc_trans_id;

        $user = DB::connection("sqlsrvHrd")
            ->table("employee")
            ->select("office_id")
            ->where("user_id2", getAuth($request)->user_id2)
            ->first();

        $spk = DB::connection("sqlSrvBCKI")
            ->table('spk_hdr')
            ->where('id_spk', $id_spk)
            ->first();
        
        if ($spk->dt_aprv1 != '1900-01-01 00:00:00.000' || $spk->dt_aprv2 != '1900-01-01 00:00:00.000') {
            return response()->json([
                'status' => false,
                'message' => 'SPK sudah diapprove, data tidak bisa diubah'
            ], 200);
        }

        $datas = DB::connection("sqlSrvBCKI")
            ->table('spk_hdr')
            ->where("id_spk", $id_spk)
            ->update([
                'pihak_1' => $request->pihak_1,
                'pihak_2' => $request->pihak_2 ?? '',
                'id_site' => $request->id_site,
                'id_cluster' => $request->id_cluster,
                'id_house_unit' => $request->id_house,
                'id_spb' => $request->id_spb,
                'active_flag' => 'Y',
                'status' => 'O',
                'date_start' => $request->date_start ?? '',
                'date_target' => $request->date_target ?? '',
                'date_finish' => $request->date_finish ?? '',
                'dt_modified' => now(),
                'modified_by' => getAuth($request)->user_id2,
                'amt' => $request->amt ?? '',
                'amt_include' => $request->amt_include ?? '',
                'amt_exclude' => $request->amt_exclude ?? '',
                'spk_type' => $request->spk_type,
                'luas_bangunan' => $request->custom_lb ?? '',
                'remark' => $request->remark ?? ''
            ]);

        if ($datas) {
            
            if (isset($request->note_qc)) {
                DB::connection("sqlSrvBCKI")
                    ->table('appl_remark')
                    ->where('doc_id', $id_spk)
                    ->where('type_code', "SPK_QC")
                    ->update([
                        'office_id' => $user->office_id,
                        'seq_num' => 1,
                        'remarks' => $request->note_qc,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);
            }
            if (isset($request->note_article)) {
                DB::connection("sqlSrvBCKI")
                    ->table('appl_remark')
                    ->where('doc_id', $id_spk)
                    ->where('type_code', "SPK_A")
                    ->update([
                        'office_id' => $user->office_id,
                        'seq_num' => 1,
                        'remarks' => $request->note_article,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);
            }

            DB::connection("sqlSrvBCKI")
                ->table('appl_remark')
                ->where('doc_id', $id_spk)
                ->where('type_code', "SPK_L")
                ->update([
                    'office_id' => $user->office_id,
                    'seq_num' => 1,
                    'remarks' => $request->notes,
                    'dt_modified' => now(),
                    'user_id' => getAuth($request)->user_id2
                ]);

            DB::connection("sqlSrvBCKI")
            ->table('qc_trans_hdr')
            ->where('doc_id','=', $id_spk)
            ->where('doc_type','=', 'SPK')
            ->where('qc_trans_id','=', $qc_trans_id)
            ->update([
                'id_qc_form' => $id_qc_form,
                'dt_modified' => now(),
                'modified_by' => getAuth($request)->user_id2
            ]);
            
            $qc = [];
                
            foreach ($qc_item as $key => $item) {
                foreach($item['qc_item'] as $key2 => $val){
                    $qc[] = [
                        'qc_trans_id' => $qc_trans_id,
                        'id_qc_item' => $val['id_qc_item'],
                        'stat' => 'N',
                        'dt_created' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'dt_aprv' => '',
                        'aprv_by' => '',
                        'remark' => ''
                    ];
                }
            }
            if($qc_item && $updateQcForm){
                DB::connection("sqlSrvBCKI")
                ->table('qc_trans_dtl')
                ->where('qc_trans_id','=', $qc_trans_id)
                ->delete();
    
                DB::connection("sqlSrvBCKI")
                    ->table('qc_trans_dtl')
                    ->insert($qc);
            }

                        
            DB::connection("sqlSrvBCKI")
                ->table('spk_article')
                ->where('id_spk', $id_spk)
                ->delete();


            // Simpan article_data
            $e = [];
            foreach ($article_data as $key => $item) {
                $e[] = [
                    'id_spk' => $id_spk,
                    'article_id' => $item['article_id'],
                    'type_article' => $item['type_article'],
                    'harga_satuan' => $item['harga_satuan'],
                    'qty' => $item['qty'],
                    'uom' => $item['uom'],
                    'select_article' => $item['select_article'],
                    'select_pengurangan' => $item['select_pengurangan'] ?? '',
                    'item_num' => $key + 1,
                    'active_flag' => 'Y',
                    'dt_modified' => Carbon::now(),
                    'modified_by' => getAuth($request)->user_id2
                ];
            }

            DB::connection("sqlSrvBCKI")
                ->table('spk_article')
                ->insert($e);

            if ($request->file) {
              // Ambil semua file yang sudah ada di DB untuk id_spk ini
              $existingFiles = DB::connection("sqlSrvBCKI")
                ->table('spk_file')
                ->where('id_spk', $id_spk)
                ->get();

              // Kumpulkan nama_document dari request (hanya file yang sudah ada / tidak ada base64 baru)
              $requestFileNames = [];

              foreach ($request->file as $key => $item) {
                $file     = $item['file'];
                $typeFile = $item['type_file'];

                $isBase64 = preg_match('/^data:(.*?);base64,(.*)$/', $file, $matches);

                if ($isBase64) {
                  // ==================== FILE BARU ====================
                  $mimeType      = $matches[1];
                  $base64Content = base64_decode($matches[2]);
                  $nama_file     = Str::random(40) . '.' . $typeFile;
                  $path          = 'fpis/spk/' . $nama_file;

                  if (str_starts_with($mimeType, 'image/')) {
                    $image = Image::make($base64Content)->encode($typeFile, 100);
                    Storage::disk('public')->put($path, (string) $image);
                  } elseif ($mimeType === 'application/pdf') {
                    Storage::disk('public')->put($path, $base64Content);
                  }

                  // Cek apakah item_num ini sudah ada di DB
                  $existingRecord = $existingFiles->firstWhere('item_num', $key + 1);

                  if ($existingRecord) {
                    // Hapus file lama dari storage
                    if (Storage::disk('public')->exists($existingRecord->doc_path)) {
                      Storage::disk('public')->delete($existingRecord->doc_path);
                    }

                    DB::connection("sqlSrvBCKI")
                      ->table('spk_file')
                      ->where('id_spk', $id_spk)
                      ->where('item_num', $key + 1)
                      ->update([
                        'nama_document' => $nama_file,
                        'doc_path'      => $path,
                        'item_num'      => $key + 1,
                        'type_file'     => $typeFile,
                        'dt_modified'   => now(),
                        'modified_by'   => getAuth($request)->user_id2,
                      ]);
                  } else {
                    DB::connection("sqlSrvBCKI")
                      ->table('spk_file')
                      ->insert([
                        'id_spk'        => $id_spk,
                        'nama_document' => $nama_file,
                        'doc_path'      => $path,
                        'item_num'      => $key + 1,
                        'type_file'     => $typeFile,
                        'dt_created'    => now(),
                        'dt_modified'   => '',
                        'created_by'    => getAuth($request)->user_id2,
                        'modified_by'   => ''
                      ]);
                  }

                  $requestFileNames[] = $nama_file;
                } else {
                  // ==================== FILE LAMA (URL) ====================
                  // Ekstrak nama file dari URL, contoh:
                  // "https://domain.com/storage/fpis/spk/namafile.pdf" → "namafile.pdf"
                  $nama_file = basename(parse_url($file, PHP_URL_PATH));
                  $requestFileNames[] = $nama_file;
                }
              }

              // Hapus file di DB & storage yang tidak ada di request
              foreach ($existingFiles as $existingFile) {
                if (!in_array($existingFile->nama_document, $requestFileNames)) {
                  // Hapus dari storage
                  if (Storage::disk('public')->exists($existingFile->doc_path)) {
                    Storage::disk('public')->delete($existingFile->doc_path);
                  }

                  // Hapus dari DB
                  DB::connection("sqlSrvBCKI")
                    ->table('spk_file')
                    ->where('id_spk', $id_spk)
                    ->where('nama_document', $existingFile->nama_document)
                    ->delete();
                }
              }
            }

        }
        return response()->json([
            'status' => true,
            'message' => 'Successfully Update SPK'
        ], 200);
    }

    public function saveVendor(Request $request)
    {
        $id_spk = $request->id_spk;
        $vendor_id = $request->vendor_id;

        $data = DB::connection("sqlSrvBCKI")
            ->table('spk_hdr')
            ->where('id_spk', '=', $id_spk)
            ->update([
                'pihak_2' => $vendor_id
            ]);
        
        return $this->sendResponse($data, 'Successfully update Vendor');
    }

    public function getListSpk(Request $request)
    {
        $id_site = $request->id_site ?? '';
        $id_cluster = $request->id_cluster ?? '';
        $spk_type = $request->spk_type ?? '';
        $id_house_unit = $request->id_house_unit ?? '';
        $start_date = $request->start_date ?? '';
        $end_date = $request->end_date ?? '';

        $sqlWhere = "1=1";

        if ($id_site) {

            $sqlWhere .= " and spk_hdr.id_site = '$id_site'";
        }
        if ($id_cluster) {

            $sqlWhere .= " and spk_hdr.id_cluster = '$id_cluster'";
        }
        if ($spk_type) {

            $sqlWhere .= " and spk_hdr.spk_type = '$spk_type'";
        }
        if ($id_house_unit) {

            $sqlWhere .= " and spk_hdr.id_house_unit = '$id_house_unit'";
        }

        if ($start_date) {
          if ($end_date) {
            $sqlWhere .= " AND spk_hdr.dt_created BETWEEN CONVERT(datetime, '$start_date 00:00:00.001') AND CONVERT(datetime, '$end_date 23:59:59.000')";
          } else {
            $sqlWhere .= " AND spk_hdr.dt_created BETWEEN CONVERT(datetime, '$start_date 00:00:00.001') AND CONVERT(datetime, '$start_date 23:59:59.000')";
          }
        }


        $response = DB::connection('sqlSrvBCKI')
            ->table('spk_hdr')
            ->select([
                'spk_hdr.*',
                'spk_hdr.remark as remark_spk',
                'spk_hdr.luas_bangunan as custom_lb',
                'site.site_name',
                'site.remark',
                'site_cluster.cluster_name',
                'site_cluster.remark as remark_cluster',
                'house_unit.house_name',
                'house_unit.building_area',
                'house_type.common_name',
                'house_type.sbk_name',
                'house_type.category',
                'vendor.vendor_name as nama_contractor',
                'vendor.contact_name as contact_name',
                'vendor.address1 as alamat_contractor',
                'vendor.phone as phone',
                'vendor.nik as nik',
                'vendor.business_name as business_name',
                'employee.name as nama_employee',
                'employee.jabatan as jabatan_employee',
                'employee.address1 as alamat_employee',
                'spb_hdr.id_spb',
                'qc_trans_hdr.id_qc_form',
                'qc_trans_hdr.qc_trans_id',
                // 'vlp.order_id',
                DB::raw('(Select SUM(spk_article.qty* spk_article.harga_satuan) from spk_article where spk_article.id_spk = spk_hdr.id_spk) as harga'),
            ])
            ->join('site', 'site.id_site', '=', 'spk_hdr.id_site')
            ->join('spb_hdr', 'spb_hdr.id_spb', '=', 'spk_hdr.id_spb')
            ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spk_hdr.id_cluster')
            ->join('house_unit', 'house_unit.id_house', '=', 'spk_hdr.id_house_unit')
            ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
            ->join('house_type', 'house_unit.id_house_type', '=', 'house_type.id_house_type')
            ->leftJoin('qc_trans_hdr', 'qc_trans_hdr.doc_id', '=', 'spk_hdr.id_spk')
            ->join(DB::raw('hrd_ol.dbo.employee'), 'employee.idemployee', '=', 'spk_hdr.pihak_1') // ini join ke database HRD_OL
            // ->join('v_list_penjualan as vlp', function($join) {
            //     $join->on('vlp.id_house', '=', 'spk_hdr.id_house_unit')
            //     ->where('vlp.status', '=', 'O');
            // })
            ->whereRaw($sqlWhere)
            ->get();

        foreach ($response as $item) {
            $resArticle = DB::connection('sqlSrvBCKI')
                ->table('spk_article')
                ->select([
                    'spk_article.*',
                    'article.description',
                ])
                ->join('article', 'article.article_id', '=', 'spk_article.article_id')
                ->where('type_article', 'A')
                ->where('id_spk', $item->id_spk)
                ->get();

            $item->article = $resArticle;

            $resRemarkArticle = DB::connection('sqlSrvBCKI')
                ->table('appl_remark')
                ->select([
                    'appl_remark.remarks'
                ])
                ->where('type_code', 'SPK_A')
                ->where('doc_id', $item->id_spk)
                ->first();

            if (isset($resRemarkArticle)) {
                $item->remark_article = $resRemarkArticle->remarks;
            }

            $resRemarkQC = DB::connection('sqlSrvBCKI')
                ->table('appl_remark')
                ->select([
                    'appl_remark.remarks'
                ])
                ->where('type_code', 'SPK_QC')
                ->where('doc_id', $item->id_spk)
                ->first();

            if (isset($resRemarkQC)) {
                $item->remark_qc = $resRemarkQC->remarks;
            }

            $resRemarkLain = DB::connection('sqlSrvBCKI')
                ->table('appl_remark')
                ->select([
                    'appl_remark.remarks'
                ])
                ->where('type_code', 'SPK_L')
                ->where('doc_id', $item->id_spk)
                ->first();

            $item->remarks = $resRemarkLain->remarks;
        }

        return $this->sendResponse($response, 'Successfully Get SPK');
    }

    public function getSpkAttachment(Request $request)
    {
      $id_spk = $request->id_spk;

      $response = DB::connection('sqlSrvBCKI')
        ->table('spk_file')
        ->select([
          'spk_file.nama_document as file_name',
          'spk_file.doc_path as file_path',
          'spk_file.type_file as file_type',
          'spk_file.item_num as item_num',
        ])
        ->where('id_spk', $id_spk)
        ->get()
        ->map(function ($item) use ($request) {
          $item->file_url = $request->getSchemeAndHttpHost() . '/storage/' . $item->file_path;
          return $item;
        });

      return $this->sendResponse($response, 'Successfully Get SPK Attachment');
    }

    public function getApplConstantSPK(Request $request) //project atau retail
    {
        $sqlWhere = "1=1";

        $key_code = $request->key_code;
        $str1 = $request->str1;

        if ($key_code) {
            $sqlWhere = $sqlWhere . "and key_code = '$key_code'";
        }
        if ($str1) {
            $sqlWhere = $sqlWhere . "and str1 = '$str1'";
        }

        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('appl_constant')
                ->selectRaw("*")
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get appl constant');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getApplConstantSpkType(Request $request)
    {
        $sqlWhere = " key_code = 'SPK_TYPE'";

        $typeFor = $request->typeFor;

        // if ($typeFor == 'T') {
            // $sqlWhere = $sqlWhere . "and key_code = 'SPK_TYPE' and str1 = 'T'";
        // }else{
        //     $sqlWhere = $sqlWhere . "and key_code = 'SPK_TYPE' and str1 <> 'T'";
        // }

        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('appl_constant')
                ->selectRaw("*")
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get appl constant');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function cekApproveQC(Request $request)
    {
        $qc_trans_id = $request->qc_trans_id;

        $data = DB::connection("sqlSrvBCKI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where(function($query) {
                        $query->where('stat_closing', '=', 'Y')
                            ->orWhere('stat_closing2', '=', 'Y')
                            ->orWhere('stat_closing3', '=', 'Y');
                    })
                    ->first();


        return $this->sendResponse($data, 'Successfully Cek Approve QC');
    }

    private function generateIdSPK($id_site)
    {
        $dateYear = date("Y");
        $dateYear2digit = date("y");
        $dateMonth = date("m");
        $dateDay = date("d");

        $romanMonths = [
            '01' => 'I',
            '02' => 'II',
            '03' => 'III',
            '04' => 'IV',
            '05' => 'V',
            '06' => 'VI',
            '07' => 'VII',
            '08' => 'VIII',
            '09' => 'IX',
            '10' => 'X',
            '11' => 'XI',
            '12' => 'XII',
        ];
        $romanMonth = $romanMonths[$dateMonth] ?? '';

        // Ambil nama site
        $site = DB::connection("sqlSrvBCKI")
            ->table('site')
            ->select("site_name")
            ->where('id_site', $id_site)
            ->where('stat', 'Y')
            ->first();

        $siteName = $site ? $site->site_name : 'UNKNOWN';

        // Ambil id_urut terbesar berdasarkan tahun dan bulan sekarang
        $getLastIdSPK = DB::connection('sqlSrvBCKI')
            ->table('spk_hdr')
            ->where('id_spk', 'like', "%" . $dateYear . $dateMonth . "%")
            ->max('id_urut');
        $getLastIdQC = DB::connection('sqlSrvBCKI')
            ->table('qc_trans_hdr')
            ->where('qc_trans_id', 'like', "%" . $dateYear2digit . $dateMonth . $dateDay . "%")
            ->max('qc_trans_id');

        if ($getLastIdSPK) {
            $nextUrut = (int) $getLastIdSPK + 1;
            $seq = str_pad($nextUrut, 3, '0', STR_PAD_LEFT);
            $get_id_spk = $dateYear . $dateMonth . $seq;
            $get_id_cetak = $seq . ' / PT.bcki / ' . $siteName . ' / ' . $romanMonth . ' / ' . $dateYear;
        } else {
            $get_id_spk = $dateYear . $dateMonth . '001';
            $get_id_cetak = '001' . ' / PT.bcki / ' . $siteName . ' / ' . $romanMonth . ' / ' . $dateYear;
            $nextUrut = 1;
        }
        if ($getLastIdQC) {
            $lastThree = substr($getLastIdQC, -3);
            $next_id = (int) $lastThree + 1;
            $seq = str_pad($next_id, 3, '0', STR_PAD_LEFT);
            $get_id_qc = 'T' . $dateYear2digit . $dateMonth . $dateDay . $seq;
        } else {
            $get_id_qc = 'T' . $dateYear2digit . $dateMonth . $dateDay . '001';
        }

        return [
            'id_spk' => $get_id_spk,
            'id_cetak' => $get_id_cetak,
            'id_urut' => $nextUrut,
            'qc_trans_id' => $get_id_qc
        ];
    }
    private function sendNotifSPKApproval($spkId, $getEmp, $getPhone)
    {
        try {
            $getLink = "bcki" . now()->format('ymdH') . rand(10000, 99999);
            DB::connection('sqlsrv')
            ->table('sec_reset_password')
            ->insert([
                'user_id2' => $getEmp ? $getEmp : '',
                'link' => $getLink,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $message = "*BANGUN CIPTA KARYA INDONESIA*\n\n" .
            "Silahkan lakukan Approval untuk SPK ID: {$spkId}\n";
            $url = 'https://notifapi.com/send_message';
            $data = array(
            "phone_no" => $getPhone,
            "key"      => '397bb50fd21e7c0f41b71eed483006ac45d42a47d6d43b79',
            "message"  => $message
            );

            $data_string = json_encode($data,1);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 360);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Authorization: Basic dXNtYW5ydWJpYW50b3JvcW9kcnFvZHJiZWV3b293YToyNjM3NmVkeXV3OWUwcmkzNDl1ZA=='
            ));

            $res = curl_exec($ch);
            curl_close($ch);

            if($res == 'Success' || $res == 'success' ){
            $stat = 1;
            $msg = $res;
            } else {
            $stat = 0;
            $msg = $res;
            }

            $dataRes = ['stat' => $stat, 'msg' => $msg];   
            
            return $dataRes;

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}