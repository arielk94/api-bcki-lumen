<?php
use Illuminate\Support\Carbon;

if (!function_exists('tglIndo')) {
    function tglIndo($tgl, $tampil_hari=true)
    {
        $nama_hari  = array("Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu");
        $nama_bulan	= array(1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April", 5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Agustus", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember");

        $tahun = substr($tgl, 0, 4);
        $bulan = $nama_bulan[(int)substr($tgl, 5, 2)];
        $tanggal = substr($tgl, 8, 2);

        $text = "";

        if ($tampil_hari) {
          $urutan_hari = date('w', mktime(0,0,0, substr($tgl, 5, 2), $tanggal, $tahun));
          $hari = $nama_hari[$urutan_hari];
          $text .= $hari. ", ";
        }
        $text .= $tanggal ." ". $bulan. " ". $tahun;
        return $text;
    }
}

if (!function_exists('tglWaktuIndo')) {
    function tglWaktuIndo($tgl, $tampil_hari=true)
    {
        $nama_hari  = array("Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu");
        $nama_bulan	= array(1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April", 5=>"Mei", 6=>"Juni", 7=>"Juli", 8=>"Agustus", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember");

        $tahun = substr($tgl, 0, 4);
        $bulan = $nama_bulan[(int)substr($tgl, 5, 2)];
        $tanggal = substr($tgl, 8, 2);
        $jam = substr($tgl, 11, 5);

        $text = "";

        if ($tampil_hari) {
          $urutan_hari = date('w', mktime(0,0,0, substr($tgl, 5, 2), $tanggal, $tahun));
          $hari = $nama_hari[$urutan_hari];
          $text .= $hari. ", ";
        }
        $text .= $tanggal ." ". $bulan. " ". $tahun. ", " . $jam ." WIB";
        return $text;
    }
}

if (!function_exists('short_date')) {
    function short_date($tgl, $tampil_hari=true)
    {
        $nama_hari  = array("Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab");
        $nama_bulan	= array(1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"Mei", 6=>"Jun", 7=>"Juli", 8=>"Ags", 9=>"Sep", 10=>"Okt", 11=>"Nov", 12=>"Des");

        $tahun = substr($tgl, 0, 4);
        $bulan = $nama_bulan[(int)substr($tgl, 5, 2)];
        $tanggal = substr($tgl, 8, 2);

        $text = "";

        if ($tampil_hari) {
          $urutan_hari = date('w', mktime(0,0,0, substr($tgl, 5, 2), $tanggal, $tahun));
          $hari = $nama_hari[$urutan_hari];
          $text .= $hari. ", ";
        }
        $text .= $tanggal ." ". $bulan. " ". $tahun;
        return $text;
    }
}

if (!function_exists('hitung_mundur')) {
    function hitung_mundur($date)
    {
        $waktu = [
            365*24*60*60    => "tahun",
            30*24*60*60     => "bulan",
            7*24*60*60      => "minggu",
            24*60*60        => "hari",
            60*60           => "jam"
        ];

        $now = gmdate ("Y-m-d H:i:s", time () + 60 * 60 * 8);
        $hitung = intval(strtotime($now) - strtotime($date));
        $hasil = array();
        
        if ($hitung<5) {
            $hasil = 'kurang dari 5 detik yang lalu';
        } else {
            $stop = 0;

            foreach ($waktu as $periode => $satuan) {
                if ($stop>=6 || ($stop>0 && $periode<60)) {
                    break;
                }

                $bagi = floor($hitung/$periode);

                if ($bagi > 0) {
                    $hasil[] = $bagi.' '.$satuan;
                    $hitung -= $bagi*$periode;
                    $stop++;
                } else if ($stop>0) {
                    $stop++;
                }

            }

            $hasil = implode(' ',$hasil).' yang lalu';
        }
        return $hasil;
    }
}

if (!function_exists('format_uang')) {
    function format_uang($value, $visible = true)
    {
        if ($visible) {
            return number_format($value ,0, ',' , '.' );
        }

        return number_format($value ,0, ',' , '.' );
    }
}

if (!function_exists('friendly_number')) {
    function friendly_number($value)
    {
        if ($value) {
            $log_floor = floor(log($value)/log(1000));
            $suffix = 'kmb'[(int) $log_floor - 1];
            $pow = pow(1000, $log_floor);
            $value /= $pow;
            $round_final = round(($value*100) / 100);
            
            return $log_floor ?  $round_final . $suffix : $value;
        }
        
        return 0;
    }
}

if (!function_exists('expire_weekend')) {
    function expire_weekend()
    {
        if (now()->isFriday() || now()->isSaturday()) {
            return now()->addDays(3);
        }

        if (now()->isSunday()) {
            return now()->addDays(2);
        }

        return now()->addDay();
    }
}

if (!function_exists('generate_otp')) {
    function generate_otp($length)
    {
        $generator = "1234567890";
		$result = ""; 
	
		for ($i = 1; $i <= $length; $i++) { 
			$result .= substr($generator, (rand()%(strlen($generator))), 1); 
		}
		
		return $result; 
    }
}

if (!function_exists('format_phone_number')) {
    function format_phone_number($number)
    {
        return preg_replace('/^0/','+62', $number);
    }
}

if (!function_exists('generate_password')) {
    function generate_password($length)
    {
        $generator = "propertyxyzushjhajsdhjashdjkaskdjkoio12102p1.,/1234567890";
		$result = ""; 
	
		for ($i = 1; $i <= $length; $i++) { 
			$result .= substr($generator, (rand()%(strlen($generator))), 1); 
		}
		
		return $result; 
    }
}

function terbilang($x = 0)
{
    $x     = abs($x);
    $angka = [
        "",
        "satu",
        "dua",
        "tiga",
        "empat",
        "lima",
        "enam",
        "tujuh",
        "delapan",
        "sembilan",
        "sepuluh",
        "sebelas",
    ];
    $temp  = "";
    if($x < 12) {
        $temp = " " . $angka[$x];
    } elseif($x < 20) {
        $temp = terbilang($x - 10) . " belas ";
    } elseif($x < 100) {
        $temp = terbilang($x / 10) . " puluh " . terbilang($x % 10);
    } elseif($x < 200) {
        $temp = " seratus " . terbilang($x - 100);
    } elseif($x < 1000) {
        $temp = terbilang($x / 100) . " ratus " . terbilang($x % 100);
    } elseif($x < 2000) {
        $temp = " seribu " . terbilang($x - 1000);
    } elseif($x < 1000000) {
        $temp = terbilang($x / 1000) . " ribu " . terbilang($x % 1000);
    } elseif($x < 1000000000) {
        $temp = terbilang($x / 1000000) . " juta " . terbilang($x % 1000000);
    } elseif($x < 1000000000000) {
        $temp = terbilang($x / 1000000000) . " milyar " . terbilang(fmod($x, 1000000000));
    } elseif($x < 1000000000000000) {
        $temp = terbilang($x / 1000000000000) . " trilyun " . terbilang(fmod($x, 1000000000000));
    }
    return trim($temp);
}

function getRomawi($bln)
{
    switch($bln) {
        case 1:
            return "I";
            break;
        case 2:
            return "II";
            break;
        case 3:
            return "III";
            break;
        case 4:
            return "IV";
            break;
        case 5:
            return "V";
            break;
        case 6:
            return "VI";
            break;
        case 7:
            return "VII";
            break;
        case 8:
            return "VIII";
            break;
        case 9:
            return "IX";
            break;
        case 10:
            return "X";
            break;
        case 11:
            return "XI";
            break;
        case 12:
            return "XII";
            break;
    }
}

if (!function_exists('now')) {
    function now($tz = null) {
        return Carbon::now($tz);
    }
}

function getAuth($request){
    return (object) $request->user;
}