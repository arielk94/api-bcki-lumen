<?php

namespace App\Http\Controllers\purchasing;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Gd\Commands\InsertCommand;

class ComparisonPriceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sqlWhere = "1=1";
            $id = $request->id;
            $start_date = $request->start_date;
            if (!$start_date) {
                $start_date = '';
            }

            $end_date = $request->end_date;
            if (!$end_date) {
                $end_date = '';
            }

            if ($id == 'S') {
                if ($start_date) {
                    if ($end_date) {
                        $sqlWhere .= " and submit_po.dt_trx BETWEEN CONVERT(datetime,'" . $start_date . " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                    } else {
                        $sqlWhere .= " and submit_po.dt_trx BETWEEN CONVERT(datetime,'" . $start_date . " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
                    }
                }
            } else {
                if ($start_date) {
                    if ($end_date) {
                        $sqlWhere .= " and submit_po.finalize_date BETWEEN CONVERT(datetime,'" . $start_date . " 00:00:00:001') AND CONVERT(datetime,'" . $end_date . " 23:59:59:000')";
                    } else {
                        $sqlWhere .= " and submit_po.finalize_date BETWEEN CONVERT(datetime,'" . $start_date . " 00:00:00:001') AND CONVERT(datetime,'" . $start_date . " 23:59:59:000')";
                    }
                }
            }

            $arrayReusable = [];

            $datas = DB::connection("sqlSrvBCKI")
                ->table('submit_po')
                ->selectRaw("
                        submit_po.office_id,submit_po.trx_id, submit_po.dt_trx, submit_po.finalize_date, submit_po.note, branch_office.office, submit_po.finalized_by, submit_po.type_code, 
                        submit_po.vendor1, 
                        submit_po.vendor_name1, 
                        submit_po.ppn1, 
                        submit_po.amt_dics1, 
                        submit_po.deliv_cost1, 
                        submit_po.pay_term1, 
                        submit_po.merk1, 
                        submit_po.garansi1, 
                        submit_po.deliv_time1, 
                        submit_po.lead_time1, 
                        submit_po.deliv_place1, 
                        submit_po.stock1, 
                        submit_po.vendor2, 
                        submit_po.vendor_name2, 
                        submit_po.ppn2, 
                        submit_po.amt_dics2, 
                        submit_po.deliv_cost2, 
                        submit_po.pay_term2, 
                        submit_po.merk2, 
                        submit_po.garansi2, 
                        submit_po.deliv_time2, 
                        submit_po.lead_time2, 
                        submit_po.deliv_place2, 
                        submit_po.stock2, 
                        submit_po.vendor3, 
                        submit_po.vendor_name3, 
                        submit_po.ppn3, 
                        submit_po.amt_dics3, 
                        submit_po.deliv_cost3, 
                        submit_po.pay_term3, 
                        submit_po.merk3, 
                        submit_po.garansi3, 
                        submit_po.deliv_time3, 
                        submit_po.lead_time3, 
                        submit_po.deliv_place3, 
                        submit_po.stock3, 
                        submit_po.vendor4, 
                        submit_po.vendor_name4, 
                        submit_po.ppn4, 
                        submit_po.amt_dics4, 
                        submit_po.deliv_cost4, 
                        submit_po.pay_term4, 
                        submit_po.merk4, 
                        submit_po.garansi4, 
                        submit_po.deliv_time4, 
                        submit_po.lead_time4, 
                        submit_po.deliv_place4, 
                        submit_po.stock4, 
                        submit_po.vendor5, 
                        submit_po.vendor_name5, 
                        submit_po.ppn5, 
                        submit_po.amt_dics5, 
                        submit_po.deliv_cost5, 
                        submit_po.pay_term5, 
                        submit_po.merk5, 
                        submit_po.garansi5, 
                        submit_po.deliv_time5, 
                        submit_po.lead_time5, 
                        submit_po.deliv_place5,
                        submit_po.stock5,
                        submit_po.note,
                        submit_po.user_id,
                        submit_po.dt_created
                    ")
                ->leftjoin('branch_office as branch_office', 'submit_po.office_id', '=', 'branch_office.office_id')
                ->whereRaw($sqlWhere)
                ->orderBy('submit_po.dt_trx', 'desc')
                ->get();

            foreach ($datas as $key => $value) {
                array_push($arrayReusable, array_merge((array) $value, ['pr_id' => $this->getItem($value)]));
            }

            return $this->sendResponse($arrayReusable, 'Successfully get comparison price');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function getItem($value)
    {
        $arrayReusable = [];

        $datas = DB::connection("sqlSrvBCKI")
            ->table('submit_po_item')
            ->where('office_id', $value->office_id)
            ->where('trx_id', $value->trx_id)
            ->orderBy('num', 'ASC')
            ->get();

        foreach ($datas as $key => $value) {
            array_push($arrayReusable, $value->pr_id);
        }
        return implode(', ', $arrayReusable);
    }

    public function show(Request $request)
    {
        $payloads = $request->only(['office_id', 'trx_id', 'pr_id', 'article_id']);
        try {
            $sqlRaw = "submit_po_item.office_id,
                submit_po_item.trx_id,
                submit_po_item.num,
                submit_po_item.pr_id,
                submit_po_item.pr_item,
                submit_po_item.article_id,
                article.description,
                submit_po_item.remark,
                submit_po_item.qty,
                submit_po_item.unit_meas,
                submit_po_item.last_vendor_id,
                submit_po_item.last_dt_trans,
                submit_po_item.last_price,
                submit_po_item.cek1,
                submit_po_item.price1 as price1,
                NULLIF(submit_po_item.nego1, 0) as nego1,
                submit_po_item.price1 * submit_po_item.qty as total_price1,
                NULLIF(submit_po_item.nego1, 0) * submit_po_item.qty as total_nego1,
                SUM(inv_bal.qty_oh) AS qty_oh,
                submit_po_item.cek2,
                submit_po_item.price2 as price2,
                NULLIF(submit_po_item.nego2, 0) as nego2,
                submit_po_item.price2 * submit_po_item.qty as total_price2,
                NULLIF(submit_po_item.nego2, 0) * submit_po_item.qty as total_nego2,

                submit_po_item.cek3,
                submit_po_item.price3 as price3,
                NULLIF(submit_po_item.nego3, 0) as nego3,
                submit_po_item.price3 * submit_po_item.qty as total_price3,
                NULLIF(submit_po_item.nego3, 0) * submit_po_item.qty as total_nego3,

                submit_po_item.cek4,
                submit_po_item.price4 as price4,
                NULLIF(submit_po_item.nego4, 0) as nego4,
                submit_po_item.price4 * submit_po_item.qty as total_price4,
                NULLIF(submit_po_item.nego4, 0) * submit_po_item.qty as total_nego4,

                submit_po_item.cek5,
                submit_po_item.price5 as price5,
                NULLIF(submit_po_item.nego5, 0) as nego5,
                submit_po_item.price5 * submit_po_item.qty as total_price5,
                NULLIF(submit_po_item.nego5, 0) * submit_po_item.qty as total_nego5,

                submit_po_item.aprroved_vendor,
                submit_po_item.approved_price,
                submit_po_item.approved_date,
                submit_po_item.approved_by";
            $groupByRaw = "submit_po_item.office_id,
                submit_po_item.trx_id,
                submit_po_item.num,
                submit_po_item.pr_id,
                submit_po_item.pr_item,
                submit_po_item.article_id,
                article.description,
                submit_po_item.remark,
                submit_po_item.qty,
                submit_po_item.unit_meas,
                submit_po_item.last_vendor_id,
                submit_po_item.last_dt_trans,
                submit_po_item.last_price,
                submit_po_item.cek1,
                submit_po_item.price1,
                NULLIF ( submit_po_item.nego1, 0 ),
                submit_po_item.price1 * submit_po_item.qty,
                NULLIF ( submit_po_item.nego1, 0 ) * submit_po_item.qty,
                submit_po_item.cek2,
                submit_po_item.price2,
                NULLIF ( submit_po_item.nego2, 0 ),
                submit_po_item.price2 * submit_po_item.qty,
                NULLIF ( submit_po_item.nego2, 0 ) * submit_po_item.qty,
                submit_po_item.cek3,
                submit_po_item.price3,
                NULLIF ( submit_po_item.nego3, 0 ),
                submit_po_item.price3 * submit_po_item.qty,
                NULLIF ( submit_po_item.nego3, 0 ) * submit_po_item.qty,
                submit_po_item.cek4,
                submit_po_item.price4,
                NULLIF ( submit_po_item.nego4, 0 ),
                submit_po_item.price4 * submit_po_item.qty,
                NULLIF ( submit_po_item.nego4, 0 ) * submit_po_item.qty,
                submit_po_item.cek5,
                submit_po_item.price5,
                NULLIF ( submit_po_item.nego5, 0 ),
                submit_po_item.price5 * submit_po_item.qty,
                NULLIF ( submit_po_item.nego5, 0 ) * submit_po_item.qty,
                submit_po_item.aprroved_vendor,
                submit_po_item.approved_price,
                submit_po_item.approved_date,
                submit_po_item.approved_by";
            $query = DB::connection('sqlSrvBCKI')
                ->table('submit_po_item')
                ->selectRaw($sqlRaw)
                ->leftjoin('article', function ($join) {
                    $join->on('submit_po_item.article_id', '=', 'article.article_id');
                })
                ->leftjoin('inv_bal', function ($join) {
                    $join->on('submit_po_item.article_id', '=', 'inv_bal.article_id')
                        ->where('inv_bal.spk_id', '=', '');
                });

            foreach (['office_id', 'trx_id', 'pr_id', 'article_id'] as $field) {
                if (!empty($payloads[$field])) {
                    $query = $query->where("submit_po_item.$field", $payloads[$field]);
                }
            }
            $data = $query->groupbyraw($groupByRaw)->get();

            return $this->sendResponse($data, 'Successfully get comparison price');
        } catch (\Exception $e) {
            return $this->sendResponse('Error', 500, $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        // ['office_id', 'trx_id', 'items', 'approve']
        $submit_po = DB::connection('sqlSrvBCKI')
            ->table('submit_po')
            ->where('office_id', $request->office_id)
            ->where('trx_id', $request->trx_id)
            ->first();

        if ($submit_po) {
            if ($submit_po->finalize_date != '1900-01-01 00:00:00.000') {
                return $this->sendError('Comparison price already finalized', 'Comparison price already finalized', 500);
            }

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {

                foreach ($request->items as $key => $value) {
                    $value = (object) $value;
                    $vendor = '';
                    $price = 0;

                    if ($request->approve) {
                        if ($value->cek1 == 'Y') {
                            $vendor = $submit_po->vendor1;
                            $price = $value->price1;
                        }
                        if ($value->cek2 == 'Y') {
                            $vendor = $submit_po->vendor2;
                            $price = $value->price2;
                        }
                        if ($value->cek3 == 'Y') {
                            $vendor = $submit_po->vendor3;
                            $price = $value->price3;
                        }
                        if ($value->cek4 == 'Y') {
                            $vendor = $submit_po->vendor4;
                            $price = $value->price4;
                        }
                        if ($value->cek5 == 'Y') {
                            $vendor = $submit_po->vendor5;
                            $price = $value->price5;
                        }
                    }

                    DB::connection('sqlSrvBCKI')->update(
                        "UPDATE submit_po_item WITH (ROWLOCK)
                        SET cek1 = ?, cek2 = ?, cek3 = ?, cek4 = ?, cek5 = ?, approved_date = ?, approved_by = ?, aprroved_vendor = ?, approved_price = ?, dt_modified = ?, user_id = ?
                        WHERE office_id = ? AND trx_id = ? AND num = ?",
                        [
                            $value->cek1,
                            $value->cek2,
                            $value->cek3,
                            $value->cek4,
                            $value->cek5,
                            $request->approve ? now() : '1900-01-01 00:00:00.000',
                            $request->approve ? getAuth($request)->user_id2 : '',
                            $request->approve ? $vendor : ' ',
                            $request->approve ? $price : 0,
                            now(),
                            getAuth($request)->user_id2,
                            $request->office_id,
                            $request->trx_id,
                            (int) $value->num
                        ]
                    );
                }

                if ($request->approve) {
                    DB::connection('sqlSrvBCKI')->update(
                        "UPDATE submit_po WITH (ROWLOCK)
                        SET finalize_date = ?, finalized_by = ?
                        WHERE office_id = ? AND trx_id = ?",
                        [
                            now(),
                            getAuth($request)->user_id2,
                            $request->office_id,
                            $request->trx_id
                        ]
                    );
                }

                DB::connection('sqlSrvBCKI')->commit();

                $submit_po = DB::connection('sqlSrvBCKI')
                    ->table('submit_po')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->first();

                return $this->sendResponse($submit_po, 'Successfully saved');
            } catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        } else {
            return $this->sendError('ID Transaksi tidak ditemukan', 'Error', 500);
        }
    }

    public function unapprove(Request $request)
    {
        // ['office_id', 'trx_id', 'items', 'approve']
        $submit_po = DB::connection('sqlSrvBCKI')
            ->table('submit_po')
            ->where('office_id', $request->office_id)
            ->where('trx_id', $request->trx_id)
            ->first();

        if ($submit_po) {

            $poId = DB::connection("sqlSrvBCKI")
                ->table('po_item')
                ->selectRaw('po_id')
                ->where('office_id', $request->office_id)
                ->where('trx_id', $request->trx_id)
                ->first();
            if ($poId) {
                return $this->sendError('Comparison price already raised into PO', 'Comparison price already raised into PO', 500);
            }

            DB::connection('sqlSrvBCKI')->beginTransaction();

            try {
                DB::connection("sqlSrvBCKI")->table('submit_po_item')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->update([
                        'approved_date' => '1900-01-01 00:00:00.000',
                        'approved_by' => "",
                        'dt_modified' => now(),
                        'user_id' => getAuth($request)->user_id2,
                    ]);
                DB::connection("sqlSrvBCKI")->table('submit_po')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->update(['finalize_date' => '1900-01-01 00:00:00.000', 'finalized_by' => ""]);

                DB::connection('sqlSrvBCKI')->commit();

                $submit_po = DB::connection('sqlSrvBCKI')
                    ->table('submit_po')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->first();

                return $this->sendResponse($submit_po, 'Successfully saved');
            } catch (\Exception $e) {
                DB::connection('sqlSrvBCKI')->rollBack();
                return $this->sendError($e->getMessage(), 'Error', 500);
            }
        } else {
            return $this->sendError('ID Transaksi tidak ditemukan', 'Error', 500);
        }
    }

    //mulai dari sini untun insert dan update comparison price

    public function getSppoId(Request $request)
    {
        try {
            $SppoId = 'SPPO/' . $request->office_id . '/' . getRomawi(date('m', strtotime($request->dt_trx))) . '/' . substr(date('Y', strtotime($request->dt_trx)), -2) . '/';

            $GenTrId = DB::connection("sqlSrvBCKI")
                ->table('submit_po')
                ->selectRaw('RIGHT(TRIM(MAX(trx_id)), 3) + 1 as trx_id')
                ->where('office_id', $request->office_id)
                ->where('trx_id', 'like', $SppoId . '%')
                ->first();

            if ($GenTrId && $GenTrId->trx_id) {
                $TrId = str_pad($GenTrId->trx_id, 3, '0', STR_PAD_LEFT);
            } else {
                $TrId = '001';
            }

            $SppoId .= $TrId;
            $trx_id = $SppoId;
            return $this->sendResponse($trx_id, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getSppoNum(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('submit_po_item')
                ->selectRaw('isnull(max(num),0) + 1  as num')
                ->where('trx_id', $request->Trx_Id)
                ->first();

            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getLastPrice(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('po_item as a')
                ->selectRaw('b.dt_po, b.po_id, b.vendor_id, c.vendor_name, a.article_id, d.description, a.unit_price, b.tax1')
                ->join('po_hdr as b', function ($join) {
                    $join->on('a.po_id', '=', 'b.po_id')
                        ->on('a.office_id', '=', 'b.office_id');
                })
                ->join('vendor as c', function ($join) {
                    $join->on('b.vendor_id', '=', 'c.vendor_id');
                })
                ->join('article as d', function ($join) {
                    $join->on('a.article_id', '=', 'd.article_id');
                })
                ->where('a.article_id', '=', $request->article_id)
                ->orderby('b.dt_po', 'desc')
                ->first();

            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getPrHdr(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('pr_item as a')
                ->selectRaw('DISTINCT a.office_id, a.pr_id, b.dt_pr, b.dept_id, g.dept_name, b.pic_id, h.pic_name')
                ->join('pr_hdr as b', function ($join) {
                    $join->on('a.pr_id', '=', 'b.pr_id')
                        ->on('a.office_id', '=', 'b.office_id');
                })
                ->join('article as c', function ($join) {
                    $join->on('a.article_id', '=', 'c.article_id');
                })

                ->leftjoin('submit_po_item as f', function ($join) {
                    $join->on('a.office_id', '=', 'f.office_id')
                        ->on('a.pr_id', '=', 'f.pr_id')
                        ->on('a.pr_item', '=', 'f.pr_item');
                })
                ->leftjoin('department as g', function ($join) {
                    $join->on('b.dept_id', '=', 'g.dept_id');
                })
                ->leftjoin('pr_req_user as h', function ($join) {
                    $join->on('b.pic_id', '=', 'h.pic_id');
                })
                ->where('a.office_id', $request->office_id)
                ->where('a.stat', 'O')
                ->where('f.trx_id', '=', null)
                ->get();

            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getPrDtl(request $request)
    {
        try {
            $sqlWhere = "1=1";
            if ($request->pr_id) {
                $sqlWhere = $sqlWhere . "and a.pr_id = '$request->pr_id'";
            }

            $datas = DB::connection("sqlSrvBCKI")
                ->table('pr_item as a')
                ->selectRaw('a.pr_id, a.pr_item, a.article_id, b.description, a.qty, a.unit_meas, a.remark')
                ->join('article as b', function ($join) {
                    $join->on('a.article_id', '=', 'b.article_id');
                })
                ->leftjoin('submit_po_item as f', function ($join) {
                    $join->on('a.office_id', '=', 'f.office_id')
                        ->on('a.pr_id', '=', 'f.pr_id')
                        ->on('a.pr_item', '=', 'f.pr_item');
                })
                ->leftjoin('category as c', function ($join) {
                    $join->on('b.cat_id', '=', 'c.cat_id');
                })
                ->leftjoin('commodity as d', function ($join) {
                    $join->on('b.com_id', '=', 'd.com_id');
                })
                ->join('pr_hdr as e', function ($join) {
                    $join->on('a.pr_id', '=', 'e.pr_id')
                        ->on('a.office_id', '=', 'e.office_id');
                })
                ->where('a.office_id', $request->office_id)
                ->where('a.stat', 'O')
                ->where('e.type_pr', '!=', 'N')
                ->where('f.trx_id', '=', null)
                ->whereRaw($sqlWhere)
                ->get();

            //dd( $datas);
            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getPayTermId(Request $request)
    {
        try {
            $datas = DB::connection("sqlSrvBCKI")
                ->table('pay_term')
                ->selectRaw('pay_term_id, pay_term_desc, num_of_days')
                ->get();

            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    public function getSaveSppo(Request $request)
    {
        try {
            $getppn = DB::connection("sqlSrvBCKI")
                ->table('appl_constant')
                ->selectRaw('str3')
                ->where('key_code', 'PPN')
                ->first();

            $datas = DB::connection("sqlSrvBCKI")
                ->table('submit_po_item')
                ->selectRaw('trx_id, num')
                ->where('office_id', $request->office_id)
                ->where('trx_id', $request->trx_id)
                ->where('num', $request->num)
                ->first();

            if (!$datas) {
                $datas = DB::connection("sqlSrvBCKI")
                    ->table('submit_po_item')
                    ->insert([
                        'office_id'      => $request->office_id ?? '',
                        'trx_id'         => $request->trx_id ?? '',
                        'num'            => $request->num ?? 0,
                        'dt_created'     => DB::raw('getdate()'),
                        'pr_id'          => $request->pr_id ?? '',
                        'pr_item'        => $request->pr_item ?? '',
                        'article_id'     => $request->article_id ?? '',
                        'remark'         => $request->remark ?? '',
                        'qty'            => $request->qty ?? 0,
                        'unit_meas'      => $request->unit_meas ?? '',
                        'last_vendor_id' => $request->last_vendor_id ?? '',
                        'last_dt_trans'  => $request->last_dt_trans ?? '',
                        'last_price'     => $request->last_price ?? 0,
                        'price1'         => $request->price1 ?? 0,
                        'nego1'          => $request->nego1 ?? 0,
                        'price2'         => $request->price2 ?? 0,
                        'nego2'          => $request->nego2 ?? 0,
                        'price3'         => $request->price3 ?? 0,
                        'nego3'          => $request->nego3 ?? 0,
                        'price4'         => $request->price4 ?? 0,
                        'nego4'          => $request->nego4 ?? 0,
                        'price5'         => $request->price5 ?? 0,
                        'nego5'          => $request->nego5 ?? 0,
                        'dt_modified'    => DB::raw('getdate()'),
                        'user_id'        => getAuth($request)->user_id2 ?? ''
                    ]);

            } else {
                $datas = DB::connection("sqlSrvBCKI")
                    ->table('submit_po_item')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->where('num', $request->num)
                    ->update([
                        'pr_id'          => $request->pr_id ?? '',
                        'pr_item'        => $request->pr_item ?? '',
                        'article_id'     => $request->article_id ?? '',
                        'remark'         => $request->remark ?? '',
                        'qty'            => $request->qty ?? 0,
                        'unit_meas'      => $request->unit_meas ?? '',
                        'last_vendor_id' => $request->last_vendor_id ?? '',
                        'last_dt_trans'  => $request->last_dt_trans ?? '',
                        'last_price'     => $request->last_price ?? 0,
                        'price1'         => $request->price1 ?? 0,
                        'nego1'          => $request->nego1 ?? 0,
                        'price2'         => $request->price2 ?? 0,
                        'nego2'          => $request->nego2 ?? 0,
                        'price3'         => $request->price3 ?? 0,
                        'nego3'          => $request->nego3 ?? 0,
                        'price4'         => $request->price4 ?? 0,
                        'nego4'          => $request->nego4 ?? 0,
                        'price5'         => $request->price5 ?? 0,
                        'nego5'          => $request->nego5 ?? 0,
                        'dt_modified'    => DB::raw('getdate()'),
                        'user_id'        => getAuth($request)->user_id2 ?? ''
                    ]);
            }

            $datas = DB::connection("sqlSrvBCKI")
                ->table('submit_po')
                ->selectRaw('trx_id')
                ->where('office_id', $request->office_id)
                ->where('trx_id', $request->trx_id)
                ->first();
            if (!$datas) {
                $datas = DB::connection("sqlSrvBCKI")
                    ->table('submit_po')
                    ->insert([
                        'office_id'         => $request->office_id ?? 0,
                        'trx_id'            => $request->trx_id ?? ' ',
                        'dt_trx'            => $request->dt_trx ?? '1900-01-01 00:00:00.000',
                        'dt_created'        => DB::raw('getdate()') ?? '1900-01-01 00:00:00.000',
                        'dt_modified'       => DB::raw('getdate()') ?? '1900-01-01 00:00:00.000',
                        'user_id'           => getAuth($request)->user_id2 ?? ' ',
                        'vendor1'           => $request->vendor1 ?? ' ',
                        'vendor2'           => $request->vendor2 ?? ' ',
                        'vendor3'           => $request->vendor3 ?? ' ',
                        'vendor4'           => $request->vendor4 ?? ' ',
                        'vendor5'           => $request->vendor5 ?? ' ',
                        'vendor_name1'      => $request->vendor_name1 ?? ' ',
                        'vendor_name2'      => $request->vendor_name2 ?? ' ',
                        'vendor_name3'      => $request->vendor_name3 ?? ' ',
                        'vendor_name4'      => $request->vendor_name4 ?? ' ',
                        'vendor_name5'      => $request->vendor_name5 ?? ' ',
                        'ppn1'              => ($request->ppn1 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn2'              => ($request->ppn2 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn3'              => ($request->ppn3 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn4'              => ($request->ppn4 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn5'              => ($request->ppn5 == 'Y') ? ($getppn ?? 0) : 0,
                        'amt_disc1'         => $request->amt_disc1 ?? 0,
                        'amt_disc2'         => $request->amt_disc2 ?? 0,
                        'amt_disc3'         => $request->amt_disc3 ?? 0,
                        'amt_disc4'         => $request->amt_disc4 ?? 0,
                        'amt_disc5'         => $request->amt_disc5 ?? 0,
                        'deliv_cost1'       => $request->deliv_cost1 ?? 0,
                        'deliv_cost2'       => $request->deliv_cost2 ?? 0,
                        'deliv_cost3'       => $request->deliv_cost3 ?? 0,
                        'deliv_cost4'       => $request->deliv_cost4 ?? 0,
                        'deliv_cost5'       => $request->deliv_cost5 ?? 0,
                        'pay_term1'         => $request->pay_term1 ?? ' ',
                        'pay_term2'         => $request->pay_term2 ?? ' ',
                        'pay_term3'         => $request->pay_term3 ?? ' ',  
                        'pay_term4'         => $request->pay_term4 ?? ' ',
                        'pay_term5'         => $request->pay_term5 ?? ' ',
                        'merk1'             => $request->merk1 ?? ' ',
                        'merk2'             => $request->merk2 ?? ' ',
                        'merk3'             => $request->merk3 ?? ' ',
                        'merk4'             => $request->merk4 ?? ' ',
                        'merk5'             => $request->merk5 ?? ' ',
                        'garansi1'          => $request->garansi1 ?? ' ',
                        'garansi2'          => $request->garansi2 ?? ' ',
                        'garansi3'          => $request->garansi3 ?? ' ',
                        'garansi4'          => $request->garansi4 ?? ' ',
                        'garansi5'          => $request->garansi5 ?? ' ',
                        'lead_time1'        => $request->lead_time1 ?? ' ',
                        'lead_time2'        => $request->lead_time2 ?? ' ',
                        'lead_time3'        => $request->lead_time3 ?? ' ',
                        'lead_time4'        => $request->lead_time4 ?? ' ',
                        'lead_time5'        => $request->lead_time5 ?? ' ',
                        'deliv_time1'       => $request->deliv_time1 ?? ' ',
                        'deliv_time2'       => $request->deliv_time2 ?? ' ',
                        'deliv_time3'       => $request->deliv_time3 ?? ' ',
                        'deliv_time4'       => $request->deliv_time4 ?? ' ',
                        'deliv_time5'       => $request->deliv_time5 ?? ' ',
                        'deliv_place1'      => $request->deliv_place1 ?? ' ',
                        'deliv_place2'      => $request->deliv_place2 ?? ' ',
                        'deliv_place3'      => $request->deliv_place3 ?? ' ',
                        'deliv_place4'      => $request->deliv_place4 ?? ' ',
                        'deliv_place5'      => $request->deliv_place5 ?? ' ',
                        'stock1'            => $request->stock1 ?? ' ',
                        'stock2'            => $request->stock2 ?? ' ',
                        'stock3'            => $request->stock3 ?? ' ',
                        'stock4'            => $request->stock4 ?? ' ',
                        'stock5'            => $request->stock5 ?? ' ',
                        'note'              => $request->note ?? ' ',
                        'type_code'         => 'N'
                    ]);
            } else {
                $datas = DB::connection("sqlSrvBCKI")
                    ->table('submit_po')
                    ->where('office_id', $request->office_id)
                    ->where('trx_id', $request->trx_id)
                    ->update([
                        'dt_modified'       => DB::raw('getdate()') ?? '1900-01-01 00:00:00.000',
                        'user_id'           => getAuth($request)->user_id2 ?? ' ',
                        'vendor1'           => $request->vendor1 ?? ' ',
                        'vendor2'           => $request->vendor2 ?? ' ',
                        'vendor3'           => $request->vendor3 ?? ' ',
                        'vendor4'           => $request->vendor4 ?? ' ',
                        'vendor5'           => $request->vendor5 ?? ' ',
                        'vendor_name1'      => $request->vendor_name1 ?? ' ',
                        'vendor_name2'      => $request->vendor_name2 ?? ' ',
                        'vendor_name3'      => $request->vendor_name3 ?? ' ',
                        'vendor_name4'      => $request->vendor_name4 ?? ' ',
                        'vendor_name5'      => $request->vendor_name5 ?? ' ',
                        'ppn1'              => ($request->ppn1 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn2'              => ($request->ppn2 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn3'              => ($request->ppn3 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn4'              => ($request->ppn4 == 'Y') ? ($getppn ?? 0) : 0,
                        'ppn5'              => ($request->ppn5 == 'Y') ? ($getppn ?? 0) : 0,
                        'amt_disc1'         => $request->amt_disc1 ?? 0,
                        'amt_disc2'         => $request->amt_disc2 ?? 0,
                        'amt_disc3'         => $request->amt_disc3 ?? 0,
                        'amt_disc4'         => $request->amt_disc4 ?? 0,
                        'amt_disc5'         => $request->amt_disc5 ?? 0,
                        'deliv_cost1'       => $request->deliv_cost1 ?? 0,
                        'deliv_cost2'       => $request->deliv_cost2 ?? 0,
                        'deliv_cost3'       => $request->deliv_cost3 ?? 0,
                        'deliv_cost4'       => $request->deliv_cost4 ?? 0,
                        'deliv_cost5'       => $request->deliv_cost5 ?? 0,
                        'pay_term1'         => $request->pay_term1 ?? ' ',
                        'pay_term2'         => $request->pay_term2 ?? ' ',
                        'pay_term3'         => $request->pay_term3 ?? ' ',  
                        'pay_term4'         => $request->pay_term4 ?? ' ',
                        'pay_term5'         => $request->pay_term5 ?? ' ',
                        'merk1'             => $request->merk1 ?? ' ',
                        'merk2'             => $request->merk2 ?? ' ',
                        'merk3'             => $request->merk3 ?? ' ',
                        'merk4'             => $request->merk4 ?? ' ',
                        'merk5'             => $request->merk5 ?? ' ',
                        'garansi1'          => $request->garansi1 ?? ' ',
                        'garansi2'          => $request->garansi2 ?? ' ',
                        'garansi3'          => $request->garansi3 ?? ' ',
                        'garansi4'          => $request->garansi4 ?? ' ',
                        'garansi5'          => $request->garansi5 ?? ' ',
                        'lead_time1'        => $request->lead_time1 ?? ' ',
                        'lead_time2'        => $request->lead_time2 ?? ' ',
                        'lead_time3'        => $request->lead_time3 ?? ' ',
                        'lead_time4'        => $request->lead_time4 ?? ' ',
                        'lead_time5'        => $request->lead_time5 ?? ' ',
                        'deliv_time1'       => $request->deliv_time1 ?? ' ',
                        'deliv_time2'       => $request->deliv_time2 ?? ' ',
                        'deliv_time3'       => $request->deliv_time3 ?? ' ',
                        'deliv_time4'       => $request->deliv_time4 ?? ' ',
                        'deliv_time5'       => $request->deliv_time5 ?? ' ',
                        'deliv_place1'      => $request->deliv_place1 ?? ' ',
                        'deliv_place2'      => $request->deliv_place2 ?? ' ',
                        'deliv_place3'      => $request->deliv_place3 ?? ' ',
                        'deliv_place4'      => $request->deliv_place4 ?? ' ',
                        'deliv_place5'      => $request->deliv_place5 ?? ' ',
                        'stock1'            => $request->stock1 ?? ' ',
                        'stock2'            => $request->stock2 ?? ' ',
                        'stock3'            => $request->stock3 ?? ' ',
                        'stock4'            => $request->stock4 ?? ' ',
                        'stock5'            => $request->stock5 ?? ' ',
                        'note'              => $request->note ?? ' ',
                        'type_code'         => 'N'
                    ]);
            }
            return $this->sendResponse($datas, 'Successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
}
