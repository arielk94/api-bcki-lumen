<?php

namespace App\Http\Controllers\warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockQueryController extends Controller
{
    public function stockQuery(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $wh_id = $request->wh_id;
            $cat_id = $request->cat_id;
            $com_id = $request->com_id;
            $article_id = $request->article_id;
            $available = $request->available;

            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.wh_id = ". "'" . $wh_id . "'";
            }

            if (!empty($cat_id))
            {
                $sqlWhere = $sqlWhere . "and article.cat_id = ". "'" . $cat_id . "'";
            }

            if (!empty($com_id))
            {
                $sqlWhere = $sqlWhere . "and article.com_id = ". "'" . $com_id . "'";
            }

            if (!empty($article_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.article_id = ". "'" . $article_id . "'";
            }

            if ($available=='Yes'){
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";
            }else if ($available=='No'){
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh <= 0";
            }else{
                
            }

            // $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('inv_bal')
                                ->selectRaw("
                                inv_bal.office_id, 
                                branch_office.office, 
                                inv_bal.article_id, 
                                article.description, 
                                inv_bal.length,
                                inv_bal.qty_oh, 
                                article.uom, 
                                inv_bal.wh_id,
                                whouse.descr,
                                inv_bal.add_id, 
                                inv_bal.tor_id, 
                                inv_bal.bin_id, 
                                article.brand, 
                                article.cat_id, 
                                article.com_id,
                                category.descr as cat_desc, 
	                            commodity.com_desc
                                ")
                                ->leftjoin('branch_office', 'inv_bal.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('article', 'inv_bal.article_id', '=', 'article.article_id')
                                // ->leftjoin('whouse', 'inv_bal.wh_id', '=', 'whouse.wh_id')
                                ->leftjoin('whouse', function ($join) {
                                    $join->on('inv_bal.wh_id', '=', 'whouse.wh_id')
                                         ->on('branch_office.office_id', '=', 'whouse.office_id');
                                })
                                ->leftjoin('category', 'article.cat_id', '=', 'category.cat_id')
                                ->leftjoin('commodity', function ($join) {
                                    $join->on('article.com_id', '=', 'commodity.com_id')
                                         ->on('category.cat_id', '=', 'commodity.cat_id');
                                })
                                ->whereRaw($sqlWhere)
                                // ->orderBy('wh_bal.dt_modified', 'desc')
                                ->get();

            return $this->sendResponse($datas, 'Successfully get Stock Query (Inv Bal) Warehouse');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }

    public function getListCategory(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $wh_id = $request->wh_id;

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and category.wh_id = ". "'" . $wh_id . "'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('category')
                                ->selectRaw("
                                category.*
                                ")
                                ->whereRaw($sqlWhere)
                                ->get();

            return $this->sendResponse($datas, 'Successfully get Category');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListCommodity(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $cat_id = $request->cat_id;

            if (!empty($cat_id))
            {
                $sqlWhere = $sqlWhere . "and commodity.cat_id = ". "'" . $cat_id . "'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('commodity')
                                ->selectRaw("
                                commodity.*
                                ")
                                ->whereRaw($sqlWhere)
                                ->get();

            return $this->sendResponse($datas, 'Successfully get commodity');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getListArticle(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $cat_id = $request->cat_id;
            $com_id = $request->com_id;

            if (!empty($cat_id))
            {
                $sqlWhere = $sqlWhere . "and article.cat_id = ". "'" . $cat_id . "'";
            }

            if (!empty($com_id))
            {
                $sqlWhere = $sqlWhere . "and article.com_id = ". "'" . $com_id . "'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('article')
                                ->selectRaw("
                                article.*
                                ")
                                ->whereRaw($sqlWhere)
                                ->get();

            return $this->sendResponse($datas, 'Successfully get article');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function eksporStockQuery(Request $request)
    {
        try {
            $sqlWhere = "1=1";

            $office_id = $request->office_id;
            $wh_id = $request->wh_id;
            $cat_id = $request->cat_id;
            $com_id = $request->com_id;
            $article_id = $request->article_id;
            $available = $request->available;

            if (!empty($office_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.office_id = ". "'" . $office_id . "'";
            }

            if (!empty($wh_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.wh_id = ". "'" . $wh_id . "'";
            }

            if (!empty($cat_id))
            {
                $sqlWhere = $sqlWhere . "and article.cat_id = ". "'" . $cat_id . "'";
            }

            if (!empty($com_id))
            {
                $sqlWhere = $sqlWhere . "and article.com_id = ". "'" . $com_id . "'";
            }

            if (!empty($article_id))
            {
                $sqlWhere = $sqlWhere . "and inv_bal.article_id = ". "'" . $article_id . "'";
            }

            if ($available=='Yes'){
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";
            }else if ($available=='No'){
                $sqlWhere = $sqlWhere . "and inv_bal.qty_oh <= 0";
            }else{
                
            }

            // $sqlWhere = $sqlWhere . "and inv_bal.qty_oh > 0";

            $datas = DB::connection("sqlSrvBCKI")
                                ->table('inv_bal')
                                ->selectRaw("
                                branch_office.office AS 'CABANG', 
                                whouse.descr AS 'GUDANG',
                                category.descr AS 'KATEGORI',
	                            commodity.com_desc AS 'KOMODITI',
                                inv_bal.article_id AS 'ARTIKEL ID', 
                                article.description AS 'ARTIKEL', 
                                inv_bal.length AS 'LENGTH',
                                inv_bal.qty_oh AS 'STOK', 
                                article.uom AS 'SATUAN'
                                ")
                                ->leftjoin('branch_office', 'inv_bal.office_id', '=', 'branch_office.office_id')
                                ->leftjoin('article', 'inv_bal.article_id', '=', 'article.article_id')
                                ->leftjoin('whouse', function ($join) {
                                    $join->on('inv_bal.wh_id', '=', 'whouse.wh_id')
                                         ->on('branch_office.office_id', '=', 'whouse.office_id');
                                })
                                ->leftjoin('category', 'article.cat_id', '=', 'category.cat_id')
                                ->leftjoin('commodity', function ($join) {
                                    $join->on('article.com_id', '=', 'commodity.com_id')
                                         ->on('category.cat_id', '=', 'commodity.cat_id');
                                })
                                ->whereRaw($sqlWhere)
                                ->get();

            return $this->sendResponse($datas, 'Successfully get Stock Query (Inv Bal) Warehouse');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
        
    }
}
