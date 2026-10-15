<?php

namespace App\Http\Controllers\finance;

use App\Traits\getFilterTrait;
use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    use getFilterTrait;
    protected $serviceAcc;

    public function __construct(AccountingService $serviceAcc)
    {
        $this->serviceAcc = $serviceAcc;
    }
    public function index(Request $request)
    {
        $customer_id = $request->customer_id;
        $start_date = $request->inv_date ?: '';
        $end_date = $request->inv_end_date ?: '';
        $due_start_date = $request->inv_due ?: '';
        $due_end_date = $request->inv_end_due ?: '';
        $pay_term = $request->payterm_id ?: '';
        $inv_stat = $request->invstatus ?: '';
        $inv_id = $request->invid ?: '';
        $inv_type = $request->invtype ?: '';
        $id_site = $request->site_id ?: '';
        $id_house = $request->house_id ?: '';
        $id_cluster = $request->cluster_id ?: '';
        $sqlWhere = "1=1";

        if ($id_site) {
            $sqlWhere .= " AND  d.id_site = '$id_site' ";
        }
        if ($id_cluster) {
            $sqlWhere .= " AND t.id_cluster = '$id_cluster' ";
        }
        if ($id_house) {
            $sqlWhere .= " AND h.id_house = '$id_house' ";
        }
        if ($inv_stat) {
            $sqlWhere .= " AND a.pelunasan = '$inv_stat' ";
        }
        if ($inv_type) {
            $sqlWhere .= " AND a.inv_type = '$inv_type' ";
        }
        if ($pay_term) {
            $sqlWhere .= " AND d.pay_method = '$pay_term' ";
        }
        if ($customer_id) {
            $sqlWhere .= " AND a.customerid = '$customer_id' ";
        }
        if ($inv_id) {
            $sqlWhere .= " AND a.salesid = '$inv_id' ";
        }
        if ($start_date) {
            if ($end_date) {
                $sqlWhere .= " AND a.tgljual BETWEEN CONVERT(datetime, '$start_date 00:00:00.001') AND CONVERT(datetime, '$end_date 23:59:59.000')";
            } else {
                $sqlWhere .= " AND a.tgljual BETWEEN CONVERT(datetime, '$start_date 00:00:00.001') AND CONVERT(datetime, '$start_date 23:59:59.000')";
            }
        }
        if ($due_start_date) {
            if ($due_end_date) {
                $sqlWhere .= " AND a.tgljtempo BETWEEN CONVERT(datetime, '$due_start_date 00:00:00.001') AND CONVERT(datetime, '$due_end_date 23:59:59.000')";
            } else {
                $sqlWhere .= " AND a.tgljtempo BETWEEN CONVERT(datetime, '$due_start_date 00:00:00.001') AND CONVERT(datetime, '$due_end_date 23:59:59.000')";
            }
        }
        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            SELECT b.namacustomer,a.penjualanno,a.tgljual,a.tgljtempo,a.totaljual,c.pay_amt,a.totaljual-c.pay_amt receivables,s.site_name,t.cluster_name,h.house_name,a.inv_type,a.kpr_type 
            FROM  penjualan a 
            inner JOIN customer b ON a.customerid= b.customerid
            inner join payment_outstanding c on a.penjualanno=c.penjualanno 
            inner join customerorder d on a.customerorderno=d.ORDER_ID 
            inner join customerorderdetil cd on cd.order_id=d.order_id 
            left outer join site s on s.id_site=d.id_site 
            left outer join site_cluster t on d.id_site=t.id_site and cd.id_cluster=t.id_cluster 
            left outer join house_unit h on d.id_site=h.id_site and cd.id_cluster=h.id_cluster and cd.id_house=h.id_house 
             WHERE $sqlWhere
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }


    public function store(Request $request)
    {

        $inv_id = $this->generateInvoice($request->inv_date, $request->inv_type);
        $userId = getAuth($request)->user_id2 ?? '';
        $detilData = [];
        foreach ($request->detail as $key => $value) {
            $value = (object) $value;
            $detilData[] = $this->buildDetailInvoice($inv_id, $value, $key);
        }
        try {

            DB::connection("sqlSrvBCKI")->beginTransaction();

            $this->insertInvoice($inv_id, $request, $userId);
            DB::connection("sqlSrvBCKI")->table('penjualandetil')->insert($detilData);
            $this->insertPaymentOutstanding($inv_id, $request->CustomerOrderNo, $request->TotalJual);

            DB::connection("sqlSrvBCKI")->commit();

            $tanggal = Carbon::parse($request->inv_date);
            $tahun4digit = $tanggal->format('Y');
            //dd($tahun4digit);

            $this->serviceAcc->JrnlCustomerInvoice(1,  $inv_id, $tahun4digit, $userId);

            return $this->sendResponse($inv_id, 'Successfully saved');
        } catch (\Exception $e) {
            DB::connection("sqlSrvBCKI")->rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function destroy(Request $request)
    {
        $inv_id = $request->inv_id;
        $userId = getAuth($request)->user_id2 ?? null;
        if ($this->hasVoucher($inv_id)) {
            return $this->sendError("Remove payment posting before delete invoice", 'Error', 500);
        }
        if (empty($inv_id)) {
            return $this->sendError("Empty invoice id", 'Error', 500);
        }

        try {
            $result = DB::connection("sqlSrvBCKI")->table('penjualan')
                ->selectRaw("isnull(tgljual,'') tr_date")
                ->where('penjualanno', $inv_id)
                ->first();
            $tr_date = $result->tr_date ?? now();
            if ($this->serviceAcc->ifAcctClosed($tr_date, $request)) {
                return $this->sendError("This periode already closed, cannot be modified", 'Error', 500);
            }
            DB::connection("sqlSrvBCKI")->transaction(function () use ($inv_id) {
                DB::connection("sqlSrvBCKI")->table('penjualandetil')
                    ->where('penjualanno', $inv_id)
                    ->delete();
                DB::connection("sqlSrvBCKI")->table('penjualan')
                    ->where('penjualanno', $inv_id)
                    ->delete();
                // DB::connection("sqlSrvBCKI")->table('SuratJalan')
                //     ->where('penjualanno', $inv_id)
                //     ->delete();
                //if (!empty($data['tr_id'])) {
                DB::connection("sqlSrvBCKI")->table('payment_outstanding')
                    ->where('penjualanno', $inv_id)
                    ->delete();
                //}
                // DB::connection("sqlSrvBCKI")->table('deliv_hdr')
                //     ->where('inv_id', $inv_id)
                //     ->update([
                //         'inv_id' => ''
                //     ]);
            });

            $tanggal = Carbon::parse($result->tr_date);
            $tahun4digit = $tanggal->format('Y');

            $this->serviceAcc->JrnlCustomerInvoice(1,  $inv_id, $tahun4digit, $userId);

            return $this->sendResponse(null, 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function show(Request $request)
    {
        try {
            $sqlWhere = " a.penjualanno= '$request->inv_id' ";
            $datas = DB::select(DB::raw("
             SELECT trim(a.PenjualanNO) inv_id,trim(a.CustomerOrderNo) CustomerOrderNo,trim(d.po_cust) po_cust,a.TglJual,trim(f.CustomerName) CustomerName,
             trim(f.address1) address1,trim(f.address2) address2,trim(d.nik) nik,'730000' kode_barang,trim(c.NamaBarang) NamaBarang,
            'Rp. ' + format(round(c.HargaJual/(1+a.ppn_val/100),2) ,'N2') + ' X ' + format(c.Lembar,'N0') + ' ' + trim(g.SatuanLembar) + ' X  ' 
            + format(c.Panjang,'N2') + ' ' + g.SatuanPanjang + ' (' + format(c.Panjang * c.Lembar,'N2') + ')' as namabarangbawah,
            case when c.price_mode='M'
            then
                round(c.HargaJual/(1+a.ppn_val/100),2)*c.Lembar*c.Panjang
            else
                round(c.HargaJual/(1+a.ppn_val/100),2)*c.Lembar
            end  subtotal,tgljtempo,trim(e.kota) kota,trim(e.NamaSales) NamaSales,
            case when d.pay_term= 'CASH BEFORE DELIVERY' 
            then 
                'LUNAS SEBELUM KIRIM'
            else
                trim(replace(d.pay_term,'DAYS','HARI'))
            end pay_term,a.ppn_val,a.discount
            FROM
            Penjualan a INNER JOIN Customer b ON
                a.CustomerId = b.CustomerId
            INNER JOIN  PenjualanDetil c ON
                a.PenjualanNO = c.PenjualanNO
            INNER JOIN CustomerOrder d ON
                a.CustomerOrderNo = d.CustomerOrderNo
            INNER JOIN Sales e ON
                a.SalesId = e.SalesId
            INNER JOIN customer_nik_npwp f ON
                d.nik = f.tr_id
            INNER JOIN BarangJadi g ON
                c.BarangJadiId = g.BarangJadiId
            WHERE $sqlWhere
            ORDER BY
            a.PenjualanNO ASC,
            c.NamaBarang ASC,
            c.HargaJual ASC,
            c.Item ASC
        "));
            if ($datas) {
                // Ubah ke bentuk header + detail
                $collection = collect($datas);

                // ambil header dari record pertama
                $header = $collection->first();

                // group detail per inv_id
                $detail = $collection->map(function ($item) {
                    return [
                        'kode_barang'    => $item->kode_barang,
                        'NamaBarang'     => $item->NamaBarang,
                        'namabarangbawah' => $item->namabarangbawah,
                        'subtotal'       => $item->subtotal,
                    ];
                });

                $result = [
                    'inv_id'         => $header->inv_id,
                    'CustomerOrderNo' => $header->CustomerOrderNo,
                    'po_cust'        => $header->po_cust,
                    'TglJual'        => $header->TglJual,
                    'CustomerName'   => $header->CustomerName,
                    'address1'       => $header->address1,
                    'address2'       => $header->address2,
                    'nik'            => $header->nik,
                    'tgljtempo'      => $header->tgljtempo,
                    'kota'           => $header->kota,
                    'NamaSales'      => $header->NamaSales,
                    'pay_term'       => $header->pay_term,
                    'ppn_val'       => $header->ppn_val,
                    'discount'       => $header->discount,
                    'detail' => $detail,
                ];
                return $this->sendResponse($result, 'Successfully get List');
            } else {
                return $this->sendResponse(null, 'Data not found');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    public function DisplayDetailInv(Request $request)
    {
        try {
            $sqlWhere = " a.penjualanno= '$request->inv_id' ";
            $datas = DB::select(DB::raw("
             SELECT trim(a.PenjualanNO) inv_id,trim(a.CustomerOrderNo) CustomerOrderNo,trim(a.customerid) customerid,a.TglJual,trim(f.CustomerName) CustomerName,
             trim(f.address1) address1,trim(f.address2) address2,trim(d.nik) nik,trim(g.barangjadiid) kode_barang,trim(c.NamaBarang) NamaBarang,
            round(c.HargaJual,2) HargaJual,c.Lembar,c.Panjang,c.price_mode,d.office_id,trim(h.office) office,
            case when c.price_mode='M'
            then
                c.HargaJual*c.Lembar*c.Panjang
            else
                c.HargaJual*c.Lembar
            end  subtotal,tgljtempo,trim(a.salesid) salesid,trim(e.NamaSales) NamaSales,
            case when d.pay_term= 'CASH BEFORE DELIVERY' 
            then 
                'LUNAS SEBELUM KIRIM'
            else
                trim(replace(d.pay_term,'DAYS','HARI'))
            end pay_term,a.ppn_val,a.discount
            FROM
            Penjualan a INNER JOIN Customer b ON
                a.CustomerId = b.CustomerId
            INNER JOIN  PenjualanDetil c ON
                a.PenjualanNO = c.PenjualanNO
            INNER JOIN CustomerOrder d ON
                a.CustomerOrderNo = d.CustomerOrderNo
            INNER JOIN Sales e ON
                a.SalesId = e.SalesId
            INNER JOIN customer_nik_npwp f ON
                d.nik = f.tr_id
            INNER JOIN BarangJadi g ON
                c.BarangJadiId = g.BarangJadiId
            inner join branch_office h on d.office_id=h.office_id
            WHERE $sqlWhere
            ORDER BY
            a.PenjualanNO ASC,
            c.NamaBarang ASC,
            c.HargaJual ASC,
            c.Item ASC
        "));
            if ($datas) {
                // Ubah ke bentuk header + detail
                $collection = collect($datas);

                // ambil header dari record pertama
                $header = $collection->first();

                // group detail per inv_id
                $detail = $collection->map(function ($item) {
                    return [
                        'kode_barang'    => $item->kode_barang,
                        'NamaBarang'     => $item->NamaBarang,
                        'Lembar'     => $item->Lembar,
                        'Panjang' => $item->Panjang,
                        'HargaJual' => $item->HargaJual,
                        'subtotal'       => $item->subtotal,
                        'price_mode'       => $item->price_mode,
                    ];
                });

                $result = [
                    'inv_id'         => $header->inv_id,
                    'CustomerOrderNo' => $header->CustomerOrderNo,
                    'customerid'        => $header->customerid,
                    'TglJual'        => $header->TglJual,
                    'CustomerName'   => $header->CustomerName,
                    'address1'       => $header->address1,
                    'address2'       => $header->address2,
                    'nik'            => $header->nik,
                    'tgljtempo'      => $header->tgljtempo,
                    'salesid'           => $header->salesid,
                    'NamaSales'      => $header->NamaSales,
                    'pay_term'       => $header->pay_term,
                    'ppn_val'       => $header->ppn_val,
                    'discount'       => $header->discount,
                    'office_id'       => $header->office_id,
                    'office'       => $header->office,
                    'detail' => $detail,
                ];
                return $this->sendResponse($result, 'Successfully get List');
            } else {
                return $this->sendResponse(null, 'Data not found');
            }
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }
    private function hasVoucher($inv_id)
    {
        $result = DB::connection("sqlSrvBCKI")->table('payment_dtl')
            ->selectRaw("voucher")
            ->where('penjualanno', $inv_id)
            ->first();
        return !empty($result?->voucher); // return boolean
    }
    private function generateInvID($entity, $invDate, $office_id)
    {
        $tahun2digit = date('y', strtotime($invDate));
        $bulan2digit = date('m', strtotime($invDate));
        $hari2digit = date('d', strtotime($invDate));
        //$office_id = str_pad($office_id, 2, '0', STR_PAD_LEFT);
        $gen_inv_id = '';
        $prf0 = "3";

        switch ($entity) {
            case 'KKA':
                $prfcv = "0";
                $office_id = str_pad($office_id, 3, '0', STR_PAD_LEFT);
                break;
            case 'KMB':
                $prfcv = "1";
                $office_id = str_pad($office_id, 2, '0', STR_PAD_LEFT);
                break;
            case 'MMM':
                $prfcv = "2";
                $office_id = str_pad($office_id, 2, '0', STR_PAD_LEFT);
                break;
            default:
                $prfcv = "9";
                $office_id = str_pad($office_id, 2, '0', STR_PAD_LEFT);
        }
        $prf1 = $prf0 . $prfcv . $office_id . $tahun2digit . $bulan2digit . $hari2digit;

        $getLastTrID =  DB::connection("sqlSrvBCKI")->table('penjualan')
            ->where('penjualanno', 'like', "%" . $prf1 . "%")
            ->max('penjualanno');

        if ($getLastTrID) {
            $seq = substr(trim($getLastTrID), -3) + 1;
            $gen_inv_id = $prf1 . str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_inv_id = $prf1 . '001';
        }

        return $gen_inv_id;
    }
    private function insertInvoice($inv_id,  $request, $user_id)
    {
        DB::connection("sqlSrvBCKI")->table('penjualan')
            ->insert([
                'PenjualanNO' => $inv_id,
                'SalesId' => $this->getSalesfromSpr($request->CustomerOrderNo),
                'CustomerId' => $request->customer_id,
                'CustomerOrderNo' => $request->CustomerOrderNo,
                'TglJual' => $request->inv_date ? Carbon::parse($request->inv_date)->format('Y-m-d') : Carbon::parse(now())->format('Y-m-d'),
                //'tglserah' => $request->inv_date ? Carbon::parse($request->inv_date)->format('Y-m-d') : Carbon::parse(now())->format('Y-m-d'),
                'TglJTempo' => $request->TglJTempo ? Carbon::parse($request->TglJTempo)->format('Y-m-d') : Carbon::parse($request->TglJTempo)->format('Y-m-d'),
                'Discount' => 0,
                //'Kurs' => 'IDR',
                //'NilaiKurs' => 1,
                'PPN' => 0,
                'TotalJual' => (float) preg_replace('/[^0-9]/', '', $request->TotalJual),
                'Pelunasan' => 'BELUM',
                'dt_modified' => now(),
                'tglcreate' => now(),
                'user_id' => $user_id,
                'inv_type' => $request->inv_type,
                'kpr_type' => $request->kpr_type ?? '',
                'SubTotalAmount' => (float) preg_replace('/[^0-9]/', '', $request->SubTotalAmount),
            ]);
    }
    private function insertPaymentOutstanding($inv_id, $order_id, $inv_amt)
    {

        DB::connection("sqlSrvBCKI")->table('payment_outstanding')
            ->insert([
                'penjualanno' => $inv_id,
                'customerorderno' => $order_id,
                'faktur_amt' => (float) preg_replace('/[^0-9]/', '', $inv_amt),
                'dt_modified' =>  now(),
            ]);
    }
    private function insertTabelSuratJalan($inv_id, $order_id, $deliv_id)
    {
        DB::connection("sqlSrvBCKI")->table('SuratJalan')
            ->insert([
                'PenjualanNO' => $inv_id,
                'CustomerOrderNo' => $order_id,
                'Nomor1' => $deliv_id,
            ]);
    }
    private function buildDetailInvoice($inv_id, $value, $index)
    {
        $item_num_dtl = $index + 1;
        return [
            'BarangJadiId' => $value->prod_code,
            'PenjualanNO' => $inv_id,
            'Lembar' => 1,
            'Panjang' => 1,
            'HargaDasar' => (float) preg_replace('/[^0-9]/', '', $value->unit_price), //(float) $value->unit_price,
            'HargaJual' => (float) preg_replace('/[^0-9]/', '', $value->unit_price), //(float) $value->unit_price,
            'HargaPerMeter' => (float) preg_replace('/[^0-9]/', '', $value->unit_price), //(float) $value->unit_price,
            'TotalJual' => (float) preg_replace('/[^0-9]/', '', $value->TotalJual), //(float) $value->TotalJual,
            'Item' => $item_num_dtl,
            'NamaBarang' => $value->namabarang,
            'price_mode' => 'U',
            'disc_unit' => 0,
            'ac_num' => $this->GetAcNumSa($value->prod_code),
        ];


        return [$detilRow];
    }
    private function buildDelivId($value, $index)
    {
        return  [
            'deliv_id' => $value->deliv_id,
        ];
        return [$deliv_id];
    }
    private function generateFPajak($invDate)
    {
        $getLastTrID  = DB::connection("sqlSrvBCKI")->table('Penjualan')
            ->selectRaw("ISNULL(MAX(RIGHT(RTRIM(f_pajak),3)),0)+1 as fPajak")
            ->where('TglJual', '=', Carbon::parse($invDate)->format('Ymd'))
            ->whereRaw("LEFT(f_pajak,6) = ?", [trim($invDate)])
            ->value('fPajak');
        $genFPajak = Carbon::parse($invDate)->format('ymd') . str_pad($getLastTrID, 3, '0', STR_PAD_LEFT);
        return $genFPajak;
    }
    private function getPayTermDuration($PayTerm)
    {
        return DB::connection("sqlSrvBCKI")->table('pay_term')
            ->where('pay_term', $PayTerm)
            ->value('duration') ?? 0;
    }
    private function getSalesfromSpr($sprId)
    {
        return DB::connection("sqlSrvBCKI")->table('customerorder')
            ->where('order_id', $sprId)
            ->value('salesid') ?? '';
    }
    private function GetAcNumSa($article_id)
    {
        return DB::connection("sqlSrvBCKI")->table('article')
            ->where('article_id', $article_id)
            ->value('ac_num_penj') ?? '';
    }
    private function GenTrID($invDate)
    {
        $HariIni = "JJ" . Carbon::parse($invDate)->format('ymd');
        $getLastTrID  = DB::connection("sqlSrvBCKI")->table('Penjualan')
            ->selectRaw("ISNULL(MAX(RIGHT(RTRIM(f_pajak),3)),0)+1 as gen")
            ->where('TglJual', '=', Carbon::parse($invDate)->format('Ymd'))
            ->value('tr_id');

        //->whereRaw("LEFT(tr_id,8) = ?", [trim($HariIni)])
        $genFPajak = $HariIni . str_pad($getLastTrID, 3, '0', STR_PAD_LEFT);
        return $genFPajak;
    }

    public function getPpnVal()
    {
        //$year_id = $request->year_id;
        $ppnVal = $this->getEnvConf("FINANCE", "PPNVAL");
        return $this->sendResponse($ppnVal, 'Successfully get ppn value');
    }
    public function loadEnvVar(Request $request)
    {
        $user_id = getAuth($request)->user_id2 ?? '';
        $app_id = $request->input('app_id', 'FINANCE');
        $var_name = $request->input('var_name', []);

        $result = [];
        foreach ($var_name as $key => $conf_name) {
            $result[$key] = $this->getEnvConf($app_id, $conf_name, $user_id);
        }

        return $this->sendResponse($result, 'Successfully get value');
    }
    public function countDiscount($order_id)
    {
        $jumlah = DB::connection("sqlSrvBCKI")->table('penjualan')
            ->where('customerorderno', trim($order_id))
            ->count();
        if ($jumlah > 1) {
            return 0;
        }
        $tDisc = DB::connection("sqlSrvBCKI")->table('customerorderdetil')
            ->where('customerorderno', trim($order_id))
            ->where('Stat', '<>', 'X')
            ->sum('disc_unit');
        return $tDisc;
    }

    public function GetDataOrder(Request $request)
    {
        $customer_id = $request->customer_id;
        $spr_id = $request->spr_id;
        $sqlWhere = " 1=1 ";

        if ($customer_id) {
            $sqlWhere .= " AND th.customerid = '$customer_id' ";
        }
        if ($spr_id) {
            $sqlWhere .= " AND th.order_id = '$spr_id' ";
        }

        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            select th.order_id,NamaCustomer,sum(unit_price-disc_unit) total,order_date,pay_method,th.salesid ,th.utj,th.dp_amt,th.plafon_naik
            from customerorder th inner join customer p 
            on th.customerid = p.customerid 
            inner join CustomerOrderDetil aa on th.order_id=aa.order_id
            where $sqlWhere 
            group by th.order_id,NamaCustomer,order_date,pay_method,th.salesid ,th.utj,th.dp_amt,th.plafon_naik
        "));

        return $this->sendResponse($datas, 'Successfully get List');
    }
    public function GetDataOrdertoInvoice(Request $request)
    {
        $order_id = $request->spr_id;
        $sqlWhere = " 1=1 ";

        if ($order_id) {
            $sqlWhere .= " AND customerorderno = '$order_id' ";
        }

        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            select inv_type,b.dp_amt,b.utj,b.ord_amt,b.plafon_naik,sum(TotalJual) totaljual from penjualan a 
            inner join customerorder b on a.customerorderno=b.order_id
            where $sqlWhere
            group by inv_type,b.dp_amt,b.utj,b.ord_amt,b.plafon_naik
        "));
        return $this->sendResponse($datas, 'Successfully get List');
    }

    public function GetDataInvoicebySpr(Request $request)
    {
        $order_id = $request->spr_id;
        $tr_type = $request->inv_type;
        $sqlWhere = " 1=1 ";

        if ($order_id) {
            $sqlWhere .= " AND customerorderno = '$order_id' ";
        }
        if ($tr_type) {
            $sqlWhere .= " AND inv_type = '$tr_type' ";
        }

        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            select penjualanno,tgljual,tgljtempo,SubTotalAmount,amt_ppn,totalJual,inv_type
            from penjualan
            where $sqlWhere
            order by tgljual
         "));

        // $grouped = collect($datas)->groupBy('inv_type')->map(function ($items, $type) {
        //     return [
        //         'inv_type' => $type,
        //         $type => $items->values(),
        //     ];
        // })->values();

        return $this->sendResponse($datas, 'Successfully get grouped list');
    }
    private function generateInvoice($inv_date, $inv_type)
    {
        match ($inv_type) {
            "SOFT CASH", "HARD CASH" => $invType = "CAS",
            default => $invType = $inv_type,
        };
        $prefix = sprintf("FAA/%s/%s/", $invType, date('Ym', strtotime($inv_date)));

        $lastId = DB::connection("sqlSrvBCKI")->table('penjualan')
            ->where('penjualanno', 'like', "%{$prefix}%")
            ->max('penjualanno');

        $nextNumber = $lastId
            ? ((int) substr($lastId, -4)) + 1
            : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    public function GetDataInvoicebyId(Request $request)
    {
        $inv_id = $request->inv_id;
        $sqlWhere = " 1=1 ";

        if ($inv_id) {
            $sqlWhere .= " AND a.penjualanno = '$inv_id' ";
        }

        $datas = DB::connection("sqlSrvBCKI")->select(DB::raw("
            select a.penjualanno,a.customerorderno,tgljual,tgljtempo,subtotalamount,amt_ppn,a.totaljual,inv_type,namacustomer,
            b.namabarang,b.lembar as qty,b.hargajual,isnull(c.Alamat_id_card,'') alamat,d.city
            from penjualan a inner join penjualandetil b on a.penjualanno=b.penjualanno
            inner join customer c on a.customerid=c.customerid
            left outer join city_list d on c.Kota=d.city_code
            where $sqlWhere
         "));

        return $this->sendResponse($datas, 'Successfully get grouped list');
    }
}
