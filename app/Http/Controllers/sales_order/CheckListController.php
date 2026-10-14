<?php

namespace App\Http\Controllers\sales_order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CheckListController extends Controller
{

    public function getCheckList(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_site = $request->id_site;
            $id_cluster = $request->id_cluster;
            $id_house = $request->id_house;
            $cl_type = $request->cl_type;

            if (!empty($id_house)) {
                $sqlWhere = $sqlWhere . " and qc_trans_hdr.id_house = " . "'" . $id_house . "'";
            } else {
                return $this->sendError("House ID Kosong", 'Error', 500);
            }
            if (!empty($cl_type)) {
                $sqlWhere = $sqlWhere . " and qc_form.qc_type = " . "'" . $cl_type . "'";
            } else {
                return $this->sendError("Type Kosong", 'Error', 500);
            }
            // dd($sqlWhere);

            $datas = DB::connection("sqlSrvFPI")
                ->table('qc_trans_dtl')
                ->selectRaw("
                                qc_trans_dtl.qc_trans_id, 
                                qc_trans_dtl.id_qc_item, 
                                qc_item.qc_item, 
                                qc_trans_dtl.stat, 
                                qc_item.com_id, 
                                commodity.com_desc, 
                                site_cluster.cluster_name, 
                                site.site_name, 
                                house_unit.house_name, 
                                qc_trans_dtl.dt_aprv, 
                                qc_trans_dtl.aprv_by
                            ")
                ->leftjoin('qc_trans_hdr', 'qc_trans_dtl.qc_trans_id', '=', 'qc_trans_hdr.qc_trans_id')
                ->leftjoin('qc_item', 'qc_trans_dtl.id_qc_item', '=', 'qc_item.id_qc_item')
                ->leftjoin('house_unit', 'qc_trans_hdr.id_house', '=', 'house_unit.id_house')
                ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
                ->leftjoin('site', 'house_unit.id_site', '=', 'site.id_site')
                ->leftJoin('commodity', function ($join) {
                    $join->on('qc_item.com_id', '=', 'commodity.com_id')
                        ->on('qc_item.cat_id', '=', 'commodity.cat_id');
                })
                ->leftjoin('qc_form', 'qc_trans_hdr.id_qc_form', '=', 'qc_form.id_qc_form')
                ->whereRaw($sqlWhere)
                ->orderBy('qc_trans_dtl.id_qc_item', 'asc')
                ->get();
            return $this->sendResponse($datas, 'Successfully get Check List Data');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /* public function aprvCheckList(Request $request)
    {
        // $houseUnitId = $request->id_house;
        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;
        $image = $request->file('img');

        // dd($image);
        
        if (empty($qc_trans_id)) {
            return $this->sendError("QC Trx ID Kosong", 'Error', 500);
        }
        if (empty($id_qc_item)) {
            return $this->sendError("QC Item ID Kosong", 'Error', 500);
        }
        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }

        try {
            if($id_work == '1' || $id_work == 1){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->value('dt_aprv');
                    
                if ($cekAprv !== '1900-01-01 00:00:00.000') {
                    $linkGambar = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->value('img_link');
                    
                    if ($linkGambar) {
                        Storage::disk('public')->delete($linkGambar);
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark' => '',
                            'dt_aprv' => '',
                            'aprv_by' => '',
                            'img_link' => ''
                        ]);
    
                    return $this->sendResponse('Success Update', 'Successfully unapprove');
                } else {
                    if ($image) {
                        $directory = 'fpi-check-list/'; // Direktori penyimpanan
        
                        $filename2 = Str::random(40) . '.jpg'; // Nama file hasil compress
                        $path2 = $directory . $filename2;
        
                        // Kompres gambar
                        $quality = 90;
                        $maxSize = 1000 * 1024;
        
                        // Resize untuk efisiensi (opsional tapi direkomendasikan)
                        $imageInstance = Image::make($image);
                        $imageInstance->resize(1280, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
        
                        do {
                            $medium2 = (clone $imageInstance)->encode('jpg', $quality);
                            $imageSize = strlen((string) $medium2);
                            $quality -= 5;
                        } while ($imageSize > $maxSize && $quality > 10);
        
                        // Simpan kedua gambar ke storage
                        Storage::disk('public')->put($path2, (string) $medium2); // Compressed
                    } else {
                        $path2 = '';
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark' => $request->remark ?? '',
                            'dt_aprv' => now(),
                            'aprv_by' => getAuth($request)->user_id2,
                            'img_link' => $path2
                        ]);
                    
                    return $this->sendResponse('Success Update', 'Successfully approve');
                }
            }elseif($id_work == '2' || $id_work == 2){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->value('dt_aprv2');
                    
                if ($cekAprv !== '1900-01-01 00:00:00.000') {
                    $linkGambar = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->value('img_link');
                    
                    if ($linkGambar) {
                        Storage::disk('public')->delete($linkGambar);
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark2' => '',
                            'dt_aprv2' => '',
                            'aprv2_by' => '',
                            'img_link2' => ''
                        ]);
    
                    return $this->sendResponse('Success Update', 'Successfully unapprove 2');
                } else {
                    if ($image) {
                        $directory = 'fpi-check-list/'; // Direktori penyimpanan
        
                        $filename2 = Str::random(40) . '.jpg'; // Nama file hasil compress
                        $path2 = $directory . $filename2;
        
                        // Kompres gambar
                        $quality = 90;
                        $maxSize = 1000 * 1024;
        
                        // Resize untuk efisiensi (opsional tapi direkomendasikan)
                        $imageInstance = Image::make($image);
                        $imageInstance->resize(1280, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
        
                        do {
                            $medium2 = (clone $imageInstance)->encode('jpg', $quality);
                            $imageSize = strlen((string) $medium2);
                            $quality -= 5;
                        } while ($imageSize > $maxSize && $quality > 10);
        
                        // Simpan kedua gambar ke storage
                        Storage::disk('public')->put($path2, (string) $medium2); // Compressed
                    } else {
                        $path2 = '';
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark2' => $request->remark ?? '',
                            'dt_aprv2' => now(),
                            'aprv2_by' => getAuth($request)->user_id2,
                            'img_link2' => $path2
                        ]);
                    
                    return $this->sendResponse('Success Update', 'Successfully approve 2');
                }
            }elseif($id_work == '3' || $id_work == 3){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->value('dt_aprv3');
                    
                if ($cekAprv !== '1900-01-01 00:00:00.000') {
                    $linkGambar = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->value('img_link');
                    
                    if ($linkGambar) {
                        Storage::disk('public')->delete($linkGambar);
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark3' => '',
                            'dt_aprv3' => '',
                            'aprv3_by' => '',
                            'img_link3' => ''
                        ]);
    
                    return $this->sendResponse('Success Update', 'Successfully unapprove 3');
                } else {
                    if ($image) {
                        $directory = 'fpi-check-list/'; // Direktori penyimpanan
        
                        $filename2 = Str::random(40) . '.jpg'; // Nama file hasil compress
                        $path2 = $directory . $filename2;
        
                        // Kompres gambar
                        $quality = 90;
                        $maxSize = 1000 * 1024;
        
                        // Resize untuk efisiensi (opsional tapi direkomendasikan)
                        $imageInstance = Image::make($image);
                        $imageInstance->resize(1280, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
        
                        do {
                            $medium2 = (clone $imageInstance)->encode('jpg', $quality);
                            $imageSize = strlen((string) $medium2);
                            $quality -= 5;
                        } while ($imageSize > $maxSize && $quality > 10);
        
                        // Simpan kedua gambar ke storage
                        Storage::disk('public')->put($path2, (string) $medium2); // Compressed
                    } else {
                        $path2 = '';
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark3' => $request->remark ?? '',
                            'dt_aprv3' => now(),
                            'aprv3_by' => getAuth($request)->user_id2,
                            'img_link3' => $path2
                        ]);
                    
                    return $this->sendResponse('Success Update', 'Successfully approve 3');
                }
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }*/

    public function aprvCheckList(Request $request)
    {
        // $houseUnitId = $request->id_house;
        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;
        // $image = $request->file('img');

        // dd($image);
        
        if (empty($qc_trans_id)) {
            return $this->sendError("QC Trx ID Kosong", 'Error', 500);
        }
        if (empty($id_qc_item)) {
            return $this->sendError("QC Item ID Kosong", 'Error', 500);
        }
        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }

        try {
            if($id_work == '1' || $id_work == 1){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv, 'yyyy-MM-dd') as dt_aprv")
                    ->value('dt_aprv');
                    
                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 1", 'Error', 500);
                } else {
                    // -- file uploads
                    $img = [];

                    if ($request->hasFile('img')) {
                        foreach ($request->file('img') as $key => $file) {
                            if ($file && $file->isValid()) {
                                $extension = $file->getClientOriginalExtension();
                                $originalName = $file->getClientOriginalName();

                                $filename = Str::random(40) . '.' . $extension;
                                $path = 'fpi-check-list/';

                                // -- save file local
                                Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                                $img[] = [
                                    "qc_trans_id" => $qc_trans_id,
                                    "id_qc_item" => $id_qc_item,
                                    "item_num" => $key + 1,
                                    "doc_name" => $filename,
                                    "doc_path" => $path,
                                    "dt_created" => now(),
                                    "created_by" => getAuth($request)->user_id2,
                                    "file_name" => $originalName,
                                    "id_work" => 1
                                ];
                            }
                        }
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark' => $request->remark ?? '',
                            'dt_aprv' => now(),
                            'aprv_by' => getAuth($request)->user_id2,
                            'longitude1' => $request->longitude ?? '',
                            'latitude1' => $request->latitude ?? ''
                        ]);

                    if ($img) {
                        DB::connection('sqlSrvFPI')
                            ->table('qc_trans_doc')
                            ->insert($img);
                    }
                    
                    return $this->sendResponse('Success Update', 'Successfully approve 1');
                }
            }elseif($id_work == '2' || $id_work == 2){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv2, 'yyyy-MM-dd') as dt_aprv2")
                    ->value('dt_aprv2');
                    
                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 2", 'Error', 500);
                } else {
                    // -- file uploads
                    $img = [];

                    if ($request->hasFile('img')) {
                        foreach ($request->file('img') as $key => $file) {
                            if ($file && $file->isValid()) {
                                $extension = $file->getClientOriginalExtension();
                                $originalName = $file->getClientOriginalName();

                                $filename = Str::random(40) . '.' . $extension;
                                $path = 'fpi-check-list/';

                                // -- save file local
                                Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                                $img[] = [
                                    "qc_trans_id" => $qc_trans_id,
                                    "id_qc_item" => $id_qc_item,
                                    "item_num" => $key + 1,
                                    "doc_name" => $filename,
                                    "doc_path" => $path,
                                    "dt_created" => now(),
                                    "created_by" => getAuth($request)->user_id2,
                                    "file_name" => $originalName,
                                    "id_work" => 2
                                ];
                            }
                        }
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark2' => $request->remark ?? '',
                            'dt_aprv2' => now(),
                            'aprv2_by' => getAuth($request)->user_id2,
                            'longitude2' => $request->longitude ?? '',
                            'latitude2' => $request->latitude ?? ''
                        ]);

                    if ($img) {
                        DB::connection('sqlSrvFPI')
                            ->table('qc_trans_doc')
                            ->insert($img);
                    }
                    
                    return $this->sendResponse('Success Update', 'Successfully approve 2');
                }
            }elseif($id_work == '3' || $id_work == 3){
                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv3, 'yyyy-MM-dd') as dt_aprv3")
                    ->value('dt_aprv3');
                    
                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 3", 'Error', 500);
                } else {
                    // -- file uploads
                    $img = [];

                    if ($request->hasFile('img')) {
                        foreach ($request->file('img') as $key => $file) {
                            if ($file && $file->isValid()) {
                                $extension = $file->getClientOriginalExtension();
                                $originalName = $file->getClientOriginalName();

                                $filename = Str::random(40) . '.' . $extension;
                                $path = 'fpi-check-list/';

                                // -- save file local
                                Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                                $img[] = [
                                    "qc_trans_id" => $qc_trans_id,
                                    "id_qc_item" => $id_qc_item,
                                    "item_num" => $key + 1,
                                    "doc_name" => $filename,
                                    "doc_path" => $path,
                                    "dt_created" => now(),
                                    "created_by" => getAuth($request)->user_id2,
                                    "file_name" => $originalName,
                                    "id_work" => 3
                                ];
                            }
                        }
                    }
    
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_dtl')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->update([
                            'remark3' => $request->remark ?? '',
                            'dt_aprv3' => now(),
                            'aprv3_by' => getAuth($request)->user_id2,
                            'longitude3' => $request->longitude ?? '',
                            'latitude3' => $request->latitude ?? ''
                        ]);

                    if ($img) {
                        DB::connection('sqlSrvFPI')
                            ->table('qc_trans_doc')
                            ->insert($img);
                    }
                    
                    return $this->sendResponse('Success Update', 'Successfully approve 3');
                }
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    

    public function getCheckListHdr(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_site = $request->id_site;
            $id_cluster = $request->id_cluster;
            $id_house_item = $request->id_house_item;
            $vendor_id = $request->vendor_id;
            $active_flag = $request->active_flag;
            $doc_type = $request->doc_type;



            if (!empty($doc_type)) {
                if ($doc_type == 'SPK') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and spk_hdr.id_site = " . "'" . $id_site . "'";
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and spk_hdr.id_cluster = " . "'" . $id_cluster . "'";
                    }
                    if (!empty($id_house_item)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.id_house = " . "'" . $id_house_item . "'";
                    }
                    if (!empty($vendor_id)) {
                        $sqlWhere = $sqlWhere . " and spk_hdr.pihak_2 = " . "'" . $vendor_id . "'";
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = 'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_hdr')
                        ->selectRaw("
                            spk_hdr.id_site, 
                            site.site_name, 
                            spk_hdr.id_cluster, 
                            site_cluster.cluster_name, 
                            spk_hdr.id_house_unit as id_house, 
                            house_unit.house_name, 
                            spk_hdr.id_spk, 
                            spk_hdr.pihak_2, 
                            TRIM(vendor.vendor_name) as vendor_name,
                            qc_trans_hdr.qc_trans_id,
                            spk_hdr.spk_type,
                            appl_constant.str2 as spk_label,
                            spk_hdr.remark
                        ")
                        ->leftJoin('spk_hdr', 'qc_trans_hdr.doc_id', '=', 'spk_hdr.id_spk')
                        ->leftJoin('site', 'spk_hdr.id_site', '=', 'site.id_site')
                        ->leftJoin('site_cluster', 'spk_hdr.id_cluster', '=', 'site_cluster.id_cluster')
                        ->leftJoin('house_unit', 'spk_hdr.id_house_unit', '=', 'house_unit.id_house')
                        ->leftJoin('vendor', 'spk_hdr.pihak_2', '=', 'vendor.vendor_id')
                        ->leftJoin('appl_constant', function ($join) {
                            $join->on('spk_hdr.spk_type', '=', 'appl_constant.str1')
                                ->where('appl_constant.key_code', '=', 'SPK_TYPE');
                        })
                        ->whereRaw($sqlWhere)
                        ->orderBy('qc_trans_hdr.qc_trans_id', 'asc')
                        ->get();

                    return $this->sendResponse($datas, 'Successfully get Check List Data');
                } elseif ($doc_type == 'SPR') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and v_penjualan_payment.id_site = " . "'" . $id_site . "'";
                    } else {
                        return $this->sendError("Site ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and v_penjualan_payment.id_cluster = " . "'" . $id_cluster . "'";
                    } else {
                        // return $this->sendError("Cluster ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_house_item)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.id_house = " . "'" . $id_house_item . "'";
                    } else {
                        // return $this->sendError("House ID Kosong", 'Error', 500);
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = " . "'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_hdr')
                        ->selectRaw("
                                        v_penjualan_payment.id_site, 
                                        v_penjualan_payment.site_name, 
                                        v_penjualan_payment.id_cluster, 
                                        v_penjualan_payment.cluster_name, 
                                        v_penjualan_payment.id_house, 
                                        v_penjualan_payment.house_name, 
                                        qc_trans_hdr.doc_id, 
                                        qc_trans_hdr.qc_trans_id
                                    ")
                        ->leftjoin('v_penjualan_payment', 'qc_trans_hdr.doc_id', '=', 'v_penjualan_payment.order_id')
                        ->whereRaw($sqlWhere)
                        ->where('doc_type','SPR')
                        ->orderBy('qc_trans_hdr.qc_trans_id', 'asc')
                        ->get();
                    return $this->sendResponse($datas, 'Successfully get Check List Data');
                } elseif ($doc_type == 'KK') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and v_penjualan_payment.id_site = " . "'" . $id_site . "'";
                    } else {
                        return $this->sendError("Site ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and v_penjualan_payment.id_cluster = " . "'" . $id_cluster . "'";
                    } else {
                        // return $this->sendError("Cluster ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_house_item)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.id_house = " . "'" . $id_house_item . "'";
                    } else {
                        // return $this->sendError("House ID Kosong", 'Error', 500);
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and qc_trans_hdr.active_flag = " . "'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                        ->table('qc_trans_hdr')
                        ->selectRaw("
                                        v_penjualan_payment.id_site, 
                                        v_penjualan_payment.site_name, 
                                        v_penjualan_payment.id_cluster, 
                                        v_penjualan_payment.cluster_name, 
                                        v_penjualan_payment.id_house, 
                                        v_penjualan_payment.house_name, 
                                        qc_trans_hdr.doc_id, 
                                        qc_trans_hdr.qc_trans_id
                                    ")
                        ->leftjoin('v_penjualan_payment', 'qc_trans_hdr.doc_id', '=', 'v_penjualan_payment.order_id')
                        ->whereRaw($sqlWhere)
                        ->where('doc_type','KK')
                        ->whereDate('qc_trans_hdr.dt_finish', '1900-01-01')
                        ->orderBy('qc_trans_hdr.qc_trans_id', 'asc')
                        ->get();
                    return $this->sendResponse($datas, 'Successfully get Check List Data');
                } 
            } else {
                return $this->sendError("Type Kosong", 'Error', 500);
            }
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getCheckListDtl(Request $request)
{
    try {
        $sqlWhere = "1=1";

        $qc_trans_id = $request->qc_trans_id;

        if (!empty($qc_trans_id)) {
            $sqlWhere = $sqlWhere . " and qc_trans_dtl.qc_trans_id = '" . $qc_trans_id . "'";
        } else {
            return $this->sendError("Trx ID Kosong", 'Error', 500);
        }

        // 🔥 Ambil semua attachment, group by id_qc_item lalu id_work
        $allImages = DB::connection("sqlSrvFPI")
            ->table('qc_trans_doc')
            ->where('qc_trans_id', $qc_trans_id)
            ->get()
            ->groupBy('id_qc_item');

        $datas = DB::connection("sqlSrvFPI")
            ->table('qc_trans_dtl')
            ->selectRaw("
                qc_trans_dtl.qc_trans_id,
                qc_trans_dtl.id_qc_item,
                qc_item.qc_item,
                qc_trans_dtl.stat,
                qc_item.com_id,
                commodity.com_desc,
                site_cluster.cluster_name,
                site.site_name,
                house_unit.house_name,
                qc_trans_dtl.dt_aprv,
                qc_trans_dtl.aprv_by
            ")
            ->leftJoin('qc_trans_hdr', 'qc_trans_dtl.qc_trans_id', '=', 'qc_trans_hdr.qc_trans_id')
            ->leftJoin('qc_item', 'qc_trans_dtl.id_qc_item', '=', 'qc_item.id_qc_item')
            ->leftJoin('house_unit', 'qc_trans_hdr.id_house', '=', 'house_unit.id_house')
            ->leftJoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
            ->leftJoin('site', 'house_unit.id_site', '=', 'site.id_site')
            ->leftJoin('commodity', function ($join) {
                $join->on('qc_item.com_id', '=', 'commodity.com_id')
                     ->on('qc_item.cat_id', '=', 'commodity.cat_id');
            })
            ->leftJoin('qc_form', 'qc_trans_hdr.id_qc_form', '=', 'qc_form.id_qc_form')
            ->whereRaw($sqlWhere)
            ->orderBy('qc_trans_dtl.id_qc_item', 'asc')
            ->get()
            ->map(function ($item) use ($allImages) {
                $images = $allImages[$item->id_qc_item] ?? collect();

                // 🔥 Group by id_work, tiap work berisi array URL gambar
                $item->attachments = $images
                    ->groupBy('id_work')
                    ->map(function ($workImages, $id_work) {
                        return [
                            'id_work' => (int) $id_work,
                            'images'  => $workImages->map(function ($img) {
                                return url("/storage/" . $img->doc_path . $img->doc_name);
                            })->values()->toArray(),
                        ];
                    })
                    ->values()
                    ->toArray();

                return $item;
            });

        return $this->sendResponse($datas, 'Successfully get Check List Data');

    } catch (\Exception $e) {
        return $this->sendError($e->getMessage(), 'Error', 500);
    }
}

    // public function getCheckListDtl(Request $request)
    // {
    //     try {
    //         $sqlWhere = "1=1";

    //         $qc_trans_id = $request->qc_trans_id;

    //         if (!empty($qc_trans_id)) {
    //             $sqlWhere = $sqlWhere . " and qc_trans_dtl.qc_trans_id = " . "'" . $qc_trans_id . "'";
    //         } else {
    //             return $this->sendError("Trx ID Kosong", 'Error', 500);
    //         }
    //         // dd($sqlWhere);

    //         $datas = DB::connection("sqlSrvFPI")
    //             ->table('qc_trans_dtl')
    //             ->selectRaw("
    //                             qc_trans_dtl.qc_trans_id, 
    //                             qc_trans_dtl.id_qc_item, 
    //                             qc_item.qc_item, 
    //                             qc_trans_dtl.stat, 
    //                             qc_item.com_id, 
    //                             commodity.com_desc, 
    //                             site_cluster.cluster_name, 
    //                             site.site_name, 
    //                             house_unit.house_name, 
    //                             qc_trans_dtl.dt_aprv, 
    //                             qc_trans_dtl.aprv_by,
    //                             qc_trans_dtl.img_link
    //                         ")
    //             ->leftjoin('qc_trans_hdr', 'qc_trans_dtl.qc_trans_id', '=', 'qc_trans_hdr.qc_trans_id')
    //             ->leftjoin('qc_item', 'qc_trans_dtl.id_qc_item', '=', 'qc_item.id_qc_item')
    //             ->leftjoin('house_unit', 'qc_trans_hdr.id_house', '=', 'house_unit.id_house')
    //             ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
    //             ->leftjoin('site', 'house_unit.id_site', '=', 'site.id_site')
    //             ->leftJoin('commodity', function ($join) {
    //                 $join->on('qc_item.com_id', '=', 'commodity.com_id')
    //                     ->on('qc_item.cat_id', '=', 'commodity.cat_id');
    //             })
    //             ->leftjoin('qc_form', 'qc_trans_hdr.id_qc_form', '=', 'qc_form.id_qc_form')

    //             // 🔥 JOIN ke qc_trans_doc
    //             ->leftJoin('qc_trans_doc', function ($join) {
    //                 $join->on('qc_trans_dtl.qc_trans_id', '=', 'qc_trans_doc.qc_trans_id')
    //                     ->on('qc_trans_dtl.id_qc_item', '=', 'qc_trans_doc.id_qc_item');
    //             })
                
    //             ->whereRaw($sqlWhere)
    //             ->orderBy('qc_trans_dtl.id_qc_item', 'asc')
    //             ->get()
    //             ->map(function ($item) {
    //                 $item->images = url("/storage/{$item->img_link}");

                   
    //                 return $item;
    //             });
    //         return $this->sendResponse($datas, 'Successfully get Check List Data');
    //     } catch (\Exception $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

//    public function getCheckListDtl(Request $request)
//     {
//         try {
//             $sqlWhere = "1=1";

//             $qc_trans_id = $request->qc_trans_id;

//             if (!empty($qc_trans_id)) {
//                 $sqlWhere = $sqlWhere . " and qc_trans_dtl.qc_trans_id = " . "'" . $qc_trans_id . "'";
//             } else {
//                 return $this->sendError("Trx ID Kosong", 'Error', 500);
//             }

//             $datas = DB::connection("sqlSrvFPI")
//                 ->table('qc_trans_dtl')
//                 ->selectRaw("
//                     qc_trans_dtl.qc_trans_id, 
//                     qc_trans_dtl.id_qc_item, 
//                     qc_item.qc_item, 
//                     qc_trans_dtl.stat, 
//                     qc_item.com_id, 
//                     commodity.com_desc, 
//                     site_cluster.cluster_name, 
//                     site.site_name, 
//                     house_unit.house_name, 
//                     qc_trans_dtl.dt_aprv, 
//                     qc_trans_dtl.aprv_by
//                 ")
//                 ->leftjoin('qc_trans_hdr', 'qc_trans_dtl.qc_trans_id', '=', 'qc_trans_hdr.qc_trans_id')
//                 ->leftjoin('qc_item', 'qc_trans_dtl.id_qc_item', '=', 'qc_item.id_qc_item')
//                 ->leftjoin('house_unit', 'qc_trans_hdr.id_house', '=', 'house_unit.id_house')
//                 ->leftjoin('site_cluster', 'house_unit.id_cluster', '=', 'site_cluster.id_cluster')
//                 ->leftjoin('site', 'house_unit.id_site', '=', 'site.id_site')
//                 ->leftJoin('commodity', function ($join) {
//                     $join->on('qc_item.com_id', '=', 'commodity.com_id')
//                         ->on('qc_item.cat_id', '=', 'commodity.cat_id');
//                 })
//                 ->leftjoin('qc_form', 'qc_trans_hdr.id_qc_form', '=', 'qc_form.id_qc_form')
//                 ->whereRaw($sqlWhere)
//                 ->orderBy('qc_trans_dtl.id_qc_item', 'asc')
//                 ->get()
//                 ->map(function ($item) {

//                     // 🔥 ambil semua gambar dari qc_trans_doc
//                     $images = DB::connection("sqlSrvFPI")
//                         ->table('qc_trans_doc')
//                         ->where('qc_trans_id', $item->qc_trans_id)
//                         ->where('id_qc_item', $item->id_qc_item)
//                         ->get();

//                     // 🔥 mapping per work
//                     $item->img_link = $images->where('id_work', 1)->map(function ($img) {
//                         return url("/storage/" . $img->doc_path . $img->doc_name);
//                     })->values();

//                     $item->img_link2 = $images->where('id_work', 2)->map(function ($img) {
//                         return url("/storage/" . $img->doc_path . $img->doc_name);
//                     })->values();

//                     $item->img_link3 = $images->where('id_work', 3)->map(function ($img) {
//                         return url("/storage/" . $img->doc_path . $img->doc_name);
//                     })->values();

//                     return $item;
//                 });

//             return $this->sendResponse($datas, 'Successfully get Check List Data');

//         } catch (\Exception $e) {
//             return $this->sendError($e->getMessage(), 'Error', 500);
//         }
//     }

    public function getSpkHdr(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house_item = $request->id_house_item;
        $doc_type = $request->doc_type;

        if (empty($id_site)) {
            return $this->sendError("Site ID Kosong", 'Error', 500);
        }
        if (empty($id_cluster)) {
            return $this->sendError("Cluster ID Kosong", 'Error', 500);
        }
        if (empty($id_house_item)) {
            return $this->sendError("House ID Kosong", 'Error', 500);
        }
        if (empty($doc_type)) {
            return $this->sendError("Check List Type Kosong", 'Error', 500);
        }

        if($doc_type=='SPK'){
            $sqlWhere = $sqlWhere . " and sh.id_site = " . "'" . $id_site . "'" . " and sh.id_cluster = " . "'" . $id_cluster . "'";
        }elseif($doc_type=='SPR'){
            $sqlWhere = $sqlWhere . " and vlp.id_site = " . "'" . $id_site . "'" . " and vlp.id_cluster = " . "'" . $id_cluster . "'";
        }

        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('qc_trans_hdr as qth')
                ->leftjoin('spk_hdr as sh', function ($e) {
                        $e->on('qth.doc_id', '=', 'sh.id_spk')
                            ->where('qth.doc_type', 'SPK');
                    })
                ->leftjoin('v_list_penjualan as vlp', function ($e) {
                        $e->on('qth.doc_id', '=', 'vlp.order_id')
                            ->where('qth.doc_type', 'SPR');
                    })
                    ->selectRaw("
                    qth.qc_trans_id,
                    qth.id_qc_form,
                    qth.id_house,
                    
                    vlp.house_name,
                    vlp.id_site,
                    vlp.site_name,
                    vlp.id_cluster,
                    vlp.cluster_name,
                    
                    qth.doc_type,
                    qth.doc_id
                    ")
                ->when($id_house_item, function ($query, $id_house_item) {
                    return $query->where('qth.id_house', $id_house_item);
                })
                ->when($doc_type, function ($query, $doc_type) {
                    return $query->where('qth.doc_type', $doc_type);
                })
                // ->when($id_site, function ($query, $id_site) {
                //     return $query->where('vlp.id_site', $id_site);
                // })
                // ->when($id_cluster, function ($query, $id_cluster) {
                //     return $query->where('vlp.id_cluster', $id_cluster);
                // })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($result, 'Successfully get hdr');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    // public function getQcTrans(Request $request)
    // {
    //     $qc_trans_id = $request->qc_trans_id;

    //     try {

    //         // 🔥 ambil semua gambar (group by item)
    //         $allImages = DB::connection("sqlSrvFPI")
    //             ->table('qc_trans_doc')
    //             ->where('qc_trans_id', $qc_trans_id)
    //             ->get()
    //             ->groupBy('id_qc_item');

    //         $result =  DB::connection("sqlSrvFPI")
    //             ->table('qc_trans_dtl as qtd')
    //             ->join('qc_trans_hdr as qth', 'qtd.qc_trans_id', '=', 'qth.qc_trans_id')
    //             ->join('qc_item as qi', 'qtd.id_qc_item', '=', 'qi.id_qc_item')
    //             ->join('qc_item_commodity as com', 'qi.com_id', '=', 'com.com_id')
    //             ->leftJoin('qc_item_category as cat', 'qi.cat_id', '=', 'cat.cat_id')
    //             ->where('qth.qc_trans_id', $qc_trans_id)
    //             ->selectRaw("
    //                 qtd.id_qc_item,
    //                 qtd.dt_aprv,
    //                 qtd.dt_aprv2,
    //                 qtd.dt_aprv3,
    //                 qtd.stat_closing,
    //                 qtd.stat_closing2,
    //                 qtd.stat_closing3,
    //                 qi.qc_item,
    //                 qi.qc_item_html,
    //                 qi.com_id,
    //                 qi.cat_id,
    //                 com.com_desc,
    //                 trim(cat.descr) as descr
    //             ")
    //             ->get()
    //             ->groupBy('com_id')
    //             ->map(function ($items, $com_id) use ($allImages) {

    //                 $catGroups = $items->groupBy('cat_id')->map(function ($catItems, $cat_id) use ($allImages) {

    //                     return [
    //                         'cat_id' => $cat_id,
    //                         'cat_name' => $catItems->first()->descr ?? '',
    //                         'items' => $catItems->map(function ($item) use ($allImages) {

    //                             // 🔥 ambil semua image item ini
    //                             $images = $allImages[$item->id_qc_item] ?? collect();

    //                             // 🔥 ambil masing-masing work (1,2,3)
    //                             $img1 = $images->firstWhere('item_num', 1);
    //                             $img2 = $images->firstWhere('item_num', 2);
    //                             $img3 = $images->firstWhere('item_num', 3);

    //                             return [
    //                                 'id_qc_item' => $item->id_qc_item,
    //                                 'item_name' => $item->qc_item,
    //                                 'item_name_html' => $item->qc_item_html,
    //                                 'dt_aprv' => $item->dt_aprv,
    //                                 'dt_aprv2' => $item->dt_aprv2,
    //                                 'dt_aprv3' => $item->dt_aprv3,
    //                                 'stat_closing' => $item->stat_closing,
    //                                 'stat_closing2' => $item->stat_closing2,
    //                                 'stat_closing3' => $item->stat_closing3,

    //                                 // ✅ FIX: assign dari qc_trans_doc
    //                                 'img_link' => $img1
    //                                     ? url("/storage/" . $img1->doc_path . $img1->doc_name)
    //                                     : '',

    //                                 'img_link2' => $img2
    //                                     ? url("/storage/" . $img2->doc_path . $img2->doc_name)
    //                                     : '',

    //                                 'img_link3' => $img3
    //                                     ? url("/storage/" . $img3->doc_path . $img3->doc_name)
    //                                     : '',
    //                             ];
    //                         })->values()
    //                     ];
    //                 })->values();

    //                 return [
    //                     'com_id' => $com_id,
    //                     'com_desc' => $items->first()->com_desc ?? '',
    //                     'categories' => $catGroups
    //                 ];
    //             })
    //             ->values();

    //         return $this->sendResponse($result, 'Successfully get QC Trans');

    //     } catch (\Throwable $e) {
    //         return $this->sendError($e->getMessage(), 'Error', 500);
    //     }
    // }

    public function getQcTrans(Request $request)
{
    $qc_trans_id = $request->qc_trans_id;

    try {

        $allImages = DB::connection("sqlSrvFPI")
            ->table('qc_trans_doc')
            ->where('qc_trans_id', $qc_trans_id)
            ->get()
            ->groupBy('id_qc_item');

        $result = DB::connection("sqlSrvFPI")
            ->table('qc_trans_dtl as qtd')
            ->join('qc_trans_hdr as qth', 'qtd.qc_trans_id', '=', 'qth.qc_trans_id')
            ->join('qc_item as qi', 'qtd.id_qc_item', '=', 'qi.id_qc_item')
            ->join('qc_item_commodity as com', 'qi.com_id', '=', 'com.com_id')
            ->leftJoin('qc_item_category as cat', 'qi.cat_id', '=', 'cat.cat_id')
            ->where('qth.qc_trans_id', $qc_trans_id)
            ->selectRaw("
                qtd.id_qc_item,
                qtd.dt_aprv,
                qtd.dt_aprv2,
                qtd.dt_aprv3,
                qtd.stat_closing,
                qtd.stat_closing2,
                qtd.stat_closing3,
                qi.qc_item,
                qi.qc_item_html,
                qi.com_id,
                qi.cat_id,
                com.com_desc,
                trim(cat.descr) as descr
            ")
            ->get()
            ->groupBy('com_id')
            ->map(function ($items, $com_id) use ($allImages) {

                $catGroups = $items->groupBy('cat_id')->map(function ($catItems, $cat_id) use ($allImages) {

                    return [
                        'cat_id' => $cat_id,
                        'cat_name' => $catItems->first()->descr ?? '',
                        'items' => $catItems->map(function ($item) use ($allImages) {

                            $images = $allImages[$item->id_qc_item] ?? collect();

                            $img1 = $images->firstWhere('item_num', 1);
                            $img2 = $images->firstWhere('item_num', 2);
                            $img3 = $images->firstWhere('item_num', 3);

                            // 🔥 Group by id_work
                            $attachments = $images
                                ->groupBy('id_work')
                                ->map(function ($workImages, $id_work) {
                                    return [
                                        'id_work' => (int) $id_work,
                                        'images'  => $workImages->map(function ($img) {
                                            return url("/storage/" . $img->doc_path . $img->doc_name);
                                        })->values()->toArray(),
                                    ];
                                })
                                ->values()
                                ->toArray();

                            return [
                                'id_qc_item'     => $item->id_qc_item,
                                'item_name'      => $item->qc_item,
                                'item_name_html' => $item->qc_item_html,
                                'dt_aprv'        => $item->dt_aprv,
                                'dt_aprv2'       => $item->dt_aprv2,
                                'dt_aprv3'       => $item->dt_aprv3,
                                'stat_closing'   => $item->stat_closing,
                                'stat_closing2'  => $item->stat_closing2,
                                'stat_closing3'  => $item->stat_closing3,
                                'img_link'       => $img1 ? url("/storage/" . $img1->doc_path . $img1->doc_name) : '',
                                'img_link2'      => $img2 ? url("/storage/" . $img2->doc_path . $img2->doc_name) : '',
                                'img_link3'      => $img3 ? url("/storage/" . $img3->doc_path . $img3->doc_name) : '',
                                'attachments'    => $attachments,
                            ];
                        })->values()
                    ];
                })->values();

                return [
                    'com_id'      => $com_id,
                    'com_desc'    => $items->first()->com_desc ?? '',
                    'categories'  => $catGroups
                ];
            })
            ->values();

        return $this->sendResponse($result, 'Successfully get QC Trans');

    } catch (\Throwable $e) {
        return $this->sendError($e->getMessage(), 'Error', 500);
    }
}
   

    public function getCheckListHouse(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $id_site = $request->id_site;
            $id_cluster = $request->id_cluster;
            $doc_type = $request->doc_type;

            // dd($sqlWhere);
            if (!empty($doc_type)) {
                if ($doc_type == 'SPK') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and hu.id_site = " . "'" . $id_site . "'";
                    } else {
                        return $this->sendError("Site ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and hu.id_cluster = " . "'" . $id_cluster . "'";
                    } else {
                        return $this->sendError("Cluster ID Kosong", 'Error', 500);
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                            ->table('house_unit as hu')
                            ->selectRaw("
                                        DISTINCT
                                        hu.id_site,
                                        hu.id_cluster,
                                        hu.id_house,
                                        hu.house_name,
                                        hu.active_flag,
                                        hu.building_area,
                                        hu.land_area,
                                        hu.remark,
                                        hu.id_house_type,
                                        hu.sold_stat,
                                        hu.house_price
                                        ")
                            ->join('spk_hdr as sh', 'sh.id_house_unit', '=', 'hu.id_house')
                            ->whereRaw($sqlWhere)
                            ->get();
                    return $this->sendResponse($datas, 'Successfully get House Unit Only Those Who Have SPK');

                } elseif ($doc_type == 'SPR') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and hu.id_site = " . "'" . $id_site . "'";
                    } else {
                        return $this->sendError("Site ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and hu.id_cluster = " . "'" . $id_cluster . "'";
                    } else {
                        return $this->sendError("Cluster ID Kosong", 'Error', 500);
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                            ->table('house_unit as hu')
                            ->selectRaw("
                                        hu.id_site,
                                        hu.id_cluster,
                                        hu.id_house,
                                        hu.house_name,
                                        hu.active_flag,
                                        hu.building_area,
                                        hu.land_area,
                                        hu.remark,
                                        hu.id_house_type,
                                        hu.sold_stat,
                                        hu.house_price
                                        ")
                            ->join('CustomerOrderDetil as cod', 'cod.id_house', '=', 'hu.id_house')
                            ->whereRaw($sqlWhere)
                            ->where('doc_type','SPR')
                            ->get();
                    return $this->sendResponse($datas, 'Successfully get House Unit Only Those Who Have SPR');
                } elseif ($doc_type == 'KK') {
                    if (!empty($id_site)) {
                        $sqlWhere = $sqlWhere . " and hu.id_site = " . "'" . $id_site . "'";
                    } else {
                        return $this->sendError("Site ID Kosong", 'Error', 500);
                    }
                    if (!empty($id_cluster)) {
                        $sqlWhere = $sqlWhere . " and hu.id_cluster = " . "'" . $id_cluster . "'";
                    } else {
                        return $this->sendError("Cluster ID Kosong", 'Error', 500);
                    }
                    if (!empty($active_flag)) {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'" . $active_flag . "'";
                    } else {
                        $sqlWhere = $sqlWhere . " and hu.active_flag = " . "'Y'";
                    }

                    $datas = DB::connection("sqlSrvFPI")
                            ->table('house_unit as hu')
                            ->selectRaw("
                                        hu.id_site,
                                        hu.id_cluster,
                                        hu.id_house,
                                        hu.house_name,
                                        hu.active_flag,
                                        hu.building_area,
                                        hu.land_area,
                                        hu.remark,
                                        hu.id_house_type,
                                        hu.sold_stat,
                                        hu.house_price
                                        ")
                            ->join('qc_trans_hdr as qth', 'qth.id_house', '=', 'hu.id_house')
                            ->whereRaw($sqlWhere)
                            ->where('doc_type','KK')
                            ->get();
                    return $this->sendResponse($datas, 'Successfully get House Unit Only Those Who Have KK');
                }
            } else {
                return $this->sendError("Type Kosong", 'Error', 500);
            }
            
            
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getCheckListVendor(Request $request)
    {
        $sqlWhere = '1=1';
        $vendor_id = $request->vendor_id;
        $active_flag = $request->active_flag;

        
        if(empty($active_flag)){
            $sqlWhere = $sqlWhere . " and sh.active_flag = 'Y'";
        }else{
            $sqlWhere = $sqlWhere . " and sh.active_flag = " . "'" . $active_flag . "'";
        }
        if(empty($vendor_id)){
            
        }else{
            $sqlWhere = $sqlWhere . " and v.vendor_id = " . "'" . $vendor_id . "'";
        }

        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('vendor as v')
                ->selectRaw("
                        DISTINCT
                        TRIM(v.vendor_id) as vendor_id,
                        TRIM(v.vendor_name) as vendor_name
                    ")
                ->leftjoin('spk_hdr as sh', 'sh.pihak_2', '=', 'v.vendor_id')
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($result, 'Successfully get Vendor Only Those Who Have SPK');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getAprvDtl(Request $request)
    {
        $sqlWhere = '1=1';
        $sqlWhereImg = '1=1';
        $sqlSelect = '';
        $sqlSelectImg = '';

        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;

        if(empty($qc_trans_id)){
            return $this->sendError("qc_trans_id Kosong", 'Error', 500);
        }else{
            $sqlWhere = $sqlWhere . " and qc_trans_id = " . "'" . $qc_trans_id . "'";
            $sqlWhereImg = $sqlWhereImg . " and qc_trans_id = " . "'" . $qc_trans_id . "'";
        }

        if(empty($id_qc_item)){
            return $this->sendError("id_qc_item Kosong", 'Error', 500);
        }else{
            $sqlWhere = $sqlWhere . " and id_qc_item = " . "'" . $id_qc_item . "'";
            $sqlWhereImg = $sqlWhereImg . " and id_qc_item = " . "'" . $id_qc_item . "'";
        }

        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }elseif($id_work == '1' || $id_work == 1){
            $sqlSelect = "remark, longitude1 as longitude, latitude1 as latitude";
            $sqlWhereImg = $sqlWhereImg . " and id_work = " . "'" . $id_work . "'";
        }elseif($id_work == '2' || $id_work == 2){
            $sqlSelect = "remark2 as remark, longitude2 as longitude, latitude2 as latitude";
            $sqlWhereImg = $sqlWhereImg . " and id_work = " . "'" . $id_work . "'";
        }elseif($id_work == '3' || $id_work == 3){
            $sqlSelect = "remark3 as remark, longitude3 as longitude, latitude3 as latitude";
            $sqlWhereImg = $sqlWhereImg . " and id_work = " . "'" . $id_work . "'";
        }

        try {
            $result = DB::connection("sqlSrvFPI")
                ->table('qc_trans_dtl')
                ->selectRaw($sqlSelect)
                ->whereRaw($sqlWhere)
                ->get();
            
            $img = DB::connection("sqlSrvFPI")
                ->table('qc_trans_doc')
                // ->selectRaw($sqlSelect)
                ->selectRaw("
                            qc_trans_id,
                            id_qc_item,
                            doc_name,
                            doc_path,
                            file_name,
                            id_work
                            ")
                ->whereRaw($sqlWhereImg)
                ->get();

            // return $this->sendResponse($result, 'Successfully Get Approval Detil');

            return response()->json([
                'data' => $result,
                'images' => $img,
                'message' => 'Successfully Get Approval Detail'
            ]);
            
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateRemarkCheckList(Request $request)
    {
        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;
        $removed_file = $request->removed_file;

        if (is_string($removed_file)) {
            $removed_file = json_decode($removed_file, true);
        }

        $removed_file = array_map(function ($item) {
            return basename($item);
        }, $removed_file ?? []);

        if (empty($qc_trans_id)) {
            return $this->sendError("QC Trx ID Kosong", 'Error', 500);
        }

        if (empty($id_qc_item)) {
            return $this->sendError("QC Item ID Kosong", 'Error', 500);
        }

        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | WORK 1
            |--------------------------------------------------------------------------
            */
            if ($id_work == '1' || $id_work == 1) {

                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv, 'yyyy-MM-dd') as dt_aprv")
                    ->value('dt_aprv');

                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 1", 'Error', 500);
                }

                // ================= DELETE IMAGE =================
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->get();

                foreach ($listAttch as $item) {
                    $filePath = $item->doc_path . '/' . $item->doc_name;

                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->delete();

                // ================= LAST ITEM NUM =================
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // ================= UPLOAD IMAGE =================
                $img = [];

                if ($request->hasFile('img')) {
                    foreach ($request->file('img') as $key => $file) {

                        if ($file && $file->isValid()) {

                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list/';

                            Storage::disk('public')->put(
                                "$path/$filename",
                                file_get_contents($file)
                            );

                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 1
                            ];
                        }
                    }
                }

                // ================= UPDATE REMARK =================
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark' => $request->remark ?? '',
                        'longitude1' => $request->longitude ?? '',
                        'latitude1' => $request->latitude ?? ''
                    ]);

                // ================= INSERT IMAGE =================
                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }

                return $this->sendResponse(
                    'Success Update',
                    'Successfully approve 1'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | WORK 2
            |--------------------------------------------------------------------------
            */
            elseif ($id_work == '2' || $id_work == 2) {

                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv2, 'yyyy-MM-dd') as dt_aprv2")
                    ->value('dt_aprv2');

                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 2", 'Error', 500);
                }

                // ================= DELETE IMAGE =================
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->get();

                foreach ($listAttch as $item) {

                    $filePath = $item->doc_path . '/' . $item->doc_name;

                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->delete();

                // ================= LAST ITEM NUM =================
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // ================= UPLOAD IMAGE =================
                $img = [];

                if ($request->hasFile('img')) {

                    foreach ($request->file('img') as $key => $file) {

                        if ($file && $file->isValid()) {

                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list';

                            Storage::disk('public')->put(
                                "$path/$filename",
                                file_get_contents($file)
                            );

                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 2
                            ];
                        }
                    }
                }

                // ================= UPDATE REMARK =================
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark2' => $request->remark ?? '',
                        'longitude2' => $request->longitude ?? '',
                        'latitude2' => $request->latitude ?? ''
                    ]);

                // ================= INSERT IMAGE =================
                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }

                return $this->sendResponse(
                    'Success Update',
                    'Successfully approve 2'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | WORK 3
            |--------------------------------------------------------------------------
            */
            elseif ($id_work == '3' || $id_work == 3) {

                $cekAprv = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->selectRaw("FORMAT(dt_aprv3, 'yyyy-MM-dd') as dt_aprv3")
                    ->value('dt_aprv3');

                if ($cekAprv !== '1900-01-01') {
                    return $this->sendError("Sudah di ceklist 3", 'Error', 500);
                }

                // ================= DELETE IMAGE =================
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->get();

                foreach ($listAttch as $item) {

                    $filePath = $item->doc_path . '/' . $item->doc_name;

                    if (Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->delete();

                // ================= LAST ITEM NUM =================
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // ================= UPLOAD IMAGE =================
                $img = [];

                if ($request->hasFile('img')) {

                    foreach ($request->file('img') as $key => $file) {

                        if ($file && $file->isValid()) {

                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list';

                            Storage::disk('public')->put(
                                "$path/$filename",
                                file_get_contents($file)
                            );

                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 3
                            ];
                        }
                    }
                }

                // ================= UPDATE REMARK =================
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark3' => $request->remark ?? '',
                        'longitude3' => $request->longitude ?? '',
                        'latitude3' => $request->latitude ?? ''
                    ]);

                // ================= INSERT IMAGE =================
                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }

                return $this->sendResponse(
                    'Success Update',
                    'Successfully approve 3'
                );
            }

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function updateAprvCheckList(Request $request)
    {
        // $houseUnitId = $request->id_house;
        // $image = $request->file('img');
        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;
        $removed_file = $request->removed_file;

        if (is_string($removed_file)) {
            $removed_file = json_decode($removed_file, true);
        }

        $removed_file = array_map(function ($item) {
            return basename($item); // 🔥 penting
        }, $removed_file ?? []);

        // dd($removed_file);
        
        if (empty($qc_trans_id)) {
            return $this->sendError("QC Trx ID Kosong", 'Error', 500);
        }
        if (empty($id_qc_item)) {
            return $this->sendError("QC Item ID Kosong", 'Error', 500);
        }
        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }

        try {
            if($id_work == '1' || $id_work == 1){
                               // Update yang ada, hapus gambar yg mau dihapus
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->get();
                
                foreach ($listAttch as $item) {
                    $filePath = $item->doc_path . '/' . $item->doc_name;

                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                    }
                }

                //delete image
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->delete();

                // -- item_num
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // dd($lastItemNum);

                // -- file uploads
                $img = [];

                if ($request->hasFile('img')) {
                    foreach ($request->file('img') as $key => $file) {
                        if ($file && $file->isValid()) {
                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list/';

                            // -- save file local
                            Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 1
                            ];
                        }
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark' => $request->remark ?? '',
                        'dt_aprv' => now(),
                        'aprv_by' => getAuth($request)->user_id2,
                        'longitude1' => $request->longitude ?? '',
                        'latitude1' => $request->latitude ?? ''
                    ]);

                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }
                
                return $this->sendResponse('Success Update', 'Successfully update approve 1');
                
            }elseif($id_work == '2' || $id_work == 2){
                // Delete semua gambar
                // $linkGambar = DB::connection("sqlSrvFPI")
                //     ->table('qc_trans_doc')
                //     ->where('qc_trans_id', $qc_trans_id)
                //     ->where('id_qc_item', $id_qc_item)
                //     ->where('id_work', $id_work)
                //     ->select('doc_name')
                //     ->get();
                    
                // if($linkGambar->isNotEmpty()) {
                //     foreach ($linkGambar as $item) {
                //         Storage::disk('public')->delete('fpi-check-list/' . $item->doc_name);
                //     }
                // }

                // DB::connection('sqlSrvFPI')
                //         ->table('qc_trans_doc')
                //         ->where('qc_trans_id', $qc_trans_id)
                //         ->where('id_qc_item', $id_qc_item)
                //         ->where('id_work', $id_work)
                //         ->delete();

                // Update yang ada, hapus gambar yg mau dihapus
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->get();
                
                foreach ($listAttch as $item) {
                    $filePath = $item->doc_path . '/' . $item->doc_name;

                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                    }
                }

                //delete image
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('doc_name', $removed_file)
                    ->delete();

                // -- item_num
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // dd($lastItemNum);

                // -- file uploads
                $img = [];

                if ($request->hasFile('img')) {
                    foreach ($request->file('img') as $key => $file) {
                        if ($file && $file->isValid()) {
                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list/';

                            // -- save file local
                            Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 2
                            ];
                        }
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark2' => $request->remark ?? '',
                        'dt_aprv2' => now(),
                        'aprv2_by' => getAuth($request)->user_id2,
                        'longitude2' => $request->longitude ?? '',
                        'latitude2' => $request->latitude ?? ''
                    ]);

                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }
                
                return $this->sendResponse('Success Update', 'Successfully update approve 2');

            }elseif($id_work == '3' || $id_work == 3){
                // Delete semua gambar
                // $linkGambar = DB::connection("sqlSrvFPI")
                //     ->table('qc_trans_doc')
                //     ->where('qc_trans_id', $qc_trans_id)
                //     ->where('id_qc_item', $id_qc_item)
                //     ->where('id_work', $id_work)
                //     ->select('doc_name')
                //     ->get();
                    
                // if($linkGambar->isNotEmpty()) {
                //     foreach ($linkGambar as $item) {
                //         Storage::disk('public')->delete('fpi-check-list/' . $item->doc_name);
                //     }
                // }

                // DB::connection('sqlSrvFPI')
                //         ->table('qc_trans_doc')
                //         ->where('qc_trans_id', $qc_trans_id)
                //         ->where('id_qc_item', $id_qc_item)
                //         ->where('id_work', $id_work)
                //         ->delete();

                // Update yang ada, hapus gambar yg mau dihapus
                $listAttch = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('file_name', $removed_file)
                    ->get();
                
                foreach ($listAttch as $item) {
                    $filePath = $item->doc_path . '/' . $item->doc_name;

                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                    }
                }

                //delete image
                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->whereIn('file_name', $removed_file)
                    ->delete();

                // -- item_num
                $lastItemNum = DB::connection('sqlSrvFPI')
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->max('item_num') ?? 0;

                // dd($lastItemNum);

                // -- file uploads
                $img = [];

                if ($request->hasFile('img')) {
                    foreach ($request->file('img') as $key => $file) {
                        if ($file && $file->isValid()) {
                            $extension = $file->getClientOriginalExtension();
                            $originalName = $file->getClientOriginalName();

                            $filename = Str::random(40) . '.' . $extension;
                            $path = 'fpi-check-list/';

                            // -- save file local
                            Storage::disk('public')->put("$path/$filename", file_get_contents($file));
                            $img[] = [
                                "qc_trans_id" => $qc_trans_id,
                                "id_qc_item" => $id_qc_item,
                                "item_num" => $lastItemNum + $key + 1,
                                "doc_name" => $filename,
                                "doc_path" => $path,
                                "dt_created" => now(),
                                "created_by" => getAuth($request)->user_id2,
                                "file_name" => $originalName,
                                "id_work" => 3
                            ];
                        }
                    }
                }

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark3' => $request->remark ?? '',
                        'dt_aprv3' => now(),
                        'aprv3_by' => getAuth($request)->user_id2,
                        'longitude3' => $request->longitude ?? '',
                        'latitude3' => $request->latitude ?? ''
                    ]);

                if ($img) {
                    DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->insert($img);
                }
                
                return $this->sendResponse('Success Update', 'Successfully update approve 3');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function cancelAprvCheckList(Request $request)
    {
        // $houseUnitId = $request->id_house;
        $qc_trans_id = $request->qc_trans_id;
        $id_qc_item = $request->id_qc_item;
        $id_work = $request->id_work;
        // $image = $request->file('img');

        // dd($image);
        
        if (empty($qc_trans_id)) {
            return $this->sendError("QC Trx ID Kosong", 'Error', 500);
        }
        if (empty($id_qc_item)) {
            return $this->sendError("QC Item ID Kosong", 'Error', 500);
        }
        if (empty($id_work)) {
            return $this->sendError("Work ID Kosong", 'Error', 500);
        }

        try {
            if($id_work == '1' || $id_work == 1){
                $linkGambar = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->select('doc_name')
                    ->get();
                    
                if($linkGambar->isNotEmpty()) {
                    foreach ($linkGambar as $item) {
                        Storage::disk('public')->delete('fpi-check-list/' . $item->doc_name);
                    }
                }

                DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->where('id_work', $id_work)
                        ->delete();

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark' => '',
                        'dt_aprv' => '',
                        'aprv_by' => ''
                    ]);
                
                return $this->sendResponse('Success Update', 'Successfully cancel approve 1');
                
            }elseif($id_work == '2' || $id_work == 2){
                $linkGambar = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->select('doc_name')
                    ->get();
                    
                if($linkGambar->isNotEmpty()) {
                    foreach ($linkGambar as $item) {
                        Storage::disk('public')->delete('fpi-check-list/' . $item->doc_name);
                    }
                }

                DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->where('id_work', $id_work)
                        ->delete();

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark2' => '',
                        'dt_aprv2' => '',
                        'aprv2_by' => ''
                    ]);
                
                return $this->sendResponse('Success Update', 'Successfully cancel approve 2');

            }elseif($id_work == '3' || $id_work == 3){
                $linkGambar = DB::connection("sqlSrvFPI")
                    ->table('qc_trans_doc')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->where('id_work', $id_work)
                    ->select('doc_name')
                    ->get();
                    
                if($linkGambar->isNotEmpty()) {
                    foreach ($linkGambar as $item) {
                        Storage::disk('public')->delete('fpi-check-list/' . $item->doc_name);
                    }
                }

                DB::connection('sqlSrvFPI')
                        ->table('qc_trans_doc')
                        ->where('qc_trans_id', $qc_trans_id)
                        ->where('id_qc_item', $id_qc_item)
                        ->where('id_work', $id_work)
                        ->delete();

                DB::connection('sqlSrvFPI')
                    ->table('qc_trans_dtl')
                    ->where('qc_trans_id', $qc_trans_id)
                    ->where('id_qc_item', $id_qc_item)
                    ->update([
                        'remark3' => '',
                        'dt_aprv3' => '1900-01-01 00:00:00.000',
                        'aprv3_by' => ''
                    ]);
                
                return $this->sendResponse('Success Update', 'Successfully cancel approve 3');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
