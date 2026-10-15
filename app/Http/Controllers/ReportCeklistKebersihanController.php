<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportCeklistKebersihanController extends Controller
{
    public function index(Request $request)
    {
        $sqlWhere = "1=1";
        $id_site = $request->id_site;
        $id_cluster = $request->id_cluster;
        $status = $request->status;

        if ($id_site) {
    
            $sqlWhere.= " and site.id_site = '$id_site'";
        }

        if ($id_cluster) {
    
            $sqlWhere.= " and site_cluster.id_cluster = '$id_cluster'";
        }

        if($status){
            if($status =='Y')
            {
                $sqlWhere.= " and qhdr.dt_finish != '1900-01-01 00:00:00.000'";
            }
            else if($status == 'N')
            {
                $sqlWhere.= " and qhdr.dt_finish = '1900-01-01 00:00:00.000'";
            }
        }

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
            SELECT
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                qhdr.doc_type,
                qhdr.doc_id AS spk_id,
                qtl.qc_trans_id,
                qhdr.dt_finish,
                'BELUM CLOSING' as stat_closing,
                'BELUM DIKONFIRM' as stat_opname
            FROM
                [dbo].[qc_trans_dtl] AS qtl
                INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                INNER JOIN site AS site ON house_unit.id_site = site.id_site
            WHERE $sqlWhere AND qhdr.doc_type = 'KK' 
            GROUP BY
                site.id_site,
                site.site_name,
                site_cluster.id_cluster,
                site_cluster.cluster_name,
                house_unit.id_house,
                house_unit.house_name,
                qhdr.doc_type,
                qhdr.doc_id,
                qhdr.dt_finish,
                qtl.qc_trans_id
            ORDER BY
                site.id_site,
                site_cluster.id_cluster,
                house_unit.id_house
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');

    }

    public function show(Request $request)
    {
        $sqlWhere = "1=1";
        $spk_id = trim($request->spk_id);
        $spk_type = trim($request->spk_type);
        $com_id = $request->com_id;

        $datas = DB::connection('sqlSrvBCKI')->select(DB::raw("
                    SELECT
                        site.id_site,
                        site.site_name + ' ' + site.site_address AS site_name,
                        site_cluster.id_cluster,
                        site_cluster.cluster_name,
                        house_unit.house_name,
                        qhdr.doc_type,
                        qhdr.doc_id AS spk_id,
                        qtl.qc_trans_id,
                        qtl.id_qc_item,
                        qc_item.qc_item,
	                    qc_item.qc_item_html,
                        qtl.stat,
                        qtl.dt_aprv,
                        qtl.aprv_by,
                        qtl.dt_aprv2,
                        qtl.aprv2_by,
                        qtl.dt_aprv3,
                        qtl.aprv3_by,
                        qtl.remark,
                        qtl.stat_closing,
                        qtl.stat_closing2,
                        qtl.stat_closing3,
                        qhdr.id_qc_form,
                        qhdr.id_house 
                    FROM
                        [dbo].[qc_trans_dtl] AS qtl
                        INNER JOIN qc_trans_hdr AS qhdr ON qhdr.qc_trans_id = qtl.qc_trans_id
                        INNER JOIN qc_item AS qc_item ON qc_item.id_qc_item = qtl.id_qc_item
                        INNER JOIN house_unit AS house_unit ON qhdr.id_house = house_unit.id_house
                        INNER JOIN site_cluster AS site_cluster ON house_unit.id_cluster = site_cluster.id_cluster
                        INNER JOIN site AS site ON house_unit.id_site = site.id_site
                    WHERE
                        qhdr.doc_type = 'KK' AND qhdr.doc_id = '$spk_id'
                    ORDER BY
                        qtl.qc_trans_id,
                        qtl.id_qc_item
        "));

        return $this->sendResponse($datas, 'Successfully get Delivery');
    }

    public function completeChecklist(Request $request){
        $spk_id = trim($request->spk_id);
        $doc_type = trim($request->doc_type);
        $qc_trans_id = trim($request->qc_trans_id);

        DB::connection('sqlSrvBCKI')
        ->table('qc_trans_hdr')
        ->where('doc_id', $spk_id)
        ->where('doc_type', $doc_type)
        ->where('qc_trans_id', $qc_trans_id)
        ->update(['dt_finish' => now('Asia/Jakarta')]);

        return $this->sendResponse('Success', 'Successfully complete checklist');
    }
}
