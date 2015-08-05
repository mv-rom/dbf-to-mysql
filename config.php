<?php

//-- MySql parametrs
$mysql = array(
    'host' => '127.0.0.1',
    'name' => 'stg',
    'user' => 'stg',
    'pass' => 'supernewyear'
);


//-- DBF parametrs
$dbf_dir_path = dirname(__FILE__) . "/DB/WORK/";
$dbf = array (
    //Group of enabled abonents
    'abonents' => array(
        'name' => '_abonents.dbf',
        'link' => NULL,
    ),
    'pays' => array(
        'name' => '_pays.dbf',
        'link' => NULL,
    ),

    //Group of disabled abonents
    'oabonents' => array(
        'name' => '_OABONENTS.dbf',
        'link' => NULL,
    ),
    'opays' => array(
        'name' => '_opays.dbf',
        'link' => NULL,
    ),

    //Main settings
    'tarif' => array(
        'name' => '_dictarif.dbf',
        'link' => NULL,
        'data' => NULL,
        'cod_filter' => array(9),
    ),
    'street' => array(
        'name' => '_DICSTREET.dbf',
        'link' => NULL,
        'data' => NULL,
        'cod_filter' => array(29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44),
    )
);

?>