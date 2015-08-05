#!/usr/bin/env php
<?php

/*
Add support for dbase in php 5.3.8
http://www.calvinfroedge.com/add-support-for-dbase-in-php-5-3-8/
--------------------------

//do in FreeBsd 9.2

cd ~/
fetch http://pecl.php.net/get/dbase-5.1.0.tgz
tar -xzf dbase-5.1.0.tgz
cd dbase-5.1.0/
phpize
./configure
make
make install
echo "extension=dbase.so" >> /usr/local/etc/php/extensions.ini
*/


/*
 *  Source of DBF DB must have a special format(dBase 3 or ), which php can read
 */



//=========================================================================
//-- Working system locale
setlocale(LC_ALL, "ru_RU.cp1251");

include 'lib.php';
include 'config.php';


global $mysql;
$conn = NULL;
$global_count_users = 0;
$time_start = microtime(true);


//=========================================================================
//-- open dbf files
print("\n\n=================================================\n");
print("|> Start opening DBF DB in directory: " . $dbf_dir_path."\n");
foreach ($dbf as $k => $v) {
    $dbf[$k]['link'] = dbase_open($dbf_dir_path.$v['name'], 0) or die("Error! Could not open dbase database file '".$dbf_dir_path.$v['name']."'.\n");
}
print("|- Opening is OK.\n");


//-- load tariffs to cache
$dbf['tarif']['data'] = load_tarif($dbf['tarif']);
//load streets
$dbf['street']['data'] = load_street($dbf['street']);



//-- processing mysql
print("\n|> Start processing with MySql DB: " . $mysql['name']."\n");
print_r($mysql);

$conn = mysql_connect($mysql['host'], $mysql['user'], $mysql['pass']);
if (!$conn) {
    print("Error! Could not connect to mysql db '".$mysql['name']."'.\n");
    final_exit(-1);
}
mysql_select_db($mysql['name'], $conn);
if (mysql_select_db($mysql['name'], $conn) === false) {
    print('Could not select database: ' . mysql_error($conn)."\n");
    final_exit(-1);
}
mysql_query("SET NAMES `cp1251`");


//-- set unlimited time for limit of script runtime
set_time_limit(0);


//-- clear all catv data at first
mysql_query("TRUNCATE TABLE `catv_activity`");
mysql_query("ALTER TABLE `catv_activity` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_activity`");

mysql_query("TRUNCATE TABLE `catv_fees`");
mysql_query("ALTER TABLE `catv_fees` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_fees`");

mysql_query("TRUNCATE TABLE `catv_payments`");
mysql_query("ALTER TABLE `catv_payments` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_payments`");

mysql_query("TRUNCATE TABLE `catv_signups`");
mysql_query("ALTER TABLE `catv_signups` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_signups`");

mysql_query("TRUNCATE TABLE `catv_tariffs`");
mysql_query("ALTER TABLE `catv_tariffs` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_tariffs`");

mysql_query("TRUNCATE TABLE `catv_users`");
mysql_query("ALTER TABLE `catv_users` AUTO_INCREMENT = 1");
mysql_query("FLUSH TABLES `catv_users`");



//-- save tariffs to db
for ($i = 0; $i < count($dbf['tarif']['data']); $i++) {
    $arr = $dbf['tarif']['data'][$i];
    if (!empty($arr)) {
        $query="INSERT INTO `catv_tariffs`
                    (`id`, `name`, `price`, `chans`)
                VALUES (
                    NULL,
                    '".$arr['NOTES']."',
                    '".$arr['SUMMA']."',
                    21
                );";

        $q_res = mysql_query($query, $conn);
        if (!empty($q_res)) {
            $dbf['tarif']['data'][$i]['id'] = mysql_insert_id($conn);
        } else {
            $msg = "\n>>> Error adding tariffs:\n";
            $msg .= "Invalid query: " . mysql_error() . "\n";
            $msg .= 'Whole query: ' . $query."\n";
            print($msg);

            final_exit(-1);
        }
    }
}
print("\n|- Tariffs:\n");
print_r($dbf['tarif']['data']);



//-- main processing
$ooo = array(
    array(
        'a'=> 'abonents',
        'p'=> 'pays'
    ),
    array(
        'a'=> 'oabonents',
        'p'=> 'opays'
    )
);

foreach ($ooo as $o) {
    print("\n");
    if ($o['a'] == "abonents") {
        print("|- Group of adding abonents: Enabled.\n\n");
    } else {
        print("|- Group of adding abonents: Disabled.\n\n");
    }

    //-- add abonents
    $abon_link = $dbf[$o['a']]['link'];
    $count = dbase_numrecords($abon_link);
    print("\n|- Count of adding abonents:".$count."\n");

    for ($i = 0; $i <= $count; $i++) {
        //if ($o['a'] == "abonents" && $i <= 1970) continue;
        //if ($o['a'] == "oabonents" && $i <= 210) continue;

        $user_id = NULL;
        $abon_result = array();

        $row = @dbase_get_record_with_names($abon_link, $i);
        if (empty($row)) break;
        foreach ($row as $key => $val) {
            if ($key == 'deleted') {
                continue;
            }
            $abon_result[$key] = addslashes(trim($val));
        }

        if (!empty($abon_result) && !empty($abon_result['FIO'])) {
            $__t = $abon_result['TARIF'];

            //cod filter apply with adding abonents
            $__cf = $dbf['tarif']['cod_filter'];
            if ($__t != NULL && !empty($__cf) && in_array(intval($__t), $__cf)) {
                continue;
            }

            $_fio = fio_correct($abon_result['FIO']);
            $_notes = $abon_result['NOTES1'];
            $_datedog = $abon_result['DATEDOG'];
            $_datedog_mysql_format = sprintf("%04d-%02d-%02d",
                substr($_datedog, 0, 4), substr($_datedog, 4, 2), substr($_datedog, 6, 2)
            );
            $_corp = "NULL";
            if (!empty($abon_result['CORP'])) {
                $_corp = $abon_result['CORP'];
            }
            $_street = street_correct(
                get_street($dbf['street'], $abon_result['STREET'])
            );
            $_tarif = get_tarif_id($dbf['tarif'], $__t);

            $query = "INSERT INTO `catv_users` (
                    `id`, `contract`, `regdate`, `corp`, `realname`, `passnum`, `street`, `build`, `apt`, `phone`, `tariff`, `tariff_nm`,
                    `cash`, `saldo`, `discount`, `notes`, `decoder`
                )
                VALUES (
                     NULL,
                     '" . $abon_result['NOMDOG'] . "',
                     '" . $_datedog_mysql_format . "',
                     '" . $_corp . "',
                     '" . $_fio . "',
                     '" . $abon_result['PAS1'] . " " . $abon_result['PAS2'] . "',
                     '" . $_street . "',
                     '" . $abon_result['HOME'] . "',
                     '" . $abon_result['FLAT'] . "',
                     '" . $abon_result['PHONE'] . "',
                     '" . $_tarif . "',
                     NULL,
                     '" . $abon_result['SUMMABON'] . "',
                     '" . $abon_result['SALDO'] . "',
                     '0',
                     '" . $_notes . "',
                     '1'
                );";


            if (mysql_query($query, $conn)) {
                $global_count_users += 1;
                $user_id = mysql_insert_id($conn);

                if (empty($user_id)) continue;


                //-- add signup info
                $q = "INSERT INTO `catv_signups` (
                        `id`, `date`, `userid`, `admin`
                    )
                    VALUES (
                        NULL,
                        now(),
                        '" . $user_id . "',
                        'migration'
                    );";
                mysql_query($q, $conn);


                //-- change activity of enabled abonents
                if ($o['a'] == "abonents") {
                    $q = "INSERT INTO `catv_activity` (
                            `id`, `userid`, `state`, `date`, `admin`
                        )
                        VALUES (
                            NULL,
                            '" . $user_id . "',
                            1,
                            now(),
                            'migration'
                        )";
                    mysql_query($q, $conn);
                    print("\nenabling of user: " . $user_id . "\n");
                }


                //-- add pays
                $pays_link = $dbf[$o['p']]['link'];
                $count = dbase_numrecords($pays_link);
                print("Cash: " . $abon_result['SUMMABON'] . "\n");

                for ($j = 0; $j <= $count; $j++) {
                    $pay_result = array();

                    $row = @dbase_get_record_with_names($pays_link, $j);
                    foreach ($row as $key => $val) {
                        if ($key == 'deleted') {
                            continue;
                        }
//                        $pay_result[$key] = addslashes(trim($val));
                        $pay_result[$key] = $val;
                    }

                    if (!empty($pay_result)) {
                        //filter all, use only pays of this abonent
                        if ($abon_result['COD'] != $pay_result['COD']) {
                            continue;
                        }
                        $_date = $pay_result['DATE_PAY1'];
                        $_date_mysql_format = sprintf("%04d-%02d-%02d",
                            substr($_date, 0, 4), substr($_date, 4, 2), substr($_date, 6, 2)
                        );
                        $_summa = floatval($pay_result['SUMMA']);
                        $_notes = $pay_result['NOTES'];
                        $_from_to = get_from_to(
                            intval(substr($_date, 4, 2)),
                            intval(substr($_date, 0, 4)),
                            $_notes
                        );
                        $_tarif = get_tarif($dbf['tarif'], $abon_result['TARIF']);

                        $p_query = "INSERT INTO `catv_payments` (
                                `id`, `date`, `userid`, `summ`, `tariff`, `from_month`, `from_year`, `to_month`, `to_year`, `notes`, `admin`
                            )
                            VALUES (
                                NULL ,
                                '" . $_date_mysql_format . "',
                                '" . $user_id . "',
                                '" . $_summa . "',
                                '" . $_tarif . "',
                                '" . $_from_to['from_month'] . "',
                                '" . $_from_to['from_year'] . "',
                                '" . $_from_to['to_month'] . "',
                                '" . $_from_to['to_year'] . "',
                                '" . $_notes . "',
                                'migration'
                            );";


                        if (mysql_query($p_query, $conn)) {
                            //-- correct balance of abonent
                            $u_query = "UPDATE `catv_users` SET `cash` = `cash`- '" . $_summa . "' WHERE `catv_users`.`id` = " . $user_id;
                            mysql_query($u_query, $conn);

                            //print("{[".$_summa."]=");

                            //-- calculate fees using date
                            $_last_cash = get_cash($user_id, $conn);
                            $a = array();
                            //-- by default have range like one year
                            $r_year = 1 + $_from_to['to_year'] - $_from_to['from_year'];

                            for ($ii = $_from_to['from_year']; $ii <= $_from_to['to_year']; $ii++) {
                                $aa = array();
                                $r_month = 0; //by default have range like one month
                                if ($r_year > 1) {
                                    //x(1)..12 -..- 1..x(12)
                                    for ($iii = $_from_to['from_month']; $iii <= 12; $iii++) {
                                        $r_month += 1;
                                        $aa[$r_month] = $iii;
                                    }
                                    for ($iii = 1; $iii <= $_from_to['to_month']; $iii++) {
                                        $r_month += 1;
                                        $aa[$r_month] = $iii;
                                    }
                                } else {
                                    //x(1)..x(12)
                                    for ($iii = $_from_to['from_month']; $iii <= $_from_to['to_month']; $iii++) {
                                        $r_month += 1;
                                        $aa[$r_month] = $iii;
                                    }
                                }

                                if ($r_month > 0) {
                                    for ($jj = 1; $jj <= $r_month; $jj++) {
                                        array_push($a, array(
                                                'month' => $aa[$jj],
                                                'year' => $ii,
                                                'summa' => $_summa / $r_month / $r_year
                                            )
                                        );
                                    }
                                }
                            } //end for work with $a

                            //-- add fees
                            $l = 0;
                            for ($l = 0; $l < count($a); $l++) {
                                $al = $a[$l];
                                $q = "INSERT INTO `catv_fees` (
                                        `id`, `date`, `userid`, `summ` ,`balance` ,`month` ,`year` ,`admin`
                                    )
                                    VALUES (
                                        NULL,
                                        now(),
                                        '" . $user_id . "',
                                        '" . $al['summa'] . "',
                                        '" . $_last_cash . "',
                                        '" . $al['month'] . "',
                                        '" . $al['year'] . "',
                                        'migration'
                                    )";
                                //print("fee: [".$l."] = [".$q."]\n");
                                //print("(".($l+1).")} ");
                                mysql_query($q, $conn);
                            }

                        } else {
                            $msg = "\n\n== Error ====================================================\n";
                            $msg .= "Pay Error, when do adding pay for user: " . $abon_result['user'] . "\n";
                            print($msg);
                            print_r($pay_result);
                            print(mysql_error($conn)."\n");
                            $msg = "SQL: $p_query\n";
                            //print($msg);
                            print(iconv('cp1251','UTF-8',$msg));
                            print(substr_count($p_query, ',') + 1) . " Fields total.\n";

                            $msg = "------------------------\n";
                            $msg .= "User Error, when do adding user: " . $abon_result['user'] . "\n";
                            $msg .= mysql_error($conn) . "\nSQL: $query\n";
                            $msg .= substr_count($query, ',') + 1 . " Fields total.\n";
                            //print($msg);
                            print(iconv('cp1251','UTF-8',$msg));
                            print_r($abon_result);

                            final_exit(-1);
                        }
                    }
                } //end of add pays


            } else {
                $msg = "\n\n== Error ====================================================\n";
                $msg .= "User Error, when do adding user: " . $abon_result['user'] . "\n";
                $msg .= mysql_error($conn) . "\nSQL: $query\n";
                $msg .= substr_count($query, ',') + 1 . " Fields total.\n";
                //print($msg);
                print(iconv('cp1251','UTF-8',$msg));
                print_r($abon_result);

                final_exit(-1);
            }

        }

    } //end of add user

    $time_end = microtime(true);
    $exec_time_m = ($time_end - $time_start)/60;
    print("|- Total Execution Time: ".round($exec_time_m/60)." hours ".round($exec_time_m, 2)." mins");
}
print("\n|- Processing is OK\n\n\n");



//==============================================================================

//-- status
print("|-> Result: -----------------------------------\n");
print("|- Count of added users: " . $global_count_users ."\n");


final_exit(0);
?>