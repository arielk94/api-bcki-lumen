<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestDetailController extends Controller
{
    public function index(Request $request) 
    {
        $sqlWhere = "1=1";
        $office_id = $request->office_id;

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
    
            $sqlWhere.= " and pr_item.office_id = '$office_id'";
        }

        if ($start_date)
        {
            if ($end_date)
            {
                $sqlWhere .= " and pr_hdr.dt_pr BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
            }
            else
            {
                $sqlWhere .= " and pr_hdr.dt_pr BETWEEN CONVERT(datetime,'" .$start_date. " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
            }
        }

        $datas = DB::connection("sqlSrvBCKI")
                ->table('pr_item')
                ->selectRaw("
                    pr_item.office_id,
                    TRIM(pr_item.pr_id) as pr_id,
                    pr_item.pr_item,
                    TRIM(pr_item.article_id) as article_id,
                    TRIM(pr_item.unit_meas) as unit_meas,
                    pr_item.qty,
                    pr_item.wgt,
                    pr_item.qty_po,
                    pr_item.wgt_po,
                    pr_item.qty_rcv,
                    pr_item.wgt_rcv,
                    pr_item.dt_req,
                    pr_item.remark,
                    TRIM(pr_item.contr_id) as contr_id,
                    pr_item.contr_item,
                    TRIM(pr_item.stat) as stat,
                    pr_hdr.dt_pr,
                    pr_hdr.dept_id,
                    pr_hdr.pic_id,
                    pr_hdr.stat,
                    pr_hdr.memo_txt,
                    article.description, 
                    article.uom, 
                    article.ppn, 
                    article.berat,
                    branch_office.office,
                    branch_office.address as office_address, 
                    branch_office.tlp as office_tlp
                ")
                ->join('pr_hdr', function($join){
                    $join->on('pr_item.office_id', '=', 'pr_hdr.office_id')
                    ->on('pr_item.pr_id', '=', 'pr_hdr.pr_id');
                })
                ->join('article', 'pr_item.article_id', '=', 'article.article_id')
                ->join('branch_office', function($join){
                    $join->on('pr_item.office_id', '=', 'branch_office.office_id');
                })
                ->whereRaw($sqlWhere)
                ->orderBy('pr_hdr.dt_pr', 'ASC')
                ->get();

        return $this->sendResponse($datas, 'Successfully get PR');
    }
}
