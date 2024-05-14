<?php
include_once( 'weatherapi_common.php' );

if( !defined('WP_UNINSTALL_PLUGIN') )
    exit();

delete_option(WEATHERAPI_PREFIX.'key');
delete_option(WEATHERAPI_PREFIX.'code');
delete_option(WEATHERAPI_PREFIX.'weatherdata');
delete_option(WEATHERAPI_PREFIX.'lastupdate');
delete_option(WEATHERAPI_PREFIX.'lastattempt');
delete_option(WEATHERAPI_PREFIX.'status');
delete_option(WEATHERAPI_PREFIX.'minduration');
delete_option(WEATHERAPI_PREFIX.'formats');
delete_option(WEATHERAPI_PREFIX.'novalue');

delete_option(WEATHERAPI_PREFIX.'suninfo');
delete_option(WEATHERAPI_PREFIX.'sun_date_format');
delete_option(WEATHERAPI_PREFIX.'sun_format');



?>
