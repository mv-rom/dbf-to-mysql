<?php


function final_exit($cod) {
    global $dbf;
    global $conn;

    //close DBF files
    foreach($dbf as $k => $v) {
        $_file = $v['link'];
        if ($_file) {
            dbase_close($_file);
        }
    }

    //close connect to db
    if ($conn) { mysql_close($conn); }
    $conn = NULL;

    exit($cod);
}



//=========================================================================



function load_tarif($arr) {
    $l = $arr['link'];
    $cod_filter = $arr['cod_filter'];
    $data = array();

    $count = dbase_numrecords($l);
    for ($i = 0; $i <= $count; $i++) {
        $result = array();

        $row = @dbase_get_record_with_names($l, $i);
        foreach ($row as $key => $val){
            if ($key == 'deleted'){ continue; }
            $result[$key] = addslashes(trim($val));
        }

        //apply filter cod
        if ($cod_filter) {
            foreach ($cod_filter as $key => $val) {
                if (intval($result['COD']) == $val) {
                    $result = NULL;
                }
            }
        }

        //save result
        if (!empty($result) && !empty($result['NOTES'])) {
            array_push($data,
                array(
                    'COD' => $result['COD'],
                    'NOTES' => $result['NOTES'],
                    'SUMMA' => intval($result['SUMMA'])
                )
            );

        }
    }

    $arr['data'] = $data;
    return $data;
}

function load_street($arr) {
    $l = $arr['link'];
    $cod_filter = $arr['cod_filter'];
    $data = array();

    $count = dbase_numrecords($l);
    for ($i = 0; $i <= $count; $i++) {
        $result = array();

        $row = @dbase_get_record_with_names($l, $i);
        foreach ($row as $key => $val){
            if ($key == 'deleted'){ continue; }
            $result[$key] = addslashes(trim($val));
        }

        //apply filter cod
        if ($cod_filter) {
            foreach ($cod_filter as $cf) {
                if (intval($result['COD']) == $cf) {
                    $result = NULL;
                }
            }
        }

        //save result
        if (!empty($result)) {
            array_push($data,
                array(
                    'COD' => $result['COD'],
                    'NOTES' => $result['NOTES']
                )
            );
        }

    }

    $arr['data'] = $data;
    return $data;
}


function get_tarif_id($arr, $cod) {
    foreach ($arr['data'] as $a) {
        foreach ($a as $k=>$v) {
            if (($k == "COD") && ($v == $cod))  {
                return intval($a['id']);
            }
        }
    }
    return NULL;
}


function get_tarif($arr, $cod) {
    return get_notes($arr, $cod);
}

function get_street($arr, $cod) {
    return get_notes($arr, $cod);
}

function get_notes($arr, $cod) {
    foreach ($arr['data'] as $a) {
        foreach ($a as $k=>$v) {
            if (($k === "COD") && ($v === $cod))  {
                return $a['NOTES'];
            }
        }
    }
    return NULL;
}


$months = array(
    'ЯНВАРЬ' =>   '1',
    'ФЕВРАЛЬ' =>  '2',
    'МАРТ' =>     '3',
    'АПРЕЛЬ' =>   '4',
    'МАЙ' =>      '5',
    'ИЮНЬ' =>     '6',
    'ИЮЛЬ' =>     '7',
    'АВГУСТ' =>   '8',
    'СЕНТЯБРЬ' => '9',
    'ОКТЯБРЬ' =>  '10',
    'НОЯБРЬ' =>   '11',
    'ДЕКАБРЬ' =>  '12',
);
$pattern = '/^.*\s.*\s(?<from>\w+)\/(?<to>\w+).*$/';
///mb_internal_encoding("UTF-8");

function get_from_to($date_month, $date_year, $str) {
    $source_codepage = 'CP1251';
    $codepage = 'UTF-8';
    $matches = "";
    $arr = array(
        'from_month' => $date_month,
        'from_year' => $date_year,
        'to_month' => $date_month,
        'to_year' => $date_year
    );
    global $months;
    global $pattern;
    $_str = trim(iconv($source_codepage, $codepage, $str));


    if (mb_eregi($pattern, $_str, $matches)) {
        $_from = trim($matches['from']);
        foreach ($months as $k => $v) {
            if (mb_stripos($_from, $k, 0, $codepage) !== false) {
                $arr['from_month'] = $v;
            }
        }
        $_to = trim($matches['to']);
        foreach ($months as $k => $v) {
            if (mb_stripos($_to, $k, 0, $codepage) !== false) {
                $arr['to_month'] = $v;
            }
        }

        //check if month is in old year
        if ($arr['from_month'] >= $arr['to_month']) {
            $arr['from_year'] -= 1;
        }
    } else {
        foreach ($months as $k => $v) {
            if (mb_stripos($_str, $k, 0, $codepage) !== false) {
                $arr['from_month'] = $v;
                $arr['to_month'] = $v;
            }
        }
    }

    return $arr;
}


/**
 * @param $fio
 * @return string
 */
function correct($s) {
    $source_codepage = 'cp1251';

    if (empty($s)) {
        return NULL;
    }
    //print(iconv('cp1251','UTF-8',"==>> [".$s."] "));

    $len = mb_strlen($s, $source_codepage);
    $n = mb_strpos($s, ' ', 1, $source_codepage);
    if (empty($n)) $n=$len;

    //print "\nn=".$n;
    //print(iconv('cp1251','UTF-8',"\ns=[".$s."]\n"));
    //print(iconv('cp1251','UTF-8',"1_s=[".mb_substr($s, 0, 1, $codepage)."]\n"));
    //print(iconv('cp1251','UTF-8',"2_s=[".mb_strtolower(mb_substr($s, 1, $n-1, $codepage), $codepage)."]\n"));
    //print(iconv('cp1251','UTF-8',"3_s=[".mb_substr($s, $n, $len, $codepage)."]\n"));

    $result = mb_substr($s, 0, 1, $source_codepage).
        "".
        mb_strtolower(mb_substr($s, 1, $n-1, $source_codepage), $source_codepage).
        "".
        mb_substr($s, $n, $len, $source_codepage);

    return $result;
}

/**
 * @param $fio
 * @return string
 */
function fio_correct($fio) {
    return correct($fio);
}

/**
 * @param $street
 * @return string
 */
function street_correct($street) {
    return correct($street);
}


function get_cash($id, $conn) {
    $result = NULL;
    $q="SELECT `cash` FROM `catv_users` WHERE `id`=".$id;
    $res = mysql_query($q, $conn);

    if (!empty($res)) {
        $row = mysql_fetch_assoc($res);
        if (!empty($row)) {
            $result = floatval($row['cash']);
        }
    } else {
        $msg  = 'Invalid query: ' . mysql_error() . "\n";
        $msg .= 'Whole query: ' . $q."\n";
        print($msg);
    }

    return $result;
}

?>