<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\MasterFPI;
use App\Services\PerhitunganIncludeService;
use Illuminate\Http\ResponseTrait;
use App\Services\AccountingService;

class DpPayRequestController extends Controller
{
    use MasterFPI;
    use ResponseTrait;

    protected $ServiceInclude;
    protected $ServiceAccounting;

    public function __construct(PerhitunganIncludeService $ServiceInclude)
    {
        $this->ServiceInclude = $ServiceInclude;
        $this->ServiceAccounting = new AccountingService();
    }


    public function index(Request $request)
    {

        $GVendor = $this->getVendor("");
        $GOffice = $this->getOffice("");
        $GDepartment = $this->getDepartment("", "");
        $gpic = $this->getPicName("", "", "");
        $GArticleId = $this->getArticleId("");
        $GIdSite = $this->getIdSite("");
        $GIdCluster = $this->getIdCluster("");
        $GIdHouse = $this->getIdHouse("");
        $GPayterm = $this->getPayTerm("");
        $getAuth = getAuth($request);
        $currId = $this->getCurrId("");

        return $this->sendResponse([
            'vendor' => $GVendor,
            'office' => $GOffice,
            'department' => $GDepartment,
            'pic' => $gpic,
            'articleId' => $GArticleId,
            'idSite' => $GIdSite,
            'idCluster' => $GIdCluster,
            'idHouse' => $GIdHouse,
            'payterm' => $GPayterm,
            'userId' => $getAuth,
            'currency' => $currId,
        ], 'Successful');
    }

    public function GetPo(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->select(DB::raw("select a.po_id, a.dt_po, a.pay_term_id, a.curr_id, a.amt_net,
                    isnull((select sum(b.req_dp_amt) from purc_dp_req_dtl b where b.po_id = a.po_id),0) as amt_req 
                    from po_hdr a where a.vendor_id = '$request->vendorid' and a.office_id = '$request->officeid' and a.stat != 'C' order by a.dt_po desc"));
                            
            return $this->sendResponse($datas, 'Successfully');

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }   
}
