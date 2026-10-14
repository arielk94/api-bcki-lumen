<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SettingPriceController extends Controller
{


    public function ListPrice(Request $request)
    {
        try {

            $sqlWhere = "1=1";
            if ($request->region) {
                $sqlWhere .= " and a.region = '" . $request->region . "'";
            }

            if ($request->article_id) {
                $sqlWhere .= " and a.article_id = '" . $request->article_id . "'";
            }

            if ($request->cust_type) {
                $sqlWhere .= " and a.cust_type = '" . $request->cust_type . "'";
            }

            $datas = DB::connection('sqlSrvFPI')
                ->table('price_list as a')
                ->selectRaw("a.region, a.article_id, b.description, b.uom, a.art_price, a.avg_price, a.cust_type, c.str2 as cust_type_name,
                    a.price1, a.qty1, a.price2, a.qty2, a.price3, a.qty3, a.price4, a.qty4, 
                    a.price5, a.qty5, a.het, a.min_stock")
                ->leftJoin('article as b', 'a.article_id', '=', 'b.article_id')
                ->leftJoin('appl_constant as c', function ($join) {
                    $join->on('a.cust_type', '=', 'c.str1');
                    $join->where('c.key_code', '=', 'CUST_TYPE');
                })
                ->whereRaw($sqlWhere)
                ->get();

            return $this->sendResponse($datas, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function Region(Request $request)
    {
        try {
            $results = DB::connection('sqlSrvFPI')
                ->select(DB::raw("
                    SELECT DISTINCT TRIM(region) AS region
                    FROM branch_office"));

            return $this->sendResponse($results, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function GetArticle(Request $request)
    {
        try {
            $datas = DB::connection('sqlSrvFPI')
                ->select(DB::raw("
                    select article_id, description, uom
                    FROM article"));

            return $this->sendResponse($datas, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function GetPrice(Request $request)
    {
        try {
            $datas = DB::connection('sqlSrvFPI')
                ->select(DB::raw("
                    select a.region, a.article_id, b.description, b.uom, a.cust_type, 
                    case when a.art_price <=0 then c.last_price else a.art_price end as art_price,
                    case when a.avg_price <=0 then c.avg_price else a.avg_price end as avg_price,
                    price1, qty1, price2, qty2, price3, qty3, price4, qty4, price5, qty5, a.het, a.min_stock 
                    from price_list a join article b on a.article_id = b.article_id
                    left outer join (SELECT article_id, avg_price, last_price FROM (SELECT article_id, avg_price, last_price,
                    ROW_NUMBER() OVER (PARTITION BY article_id ORDER BY dt_modified DESC) AS rn
                    FROM inv_bal) x WHERE rn = 1) c on a.article_id = c.article_id
                    where a.region = '" . $request->region . "' and a.article_id = '" . $request->article_id . "'"));

            return $this->sendResponse($datas, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function GetSave(Request $request)
    {
        try {
            $datas = DB::connection('sqlSrvFPI')
                ->table('price_list')
                ->where('region', $request->region)
                ->where('article_id', $request->article_id)
                ->where('cust_type', $request->cust_type)
                ->first();

            if ($datas) {
                $update = DB::connection('sqlSrvFPI')
                    ->table('price_list')
                    ->where('region', $request->region)
                    ->where('article_id', $request->article_id)
                    ->where('cust_type', $request->cust_type)
                    ->update([
                        'art_price' => $request->art_price,
                        'avg_price' => $request->avg_price,
                        'price1' => $request->price1,
                        'qty1' => $request->qty1,
                        'price2' => $request->price2,
                        'qty2' => $request->qty2,
                        'price3' => $request->price3,
                        'qty3' => $request->qty3,
                        'price4' => $request->price4,
                        'qty4' => $request->qty4,
                        'price5' => $request->price5,
                        'qty5' => $request->qty5,
                        'het' => $request->het,
                        'min_stock' => $request->min_stock,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);
            } else {
                $insert = DB::connection('sqlSrvFPI')
                    ->table('price_list')
                    ->insert([
                        'region' => $request->region,
                        'article_id' => $request->article_id,
                        'art_price' => $request->art_price,
                        'avg_price' => $request->avg_price,
                        'cust_type' => $request->cust_type,
                        'price1' => $request->price1,
                        'qty1' => $request->qty1,
                        'price2' => $request->price2,
                        'qty2' => $request->qty2,
                        'price3' => $request->price3,
                        'qty3' => $request->qty3,
                        'price4' => $request->price4,
                        'qty4' => $request->qty4,
                        'price5' => $request->price5,
                        'qty5' => $request->qty5,
                        'het' => $request->het,
                        'min_stock' => $request->min_stock,
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2
                    ]);
            }
            return $this->sendResponse($datas, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function batchSetPrice(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataArray = $request->dataArray;
            if ($dataArray) {
                foreach ($dataArray as $key => $value) {
                    DB::connection('sqlSrvFPI')
                        ->table('price_list')
                        ->updateOrInsert([
                            'region' => $value['region'],
                            'article_id' => $value['article_id'],
                        ], [
                            'art_price' => $value['art_price'],
                            'avg_price' => $value['avg_price'],
                            'cust_type' => $value['cust_type'],
                            'price1' => $value['price1'],
                            'qty1' => $value['qty1'],
                            'price2' => $value['price2'],
                            'qty2' => $value['qty2'],
                            'price3' => $value['price3'],
                            'qty3' => $value['qty3'],
                            'price4' => $value['price4'],
                            'qty4' => $value['qty4'],
                            'price5' => $value['price5'],
                            'qty5' => $value['qty5'],
                            'het' => $value['het'],
                            'min_stock' => $value['min_stock'],
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);
                }
            }
            DB::commit();
            return $this->sendResponse([], 'Successfully get List');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->sendError($e->getMessage());
        }
    }

    public function getCustomerType()
    {
        try {
            $datas = DB::connection('sqlSrvFPI')
                ->table('appl_constant')
                ->where('key_code', 'CUST_TYPE')
                ->select('str1', 'str2')
                ->get();
            return $this->sendResponse($datas, 'Successfully get List');
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}