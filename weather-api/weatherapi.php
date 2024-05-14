<?php
/*
Plugin Name: WP YuMake Weather API
Plugin URI:
Description: 気象情報表示プラグイン for YuMake Weather API
Version: 1.5
Author: TouchWorks
Author URI: 
*/
include_once( 'weatherapi_common.php' );


/** 初期化 **/
register_activation_hook(__FILE__,'weatherapi_install');

function weatherapi_install () {
  add_option( WEATHERAPI_PREFIX.'key','' );
  add_option( WEATHERAPI_PREFIX.'code','13' );
  add_option( WEATHERAPI_PREFIX.'weatherdata',[] );
  add_option( WEATHERAPI_PREFIX.'lastupdate', 0 );
  add_option( WEATHERAPI_PREFIX.'lastattempt',0 );
  add_option( WEATHERAPI_PREFIX.'status',FALSE );
  add_option( WEATHERAPI_PREFIX.'minduration', 15 ); // 最短取得間隔
  add_option( WEATHERAPI_PREFIX.'formats', [
      '<div><p>[forecastDateName]の天気</p><p>[weather]</p><p>気温: [minTemp]℃ | [maxTemp]℃</p></div>',
      '<div><p>[forecastDateName]の天気</p><p>[weather]</p><p>気温: [minTemp]℃ | [maxTemp]℃</p></div>',
      '<div><p>[forecastDateName]の天気</p><p>[weather]</p><p>気温: [minTemp]℃ | [maxTemp]℃</p></div>'
      ]  ); // 出力フォーマット
  add_option( WEATHERAPI_PREFIX.'novalue', '--'); // 値の無いときのテキスト


}


/* メニュー追加 */
add_action('admin_menu', 'weatherapi_mainmenu_content_menu');


function weatherapi_mainmenu_content_menu() {
  // メインメニュー
  add_menu_page('天気API',
                '天気API', 8,
                 __FILE__,
                 'weatherapi_mainmenu_content',
                  plugins_url() .'/'.dirname(plugin_basename( __FILE__ )). '/weatherapi_icon.png');
}

// メニューの内容
function weatherapi_mainmenu_content() {
  echo "<h2>お天気情報 for YuMake API</h2>";
  $update_flag = false;

  
  // 再取得
  if ( isset( $_POST['action'] )) {
    if ( $_POST['action'] == 'update' ) {
      $update_flag = true;
    }
  }

  // キーと取得対象更新
  if ( isset( $_POST['key'] ) ) {
    $p_key = $_POST['key'];
    if ( filter_var( $p_key, FILTER_VALIDATE_REGEXP, ['options'=> ['regexp'=>'/[0-9a-z]{32}/']]) === FALSE ) {
      echo '<div class="error notice is-dismissible"><p><strong>キーの形式が正しくありません。</strong></p></div>';
    } else {
      // キーが変更されているか
      $key = get_option( WEATHERAPI_PREFIX.'key');
      if ( $key!==$p_key ) {
        echo '<div class="updated notice is-dismissible"><p><strong>キーを更新しました。</strong></p></div>';
        update_option( WEATHERAPI_PREFIX.'key', $p_key );
        $update_flag = true;
      }
    }
  }

  // 緯度経度
  if ( isset( $_POST['lon'] ) && isset( $_POST['lat'] ) ) {
    $lon = $_POST['lon'];
    $lat = $_POST['lat'];
    
    if ( !is_numeric( $lon ) || !is_numeric( $lat ) ) {
      echo '<div class="error notice is-dismissible"><p><strong>緯度・経度の形式が正しくありません。</strong></p></div>';
    } else {
      // 変更されているか
      $sun = new WAPI_sun( $key );
      if ( $lon != $sun->suninfo->lon || $lat != $sun->suninfo->lat ) {
        echo '<div class="updated notice is-dismissible"><p><strong>緯度・経度を変更しました。</strong></p></div>';
        $sun->suninfo->lon = floatval($lon);
        $sun->suninfo->lat = floatval($lat);
        $sun->get_json();
      }
    }
  }
  
  
  if ( isset( $_POST['code'] )) {
    $p_code = $_POST['code'];
    if ( in_array( $p_code, $GLOBALS['wapi_region_codes'] ) ) {
      // コード変更されたか
      $code = get_option( WEATHERAPI_PREFIX.'code');
      if ( $code !== $p_code ) {
        update_option( WEATHERAPI_PREFIX.'code', $p_code );
        echo '<div class="updated notice is-dismissible"><p><strong>対象地域を更新しました。</strong></p></div>';
        $update_flag=true;
      }
    } else {
      echo '<div class="error notice is-dismissible"><p><strong>対象地域が正しくありません。</strong></p></div>';
    }
  }
  
  // 最短間隔が更新されたか
  if ( isset( $_POST['minduation'] )) {
    $p_minduation = $_POST['minduation'];
    if ( in_array( $p_minduation, $GLOBALS['wapi_min_durations'] ) ) {
      // 更新されたか
      $minduation = get_option( WEATHERAPI_PREFIX.'minduration');
      if ( $minduation !== $p_minduation ) {
        update_option( WEATHERAPI_PREFIX.'minduration', $p_minduation );
        echo '<div class="updated notice is-dismissible"><p><strong>最短取得間隔を更新しました。</strong></p></div>';
      }
    } else {
      echo '<div class="error notice is-dismissible"><p><strong>最短取得間隔が正しくありません。</strong></p></div>';
    }
  }
  
  if ( $update_flag ) {
    if ( wapi_update_data() ) {
      wapi_set_formatted();
      echo '<div class="updated notice is-dismissible"><p><strong>気象データを正常に取得しました。</strong></p></div>';
    } else {
      echo '<div class="error notice is-dismissible"><p><strong>気象データの取得に失敗しました。</strong></p></div>';
    }
  }

  // 出力フォーマットの更新
  if ( isset( $_POST['novalue'] ) && isset( $_POST['formats'] ) ) {
    update_option( WEATHERAPI_PREFIX.'novalue', stripslashes_deep($_POST['novalue']) );
    update_option( WEATHERAPI_PREFIX.'formats', stripslashes_deep($_POST['formats']) );
    wapi_set_formatted();
    echo '<div class="updated notice is-dismissible"><p><strong>出力フォーマットを更新しました。</strong></p></div>';
  }

  // 日の出・出力フォーマットの更新
  if ( isset( $_POST['sun_date_format'] ) && isset( $_POST['sun_format'] ) ) {
    $sun = new WAPI_sun( $key );
    $sun->sun_date_format = stripslashes_deep( $_POST['sun_date_format'] );
    $sun->sun_format = stripslashes_deep($_POST['sun_format']);
    $sun->save();
    echo '<div class="updated notice is-dismissible"><p><strong>日の出・日の入り出力フォーマットを更新しました。</strong></p></div>';
  }
  
  
  /// フォーム表示
  $key = get_option( WEATHERAPI_PREFIX.'key');
  $code = get_option( WEATHERAPI_PREFIX.'code');
  
  $weatherdata = get_option( WEATHERAPI_PREFIX.'weatherdata');
  $lastupdate = get_option( WEATHERAPI_PREFIX.'lastupdate');
  $lastattempt = get_option( WEATHERAPI_PREFIX.'lastattempt');
  $status = get_option( WEATHERAPI_PREFIX.'status');
  $minduration = get_option( WEATHERAPI_PREFIX.'minduration');
  $formats = get_option( WEATHERAPI_PREFIX.'formats');
  $novalue = get_option( WEATHERAPI_PREFIX.'novalue');
  
  $sun = new WAPI_sun( $key ); // 日の出日の入り情報クラス
  
?>

  <div class='card'>
  <h3>基本設定</h3>
  <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);?>">
  <p>APIキー: <input type="text" name="key" value="<?php echo htmlspecialchars($key);?>" size="35" maxlength="32"></p>
  <p>取得対象: <select name="code"><?php
    foreach( $GLOBALS['wapi_region_codes'] as $key=>$val ) {
      if ( $code === $val ) {
        $selected = ' selected';
      } else {
        $selected = '';
      }
      echo '<option value="'.$val.'"'.$selected.'>'.htmlspecialchars( $key ).'</option>';
    }
    ?></select></p>
  <p>最短取得間隔: <select name="minduation"><?php
    foreach( $GLOBALS['wapi_min_durations'] as $val ) {
      if ( $minduration == $val ) {
        $selected = ' selected';
      } else {
        $selected = '';
      }
      echo '<option value="'.($val).'"'.$selected.'>'.$val.'分'.'</option>';
    }
?></select></p>
  <h4>日の出／日の入り情報</h4>
  <p>緯度: <input type="text" name="lat" value="<?php echo htmlspecialchars($sun->suninfo->lat); ?>" style="width: 100px;">&nbsp;&nbsp;
  経度: <input type="text" name="lon" value="<?php echo htmlspecialchars($sun->suninfo->lon); ?>" style="width: 100px;">&nbsp;&nbsp;
<?php echo '      <a href="https://www.google.co.jp/maps/@'.htmlspecialchars($sun->suninfo->lat).','.htmlspecialchars($sun->suninfo->lon).',17z?hl=ja" target="_blank">Googleマップで確認</a>';?>
  </p>
  <input type="submit" class="button button-primary button-large" name="delete_month" value="更新">
  </form>
  </div>

  <div class='card'>
  <h3>キャッシュ状況</h3>
<?php
  $lastupdate_txt = ( $lastupdate == 0 ) ? '未取得' : date('Y-m-d G:i:s', $lastupdate );
  $lastattempt_txt = ( $lastattempt == 0 ) ? '未取得' : date('Y-m-d G:i:s', $lastattempt );
  $status_txt = ( $status ) ? '<span style="color:green">有効</span>':'<span style="color:red">無効</span>';

  $sun_lastattempt_txt = ( $sun->suninfo->last_attempt == 0 ) ? '未取得' : date('Y-m-d G:i:s', $sun->suninfo->last_attempt );
  $sun_status_txt = ( $sun->suninfo->status ) ? '<span style="color:green">有効</span>':'<span style="color:red">無効</span>';

?><h4>天気予報情報</h4>
     <p>最終取得成功: <b><?php echo $lastupdate_txt;?></b><br>
       最終取得試行: <b><?php echo $lastattempt_txt;?></b><br>
       データ有効性: <b><?php echo $status_txt;?></b><br>
    </p>
  <h4>日の出・日の入り情報</h4>
       最終取得試行: <b><?php echo $sun_lastattempt_txt;?></b><br>
       データ有効性: <b><?php echo $sun_status_txt;?></b><br>
    </p>
    <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);?>"> 
      <input type="hidden" name="action" value="update">
      <input type="submit" class="button button-primary button-large" name="" value="再取得">
    </form>
  </div>
<?php
  
  // 出力フォーマット
  echo '<div class="card"><h3>日の出・日の入り時刻出力フォーマット</h3>';
  echo '<form name="form1" method="post" action="'.str_replace( '%7E', '~', $_SERVER['REQUEST_URI']).'">';
  echo '<p>日付時刻出力フォーマット: <input type="text" name="sun_date_format" value="'.htmlspecialchars($sun->sun_date_format).'" size="10"> <a href="http://php.net/manual/ja/function.date.php" target="_blank">PHPの日付形式</a></p>';
  echo '<textarea cols="40" rows="3" name="sun_format" style="width: 100%;">'.htmlspecialchars($sun->sun_format).'</textarea>';
  echo '<input type="submit" class="button button-primary button-large" name="delete_month" value="更新">';
  echo '</form></div>';
  
  echo '<div class="card"><h3>天気予報出力フォーマット</h3>';
  echo '<form name="form1" method="post" action="'.str_replace( '%7E', '~', $_SERVER['REQUEST_URI']).'">';
  echo '<p>値の無い項目の出力: <input type="text" name="novalue" value="'.htmlspecialchars($novalue).'" size="10"></p>';
  echo '<h4>今日の予報</h4>';
  echo '<textarea cols="40" rows="5" name="formats[]" style="width: 100%;">'.htmlspecialchars($formats[0]).'</textarea>';
  echo '<h4>明日の予報</h4>';
  echo '<textarea cols="40" rows="5" name="formats[]" style="width: 100%;">'.htmlspecialchars($formats[1]).'</textarea>';
  echo '<h4>明後日の予報（無い場合は出力されません）</h4>';
  echo '<textarea cols="40" rows="5" name="formats[]" style="width: 100%;">'.htmlspecialchars($formats[2]).'</textarea>';
  echo '<input type="submit" class="button button-primary button-large" name="delete_month" value="更新">';
  echo '</form></div>';

  
  echo '<hr><h3>保持データ</h3>';
  
  // 有効なデータがあれば内容を表示j
  if ( $sun->suninfo->status ) {
    echo '<div class="card">';
    echo '<h3>日の出・日の入り情報</h3>';
    echo '<table class="widefat striped">';
    echo '<tr><td colspan="3">';
    echo '<p>ショートコード: <input type="text" readonly value="[weatherapi_suninfo]" size="40" onclick="this.select();"></p></td></tr>';
    echo '<tr><td class="row-title">緯度・経度</td><td><a href="https://www.google.co.jp/maps/@'.htmlspecialchars($sun->suninfo->lat).','.htmlspecialchars($sun->suninfo->lon).',17z?hl=ja" target="_blank">Googleマップで確認</a></td><td>'.htmlspecialchars($sun->suninfo->lat).'<br>'.htmlspecialchars($sun->suninfo->lon).'</td></tr>';
    echo '<tr><td class="row-title">日の出</td><td>[sunrise]</td><td>'.date('G:i', $sun->suninfo->sunrise).'</td></tr>';
    echo '<tr><td class="row-title">日の入り</td><td>[sunset]</td><td>'.date('G:i', $sun->suninfo->sunset).'</td></tr>';
    echo '<tr><td class="row-title">南中</td><td>[culmination]</td><td>'.date('G:i', $sun->suninfo->culmination).'</td></tr>';
    echo '</table>';
    echo '</div>';
  }
  
  if ( $status ) {
    $weathers = get_option( WEATHERAPI_PREFIX.'weatherdata');
    
    // 

    // 天気
    foreach ( $weathers as $key=>$var) {
      echo '<div class="card">';
      echo '<h3>予報エリアコード: '. $key.'</h3>';
      foreach ( $var as $index=>$item ) {
        echo '<table class="widefat striped">';
        echo '<tr><td colspan="3">';
        echo '<p>ショートコード: <input type="text" readonly value="[weatherapi areacode=&quot;'.$key.'&quot; item=&quot;'.$index.'&quot;]" size="40" onclick="this.select();"></p>';
        echo '</td></tr>';
        echo '<tr><td class="row-title">予報エリア名</td><td>[areaName]</td><td>'.htmlspecialchars($item->areaName).'</td></tr>';
        echo '<tr><td class="row-title">予報日時</td><td>[forecastDateTime]</td><td>'.date('Y-m-d G:i:s', $item->forecastDateTime).'</td></tr>';
        echo '<tr><td class="row-title">予報日時（日本語）</td><td>[forecastDateName]</td><td>'.htmlspecialchars($item->forecastDateName).'</td></tr>';
        echo '<tr><td class="row-title">天気</td><td>[weather]</td><td>'.htmlspecialchars($item->weather).'</td></tr>';
        echo '<tr><td class="row-title">天気テロップコード</td><td>[weatherCode]</td><td>'.htmlspecialchars($item->weatherCode).'</td></tr>';
        echo '<tr><td class="row-title">風に関する情報</td><td>[windDirection]</td><td>'.htmlspecialchars($item->windDirection).'</td></tr>';
        echo '<tr><td class="row-title">最高気温</td><td>[maxTemp]</td><td>'.htmlspecialchars($item->maxTemp).'</td></tr>';
        echo '<tr><td class="row-title">最低気温</td><td>[minTemp]</td><td>'.htmlspecialchars($item->minTemp).'</td></tr>';
        echo '<tr><td class="row-title">気温予測地点名</td><td>[stationName]</td><td>'.htmlspecialchars($item->stationName).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（0～6時）</td><td>[precipitation0_6]</td><td>'.htmlspecialchars($item->precipitation0_6).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（6～12時）</td><td>[precipitation6_12]</td><td>'.htmlspecialchars($item->precipitation6_12).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（12～18時）</td><td>[precipitation12_18]</td><td>'.htmlspecialchars($item->precipitation12_18).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（18～24時）</td><td>[precipitation18_24]</td><td>'.htmlspecialchars($item->precipitation18_24).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（午前）</td><td>[precipitation_am]</td><td>'.htmlspecialchars($item->precipitation_am).'</td></tr>';
        echo '<tr><td class="row-title">降水確率（午後）</td><td>[precipitation_pm]</td><td>'.htmlspecialchars($item->precipitation_pm).'</td></tr>';
        echo '</table>';
      }
      echo '</div>';
    }

  }
?>

<?php

}

//--------------------------------------------------------------------------
// データ更新が必要かチェックする関数
function wapi_check_update_necessity() {

  $lastattempt = get_option( WEATHERAPI_PREFIX.'lastattempt');
  $minduration = get_option( WEATHERAPI_PREFIX.'minduration');
  
  /// 更新判定
  // 最終試行から $minduration 分経っているときのみ
  if ( (time()-$lastattempt) > $minduration*60 ) {
    return true;
  } else {
    return false;
  }
}


//--------------------------------------------------------------------------
// データを取得して設定する関数
function wapi_update_data() {

  $t = time()+1;

  // JSON取得
  $status = false; // 正しく取得できたか

  $context = stream_context_create(array(
      'http' => array('ignore_errors' => true, 'timeout'=>10 )
  ));
  
  // 引数
  $key = get_option( WEATHERAPI_PREFIX.'key');
  $code = get_option( WEATHERAPI_PREFIX.'code');
  
  $params=[];
  $params['code'] = $code;
  $params['key'] = $key;
  $params['format'] = 'json';
  $url = WEATHERAPI_URL.http_build_query($params);
  $response = @file_get_contents( $url, false, $context);

  
  preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
  $status_code = $matches[1];
  
  // タイムアウトじゃない場合
  if ( count($http_response_header) >0 ) {
    // タイムアウトじゃない場合
    switch ($status_code) {
        case '200':
            // 200の場合
            $wdata = json_decode( $response );
            // ステータスチェック
            if ( $wdata->status ==='success' ) {
              $status = true;
              $weathers = wapi_reconstruct_data( $wdata );
              update_option( WEATHERAPI_PREFIX.'weatherdata', $weathers );
              update_option( WEATHERAPI_PREFIX.'lastupdate', $t );
              
              // ZEN CACHEを無効にする
              $_SERVER['ZENCACHE_ALLOWED'] = FALSE;
             }
            break;
        case '404':
            // 404の場合
            break;
        default:
            break;
    }
  }
  
  update_option( WEATHERAPI_PREFIX.'lastattempt', $t );
  update_option( WEATHERAPI_PREFIX.'status', $status );
  return $status;
}

//-------------------------------------------------------------------------------
// 取得したjson->オブジェクトデータを使いやすく解釈したオブジェクト配列を返す関数
function wapi_reconstruct_data( $p_wdata ) {
  //echo '<pre>';var_dump($p_wdata);echo '</pre>';

  // データ解析と再構成
  $weathers = []; // エリア天気保持
  
  // エリアデータ
  foreach ( $p_wdata->area as $adata ) {
    $reportDateTime = strtotime($p_wdata->reportDateTime); // 予報時刻
    $areaName = htmlspecialchars($adata->areaName); // 予報エリア名
    $areaCode = $adata->areaCode; // 予報エリアコード
    
    // そのエリアの予報をまとめる
    for ( $i=0;$i<count($adata->forecastDateTime);$i++ ) {
      $weathers[ $areaCode ][ $i ] = new WAPI_Weather( $reportDateTime, $areaName, $areaCode );
      $weathers[ $areaCode ][ $i ]->forecastDateTime = strtotime($adata->forecastDateTime[$i]);
      $weathers[ $areaCode ][ $i ]->forecastDateName = $adata->forecastDateName[$i];
      $weathers[ $areaCode ][ $i ]->weather = $adata->weather[$i];
      $weathers[ $areaCode ][ $i ]->weatherCode = $adata->weatherCode[$i];
      $weathers[ $areaCode ][ $i ]->windDirection = htmlspecialchars($adata->windDirection[$i]);
    }
    
    // 降水確率を統合
    for ( $i=0;$i<count($adata->precipitationDateTime);$i++ ) {
      // データがあてはまる場所を探し出す
      $precipitationDateTime = strtotime($adata->precipitationDateTime[$i]); // 予報対象日付時刻
      $precipitationDate = date('Ymd',$precipitationDateTime); // 予報対象日付（文字列）
      $precipitationHour = date('H',$precipitationDateTime); // 予報対象開始時刻（文字列） 

      // 該当捜索
      for( $j=0;$j<count($weathers[ $areaCode ]);$j++ ) {
        // 日付チェック
        $target_date = date('Ymd', $weathers[ $areaCode ][$j]->forecastDateTime); // 予報マスタ日付（文字列）

        if ( $precipitationDate === $target_date ) {
          // 時間帯に応じて値を設定
          switch ($precipitationHour) {
            case 0: // 0～6時
                $weathers[ $areaCode ][ $j ]->precipitation0_6 = $adata->precipitation[$i];
                break;
            case 6: // 6～12時
                $weathers[ $areaCode ][ $j ]->precipitation6_12 = $adata->precipitation[$i];
                break;
            case 12: // 12～18時
                $weathers[ $areaCode ][ $j ]->precipitation12_18 = $adata->precipitation[$i];
                break;
            case 18: // 18～24時
                $weathers[ $areaCode ][ $j ]->precipitation18_24 = $adata->precipitation[$i];
                break;
          }
        break;
        }
      }

      // 午前と午後の最大降水確率を選び出す
      if ( is_numeric( $weathers[ $areaCode ][ $j ]->precipitation0_6) && is_numeric( $weathers[ $areaCode ][ $j ]->precipitation6_12) ) {
        $weathers[ $areaCode ][ $j ]->precipitation_am = 
            ( $weathers[ $areaCode ][ $j ]->precipitation0_6 > $weathers[ $areaCode ][ $j ]->precipitation6_12 ) ? $weathers[ $areaCode ][ $j ]->precipitation0_6:$weathers[ $areaCode ][ $j ]->precipitation6_12;
      } else if (is_numeric( $weathers[ $areaCode ][ $j ]->precipitation6_12)) {
        $weathers[ $areaCode ][ $j ]->precipitation_am = $weathers[ $areaCode ][ $j ]->precipitation6_12;
      } else if (is_numeric( $weathers[ $areaCode ][ $j ]->precipitation0_6)) {
        $weathers[ $areaCode ][ $j ]->precipitation_am = $weathers[ $areaCode ][ $j ]->precipitation0_6;
      }
      
      if ( is_numeric( $weathers[ $areaCode ][ $j ]->precipitation12_18) && is_numeric( $weathers[ $areaCode ][ $j ]->precipitation18_24) ) {
        $weathers[ $areaCode ][ $j ]->precipitation_pm = 
            ( $weathers[ $areaCode ][ $j ]->precipitation12_18 > $weathers[ $areaCode ][ $j ]->precipitation18_24 ) ? $weathers[ $areaCode ][ $j ]->precipitation12_18:$weathers[ $areaCode ][ $j ]->precipitation18_24;
      } else if (is_numeric( $weathers[ $areaCode ][ $j ]->precipitation12_18)) {
        $weathers[ $areaCode ][ $j ]->precipitation_pm = $weathers[ $areaCode ][ $j ]->precipitation12_18;
      } else if (is_numeric( $weathers[ $areaCode ][ $j ]->precipitation18_24)) {
        $weathers[ $areaCode ][ $j ]->precipitation_pm = $weathers[ $areaCode ][ $j ]->precipitation18_24;
      }
      
    }
  }

  // 代表地点の気温データを統合
  foreach ( $p_wdata->temperatureStation as $tdata ) {

    // 代表地点なら
    if ( $tdata->representativePoint ) {

      $stationName = htmlspecialchars($tdata->stationName);
      $areaCodeBelong = $tdata->areaCodeBelong;
      $today_temp_max_day = $tdata->temperature[0];
      $today_temp_max = $tdata->temperature[1];
      $tomorrow_temp_min = $tdata->temperature[2];
      $tomorrow_temp_max = $tdata->temperature[3];

      // 今日の最高の判断
      if ( is_numeric( $today_temp_max_day ) && is_numeric( $today_temp_max )) {
        $today_temp_max = ( $today_temp_max_day > $today_temp_max ) ? $today_temp_max_day : $today_temp_max;
      }
      $weathers[ $areaCodeBelong ][ 0 ]->maxTemp = $today_temp_max; // 今日の最高
      $weathers[ $areaCodeBelong ][ 1 ]->minTemp = $tomorrow_temp_min; // 明日の最低
      $weathers[ $areaCodeBelong ][ 1 ]->maxTemp = $tomorrow_temp_max; // 明日の最高
      $weathers[ $areaCodeBelong ][ 0 ]->stationName = $stationName; // 気温予報地点名
      $weathers[ $areaCodeBelong ][ 1 ]->stationName = $stationName; // 気温予報地点名
      
    }
  }
  
  return $weathers;
}


//--------------------------------------------------------------------------------
// フォーマット済のテキストを各オブジェクトにセットする関数
function wapi_set_formatted() {
  
  $weathers = get_option( WEATHERAPI_PREFIX.'weatherdata');
  $formats = get_option( WEATHERAPI_PREFIX.'formats');
  $novalue = get_option( WEATHERAPI_PREFIX.'novalue');
  
  foreach( $weathers as $key=>$area_weathers ) {
    foreach ( $area_weathers as $item=>$weather ) {
      
      $weather->format_weather( $formats[$item], $novalue );
    }
  }
  update_option( WEATHERAPI_PREFIX.'weatherdata', $weathers );
}


//--------------------------------------------------------------------------------
// ショートコード
add_shortcode('weatherapi', 'weatherapi_get');
function weatherapi_get($params) {

  extract(
    shortcode_atts(
      array(
        'areacode' => 'val1',
        'item' => 'val2'
      ),
      $params
    )
  );

  // 再取得
  if ( wapi_check_update_necessity() ) { // 最短取得間隔を過ぎているか
    if ( wapi_update_data() ) { // 正しく取得できたか
      wapi_set_formatted(); // フォーマットしたテキスト保存
    }
  }
  
  // 取得状況チェック
  $status = get_option( WEATHERAPI_PREFIX.'status');  
  if ( $status == FALSE ) { return ''; }
  
  $weatherdata = get_option( WEATHERAPI_PREFIX.'weatherdata');
  
  // リクエスト対象の有無チェック
  if ( !isset( $weatherdata[ $areacode ][ $item ]) ) { return ''; }
  
  return $weatherdata[ $areacode ][ $item ]->formatted;

}

add_shortcode('weatherapi_suninfo', 'weatherapi_suninfo_get');
function weatherapi_suninfo_get() {
  $key = get_option( WEATHERAPI_PREFIX.'key');
  $sun = new WAPI_sun( $key );
  return $sun->output();
}
