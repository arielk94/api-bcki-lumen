<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Str;
use Image, Storage;

class ArticleController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/fpi/article/getArticle",
     *     tags={"Master Article"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="article_id", type="string", example="ABC"),
     *             @OA\Property(property="cat_id", type="string", example="XYZ"),
     *             @OA\Property(property="unit_meas", type="string", example="ABC"),
     *             @OA\Property(property="active_flag", type="string", example="ABC"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all article",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function getArticle(Request $request)
    {
        $article_id = $request->article_id;
        $cat_id = $request->cat_id;
        $com_id = $request->com_id;
        $com_grp = $request->com_grp;
        $unit_meas = $request->unit_meas;
        $active_flag = $request->active_flag;
        $search = $request->search;

        $sqlWhere = "1=1";

        if ($article_id) {

            $sqlWhere .= " and ( article.article_id like '%$article_id%' OR article.description like '%$article_id%' )";
        }
        if ($cat_id) {

            $sqlWhere .= " and article.cat_id = '$cat_id'";
        }
        if ($com_id) {

            $sqlWhere .= " and article.com_id = '$com_id'";
        }
        if ($com_grp) {

            $sqlWhere .= " and article.com_grp = '$com_grp'";
        }
        if ($unit_meas) {

            $sqlWhere .= " and article.uom = '$unit_meas'";
        }
        if ($active_flag) {

            $sqlWhere .= " and article.active_flag = '$active_flag'";
        }
        if ($search) {

            $sqlWhere .= " and article.description = '$search'";
        }

        $datas = DB::connection('sqlSrvFPI')
            ->table('article')
            ->selectraw("
                TRIM(article.article_id) AS article_id,
                TRIM(article.description) AS description,
                article.cat_id,
                article.uom,
                article.add_id,
                article.tor_id,
                article.mku,
                article.min_stock,
                article.length,
                article.berat,
                article.ppn,
                article.az,
                article.grade,
                article.import_flag,
                article.active_flag,
                category.descr as cat_descr,
                category.consume_able,
                category.active_flag as cat_af,
                article.com_id,
                commodity.com_desc
            ")
            ->leftjoin('category', function ($join) {
                $join->on('article.cat_id', '=', 'category.cat_id');
            })
            ->leftjoin('commodity', function ($join) {
                $join->on('article.com_id', '=', 'commodity.com_id')
                    ->on('article.cat_id', '=', 'commodity.cat_id');
            })
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($datas, 'Successfully get article');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/article/getCategory",
     *     tags={"Master Article"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cat_id", type="string", example="XYZ"),
     *             @OA\Property(property="consume_able", type="string", example="ABC"),
     *             @OA\Property(property="active_flag", type="string", example="ABC"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all category",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getCategory(Request $request)
    {
        $cat_id = $request->cat_id;
        $consume_able = $request->consume_able;
        $active_flag = $request->active_flag;

        $sqlWhere = "1=1";

        if ($cat_id) {

            $sqlWhere .= " and cat_id = '$cat_id'";
        }
        if ($consume_able) {

            $sqlWhere .= " and consume_able = '$consume_able'";
        }
        if ($active_flag) {

            $sqlWhere .= " and active_flag = '$active_flag'";
        }

        $datas = DB::connection('sqlSrvFPI')
            ->table('category')
            ->selectraw("
                                    cat_id,
                                    TRIM(descr) as descr,
                                    TRIM(consume_able) as consume_able,
                                    active_flag
                                ")
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($datas, 'Successfully get category');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/article/getUOM",
     *     tags={"Master Article"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="active_flag", type="string", example="ABC"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all UOM",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getUOM(Request $request)
    {
        $active_flag = $request->active_flag;

        $sqlWhere = "1=1";

        if ($active_flag) {

            $sqlWhere .= " and active_flag = '$active_flag'";
        }

        $datas = DB::connection('sqlSrvFPI')
            ->table('unit_meas')
            ->selectraw("
                                    unit_meas,
                                    TRIM(descr) as descr,
                                    active_flag
                                ")
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($datas, 'Successfully get UOM');
    }

    private function genArtID($CustomerId) //10digit 
    {
        $dateYear = date("Y");
        $dateMonth = date("m");
        $gen_cluster_id = '';

        $getLastTrID = DB::connection('sqlSrvFPI')
            ->table('article')
            ->max('article_id');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -9) + 1;
            $gen_cluster_id = 'A' . str_pad($seq, 9, '0', STR_PAD_LEFT);
        } else {
            $gen_cluster_id = 'A' . '000000001';
        }

        return $gen_cluster_id;
    }

    private function genArticleID($cat_id)
    {
        $sqlWhere = "1=1";

        if ($cat_id) {
            $sqlWhere .= " and LEFT(CAST(article_id AS INT), 1) = '$cat_id'";
            // $sqlWhere.= " and cat_id = '$cat_id'";
        } else {
            $cat_id = '0';
            $sqlWhere .= " and LEFT(CAST(article_id AS INT), 1) = '0'";
        }

        $getLastArticleID =  DB::connection('sqlSrvFPI')
            ->table('article')
            ->whereRaw($sqlWhere)
            ->select(DB::raw('max(cast(article_id as int))'))
            ->value('max(cast(article_id as int))');

        if ($getLastArticleID) {
            $getLastArticleID = '0' . $getLastArticleID + 1;
        } else {
            $getLastArticleID = '0' . $cat_id . '000001';
        }
        return $getLastArticleID;
    }

    public function deleteArticle(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $sales = DB::connection('sqlSrvFPI')
                ->table('article')
                ->where('article_id', $request->article_id)
                ->first();

            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('article')
                    ->where('article_id', $request->article_id)
                    ->update([
                        'active_flag' => 'N',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,
                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Delete Article');
            } else {
                return $this->sendError('Article tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function activateArticle(Request $request)
    {
        DB::connection('sqlSrvFPI')->beginTransaction();

        try {

            $sales = DB::connection('sqlSrvFPI')
                ->table('article')
                ->where('article_id', $request->article_id)
                ->first();

            if ($sales) {
                $save = DB::connection('sqlSrvFPI')
                    ->table('article')
                    ->where('article_id', $request->article_id)
                    ->update([
                        'active_flag' => 'Y',
                        'dt_modified' => now(),
                        'modified_by' => getAuth($request)->user_id2,

                    ]);
                DB::connection('sqlSrvFPI')->commit();

                return $this->sendResponse('Success Update', 'Successfully Activate Article');
            } else {
                return $this->sendError('Article tidak ditemukan', 'Error', 500);
            }
        } catch (\Exception $e) {
            DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/article/getWhArticle",
     *     tags={"Master Article"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="active_flag", type="string", example="ABC"),
     *             @OA\Property(property="article_id", type="string", example="ABC"),
     *             @OA\Property(property="barcode", type="string", example="ABC"),
     *             @OA\Property(property="berat", type="string", example="ABC"),
     *             @OA\Property(property="add_id", type="string", example="ABC"),
     *             @OA\Property(property="tor_id", type="string", example="ABC"),
     *             @OA\Property(property="length", type="string", example="ABC"),
     *             @OA\Property(property="BarangJadiId", type="string", example="ABC"),
     *             @OA\Property(property="cat_id", type="string", example="ABC"),
     *             @OA\Property(property="com_grp", type="string", example="ABC"),
     *             @OA\Property(property="com_id", type="string", example="ABC"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all WH Article",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */

    public function getWhArticle(Request $request)
    {
        $where = "1=1";

        if ($request->article_id != null) {
            $article_id = $request->article_id;
        } else {
            $article_id = '';
        }
        if (!empty($article_id)) {
            $where = $where . "and ( article.article_id like " . "'%" . $article_id . "%' or article.description like '%$article_id%' )";
        }

        if (!empty($request->barcode)) {
            $where = $where . "and barcode = " . "'" . $request->barcode . "'";
        }

        if (!empty($request->berat)) {
            $where = $where . "and berat = " . "'" . $request->berat . "'";
        }

        if (!empty($request->add_id)) {
            $where = $where . "and add_id = " . "'" . $request->add_id . "'";
        }

        if (!empty($request->tor_id)) {
            $where = $where . "and tor_id = " . "'" . $request->tor_id . "'";
        }

        if (!empty($request->length)) {
            $where = $where . "and length = " . "'" . $request->length . "'";
        }

        if (!empty($request->BarangJadiId)) {
            $where = $where . "and article.BarangJadiId = " . "'" . $request->BarangJadiId . "'";
        }

        if (!empty($request->cat_id)) {
            $where = $where . "and article.cat_id = " . "'" . $request->cat_id . "'";
        }
        if (!empty($request->com_id)) {
            $where = $where . "and article.com_id = " . "'" . $request->com_id . "'";
        }
        if (!empty($request->com_grp)) {
            $where = $where . "and article.com_grp = " . "'" . $request->com_grp . "'";
        }
        if (!empty($request->active_flag)) {
            $where = $where . "and article.active_flag = " . "'" . $request->active_flag . "'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('article')
            ->selectraw("
                    trim(article.article_id) as article_id,
                    trim(article.description) as description,
                    trim(article.varian) as varian,
                    trim(article.uom) as uom,
                    article.berat,
                    article.cat_id,
                    article.com_id,
                    article.com_grp,
                    trim(article.mku) as mku,
                    trim(article.active_flag) as active_flag,                    
                    trim(article.BarangJadiId) as BarangJadiId,
                    article.berat,
                    trim(article.brand) as brand,
                    trim(article.add_id) as add_id,
                    trim(article.tor_id) as tor_id,
                    article.min_stock,
                    article.length,
                    trim(article.ppn) as ppn,
                    trim(article.import_flag) as import_flag,
                    trim(article.ac_num_persd) as ac_num_persd,
                    trim(article.ac_num_exp) as ac_num_exp,
                    trim(article.ac_num_penj) as ac_num_penj,
                    trim(article.ac_num_aktiva) as ac_num_aktiva,
                    trim(article.cost_ctr_id) as cost_ctr_id,
                    trim(category.descr) as cat_descr,
                    case
                    when
                        TRIM(commodity.com_desc) is not null then TRIM(commodity.com_desc)
                        else ''
                    end AS com_desc,
                    case
                    when
                        TRIM(commodity_grp.com_grp_desc) is not null then TRIM(commodity_grp.com_grp_desc)
                        else ''
                    end AS com_grp_desc,
                    (SELECT count(inv_trans_dtl.article_id) 
                    FROM inv_trans_dtl 
                    WHERE inv_trans_dtl.article_id = article.article_id) AS total_article,
                    (SELECT count(CustomerOrderDetil.article_id) 
                    FROM CustomerOrderDetil 
                    WHERE CustomerOrderDetil.article_id = article.article_id) AS total_article_order,
                    (SELECT count(po_item.article_id) 
                    FROM po_item 
                    WHERE po_item.article_id = article.article_id) AS total_article_po
                ")
            ->leftJoin('category', 'article.cat_id', '=', 'category.cat_id')
            ->leftJoin('commodity', function ($join) {
                $join->on('article.com_id', '=', 'commodity.com_id')
                    ->on('article.cat_id', '=', 'commodity.cat_id');
            })
            ->leftJoin('commodity_grp', function ($join) {
                $join->on('article.com_grp', '=', 'commodity_grp.com_grp')
                    ->on('article.com_id', '=', 'commodity_grp.com_id')
                    ->on('article.cat_id', '=', 'commodity_grp.cat_id');
            })
            ->whereRaw($where)
            ->where('article.article_id', '!=', '')
            ->where('article.cat_id', '!=', 4)
            ->orderBy('article_id', 'asc')
            ->get();

        return $this->sendResponse($result, 'Successfully get Article Jadi');
    }

    /**
     * @OA\Post(
     *     path="/api/fpi/article/getCommodity",
     *     tags={"Master Article"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cat_id", type="string", example="ABC"),
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all Commodity",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function getCommodity(Request $request)
    {
        $cat_id = $request->cat_id;

        $data = DB::connection("sqlSrvFPI")
            ->table('commodity')
            ->select([
                "com_id",
                "com_desc",
            ]);

        if ($cat_id) {
            $data->where('cat_id', $cat_id);
        }

        $result = $data->get();

        return $this->sendResponse($result, 'Successfully get Commidity');
    }

    // Master Article

    public function index(Request $request)
    {
        $where = "1=1";

        if ($request->article_id != null) {
            $article_id = $request->article_id;
        } else {
            $article_id = '';
        }
        if (!empty($article_id)) {
            $where = $where . "and ( article.article_id like " . "'%" . $article_id . "%' or article.description like '%$article_id%' )";
        }

        if (!empty($request->barcode)) {
            $where = $where . "and barcode = " . "'" . $request->barcode . "'";
        }

        if (!empty($request->berat)) {
            $where = $where . "and berat = " . "'" . $request->berat . "'";
        }

        if (!empty($request->add_id)) {
            $where = $where . "and add_id = " . "'" . $request->add_id . "'";
        }

        if (!empty($request->tor_id)) {
            $where = $where . "and tor_id = " . "'" . $request->tor_id . "'";
        }

        if (!empty($request->length)) {
            $where = $where . "and length = " . "'" . $request->length . "'";
        }

        if (!empty($request->BarangJadiId)) {
            $where = $where . "and BarangJadiId = " . "'" . $request->BarangJadiId . "'";
        }

        if (!empty($request->cat_id)) {
            $where = $where . "and article.cat_id = " . "'" . $request->cat_id . "'";
        }
        if (!empty($request->com_id)) {
            $where = $where . "and article.com_id = " . "'" . $request->com_id . "'";
        }
        if (!empty($request->com_grp)) {
            $where = $where . "and article.com_grp = " . "'" . $request->com_grp . "'";
        }
        if (!empty($request->active_flag)) {
            $where = $where . "and article.active_flag = " . "'" . $request->active_flag . "'";
        }
        if (!empty($request->internal_brand)) {
            // $where = $where . "and article.brand = ". "'" . $request->brand . "' and article.internal_brand = ". "'" . $request->brand . "'";
            $where = $where . "and article.brand = " . "'" . $request->internal_brand . "'";
        }
        //vendor_id
        if (!empty($request->vendor_id)) {
            $where = $where . "and article.vendor_id = " . "'" . $request->vendor_id . "'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('article')
            ->selectraw("
                    trim(article.article_id) as article_id,
                    trim(article.description) as description,
                    trim(article.varian) as varian,
                    trim(article.uom) as uom,
                    article.berat,
                    article.cat_id,
                    article.com_id,
                    article.com_grp,
                    trim(article.mku) as mku,
                    trim(article.active_flag) as active_flag,                    
                    trim(article.BarangJadiId) as BarangJadiId,
                    article.berat,
                    trim(article.brand) as brand,
                    trim(article.add_id) as add_id,
                    trim(article.tor_id) as tor_id,
                    article.min_stock,
                    article.length,
                    trim(article.ppn) as ppn,
                    trim(article.import_flag) as import_flag,
                    trim(article.ac_num_persd) as ac_num_persd,
                    trim(article.ac_num_exp) as ac_num_exp,
                    trim(article.ac_num_penj) as ac_num_penj,
                    trim(article.ac_num_aktiva) as ac_num_aktiva,
                    trim(article.cost_ctr_id) as cost_ctr_id,
                    trim(category.descr) as cat_descr,
                    case
                    when
                        TRIM(commodity.com_desc) is not null then TRIM(commodity.com_desc)
                        else ''
                    end AS com_desc,
                    case
                    when
                        TRIM(commodity_grp.com_grp_desc) is not null then TRIM(commodity_grp.com_grp_desc)
                        else ''
                    end AS com_grp_desc,
                    trim(article.vendor_description) as vendor_description,
                    trim(article.internal_brand) as internal_brand,
                    trim(article.vendor_id) as vendor_id,
                    trim(vendor.vendor_name) as vendor_name,
                    trim(article.article_id_supp) as article_id_supp,
                    TRIM(eksternal.descr) AS nama_brand_eksternal,
		            TRIM(internal.descr) AS nama_brand_internal,
                    trim(article.az) as az,
                    trim(article.grade) as grade
                ")
            ->leftJoin('category', 'article.cat_id', '=', 'category.cat_id')
            ->leftJoin('commodity', function ($join) {
                $join->on('article.com_id', '=', 'commodity.com_id')
                    ->on('article.cat_id', '=', 'commodity.cat_id');
            })
            ->leftJoin('commodity_grp', function ($join) {
                $join->on('article.com_grp', '=', 'commodity_grp.com_grp')
                    ->on('article.com_id', '=', 'commodity_grp.com_id')
                    ->on('article.cat_id', '=', 'commodity_grp.cat_id');
            })
            ->leftJoin('brand as eksternal', 'article.brand', '=', 'eksternal.brand_id')
            ->leftJoin('brand as internal', 'article.internal_brand', '=', 'internal.brand_id')
            ->leftJoin('vendor', 'article.vendor_id', '=', 'vendor.vendor_id')
            ->whereRaw($where)
            ->where('article.article_id', '!=', '')
            ->orderBy('article_id', 'asc')
            ->get();
        return $this->sendResponse($result, 'Successfully get Article Jadi');
    }

    public function showCategory()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('category')
            ->selectraw("cat_id,descr")
            ->where('active_flag', 'Y')
            ->get();
        return $this->sendResponse($result, 'Successfully get category');
    }

    public function showCommodity(Request $request)
    {
        $sqlWhere = "1=1";
        $cat_id = $request->cat_id;

        if ($cat_id) {
            $sqlWhere .= " and cat_id = '$cat_id'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('commodity')
            ->selectraw("cat_id, com_id, com_desc")
            ->whereraw($sqlWhere)
            ->orderBy('commodity.com_id', 'asc')
            ->get();
        return $this->sendResponse($result, 'Successfully get commodity');
    }

    public function showCommodityGrp(Request $request)
    {
        $sqlWhere = "1=1";
        $cat_id = $request->cat_id;
        $com_id = $request->com_id;

        if ($cat_id and $com_id) {
            $sqlWhere .= " and cat_id = '$cat_id' and com_id = '$com_id'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('commodity_grp')
            ->selectraw("cat_id, com_id, com_grp, com_grp_desc")
            ->whereraw($sqlWhere)
            ->orderBy('com_grp', 'asc')
            ->get();
        return $this->sendResponse($result, 'Successfully get commodity group');
    }

    public function showArticleID()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('article')
            ->selectraw("article_id, barcode, description, TRIM(varian) as varian")
            ->where('active_flag', 'Y')
            ->get();
        return $this->sendResponse($result, 'Successfully get acticle');
    }

    public function showBrandID(Request $request)
    {
        $sqlWhere = "1=1";
        $vendor_id = $request->vendor_id;

        if ($vendor_id) {
            $sqlWhere .= " and brand.vendor_id = '$vendor_id'";
        }

        $result = DB::connection('sqlSrvFPI')
            ->table('brand')
            ->selectraw("trim(brand.brand_id) as brand_id, trim(brand.descr) as descr, trim(brand.vendor_id) as vendor_id, trim(vendor.vendor_name) as vendor_name")
            ->leftJoin('vendor', 'brand.vendor_id', '=', 'vendor.vendor_id')
            ->where('brand.active_flag', 'Y')
            ->whereraw($sqlWhere)
            ->get();
        return $this->sendResponse($result, 'Successfully get brand');
    }

    public function showUOM()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('appl_constant')
            ->selectraw("str1,str2")
            ->where('key_code', 'UOM')
            ->get();
        return $this->sendResponse($result, 'Successfully get unit measurement');
    }

    public function showACnum()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('g_ledger')
            ->selectraw("
                TRIM(ac_num) AS ac_num,
                TRIM(ac_name) AS ac_name,
                trim(drcr) as drcr")
            ->where('office_id', '1')
            ->where('active_flag', 'Y')
            ->get();
        return $this->sendResponse($result, 'Successfully get AC number');
    }

    public function showCostCenter()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('cost_center')
            ->selectraw("
                TRIM(cost_ctr_id) AS cost_ctr_id,
                TRIM(descr) AS descr")
            // ->where('office_id', '1')
            ->where('active_flag', 'Y')
            ->get();
        return $this->sendResponse($result, 'Successfully get Cost Center');
    }

    public function showBarangJadiID()
    {
        $result = DB::connection('sqlSrvKmb')
            ->table('BarangJadi')
            ->selectraw("
                    BarangJadiId,NamaBarang,Satuan
                ")
            ->get();
        return $this->sendResponse($result, 'Successfully get Barang Jadi');
    }

    public function showVendor()
    {
        $result = DB::connection('sqlSrvFPI')
            ->table('vendor')
            ->selectraw("
                TRIM(vendor_id) AS vendor_id,
                TRIM(vendor_name) AS vendor_name")
            ->get();
        return $this->sendResponse($result, 'Successfully get Vendor');
    }

    public function saveArticle(Request $request)
    {
        try {
            $data = DB::connection('sqlSrvFPI')
                ->table('article')
                ->where('article_id', $request->article_id)
                ->first();

            if ($data) {
                $update = DB::connection('sqlSrvFPI')
                    ->table('article')
                    ->where('article_id', $request->article_id)
                    ->update([
                        'barcode' => $request->barcode ? $request->barcode : ' ',
                        'description' => $request->description ? $request->description : ' ',
                        'varian' => $request->varian ? $request->varian : ' ',
                        'mku' => $request->mku ? $request->mku : ' ',
                        'uom' => $request->uom ? $request->uom : ' ',
                        'uom2' => $request->uom2 ? $request->uom2 : ' ',
                        'uom_qty2' => $request->uom_qty2 ? $request->uom_qty2 : ' ',
                        'uom3' => $request->uom3 ? $request->uom3 : ' ',
                        'uom_qty3' => $request->uom_qty3 ? $request->uom_qty3 : ' ',
                        'uom4' => $request->uom4 ? $request->uom4 : ' ',
                        'uom_qty4' => $request->uom_qty4 ? $request->uom_qty4 : ' ',
                        'uom5' => $request->uom5 ? $request->uom5 : ' ',
                        'uom_qty5' => $request->uom_qty5 ? $request->uom_qty5 : ' ',
                        'ppn' => $request->ppn ? $request->ppn : ' ',
                        'brand' => $request->brand ? $request->brand : ' ',
                        'cat_id' => $request->cat_id ? $request->cat_id : ' ',
                        'com_id' => $request->com_id ? $request->com_id : ' ',
                        'com_grp' => $request->com_grp ? $request->com_grp : ' ',
                        'min_stock' => $request->min_stock ? $request->min_stock : ' ',
                        'berat' => $request->berat ? $request->berat : ' ',
                        'import_flag' => $request->import_flag ? $request->import_flag : ' ',
                        'user_id' => getAuth($request)->user_id2,
                        'img' => $request->img ? $request->img : ' ',
                        'consumable' => $request->consumable ? $request->consumable : ' ',
                        'ac_num_persd' => $request->ac_num_persd ? $request->ac_num_persd : ' ',
                        'ac_num_exp' => $request->ac_num_exp ? $request->ac_num_exp : ' ',
                        'ac_num_penj' => $request->ac_num_penj ? $request->ac_num_penj : ' ',
                        'ac_num_aktiva' => $request->ac_num_aktiva ? $request->ac_num_aktiva : ' ',
                        'cost_ctr_id' => $request->cost_ctr_id ? $request->cost_ctr_id : ' ',
                        'active_flag' => $request->active_flag ? $request->active_flag : 'Y',
                        'dt_modified' => now(),
                        'BarangJadiId' => $request->BarangJadiId ? $request->BarangJadiId : ' ',
                        'add_id' => $request->add_id ? $request->add_id : ' ',
                        'tor_id' => $request->tor_id ? $request->tor_id : ' ',
                        'length' => $request->length ? $request->length : ' ',
                        'vendor_description' => $request->vendor_description ? $request->vendor_description : ' ',
                        'internal_brand' => $request->internal_brand ? $request->internal_brand : ' ',
                        'vendor_id' => $request->vendor_id ? $request->vendor_id : ' ',
                        'article_id_supp' => $request->article_id_supp ? $request->article_id_supp : ' ', //prodcode
                        'az' => $request->az ? $request->az : ' ',
                        'grade' => $request->grade ? $request->grade : ' ',
                        'modified_by' => getAuth($request)->user_id2
                    ]);
                return $this->sendResponse('Success edit', 'Successfully edit Article');
            } else {
                $article_id = $this->genArticleID($request->cat_id);
                $insert = DB::connection('sqlSrvFPI')
                    ->table('article')
                    ->insert([
                        'article_id' => $article_id,
                        'barcode' => $request->barcode ? $request->barcode : ' ',
                        'description' => $request->description ? $request->description : ' ',
                        'varian' => $request->varian ? $request->varian : ' ',
                        'mku' => $request->mku ? $request->mku : ' ',
                        'uom' => $request->uom ? $request->uom : ' ',
                        'uom2' => $request->uom2 ? $request->uom2 : ' ',
                        'uom_qty2' => $request->uom_qty2 ? $request->uom_qty2 : ' ',
                        'uom3' => $request->uom3 ? $request->uom3 : ' ',
                        'uom_qty3' => $request->uom_qty3 ? $request->uom_qty3 : ' ',
                        'uom4' => $request->uom4 ? $request->uom4 : ' ',
                        'uom_qty4' => $request->uom_qty4 ? $request->uom_qty4 : ' ',
                        'uom5' => $request->uom5 ? $request->uom5 : ' ',
                        'uom_qty5' => $request->uom_qty5 ? $request->uom_qty5 : ' ',
                        'ppn' => $request->ppn ? $request->ppn : ' ',
                        'brand' => $request->brand ? $request->brand : ' ',
                        'cat_id' => $request->cat_id ? $request->cat_id : ' ',
                        'com_id' => $request->com_id ? $request->com_id : ' ',
                        'com_grp' => $request->com_grp ? $request->com_grp : ' ',
                        'min_stock' => $request->min_stock ? $request->min_stock : ' ',
                        'berat' => $request->berat ? $request->berat : ' ',
                        'import_flag' => $request->import_flag ? $request->import_flag : ' ',
                        'user_id' => getAuth($request)->user_id2,
                        'img' => $request->img ? $request->img : ' ',
                        'consumable' => $request->consumable ? $request->consumable : ' ',
                        'ac_num_persd' => $request->ac_num_persd ? $request->ac_num_persd : ' ',
                        'ac_num_exp' => $request->ac_num_exp ? $request->ac_num_exp : ' ',
                        'ac_num_penj' => $request->ac_num_penj ? $request->ac_num_penj : ' ',
                        'ac_num_aktiva' => $request->ac_num_aktiva ? $request->ac_num_aktiva : ' ',
                        'cost_ctr_id' => $request->cost_ctr_id ? $request->cost_ctr_id : ' ',
                        'active_flag' => $request->active_flag ? $request->active_flag : 'Y',
                        'dt_modified' => now(),
                        'BarangJadiId' => $request->BarangJadiId ? $request->BarangJadiId : ' ',
                        'add_id' => $request->add_id ? $request->add_id : ' ',
                        'tor_id' => $request->tor_id ? $request->tor_id : ' ',
                        'length' => $request->length ? $request->length : ' ',
                        'vendor_description' => $request->vendor_description ? $request->vendor_description : ' ',
                        'internal_brand' => $request->internal_brand ? $request->internal_brand : ' ',
                        'vendor_id' => $request->vendor_id ? $request->vendor_id : ' ',
                        'article_id_supp' => $request->article_id_supp ? $request->article_id_supp : ' ', //prodcode
                        'az' => $request->az ? $request->az : ' ',
                        'grade' => $request->grade ? $request->grade : ' ',
                        'modified_by' => getAuth($request)->user_id2
                    ]);
                return $this->sendResponse('Success Insert', 'Successfully insert Article');
            }
        } catch (\Exception $e) {
            // DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
