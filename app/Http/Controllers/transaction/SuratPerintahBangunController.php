<?php

namespace App\Http\Controllers\transaction;

use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class SuratPerintahBangunController extends Controller
{
    use getFilterTrait;

    public function index(Request $request)
    {
        try {
            $secEnv = $this->getEnvConf("TRANSACTION", "SPPJB", getAuth($request)->user_id2);

            return $this->sendResponse([
                'is_sppjb' => $secEnv,
            ], 'Data Enviroment');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getData(Request $request)
    {
        $site_id = $request->id_site;
        $cluster_id = $request->id_cluster;
        $aprv_1 = $request->aprv_1;
        $aprv_2 = $request->aprv_2;

        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                // ->join('v_list_penjualan as vlp', function ($join) {
                //   $join->on('vlp.id_house', '=', 'spb_hdr.id_house_unit')
                //     ->where('vlp.status', '=', 'O');
                // })
                ->where('spb_hdr.active_flag', 'Y')
                ->when($site_id, function ($query, $site_id) {
                    return $query->where('spb_hdr.id_site', $site_id);
                })
                ->when($cluster_id, function ($query, $cluster_id) {
                    return $query->where('spb_hdr.id_cluster', $cluster_id);
                })
                ->where('spb_hdr.dt_aprv1', $aprv_1 == true ? '!=' : '=', '1900-01-01 00:00:00.0000000')
                ->where('spb_hdr.dt_aprv2', $aprv_2 == true ? '!=' : '=', '1900-01-01 00:00:00.0000000')
                ->selectRaw("
                    spb_hdr.*,
                    site.site_name,
                    site_cluster.cluster_name,
                    house_unit.house_name
                ")
                ->get();

            return $this->sendResponse($data, 'Data SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getAllSPBForSPK(Request $request)
    {
        $typeSPK = $request->type_spk;

        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                // ->whereNotIn('spb_hdr.id_spb', function ($query) use ($typeSPK) {
                //     $query->select('id_spb')->from('spk_hdr')->where('spk_type', '=', $typeSPK);
                // })
                ->where('spb_hdr.dt_aprv1', '!=', '1900-01-01 00:00:00.0000000')
                ->where('spb_hdr.active_flag', '=', 'Y')
                ->selectRaw("
                    spb_hdr.*,
                    site.site_name,
                    site_cluster.cluster_name,
                    house_unit.house_name,
                    house_unit.building_area,
		            (site.site_name + ', ' + site_cluster.cluster_name + ', ' + house_unit.house_name) AS nama_select
                ")
                ->get();

            return $this->sendResponse($data, 'Data SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getAllSPBForSPKTambahan(Request $request)
    {
        $typeSPK = $request->type_spk;

        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                ->where('spb_hdr.active_flag', '=', 'Y')
                ->selectRaw("
                    spb_hdr.*,
                    site.site_name,
                    site_cluster.cluster_name,
                    house_unit.house_name,
                    house_unit.building_area,
		            (site.site_name + ', ' + site_cluster.cluster_name + ', ' + house_unit.house_name) AS nama_select
                ")
                ->get();

            return $this->sendResponse($data, 'Data SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function getAllSPBForSPKnoWhere()
    {
        try {
            $data = DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->join('site', 'site.id_site', '=', 'spb_hdr.id_site')
                ->join('site_cluster', 'site_cluster.id_cluster', '=', 'spb_hdr.id_cluster')
                ->join('house_unit', 'house_unit.id_house', '=', 'spb_hdr.id_house_unit')
                ->selectRaw("
                    spb_hdr.*,
                    site.site_name,
                    site_cluster.cluster_name,
                    house_unit.house_name,
                    house_unit.building_area,
		            (site.site_name + ', ' + site_cluster.cluster_name + ', ' + house_unit.house_name) AS nama_select
                ")
                ->get();

            return $this->sendResponse($data, 'Data SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function storeSPB(Request $request)
    {
        $site_id = $request->id_site ?? '';
        $cluster_id = $request->id_cluster ?? '';
        $qc_form_id = $request->id_qc_form ?? '';
        $house_unit = $request->id_house_unit ?? '';
        $start_date = $request->start_date ?? '';
        $finish_date = $request->finish_date ?? '';
        $remark = $request->remark ?? '';

        // -- generate ids
        $gen_spb = $this->generateIdSPB($site_id);
        // $gen_qc = $this->generateIdQcTrans($qc_form_id);

        // -- validated
        $isExist = DB::connection('sqlSrvBCKI')
            ->table('spb_hdr')
            ->where('id_site', $site_id)
            ->where('id_cluster', $cluster_id)
            ->where('id_house_unit', $house_unit)
            ->where('active_flag', 'Y')
            ->exists();

        if ($isExist) {
            return $this->sendError("Data ini sudah pernah dibuat", 'Error', 500);
        }

        // -- file uploads
        $fileAttachments = [];

        if ($request->hasFile('file_attachments')) {
            foreach ($request->file('file_attachments') as $key => $file) {
                if ($file && $file->isValid()) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();

                    $filename = Str::random(40) . '.' . $extension;
                    $path = 'fpis/spb';

                    // -- save file local
                    Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                    $fileAttachments[] = [
                        "id_spb" => $gen_spb,
                        "nama_document" => $originalName,
                        "doc_path" => $path,
                        "item_num" => $key + 1,
                        "dt_created" => now(),
                        "created_by" => getAuth($request)->user_id2,
                        "type_file" => $extension,
                        "doc_file" => $filename,
                        "sppjb" => "N"
                    ];
                }
            }
        }

        // -- get qc_item
        // $qcItems = DB::connection('sqlSrvBCKI')
        //     ->table('qc_item')
        //     ->where('id_qc_form', $qc_form_id)
        //     ->get()
        //     ->map(function ($item) use ($gen_qc) {
        //         return [
        //             'qc_trans_id' => $gen_qc,
        //             'id_qc_item' => $item->id_qc_item,
        //             'stat' => 'O', // Open
        //             'dt_created' => now(),
        //             'created_by' => getAuth($request)->user_id2,
        //         ];
        //     })
        //     ->toArray();

        try {
            DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->insert([
                    'id_spb' => $gen_spb,
                    'id_site' => $site_id,
                    'id_cluster' => $cluster_id,
                    'id_house_unit' => $house_unit,
                    // 'id_qc_form' => $qc_form_id,
                    'active_flag' => 'Y',
                    'status' => 'O',
                    'date_start' => $start_date,
                    'date_finish' => $finish_date,
                    'dt_created' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'remark' => $remark,
                ]);

            // -- save file uploads
            if ($fileAttachments) {
                DB::connection('sqlSrvBCKI')
                    ->table('spb_file')
                    ->insert($fileAttachments);
            }

            // -- insert qc_trans_dtl
            // if ($qcItems) {
            //     DB::connection('sqlSrvBCKI')
            //         ->table('qc_trans_hdr')
            //         ->insert([
            //             'qc_trans_id' => $gen_qc,
            //             'id_qc_form' => $qc_form_id,
            //             'id_house' => $house_unit,
            //             'dt_created' => now(),
            //             'created_by' => getAuth($request)->user_id2,
            //             'doc_type' => 'SPB',
            //             'doc_id' => $gen_spb,
            //         ]);

            //     // -- insert qc_trans_hdr
            //     DB::connection('sqlSrvBCKI')
            //         ->table('qc_trans_dtl')
            //         ->insert($qcItems);
            // }

            return $this->sendResponse('Success', 'Successfully Save SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateSPB(Request $request)
    {
        $spb_id = $request->id_spb ?? '';
        $site_id = $request->id_site ?? '';
        $cluster_id = $request->id_cluster ?? '';
        $qc_form_id = $request->id_qc_form ?? '';
        $house_unit = $request->id_house_unit ?? '';
        $start_date = $request->start_date ?? '';
        $finish_date = $request->start_date ?? '';
        $remark = $request->remark ?? '';
        $removed_file = $request->remove_attachments ?? [];

        // -- check approve
        // $dataSpb =  DB::connection('sqlSrvBCKI')
        //     ->table('spb_hdr as sh')
        //     ->join('qc_trans_hdr as qth', 'qth.doc_id', '=', 'sh.id_spb')
        //     ->where('id_spb', $spb_id)
        //     ->selectRaw("
        //         sh.*,
        //         qth.qc_trans_id
        //     ")
        //     ->first();

        // -- remove files
        $listAttch = DB::connection('sqlSrvBCKI')
            ->table('spb_file')
            ->where('id_spb', $spb_id)
            ->whereIn('doc_file', $removed_file)
            ->get();

        foreach ($listAttch as $item) {
            $filePath = $item->doc_path . '/' . $item->doc_file;

            // Hapus file jika ada
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }

        // -- enviroment, item_num
        $secEnv = $this->getEnvConf("TRANSACTION", "SPPJB", getAuth($request)->user_id2);
        $lastItemNum = DB::connection('sqlSrvBCKI')
            ->table('spb_file')
            ->where('id_spb', $spb_id)
            ->max('item_num') ?? 0;

        // -- file uploads
        $fileAttachments = [];
        if ($request->hasFile('file_attachments')) {
            foreach ($request->file('file_attachments') as $key => $file) {
                if ($file && $file->isValid()) {
                    $extension = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();

                    $filename = Str::random(40) . '.' . $extension;
                    $path = 'fpis/spb';

                    // -- save file local
                    Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                    $fileAttachments[] = [
                        "id_spb" => $spb_id,
                        "nama_document" => $originalName,
                        "doc_path" => $path,
                        "item_num" => $lastItemNum + $key + 1,
                        "dt_created" => now(),
                        "created_by" => getAuth($request)->user_id2,
                        "type_file" => $extension,
                        "doc_file" => $filename,
                        "sppjb" => $secEnv == "Y" ? "Y" : "N"
                    ];
                }
            }
        }

        $dataUpdate = [
            'id_site' => $site_id,
            'id_cluster' => $cluster_id,
            'id_house_unit' => $house_unit,
            'active_flag' => 'Y',
            'status' => 'O',
            'date_start' => $start_date,
            'date_finish' => $finish_date,
            'dt_modified' => now(),
            'modified_by' => getAuth($request)->user_id2,
            'remark' => $remark,
        ];

        try {
            // -- not approved
            // if (!$dataSpb->aprv1_by && !$dataSpb->aprv2_by) {
            //     $dataUpdate['id_qc_form'] = $qc_form_id;
            // }

            DB::connection('sqlSrvBCKI')
                ->table('spb_hdr')
                ->where('id_spb', $spb_id)
                ->update($dataUpdate);

            // -- remove file uploads
            DB::connection('sqlSrvBCKI')
                ->table('spb_file')
                ->where('id_spb', $spb_id)
                ->whereIn('doc_file', $removed_file)
                ->delete();

            // -- save file uploads
            if ($fileAttachments) {
                DB::connection('sqlSrvBCKI')
                    ->table('spb_file')
                    ->insert($fileAttachments);
            }

            // -- qc_trans not approved
            // if (!$dataSpb->aprv1_by && !$dataSpb->aprv2_by) {
            //     // -- get qc_item
            //     $qcItems = DB::connection('sqlSrvBCKI')
            //         ->table('qc_item')
            //         ->where('id_qc_form', $qc_form_id)
            //         ->get()
            //         ->map(function ($item) use ($dataSpb) {
            //             return [
            //                 'qc_trans_id' => $dataSpb->qc_trans_id,
            //                 'id_qc_item' => $item->id_qc_item,
            //                 'stat' => 'O', // Open
            //                 'dt_created' => now(),
            //                 'created_by' => getAuth($request)->user_id2,
            //             ];
            //         })
            //         ->toArray();

            //     // -- update qc_trans_hdr
            //     DB::connection('sqlSrvBCKI')
            //         ->table('qc_trans_hdr')
            //         ->where('doc_id', $spb_id)
            //         ->update([
            //             'id_qc_form' => $qc_form_id,
            //             'id_house' => $house_unit,
            //             'dt_modified' => now(),
            //             'modified_by' => getAuth($request)->user_id2,
            //         ]);

            //     // -- update qc_trans_dtl
            //     DB::connection('sqlSrvBCKI')
            //         ->table('qc_trans_dtl')
            //         ->where('qc_trans_id', $dataSpb->qc_trans_id)
            //         ->delete();

            //     // -- insert qc_trans_dtl
            //     if ($qcItems) {
            //         DB::connection('sqlSrvBCKI')
            //             ->table('qc_trans_dtl')
            //             ->insert($qcItems);
            //     }
            // }

            return $this->sendResponse('Success', 'Successfully Update SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getSpbAttachment(Request $request)
    {
        $spb_id = $request->spb_id;

        try {
            $result = DB::connection('sqlSrvBCKI')
                ->table('spb_file')
                ->where('id_spb', $spb_id)
                ->selectRaw("
                    nama_document,
                    doc_path,
                    type_file,
                    doc_file,
                    sppjb
                ")
                ->orderBy("item_num")
                ->get()
                ->map(function ($item) {
                    $item->file_hash = $item->doc_file;
                    $item->doc_file = url("/storage/{$item->doc_path}/{$item->doc_file}");
                    return $item;
                });

            return $this->sendResponse($result, 'Successfully Update SPB');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function generateIdSPB()
    {
        $dateYear = date("Y");
        $dateMonth = date("m");

        // Ambil id_urut terbesar berdasarkan tahun dan bulan sekarang
        $getLastTrID = DB::connection('sqlSrvBCKI')
            ->table('spb_hdr')
            ->where('id_spb', 'like', "%" . $dateYear . $dateMonth . "%")
            ->max('id_spb');


        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -3) + 1;
            $get_id_spb = $dateYear . $dateMonth . str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $get_id_spb = $dateYear . $dateMonth . '001';
        }

        return $get_id_spb;
    }

    private function generateIdQcTrans()
    {
        $dateNow = date("ymd");
        $genId = "T{$dateNow}001";

        $lastId = DB::connection('sqlSrvBCKI')
            ->table('qc_trans_hdr')
            ->where('qc_trans_id', 'like', "%T$dateNow%")
            ->max('qc_trans_id');

        if ($lastId) {
            $seq = substr(trim($lastId), -3) + 1;
            $genId = "T{$dateNow}" . str_pad($seq, 3, '0', STR_PAD_LEFT);
        }

        return $genId;
    }
}
