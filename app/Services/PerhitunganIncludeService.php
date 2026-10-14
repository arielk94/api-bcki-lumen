<?php

namespace App\Services;

use App\Traits\getFilterTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpParser\Node\Expr\Cast\Object_;

class PerhitunganIncludeService
{

    public function hitungHargaDasarTanpaSelisih($hargaInclude, $ppnRate, $pph23Rate, $pph21Rate)
    {

        // 1. Hitung harga dasar
        $hargaDasar = round($hargaInclude / (1 + $ppnRate - $pph23Rate - $pph21Rate));

        // 2. Hitung PPh 23 dan PPh 21 (dibulatkan ke rupiah)
        $pph23 = round($hargaDasar * $pph23Rate);
        $pph21 = round($hargaDasar * $pph21Rate);

        // 3. Hitung PPN sebagai penyesuaian (agar total pas)
        $ppn = $hargaInclude - $hargaDasar + $pph23 + $pph21;

        // 4. Total pengecekan
        $total = $hargaDasar + $ppn - $pph23 - $pph21;

        return [
            'harga_dasar' => $hargaDasar,
            'ppn'         => $ppn,
            'pph23'       => $pph23,
            'pph21'       => $pph21,
            'total'       => $total,
        ];
    }

    public function hitungHargaDasarTanpaSelisih_All($hargaInclude, $tarifPPN, $tarifPPh23, $tarifPPh21)
    {
        // 1. Hitung harga dasar
        $dasar = $hargaInclude / (1 + $tarifPPN - $tarifPPh23 - $tarifPPh21);
        $hargaDasar = round($dasar, 0);

        // 2. Hitung PPh23 dan PPh21
        $pph23_full = $hargaDasar * $tarifPPh23;
        $pph23 = round($pph23_full, 0);

        $pph21_full = $hargaDasar * $tarifPPh21;
        $pph21 = round($pph21_full, 0);

        // 3. Hitung ppn (agar total pas)
        $ppn = $hargaInclude - $hargaDasar + $pph23 + $pph21;

        // 4. Total akhir
        $total = $hargaDasar + $ppn - $pph23 - $pph21;

        // Return semua nilai sebagai array
        return [
            $hargaDasar, // index 0
            $ppn,        // index 1
            $pph23,      // index 2
            $pph21,      // index 3
            $total       // index 4
        ];
    }

    public function hitungHargaDasarIncludeExclude($hargaInclude, $tarifPPN, $tarifPPh23, $tarifPPh21, $tarifTypes, $tarifPPh4 = 0)
    {
        $dividerAmt = 0;
        [$ppnType, $pph23Type, $pph21Type, $pph4Type] = $tarifTypes;

        // ------- DIVIDER AMT ------- //

        /**
         * revisi 2025/12/09
         * T = Exclude
         * E = Include
         * N = Non
         */


        // -- PPN
        if ($ppnType && $ppnType != 'N') {
            $ppnCond = $ppnType == 'E' ? 1 : 0;
            $dividerAmt += $ppnCond * $tarifPPN;
        }

        // -- PPH21
        if ($pph21Type && $pph21Type != 'N') {
            $pph21Cond = $pph21Type == 'E' ? -1 : 0;
            $dividerAmt += $pph21Cond * $tarifPPh21;
        }

        // -- PPH23
        if ($pph23Type && $pph23Type != 'N') {
            $pph23Cond = $pph23Type == 'E' ? -1 : 0;
            $dividerAmt += $pph23Cond * $tarifPPh23;
        }

        // -- PPH4
        if ($pph4Type && $pph4Type != 'N') {
            $pph4Cond = $pph4Type == 'E' ? $tarifPPh4 : 0;
            $hargaInclude += $pph4Cond;
        }

        // ------- END DIVIDER AMT  ------- //

        // 1. Hitung harga dasar
        $dasar = $hargaInclude / (1 + $dividerAmt);
        // $hargaDasar = round($dasar, 0);
        $hargaDasar = ceil($dasar);
        // 2. Hitung PPN, PPh23, PPh21 dan Total Amt
        $ppn = 0;
        $pph23 = 0;
        $pph21 = 0;
        $totalAmt = $hargaDasar;

        // -- PPN
        if ($ppnType && $ppnType != 'N') {
            $ppn_full = $hargaDasar * $tarifPPN;
            // $ppn = round($ppn_full, 0);
            $ppn = ceil($ppn_full);

            $totalAmt += $ppn;
        }

        // -- PPH23
        if ($pph23Type && $pph23Type != 'N') {
            $pph23_full = $hargaDasar * $tarifPPh23;
            // $pph23 = round($pph23_full, 0);
            $pph23 = ceil($pph23_full);

            $totalAmt -= $pph23;
        }

        // -- PPH21
        if ($pph21Type && $pph21Type != 'N') {
            $pph21_full = $hargaDasar * $tarifPPh21;
            // $pph21 = round($pph21_full, 0);
            $pph21 = ceil($pph21_full);

            $totalAmt -= $pph21;
        }

         // -- PPH4
        if ($pph4Type && $pph4Type != 'N') {
            $totalAmt -= $tarifPPh4;
        }

        // Return semua nilai sebagai array
        return [
            $hargaDasar, // index 0
            $ppn,        // index 1
            $pph23,      // index 2
            $pph21,      // index 3
            $tarifPPh4,  // index 4
            $totalAmt    // index 5
        ];
    }
}
