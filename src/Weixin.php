<?php

namespace QKPHP\SNS;

use \QKPHP\Common\Utils\Http;

class Weixin {

  private $sessionAccessToken;
  private $sessionAccessTokenExpire = 0;
  private $accessToken;
  private $accessTokenExpire = 0;
  private $jsTicket;
  private $jsTicketExire = 0;

  public $appId;
  public $appSecret;
  public $mchId;

  public $openId;
  public $unionId;
  public $user;

  private $authApi = 'https://open.weixin.qq.com/connect/oauth2/authorize';
  private $authAccessTokenApi = 'https://api.weixin.qq.com/sns/oauth2/access_token';
  private $userInfoApi = 'https://api.weixin.qq.com/sns/userinfo';
  private $jsTicketApi = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';

  private $accessTokenApi = 'https://api.weixin.qq.com/cgi-bin/token';

  private $unifiedApi = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

  private static $DEFAULT_JSAPILIST = array(
    'scanQRCode', 'chooseWXPay', 'closeWindow',
    'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ', 'onMenuShareQZone',
    'getNetworkType', 'openLocation', 'getLocation',
    'showOptionMenu', 'showMenuItems', 'showAllNonBaseMenuItem',
    'hideOptionMenu', 'hideMenuItems', 'hideAllNonBaseMenuItem'
  );
  
  public function __construct ($appId, $appSecret, array $config=null) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
    if (!empty($config)) {
      foreach ($config as $k=>$v) {
        $this->$k = $v;
      }
    }
  }

  public function toAuth ($redirect, $userScope=true, $state=null) {
    if (empty($state)) {
      $state = time();
    }
    $scope = 'snsapi_base';
    if ($userScope) {
    	$scope = 'snsapi_userinfo';
    }

    return $this->authApi .
      "?appid=" . $this->appId .
      "&redirect_uri=" . urlencode($redirect) .
      "&response_type=code&scope=$scope&state=$state#wechat_redirect";
  }

  public function getSessionAccessTokenByAuth ($code) {
    $querys = array(
      'appid'  => $this->appId,
      'secret' => $this->appSecret,
      'code'   => $code,
      'grant_type' => 'authorization_code'
    );
    list($status, $content) = Http::get($this->authAccessTokenApi, $querys);
    if (empty($content)) {
      return null;
    }
    $content = json_decode($content, true);
    if (empty($content) || !isset($content['access_token'])) {
      return null;
    }
    $this->setSessionAccessToken($content['access_token']);
    $this->sessionAccessTokenExpire = $content['expires_in'];
    $this->openId = $content['openid'];
    return $this->sessionAccessToken;
  }

  public function getUserInfoByAuth () {
    $querys = array( 
      'access_token' => $this->sessionAccessToken,
      'openid'       => $this->openId,
      'lang'         => 'zh_CN'
    );
    list($status, $content) = Http::get($this->userInfoApi, $querys);
    if (empty($content)) {
      return null;
    }
    $user = json_decode($content, true);
    if (empty ($user) || !isset($user['unionid'])) {
      return null;
    }
    $this->unionId = $user['unionid'];
    $this->user = $user;
    return $user;
  }

  public function setSessionAccessToken ($accessToken) {
    $this->sessionAccessToken = $accessToken;
  }

  public function setAccessToken ($accessToken) {
    $this->accessToken = $accessToken;
  }

  public function setJSTicket ($jsTicket) {
    $this->jsTicket = $jsTicket;
  }

  public function getSessionAccessToken () {
    return $this->sessionAccessToken;
  }

  public function getAccessToken () {
    if (!empty($this->accessToken)) {
      return $this->accessToken;
    }
    $querys = array( 
      'appid'      => $this->appId,
      'secret'     => $this->appSecret,
      'grant_type' => 'client_credential'
    );
    list($status, $content) = Http::get($this->accessTokenApi, $querys);
    if (empty($content)) {
      return null;
    }
    $content = json_decode($content, true);
    if (empty($content) || !isset($content['access_token'])) {
      return null;
    }
    $this->accessTokenExpire = $content['expires_in'];
    $this->setAccessToken($content['access_token']);
    return $this->accessToken;
  }

  public function getJSTicket () {
    if (!empty($this->jsTicket)) {
      return $this->jsTicket;
    }
    $querys = array( 
      'access_token' => $this->getAccessToken(),
      'type'         => 'jsapi'
    );
    list($status, $content) = Http::get($this->jsTicketApi, $querys);
    if (empty($content)) {
      return null;
    }
    $content = json_decode($content, true);
    if (empty($content) || $content['errcode'] != 0) {
      return null;
    }
    $this->jsTicketExire = $content['expires_in'];
    $this->setJSTicket($content['ticket']);
    return $this->jsTicket;
  }

  public function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  public function getSignature(array $params, $secret) {
    ksort($params, SORT_REGULAR);
    $params0 = array();
    foreach($params as $key=>$value) {
      if (empty($value)) {
        continue;
      }
      $params0[] = "$key=$value";
    }
    return strtoupper(md5(implode('&', $params0) . "&key=$secret"));
  }

  public function jsConfig ($url, array $jsApiList=null) {
    $jsTicket = $this->getJSTicket();
    $nonceStr = $this->createNonceStr();
    $timestamp = time();
    $signature = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "signature" => sha1("jsapi_ticket=$jsTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url"),
      "jsApiList" => self::$DEFAULT_JSAPILIST
    );
    return 'wx.config('.json_encode($signature).')';
  }

}
