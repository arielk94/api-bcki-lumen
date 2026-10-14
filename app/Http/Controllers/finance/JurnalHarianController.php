<?php

namespace App\Http\Controllers\finance;

use App\Services\AccountingService;
use App\Http\Controllers\Controller;
use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JurnalHarianController extends Controller
{
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }

    public function getData(Request $request){
        $sqlWhere = "1=1";
        $start_date = $request->start_date; // contoh: '20250101'
        $end_date = $request->end_date; // contoh: '20250531'
        $office_id = $request->office_id; // contoh: '1'
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $id_house = $request->id_house;
        $source = $request->source;
        $ac_num = $request->ac_num; // contoh: '120-010-001'

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
        
        if ($office_id) {
            $sqlWhere .= " and office_id = '$office_id'";
        }
        if ($id_site) {
            $sqlWhere .= " and id_site = '$id_site'";
        }
        if ($id_cluster) {
            $sqlWhere .= " and id_cluster = '$id_cluster'";
        }
        if ($id_house) {
            $sqlWhere .= " and id_house = '$id_house'";
        }
        if ($source) {
            $sqlWhere .= " and source = '$source'";
        }

        // Jika ac_num terisi, tambahkan subquery source_num
        if ($ac_num) {
            $subQuery = DB::connection('sqlSrvFPI')
                ->table('v_general_ledger_detail')
                ->selectRaw('DISTINCT source_num')
                ->where('ac_num', $ac_num)
                ->toSql(); // kita dapatkan query mentahnya

            // karena toSql tidak menjalankan binding, kita langsung tempelkan nilai ac_num
            $sqlWhere .= " AND source_num IN (SELECT DISTINCT source_num FROM v_general_ledger_detail WHERE ac_num = '$ac_num')";
        }

        $data = DB::connection('sqlSrvFPI')
            ->table('v_general_ledger_detail')
            ->selectRaw("
                office_id,
                trim(source) as source,
                trim(source_num) as source_num,
                jv_num,
                trim(ac_num) as ac_num,
                trim(ac_name)  + ' (' + TRIM(ac_num) + ')' as ac_name,
                tr_date,
                drcr,
                amt_dr,
                amt_cr,
                trim(cust_id) as cust_id,
                trim(customer) as customer,
                trim(vendor_id) as vendor_id,
                trim(vendor_name) as vendor_name,
                trim(inv_id) as inv_id,
                trim(remark) as remark,
                trim(id_site) as id_site,
                trim(site_name) as site_name,
                trim(id_cluster) as id_cluster,
                trim(cluster_name) as cluster_name,
                trim(id_house) as id_house,
                trim(house_name) as house_name,
                trim(user_id) as user_id,
                trim(username) as username
            ")
            ->whereRaw($sqlWhere)
            ->OrderBy('source', 'asc')
            ->OrderBy('tr_date', 'asc')
            ->OrderBy('source_num', 'asc')
            ->get();

        return $this->sendResponse($data, 'Successfully retrieved Kasbon data');
    }

    public function getSourceData(){
        $data = DB::connection('sqlSrvFPI')
            ->table('v_general_ledger_detail')
            ->selectRaw('trim(source) as source')
            ->distinct()
            ->get();
        return $this->sendResponse($data, 'Successfully retrieved Source data');
    }

    public function cekClosing(Request $request){
        $tr_date = $request->tr_date; // contoh: '2025-01-01'

        if ($this->serviceAcc->ifAcctClosed($tr_date, $request)) {
            return $this->sendResponse('failed', 'This periode already closed, cannot be modified');
        }
        else {
            return $this->sendResponse('success', 'This periode is still open, can be modified');
        }
    }

    public function updateJurnal(Request $request){

        $source = trim($request->source);
        $source_num = trim($request->source_num);
        $ac_num_baru = trim($request->ac_num_baru);
        $ac_num_lama = trim($request->ac_num_lama);

        $data = DB::connection('sqlSrvFPI')
            ->table('v_general_ledger_detail')
            ->where('source', $source)
            ->where('source_num', $source_num)
            ->where('ac_num', $ac_num_lama)
            ->get();
        // dd($data);
        $jv_num = $data->first()->jv_num;
        // dd($jv_num);
        $drcr = $data->first()->drcr;
        $ac_num_lama = trim($data->first()->ac_num);
        $office_id = $data->first()->office_id;
        $tanggal = Carbon::parse($data->first()->tr_date);
        $tahun4digit = $tanggal->format('Y');
        // dd($tahun4digit, $office_id, $jv_num, $ac_num_lama, $ac_num_baru, getAuth($request)->user_id2);
        $this->serviceAcc->JournalRevise($office_id, $tahun4digit, $jv_num, $ac_num_lama, $ac_num_baru, getAuth($request)->user_id2);

        return $this->sendResponse($data, 'Successfully updated Jurnal data');
        
    }
}
