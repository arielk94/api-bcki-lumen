<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use Illuminate\Support\Facades\DB;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->options('{any:.*}', function () {
    return response('', 200);
});

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/api/documentation', function () {
    return view('swagger-lume::swagger');
});

$router->get('/docs', function () {
    return response()->file(storage_path('api-docs/swagger.json'));
});

$router->get('/check-db', function () {
    $connections = config('database.connections');
    $results = [];

    foreach ($connections as $name => $config) {
        try {
            $connection = app('db')->connection($name);
            $dbName = $connection->getDatabaseName();
            $dbHost = config("database.connections.$name.host"); // ✅ ini benar
            $results[] = "[$name] connected to database: $dbName; Database Host => $dbHost";
        } catch (\Exception $e) {
            $results[] = "[$name] connection failed: " . $e->getMessage();
        }
    }

    return implode("<br>", $results);
});

$router->group(['prefix' => 'api/public'], function ($router) {
    $router->group(['prefix' => 'master'], function ($router) {
        $router->post('/getApplConstant', 'master\masterController@getApplConstant');
    });
    $router->group(['prefix' => 'site'], function ($router) {
        $router->post('/getSite', 'master\masterController@getSite');
    });
    $router->group(['prefix' => 'cluster'], function ($router) {
        $router->post('/getCluster', 'master\masterController@getCluster');
    });
    $router->group(['prefix' => 'houseUnit'], function ($router) {
        $router->post('/getHouseUnit', 'master\masterController@getHouseUnit');
    });
    $router->group(['prefix' => 'telemarketing'], function ($router) {
        $router->post('/saveTelemarketingWithoutCreateCustomer', 'sales\TelemarketingController@saveTelemarketingWithoutCreateCustomer');
        $router->post('/saveTelemarketingWithCreateCustomer', 'sales\TelemarketingController@saveTelemarketingWithCreateCustomer');
        $router->post('/history', 'sales\TelemarketingController@getHistoryTelemarketing');
    });
});

$router->group(['middleware' => 'check.auth'], function () use ($router) {
    $router->group(['prefix' => '/api/fpi'], function () use ($router) {
        $router->group(['prefix' => 'master'], function ($router) {
            $router->post('/getProvince', 'master\masterController@getProvince');
            $router->post('/getCity', 'master\masterController@getCity');
            $router->post('/getDistrict', 'master\masterController@getDistrict');
            $router->post('/getVillage', 'master\masterController@getVillage');

            $router->post('/getDepartment', 'purchasing\PurchaseRequestAprv@getDepartment');
            $router->post('/getConfAprv', 'purchasing\PurchaseRequestAprv@getConfAprv');

            $router->post('/getCustomer', 'master\masterController@getCustomer');
            $router->post('/getApplConstant', 'master\masterController@getApplConstant');
            $router->post('/getApplConstantMasterCustomer', 'master\masterController@getApplConstantMasterCustomer');


            $router->post('/CreateConsignee', 'master\ConsigneeController@saveConsignee');
            $router->post('/ShowConsignee', 'master\ConsigneeController@index');

            $router->post('/getPayTerm2', 'master\masterController@getPayTerm2');
            $router->post('/getbankaccount', 'master\masterController@getbankaccount');
            $router->post('/getbankacc', 'master\masterController@getbankacc');
            $router->post('/getVendor', 'master\masterController@getVendor');
            $router->post('/getEmployeeHrdOl', 'master\masterController@getEmployeeHrdOl');
            $router->post('/getDeptHrdOl', 'master\masterController@getDeptHrdOl');
            $router->post('/getWhoWillApprove', 'master\masterController@getWhoWillApprove');
            $router->post('/getDocumentStatus', 'master\masterController@getDocumentStatus');
            $router->post('/getDataMasterFinance', 'master\masterController@getDataMasterFinance');
            $router->post('/getSource', 'master\masterController@getSource');


            $router->post('/getFinanceReport', 'master\FinanceController@index');
            $router->post('/getReportName', 'master\FinanceController@getReportName');
            $router->post('/getEntity', 'master\FinanceController@getEntity');
            $router->post('/getGroup', 'master\FinanceController@getGroup');
            $router->post('/getAcNum', 'master\FinanceController@getAcNum');

            $router->post('/insertAcNum', 'master\FinanceController@insertAcNum');
            $router->post('/deleteAcNum', 'master\FinanceController@deleteAcNum');

            $router->group(['prefix' => '/g_ledger'], function () use ($router) {
                $router->post('/', 'master\ChartofAccountController@index');
                $router->post('/store', 'master\ChartofAccountController@store');
                $router->post('/updateStatus', 'master\ChartofAccountController@updateStatus');
            });

            $router->post('/sourceData', 'finance\JurnalHarianController@getSourceData');

            $router->post('/getNoNota', 'NoUrutNotaController@getNoUrut');

            $router->group(['prefix' => 'commodity'], function ($router) {
                $router->post('/', 'master\CommodityController@index');
                $router->post('/show', 'master\CommodityController@show');
            });

            $router->group(['prefix' => 'g_ledger_map'], function ($router) {
                $router->post('/', 'master\gLedgerMapController@index');
                $router->post('/store', 'master\gLedgerMapController@store');
                $router->post('/delete', 'master\gLedgerMapController@delete');
                $router->post('/update', 'master\gLedgerMapController@update');
                $router->post('/getDistinctRep', 'master\gLedgerMapController@getDistinctRep');
            });

            $router->group(['prefix' => '/bank'], function () use ($router) {
                $router->post('/', 'master\BankMasterController@index');
                $router->post('/account', 'master\BankMasterController@account');
                $router->post('/bank_mast', 'master\BankMasterController@bank_mast');
                $router->post('/getData', 'master\BankMasterController@getData');
                $router->post('/store', 'master\BankMasterController@store');
                $router->post('/update', 'master\BankMasterController@update');
                $router->post('/updateStatus', 'master\BankMasterController@updateStatus');
                $router->post('/upload_csv', 'master\BankMasterController@upload_csv');
            });

            $router->post('/getCurrency', 'master\masterController@getCurrency');
            $router->post('/storeCOA', 'master\masterController@storeCOA');
            $router->post('/getDataAcNum', 'master\masterController@getDataAcNum');

            $router->group(['prefix' => 'region'], function () use ($router) {
                $router->post('/getRegion', 'master\masterController@getRegion');
                $router->post('/updateRegion', 'master\masterController@updateRegion');
                $router->post('/storeRegion', 'master\masterController@storeRegion');
                $router->post('/deleteRegion', 'master\masterController@deleteRegion');
                $router->post('/activateRegion', 'master\masterController@activateRegion');
            });
            $router->group(['prefix' => 'office'], function () use ($router) {
                $router->post('/getOffice', 'master\masterController@getOffice');
                $router->post('/updateOffice', 'master\masterController@updateOffice');
                $router->post('/storeOffice', 'master\masterController@storeOffice');
                $router->post('/deleteOffice', 'master\masterController@deleteOffice');
                $router->post('/activateOffice', 'master\masterController@activateOffice');
            });
            $router->group(['prefix' => 'vendor'], function () use ($router) {
                $router->post('/getVendorMaster', 'master\masterController@getVendorMaster');
                $router->post('/updateVendor', 'master\masterController@updateVendor');
                $router->post('/storeVendor', 'master\masterController@storeVendor');
                $router->post('/deleteVendor', 'master\masterController@deleteVendor');
                $router->post('/activateVendor', 'master\masterController@activateVendor');
                $router->post('/getCurrency', 'master\masterController@getCurrency');
            });
            $router->group(['prefix' => 'customer'], function () use ($router) {
                $router->post('/insertcust', 'master\CustomerController@saveCustomer');
                $router->post('/showcust', 'master\CustomerController@index');
                $router->post('/CustGroup', 'master\CustomerController@showCustGroup');
                $router->post('/setActiveCust', 'master\CustomerController@setActiveCust');

                $router->post('/getProvince', 'master\CustomerController@getProvince');
                $router->post('/getCity', 'master\CustomerController@getCity');
                $router->post('/getDistrict', 'master\CustomerController@getDistrict');
                $router->post('/getVillage', 'master\CustomerController@getVillage');
                $router->post('/getDocument', 'master\CustomerController@getDocument');
                $router->post('/storeDocument', 'master\CustomerController@storeDocument');
                $router->post('/getListNpwp', 'master\CustomerController@getListNpwp');
                $router->post('/storeNpwp', 'master\CustomerController@storeNpwp');
                $router->post('/getListNitku', 'master\CustomerController@getListNitku');
                $router->post('/storeNitku', 'master\CustomerController@storeNitku');
                $router->post('/setActiveFlagNitku', 'master\CustomerController@setActiveFlagNitku');
            });

            $router->group(['prefix' => 'article'], function ($router) {
                $router->post('/', 'master\ArticleController@index');
                $router->post('/saveArticle', 'master\ArticleController@saveArticle');
                $router->post('/showCat', 'master\ArticleController@showCategory');
                $router->post('/showCom', 'master\ArticleController@showCommodity');
                $router->post('/showComGrp', 'master\ArticleController@showCommodityGrp');
                $router->post('/showArticleID', 'master\ArticleController@showArticleID');
                $router->post('/showBrand', 'master\ArticleController@showBrandID');
                $router->post('/showUOM', 'master\ArticleController@showUOM');
                $router->post('/showAC', 'master\ArticleController@showACnum');
                $router->post('/showCostCenter', 'master\ArticleController@showCostCenter');
                $router->post('/showBarangJadi', 'master\ArticleController@showBarangJadiID');
                $router->post('/showVendor', 'master\ArticleController@showVendor');
            });
            $router->group(['prefix' => 'gledgeryear'], function () use ($router) {
                $router->post('/', 'master\gLedgerYearController@index');
                $router->post('/DisplayDetailLedgerbyId', 'master\gLedgerYearController@DisplayDetailLedgerbyId');
                $router->post('/DisplayTransactionDetailbyAcNum', 'master\gLedgerYearController@DisplayTransactionDetailbyAcNum');
                $router->post('/store', 'master\gLedgerYearController@store');
                $router->post('/displayCoa', 'master\gLedgerYearController@displayCoa');
                $router->post('/DisplayTransactionDetailbyAcNum', 'master\gLedgerYearController@DisplayTransactionDetailbyAcNum');
            });

            $router->post('/sourceData', 'master\SourceJournalController@index');

            $router->group(['prefix' => 'nota'], function () use ($router) {
                $router->post('/setNota', 'NoUrutNotaController@updateNoUrut');
            });
        });

        $router->group(['prefix' => 'article'], function ($router) {
            $router->post('/', 'master\ArticleController@getArticle');
            $router->post('/getArticle', 'master\ArticleController@getArticle');
            $router->post('/getCategory', 'master\ArticleController@getCategory');
            $router->post('/getUOM', 'master\ArticleController@getUOM');
            $router->post('/getArticle', 'master\ArticleController@getArticle');
            $router->post('/saveArticle', 'master\ArticleController@saveArticle');
            $router->post('/deleteArticle', 'master\ArticleController@deleteArticle');
            $router->post('/activateArticle', 'master\ArticleController@activateArticle');
            $router->post('/getWhArticle', 'master\ArticleController@getWhArticle');
            $router->post('/getCommodity', 'master\ArticleController@getCommodity');
        });

        $router->group(['prefix' => 'houseType'], function ($router) {
            $router->post('/getHouseType', 'master\masterController@getHouseType');
            $router->post('/storeHouseType', 'master\masterController@storeHouseType');
            $router->post('/getDetailHouseType', 'master\masterController@getDetailHouseType');
            $router->post('/updateHouseType', 'master\masterController@updateHouseType');
            $router->post('/deleteHouseType', 'master\masterController@deleteHouseType');
            $router->post('/activateHouseType', 'master\masterController@activateHouseType');
        });

        $router->group(['prefix' => 'site'], function ($router) {
            $router->post('/getSite', 'master\masterController@getSite');
            $router->post('/getAllSite', 'master\masterController@getAllSite');
            $router->post('/storeSite', 'master\masterController@storeSite');
            $router->post('/getDetailSite', 'master\masterController@getDetailSite');
            $router->post('/updateSite', 'master\masterController@updateSite');
            $router->post('/deleteSite', 'master\masterController@deleteSite');
            $router->post('/activateSite', 'master\masterController@activateSite');
        });

        $router->group(['prefix' => 'cluster'], function ($router) {
            $router->post('/getCluster', 'master\masterController@getCluster');
            $router->post('/storeCluster', 'master\masterController@storeCluster');
            $router->post('/getDetailCluster', 'master\masterController@getDetailCluster');
            $router->post('/updateCluster', 'master\masterController@updateCluster');
            $router->post('/deleteCluster', 'master\masterController@deleteCluster');
            $router->post('/activateCluster', 'master\masterController@activateCluster');
        });

        $router->group(['prefix' => 'contractor'], function ($router) {
            $router->post('/getContractor', 'master\masterController@getContractor');
            $router->post('/storeContractor', 'master\masterController@storeContractor');
            $router->post('/getDetailContractor', 'master\masterController@getDetailContractor');
            $router->post('/updateContractor', 'master\masterController@updateContractor');
            $router->post('/deleteContractor', 'master\masterController@deleteContractor');
            $router->post('/activateContractor', 'master\masterController@activateContractor');
        });

        $router->group(['prefix' => 'houseUnit'], function ($router) {
            $router->post('/getHouseUnit', 'master\masterController@getHouseUnit');
            $router->post('/createHouseUnit', 'master\masterController@createHouseUnit');
            $router->post('/updateHouseUnit', 'master\masterController@updateHouseUnit');
            $router->post('/saveKoordinat', 'master\masterController@saveKoordinat');
            $router->post('/getKoordinat', 'master\masterController@getKoordinat');
            $router->post('/updateHousePrice', 'master\masterController@updateHousePrice');
            $router->post('/setActiveHouseUnit', 'master\masterController@setActiveHouseUnit');
        });

        $router->group(['prefix' => 'customer'], function ($router) {
            $router->post('/insertcust', 'master\CustomerController@saveCustomer');
            $router->post('/showcust', 'master\CustomerController@index');
            $router->post('/CustGroup', 'master\CustomerController@CustGroup');
            $router->post('/office', 'master\CustomerController@office');
            $router->post('/switch-active', 'master\CustomerController@switchActive');
            $router->post('/CreateConsignee', 'master\ConsigneeController@saveConsignee');
            $router->post('/ShowConsignee', 'master\ConsigneeController@index');
            // -- file
            $router->post('/getDocument', 'master\CustomerController@getDocument');
            $router->post('/storeDocument', 'master\CustomerController@storeDocument');
        });

        $router->group(['prefix' => 'purchase'], function ($router) {
            $router->group(['prefix' => 'purchaseRequest'], function ($router) {
                $router->post('/getPR', 'purchasing\PurchaseRequestAprv@getPR');
                $router->post('/getDetailPR', 'purchasing\PurchaseRequestAprv@getDetailPR');
                $router->post('/approvePR1', 'purchasing\PurchaseRequestAprv@approvePR1');
                $router->post('/approvePR2', 'purchasing\PurchaseRequestAprv@approvePR2');
                $router->post('/unApprovePR1', 'purchasing\PurchaseRequestAprv@unApprovePR1');
                $router->post('/unApprovePR2', 'purchasing\PurchaseRequestAprv@unApprovePR2');
                $router->post('/show', 'purchasing\PurchaseRequestDetailController@index');
            });
            $router->group(['prefix' => 'purchaseOrder'], function ($router) {
                $router->post('/getPO', 'purchasing\PurchaseRequestAprv@getPO');
                $router->post('/getDetailPO', 'purchasing\PurchaseRequestAprv@getDetailPO');
                $router->post('/approvePO1', 'purchasing\PurchaseRequestAprv@approvePO1');
                $router->post('/approvePO2', 'purchasing\PurchaseRequestAprv@approvePO2');
                $router->post('/unApprovePO1', 'purchasing\PurchaseRequestAprv@unApprovePO1');
                $router->post('/unApprovePO2', 'purchasing\PurchaseRequestAprv@unApprovePO2');

                $router->group(['prefix' => 'comparison_price'], function ($router) {
                    $router->post('/', 'purchasing\ComparisonPriceController@index');
                    $router->post('/show', 'purchasing\ComparisonPriceController@show');
                    $router->post('/update', 'purchasing\ComparisonPriceController@update');
                    $router->post('/unapprove', 'purchasing\ComparisonPriceController@unapprove');
                    $router->post('/getSppoId', 'purchasing\ComparisonPriceController@getSppoId');
                    $router->post('/getPrHdr', 'purchasing\ComparisonPriceController@getPrHdr');
                    $router->post('/getPrDtl', 'purchasing\ComparisonPriceController@getPrDtl');
                    $router->post('/getSppoNum', 'purchasing\ComparisonPriceController@getSppoNum');
                    $router->post('/getLastPrice', 'purchasing\ComparisonPriceController@getLastPrice');
                    $router->post('/getPayTermId', 'purchasing\ComparisonPriceController@getPayTermId');
                });
            });
            $router->group(['prefix' => 'purchaseReceive'], function ($router) {
                $router->post('/getPurcRcv', 'purchasing\PurchaseReceiveController@getPurcRcv');
                $router->post('/getPurcRcvDtl', 'purchasing\PurchaseReceiveController@getPurcRcvDtl');
                $router->post('/getPOID', 'purchasing\PurchaseReceiveController@getPOID');
                $router->post('/storeHdr', 'purchasing\PurchaseReceiveController@storePurcRcvHdr');
                $router->post('/storeDtl', 'purchasing\PurchaseReceiveController@storePurcRcvDtl');
                $router->post('/destroy', 'purchasing\PurchaseReceiveController@destroypurcrcv');
                /*revisi jurnal rcv dan inv */
                $router->post('/rejournal_rcv', 'purchasing\PurchaseReceiveController@jurnalulangrcv');
                $router->post('/rejournal_inv', 'purchasing\PurchaseReceiveController@jurnalulanginv');
            });

            $router->group(['prefix' => 'article'], function ($router) {
                $router->post('/getCategory', 'master\ArticleController@getCategory');
                $router->post('/getUOM', 'master\ArticleController@getUOM');
                $router->post('/getArticle', 'master\ArticleController@getArticle');
                $router->post('/saveArticle', 'master\ArticleController@saveArticle');
                $router->post('/deleteArticle', 'master\ArticleController@deleteArticle');
                $router->post('/activateArticle', 'master\ArticleController@activateArticle');
            });
        });

        $router->group(['prefix' => 'rab'], function ($router) {
            $router->post('/', 'RabController@index');
            $router->post('/store', 'RabController@store');
            $router->post('/show', 'RabController@show');
            $router->post('/showArticleUsage', 'RabController@showArticleUsage');
            $router->post('/showImage', 'RabController@showImage');
            $router->post('/deleteImage', 'RabController@deleteImage');
            $router->post('/update', 'RabController@update');
            $router->post('/destroy', 'RabController@destroy');
            $router->post('/approve', 'RabController@approve');
            $router->post('/getArticleUsageDtl', 'RabController@getArticleUsageDtl');
            $router->post('/getJasaUsageDtl', 'RabController@getJasaUsageDtl');
            $router->post('/getJasaUsageStepDtl', 'RabController@getJasaUsageStepDtl');
            $router->post('/saveArticleUsageDtl', 'RabController@saveArticleUsageDtl');
            $router->post('/saveJasaUsageDtl', 'RabController@saveJasaUsageDtl');
            $router->post('/deleteJasaUsageDtl', 'RabController@deleteJasaUsageDtl');
            $router->post('/getSumTotalJasaUsageStepDtl', 'RabController@getSumTotalJasaUsageStepDtl');
            $router->post('/saveJasaUsageStepDtl', 'RabController@saveJasaUsageStepDtl');
            $router->post('/deleteJasaUsageStepDtl', 'RabController@deleteJasaUsageStepDtl');
        });

        $router->group(['prefix' => 'sales'], function ($router) {
            $router->post('/getSales', 'master\masterController@getSales');
            $router->post('/getKaCab', 'master\masterController@getKaCab');
            $router->post('/updateSales', 'master\masterController@updateSales');
            $router->post('/storeSales', 'master\masterController@storeSales');
            $router->post('/deleteSales', 'master\masterController@deleteSales');
            $router->post('/activateSales', 'master\masterController@activateSales');
            $router->post('/getPayTerm', 'master\masterController@getPayTerm');
            $router->post('/getIncludePrice', 'master\masterController@getIncludePrice');
            $router->post('/getEmployeeForSales', 'master\masterController@getEmployeeForSales');
            $router->post('/getSalesArea', 'master\masterController@getSalesArea');
        });

        $router->group(['prefix' => 'telemarketing'], function ($router) {
            $router->post('/getTelemarketing', 'sales\TelemarketingController@getTelemarketing');
            $router->post('/saveTelemarketingWithoutCreateCustomer', 'sales\TelemarketingController@saveTelemarketingWithoutCreateCustomer');
            $router->post('/saveTelemarketingWithCreateCustomer', 'sales\TelemarketingController@saveTelemarketingWithCreateCustomer');
            $router->post('/deactivate', 'sales\TelemarketingController@deactivateTelemarketing');
            $router->post('/activate', 'sales\TelemarketingController@activateTelemarketing');
            $router->post('/savefeedback', 'sales\TelemarketingController@saveFeedbackTelemarketing');
        });

        $router->group(['prefix' => 'whouse'], function ($router) {
            $router->post('/', 'master\WarehouseController@index');
            $router->post('/bin', 'master\WarehouseBinController@index');
            $router->post('/wh_bal', 'warehouse\WarehouseController@index');
            $router->post('/wh_bal/show', 'warehouse\WarehouseController@show');
            $router->post('/storeHeader', 'warehouse\WarehouseController@storeHeader');
            $router->post('/storeHeaderIssue', 'warehouse\WarehouseController@storeHeaderIssue');
            $router->post('/store', 'warehouse\WarehouseController@store');
            $router->post('/destroy', 'warehouse\WarehouseController@destroy');
            $router->post('/destroyDropShipper', 'warehouse\WarehouseController@destroyDropShipper');

            $router->post('/period', 'master\WarehouseController@getCurrentPeriod');
            $router->post('/listTransaction', 'warehouse\WarehouseController@listDetailTransaction');
            $router->post('/listHeaderTransaction', 'warehouse\WarehouseController@listTransaction');

            $router->post('/listTransactionWarehouse', 'warehouse\ListTransactionWarehouseController@index');

            // Route::post('/listTransactionWarehouse', [ListTransactionWarehouseController::class, 'index']);

            $router->post('/stockQuery', 'warehouse\StockQueryController@stockQuery');
            $router->post('/eksporStockQuery', 'warehouse\StockQueryController@eksporStockQuery');
            $router->post('/getListCategory', 'warehouse\StockQueryController@getListCategory');
            $router->post('/getListCommodity', 'warehouse\StockQueryController@getListCommodity');
            $router->post('/getListArticle', 'warehouse\StockQueryController@getListArticle');

            $router->group(['prefix' => 'transfer_whouse'], function ($router) {
                $router->post('/', 'warehouse\TransferWarehouseController@index');
                $router->post('/getPeriod', 'warehouse\TransferWarehouseController@getPeriod');
                $router->post('/wh_bal', 'warehouse\TransferWarehouseController@wh_bal');
                $router->post('/getDelivery', 'warehouse\TransferWarehouseController@getDelivery');
                $router->post('/storeHeader', 'warehouse\TransferWarehouseController@storeHeader');
                $router->post('/store', 'warehouse\TransferWarehouseController@store');
                $router->post('/destroy', 'warehouse\TransferWarehouseController@destroy');
            });

            $router->group(['prefix' => 'closing_daily'], function ($router) {
                $router->post('/', 'warehouse\ClosingDailyController@index');
                $router->post('/show', 'warehouse\ClosingDailyController@show');
                $router->post('/check_transaction', 'warehouse\ClosingDailyController@check_transaction');
                $router->post('/check_balance', 'warehouse\ClosingDailyController@check_balance');
                $router->post('/calculate', 'warehouse\ClosingDailyController@calculate');
                $router->post('/closing', 'warehouse\ClosingDailyController@closing');
                $router->post('/fetchData', 'warehouse\ClosingDailyController@getDataClosing');
                $router->post('/period_daily', 'warehouse\ClosingDailyController@period_daily');
            });

            $router->group(['prefix' => 'reprocess'], function ($router) {
                $router->post('/', 'warehouse\ReprocessController@index');
                $router->post('/show', 'warehouse\ReprocessController@show');
                $router->post('/show2', 'warehouse\ReprocessController@show2');
                $router->post('/store', 'warehouse\ReprocessController@store');
                $router->post('/getArticle', 'warehouse\ReprocessController@getArticle');
                $router->post('/getRegion', 'warehouse\ReprocessController@getRegion');
                $router->post('/getSubregion', 'warehouse\ReprocessController@getSubregion');
                $router->post('/getOffice', 'warehouse\ReprocessController@getOffice');
            });
        });

        $router->group(['prefix' => 'warehouse-issue'], function ($router) {
            $router->post('/', 'warehouse\WarehouseIssueController@index');
            $router->post('/getData', 'warehouse\WarehouseIssueController@getData');
            $router->post('/submitData', 'warehouse\WarehouseIssueController@submitData');
            $router->post('/getBinID', 'warehouse\WarehouseIssueController@getBinID');
            $router->post('/getWHID', 'warehouse\WarehouseIssueController@getWHID');
            $router->post('/getListIssue', 'warehouse\WarehouseIssueController@getListIssue');
            $router->post('/deleteIssue', 'warehouse\WarehouseIssueController@deleteIssue');
            $router->post('/getListIssueToday', 'warehouse\WarehouseIssueController@getListIssueToday');
            $router->post('/getListIssueTodayDetail', 'warehouse\WarehouseIssueController@getListIssueTodayDetail');
        });

        $router->group(['prefix' => 'sales_order'], function ($router) {
            $router->group(['prefix' => 'cash_register'], function ($router) {
                $router->post('/getNoOrder', 'sales_order\ReportSalesController@getNoOrder');
            });
            $router->group(['prefix' => 'report'], function ($router) {
                $router->post('/showReportOI', 'sales_order\ReportSalesController@showReportOI');
                $router->post('/showReportMonthly', 'sales_order\ReportSalesController@showReportMonthly');
                $router->post('/showReportDaily', 'sales_order\ReportSalesController@showReportDaily');
                $router->post('/showDetailReportOI', 'sales_order\ReportSalesController@showDetailReportOI');
                $router->post('/showDetailReportMonthly', 'sales_order\ReportSalesController@showDetailReportMonthly');
                $router->post('/showDetailReportDaily', 'sales_order\ReportSalesController@showDetailReportDaily');
                $router->post('/exportCSVOrderReport', 'sales_order\ReportSalesController@exportCSVOrderReport');
                $router->post('/exportExcelOrderReportPerInvoice', 'sales_order\ReportSalesController@exportExcelOrderReportPerInvoice');

                $router->post('/showReturnReportOI', 'sales_order\ReportSalesController@showReturnReportOI');
                $router->post('/showReturnReportMonthly', 'sales_order\ReportSalesController@showReturnReportMonthly');
                $router->post('/showReturnReportDaily', 'sales_order\ReportSalesController@showReturnReportDaily');
                $router->post('/showReturnDetailReportOI', 'sales_order\ReportSalesController@showReturnDetailReportOI');
                $router->post('/showReturnDetailReportMonthly', 'sales_order\ReportSalesController@showReturnDetailReportMonthly');
                $router->post('/showReturnDetailReportDaily', 'sales_order\ReportSalesController@showReturnDetailReportDaily');
                $router->post('/exportCSVReturnReport', 'sales_order\ReportSalesController@exportCSVReturnReport');

                $router->post('/showCreditNote', 'sales_order\ReportSalesController@showCreditNote');
                $router->post('/getCustInList', 'sales_order\ReportSalesController@getCustInList');
                $router->post('/getDetailCreditNote', 'sales_order\ReportSalesController@getDetailCreditNote');
                $router->post('/exportCSVCreditNote', 'sales_order\ReportSalesController@exportCSVCreditNote');

                $router->post('/getPayTerm', 'sales_order\ReportSalesController@getPayTerm');

                $router->post('/getReportBalanceDaily', 'sales_order\ReportSalesController@getReportBalanceDaily');
                $router->post('/showDetailReportBalance', 'sales_order\ReportSalesController@showDetailReportBalance');
                $router->post('/storeCashDeposit', 'sales_order\ReportSalesController@storeCashDeposit');
                $router->post('/getReportBalance', 'sales_order\ReportSalesController@getReportBalance');

                $router->post('/getGenLedgerDetail', 'ReportGenLedgerDetail@getGenLedgerDetail');
                $router->post('/coa_mapping/getDistinctRep', 'COAMappingController@getDistinctRep');
                $router->post('/coa_mapping/getReport', 'COAMappingController@getReport');
            });

            $router->group(['prefix' => 'traffic_toko'], function ($router) {
                $router->post('/', 'sales_order\TrafficTokoController@index');
                $router->post('/store', 'sales_order\TrafficTokoController@store');
                $router->post('/update', 'sales_order\TrafficTokoController@update');
                $router->post('/destroy', 'sales_order\TrafficTokoController@destroy');
                $router->post('/getProject', 'sales_order\TrafficTokoController@getProject');
                $router->post('/updateCustHist', 'sales_order\TrafficTokoController@updateCustHist');                
            });
        });

        $router->group(['prefix' => 'office'], function ($router) {
            $router->post('/', 'master\OfficeController@index');
            $router->post('/create', 'master\OfficeController@saveOffice');
            $router->post('/update', 'master\OfficeController@updateOffice');
        });

        $router->group(['prefix' => 'sales-channel'], function ($router) {
            $router->post('/', 'master\SalesChannelController@index');
            $router->post('/create', 'master\SalesChannelController@saveChannel');
            $router->post('/update', 'master\SalesChannelController@updateChannel');
            $router->post('/switch-active', 'master\SalesChannelController@switchActive');
        });

        $router->group(['prefix' => 'customer_order'], function ($router) {
            $router->post('/', 'OrderEntryController@getData');
            $router->post('/getDetail', 'OrderEntryController@getDetail');
            $router->post('/getDetailAddOn', 'OrderEntryController@getDetailAddOn');
            $router->post('/getDetailPayTerm', 'OrderEntryController@getDetailPayTerm');
            $router->post('/savecustomerorder', 'OrderEntryController@savecustomerorder');
            $router->post('/savecustomerorderdetil', 'OrderEntryController@savecustomerorderdetil');
            $router->post('/saveCustomerOrderAddonUnit', 'OrderEntryController@saveCustomerOrderAddonUnit');
            $router->post('/saveCustomerOrderAddonUnitIncludedPrice', 'OrderEntryController@saveCustomerOrderAddonUnitIncludedPrice');
            $router->post('/saveCustomerOrderDetilPayterm', 'OrderEntryController@saveCustomerOrderDetilPayterm');
            $router->post('/updatePlafon', 'OrderEntryController@updatePlafon');
            $router->post('/getNoBooking', 'OrderEntryController@getNoBooking');
            $router->post('/cancelSPR', 'OrderEntryController@cancelSPR');
            $router->post('/getKPRdata', 'OrderEntryController@getKPRdata');
            $router->post('/getKPRdataNull', 'OrderEntryController@getKPRdataNull');
            $router->post('/getNoKwitansi', 'OrderEntryController@getNoKwitansi');
            $router->post('/export', 'OrderEntryController@getDataforExport');
        });

        $router->group(['prefix' => 'spk'], function ($router) {
            $router->post('/getEmployee', 'transaction\SuratPerintahKerjaController@getEmployee');
            $router->post('/getQCForm', 'transaction\SuratPerintahKerjaController@getQCForm');
            $router->post('/getQCItem', 'transaction\SuratPerintahKerjaController@getQCItem');
            $router->post('/getKontraktor', 'transaction\SuratPerintahKerjaController@getKontraktor');
            $router->post('/storeSPK', 'transaction\SuratPerintahKerjaController@storeSPK');
            $router->post('/updateSPK', 'transaction\SuratPerintahKerjaController@updateSPK');
            $router->post('/getListSPK', 'transaction\SuratPerintahKerjaController@getListSPK');
            $router->post('/getSpkAttachment', 'transaction\SuratPerintahKerjaController@getSpkAttachment');
            $router->post('/getApplConstantSPK', 'transaction\SuratPerintahKerjaController@getApplConstantSPK');
            $router->post('/cekApproveQC', 'transaction\SuratPerintahKerjaController@cekApproveQC');
            $router->post('/saveVendor', 'transaction\SuratPerintahKerjaController@saveVendor');
            $router->post('/getApplConstantSpkType', 'transaction\SuratPerintahKerjaController@getApplConstantSpkType');
        });

        $router->group(['prefix' => 'booking'], function ($router) {
            $router->post('/getListBooking', 'transaction\BookingController@getListBooking');
            $router->post('/storeBooking', 'transaction\BookingController@storeBooking');
        });

        $router->group(['prefix' => 'site_target'], function ($router) {
            $router->post('/getSiteTarget', 'master\masterController@getSiteTarget');
            $router->post('/updateUnitPriceSiteTarget', 'master\masterController@updateUnitPriceSiteTarget');
        });

        $router->group(['prefix' => 'kwitansi'], function ($router) {
            $router->post('/', 'kwitansi\KwitansiController@index');
            $router->post('/siteOnChange', 'kwitansi\KwitansiController@siteOnChange');
            $router->post('/clusterOnChange', 'kwitansi\KwitansiController@clusterOnChange');
            $router->post('/populateSPR', 'kwitansi\KwitansiController@populateSPR');
            $router->post('/searchData', 'kwitansi\KwitansiController@searchData');
            $router->post('/getCustomer', 'kwitansi\KwitansiController@getCustomer');
            $router->post('/getCustomerById', 'kwitansi\KwitansiController@getCustomerById');
            $router->post('/saveData', 'kwitansi\KwitansiController@saveData');
            $router->post('/deleteData', 'kwitansi\KwitansiController@deleteData');
            $router->post('/editData', 'kwitansi\KwitansiController@editData');
            $router->post('/getSPR', 'kwitansi\KwitansiController@getSPR');
        });

        $router->group(['prefix' => 'payment-progress'], function ($router) {
            $router->post('/', 'transaction\PaymentProgressController@index');
            $router->post('/getData', 'transaction\PaymentProgressController@getData');
            $router->post('/approveData', 'transaction\PaymentProgressController@approveData');
            $router->post('/approveDataLegal', 'transaction\PaymentProgressController@approveDataLegal');
            $router->post('/getApproveData', 'transaction\PaymentProgressController@getApproveData');
            $router->post('/getNoSpr', 'transaction\PaymentProgressController@getNoSpr');
            $router->post('/getDetailData', 'transaction\PaymentProgressController@getDetailData');
            $router->post('/getDetailDataLegal', 'transaction\PaymentProgressController@getDetailDataLegal');            
            $router->post('/deleteData', 'transaction\PaymentProgressController@deleteData');
            $router->post('/deleteAttachment', 'transaction\PaymentProgressController@deleteAttachment');
            $router->post('/updateChecklist', 'transaction\PaymentProgressController@updateChecklist');
            $router->post('/getChecklist', 'transaction\PaymentProgressController@getChecklist');
        });

        $router->group(['prefix' => 'book-approval'], function ($router) {
            $router->post('/', 'finance\BookApprovalController@index');
            $router->post('/getData', 'finance\BookApprovalController@getData');
            // $router->post('/approveData', 'finance\BookApprovalController@approveData');
        });

        $router->group(['prefix' => 'spb'], function ($router) {
            $router->post('/', 'transaction\SuratPerintahBangunController@index');
            $router->post('/getData', 'transaction\SuratPerintahBangunController@getData');
            $router->post('/getAllSPBForSPK', 'transaction\SuratPerintahBangunController@getAllSPBForSPK');
            $router->post('/getAllSPBForSPKTambahan', 'transaction\SuratPerintahBangunController@getAllSPBForSPKTambahan');
            $router->post('/getAllSPBForSPKnoWhere', 'transaction\SuratPerintahBangunController@getAllSPBForSPKnoWhere');
            $router->post('/storeSPB', 'transaction\SuratPerintahBangunController@storeSPB');
            $router->post('/updateSPB', 'transaction\SuratPerintahBangunController@updateSPB');
            $router->post('/getSpbAttachment', 'transaction\SuratPerintahBangunController@getSpbAttachment');
        });

        $router->group(['prefix' => 'checklist'], function ($router) {
            $router->post('/aprvCheckList', 'sales_order\CheckListController@aprvCheckList');
            $router->post('/updateRemarkCheckList', 'sales_order\CheckListController@updateRemarkCheckList');
            $router->post('/getCheckListHdr', 'sales_order\CheckListController@getCheckListHdr');
            $router->post('/getCheckListDtl', 'sales_order\CheckListController@getQcTrans');
            $router->post('/getCheckListHouse', 'sales_order\CheckListController@getCheckListHouse');
            $router->post('/getCheckListVendor', 'sales_order\CheckListController@getCheckListVendor');
            $router->post('/getAprvDtl', 'sales_order\CheckListController@getAprvDtl');
            $router->post('/updateAprvCheckList', 'sales_order\CheckListController@updateAprvCheckList');
            $router->post('/cancelAprvCheckList', 'sales_order\CheckListController@cancelAprvCheckList');
        });

        $router->group(['prefix' => 'cortex'], function ($router) {
            $router->post('/getListFaktur', 'finance\CortexController@getListFakturCortex');
            $router->post('/getListFakturCortexDtl', 'finance\CortexController@getListFakturCortexDtl');
        });

        $router->group(['prefix' => 'deposit'], function ($router) {
            $router->post('/getListBankNote', 'finance\DepositController@getListBankNote');
            $router->post('/getListDeposit', 'finance\DepositController@getListDeposit');
            $router->post('/getListInvoice', 'finance\DepositController@getListInvoice');
            $router->post('/saveDeposit', 'finance\DepositController@saveDeposit');
            $router->post('/deleteDeposit', 'finance\DepositController@deleteDeposit');
            $router->post('/getListPurchDeposit', 'finance\DepositController@getListPurchDeposit');
            $router->post('/getListPInvoice', 'finance\DepositController@getListPInvoice');
            $router->post('/savePDeposit', 'finance\DepositController@savePDeposit');
            $router->post('/deletePDeposit', 'finance\DepositController@deletePDeposit');
        });

        $router->group(['prefix' => 'kasbon'], function ($router) {
            $router->post('/getKasbon', 'finance\KasbonAprvController@getKasbon');
            $router->post('/getKasbonDetail', 'finance\KasbonAprvController@getKasbonDetail');
            $router->post('/aprv1Kasbon', 'finance\KasbonAprvController@aprv1Kasbon');
            $router->post('/aprv2Kasbon', 'finance\KasbonAprvController@aprv2Kasbon');

            $router->post('/getKasbonSPK', 'finance\KasbonController@getKasbonSPK');
            $router->post('/storeKasbon', 'finance\KasbonController@storeKasbon');
            $router->post('/getKasbonVendor', 'finance\KasbonController@getKasbonVendor');

            //$router->post('/genKBID', 'finance\KasbonController@genKBID');
            //$router->post('/getLastSeqKB', 'finance\KasbonController@getLastSeqKB');
            $router->post('/getListKBHdr', 'finance\KasbonController@getListKBHdr');
            $router->post('/getListKBDtl', 'finance\KasbonController@getListKBDtl');
            $router->post('/saveKasBon', 'finance\KasbonController@saveKasBon');
            $router->post('/deleteKasBon', 'finance\KasbonController@deleteKasBon');
            $router->post('/deleteKasBonMat', 'finance\KasbonController@deleteKasBonMat');
            $router->post('/saveKasBonPengembalian', 'finance\KasbonController@saveKasBonPengembalian');
            $router->post('/deleteKasBonPengembalian', 'finance\KasbonController@deleteKasBonPengembalian');

            $router->post('/getKasbonVendorAprv', 'finance\KasbonAprvController@getKasbonVendorAprv');
            $router->post('/getKasbonMobile', 'finance\KasbonAprvController@getKasbonMobile');
            $router->post('/aprvKasbon', 'finance\KasbonAprvController@aprvKasbon');
            $router->post('/rjctKasbon', 'finance\KasbonAprvController@rjctKasbon');

            $router->post('/SaveOtherPaymentBreakdown', 'finance\KasbonController@SaveOtherPaymentBreakdown');
            $router->post('/GetOtherPaymentBreakdown', 'finance\KasbonController@GetOtherPaymentBreakdown');
            $router->post('/DeleteOtherPaymentBreakdown', 'finance\KasbonController@DeleteOtherPaymentBreakdown');
        });

        $router->group(['prefix' => 'closing'], function ($router) {
            $router->post('/', 'transaction\ClosingWeeklyController@index');
            $router->post('/closed', 'transaction\ClosingWeeklyController@indexClosed');
            $router->post('/show', 'transaction\ClosingWeeklyController@show');
            $router->post('/store', 'transaction\ClosingWeeklyController@store');
            $router->post('/unclosing', 'transaction\ClosingWeeklyController@unclosing');
        });

        $router->group(['prefix' => 'cair-dana-bank'], function ($router) {
            $router->post('/getData', 'finance\CairDanaBankController@getData');
            $router->post('/getDocData', 'finance\CairDanaBankController@getDocData');
            $router->post('/getRekening', 'finance\CairDanaBankController@getRekening');
            $router->post('/saveLogPrint', 'finance\CairDanaBankController@saveLogPrint');
            $router->post('/getDetilPencairan', 'finance\CairDanaBankController@getDetilPencairan');
            $router->post('/getNilaiPencairan', 'finance\CairDanaBankController@getNilaiPencairan');
            $router->post('/saveCDB_KPR', 'finance\CairDanaBankController@saveCDB_KPR');
            $router->post('/getLastnoUrut', 'finance\CairDanaBankController@getLastnoUrut');
        });

        $router->group(['prefix' => 'opname_pekerjaan'], function ($router) {
            $router->post('/', 'transaction\OpnamePekerjaanController@index');
            $router->post('/distinct', 'transaction\OpnamePekerjaanController@indexDistinct');
            $router->post('/showTransaction', 'transaction\OpnamePekerjaanController@showTransaction');
            $router->post('/store', 'transaction\OpnamePekerjaanController@store');
            $router->post('/closeSpk', 'transaction\OpnamePekerjaanController@closeSpk');
            $router->post('/show', 'transaction\OpnamePekerjaanController@show');
            $router->post('/destroy', 'transaction\OpnamePekerjaanController@destroy');
            $router->post('/update', 'transaction\OpnamePekerjaanController@update');
            $router->post('/confirm', 'transaction\ConfirmOpnamePekerjaanController@store');
            $router->post('/approve', 'transaction\ConfirmOpnamePekerjaanController@approve');
            $router->post('/sumKasbon', 'transaction\OpnamePekerjaanController@sumKasbon');
            $router->post('/sumKasbonMaterial', 'transaction\OpnamePekerjaanController@sumKasbonMaterial');
            $router->post('/sumKasbonOther', 'transaction\OpnamePekerjaanController@sumKasbonOther');

            $router->post('/showKasbon', 'transaction\OpnamePekerjaanController@showKasbon');
            $router->post('/showKasbonMaterial', 'transaction\OpnamePekerjaanController@showKasbonMaterial');
            $router->post('/showKasbonOther', 'transaction\OpnamePekerjaanController@showKasbonOther');

            $router->post('/adjust', 'transaction\OpnamePekerjaanController@adjust');
            $router->post('/reJournal', 'transaction\ConfirmOpnamePekerjaanController@reJournal');
            $router->post('/reUpdateInv', 'transaction\ConfirmOpnamePekerjaanController@reUpdateInv');


            $router->group(['prefix' => 'kasbon'], function ($router) {
                $router->post('/show', 'transaction\OpnamePekerjaanKasbonController@show');
                $router->post('/store', 'transaction\OpnamePekerjaanKasbonController@store');
            });
        });

        $router->group(['prefix' => 'aprv-spk'], function ($router) {
            $router->post('/getListSPK', 'transaction\ApprovalSPKController@getListSPK');
            $router->post('/getListSPKMobile', 'transaction\ApprovalSPKController@getListSPKMobile');
            $router->post('/approvalSPK', 'transaction\ApprovalSPKController@approvalSPK');
            $router->post('/rejectApprove', 'transaction\ApprovalSPKController@rejectApprove');
            $router->post('/getStatusSPK', 'transaction\ApprovalSPKController@getStatusSPK');
        });

        $router->group(['prefix' => 'sales'], function ($router) {
            $router->group(['prefix' => 'CustomerVisit'], function ($router) {
                $router->post('/', 'sales\CustomerVisitController@index');
                $router->post('/submitData', 'sales\CustomerVisitController@submitData');
                $router->post('/getCustomerById', 'sales\CustomerVisitController@getCustomerById');
                $router->post('/getCustomer', 'sales\CustomerVisitController@getCustomer');
                $router->post('/provinceOnChange', 'sales\CustomerVisitController@provinceOnChange');
                $router->post('/cityOnChange', 'sales\CustomerVisitController@cityOnChange');
                $router->post('/districtOnChange', 'sales\CustomerVisitController@districtOnChange');
                $router->post('/getAddress', 'sales\CustomerVisitController@getAddress');
            });
            $router->group(['prefix' => 'VisitReport'], function ($router) {
                $router->post('/', 'sales\VisitReportController@index');
                $router->post('/searchData', 'sales\CustomerVisitController@searchData');
                $router->post('/getCustomer', 'sales\CustomerVisitController@getCustomer');
                $router->post('/getOffice', 'sales\CustomerVisitController@getOffice');
                $router->post('/getSales', 'sales\CustomerVisitController@getSales');
                $router->post('/getDetailImages', 'sales\CustomerVisitController@getDetailImages');
                $router->post('/addDetailImages', 'sales\CustomerVisitController@addDetailImages');
            });

            $router->group(['prefix' => 'revisiSPR'], function ($router) {
              $router->post('/', 'sales\RevisiSPRController@index');
              $router->post('/getCustomer', 'sales\RevisiSPRController@getCustomer');
              $router->post('/getCustOrder', 'sales\RevisiSPRController@getCustOrder');
              $router->post('/getFormOrder', 'sales\RevisiSPRController@getFormOrder');
              $router->post('/storeRevisi', 'sales\RevisiSPRController@storeRevisi');
              $router->post('/activateRevisiSPR', 'sales\RevisiSPRController@activateRevisiSPR');
            });
        });

        //-- route
        $router->group(['prefix' => 'OtherDeposit'], function ($router) {
            $router->post('/getDataMasterFinance', 'finance\OtherDepositController@getDataMasterFinance');
            $router->post('/getDataById', 'finance\OtherDepositController@getDataById');
            $router->post('/getBankStatement', 'finance\OtherDepositController@getBankStatement');
            $router->post('/storeDeposit', 'finance\OtherDepositController@storeDeposit');
            $router->post('/getDataTransaction', 'finance\OtherDepositController@getDataTransaction');
            $router->post('/updateDeposit', 'finance\OtherDepositController@updateDeposit');
            $router->post('/destroyDeposit', 'finance\OtherDepositController@destroyDeposit');
            $router->post('/ifAcctClosed', 'finance\OtherDepositController@ifAcctClosed');
        });
        $router->group(['prefix' => 'OtherJournal'], function ($router) {
            $router->post('/getDataById', 'finance\OtherJournalController@getDataById');
            $router->post('/storeOtherJurnal', 'finance\OtherJournalController@storeOtherJurnal');
            $router->post('/getDataTransaction', 'finance\OtherJournalController@getDataTransaction');
            $router->post('/updateOtherJurnal', 'finance\OtherJournalController@updateOtherJurnal');
            $router->post('/destroyOtherJurnal', 'finance\OtherJournalController@destroyOtherJurnal');
            $router->post('/getBankStatement', 'finance\OtherJournalController@getBankStatement');
        });
        $router->group(['prefix' => 'Accounting'], function ($router) {
            $router->post('/ShowJurnal', 'finance\AccountingController@ShowJurnal');
            $router->post('/checkMonthClosing', 'finance\AccountingController@check_acctmonthlyclosing');
            $router->post('/closeMonthClosing', 'finance\AccountingController@close_acctmonthlyclosing');
            $router->post('/finalcloseMonthClosing', 'finance\AccountingController@final_close_acctmonthlyclosing');
            $router->post('/updateMonthClosing', 'finance\AccountingController@update_acctmonthlyclosing');
            $router->post('/cekClosing', 'finance\AccountingController@cekClosing');
        });
        $router->group(['prefix' => 'BankStatement'], function ($router) {
            $router->post('/uploadFile', 'finance\BankStatementController@uploadFile');
            $router->post('/getBankStatement', 'finance\BankStatementController@getBankStatement');
            $router->post('/getDataMaster', 'finance\BankStatementController@getDataMaster');
            $router->post('/StoreBank', 'finance\BankStatementController@StoreBank');
            $router->post('/SplitBank', 'finance\BankStatementController@SplitBank');
            $router->post('/displaySplitBank', 'finance\BankStatementController@displaySplitBank');
            $router->post('/GetListKasbon', 'finance\BankStatementController@GetListKasbon');
        });

        $router->group(['prefix' => 'PurchasePayment'], function ($router) {
            $router->post('/GetInvByVendor', 'finance\PurchasePaymentController@GetInvByVendor');
            $router->post('/GetPurcPayByIdSplit', 'finance\PurchasePaymentController@GetPurcPayByIdSplit');
            $router->post('/GetPurcPaymentList', 'finance\PurchasePaymentController@GetPurcPaymentList');
            $router->post('/GetPurcPayById', 'finance\PurchasePaymentController@GetPurcPayById');
            $router->post('/GetListDepositByVendor', 'finance\PurchasePaymentController@GetListDepositByVendor');
            $router->post('/GetListGiroByVendor', 'finance\PurchasePaymentController@GetListGiroByVendor');
            $router->post('/StorePurcPayment', 'finance\PurchasePaymentController@StorePurcPayment');
            $router->post('/destroyPurcPayment', 'finance\PurchasePaymentController@destroyPurcPayment');
            $router->post('/getBankStatement', 'finance\PurchasePaymentController@getBankStatement');
            $router->post('/updateHeaderPurcPayment', 'finance\PurchasePaymentController@updateHeaderPurcPayment');
            $router->post('/rejournal_purc_pay', 'finance\PurchasePaymentController@jurnalulangpurcpay');
        });
        $router->group(['prefix' => 'CustomerPayment'], function ($router) {
            $router->post('/GetInvByCustomer', 'finance\CustomerPaymentController@GetInvByCustomer');
            $router->post('/GetCustPayByIdSplit', 'finance\CustomerPaymentController@GetCustPayByIdSplit');
            $router->post('/GetCustPaymentList', 'finance\CustomerPaymentController@GetCustPaymentList');
            $router->post('/GetCustPayById', 'finance\CustomerPaymentController@GetCustPayById');
            $router->post('/GetListDepositByCustomer', 'finance\CustomerPaymentController@GetListDepositByCustomer');
            $router->post('/GetListGiroByCustomer', 'finance\CustomerPaymentController@GetListGiroByCustomer');
            $router->post('/StoreCustPayment', 'finance\CustomerPaymentController@StoreCustPayment');
            $router->post('/destroyCustPayment', 'finance\CustomerPaymentController@destroyCustPayment');
            $router->post('/getBankStatement', 'finance\CustomerPaymentController@getBankStatement');
            $router->post('/revisiJurnal', 'finance\CustomerPaymentController@revisiJurnal');
            $router->post('/GetListKwitansiByCustomer', 'finance\CustomerPaymentController@GetListKwitansiByCustomer');
        });

        $router->group(['prefix' => 'JournalCheking'], function ($router) {
            $router->post('/GetDataJournal', 'finance\JournalCheckingController@GetDataJournal');
            $router->post('/DeleteJurnalNoTransaction', 'finance\JournalCheckingController@DeleteJurnalNoTransaction');
            $router->post('/UpdateJournalYear', 'finance\JournalCheckingController@UpdateJournalYear');
            $router->post('/createJournal', 'finance\JournalCheckingController@createJournal');
        });

        $router->group(['prefix' => 'PurchaseRequest'], function ($router) {
            $router->get('/', 'purchasing\PurchaseRequestController@index');
            $router->post('/getCustomer', 'purchasing\PurchaseRequestController@getCustomer');
            $router->post('/getPrId', 'purchasing\PurchaseRequestController@getPrId');
            $router->post('/getDetailPrList', 'purchasing\PurchaseRequestController@getDetailPrList');
            $router->post('/getPo', 'purchasing\PurchaseRequestController@getPo');
            $router->post('/getNumPrItem', 'purchasing\PurchaseRequestController@getNumPrItem');
            $router->post('/getGenPrId', 'purchasing\PurchaseRequestController@getGenPrId');
            $router->post('/getPrItem', 'purchasing\PurchaseRequestController@getPrItem');
            $router->post('/getMaster', 'purchasing\PurchaseRequestController@getMaster');
            $router->post('/getEnvUser', 'purchasing\PurchaseRequestController@getEnvUser');
            $router->post('/getPrItem', 'purchasing\PurchaseRequestController@getPrItem');
            $router->post('/getVendorFpi', 'purchasing\PurchaseRequestController@getVendorFpi');
            $router->post('/InsertPr', 'purchasing\PurchaseRequestController@InsertPr');
            $router->post('/deletePr', 'purchasing\PurchaseRequestController@deletePr');
            $router->post('/complitePr', 'purchasing\PurchaseRequestController@complitePr');
            $router->post('/SendWaPr', 'purchasing\PurchaseRequestController@SendWaPr');
            $router->post('/getPrHdrList', 'purchasing\PurchaseRequestController@getPrHdrList');
            $router->post('/departmentOnChange', 'purchasing\PurchaseRequestController@departmentOnChange');
            $router->post('/printDoc', 'purchasing\PurchaseRequestController@printDoc');
            $router->post('/siteOnChange', 'purchasing\PurchaseRequestController@siteOnChange');
            $router->post('/clusterOnChange', 'purchasing\PurchaseRequestController@clusterOnChange');
            $router->post('/closePurchaseRequest', 'purchasing\PurchaseRequestController@closePurchaseRequest');
        });

        $router->group(['prefix' => 'InvoiceNonPo'], function ($router) {
            $router->get('/', 'purchasing\InvoiceNonPoController@index');
            $router->post('/getInvoiceList', 'purchasing\InvoiceNonPoController@getInvoiceList');
            $router->post('/getMaster', 'purchasing\InvoiceNonPoController@getMaster');
            $router->post('/getNumInvItem', 'purchasing\InvoiceNonPoController@getNumInvItem');
            $router->post('/getAcNumDr', 'purchasing\InvoiceNonPoController@getAcNumDr');
            $router->post('/getSaveInv', 'purchasing\InvoiceNonPoController@getSaveInv');
            $router->post('/getDataInv', 'purchasing\InvoiceNonPoController@getDataInv');
            $router->post('/getInvoiceDetail', 'purchasing\InvoiceNonPoController@getInvoiceDetail');
            $router->post('/getDeleteInv', 'purchasing\InvoiceNonPoController@getDeleteInv');
            $router->post('/getListHeader', 'purchasing\InvoiceNonPoController@getListHeader');
            $router->post('/getListDtlHeader', 'purchasing\InvoiceNonPoController@getListDtlHeader');
        });

        $router->group(['prefix' => 'PurchaseRetur'], function ($router) {
            $router->get('/', 'purchasing\PurchaseReturController@index');
            $router->post('/getMaster', 'purchasing\PurchaseReturController@getMaster');
            $router->post('/getReturHdr', 'purchasing\PurchaseReturController@getReturHdr');
            $router->post('/getReturDtl', 'purchasing\PurchaseReturController@getReturDtl');
            $router->post('/getListArticle', 'purchasing\PurchaseReturController@getListArticle');
            $router->post('/CategoryOnChange', 'purchasing\PurchaseReturController@CategoryOnChange');
            $router->post('/CommodityOnChange', 'purchasing\PurchaseReturController@CommodityOnChange');
            $router->post('/SaveReturn', 'purchasing\PurchaseReturController@SaveReturn');
            $router->post('/DeleteReturn', 'purchasing\PurchaseReturController@DeleteReturn');
            $router->post('/ConfirmReturn', 'purchasing\PurchaseReturController@ConfirmReturn');
            $router->post('/getRcvHeader', 'purchasing\PurchaseReturController@getRcvHeader');
        });


        $router->group(['prefix' => 'PurchaseOrderPO'], function ($router) {
            $router->get('/', 'purchasing\PurchaseOrderController@index');
            $router->post('/getMaster', 'purchasing\PurchaseOrderController@getMaster');
            $router->post('/getListPoHdr', 'purchasing\PurchaseOrderController@getListPoHdr');
            $router->post('/getListPoDtl', 'purchasing\PurchaseOrderController@getListPoDtl');
            $router->post('/getCategory', 'purchasing\PurchaseOrderController@getCategory');
            $router->post('/getCommodity', 'purchasing\PurchaseOrderController@getCommodity');
            $router->post('/getCommodityGroup', 'purchasing\PurchaseOrderController@getCommodityGroup');
            $router->post('/getPrHdr', 'purchasing\PurchaseOrderController@getPrHdr');
            $router->post('/getListPr', 'purchasing\PurchaseOrderController@getListPr');
            $router->post('/getGenPoId', 'purchasing\PurchaseOrderController@getGenPoId');
            $router->post('/getPoItem', 'purchasing\PurchaseOrderController@getPoItem');
            $router->post('/getSavePoItem', 'purchasing\PurchaseOrderController@getSavePoItem');
            $router->post('/getSavePoHdr', 'purchasing\PurchaseOrderController@getSavePoHdr');
            $router->post('/getGrandTotal', 'purchasing\PurchaseOrderController@getGrandTotal');
            $router->post('/getUpdatePr', 'purchasing\PurchaseOrderController@getUpdatePr');
            $router->post('/getRcvList', 'purchasing\PurchaseOrderController@getRcvList');
            $router->post('/storePo', 'purchasing\PurchaseOrderController@storePo');
            $router->post('/updatePo', 'purchasing\PurchaseOrderController@updatePo');
            $router->post('/getTaxValue', 'purchasing\PurchaseOrderController@getTaxValue');
            $router->post('/deletePo', 'purchasing\PurchaseOrderController@deletePo');
            $router->post('/rejectPOitem', 'purchasing\PurchaseOrderController@rejectPOitem');
        });

        $router->group(['prefix' => 'PurchaseReport'], function ($router) {
            $router->get('/', 'purchasing\PurchaseReportController@index');
            $router->post('/GetPurchaseHistory', 'purchasing\PurchaseReportController@GetPurchaseHistory');
            $router->post('/UpdateProgressPr', 'purchasing\PurchaseReportController@UpdateProgressPr');
            $router->post('/UpdateProgressPo', 'purchasing\PurchaseReportController@UpdateProgressPo');
            $router->post('/getMonitoring', 'purchasing\PurchaseReportController@getMonitoring');
        });

        $router->group(['prefix' => '/SettingPrice'], function () use ($router) {
            $router->post('/', 'finance\SettingPriceController@index');
            $router->post('/ListPrice', 'finance\SettingPriceController@ListPrice');
            $router->post('/PriceList', 'finance\SettingPriceController@PriceList');
            $router->post('/Region', 'finance\SettingPriceController@Region');
            $router->post('/GetPrice', 'finance\SettingPriceController@GetPrice');
            $router->post('/GetSave', 'finance\SettingPriceController@GetSave');
            $router->post('/batchSetPrice', 'finance\SettingPriceController@batchSetPrice');
            $router->post('/getCustomerType', 'finance\SettingPriceController@getCustomerType');
        });

        $router->group(['prefix' => 'DpPayRequest'], function ($router) {
            $router->get('/', 'purchasing\DpPayRequestController@index');
            $router->post('/GetPo', 'purchasing\DpPayRequestController@GetPo');
        });


        $router->group(['prefix' => 'Vendor'], function ($router) {
            $router->post('/getVendor', 'master\VendorController@getVendor');
        });

        $router->group(['prefix' => 'aprv-spb'], function ($router) {
            $router->post('/getListSPB', 'transaction\ApprovalSPBController@getListSPB');
            $router->post('/getListSPBMobile', 'transaction\ApprovalSPBController@getListSPBMobile');
            $router->post('/approvalSPB', 'transaction\ApprovalSPBController@approvalSPB');
            $router->post('/rejectApprove', 'transaction\ApprovalSPBController@rejectApprove');
            $router->post('/getStatusSPB', 'transaction\ApprovalSPBController@getStatusSPB');
        });

        $router->group(['prefix' => 'aprv-spr'], function ($router) {
            $router->post('/getListSPR', 'transaction\ApprovalSPRController@getListSPR');
            $router->post('/getListSPRMobile', 'transaction\ApprovalSPRController@getListSPRMobile');
            $router->post('/approvalSPR', 'transaction\ApprovalSPRController@approvalSPR');
            $router->post('/rejectApprove', 'transaction\ApprovalSPRController@rejectApprove');
            $router->post('/getStatusSPR', 'transaction\ApprovalSPRController@getStatusSPR');
        });

        $router->group(['prefix' => 'Finance'], function ($router) {
            $router->post('/mutasiKasBank', 'MutasiKasBankController@getDataMutasi');
            $router->post('/summaryMutasi', 'MutasiKasBankController@summaryMutasi');
            $router->post('/confirmKasbon', 'finance\KasBontoDebitNoteController@confirmKasbon');
            $router->post('/jurnalharian', 'finance\JurnalHarianController@getData');
            $router->post('/updateJurnal', 'finance\JurnalHarianController@updateJurnal');
            $router->post('/cekClosing', 'finance\JurnalHarianController@cekClosing');

            $router->group(['prefix' => 'coaMappingReport'], function ($router) {
                $router->post('/getData', 'finance\COAMappingReportController@getData');
                $router->post('/getDistinctRep', 'finance\COAMappingReportController@getDistinctRep');
            });
            $router->group(['prefix' => 'journal_by_source'], function () use ($router) {
                $router->post('/', 'finance\JurnalbySourceController@index');
            });
             $router->group(['prefix' => 'journal_by_ac_num'], function () use ($router) {
                $router->post('/', 'finance\JurnalbyAccountController@index');
                $router->post('/info', 'finance\JurnalbyAccountController@info');
            });
            $router->group(['prefix' => '/SalesFee'], function () use ($router) {
                $router->post('/', 'finance\SalesFeeController@index');
                $router->post('/getBrandFromArticle', 'finance\SalesFeeController@getBrandFromArticle');
                $router->post('/getChannel', 'finance\SalesFeeController@getChannel');
                $router->post('/getSales', 'finance\SalesFeeController@getSales');
                $router->post('/storeFee', 'finance\SalesFeeController@storeFee');
                $router->post('/storeCopyFeeSales', 'finance\SalesFeeController@storeCopyFeeSales');
                $router->post('/storeCopyAllFeeSales', 'finance\SalesFeeController@storeCopyAllFeeSales');                
                $router->post('/getOrderData', 'finance\SalesFeeController@getOrderData');                
                $router->post('/calculatefee', 'finance\SalesFeeController@calculatefee');                
                $router->post('/getdatafeesalesForClosing', 'finance\SalesFeeController@getdatafeesalesForClosing');
                $router->post('/closingfee', 'finance\SalesFeeController@closingfee');
                $router->post('/getdatafeesalesDoneClosing', 'finance\SalesFeeController@getdatafeesalesDoneClosing');
                $router->post('/unclosingfee', 'finance\SalesFeeController@unclosingfee');
                $router->post('/uncalculatefee', 'finance\SalesFeeController@uncalculatefee');
            });
        });

        $router->group(['prefix' => 'g_ledger_year'], function ($router) {
            $router->post('/neracaReport', 'finance\GLedgerYearlyController@neracaReport');
            $router->post('/getGenLedgerDetail', 'finance\GLedgerYearlyController@getGenLedgerDetail');
        });
        
        $router->group(['prefix' => '/SettingPrice'], function () use ($router) {
            $router->post('/', 'finance\SettingPriceController@index');
            $router->post('/ListPrice', 'finance\SettingPriceController@ListPrice');
            $router->post('/Region', 'finance\SettingPriceController@Region');
            $router->post('/GetPrice', 'finance\SettingPriceController@GetPrice');
            $router->post('/GetSave', 'finance\SettingPriceController@GetSave');
            $router->post('/GetArticle', 'finance\SettingPriceController@GetArticle');
            $router->post('/batchSetPrice', 'finance\SettingPriceController@batchSetPrice');
        });

        $router->group(['prefix' => 'prpo'], function ($router) {
            $router->post('/getList', 'purchasing\PurchaseAprvMobile@getList');
            $router->post('/approve', 'purchasing\PurchaseAprvMobile@approve');
            $router->post('/cancel', 'purchasing\PurchaseAprvMobile@cancel');
        });

        $router->group(['prefix' => 'operational-progress'], function ($router) {
            $router->post('/', 'transaction\OperationalProgressController@index');
            $router->post('/generateQcTrans', 'transaction\OperationalProgressController@generateQcTrans');
            $router->post('/storeProgress', 'transaction\OperationalProgressController@storeProgress');
            $router->post('/getData', 'transaction\OperationalProgressController@getData');
            $router->post('/approveData', 'transaction\OperationalProgressController@approveData');
            $router->post('/getApproveData', 'transaction\OperationalProgressController@getApproveData');
            $router->post('/getNoSpr', 'transaction\OperationalProgressController@getNoSpr');
            $router->post('/getNoSpr', 'transaction\OperationalProgressController@getNoSpr');
            $router->post('/getDetailData', 'transaction\OperationalProgressController@getDetailData');
            $router->post('/deleteData', 'transaction\OperationalProgressController@deleteData');
        });

        $router->group(['prefix' => 'finance/bank-note-history'], function ($router) {
            $router->post('/', 'finance\BankNoteHistoryController@index');
            $router->post('/searchData', 'finance\BankNoteHistoryController@searchData');
            $router->post('/getDetails', 'finance\BankNoteHistoryController@getDetails');
        });

        $router->group(['prefix' => 'payment-request'], function ($router) {
            $router->post('/initPaymentRequest', 'finance\PaymentRequestController@initPaymentRequest');
            $router->post('/getListInv', 'finance\PaymentRequestController@getListInv');
            $router->post('/getListInvPrint', 'finance\PaymentRequestController@getListInvPrint');
            $router->post('/getDetailListInv', 'finance\PaymentRequestController@getDetailListInv');
            $router->post('/getDokumenCetakInvoice', 'finance\PaymentRequestController@getDokumenCetakInvoice');
            $router->post('/kunciCetakInvoice', 'finance\PaymentRequestController@kunciCetakInvoice');
            $router->post('/historyCetakInvoice', 'finance\PaymentRequestController@historyCetakInvoice');
            $router->post('/hapusCetakInvoice', 'finance\PaymentRequestController@hapusCetakInvoice');
            $router->post('/approvePermit', 'finance\PaymentRequestController@approvePermit');
        });

        $router->group(['prefix' => 'report'], function ($router) {
            $router->post('/getGenLedgerDetail', 'ReportGenLedgerDetail@getGenLedgerDetail');
            $router->post('/getGenLedgerDetail_baru', 'ReportGenLedgerDetail@getGenLedgerDetail_baru');
            $router->post('/getListSPKByVendor', 'transaction\ApprovalSPKController@getListSPKByVendor');
        });

        $router->group(['prefix' => 'project'], function ($router) {
            $router->post('/getProjectData', 'finance\ProjectController@getProjectData');
            $router->post('/getProjectDataDtl', 'finance\ProjectController@getProjectDataDtl');
        });
        $router->group(['prefix' => 'SPKInformation'], function ($router) {
            $router->post('/', 'transaction\SPKInformationController@getSPKInformation');
            $router->post('/getKasbonbySPK', 'transaction\SPKInformationController@getKasbonbySPK');
        });
        $router->group(['prefix' => '/invoice'], function () use ($router) {
            $router->post('/', 'finance\InvoiceController@index');
            $router->post('/GetDataOrder', 'finance\InvoiceController@GetDataOrder');
            $router->post('/GetDataOrdertoInvoice', 'finance\InvoiceController@GetDataOrdertoInvoice');
            $router->post('/store', 'finance\InvoiceController@store');
            $router->post('/destroy', 'finance\InvoiceController@destroy');
            $router->post('/show', 'finance\InvoiceController@show');
            $router->post('/DisplayDetailInv', 'finance\InvoiceController@DisplayDetailInv');
            $router->post('/getPpnVal', 'finance\InvoiceController@getPpnVal');
            $router->post('/loadEnvVar', 'finance\InvoiceController@loadEnvVar');
            $router->post('/GetDataInvoicebySpr', 'finance\InvoiceController@GetDataInvoicebySpr');
            $router->post('/GetDataInvoicebyId', 'finance\InvoiceController@GetDataInvoicebyId');
        });

        $router->group(['prefix' => 'ceklist-kebersihan'], function ($router) {
            $router->post('/', 'ReportCeklistKebersihanController@index');
            $router->post('/show', 'ReportCeklistKebersihanController@show');
            $router->post('/complete', 'ReportCeklistKebersihanController@completeChecklist');
        });

        $router->group(['prefix' => '/project'], function () use ($router) {
            $router->group(['prefix' => '/project-register'], function () use ($router) {
                $router->get('/', 'project\ProjectRegisterController@index');
                $router->post('/regionChange', 'project\ProjectRegisterController@regionChange');
                $router->post('/officeChange', 'project\ProjectRegisterController@officeChange');
                $router->post('/submitData', 'project\ProjectRegisterController@submitData');
                $router->post('/getData', 'project\ProjectRegisterController@getData');
                $router->post('/searchData', 'project\ProjectRegisterController@searchData');
                $router->post('/searchDataFinance', 'project\ProjectRegisterController@searchDataFinance');
                $router->post('/inactive', 'project\ProjectRegisterController@inactiveproject');
                $router->post('/active', 'project\ProjectRegisterController@activeproject');
            });
        });

        $router->group(['prefix' => '/worksheet'], function () use ($router) {
            $router->post('/', 'WorksheetController@getData');
        });        
    });
});
