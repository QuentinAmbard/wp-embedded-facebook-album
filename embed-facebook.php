<?php

require('facebook-php/autoload.php');
use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\Entities\AccessToken;


include_once('json.php');
define('SOHAIL_EMBED_FACEBOOK_URL', get_option( 'siteurl' ). '/wp-content/plugins/embed-facebook');

add_action('wp_head', 'sohail_embed_facebook_head');
function sohail_embed_facebook_head()
{
	echo '<script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>';
	echo '<script src="'.SOHAIL_EMBED_FACEBOOK_URL.'/masonry.js"  type="text/javascript"></script>';
	echo '<link rel="stylesheet" href="'.SOHAIL_EMBED_FACEBOOK_URL.'/magnific-popup.css"> ';
	echo '<script src="'.SOHAIL_EMBED_FACEBOOK_URL.'/jquery.magnific-popup.js"></script>';

}    

add_filter('the_content', 'sohail_embed_facebook');
function sohail_embed_facebook($the_content)
{
	return preg_replace_callback("/<p>(http|https):\/\/www\.facebook\.com\/media\/set\/([^<\s]*)<\/p>/", "sohail_do_embed", $the_content);
}

function sohail_do_embed($query)
{
  session_start();
  $query = explode('=', $query[2]);
  $query = explode('.', $query[1]);
  $album_id = $query[1];
 

  FacebookSession::setDefaultApplication('XXXXXXXXXXXXXX6', '2XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX7');
  $tokenFileName = "./fb-token.txt";
  $result = "";
  $token = null;
  if (file_exists($tokenFileName)) {
    $token = file_get_contents($tokenFileName);
    $lastTokenRefresh = time() - filemtime($tokenFileName);

    if($lastTokenRefresh>60*60*24*7){
      $longLivedAccessToken = new AccessToken($token);
      try {
        // Get a code from a long-lived access token
        $code = AccessToken::getCodeFromAccessToken($longLivedAccessToken);
      } catch(FacebookSDKException $e) {
        $result = 'Error getting code: ' . $e->getMessage();
        unlink($tokenFileName);
      }

      try {
        // Get a new long-lived access token from the code
        $token = AccessToken::getAccessTokenFromCode($code);
        file_put_contents($tokenFileName, $token);
      } catch(FacebookSDKException $e) {
        $result = 'Error getting a new long-lived access token: ' . $e->getMessage();
        unlink($tokenFileName);
      }
    }
  }

  if($token == null) {
    global $wp;
    $current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );

    $helper = new FacebookRedirectLoginHelper($current_url);
    $permissions = array("scope" =>'user_photos');

    try {
        $session = $helper->getSessionFromRedirect();
    } catch(FacebookSDKException $e) {
        $session = null;
    }

    if ($session) {
      $accessToken = $session->getAccessToken();
      $token = $accessToken->extend();

      file_put_contents($tokenFileName, $token);

    } else {
      $result = '<a href="' . $helper->getLoginUrl($permissions) . '">Relier le compte facebook au blog</a>';
    }
  } 

  if($token != null){

    try{
      $session = new FacebookSession($token);
      $request = new FacebookRequest($session,'GET','/'.$album_id.'/photos');
      } catch(FacebookRequestException $e) {
      $result = 'Error getting code: ' . $e->getMessage();
        unlink($tokenFileName);
    }
    $response = $request->execute();
    $graphObject = $response->getGraphObject()->getProperty('data')->asArray();

    $border = 1;
    $result .= '<style type="text/css">
                .grid {
                  background: #EEE;
                  max-width: 1183px;
                  width: 1183px;
                }

                /* clearfix */
                .grid:after {
                  content: \'\';
                  display: block;
                  clear: both;
                }

                .grid-item {
                  border: '.$border.'px solid white;
                  float: left;
                }
                </style>';

    $result .= '<div id="grid">';
    $small = 4;

    $height = array(5 => 113, 6 => 226+$border*2);
    $width = array(5 => 169, 6 => 338+$border*2);

    $i = 0;
    foreach($graphObject as $photos)    
    {
      $i++;
      $image = $photos->images[3];
      $bigImage = $photos->images[1];
      $size = 6;
      // $rand = rand(0, 100);
      // if($small == 0 && $rand<50){
      //   $small = 3;
      // }
    $image = $photos->images[3];
    //Peut arriver sur certain formats
    if(!$image){
        $image = $photos->images[1];
    }
    //On sait jamais ca coute rien
    if(!$image){
        $image = $photos->images[0];
    }


    if($i%7 == 0){
       $small = 4;
    }
    if($small >0){
      $size = 5;
      $small -- ;
    }
    if($image->height>$image->width){
        //$image = $photos->images[3];
        if($size == 5){
          $size = 6;
          $small ++;
        }
        $h = $height[$size];
        $w = $width[$size]/2-1;
    } else {
        $h = $height[$size];
        $w = $width[$size];
    }
    //Panorama : full screen.
    if($image->width > $image->height*2){
      $w = $w*2;
    }

    //print_r($image);
    $result .= '<div class="grid-item grid-item-width'.$size.'">
                  <a class="img-gallery" href="'.$photos->images[0]->source.'">
                    <img src='.$image->source.' style="display:block;height:'.$h.'px;width:'.$w.'px"/>
                  </a>
                </div>';
  }
  $result .= '</div>';

    $result .= "
    <script type='text/javascript'>
      $('#grid').masonry({itemSelector: '.grid-item',columnWidth: ".(169+$border*2)."});
      $('.img-gallery').magnificPopup({
        type: 'image',
        gallery:{
          enabled:true
        }
      });
    </script>";
  }

  return $result;




}
