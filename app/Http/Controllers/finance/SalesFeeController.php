<?php

namespace App\Http\Controllers\finance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SalesFeeController extends Controller
{
  public function index(Request $request)
  {
    try {

      $sqlWhere = "1=1";
      if ($request->sales_id) {
        $sqlWhere .= " and a.salesid = '" . $request->sales_id . "'";
      }

      $datas = DB::connection('sqlSrvBCKI')
        ->table('fee_sales_param_new as a')
        ->selectRaw('a.salesid as sales,
          a.channel_id,
          a.utj_type,
          a.active_flag,
          a.user_id,
          a.dt_modified,
          a.fee_closing,
          a.fee_commision,
          a.fee_closing_marcom,
          a.fee_commision_marcom,
          a.fee_closing_agency,
          a.fee_commision_agency,
          a.fee_closing_karyawan,
          a.fee_commision_karyawan,
          a.fee_closing_warga,
          a.fee_commision_warga,
          a.fee_closing_hr,
          a.fee_commision_hr,
          a.fee_closing_spv_sales,
          a.fee_commision_spv_sales,
          a.fee_closing_koordinator_sales,
          a.fee_commision_koordinator_sales,
          a.fee_closing_manager_sales,
          a.fee_commision_manager_sales,
          a.fee_closing_manager_smd,
          a.fee_commision_manager_smd,
          b.NamaSales,
          c.channel_name,
          d.str2')
        ->join('sales as b', 'a.salesid', '=', 'b.SalesId')
        ->join('channel_fee_sales as c', 'a.channel_id', '=', 'c.channel_id')
        ->join('appl_constant as d', function ($join) {
            $join->on('a.utj_type', '=', 'd.str1')
                ->where('d.key_code', '=', 'UTJ_TYPE');
        })
        ->whereRaw($sqlWhere)
        ->get();

      return $this->sendResponse($datas, 'Successfully get List');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function getSales(Request $request)
  {
    try {
      $datas = DB::connection('sqlSrvBCKI')
        ->select(DB::raw("
                    select NamaSales, salesId
                    FROM sales"));

      return $this->sendResponse($datas, 'Successfully get List');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function getChannel(Request $request)
  {
    try {
      $datas = DB::connection('sqlSrvBCKI')
        ->table('channel_fee_sales')
        ->selectRaw("channel_id, channel_name")
        ->where('active_flag', 'Y')
        ->get();
      return $this->sendResponse($datas, 'Successfully get List');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function storeFee(Request $request)
  {
    try {
      $datas = DB::connection('sqlSrvBCKI')
        ->table('fee_sales_param_new')
        ->where('salesid', $request->sales)
        ->where('utj_type', $request->utj_type)
        ->first();

      if ($datas) {
        $update = DB::connection('sqlSrvBCKI')
          ->table('fee_sales_param_new')
          ->where('salesid', $request->sales)
          ->where('utj_type', $request->utj_type)
          ->update([
            'salesid' => $request->sales ?? '',
            'channel_id' => $request->channel_id ?? '',
            // 'utj_type' => $request->utj_type ?? '',
            'active_flag' => $request->active_flag ?? 'N',
            'dt_modified' => now('Asia/Jakarta'),
            'user_id' => getAuth($request)->user_id2,
            'fee_closing' => $request->fee_closing ?? 0,
            'fee_commision' => $request->fee_commision ?? 0,
            'fee_closing_marcom' => $request->fee_closing_marcom ?? 0,
            'fee_commision_marcom' => $request->fee_commision_marcom ?? 0,
            'fee_closing_agency' => $request->fee_closing_agency ?? 0,
            'fee_commision_agency' => $request->fee_commision_agency ?? 0,
            'fee_closing_karyawan' => $request->fee_closing_karyawan ?? 0,
            'fee_commision_karyawan' => $request->fee_commision_karyawan ?? 0,
            'fee_closing_warga' => $request->fee_closing_warga ?? 0,
            'fee_commision_warga' => $request->fee_commision_warga ?? 0,
            'fee_closing_hr' => $request->fee_closing_hr ?? 0,
            'fee_commision_hr' => $request->fee_commision_hr ?? 0,
            'fee_closing_spv_sales' => $request->fee_closing_spv_sales ?? 0,
            'fee_commision_spv_sales' => $request->fee_commision_spv_sales ?? 0,
            'fee_closing_koordinator_sales' => $request->fee_closing_koordinator_sales ?? 0,
            'fee_commision_koordinator_sales' => $request->fee_commision_koordinator_sales ?? 0,
            'fee_closing_manager_sales' => $request->fee_closing_manager_sales ?? 0,
            'fee_commision_manager_sales' => $request->fee_commision_manager_sales ?? 0,
            'fee_closing_manager_smd' => $request->fee_closing_manager_smd ?? 0,
            'fee_commision_manager_smd' => $request->fee_commision_manager_smd ?? 0
          ]);
      } else {
        $insert = DB::connection('sqlSrvBCKI')
          ->table('fee_sales_param_new')
          ->insert([
            'salesid' => $request->sales ?? '',
            'channel_id' => $request->channel_id ?? '',
            'utj_type' => $request->utj_type ?? '',
            'active_flag' => $request->active_flag ?? 'N',
            'dt_modified' => now('Asia/Jakarta'),
            'user_id' => getAuth($request)->user_id2,
            'fee_closing' => $request->fee_closing ?? 0,
            'fee_commision' => $request->fee_commision ?? 0,
            'fee_closing_marcom' => $request->fee_closing_marcom ?? 0,
            'fee_commision_marcom' => $request->fee_commision_marcom ?? 0,
            'fee_closing_agency' => $request->fee_closing_agency ?? 0,
            'fee_commision_agency' => $request->fee_commision_agency ?? 0,
            'fee_closing_karyawan' => $request->fee_closing_karyawan ?? 0,
            'fee_commision_karyawan' => $request->fee_commision_karyawan ?? 0,
            'fee_closing_warga' => $request->fee_closing_warga ?? 0,
            'fee_commision_warga' => $request->fee_commision_warga ?? 0,
            'fee_closing_hr' => $request->fee_closing_hr ?? 0,
            'fee_commision_hr' => $request->fee_commision_hr ?? 0,
            'fee_closing_spv_sales' => $request->fee_closing_spv_sales ?? 0,
            'fee_commision_spv_sales' => $request->fee_commision_spv_sales ?? 0,
            'fee_closing_koordinator_sales' => $request->fee_closing_koordinator_sales ?? 0,
            'fee_commision_koordinator_sales' => $request->fee_commision_koordinator_sales ?? 0,
            'fee_closing_manager_sales' => $request->fee_closing_manager_sales ?? 0,
            'fee_commision_manager_sales' => $request->fee_commision_manager_sales ?? 0,
            'fee_closing_manager_smd' => $request->fee_closing_manager_smd ?? 0,
            'fee_commision_manager_smd' => $request->fee_commision_manager_smd ?? 0
          ]);
      }
      return $this->sendResponse($datas, 'Successfully get List');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function storeCopyFeeSales(Request $request)
  {
    try {
      $datas = DB::connection('sqlSrvBCKI')
        ->table('fee_sales_param_new')
        ->select('*')
        ->where('salesid', $request->sales)
        ->get();

      // 2. Cek collection benar-benar ada isinya
      if ($datas->isEmpty()) {
        return $this->sendError('Data Fee Sales tidak ditemukan!');
      }

      // 3. Siapkan bulk insert
      $insertData = $datas->map(function ($data) use ($request) {
        return [
          'salesid'                         => $request->sales_tujuan,
          'channel_id'                      => $data->channel_id ?? '',
          'utj_type'                        => $data->utj_type ?? '',
          'active_flag'                     => $data->active_flag ?? 'N',
          'dt_modified'                     => now('Asia/Jakarta'),
          'user_id'                         => getAuth($request)->user_id2,
          'fee_closing'                     => $data->fee_closing ?? 0,
          'fee_commision'                   => $data->fee_commision ?? 0,
          'fee_closing_marcom'              => $data->fee_closing_marcom ?? 0,
          'fee_commision_marcom'            => $data->fee_commision_marcom ?? 0,
          'fee_closing_agency'              => $data->fee_closing_agency ?? 0,
          'fee_commision_agency'            => $data->fee_commision_agency ?? 0,
          'fee_closing_karyawan'            => $data->fee_closing_karyawan ?? 0,
          'fee_commision_karyawan'          => $data->fee_commision_karyawan ?? 0,
          'fee_closing_warga'               => $data->fee_closing_warga ?? 0,
          'fee_commision_warga'             => $data->fee_commision_warga ?? 0,
          'fee_closing_hr'                  => $data->fee_closing_hr ?? 0,
          'fee_commision_hr'                => $data->fee_commision_hr ?? 0,
          'fee_closing_spv_sales'           => $data->fee_closing_spv_sales ?? 0,
          'fee_commision_spv_sales'         => $data->fee_commision_spv_sales ?? 0,
          'fee_closing_koordinator_sales'   => $data->fee_closing_koordinator_sales ?? 0,
          'fee_commision_koordinator_sales' => $data->fee_commision_koordinator_sales ?? 0,
          'fee_closing_manager_sales'       => $data->fee_closing_manager_sales ?? 0,
          'fee_commision_manager_sales'     => $data->fee_commision_manager_sales ?? 0,
          'fee_closing_manager_smd'         => $data->fee_closing_manager_smd ?? 0,
          'fee_commision_manager_smd'       => $data->fee_commision_manager_smd ?? 0
        ];
      })->toArray();


      DB::connection('sqlSrvBCKI')->transaction(function () use ($insertData) {
        foreach ($insertData as $row) {
          DB::connection('sqlSrvBCKI')
            ->table('fee_sales_param_new')
            ->updateOrInsert(
              [ // kondisi pengecekan (WHERE)
                'salesid'                         => $row['salesid'],  
                'utj_type'                        => $row['utj_type']
              ],
              [ // data yang di-insert/update
                'active_flag'                     => $row['active_flag'],             
                'channel_id'                      => $row['channel_id'],
                'dt_modified'                     => $row['dt_modified'],
                'user_id'                         => $row['user_id'],
                'fee_closing'                     => $row['fee_closing'],
                'fee_commision'                   => $row['fee_commision'],
                'fee_closing_marcom'              => $row['fee_closing_marcom'],
                'fee_commision_marcom'            => $row['fee_commision_marcom'],
                'fee_closing_agency'              => $row['fee_closing_agency'],
                'fee_commision_agency'            => $row['fee_commision_agency'],
                'fee_closing_karyawan'            => $row['fee_closing_karyawan'],
                'fee_commision_karyawan'          => $row['fee_commision_karyawan'],
                'fee_closing_warga'               => $row['fee_closing_warga'],
                'fee_commision_warga'             => $row['fee_commision_warga'],
                'fee_closing_hr'                  => $row['fee_closing_hr'],
                'fee_commision_hr'                => $row['fee_commision_hr'],
                'fee_closing_spv_sales'           => $row['fee_closing_spv_sales'],
                'fee_commision_spv_sales'         => $row['fee_commision_spv_sales'],
                'fee_closing_koordinator_sales'   => $row['fee_closing_koordinator_sales'],
                'fee_commision_koordinator_sales' => $row['fee_commision_koordinator_sales'],
                'fee_closing_manager_sales'       => $row['fee_closing_manager_sales'],
                'fee_commision_manager_sales'     => $row['fee_commision_manager_sales'],
                'fee_closing_manager_smd'         => $row['fee_closing_manager_smd'],
                'fee_commision_manager_smd'       => $row['fee_commision_manager_smd']
              ]
            );
        }
      });

      return $this->sendResponse(true, 'Successfully copy sales fee');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function storeCopyAllFeeSales(Request $request)
  {
    try {
      // 1. Ambil semua sales aktif
      $allSales = DB::connection('sqlSrvBCKI')
        ->table('sales')
        ->where('active_flag', 'Y')
        ->pluck('sales_id'); // ambil salesid saja

      // 2. Ambil salesid yang sudah punya fee (1 query)
      $existingSalesIds = DB::connection('sqlSrvBCKI')
        ->table('fee_sales_param_new')
        ->whereIn('salesid', $allSales)
        ->pluck('salesid')
        ->toArray();

      // 3. Pisahkan sales baru vs yang sudah ada
      $newSalesIds = $allSales->diff($existingSalesIds);

      $userId = getAuth($request)->user_id2;
      $now    = now('Asia/Jakarta');

      DB::connection('sqlSrvBCKI')->transaction(function () use (
        $newSalesIds,
        $existingSalesIds,
        $request,
        $userId,
        $now
      ) {
        // 4. Bulk insert untuk sales yang belum punya fee
        if ($newSalesIds->isNotEmpty()) {
          $insertData = $newSalesIds->map(fn($salesid) => [
            'salesid'                         => $salesid,
            'channel_id'                      => $request->channel_id ?? '',
            'utj_type'                        => $request->utj_type ?? '',
            'active_flag'                     => $request->active_flag ?? 'N',
            'dt_modified'                     => $now,
            'user_id'                         => $userId,
            'fee_closing'                     => $request->fee_closing ?? 0,
            'fee_commision'                   => $request->fee_commision ?? 0,
            'fee_closing_marcom'              => $request->fee_closing_marcom ?? 0,
            'fee_commision_marcom'            => $request->fee_commision_marcom ?? 0,
            'fee_closing_agency'              => $request->fee_closing_agency ?? 0,
            'fee_commision_agency'            => $request->fee_commision_agency ?? 0,
            'fee_closing_karyawan'            => $request->fee_closing_karyawan ?? 0,
            'fee_commision_karyawan'          => $request->fee_commision_karyawan ?? 0,
            'fee_closing_warga'               => $request->fee_closing_warga ?? 0,
            'fee_commision_warga'             => $request->fee_commision_warga ?? 0,
            'fee_closing_hr'                  => $request->fee_closing_hr ?? 0,
            'fee_commision_hr'                => $request->fee_commision_hr ?? 0,
            'fee_closing_spv_sales'           => $request->fee_closing_spv_sales ?? 0,
            'fee_commision_spv_sales'         => $request->fee_commision_spv_sales ?? 0,
            'fee_closing_koordinator_sales'   => $request->fee_closing_koordinator_sales ?? 0,
            'fee_commision_koordinator_sales' => $request->fee_commision_koordinator_sales ?? 0,
            'fee_closing_manager_sales'       => $request->fee_closing_manager_sales ?? 0,
            'fee_commision_manager_sales'     => $request->fee_commision_manager_sales ?? 0,
            'fee_closing_manager_smd'         => $request->fee_closing_manager_smd ?? 0,
            'fee_commision_manager_smd'       => $request->fee_commision_manager_smd ?? 0
          ])->values()->toArray();

          DB::connection('sqlSrvBCKI')
            ->table('fee_sales_param_new')
            ->insert($insertData);
        }

        // 5. Bulk update untuk sales yang sudah ada (1 query)
        if (!empty($existingSalesIds)) {
          DB::connection('sqlSrvBCKI')
            ->table('fee_sales_param_new')
            ->whereIn('salesid', $existingSalesIds)
            ->where('utj_type', $request->utj_type ?? '')
            ->update([
              'active_flag'                     => $request->active_flag ?? 'N',
              'channel_id'                      => $request->channel_id ?? '',
              'utj_type'                        => $request->utj_type ?? '',
              'dt_modified'                     => $now,
              'user_id'                         => $userId,
              'fee_closing'                     => $request->fee_closing ?? 0,
              'fee_commision'                   => $request->fee_commision ?? 0,
              'fee_closing_marcom'              => $request->fee_closing_marcom ?? 0,
              'fee_commision_marcom'            => $request->fee_commision_marcom ?? 0,
              'fee_closing_agency'              => $request->fee_closing_agency ?? 0,
              'fee_commision_agency'            => $request->fee_commision_agency ?? 0,
              'fee_closing_karyawan'            => $request->fee_closing_karyawan ?? 0,
              'fee_commision_karyawan'          => $request->fee_commision_karyawan ?? 0,
              'fee_closing_warga'               => $request->fee_closing_warga ?? 0,
              'fee_commision_warga'             => $request->fee_commision_warga ?? 0,
              'fee_closing_hr'                  => $request->fee_closing_hr ?? 0,
              'fee_commision_hr'                => $request->fee_commision_hr ?? 0,
              'fee_closing_spv_sales'           => $request->fee_closing_spv_sales ?? 0,
              'fee_commision_spv_sales'         => $request->fee_commision_spv_sales ?? 0,
              'fee_closing_koordinator_sales'   => $request->fee_closing_koordinator_sales ?? 0,
              'fee_commision_koordinator_sales' => $request->fee_commision_koordinator_sales ?? 0,
              'fee_closing_manager_sales'       => $request->fee_closing_manager_sales ?? 0,
              'fee_commision_manager_sales'     => $request->fee_commision_manager_sales ?? 0,
              'fee_closing_manager_smd'         => $request->fee_closing_manager_smd ?? 0,
              'fee_commision_manager_smd'       => $request->fee_commision_manager_smd ?? 0
            ]);
        }
      });

      return $this->sendResponse(true, 'Successfully Apply to All Sales');
    } catch (\Throwable $e) {
      return $this->sendError($e->getMessage());
    }
  }

  public function getOrderData(Request $request)
  {
      try {
          $sales_id   = $request->sales_id;
          $inv_type   = $request->inv_type;
          $start_date = $request->start_date;
          $end_date   = $request->end_date;

          $bindings = [];
          $whereClause = "1=1 ";

          // Filter Dynamic Parameter Binding
          if ($sales_id) {
              $whereClause .= " AND sales_id = ?";
              $bindings[] = $sales_id;
          }

          if ($inv_type) {
              $whereClause .= " AND inv_type = ?";
              $bindings[] = $inv_type;
          }

          if ($start_date) {
              if ($end_date) {
                  $whereClause .= " AND order_date BETWEEN CONVERT(datetime, ?) AND CONVERT(datetime, ?)";
                  $bindings[] = "{$start_date} 00:00:00.001";
                  $bindings[] = "{$end_date} 23:59:59.000";
              } else {
                  $whereClause .= " AND order_date BETWEEN CONVERT(datetime, ?) AND GETDATE()";
                  $bindings[] = "{$start_date} 00:00:00";
              }
          }

          $data = DB::connection('sqlSrvBCKI')
            ->table('v_sales_fee')
            ->select('*')
            ->whereRaw($whereClause, $bindings)
            ->whereRaw("NULLIF(LTRIM(RTRIM(CAST(order_id_fee AS VARCHAR))), '') IS NULL")
            ->get();
            

          return $this->sendResponse($data, 'Successfully get data');

      } catch (\Throwable $e) {
          return $this->sendError($e->getMessage());
      }
  }

  public function calculatefee(Request $request){
    $orders = $request->input('order', []);
    $insertData = [];
    $userId = getAuth($request)->user_id2; // Ambil ID user yang login atau fallback ke 'system'
    $now = Carbon::now('Asia/Jakarta');
    foreach ($orders as $item) {
        $dpp = (float) ($item['dpp'] ?? 0);

        // Ambil area (ubah ke uppercase untuk konsistensi pengecekan)
        $area = strtoupper(trim($item['site_name'] ?? $item['area'] ?? ''));

        $invType = strtoupper(trim($item['inv_type'] ?? ''));
        
        // Helper anonim untuk Closing Fee (hanya aktif jika inv_type === UTJ)
        $calcClosing = function ($key) use ($item, $invType) {
            if ($invType === 'UTJ') {
                return round((float) ($item[$key] ?? 0), 2);
            }
            return 0.00;
        };

        // Helper anonim untuk Komisi (hanya aktif jika inv_type CAS/KPR)
        $calcCommision = function ($key) use ($item, $dpp, $invType) {
            if (in_array($invType, ['CAS', 'CASH', 'KPR'])) {
                $percent = (float) ($item[$key] ?? 0);
                return round($dpp * $percent / 100, 2); // Presisi 2 angka dibelakang koma
            }
            return 0.00;
        };

        // Kondisi Aturan Berdasarkan Area (Khusus Cikarang & Cirebon, sisanya Default/Semarang)
        if ($area === 'CIKARANG') {
            // Cikarang: Sales, Koordinator, Manager SMD (SPV Sales & Manager Sales = 0)
            $feeClosingSpv           = 0.00;
            $feeCommisionSpv         = 0.00;
            $feeClosingKoord         = $calcClosing('fee_closing_koordinator_sales');
            $feeCommisionKoord       = $calcCommision('fee_commision_koordinator_sales');
            $feeClosingManagerSales   = 0.00;
            $feeCommisionManagerSales = 0.00;
        } elseif ($area === 'CIREBON') {
            // Cirebon: Sales, Manager SMD (SPV Sales, Koordinator, & Manager Sales = 0)
            $feeClosingSpv           = 0.00;
            $feeCommisionSpv         = 0.00;
            $feeClosingKoord         = 0.00;
            $feeCommisionKoord       = 0.00;
            $feeClosingManagerSales   = 0.00;
            $feeCommisionManagerSales = 0.00;
        } else {
            // Default / Else (Termasuk Semarang): Sales, SPV Sales, Manager Sales, Manager SMD (Koordinator = 0)
            $feeClosingSpv           = $calcClosing('fee_closing_spv_sales');
            $feeCommisionSpv         = $calcCommision('fee_commision_spv_sales');
            $feeClosingKoord         = 0.00;
            $feeCommisionKoord       = 0.00;
            $feeClosingManagerSales   = $calcClosing('fee_closing_manager_sales');
            $feeCommisionManagerSales = $calcCommision('fee_commision_manager_sales');
        }

        // Mapping data sesuai struktur kolom tabel database
        $insertData[] = [
            'order_id'                         => $item['orderid'] ?? '',
            'tr_type'                          => $item['inv_type'] ?? '',
            
            // Fee & Commission Umum
            'fee_closing'                      => $calcClosing('fee_closing'),
            'fee_commision'                    => $calcCommision('fee_commision'),
            'fee_closing_marcom'               => $calcClosing('fee_closing_marcom'),
            'fee_commision_marcom'             => $calcCommision('fee_commision_marcom'),
            'fee_closing_agency'               => $calcClosing('fee_closing_agency'),
            'fee_commision_agency'             => $calcCommision('fee_commision_agency'),
            'fee_closing_karyawan'             => $calcClosing('fee_closing_karyawan'),
            'fee_commision_karyawan'           => $calcCommision('fee_commision_karyawan'),
            'fee_closing_warga'                => $calcClosing('fee_closing_warga'),
            'fee_commision_warga'              => $calcCommision('fee_commision_warga'),
            'fee_closing_hr'                   => $calcClosing('fee_closing_hr'),
            'fee_commision_hr'                 => $calcCommision('fee_commision_hr'),

            // Hasil filter sesuai matriks area
            'fee_closing_spv_sales'            => $feeClosingSpv,
            'fee_commision_spv_sales'          => $feeCommisionSpv,
            'fee_closing_koordinator_sales'    => $feeClosingKoord,
            'fee_commision_koordinator_sales'  => $feeCommisionKoord,
            'fee_closing_manager_sales'        => $feeClosingManagerSales,
            'fee_commision_manager_sales'      => $feeCommisionManagerSales,

            // Manager SMD berlaku untuk semua area
            'fee_closing_manager_smd'          => $calcClosing('fee_closing_manager_smd'),
            'fee_commision_manager_smd'        => $calcCommision('fee_commision_manager_smd'),

            'user_id'                          => $userId,
            'dt_create'                        => $now,
        ];
    }

    // Gunakan Database Transaction untuk memastikan keamanan input banyak data sekaligus
    try {
        DB::beginTransaction();

        if (!empty($insertData)) {
            DB::connection('sqlSrvBCKI')->table('fee')->insert($insertData);
        }

        DB::commit();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data fee berhasil dihitung dan disimpan ke database'
            // 'total'   => count($insertData),
            // 'data'    => $insertData
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status'  => 'error',
            'message' => 'Gagal menyimpan data: ' . $e->getMessage()
        ], 500);
    }
  }

  public function getdatafeesalesForClosing(Request $request){
    try {
          $sales_id   = $request->sales_id;
          $inv_type   = $request->inv_type;
          $start_date = $request->start_date;
          $end_date   = $request->end_date;

          $bindings = [];
          $whereClause = "1=1 ";

          // Filter Dynamic Parameter Binding
          if ($sales_id) {
              $whereClause .= " AND sales_id = ?";
              $bindings[] = $sales_id;
          }

          if ($inv_type) {
              $whereClause .= " AND inv_type = ?";
              $bindings[] = $inv_type;
          }

          if ($start_date) {
              if ($end_date) {
                  $whereClause .= " AND order_date BETWEEN CONVERT(datetime, ?) AND CONVERT(datetime, ?)";
                  $bindings[] = "{$start_date} 00:00:00.001";
                  $bindings[] = "{$end_date} 23:59:59.000";
              } else {
                  $whereClause .= " AND order_date BETWEEN CONVERT(datetime, ?) AND GETDATE()";
                  $bindings[] = "{$start_date} 00:00:00";
              }
          }

        $data = DB::connection('sqlSrvBCKI')
        ->table('v_sales_fee')
        ->select('*')
        ->whereRaw($whereClause, $bindings)
        
        // 1. Filter order_id_fee: Tidak NULL dan bukan string kosong/spasi
        ->whereRaw("NULLIF(LTRIM(RTRIM(CAST(order_id_fee AS VARCHAR))), '') IS NOT NULL")
        
        // 2. Filter dt_closing: Boleh NULL ATAU bertanggal 1900-01-01
        ->where(function ($query) {
            $query->whereNull('dt_closing')
                  ->orWhereRaw("CAST(dt_closing AS DATE) = '1900-01-01'")
                  ->orWhereRaw("NULLIF(LTRIM(RTRIM(CAST(dt_closing AS VARCHAR))), '') IS NULL");
        })
        ->get();

        return $this->sendResponse($data, 'Successfully get data');

      } catch (\Throwable $e) {
          return $this->sendError($e->getMessage());
      }
  }

  public function closingfee(Request $request)
  {
      $orders = $request->input('order', []);
      
      if (empty($orders)) {
          return response()->json([
              'status'  => 'error',
              'message' => 'Tidak ada data order yang dikirim.'
          ], 400);
      }

      $upsertData = [];
      $now = Carbon::now('Asia/Jakarta');

      foreach ($orders as $item) {
          $orderId = $item['orderid'] ?? '';
          $trType  = $item['inv_type'] ?? '';

          // Abaikan jika order_id kosong
          if (empty($orderId)) {
              continue;
          }

          $upsertData[] = [
              'order_id'   => $orderId,
              'tr_type'    => $trType,
              'dt_closing' => $now,
          ];
      }

      try {
          // Gunakan koneksi sqlSrvBCKI untuk transaksi
          DB::connection('sqlSrvBCKI')->beginTransaction();

          if (!empty($upsertData)) {
              // Parameter 1: Data yang dimasukkan
              // Parameter 2: Kolom acuan unik (order_id + tr_type)
              // Parameter 3: Kolom yang di-update jika data sudah ada
              DB::connection('sqlSrvBCKI')
                  ->table('fee')
                  ->upsert($upsertData, ['order_id', 'tr_type'], ['dt_closing']);
          }

          DB::connection('sqlSrvBCKI')->commit();

          return response()->json([
              'status'  => 'success',
              'message' => 'Data dt_closing fee berhasil diperbarui.'
          ], 200);

      } catch (\Exception $e) {
          DB::connection('sqlSrvBCKI')->rollBack();

          return response()->json([
              'status'  => 'error',
              'message' => 'Gagal memperbarui data: ' . $e->getMessage()
          ], 500);
      }
  }

  public function getdatafeesalesDoneClosing(Request $request){
    try {
          $sales_id   = $request->sales_id;
          $inv_type   = $request->inv_type;
          $start_date = $request->start_date;
          $end_date   = $request->end_date;

          $bindings = [];
          $whereClause = "1=1 ";

          // Filter Dynamic Parameter Binding
          if ($sales_id) {
              $whereClause .= " AND sales_id = ?";
              $bindings[] = $sales_id;
          }

          if ($inv_type) {
              $whereClause .= " AND inv_type = ?";
              $bindings[] = $inv_type;
          }

          if ($start_date) {
              if ($end_date) {
                  $whereClause .= " AND dt_closing BETWEEN CONVERT(datetime, ?) AND CONVERT(datetime, ?)";
                  $bindings[] = "{$start_date} 00:00:00.001";
                  $bindings[] = "{$end_date} 23:59:59.000";
              } else {
                  $whereClause .= " AND dt_closing BETWEEN CONVERT(datetime, ?) AND GETDATE()";
                  $bindings[] = "{$start_date} 00:00:00";
              }
          }

        $data = DB::connection('sqlSrvBCKI')
        ->table('v_sales_fee')
        ->select('*')
        ->whereRaw($whereClause, $bindings)
        
        // 1. Filter order_id_fee: Tidak NULL dan bukan string kosong/spasi
        ->whereRaw("NULLIF(LTRIM(RTRIM(CAST(order_id_fee AS VARCHAR))), '') IS NOT NULL")
        
        // 2. Filter dt_closing: Boleh NULL ATAU bertanggal 1900-01-01
        ->where(function ($query) {
            $query->whereNotNull('dt_closing')
                  ->WhereRaw("CAST(dt_closing AS DATE) != '1900-01-01'")
                  ->WhereRaw("NULLIF(LTRIM(RTRIM(CAST(dt_closing AS VARCHAR))), '') IS NOT NULL");
        })
        ->get();

        return $this->sendResponse($data, 'Successfully get data');

      } catch (\Throwable $e) {
          return $this->sendError($e->getMessage());
      }
  }

  public function unclosingfee(Request $request)
  {
      $orders = $request->input('order', []);
      
      if (empty($orders)) {
          return response()->json([
              'status'  => 'error',
              'message' => 'Tidak ada data order yang dikirim.'
          ], 400);
      }

      $upsertData = [];

      foreach ($orders as $item) {
          $orderId = $item['orderid'] ?? '';
          $trType  = $item['inv_type'] ?? '';

          // Abaikan jika order_id kosong
          if (empty($orderId)) {
              continue;
          }

          $upsertData[] = [
              'order_id'   => $orderId,
              'tr_type'    => $trType,
              'dt_closing' => null, // Set dt_closing menjadi NULL
          ];
      }

      try {
          // Gunakan koneksi sqlSrvBCKI untuk transaksi
          DB::connection('sqlSrvBCKI')->beginTransaction();

          if (!empty($upsertData)) {
              // Parameter 1: Data yang dimasukkan
              // Parameter 2: Kolom acuan unik (order_id + tr_type)
              // Parameter 3: Kolom yang di-update jika data sudah ada
              DB::connection('sqlSrvBCKI')
                  ->table('fee')
                  ->upsert($upsertData, ['order_id', 'tr_type'], ['dt_closing']);
          }

          DB::connection('sqlSrvBCKI')->commit();

          return response()->json([
              'status'  => 'success',
              'message' => 'Data dt_closing fee berhasil dihapus.'
          ], 200);

      } catch (\Exception $e) {
          DB::connection('sqlSrvBCKI')->rollBack();

          return response()->json([
              'status'  => 'error',
              'message' => 'Gagal menghapus data: ' . $e->getMessage()
          ], 500);
      }
  }

  public function uncalculatefee(Request $request)
  {
      $orders = $request->input('order', []);
      
      if (empty($orders)) {
          return response()->json([
              'status'  => 'error',
              'message' => 'Tidak ada data order yang dikirim.'
          ], 400);
      }

      $deleteData = [];

      foreach ($orders as $item) {
          $orderId = $item['orderid'] ?? '';
          $trType  = $item['inv_type'] ?? '';

          // Abaikan jika order_id kosong
          if (empty($orderId)) {
              continue;
          }

          $deleteData[] = [
              'order_id'   => $orderId,
              'tr_type'    => $trType,
          ];
      }

      try {
          // Gunakan koneksi sqlSrvBCKI untuk transaksi
          DB::connection('sqlSrvBCKI')->beginTransaction();

          if (!empty($deleteData)) {
              foreach ($deleteData as $data) {
                  DB::connection('sqlSrvBCKI')
                      ->table('fee')
                      ->where('order_id', $data['order_id'])
                      ->where('tr_type', $data['tr_type'])
                      ->delete();
              }
          }

          DB::connection('sqlSrvBCKI')->commit();

          return response()->json([
              'status'  => 'success',
              'message' => 'Data fee berhasil dihapus.'
          ], 200);

      } catch (\Exception $e) {
          DB::connection('sqlSrvBCKI')->rollBack();

          return response()->json([
              'status'  => 'error',
              'message' => 'Gagal menghapus data: ' . $e->getMessage()
          ], 500);
      }
  }
}
