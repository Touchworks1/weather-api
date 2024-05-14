<?php 
//date_default_timezone_set('Asia/Tokyo');
define( 'WEATHERAPI_PREFIX', "weatherapi_" );
define( 'WEATHERAPI_URL', "http://api.yumake.jp/1.0/forecastPref.php?");
define( 'WEATHERAPI_SUN_URL', "http://api.yumake.jp/1.0/sun.php?");


// 日の出日の入り情報クラス
class WAPI_sun_info {
  public $status = false; // true=有効 error=エラーまたは未取得
  public $lat = 34.646111111;
  public $lon = 135.004166666;
  public $date = 0;
  public $sunrise = 0;
  public $sunset = 0;
  public $culmination = 0;
  public $last_attempt = 0; // 最終取得試行時刻
}

// 日の出日の入り情報管理クラス
class WAPI_sun {

  const APIURL = "http://api.yumake.jp/1.0/sun.php?"; // APIURL
  public $suninfo; // 情報保持
  private $apikey; 
  public $sun_date_format = 'G時i分';
  public $sun_format = '<p>日の出: [sunrise], 日の入り: [sunset], 南中: [culmination]</p>';
  
  // キャッシュデータを読み込み
  // 再取得条件
  // 保持データが今日じゃない

  function __construct( $p_apikey ) {
    $this->apikey =$p_apikey;
    $this->suninfo = new WAPI_sun_info();

    // 保存データ取得試行
    $temp = get_option( WEATHERAPI_PREFIX.'suninfo' );
    if ( $temp !== false ) {
      $this->suninfo = $temp;
    }
    $temp = get_option( WEATHERAPI_PREFIX.'sun_date_format' );
    if ( $temp !== false ) {
      $this->sun_date_format = $temp;
    }
    $temp = get_option( WEATHERAPI_PREFIX.'sun_format' );
    if ( $temp !== false ) {
      $this->sun_format = $temp;
    }

    // 保持データが今日じゃなければデータ取得
    if ( date('Ymd') !== date('Ymd', $this->suninfo->date )) {
      $this->get_json();
    }
  }
  
  // 保存
  public function save() {
    update_option( WEATHERAPI_PREFIX.'suninfo', $this->suninfo );
    update_option( WEATHERAPI_PREFIX.'sun_date_format', $this->sun_date_format );
    update_option( WEATHERAPI_PREFIX.'sun_format', $this->sun_format );
  }
  
  // JSON取得
  public function get_json() {
    $t = time()+1;
    
    $status = false;

    $context = stream_context_create(array(
        'http' => array('ignore_errors' => true, 'timeout'=>10 )
    ));
    
    // API引数
    $code = get_option( WEATHERAPI_PREFIX.'code');
    
    $params=[];
    $params['lat'] = $this->suninfo->lat;
    $params['lon'] = $this->suninfo->lon;
    $params['date'] = date('Ymd');
    $params['key'] = $this->apikey;
    $params['format'] = 'json';
    $url = self::APIURL.http_build_query($params);
    $response = @file_get_contents( $url, false, $context);
    
    preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
    $status_code = $matches[1];
    
    // タイムアウトじゃない場合
    if ( count($http_response_header) >0 ) {
      // タイムアウトじゃない場合
      switch ($status_code) {
          case '200':
              // 200の場合
              $sdata = json_decode( $response );
              // ステータスチェック
              if ( $sdata->status ==='success' ) {
                $status = true;
                $this->suninfo->status = $status;
                $this->suninfo->date = strtotime($sdata->date);
                $this->suninfo->sunrise  = strtotime( $sdata->sunrise );
                $this->suninfo->sunset  = strtotime( $sdata->sunset );
                $this->suninfo->culmination  = strtotime( $sdata->culmination);
                $this->suninfo->last_attempt = $t;
               }
              break;
          case '404':
              // 404の場合
              break;
          default:
              break;
      }
    }
    
    $this->save();
    return $status;
  }
  
  public function output() {

    $sunrise      = date( $this->sun_date_format, $this->suninfo->sunrise );
    $sunset       = date( $this->sun_date_format, $this->suninfo->sunset );
    $culmination  = date( $this->sun_date_format, $this->suninfo->culmination );

    $search = [ '[sunrise]','[sunset]','[culmination]' ];
    $replace = [ $sunrise, $sunset, $culmination  ];
    $output = str_replace( $search, $replace, $this->sun_format );

    return $output;
  }
}



//-------------------------------------------------------------------------------------
// 天気予報の一つのクラス
class WAPI_Weather {
  public $reportDateTime;
  public $areaName='';
  public $areaCode='';
  
  public $forecastDateTime ='';
  public $forecastDateName ='';
  public $weather ='';
  public $weatherCode ='';
  public $windDirection ='';

  public $maxTemp =''; //代表地点の最高気温
  public $minTemp =''; //代表地点の最低気温
  public $stationName = ''; // 代表地点名

  public $precipitation0_6 = ''; // 0-6時の降水確率
  public $precipitation6_12 = ''; // 6-12時の降水確率
  public $precipitation12_18 = ''; // 12-18時の降水確率
  public $precipitation18_24 = ''; // 18-24時の降水確率
  
  public $precipitation_am = ''; // 午前中の最大降水確率
  public $precipitation_pm = ''; // 午前中の最大降水確率
  
  
  public $formatted = ''; // フォーマット済テキスト
  
  function __construct( $p_reportDateTime, $p_areaName, $p_areaCode ) {
    $this->reportDateTime = $p_reportDateTime;
    $this->areaName = $p_areaName;
    $this->areaCode = $p_areaCode;
  }
  
  // 出力をフォーマットする
  // $p_format -> 出力フォーマット
  // $p_novalue -> 値の無いときのテキスト
  function format_weather( $p_format, $p_novalue ) {
    $areaName           = htmlspecialchars( $this->areaName );
    $forecastDateTime   = $this->forecastDateTime;
    $forecastDateName   = htmlspecialchars( $this->forecastDateName );
    $weather            = htmlspecialchars( $this->weather );
    $weatherCode        = $this->weatherCode;
    $windDirection      = htmlspecialchars( $this->windDirection );
    $maxTemp            = $this->check_novalue($this->maxTemp, $p_novalue );
    $minTemp            = $this->check_novalue($this->minTemp, $p_novalue );
    $stationName        = htmlspecialchars( $this->stationName );
    $precipitation0_6   = $this->check_novalue($this->precipitation0_6,$p_novalue );
    $precipitation6_12  = $this->check_novalue($this->precipitation6_12,$p_novalue );
    $precipitation12_18 = $this->check_novalue($this->precipitation12_18,$p_novalue );
    $precipitation18_24 = $this->check_novalue($this->precipitation18_24,$nop_novaluevalue );
    $precipitation_am   = $this->check_novalue($this->precipitation_am,$p_novalue );
    $precipitation_pm   = $this->check_novalue($this->precipitation_am,$p_novalue );
    
    $search = [ '[areaName]','[forecastDateTime]','[forecastDateName]','[weather]','[weatherCode]',
              '[windDirection]','[maxTemp]','[minTemp]','[stationName]',
              '[precipitation0_6]','[precipitation6_12]','[precipitation12_18]','[precipitation18_24]',
              '[precipitation_am]','[precipitation_pm]'
     ];
    $replace = [ $areaName, $forecastDateTime, $forecastDateName, $weather, $weatherCode,
                 $windDirection, $maxTemp, $minTemp, $stationName,
                 $precipitation0_6, $precipitation6_12, $precipitation12_18, $precipitation18_24, 
                 $precipitation_am, $precipitation_pm ];
    
    $this->formatted = str_replace( $search, $replace, $p_format );

  }
  
  private function check_novalue( $target, $replace ) {
  if ( $target === '' ) { return $replace; } else { return $target; }
}
}

// 最短取得間隔
$GLOBALS['wapi_min_durations'] = [
  1,3,5,10,15,30,45,60
];

//地域コード
$GLOBALS['wapi_region_codes'] =
  [ '宗谷地方'=>'011',
    '上川・留萌地方'=>'012',
    '網走・北見・紋別地方'=>'013',
    '釧路・根室・十勝地方'=>'014',
    '胆振・日高地方'=>'015',
    '石狩・空知・後志地方'=>'016',
    '青森県'=>'02',
    '岩手県'=>'03',
    '宮城県'=>'04',
    '秋田県'=>'05',
    '山形県'=>'06',
    '福島県'=>'07',
    '茨城県'=>'08',
    '栃木県'=>'09',
    '群馬県'=>'10',
    '埼玉県'=>'11',
    '千葉県'=>'12',
    '東京都'=>'13',
    '神奈川県'=>'14',
    '新潟県'=>'15',
    '富山県'=>'16',
    '石川県'=>'17',
    '福井県'=>'18',
    '山梨県'=>'19',
    '長野県'=>'20',
    '岐阜県'=>'21',
    '静岡県'=>'22',
    '愛知県'=>'23',
    '三重県'=>'24',
    '滋賀県'=>'25',
    '京都府'=>'26',
    '大阪府'=>'27',
    '兵庫県'=>'28',
    '奈良県'=>'29',
    '和歌山県'=>'30',
    '鳥取県'=>'31',
    '島根県'=>'32',
    '岡山県'=>'33',
    '広島県'=>'34',
    '山口県'=>'35',
    '徳島県'=>'36',
    '香川県'=>'37',
    '愛媛県'=>'38',
    '高知県'=>'39',
    '福岡県'=>'40',
    '佐賀県'=>'41',
    '長崎県'=>'42',
    '熊本県'=>'43',
    '大分県'=>'44',
    '宮崎県'=>'45',
    '鹿児島県'=>'46',
    '沖縄県'=>'47'
  ]
?>
