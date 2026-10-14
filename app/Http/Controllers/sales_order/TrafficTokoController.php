<?php

namespace App\Http\Controllers\sales_order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrafficTokoController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";

        $usertype = DB::connection('sqlsrv')
                    ->table('sec_env_conf')
                    ->where('user_id2', getAuth($request)->user_id2)
                    ->where('entity_id', 'FDPI')
                    ->where('appl_id','SALES')
                    ->where('var_id','ACCESS_ALL')
                    ->first();

        if(!$usertype)
        {
            $sqlWhere .= " and customer_visit.userid = '" . getAuth($request)->user_id2 . "'";
        }

        $office_id = $request->office_id;
        // $region = $request->region;
        $id_site = $request->id_site;
        if($id_site)
        {
            $sqlWhere.= " and customer_visit.id_site = '$id_site'";
        }
        $id_cluster = $request->id_cluster;
        if($id_cluster)
        {
            $sqlWhere.= " and customer_visit.id_cluster = '$id_cluster'";
        }

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

        if ($office_id) {
    
            $sqlWhere.= " and customer_visit.office_id = '$office_id'";
        }

        // if ($region) {
    
        //     $sqlWhere.= " and branch_office.region = '$region'";
        // }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and customer_visit.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and customer_visit.tr_date BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $datas = DB::connection('sqlSrvFPI') 
                    ->table('customer_visit as customer_visit')
                    ->fromRaw("customer_visit as customer_visit OUTER APPLY (
                        SELECT TOP 1 
                            cvh.reff_id,
                            cvh.id,
                            cvh.tr_date, 
                            cvh.cat_id, 
                            cvh.remark
                        FROM customer_visit_hist cvh
                        WHERE cvh.reff_id = customer_visit.id
                        ORDER BY cvh.id DESC
                    ) AS latest_hist")
                    ->selectRaw("
                        customer_visit.id,
                        customer_visit.office_id,
                        customer_visit.customer_name,
                        customer_visit.customer_address,
                        customer_visit.customer_phone,
                        isnull(latest_hist.cat_id, customer_visit.customer_action) as customer_action,
                        customer_visit.tr_date,
                        isnull(latest_hist.remark, customer_visit.remark) as remark,
                        customer_visit.dt_create,
                        customer_visit.dt_modified,
                        customer_visit.userid,
                        employee.name as nama_pembuat,
                        customer_visit.type_visit,
                        customer_visit.id_site,
                        customer_visit.id_cluster,
                        customer_visit.project_id,
                        customer_visit.type_leads,
                        branch_office.office, 
                        branch_office.address as office_address, 
                        branch_office.tlp as office_tlp, 
                        isnull(phdr.project_name, '') as project_name, 
                        isnull(appl_constant.str2 + ' - ' + appl_constant_hist.str2, appl_constant.str2) as activity, 
                        appl_constant.str3 as kegiatan, 
                        appl_constant_vst.str2 as type_visit_label,
                        appl_constant_type_leads.str2 as type_leads_label, 
                        customer_visit.type_visit as val_type_visit, 
                        isnull(site.site_name, '') as site_name, 
                        isnull(site_cluster.cluster_name, '') as cluster_name                     
                    ")
                    ->leftJoin('appl_constant AS appl_constant',function($join){
                        $join->on('customer_visit.customer_action','=','appl_constant.str1')
                        ->where('appl_constant.key_code', 'C_ACTION');
                    })
                    ->leftJoin('appl_constant as appl_constant_hist', function($join) {
                        $join->on('latest_hist.cat_id', '=', 'appl_constant_hist.str1')
                            ->where('appl_constant_hist.key_code', '=', 'CAT_PROSPECT');
                    })
                    ->leftJoin('appl_constant AS appl_constant_vst',function($join){
                        $join->on('customer_visit.type_visit','=','appl_constant_vst.str1')
                        ->where('appl_constant_vst.key_code', 'TYPE_VISITOR');
                    })
                    ->leftJoin('appl_constant AS appl_constant_type_leads',function($join){
                        $join->on('customer_visit.type_leads','=','appl_constant_type_leads.str1')
                        ->where('appl_constant_type_leads.key_code', 'LEADS');
                    })
                    ->leftJoin('branch_office AS branch_office',function($join){
                        $join->on('customer_visit.office_id','=','branch_office.office_id');
                    })
                    ->leftJoin('hrd_ol.dbo.employee as employee',function($join){
                        $join->on('customer_visit.userid','=','employee.idemployee');
                    })
                    ->leftJoin('site', 'site.id_site', '=', 'customer_visit.id_site')
                    ->leftJoin('site_cluster', function($join) {
                        $join->on('site_cluster.id_cluster', '=', 'customer_visit.id_cluster')
                            ->on('site_cluster.id_site', '=', 'customer_visit.id_site');
                    })
                    ->leftJoin('hrd_ol.dbo.project_hdr as phdr', 'customer_visit.project_id', '=', 'phdr.pr_id')
                    ->whereRaw($sqlWhere)
                    ->orderBy('customer_visit.tr_date', 'ASC')
                    ->get(); 

                    $datas->map(function ($data) {
                        $data->customerhistory = DB::connection('sqlSrvFPI') 
                        ->table('customer_visit_hist as customer_visit_hist')
                        ->selectRaw("
                            customer_visit_hist.id,
                            customer_visit_hist.reff_id,
                            customer_visit_hist.tr_date,
                            customer_visit_hist.cat_id,
                            customer_visit_hist.remark,
                            customer_visit_hist.dt_create,
                            customer_visit_hist.dt_modified,
                            customer_visit_hist.userid,
                            appl_constant.str2 as category
                        ")
                        ->join('appl_constant AS appl_constant',function($join){
                            $join->on('customer_visit_hist.cat_id','=','appl_constant.str1')
                            ->where('appl_constant.key_code', 'CAT_PROSPECT');
                        })
                        ->where('customer_visit_hist.reff_id', $data->id)
                        ->orderBy('customer_visit_hist.id', 'ASC')
                        ->get();
                        return $data;
                    });

        return $this->sendResponse($datas, 'Successfully get List');
    }

    public function store(Request $request)
    {

        DB::connection('sqlSrvFPI')->beginTransaction();

        try {
            
            $visitId = DB::connection('sqlSrvFPI')
            ->table('customer_visit')
            ->insertGetId([
                'office_id' => $request->office_id ? $request->office_id : '1',
                'customer_name' => $request->customer_name,
                'customer_address' => $request->customer_address,
                'customer_phone' => $request->customer_phone,
                'customer_action' => $request->customer_action,
                'type_visit' => $request->type_visit ? $request->type_visit : 'V02',
                'tr_date' => $request->tr_date,
                'remark' => $request->remark,
                'id_site' => $request->id_site,
                'id_cluster' => $request->id_cluster,
                'project_id' => $request->project_id,
                'type_leads' => $request->type_leads,
                'dt_create' => now('Asia/Jakarta'),
                'dt_modified' => now('Asia/Jakarta'),
                'userid' => getAuth($request)->user_id2
            ]);

            DB::connection('sqlSrvFPI')
            ->table('customer_visit_hist')
            ->insert([
                'reff_id'     => $visitId,
                // 'id' tidak diisi jika auto-increment, jika manual silakan tambahkan logic-nya
                'tr_date'     => $request->tr_date,
                'cat_id'      => $request->category_id ?: '',
                'remark'      => $request->remark,
                'dt_create'   => now('Asia/Jakarta'),
                'dt_modified' => now('Asia/Jakarta'),
                'userid'      => getAuth($request)->user_id2
            ]);

            DB::connection('sqlSrvFPI')->commit();

            return $this->sendResponse('Success', 'Successfully saved');                            

        }
        catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function update(Request $request)
    {
        $detail = DB::connection('sqlSrvFPI')
                    ->table('customer_visit')
                    ->where('id', $request->id)
                    ->first();

        if ($detail) {

            DB::connection('sqlSrvFPI')->beginTransaction();

            try {
                DB::connection('sqlSrvFPI')
                ->table('customer_visit')
                ->where('id', $request->id)
                ->update([
                    'office_id' => $request->office_id? $request->office_id : '1',
                    'customer_name' => $request->customer_name,
                    'customer_address' => $request->customer_address,
                    'customer_phone' => $request->customer_phone,
                    'customer_action' => $request->customer_action,
                    'type_visit' => $request->type_visit ? $request->type_visit : 'V02',
                    'tr_date' => $request->tr_date,
                    'remark' => $request->remark,
                    'id_site' => $request->id_site,
                    'id_cluster' => $request->id_cluster,
                    'project_id' => $request->project_id,
                    'type_leads' => $request->type_leads,
                    'dt_modified' => now('Asia/Jakarta'),
                    'userid' => getAuth($request)->user_id2
                ]);

                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success', 'Successfully saved');   
                
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvFPI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('Id tidak ditemukan', 'Error', 500);

        }       
    }

    public function destroy(Request $request)
    {
        $detail = DB::connection('sqlSrvFPI')
                    ->table('customer_visit')
                    ->where('id', $request->id)
                    ->first();

        if ($detail) {
            DB::connection('sqlSrvFPI')->beginTransaction();
            try {
                DB::connection('sqlSrvFPI')
                ->table('customer_visit')
                ->where('id', $request->id)
                ->delete();

                DB::connection('sqlSrvFPI')->commit();
                return $this->sendResponse('Success', 'Successfully deleted');                
            }
            catch (\Exception $e) {
                DB::connection('sqlSrvFPI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }

        } else {
            return $this->sendError('Id tidak ditemukan', 'Error', 500);
        }        
    }

    public function getProject(Request $request)
    {   
        $sqlWhere = "1=1";
        $stat = $request->stat;
        if($stat)
        {
            $sqlWhere.= " and stat = '$stat'";
        }
        $datas = DB::connection('sqlsrvHrd') 
                    ->table('V_project_hdr')
                    ->where('entity_id', 'FDPI')
                    // ->where('stat', $stat)
                    // ->selectRaw("project_id, project_name")
                    ->whereRaw($sqlWhere)
                    // ->orderBy('project_name', 'ASC')
                    ->get(); 

        return $this->sendResponse($datas, 'Successfully get List');
    }

    public function updateCustHist(Request $request)
    {
        $visitId = $request->id;

        DB::connection('sqlSrvFPI')
        ->table('customer_visit_hist')
        ->insert([
            'reff_id'     => $visitId,
            'tr_date'     => now('Asia/Jakarta'),
            'cat_id'      => $request->category_id ?: '',
            'remark'      => $request->remark,
            'dt_create'   => now('Asia/Jakarta'),
            'dt_modified' => now('Asia/Jakarta'),
            'userid'      => getAuth($request)->user_id2
        ]);

        return $this->sendResponse('Success', 'Successfully updated');
    }
}
