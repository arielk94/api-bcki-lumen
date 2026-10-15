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

class RabController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $entity_id = $request->entity_id;
        $cust_id = $request->cust_id ? $request->cust_id : ' ';
        $start_date = $request->start_date;
        if(!$start_date)
        {
            $start_date = '';
        }

        $end_date = $request->end_date;
        if(!$end_date)
        {
            $end_date = '';
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and project_hdr.created_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and project_hdr.created_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $datas = DB::connection('sqlSrvBCKI')
                ->table('project_hdr')
                ->selectRaw("project_hdr.*, list_province.province, list_city.city, list_district.district, list_village.village")
                ->leftJoin('province_list AS list_province',function($join){
                    $join->on('project_hdr.province_code','=','list_province.province_code');
                })
                ->leftJoin('city_list AS list_city',function($join){
                    $join->on('project_hdr.province_code','=','list_city.province_code')
                    ->on('project_hdr.city_code','=','list_city.city_code');
                })
                ->leftJoin('district_list AS list_district',function($join){
                    $join->on('project_hdr.province_code','=','list_district.province_code')
                    ->on('project_hdr.city_code','=','list_district.city_code')
                    ->on('project_hdr.district_code','=','list_district.district_code');
                })
                ->leftJoin('village_list AS list_village',function($join){
                    $join->on('project_hdr.province_code','=','list_village.province_code')
                    ->on('project_hdr.city_code','=','list_village.city_code')
                    ->on('project_hdr.district_code','=','list_village.district_code')
                    ->on('project_hdr.village_code','=','list_village.village_code');
                })
                ->whereRaw($sqlWhere)
                ->orderBy('project_hdr.created_date', 'ASC')
                ->get();
            
        return $this->sendResponse($datas, 'Successfully get rab');

    }

    public function store(Request $request)
    {

        DB::connection('sqlSrvBCKI')->beginTransaction();

        try {
            $id_project = $this->generateProjectID($request);

            $header = DB::connection('sqlSrvBCKI')
                    ->table('project_hdr')
                    ->insert([
                        'entity_id' => $request->entity_id,
                        'id_project' => $id_project,
                        'cust_id' => $request->cust_id ? $request->cust_id : ' ',
                        'customer' => $request->customer ? $request->customer : ' ',
                        'nama_project' => $request->nama_project,
                        'province_code' => $request->province_code,
                        'city_code' => $request->city_code,
                        'district_code' => $request->district_code,
                        'village_code' => $request->village_code,
                        'postal_code' => $request->postal_code,
                        'alamat' => $request->alamat,
                        'value' => $request->value,
                        'target_date' => $request->target_date,
                        'start_date' => $request->start_date,
                        'finish_date' => $request->finish_date,
                        'created_date' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'modified_date' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);

            // $this->storeDetail($id_project, $request);
            $this->storeArticleUsage($id_project, $request);
            $this->storeImages($id_project, $request);

            DB::connection('sqlSrvBCKI')->commit();

            return $this->sendResponse('Success', 'Successfully saved');

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function storeArticleUsage($id_project, $request)
    {
        foreach ($request->products as $key => $value) {
            $item_num = DB::connection("sqlSrvBCKI")
                    ->table('article_usage')
                    ->select('item_num')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $id_project)
                    ->max('item_num');
            
            DB::connection('sqlSrvBCKI')
            ->table('article_usage')
            ->insert([
                'entity_id' => $request->entity_id,
                'id_project' => $id_project,
                'item_num' => $item_num + 1,
                'article_id' => $value['article_id'],
                'qty' => (float) $value['qty'],
                'amt_item' => (float) $value['price'],
                'discount' =>  (float) $value['disc_unit'],
                'amt_total' => (float) $value['total'],
                'length' => (float) $value['panjang'],
                'uom' => $value['unit_meas'],
                'description' => $value['description'],
                'ppn_pph' => (float) $value['ppn'],
                'category' => $value['category'],
                'qty_realized' => 0,
                'created_date' => now(),
                'created_by' => getAuth($request)->user_id2,
                'modified_date' => now(),
                'modified_by' => getAuth($request)->user_id2,
            ]);
        }
    }

    private function storeImages($id_project, $request)
    {
        foreach ($request->images as $gambar) {
            $item_num = DB::connection("sqlSrvBCKI")
                        ->table('project_file')
                        ->select('item_num')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->max('item_num');

            $medium = Image::make($gambar)->encode('jpg', 75);
            $nameFinal = Str::random(40). '.jpg';

            Storage::disk('public')->put('fpis/' . $nameFinal, $medium->__toString());

            DB::connection('sqlSrvBCKI')
            ->table('project_file')
            ->insert([
                'entity_id' => $request->entity_id,
                'id_project' => $id_project,
                'item_num' => $item_num + 1,
                'file_dir' => $nameFinal,
                'created_date' => now(),
                'created_by' => getAuth($request)->user_id2,
                'modified_date' => now(),
                'modified_by' => getAuth($request)->user_id2,
            ]);
        }
    }

    private function storeDetail($id_project, $request)
    {
        $item_num = DB::connection("sqlSrvBCKI")
                    ->table('project_dtl')
                    ->select('id_step')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $id_project)
                    ->max('id_step');

        DB::connection('sqlSrvBCKI')
        ->table('project_dtl')
        ->insert([
            'entity_id' => $request->entity_id,
            'id_project' => $id_project,
            'id_step' => $item_num + 1,
            'step_name' => 'Create Planning',
            'step_desc' => 'Create Planning',
            'progress' => 100,
            'start_date' => now(),
            'finish_date' => now(),
            'created_date' => now(),
            'created_by' => getAuth($request)->user_id2,
            'modified_date' => now(),
            'modified_by' => getAuth($request)->user_id2,
        ]);
    }

    private function storeDetailStep($id_project, $request) 
    {
        foreach ($request->steps as $key => $value) {
            $item_num = DB::connection("sqlSrvBCKI")
                        ->table('project_dtl')
                        ->select('id_step')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->max('id_step');

            DB::connection('sqlSrvBCKI')
            ->table('project_dtl')
            ->insert([
                'entity_id' => $request->entity_id,
                'id_project' => $id_project,
                'id_step' => $item_num + 1,
                'step_name' => $value['step_name'],
                'step_desc' => $value['step_desc'],
                'progress' => (float) $value['progress'],
                'start_date' => $value['start_date'],
                'finish_date' => $value['finish_date'],
                'created_date' => now(),
                'created_by' => getAuth($request)->user_id2,
                'modified_date' => now(),
                'modified_by' => getAuth($request)->user_id2,
            ]);
        }
    }

    private function generateProjectID($request)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_tr_id = '';

        $getLastTrID =  DB::connection('sqlSrvBCKI')
                        ->table('project_hdr')
                        ->where('id_project', 'like', "%".trim($request->entity_id).$dateYear.$dateMonth.$dateDay."%")
                        ->max('id_project');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -3) + 1;
            $gen_tr_id = trim($request->entity_id).$dateYear.$dateMonth.$dateDay.str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_tr_id = trim($request->entity_id).$dateYear.$dateMonth.$dateDay.'001';
        }

        return $gen_tr_id;
    }

    public function show(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
                ->table('project_dtl')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->get();

        return $this->sendResponse($datas, 'Successfully get rab');

    }

    public function showArticleUsage(Request $request)
    {
        $datas = DB::connection("sqlSrvBCKI")
                ->table('article_usage')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->get();

        return $this->sendResponse($datas, 'Successfully get rab');

    }

    public function showImage(Request $request)
    {
        $arrayReusable = [];
        $datas = DB::connection("sqlSrvBCKI")
                ->table('project_file')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->get();

        foreach ($datas as $key => $value) {
            array_push($arrayReusable, [
                'entity_id' => $value->entity_id,
                'id_project' => $value->id_project,
                'file_dir' => $value->file_dir,
                'image' => config('image.bcki') . $value->file_dir,
                'item_num' => $value->item_num
            ]);
        }

        return $this->sendResponse($arrayReusable, 'Successfully get rab');

    }

    public function deleteImage(Request $request)
    {
        DB::connection('sqlSrvBCKI')->beginTransaction();

        try {

            $data =  DB::connection("sqlSrvBCKI")
                    ->table('project_file')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $request->id_project)
                    ->where('item_num', $request->item_num)
                    ->first();

            if ($data) {
                
                $exists = Storage::disk('public')->exists('rabs/' . $data->file_dir);

                if ($exists) {

                    Storage::disk('public')->delete('rabs/' . $data->file_dir);

                    DB::connection("sqlSrvBCKI")
                    ->table('project_file')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $request->id_project)
                    ->where('item_num', $request->item_num)
                    ->delete();

                    DB::connection('sqlSrvBCKI')->commit();

                    return $this->sendResponse('Success', 'Successfully deleted');

                } else {
                    return $this->sendError('Gambar tidak ditemukan', 'Error', 500);
                }
                
            } else {
                return $this->sendError('Gambar tidak ditemukan', 'Error', 500);
            }
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function update(Request $request)
    {
        DB::connection('sqlSrvBCKI')->beginTransaction();

        try {

            $data =  DB::connection("sqlSrvBCKI")
                    ->table('project_hdr')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $request->id_project)
                    ->first();

            if ($data) {
                $id_project = $request->id_project;

                $header = DB::connection("sqlSrvBCKI")
                        ->table('project_hdr')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->update([
                            'cust_id' => $request->cust_id ? $request->cust_id : ' ',
                            'customer' => $request->customer ? $request->customer : ' ',
                            'nama_project' => $request->nama_project,
                            'province_code' => $request->province_code,
                            'city_code' => $request->city_code,
                            'district_code' => $request->district_code,
                            'village_code' => $request->village_code,
                            'postal_code' => $request->postal_code,
                            'alamat' => $request->alamat,
                            'value' => $request->value,
                            'target_date' => $request->target_date,
                            'start_date' => $request->start_date,
                            'finish_date' => $request->finish_date,
                            'modified_date' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);

                $this->updateArticleUsage($id_project, $request);
                $this->storeImages($id_project, $request);
    
                DB::connection('sqlSrvBCKI')->commit();
    
                return $this->sendResponse('Success', 'Successfully saved');

            } else {
                return $this->sendError('ID project tidak ditemukan', 'Error', 500);

            }
        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function updateArticleUsage($id_project, $request)
    {
        foreach ($request->products as $key => $value) {
            $art_usage = DB::connection("sqlSrvBCKI")
                        ->table('article_usage')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->where('item_num', $value['item_num'])
                        ->first();

            if ($art_usage) {
                DB::connection("sqlSrvBCKI")
                ->table('article_usage')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $id_project)
                ->where('item_num', $value['item_num'])
                ->update([
                    'article_id' => $value['article_id'],
                    'qty' => (float) $value['qty'],
                    'amt_item' => (float) $value['price'],
                    'discount' =>  (float) $value['disc_unit'],
                    'amt_total' => (float) $value['total'],
                    'length' => (float) $value['panjang'],
                    'uom' => $value['unit_meas'],
                    'description' => $value['description'],
                    'ppn_pph' => (float) $value['ppn'],
                    'category' => $value['category'],
                    'qty_realized' => 0,
                    'created_date' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'modified_date' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);
            } else {
                $item_num = DB::connection("sqlSrvBCKI")
                        ->table('article_usage')
                        ->select('item_num')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->max('item_num');

                DB::connection('sqlSrvBCKI')
                ->table('article_usage')
                ->insert([
                    'entity_id' => $request->entity_id,
                    'id_project' => $id_project,
                    'item_num' => $item_num + 1,
                    'article_id' => $value['article_id'],
                    'qty' => (float) $value['qty'],
                    'amt_item' => (float) $value['price'],
                    'discount' =>  (float) $value['disc_unit'],
                    'amt_total' => (float) $value['total'],
                    'length' => (float) $value['panjang'],
                    'uom' => $value['unit_meas'],
                    'description' => $value['description'],
                    'ppn_pph' => (float) $value['ppn'],
                    'category' => $value['category'],
                    'qty_realized' => 0,
                    'created_date' => now(),
                    'created_by' => getAuth($request)->user_id2,
                    'modified_date' => now(),
                    'modified_by' => getAuth($request)->user_id2,
                ]);
            }
            
            
        }
    }

    public function approve(Request $request)
    {
        DB::connection('sqlSrvBCKI')->beginTransaction();

        try {
            $data =  DB::connection("sqlSrvBCKI")
                    ->table('project_hdr')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $request->id_project)
                    ->first();
            if ($data) {
                $header = DB::connection("sqlSrvBCKI")
                        ->table('project_hdr')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->update([
                            'value' => $request->value
                        ]);

                $id_project = $request->id_project;

                $art_usage = DB::connection("sqlSrvBCKI")
                            ->table('article_usage')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $id_project)
                            ->where('item_num', $request->item_num)
                            ->first();

                if ($art_usage) {
                    DB::connection("sqlSrvBCKI")
                    ->table('article_usage')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $id_project)
                    ->where('item_num', $request->item_num)
                    ->update([
                        'dt_approve1' => now(),
                        'approve1_by' => getAuth($request)->user_id2,
                        'dt_approve2' => now(),
                        'approve2_by' => getAuth($request)->user_id2,
                    ]);

                } else {
                    $item_num = DB::connection("sqlSrvBCKI")
                            ->table('article_usage')
                            ->select('item_num')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $id_project)
                            ->max('item_num');

                    DB::connection('sqlSrvBCKI')
                    ->table('article_usage')
                    ->insert([
                        'entity_id' => $request->entity_id,
                        'id_project' => $id_project,
                        'item_num' => $item_num + 1,
                        'article_id' => $request->article_id,
                        'qty' => (float) $request->qty,
                        'amt_item' => (float) $request->price,
                        'discount' =>  (float) $request->disc_unit,
                        'amt_total' => (float) $request->total,
                        'length' => (float) $request->panjang,
                        'uom' => $request->unit_meas,
                        'description' => $request->description,
                        'ppn_pph' => $request->ppn,
                        'category' => $request->category,
                        'qty_realized' => 0,
                        'created_date' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'modified_date' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                        'dt_approve1' => now(),
                        'approve1_by' => getAuth($request)->user_id2,
                        'dt_approve2' => now(),
                        'approve2_by' => getAuth($request)->user_id2,
                    ]);
                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully saved');

                
            } else {
                return $this->sendError('ID project tidak ditemukan', 'Error', 500);
            }

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getArticleUsageDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {
            $datas = DB::connection("sqlSrvBCKI")
            ->table('article_usage_dtl')
            ->where('entity_id', $request->entity_id)
            ->where('id_project', $request->id_project)
            ->where('item_num_usage', $request->item_num)
            ->get();

            return $this->sendResponse($datas, 'Successfully get rab');

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function saveArticleUsageDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                DB::connection("sqlSrvBCKI")
                ->table('article_usage_dtl')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->where('item_num_usage', $request->item_num)
                ->delete();

                foreach ($request->products as $key => $value) {
                    $item_num = DB::connection("sqlSrvBCKI")
                                ->table('article_usage_dtl')
                                ->select('item_num')
                                ->where('entity_id', $request->entity_id)
                                ->where('id_project', $request->id_project)
                                ->where('item_num_usage', $request->item_num)
                                ->max('item_num');

                    DB::connection("sqlSrvBCKI")
                    ->table('article_usage_dtl')
                    ->insert([
                        'entity_id' => $request->entity_id,
                        'id_project' => $request->id_project,
                        'item_num_usage' => $request->item_num,
                        'item_num' => $item_num + 1,
                        'article_id' => $value['article_id'],
                        'description' => $value['description'],
                        'date' => $value['date'],
                        'uom' => $value['uom'],
                        'panjang' => $value['panjang'],
                        'qty' => $value['qty'],
                        'created_date' => now(),
                        'created_by' => getAuth($request)->user_id2,
                        'modified_date' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);

                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully saved');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function getJasaUsageDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {
            $datas = DB::connection("sqlSrvBCKI")
            ->table('article_usage_jasa_step')
            ->where('entity_id', $request->entity_id)
            ->where('id_project', $request->id_project)
            ->where('item_num_usage', $request->item_num)
            ->get();
            
            return $this->sendResponse($datas, 'Successfully get rab');

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function saveJasaUsageDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                foreach ($request->products as $key => $value) {

                    $jasa =  DB::connection("sqlSrvBCKI")
                            ->table('article_usage_jasa_step')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $request->id_project)
                            ->where('item_num_usage', $request->item_num)
                            ->where('item_num', $value['item_num'])
                            ->first();

                    if ($jasa) {
                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->where('item_num_usage', $request->item_num)
                        ->where('item_num', $value['item_num'])
                        ->update([
                            'name' => $value['name'],
                            'description' => $value['description'],
                            'modified_date' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);

                    } else {
                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step')
                        ->insert([
                            'entity_id' => $request->entity_id,
                            'id_project' => $request->id_project,
                            'item_num_usage' => $request->item_num,
                            'item_num' => $value['item_num'],
                            'name' => $value['name'],
                            'description' => $value['description'],
                            'created_date' => now(),
                            'created_by' => getAuth($request)->user_id2,
                            'modified_date' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);
                    }                   

                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully saved');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function deleteJasaUsageDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                foreach ($request->products as $value) {

                    $jasa =  DB::connection("sqlSrvBCKI")
                            ->table('article_usage_jasa_step')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $request->id_project)
                            ->where('item_num_usage', $request->item_num)
                            ->where('item_num', $value)
                            ->first();

                    if ($jasa) {

                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step_dtl')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->where('item_num_usage', $request->item_num)
                        ->where('item_num_usage_step', $value)
                        ->delete();

                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->where('item_num_usage', $request->item_num)
                        ->where('item_num', $value)
                        ->delete();
                    }               

                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully deleted');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }
    public function getSumTotalJasaUsageStepDtl(Request $request)
    {
        $total = DB::connection("sqlSrvBCKI")
        ->table('article_usage_jasa_step_dtl')
        ->selectRaw("SUM(progress_total) as total")
        ->where('entity_id', $request->entity_id)
        ->where('id_project', $request->id_project)
        ->where('item_num_usage', $request->item_num_usage)
        ->first();

        return $this->sendResponse($total ? $total->total : 0, 'Successfully getSumTotalJasaUsageStepDtl');

    }

    public function getJasaUsageStepDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {
            $datas = DB::connection("sqlSrvBCKI")
            ->table('article_usage_jasa_step_dtl')
            ->where('entity_id', $request->entity_id)
            ->where('id_project', $request->id_project)
            ->where('item_num_usage', $request->item_num_usage)
            ->where('item_num_usage_step', $request->item_num_usage_step)
            ->get();
            
            return $this->sendResponse($datas, 'Successfully get rab');

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function saveJasaUsageStepDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                foreach ($request->products as $key => $value) {

                    $jasa =  DB::connection("sqlSrvBCKI")
                            ->table('article_usage_jasa_step_dtl')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $request->id_project)
                            ->where('item_num_usage', $request->item_num_usage)
                            ->where('item_num_usage_step', $request->item_num_usage_step)
                            ->where('item_num', $value['item_num'])
                            ->first();

                    if ($jasa) {
                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step_dtl')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->where('item_num_usage', $request->item_num_usage)
                        ->where('item_num_usage_step', $request->item_num_usage_step)
                        ->where('item_num', $value['item_num'])
                        ->update([
                            'date' => $value['date'],
                            'name' => $value['name'],
                            'description' => $value['description'],
                            'progress' => $value['progress'],
                            'progress_total' => $value['progress_total'],
                            'modified_date' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);

                    } else {
                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step_dtl')
                        ->insert([
                            'entity_id' => $request->entity_id,
                            'id_project' => $request->id_project,
                            'item_num_usage' => $request->item_num_usage,
                            'item_num_usage_step' => $request->item_num_usage_step,
                            'item_num' => $value['item_num'],
                            'date' => $value['date'],
                            'name' => $value['name'],
                            'description' => $value['description'],
                            'progress' => $value['progress'],
                            'progress_total' => $value['progress_total'],
                            'created_date' => now(),
                            'created_by' => getAuth($request)->user_id2,
                            'modified_date' => now(),
                            'modified_by' => getAuth($request)->user_id2,
                        ]);
                    }                   

                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully saved');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function deleteJasaUsageStepDtl(Request $request)
    {
        $data =  DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->first();

        if ($data) {

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                foreach ($request->products as $value) {

                    $jasa =  DB::connection("sqlSrvBCKI")
                            ->table('article_usage_jasa_step_dtl')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $request->id_project)
                            ->where('item_num_usage', $request->item_num_usage)
                            ->where('item_num_usage_step', $request->item_num_usage_step)
                            ->where('item_num', $value)
                            ->first();

                    if ($jasa) {

                        DB::connection("sqlSrvBCKI")
                        ->table('article_usage_jasa_step_dtl')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $request->id_project)
                        ->where('item_num_usage', $request->item_num_usage)
                        ->where('item_num_usage_step', $request->item_num_usage_step)
                        ->where('item_num', $value)
                        ->delete();

                    }               

                }

                DB::connection('sqlSrvBCKI')->commit();

                return $this->sendResponse('Success', 'Successfully deleted');
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('ID project tidak ditemukan', 'Error', 500);
        }
    }

    public function destroy(Request $request)
    {
        DB::connection('sqlSrvBCKI')->beginTransaction();

        try {
            $data =  DB::connection("sqlSrvBCKI")
                    ->table('project_hdr')
                    ->where('entity_id', $request->entity_id)
                    ->where('id_project', $request->id_project)
                    ->first();

            if ($data) {
                $id_project = $request->id_project;

                $detil = DB::connection("sqlSrvBCKI")
                        ->table('project_dtl')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->delete();

                $art_usage = DB::connection("sqlSrvBCKI")
                            ->table('article_usage')
                            ->where('entity_id', $request->entity_id)
                            ->where('id_project', $id_project)
                            ->delete();

                DB::connection("sqlSrvBCKI")
                ->table('article_usage_dtl')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $id_project)
                ->delete();

                DB::connection("sqlSrvBCKI")
                ->table('article_usage_jasa_step')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $id_project)
                ->delete();

                DB::connection("sqlSrvBCKI")
                ->table('article_usage_jasa_step_dtl')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $id_project)
                ->delete();

                $images = DB::connection("sqlSrvBCKI")
                        ->table('project_file')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->get();

                foreach ($images as $key => $value) {
                    $exists = Storage::disk('public')->exists('rabs/' . $value->file_dir);

                    if ($exists) {
                        Storage::disk('public')->delete('rabs/' . $value->file_dir);

                        DB::connection("sqlSrvBCKI")
                        ->table('project_file')
                        ->where('entity_id', $request->entity_id)
                        ->where('id_project', $id_project)
                        ->where('item_num', $value->item_num)
                        ->delete();
                    }
                }

                DB::connection("sqlSrvBCKI")
                ->table('project_hdr')
                ->where('entity_id', $request->entity_id)
                ->where('id_project', $request->id_project)
                ->delete();

                DB::connection('sqlSrvBCKI')->commit();
        
                return $this->sendResponse('Success', 'Successfully deleted');

            } else {
                return $this->sendError('ID project tidak ditemukan', 'Error', 500);
            }
                

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvBCKI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
        
    
}
