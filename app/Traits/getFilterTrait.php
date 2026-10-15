<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait getFilterTrait
{
    public function getEnvConf($appl_id, $var_id, $user_id2 = '')
    {
        $data =  DB::connection("sqlsrv")
            ->table('sec_env_conf')
            ->where('entity_id', 'bcki')
            ->where('appl_id', $appl_id)
            ->where('var_id', $var_id)
            ->where('active_flag', 'Y');
        
        // -- user_id2
        if($user_id2){
            $data->where('user_id2', $user_id2);
        }
            
        return $data->value('var_value');
    }

    public function queryGetUserGroup($request){

        $group = DB::connection('sqlsrv')
                    ->table('sec_group')
                    ->select('group_id')
                    ->where('entity_id', '=', 'bcki' )
                    ->where('appl_id', '=', 'SALES' )
                    ->where('user_id2', '=', getAuth($request)->user_id2 )
                    ->where('active_flag', '=', 'Y')
                    ->value('group_id');

        return $group;

    }

    public function queryGetRegions($group){

        switch ($group) {
            
            case "DEVELOPMENT":

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', 'Y' )
                            ->get();

                return $region;

            break;

            case "MANAGEMENT":
                
                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', 'Y' )
                            ->get();

                return $region;

            break;

            case "RM bcki":

                $salesid = DB::connection('sqlsrvHrd')
                            ->table('employee')
                            ->select('salesid')
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('salesid');

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('rm', '=', $salesid )
                            ->where('active_flag', '=', 'Y')
                            ->distinct()
                            ->get();

                return $region;

            break;

            case "ADMIN PENJUALAN":

                $officeEnv = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'OFFICEID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $office = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->select('office_id')
                            ->where('active_flag', '=', 'Y')
                            ->where('SalesId', '=', $officeEnv )    
                            ->orderBy('NamaSales', 'asc')                
                            ->first();

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', '=', 'Y')
                            ->where('office_id', '=', $office->office_id)
                            ->distinct('region')
                            ->get();

                return $region;
            
            break;

            case "KACAB bcki":

                $salesid = DB::connection('sqlsrvHrd')
                            ->table('employee')
                            ->select('salesid')
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('salesid');

                $office = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->select('office_id')
                            ->where('active_flag', '=', 'Y')
                            ->where('SalesId', '=', $salesid )    
                            ->orderBy('NamaSales', 'asc')                
                            ->first();

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', '=', 'Y')
                            ->where('office_id', '=', $office->office_id)
                            ->distinct('region')
                            ->get();

                return $region;
            
            break;
            
            case "SALES bcki":

                $salesid = DB::connection('sqlsrvHrd')
                            ->table('employee')
                            ->select('salesid')
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('salesid');

                $office = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->select('office_id')
                            ->where('active_flag', '=', 'Y')
                            ->where('SalesId', '=', $salesid )     
                            ->orderBy('NamaSales', 'asc')               
                            ->first();

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', '=', 'Y')
                            ->where('office_id', '=', $office->office_id)
                            ->get();

                return $region;
            
            break;

            case "RM PROJECT bcki":

                $salesid = DB::connection('sqlsrvHrd')
                            ->table('employee')
                            ->select('salesid')
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('salesid');

                $region = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('active_flag', '=', 'Y')
                            ->where('office_id', '=', $office->office_id)
                            ->get();

                return $region;

            break;

            default:

                
        }

    }

    public function queryGetOffices($group){

        switch ($group) {
            case "DEVELOPMENT":
                
                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office')
                                ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office, lng, lat")
                                ->where('active_flag', '=', 'Y')
                                ->orderBy('office', 'asc')
                                ->get();

                return $office;

            break;

            case "MANAGEMENT":
                
                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office')
                                ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office, lng, lat")
                                ->where('active_flag', '=', 'Y')
                                ->orderBy('office', 'asc')
                                ->get();

                return $office;

            break;

            case "RM bcki":

                $salesid = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->select('var_value')
                                ->where('entity_id', '=', 'bcki')
                                ->where('appl_id', '=', 'SALES')
                                ->where('var_id', '=', 'SALESID' )
                                ->where('user_id2', '=', getAuth($request)->user_id2 )
                                ->value('var_value');

                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office')
                                ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office, lng, lat")
                                ->where('active_flag', '=', 'Y')
                                ->where('rm', '=', $salesid)
                                ->orderBy('office', 'asc')
                                ->get();

                return $office;

            break;

            case "KACAB bcki":

                $salesid = DB::connection('sqlsrv')
                                ->table('sec_env_conf')
                                ->select('var_value')
                                ->where('entity_id', '=', 'bcki')
                                ->where('appl_id', '=', 'SALES')
                                ->where('var_id', '=', 'SALESID' )
                                ->where('user_id2', '=', getAuth($request)->user_id2 )
                                ->value('var_value');

                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office')
                                ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office, lng, lat")
                                ->where('active_flag', '=', 'Y')
                                ->where('ka_cab', '=', $salesid)
                                ->orderBy('office', 'asc')
                                ->get();

                return $office;
            
            break;
            
            case "SALES bcki":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office as a')
                                ->join('Sales as b', 'a.office_id', '=', 'b.office_id')
                                ->select('a.office_id', 'a.office', 'a.lng', 'a.lat')
                                ->where('a.active_flag', '=', 'Y')
                                ->where('b.SalesId', '=', $salesid)
                                ->orderBy('a.office', 'asc')
                                ->get();

                return $office;
            
            break;

            case "RM PROJECT":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $offRegion = DB::connection("sqlSrvBCKI")
                            ->table('branch_office')
                            ->select('office_id')
                            ->where('rm','=', $salesid)
                            ->where('active_flag', '=', 'Y')
                            ->pluck('office_id')
                            ->toArray();

                $office = DB::connection('sqlSrvBCKI')
                                ->table('branch_office')
                                ->selectRaw("office_id, concat(trim(office), '(', office_id, ')' ) as office, lng, lat")
                                ->where('active_flag', '=', 'Y')
                                ->whereIn('office_id', $offRegion)
                                ->orderBy('office', 'asc')
                                ->get();

                return $office;

            break;

            default:

                
        }

    }

    public function queryGetSales($group){

        switch ($group) {
            case "DEVELOPMENT":
                
                $sales = DB::connection('sqlSrvBCKI')
                                ->table('Sales')
                                ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                                ->where('active_flag', '=', 'Y')
                                ->orderBy('NamaSales', 'asc')
                                ->get();

                return $sales;

            break;

            case "MANAGEMENT":
                
                $sales = DB::connection('sqlSrvBCKI')
                                ->table('Sales')
                                ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                                ->where('active_flag', '=', 'Y')
                                ->orderBy('NamaSales', 'asc')
                                ->get();

                return $sales;

            break;

            case "RM bcki":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $regionGet = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('region')
                            ->where('rm', '=', $salesid )
                            ->where('active_flag', '=', 'Y')
                            ->value('region');

                $offRegion = DB::connection("sqlSrvBCKI")
                            ->table('branch_office')
                            ->select('office_id')
                            ->where('region','=', $regionGet)
                            ->where('active_flag', '=', 'Y')
                            ->pluck('office_id')
                            ->toArray();

                $sales = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                            ->where('active_flag', '=', 'Y')
                            ->whereIn('office_id', $offRegion)
                            ->orderBy('NamaSales', 'asc')
                            ->get();

                return $sales;

            break;

            case "KACAB bcki":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $office = DB::connection('sqlSrvBCKI')
                            ->table('branch_office')
                            ->select('office_id', 'office')
                            ->where('active_flag', '=', 'Y')
                            ->where('ka_cab', '=', $salesid)
                            ->first();

                $sales = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                            ->where('active_flag', '=', 'Y')
                            ->where('office_id', '=', $office->office_id)
                            ->orderBy('NamaSales', 'asc')
                            ->get();

                return $sales;
            
            break;
            
            case "SALES bcki":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $sales = DB::connection('sqlSrvBCKI')
                            ->table('Sales')
                            ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                            ->where('active_flag', '=', 'Y')
                            ->where('SalesId', '=', $salesid)
                            ->orderBy('NamaSales', 'asc')
                            ->get();

                return $sales;
            
            break;

            case "RM PROJECT bcki":

                $salesid = DB::connection('sqlsrv')
                            ->table('sec_env_conf')
                            ->select('var_value')
                            ->where('entity_id', '=', 'bcki')
                            ->where('appl_id', '=', 'SALES')
                            ->where('var_id', '=', 'SALESID' )
                            ->where('user_id2', '=', getAuth($request)->user_id2 )
                            ->value('var_value');

                $region = DB::connection('sqlSrvBCKI')
                            ->table('v_region_mapping')
                            ->select('Region_Proyek as region')
                            ->where('Rm_Proyek', '=', $salesid)
                            ->distinct('Region_Proyek')
                            ->get();

                $sales = DB::connection('sqlSrvBCKI')
                            ->table('v_region_mapping')
                            ->selectRaw("SalesId,(NamaSales + ' (' + SalesId + ')') as NamaSales")
                            ->where('Region_Proyek', '=', $region[0]->region)
                            ->where('division', '=', 'R')
                            ->get();

                return $sales;

            break;

            default:
                
        }

    }
    
}
