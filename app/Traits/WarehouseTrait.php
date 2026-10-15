<?php

namespace App\Traits;

use DB;
use Auth;
use Carbon\Carbon;

trait WarehouseTrait
{

    public function storeTransferHdr($arrayParams, $request)
    {
            
        // $arrayParams = (object)[
        //     'period' => $period ? $period : '',
        //     'office_id' => $office_id ? $office_id : '',
        //     'tr_type' => $tr_type ? $tr_type : '',
        //     'tr_date' => $now ? $now : '',
        //     'tr_id_ref' => $tr_id_ref ? $tr_id_ref : '',
        //     'wh_id' => $wh_id ? $wh_id : '',
        //     'bin_id' => $bin_id ? $bin_id : '',
        //     'remark' => $remark ? $remark : ''
        //     'payment' => $payment ? $payment : '',
        //     'wh_dest' => $wh_dest ? $wh_dest : '',
        //     'bin_dest' => $bin_dest ? $bin_dest : '',
        //     'office_id_dest' => $office_id_dest ? $office_id_dest : '',
        // ];

        try {
            // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

            $current =  DB::connection("sqlSrvBCKI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $arrayParams->office_id)
                            ->first();

            if ($current) {
                $period = $arrayParams->period;
                if ($current->curr_period == $period) {

                    // $yesterday_daily =  DB::connection("sqlSrvBCKI")
                    //                     ->table('period_daily')
                    //                     ->where('dept_id', 'PRODWH')
                    //                     ->where('office_id', $arrayParams->office_id)
                    //                     ->where('close_date', $yesterday_date)
                    //                     ->first();

                    // if ($yesterday_daily) {

                            $data = DB::connection('sqlSrvBCKI')
                                        ->table('inv_trans_hdr')
                                        ->where('office_id', $arrayParams->office_id)
                                        ->where('tr_type', $arrayParams->tr_type)
                                        ->where('tr_date', $arrayParams->tr_date)
                                        ->where('wh_id', $arrayParams->wh_id)
                                        ->where('bin_id', $arrayParams->bin_id)
                                        ->where('office_id_dest', $arrayParams->office_id_dest)
                                        ->where('wh_dest', $arrayParams->wh_dest)
                                        ->where('bin_dest', $arrayParams->bin_dest)
                                        ->first();

                            if ($data) {
                                $transId = $data->tr_id;
                            } else {
                                $transId = $this->genTrIdTrans($arrayParams->office_id, $arrayParams->tr_type);

                                $header = DB::connection('sqlSrvBCKI')
                                            ->table('inv_trans_hdr')
                                            ->insert([
                                                'office_id' => $arrayParams->office_id,
                                                'tr_type' => $arrayParams->tr_type,
                                                'tr_id' => $transId,
                                                'tr_date' => $arrayParams->tr_date,
                                                'wh_id' => $arrayParams->wh_id,
                                                'bin_id' => $arrayParams->bin_id,
                                                'office_id_dest' => $arrayParams->office_id_dest,
                                                'wh_dest' => $arrayParams->wh_dest,
                                                'bin_dest' => $arrayParams->bin_dest,
                                                'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                                'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : '',
                                                'dt_modified' => now(),
                                                'user_id' => getAuth($request)->user_id2
                                            ]);
                            }

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Success',
                                'response_time' => (microtime(TRUE) - LARAVEL_START),
                                'data' => $transId ? $transId : NULL
                            ];

                    // } else {
                    //     return (object)[
                    //         'status' => FALSE,
                    //         'message' => 'Belum di closing Harian',
                    //         'response_time' => (microtime(TRUE) - LARAVEL_START),
                    //         'data' => NULL
                    //     ];
                    // }  

                    
                } else {
                    return (object)[
                        'status' => FALSE,
                        'message' => 'Belum di closing',
                        'response_time' => (microtime(TRUE) - LARAVEL_START),
                        'data' => NULL
                    ];
                }   
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                    'data' => NULL
                ];
            } 
            
        }
        catch (\Exception $e) {
            return (object)[
                'status' => FALSE,
                'message' => $e->getMessage(),
                'response_time' => (microtime(TRUE) - LARAVEL_START),
                'data' => NULL
            ];
        }

    }

    public function storeTransHdr($arrayParams, $request)
    {
            
        // $arrayParams = (object)[
        //     'period' => $period ? $period : '',
        //     'office_id' => $office_id ? $office_id : '',
        //     'tr_type' => $tr_type ? $tr_type : '',
        //     'tr_date' => $now ? $now : '',
        //     'tr_id_ref' => $tr_id_ref ? $tr_id_ref : '',
        //     'wh_id' => $wh_id ? $wh_id : '',
        //     'bin_id' => $bin_id ? $bin_id : '',
        //     'remark' => $remark ? $remark : ''
        //     'payment' => $payment ? $payment : ''
        // ];

        try {
            // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

            $current =  DB::connection("sqlSrvBCKI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $arrayParams->office_id)
                            ->first();

            if ($current) {
                $period = $arrayParams->period;
                if ($current->curr_period == $period) {

                    // $yesterday_daily =  DB::connection("sqlSrvBCKI")
                    //                     ->table('period_daily')
                    //                     ->where('dept_id', 'PRODWH')
                    //                     ->where('office_id', $arrayParams->office_id)
                    //                     ->where('close_date', $yesterday_date)
                    //                     ->first();

                    // if ($yesterday_daily) {

                            $data = DB::connection('sqlSrvBCKI')
                                        ->table('inv_trans_hdr')
                                        ->where('office_id', $arrayParams->office_id)
                                        ->where('tr_type', $arrayParams->tr_type)
                                        ->where('tr_date', $arrayParams->tr_date)
                                        ->where('wh_id', $arrayParams->wh_id)
                                        ->where('bin_id', $arrayParams->bin_id)
                                        ->first();

                            if ($data) {
                                $transId = $data->tr_id;
                            } else {
                                $transId = $this->genTrIdTrans($arrayParams->office_id, $arrayParams->tr_type);

                                $header = DB::connection('sqlSrvBCKI')
                                            ->table('inv_trans_hdr')
                                            ->insert([
                                                'office_id' => $arrayParams->office_id,
                                                'tr_type' => $arrayParams->tr_type,
                                                'tr_id' => $transId,
                                                'tr_date' => $arrayParams->tr_date,
                                                'wh_id' => $arrayParams->wh_id,
                                                'bin_id' => $arrayParams->bin_id,
                                                'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                                'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : '',
                                                'dt_modified' => now(),
                                                'user_id' => getAuth($request)->user_id2,
                                            ]);
                            }

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Success',
                                'response_time' => (microtime(TRUE) - LARAVEL_START),
                                'data' => $transId ? $transId : NULL
                            ];

                    // } else {
                    //     return (object)[
                    //         'status' => FALSE,
                    //         'message' => 'Belum di closing Harian',
                    //         'response_time' => (microtime(TRUE) - LARAVEL_START),
                    //         'data' => NULL
                    //     ];
                    // }  

                    
                } else {
                    return (object)[
                        'status' => FALSE,
                        'message' => 'Belum di closing',
                        'response_time' => (microtime(TRUE) - LARAVEL_START),
                        'data' => NULL
                    ];
                }   
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                    'data' => NULL
                ];
            } 
            
        }
        catch (\Exception $e) {
            return (object)[
                'status' => FALSE,
                'message' => $e->getMessage(),
                'response_time' => (microtime(TRUE) - LARAVEL_START),
                'data' => NULL
            ];
        }

    }

    public function storeTransHdrIssue($arrayParams, $request)
    {
            
        // $arrayParams = (object)[
        //     'period' => $period ? $period : '',
        //     'office_id' => $office_id ? $office_id : '',
        //     'tr_type' => $tr_type ? $tr_type : '',
        //     'tr_date' => $now ? $now : '',
        //     'tr_id_ref' => $tr_id_ref ? $tr_id_ref : '',
        //     'wh_id' => $wh_id ? $wh_id : '',
        //     'bin_id' => $bin_id ? $bin_id : '',
        //     'remark' => $remark ? $remark : ''
        //     'payment' => $payment ? $payment : ''
        // ];

        try {
            // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

            $current =  DB::connection("sqlSrvBCKI")
                            ->table('period')
                            ->where('dept_id', 'PRODWH')
                            ->where('office_id', $arrayParams->office_id)
                            ->first();

            if ($current) {
                $period = $arrayParams->period;
                if ($current->curr_period == $period) {

                    // $yesterday_daily =  DB::connection("sqlSrvBCKI")
                    //                     ->table('period_daily')
                    //                     ->where('dept_id', 'PRODWH')
                    //                     ->where('office_id', $arrayParams->office_id)
                    //                     ->where('close_date', $yesterday_date)
                    //                     ->first();

                    // if ($yesterday_daily) {

                                $transId = $this->genTrIdTrans($arrayParams->office_id, $arrayParams->tr_type);

                                $header = DB::connection('sqlSrvBCKI')
                                            ->table('inv_trans_hdr')
                                            ->insert([
                                                'office_id' => $arrayParams->office_id,
                                                'tr_type' => $arrayParams->tr_type,
                                                'tr_id' => $transId,
                                                'tr_date' => $arrayParams->tr_date,
                                                'wh_id' => $arrayParams->wh_id,
                                                'bin_id' => $arrayParams->bin_id,
                                                'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                                'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : '',
                                                'dt_modified' => now(),
                                                'user_id' => getAuth($request)->user_id2,
                                                'id_site' => $arrayParams->id_site ? $arrayParams->id_site : '',
                                                'id_cluster' => $arrayParams->id_cluster ? $arrayParams->id_cluster : '',
                                                'id_house' => $arrayParams->id_house ? $arrayParams->id_house : '',
                                                'no_spk' => $arrayParams->no_spk ? $arrayParams->no_spk : '',
                                            ]);

                                return (object)[
                                    'status' => TRUE,
                                    'message' => 'Success',
                                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                                    'data' => $transId ? $transId : NULL
                                ];

                    // } else {
                    //     return (object)[
                    //         'status' => FALSE,
                    //         'message' => 'Belum di closing Harian',
                    //         'response_time' => (microtime(TRUE) - LARAVEL_START),
                    //         'data' => NULL
                    //     ];
                    // }  

                    
                } else {
                    return (object)[
                        'status' => FALSE,
                        'message' => 'Belum di closing',
                        'response_time' => (microtime(TRUE) - LARAVEL_START),
                        'data' => NULL
                    ];
                }   
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(TRUE) - LARAVEL_START),
                    'data' => NULL
                ];
            } 
            
        }
        catch (\Exception $e) {
            return (object)[
                'status' => FALSE,
                'message' => $e->getMessage(),
                'response_time' => (microtime(TRUE) - LARAVEL_START),
                'data' => NULL
            ];
        }

    }

    public function storeTransWH($arrayParams, $request)
    {
        $qty = (float) str_replace(',', '.', $arrayParams->qty_oh);

        $current =  DB::connection("sqlSrvBCKI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();

        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) { 

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }

                        $cek_stock = DB::connection("sqlSrvBCKI")
                                    ->table('inv_bal')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('article_id',$arrayParams->article_id)
                                    ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                                    ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                                    ->where('length', $arrayParams->length)
                                    ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                                    ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                                    ->where('wh_id', $arrayParams->wh_id)
                                    ->where('bin_id',$arrayParams->bin_id)
                                    ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                                    ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                                    ->first();
                        //   dd($cek_stock);
                        //   echo "cek stock: ".($cek_stock ? $cek_stock->qty_oh : '0')."\n";
                          if ($qty < 0) {
                            if ($cek_stock) {
                                $calculate_stock = (float) $cek_stock->qty_oh + $qty;
                                if ($calculate_stock < 0) {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda kurang',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                }
                            } else {
                                return (object)[
                                    'status' => FALSE,
                                    'message' => 'Maaf stock anda belum ada',
                                    'response_time' => (microtime(true) - LARAVEL_START),
                                    'data' => NULL
                                ];
                            }                      
                        }

                        if ($tr_type == 'I') {
                            if ($cek_stock) {
                                $calculate_stock = (float) $cek_stock->qty_oh - $qty;
                                if ($calculate_stock < 0) {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda kurang',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                }
                            }  else {
                                return (object)[
                                    'status' => FALSE,
                                    'message' => 'Maaf stock anda belum ada',
                                    'response_time' => (microtime(true) - LARAVEL_START),
                                    'data' => NULL
                                ];
                            }    
                        }

                        $seq_id = DB::connection("sqlSrvBCKI")
                                    ->table('inv_trans_dtl')
                                    ->select('seq_id')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->max('seq_id');

                        $insertItem = DB::connection('sqlSrvBCKI')
                                    ->table('inv_trans_dtl')
                                    ->insert([
                                        'office_id' => $arrayParams->office_id,
                                        'tr_type' => $arrayParams->tr_type,
                                        'tr_id' => $arrayParams->tr_id,
                                        'seq_id' => $seq_id + 1,
                                        'article_id' => $arrayParams->article_id,
                                        'wh_id' => $arrayParams->wh_id,
                                        'bin_id' => $arrayParams->bin_id,       
                                        'defect' => $arrayParams->defect ? $arrayParams->defect : 0,
                                        'batch_id' => $arrayParams->batch_id ? $arrayParams->batch_id : ' ',
                                        'add_id' => $arrayParams->add_id ? $arrayParams->add_id : ' ',
                                        'tor_id' => $arrayParams->tor_id ? $arrayParams->tor_id : ' ',
                                        'spk_id' => $arrayParams->spk_id ? $arrayParams->spk_id : ' ',
                                        'spk_item' => $arrayParams->spk_item ? $arrayParams->spk_item : 0,
                                        'length' => (float)$arrayParams->length,
                                        'amt_item' => (float)$arrayParams->amt_item ? (float)$arrayParams->amt_item : 0,
                                        'unit_price' => (float)$arrayParams->unit_price ? (float)$arrayParams->unit_price : 0,                                    
                                        'qty' => $qty,
                                        // ✅ FIX WGT (pakai qty float)
                                        'wgt' => $arrayParams->satuan == 'M' 
                                            ? $qty * (float)$arrayParams->length * (float)$arrayParams->berat 
                                            : $qty * (float)$arrayParams->berat,          

                                        'remark' => $arrayParams->remark ? $arrayParams->remark : '',
                                        'period' => $arrayParams->period,
                                        'tr_id_ref' => $arrayParams->tr_id_ref ? $arrayParams->tr_id_ref : ' ',
                                        'tr_id_seq_ref' => $arrayParams->tr_id_seq_ref ? $arrayParams->tr_id_seq_ref : ' ',
                                        'dt_modified' => now(),
                                        'user_id' => getAuth($request)->user_id2,
                                        'uom' => $arrayParams->satuan ? $arrayParams->satuan : '',
                                        'wgt_unit' => $arrayParams->berat ? (float)$arrayParams->berat : 0
                                    ]);

                        $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                       
                        $updateQty = $this->updateQtyInvBal(
                            $transQty ? ($transQty->qty ? (float) $transQty->qty : 0) : 0,
                            $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0,
                            $arrayParams,
                            $tr_type,
                            $tr_types,
                            $request
                        );
                        
                        return (object)[
                            'status' => TRUE,
                            'message' => 'Successfully saved',
                            'response_time' => (microtime(true) - LARAVEL_START),
                            'data' => $arrayParams->tr_id
                        ];
                // }                           
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    public function deleteTransWh($arrayParams, $request)
    {

        // $arrayParams = (object)[
        //     'tr_id' => $request->tr_id,
        //     'office_id' => $request->office_id,
        //     'period' => $request->period,
        //     'tr_type' => $request->tr_type,
        //     'tr_date' => $request->tr_date,
        //     'seq_id' => $request->seq_id,
        //     'article_id' => $request->article_id,
        //     'wh_id' => $request->wh_id,
        //     'bin_id' => $request->bin_id,
        //     'prod_code' => $request->article_id,
        //     'add_id' => $request->add_id ? $request->add_id : ' ',
        //     'tor_id' => $request->tor_id ? $request->tor_id : ' ',
        //     'batch_id' => $request->batch_id ? $request->batch_id : ' ',
        //     'length' => $request->length, 
        //     'defect' => $request->defect ? $request->defect : 0,
        //     'spk_id' => $request->spk_id ? $request->spk_id : ' ',
        //     'spk_item' => $request->spk_item ? $request->spk_item : 0,
        //     'BarangJadiId' => $request->article_id
        // ];

        // $yesterday_date = Carbon::parse($arrayParams->tr_date)->subDays(1)->format('Y-m-d');

        $current =  DB::connection("sqlSrvBCKI")
                        ->table('period')
                        ->where('dept_id', 'PRODWH')
                        ->where('office_id', $arrayParams->office_id)
                        ->first();

        if ($current) {
            $period = $arrayParams->period;
            if ($current->curr_period == $period) {

                // $yesterday_daily =  DB::connection("sqlSrvBCKI")
                //                     ->table('period_daily')
                //                     ->where('dept_id', 'PRODWH')
                //                     ->where('office_id', $arrayParams->office_id)
                //                     ->where('close_date', $yesterday_date)
                //                     ->first();

                // if ($yesterday_daily) {

                        $tr_type = $this->caseTrType($arrayParams->tr_type);
                        if ($tr_type == 'R') {
                            $tr_types = ['MR', 'RC', 'RW', 'SR', 'TR', 'RP'];
                        } elseif ($tr_type == 'I') {
                            $tr_types = ['IS', 'IW', 'MI', 'TI', 'PR', 'SA'];
                        } elseif ($tr_type == 'A') {
                            $tr_types = ['IA'];
                        }  else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Please insert TR Type',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }

                        $data = DB::connection("sqlSrvBCKI")
                                ->table('inv_trans_dtl')
                                ->where('office_id', $arrayParams->office_id)
                                ->where('tr_type', $arrayParams->tr_type)
                                ->where('tr_id', $arrayParams->tr_id)
                                ->where('seq_id', $arrayParams->seq_id)
                                ->first();

                        if ($data){
                            $cek_stock = DB::connection("sqlSrvBCKI")
                                        ->table('inv_bal')
                                        ->where('office_id', $arrayParams->office_id)
                                        ->where('article_id',$arrayParams->article_id)
                                        ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                                        ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                                        ->where('length', $arrayParams->length)
                                        ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                                        ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                                        ->where('wh_id', $arrayParams->wh_id)
                                        ->where('bin_id',$arrayParams->bin_id)
                                        ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                                        ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                                        ->first();

                            if ((int) $data->qty > 0) {
                                if ($cek_stock) {
                                    if ((int) $cek_stock->qty_oh < (int) $data->qty) {
                                        return (object)[
                                            'status' => FALSE,
                                            'message' => 'Maaf stock anda kurang',
                                            'response_time' => (microtime(true) - LARAVEL_START),
                                            'data' => NULL
                                        ];
                                    }
                                } else {
                                    return (object)[
                                        'status' => FALSE,
                                        'message' => 'Maaf stock anda belum ada',
                                        'response_time' => (microtime(true) - LARAVEL_START),
                                        'data' => NULL
                                    ];
                                } 
                            }

                            $delete = DB::connection("sqlSrvBCKI")
                                    ->table('inv_trans_dtl')
                                    ->where('office_id', $arrayParams->office_id)
                                    ->where('tr_type', $arrayParams->tr_type)
                                    ->where('tr_id', $arrayParams->tr_id)
                                    ->where('seq_id', $arrayParams->seq_id)
                                    ->delete();

                            $data_hdr_wh_transfer = DB::connection("sqlSrvBCKI")
                                                    ->table('inv_trans_dtl')
                                                    ->where('office_id', $arrayParams->office_id)
                                                    ->where('tr_type', $arrayParams->tr_type)
                                                    ->where('tr_id', $arrayParams->tr_id)
                                                    ->count();

                            if ((int) $data_hdr_wh_transfer == 0) {
                                $delete_wh_hdr_transfer = DB::connection("sqlSrvBCKI")
                                                            ->table('inv_trans_hdr')
                                                            ->where('office_id', $arrayParams->office_id)
                                                            ->where('tr_type', $arrayParams->tr_type)
                                                            ->where('tr_id', $arrayParams->tr_id)
                                                            ->delete();
                            }


                            $transQty = $this->GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types);
                            $updateQty = $this->updateQtyInvBal($transQty ? ($transQty->qty ? (int) $transQty->qty : 0) : 0, $transQty ? ($transQty->wgt ? (double) $transQty->wgt : 0) : 0, $arrayParams, $tr_type, $tr_types, $request);

                            return (object)[
                                'status' => TRUE,
                                'message' => 'Successfully saved',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];


                        } else {
                            return (object)[
                                'status' => FALSE,
                                'message' => 'Data not exists',
                                'response_time' => (microtime(true) - LARAVEL_START),
                                'data' => NULL
                            ];
                        }                    
                // }  else {
                //     return (object)[
                //         'status' => FALSE,
                //         'message' => 'Belum di closing',
                //         'response_time' => (microtime(true) - LARAVEL_START),
                //         'data' => NULL
                //     ];
                // }                          
                
            } else {
                return (object)[
                    'status' => FALSE,
                    'message' => 'Belum di closing',
                    'response_time' => (microtime(true) - LARAVEL_START),
                    'data' => NULL
                ];
            }   
        } else {
            return (object)[
                'status' => FALSE,
                'message' => 'Belum di closing',
                'response_time' => (microtime(true) - LARAVEL_START),
                'data' => NULL
            ];
        } 

    }

    private function genTrIdTrans($office_id, $tr_type)
    {
        $dateYear = substr( date("Y"), -2);
        $dateMonth = date("m");
        $dateDay = date("d");
        $gen_tr_id = '';

        $getLastTrID =  DB::connection('sqlSrvBCKI')
                        ->table('inv_trans_hdr')
                        ->where('office_id', '=', trim($office_id))
                        ->where('tr_id', 'like', "%".$tr_type.$dateYear.$dateMonth.$dateDay."%")
                        ->where('tr_type', $tr_type)
                        ->max('tr_id');

        if($getLastTrID){
            $seq = substr( trim($getLastTrID), -3) + 1;
            $gen_tr_id = $tr_type.$dateYear.$dateMonth.$dateDay.str_pad($seq, 3, '0', STR_PAD_LEFT);
        } else {
            $gen_tr_id = $tr_type.$dateYear.$dateMonth.$dateDay.'001';
        }

        return $gen_tr_id;

    }

    private function GetQtyTransInvDetil($arrayParams, $tr_type, $tr_types)
    {
        $data = DB::connection('sqlSrvBCKI')
                    ->table('inv_trans_dtl')
                    ->selectRaw('sum(qty) as qty, sum(wgt) as wgt')
                    ->where('office_id', $arrayParams->office_id)
                    ->whereIn('tr_type', $tr_types)
                    ->where('article_id', $arrayParams->article_id)
                    ->where('wh_id', $arrayParams->wh_id)
                    ->where('bin_id', $arrayParams->bin_id)
                    ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                    ->where('period', $arrayParams->period)
                    ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                    ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                    ->where('length', $arrayParams->length)
                    ->where('defect', $arrayParams->defect ? $arrayParams->defect : 0)
                    ->where('spk_id', $arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                    ->where('spk_item', $arrayParams->spk_item ? $arrayParams->spk_item : 0)                    
                    ->first();

        return $data;
    }

    public function updateQtyInvBal($QtyTrans, $WgtTrans, $arrayParams, $tr_type, $tr_types, $request)
    {
        $data = DB::connection("sqlSrvBCKI")
                ->table('inv_bal')
                ->where('office_id', $arrayParams->office_id)
                ->where('article_id',$arrayParams->article_id)
                ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                ->where('length', $arrayParams->length)
                ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                ->where('wh_id', $arrayParams->wh_id)
                ->where('bin_id',$arrayParams->bin_id)
                ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                ->first();

        if ($data) {
            if ($tr_type == 'R') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $QtyTrans - (float) $data->qty_iss + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $WgtTrans - (double) $data->wgt_iss + (double) $data->wgt_ia - (double) $data->wgt_waste;

                $update = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                        ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                        ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                        ->where('wh_id', $arrayParams->wh_id)
                        ->where('bin_id',$arrayParams->bin_id)
                        ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                        ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                        ->update([
                            'qty_rcv' => $QtyTrans,
                            'wgt_rcv' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            } elseif ($tr_type == 'I') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $QtyTrans + (float) $data->qty_ia - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $WgtTrans + (double) $data->wgt_ia - (double) $data->wgt_waste;

                $update = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                        ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                        ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                        ->where('wh_id', $arrayParams->wh_id)
                        ->where('bin_id',$arrayParams->bin_id)
                        ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                        ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                        ->update([
                            'qty_iss' => $QtyTrans,
                            'wgt_iss' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            } elseif ($tr_type == 'A') {
                $hitung_qty_oh = (float) $data->qty_awal + (float) $data->qty_rcv - (float) $data->qty_iss + (float) $QtyTrans - (float) $data->qty_waste;
                $hitung_wgt_oh = (double) $data->wgt_awal + (double) $data->wgt_rcv - (double) $data->wgt_iss + (double) $WgtTrans - (double) $data->wgt_waste;


                $update = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->where('office_id', $arrayParams->office_id)
                        ->where('article_id',$arrayParams->article_id)
                        ->where('defect',$arrayParams->defect ? $arrayParams->defect : 0)
                        ->where('batch_id', $arrayParams->batch_id ? $arrayParams->batch_id : ' ')
                        ->where('length', $arrayParams->length)
                        ->where('add_id', $arrayParams->add_id ? $arrayParams->add_id : ' ')
                        ->where('tor_id', $arrayParams->tor_id ? $arrayParams->tor_id : ' ')
                        ->where('wh_id', $arrayParams->wh_id)
                        ->where('bin_id',$arrayParams->bin_id)
                        ->where('spk_id',$arrayParams->spk_id ? $arrayParams->spk_id : ' ')
                        ->where('spk_item',$arrayParams->spk_item ? $arrayParams->spk_item : 0)
                        ->update([
                            'qty_ia' => $QtyTrans,
                            'wgt_ia' => $WgtTrans,
                            'qty_oh' => (float) $hitung_qty_oh,
                            'wgt_oh' => (double) $hitung_wgt_oh,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            } else {
                $hitung_qty_oh = 0;
                $hitung_wgt_oh = 0;
            }
        } else {
            if ($tr_type == 'R') {
                $insert = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ? $arrayParams->defect : 0,
                            'batch_id' => $arrayParams->batch_id ? $arrayParams->batch_id : " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ? $arrayParams->add_id : " ",
                            'tor_id' => $arrayParams->tor_id ? $arrayParams->tor_id : " ",
                            'wh_id' => $arrayParams->wh_id,
                            'bin_id' => $arrayParams->bin_id,
                            'spk_id' => $arrayParams->spk_id ? $arrayParams->spk_id : ' ',
                            'spk_item' => $arrayParams->spk_item ? $arrayParams->spk_item : 0,
                            'qty_awal' => 0,
                            'qty_oh' => (float) (0 + 0 + $QtyTrans - 0 + 0 - 0 - 0),
                            'qty_rcv' => $QtyTrans,
                            'qty_iss' => 0,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + $WgtTrans - 0 + 0 - 0 - 0,
                            'wgt_rcv' => $WgtTrans,
                            'wgt_iss' => 0,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            } elseif ($tr_type == 'A') {
                $insert = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ? $arrayParams->defect : 0,
                            'batch_id' => $arrayParams->batch_id ? $arrayParams->batch_id : " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ? $arrayParams->add_id : " ",
                            'tor_id' => $arrayParams->tor_id ? $arrayParams->tor_id : " ",
                            'wh_id' => $arrayParams->wh_id,
                            'bin_id' => $arrayParams->bin_id,
                            'spk_id' => $arrayParams->spk_id ? $arrayParams->spk_id : ' ',
                            'spk_item' => $arrayParams->spk_item ? $arrayParams->spk_item : 0,
                            'qty_awal' => 0,
                            'qty_oh' => (float) (0 + 0 + 0 - 0 + $QtyTrans - 0 - 0),
                            'qty_rcv' => 0,
                            'qty_iss' => 0,
                            'qty_ia' => $QtyTrans,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - 0 + $WgtTrans - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => 0,
                            'wgt_ia' => $WgtTrans,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            } elseif ($tr_type == 'I') {
                $insert = DB::connection("sqlSrvBCKI")
                        ->table('inv_bal')
                        ->insert([
                            'office_id' => $arrayParams->office_id,
                            'article_id' => $arrayParams->article_id,
                            'defect' => $arrayParams->defect ? $arrayParams->defect : 0,
                            'batch_id' => $arrayParams->batch_id ? $arrayParams->batch_id : " ",
                            'length' => $arrayParams->length,
                            'add_id' => $arrayParams->add_id ? $arrayParams->add_id : " ",
                            'tor_id' => $arrayParams->tor_id ? $arrayParams->tor_id : " ",
                            'wh_id' => $arrayParams->wh_id,
                            'bin_id' => $arrayParams->bin_id,
                            'spk_id' => $arrayParams->spk_id ? $arrayParams->spk_id : ' ',
                            'spk_item' => $arrayParams->spk_item ? $arrayParams->spk_item : 0,
                            'qty_awal' => 0,
                            'qty_oh' => (float) (0 + 0 + 0 - $QtyTrans + 0 - 0 - 0),
                            'qty_rcv' => 0,
                            'qty_iss' => $QtyTrans,
                            'qty_ia' => 0,
                            'qty_waste' => 0,
                            'wgt_awal' => 0,
                            'wgt_oh' => 0 + 0 + 0 - $WgtTrans + 0 - 0 - 0,
                            'wgt_rcv' => 0,
                            'wgt_iss' => $WgtTrans,
                            'wgt_ia' => 0,
                            'wgt_waste' => 0,
                            'avg_price' => 0,
                            'amt_balance' => 0,
                            'dt_modified' => now(),
                            'user_id' => getAuth($request)->user_id2
                        ]);

            }
        }

        return $data;
    }

    private function caseTrType($tr_type)
    {
        if ($tr_type == 'IS' || $tr_type == 'IW' || $tr_type == 'MI' || $tr_type == 'TI' || $tr_type == 'PR' || $tr_type == 'SA') {
            $tr_type = 'I';
            // Issue
        } elseif ($tr_type == 'MR' || $tr_type == 'RC' || $tr_type == 'RW' || $tr_type == 'SR' || $tr_type == 'TR' || $tr_type == 'RP') {
            $tr_type = 'R';
            //Receive
        } elseif ($tr_type == 'IA') {
            $tr_type = 'A';
            //Adjust
        } else {
            $tr_type = '';
        }        

        return $tr_type;
    }

}