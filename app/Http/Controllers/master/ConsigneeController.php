<?php

namespace App\Http\Controllers\master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsigneeController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/fpi/customer/ShowConsignee",
     *     tags={"Master Consignee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="string", example="ABC"),
     *             @OA\Property(property="cons_id", type="string", example="ABC"),
     *             
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns get all consignee",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error: Bad request. When required parameters were not supplied."
     *     )
     * )
     */
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $customerid = $request->customerid;
        $cons_id = $request->cons_id;

        if ($customerid) {
            $sqlWhere .= " and customerid = '$customerid'";
        }
        if ($cons_id) {
            $sqlWhere .= " and cons_id = '$cons_id'";
        }

        $datas = DB::connection('sqlSrvFPI')
            ->table('consignee')
            ->selectraw("
                        TRIM(customerid) as customer_id,
                        TRIM(cons_id) as cons_id,
                        TRIM(cons_name) as cons_name,
                        TRIM(alamat) as alamat,
                        TRIM(kota) as kota,
                        TRIM(propinsi) as propinsi,
                        TRIM(telp) as telp,
                        TRIM(person) as person,
                        dt_modified,
                        active_flag,
                        isDefault,
                        userID
                    ")
            ->whereRaw($sqlWhere)
            ->get();

        return $this->sendResponse($datas, 'Successfully get cust');
    }

    public function saveConsignee(Request $request)
    {
        try {
            $sqlWhere = "1=1";
            $isDefault = $request->isDefault;

            $data = DB::connection('sqlSrvFPI')
                ->table('consignee')
                ->where('customerid', $request->customerid)
                ->where('cons_id', $request->cons_id)
                ->first();

            if ($data) {
                //update
                $cons_id = $request->cons_id;
                if ($cons_id) {
                    $sqlWhere .= " and cons_id != '$cons_id'";
                }
                if ($isDefault) {
                    if ($isDefault == 'Y') {
                        $update = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->where('customerid', $request->customerid)
                            ->where('cons_id', $request->cons_id)
                            ->update([
                                'cons_name' => $request->cons_name ? $request->cons_name : ' ',
                                'alamat' => $request->alamat ? $request->alamat : ' ',
                                'kota' => $request->kota ? $request->kota : ' ',
                                'propinsi' => $request->propinsi ? $request->propinsi : ' ',
                                'telp' => $request->telp ? $request->telp : ' ',
                                'person' => $request->person ? $request->person : ' ',
                                'dt_modified' => now(),
                                'active_flag' => $request->active_flag ? $request->active_flag : ' ',
                                'isDefault' => $request->isDefault ? $request->isDefault : ' ',
                                'userID' => getAuth($request)->user_id2
                            ]);

                        $update = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->where('customerid', $request->customerid)
                            ->whereRaw($sqlWhere)
                            ->update([
                                'dt_modified' => now(),
                                'isDefault' => 'N',
                                'userID' => getAuth($request)->user_id2
                            ]);
                    } else {
                        $update = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->where('customerid', $request->customerid)
                            ->where('cons_id', $request->cons_id)
                            ->update([
                                'cons_name' => $request->cons_name ? $request->cons_name : ' ',
                                'alamat' => $request->alamat ? $request->alamat : ' ',
                                'kota' => $request->kota ? $request->kota : ' ',
                                'propinsi' => $request->propinsi ? $request->propinsi : ' ',
                                'telp' => $request->telp ? $request->telp : ' ',
                                'person' => $request->person ? $request->person : ' ',
                                'dt_modified' => now(),
                                'active_flag' => $request->active_flag ? $request->active_flag : ' ',
                                'isDefault' => $request->isDefault ? $request->isDefault : ' ',
                                'userID' => getAuth($request)->user_id2
                            ]);
                    }
                }
                return $this->sendResponse('Success Edit', 'Successfully edit Customer Consignee');
            } else {
                //insert
                $cons_id = $this->genConsID($request->customerid);
                if ($cons_id) {
                    $sqlWhere .= " and cons_id != '$cons_id'";
                }
                if ($isDefault) {
                    if ($isDefault == 'Y') {
                        $insert = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->insert([
                                'customerid' => $request->customerid ? $request->customerid : ' ',
                                'cons_id' => $cons_id,
                                'cons_name' => $request->cons_name ? $request->cons_name : ' ',
                                'alamat' => $request->alamat ? $request->alamat : ' ',
                                'kota' => $request->kota ? $request->kota : ' ',
                                'propinsi' => $request->propinsi ? $request->propinsi : ' ',
                                'telp' => $request->telp ? $request->telp : ' ',
                                'person' => $request->person ? $request->person : ' ',
                                'dt_modified' => now(),
                                'active_flag' => $request->active_flag ? $request->active_flag : ' ',
                                'isDefault' => $request->isDefault ? $request->isDefault : ' ',
                                'userID' => getAuth($request)->user_id2
                            ]);

                        $update = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->where('customerid', $request->customerid)
                            ->whereRaw($sqlWhere)
                            ->update([
                                'dt_modified' => now(),
                                'isDefault' => 'N',
                                'userID' => getAuth($request)->user_id2
                            ]);
                    } else {
                        $insert = DB::connection('sqlSrvFPI')
                            ->table('consignee')
                            ->insert([
                                'customerid' => $request->customerid ? $request->customerid : ' ',
                                'cons_id' => $cons_id,
                                'cons_name' => $request->cons_name ? $request->cons_name : ' ',
                                'alamat' => $request->alamat ? $request->alamat : ' ',
                                'kota' => $request->kota ? $request->kota : ' ',
                                'propinsi' => $request->propinsi ? $request->propinsi : ' ',
                                'telp' => $request->telp ? $request->telp : ' ',
                                'person' => $request->person ? $request->person : ' ',
                                'dt_modified' => now(),
                                'active_flag' => $request->active_flag ? $request->active_flag : ' ',
                                'isDefault' => $request->isDefault ? $request->isDefault : ' ',
                                'userID' => getAuth($request)->user_id2
                            ]);
                    }
                }
                return $this->sendResponse('Success Insert', 'Successfully insert Customer Consignee');
            }
        } catch (\Exception $e) {
            // DB::connection('sqlSrvFPI')->rollBack();
            return $this->sendError($e->getMessage(), 'Error', 500);
        }
    }

    private function genConsID($customerid)
    {
        $gen_cons_id = '';

        $getLastConsId = DB::connection('sqlSrvFPI')
            ->table('consignee')
            ->select('customerid', DB::raw('MAX(CAST(SUBSTRING(cons_id, CHARINDEX(\'-\', cons_id) + 1, LEN(cons_id)) AS INT)) as cons_id'))
            ->where('customerid', $customerid)
            ->groupBy('customerid')
            ->first();

        if ($getLastConsId) {
            $gen_cons_id = (int)$getLastConsId->cons_id;
            $gen_cons_id = $gen_cons_id + 1;

            if ($gen_cons_id >= 1 and $gen_cons_id < 10) {
                $gen_cons_id = $customerid . '-00' . $gen_cons_id;
            } else if ($gen_cons_id >= 10 and $gen_cons_id < 100) {
                $gen_cons_id = $customerid . '-0' . $gen_cons_id;
            } else if ($gen_cons_id >= 100) {
                $gen_cons_id = $customerid . '-' . $gen_cons_id;
            }
        } else {
            $gen_cons_id = $customerid . '-001';
        }
        return $gen_cons_id;
    }
}
