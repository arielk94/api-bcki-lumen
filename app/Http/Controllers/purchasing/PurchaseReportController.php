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

class PurchaseReportController extends Controller
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
        $GArticleId = $this->getArticleId("");
        $GIdSite = $this->getIdSite("");
        $getAuth = getAuth($request);
        $currId = $this->getCurrId("");

        return $this->sendResponse([
            'vendor' => $GVendor,
            'office' => $GOffice,
            'department' => $GDepartment,
            'article' => $GArticleId,
            'site' => $GIdSite,
            'userId' => $getAuth,
            'currency' => $currId,
        ], 'Successful');
    }

    public function getMaster(Request $request)
    {

        switch ($request->type) {
            case 'article':

                if ($request->value) {
                    $article = $this->getArticleId($request->value);
                }

                return response()->json(['article' => $article], 200);

                break;

            case 'vendor':

                if ($request->value) {
                    $vendor = $this->getArticleId($request->value);
                }

                return response()->json(['vendor' => $vendor], 200);

                break;

            default:
                # code...
                break;
        }
    }

    public function GetPurchaseHistory(Request $request)
    {
        try {

            $sqlWhere = '1=1 ';
            $dateColumns = [
                'PR'  => 'dt_pr',
                'PO'  => 'dt_po',
                'RCV' => 'dt_rcv',
                'INV' => 'dt_inv',
                'PAY' => 'dt_pay',
            ];

            // -- required
            if (empty($dateColumns[$request->type_date])) {
                return $this->sendError("Type date is required", 'Error', 500);
            }

            if (!empty($request->type_date) && !empty($dateColumns[$request->type_date]) && $request->dt_awal) {
                $col = $dateColumns[$request->type_date];
                if (!empty($request->dt_akhir)) {
                    $sqlWhere .= "and {$col} between '" . $request->dt_awal . "' and '" . $request->dt_akhir . "' ";
                } else {
                    $sqlWhere .= "and {$col} = '" . $request->dt_awal . "' ";
                }
            }

            if ($request->pr_id) {
                $sqlWhere .= "and pr_id = '$request->pr_id'";
            }
            if ($request->office_id) {
                $sqlWhere .= "and office_id = '$request->office_id'";
            }
            if ($request->dept_id) {
                $sqlWhere .= "and dept_id = '$request->dept_id'";
            }
            if ($request->vendor_id) {
                $sqlWhere .= "and vendor_id = '$request->vendor_id'";
            }

            if ($request->article_id) {
                $sqlWhere .= "and article_id = '$request->article_id'";
            }
            if ($request->remark) {
                $sqlWhere .= "and remark like '%$request->remark%'";
            }
            if ($request->po_id) {
                $sqlWhere .= "and po_id = '$request->po_id'";
            }
            if ($request->invid) {
                $sqlWhere .= "and inv_id = '$request->inv_id'";
            }
            if ($request->id_site) {
                $sqlWhere .= "and id_site = '$request->id_site'";
            }
            if ($request->id_cluster) {
                $sqlWhere .= "and id_cluster = '$request->id_cluster'";
            }
            if ($request->id_house) {
                $sqlWhere .= "and id_house = '$request->id_house'";
            }
            if ($request->pay_id) {
                $sqlWhere .= "and pay_id = '$request->pay_id'";
            }
            if ($request->type_pr == "P") {             //// type "P" = payment request
                $sqlWhere .= "and type_pr = 'N'";
            } else if ($request->type_pr == "R") {      //// type "R" = purchase request
                $sqlWhere .= "and type_pr != 'N'";
            }

            if ($request->status_akhir) {
                if ($request->status_akhir == "PR") {
                    $sqlWhere .= "and po_id is null and inv_id is null and pay_id is null ";
                }
                if ($request->status_akhir == "PO") {
                    $sqlWhere .= "and po_id is not null and purc_rcv_id is null and inv_id is null and pay_id is null ";
                }
                if ($request->status_akhir == "RCV") {
                    $sqlWhere .= "and purc_rcv_id is not null and inv_id is null and pay_id is null ";
                }
                if ($request->status_akhir == "INV") {
                    $sqlWhere .= "and inv_id is not null and pay_id is null ";
                }
                if ($request->status_akhir == "PAY") {
                    $sqlWhere .= "and pay_id is not null ";
                }
            }


            $datas = DB::connection("sqlSrvFPI")
                ->table('v_purchase_history')
                ->whereRaw($sqlWhere)
                ->orderBy('dt_pr', 'asc')
                ->orderBy('pr_id', 'asc')
                ->orderBy('pr_item', 'asc')
                ->get();

            return $this->sendResponse($datas, 'Successful');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function UpdateProgressPr(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('pr_item')
                ->where('pr_id', $request->pr_id)
                ->where('pr_item', $request->pr_item)
                ->update([
                    'remark2' => $request->remark2,
                    'dt_modified' => now(),
                    'user_id' => getAuth($request)->user_id2
                ]);

            return $this->sendResponse($datas, 'Successful');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function UpdateProgressPo(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvFPI")
                ->table('po_item')
                ->where('po_id', $request->po_id)
                ->where('po_item', $request->po_item)
                ->update([
                    'remark2' => $request->remark2,
                    'dt_modified' => now(),
                    'user_id' => getAuth($request)->user_id2
                ]);

            return $this->sendResponse($datas, 'Successful');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getMonitoring(Request $request)
    {
        try {
            $pr_id = $request->pr_id ?? '';

            $connection = DB::connection("sqlSrvFPI");

            // === PR ===
            $pr = $connection->select(DB::raw("
                SELECT DISTINCT dt_pr, pr_id, dept_id, 
                    CASE WHEN type_pr != 'N' THEN 'Purchase Request' ELSE 'Payment Request' END AS type_pr, 
                    pr_item, description, remark, qty_pr, unit_meas, vendor_name, site_name, 
                    cluster_name, house_name, dt_aprv_pr, dt_aprv_pr2, progress_pr
                FROM v_purchase_history
                WHERE pr_id = '$pr_id'
                ORDER BY pr_id, pr_item ASC
            "));

            // === PO ===
            $po = $connection->select(DB::raw("
                SELECT DISTINCT dt_po, po_id, vendor_name, po_item, description,
                    pr_id, pr_item, qty_po, unit_meas, dt_aprv_po, dt_aprv_po2, progress_po
                FROM v_purchase_history
                WHERE pr_id = '$pr_id' AND po_id IS NOT NULL
                ORDER BY po_id, po_item ASC
            "));

            // === RCV ===
            $rcv = $connection->select(DB::raw("
                SELECT DISTINCT dt_rcv, purc_rcv_id, seq_num, description, po_id, po_item, qty_rcv, unit_meas
                FROM v_purchase_history
                WHERE pr_id = '$pr_id' AND purc_rcv_id IS NOT NULL
                ORDER BY purc_rcv_id, seq_num
            "));

            // === INV ===
            $inv = $connection->select(DB::raw("
                SELECT DISTINCT dt_inv, inv_id, inv_item, pr_id, pr_item, purc_rcv_id, seq_num, qty_inv, unit_meas
                FROM v_purchase_history
                WHERE pr_id = '$pr_id' AND inv_id IS NOT NULL
                ORDER BY inv_id, inv_item
            "));

            // === PAY ===
            $pay = $connection->select(DB::raw("
                SELECT DISTINCT dt_pay, pay_id, inv_id
                FROM v_purchase_history
                WHERE pr_id = '$pr_id' AND pay_id IS NOT NULL
                ORDER BY pay_id
            "));

            $datas = [
                'pr'  => $pr,
                'po'  => $po,
                'rcv' => $rcv,
                'inv' => $inv,
                'pay' => $pay,
            ];

            return $this->sendResponse($datas, 'Successful');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
