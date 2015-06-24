<?php

class Localexpress_Shipping_Helper_Data extends Mage_Core_Helper_Abstract
{
  const NAME = "localexpress_customrate";
  const SERVER = "localexpress-shipping(0.2.4)";
  const SERVICE_NAME = "localexpress_service";
  const DEBUG = false;

  private $_fields;
  private $_last_cookies;

  public function __construct()
  {
    $this->_fields = Mage::getStoreConfig('carriers/' . self::NAME);
    $this->_last_cookies = null;
  }

  public static function log($var)
  {
    if(is_string($var))
      $msg = $var;
    else
      $msg = print_r($var, true);
    Mage::log($msg, null, 'localexpress_shipping.log', true);
  }

  public function getFields()
  {
    return $this->_fields;
  }

  public function isActive()
  {
    return ($this->_fields['active']);
  }

  public function isProcessable()
  {
    if(!$this->isActive())
      return false;
    $api_key = $this->_fields['api_key'];
    if(strlen($api_key) == 0)
      return false;
    return true;
  }

  public function canShipOrder($order)
  {
    $status = $order->getStatus();
    $shipping_method = $order->getShippingMethod();
    // shipping allowed logic
    return ($status == "processing" || $status == "complete") && 
      $this->validShippingMethod($shipping_method) && $order->canShip();
  }

  public function getShippingMethodId($shipping_method)
  {
    $start_with = self::NAME . "_" . self::SERVICE_NAME . "_";
    return substr($shipping_method, strlen($start_with));
  }

  public function validShippingMethod($shipping_method)
  {
    $start_with = self::NAME . "_" . self::SERVICE_NAME . "_";
    return (!strncmp($shipping_method, $start_with, strlen($start_with)));
  }

  public function hasOriginAddress()
  {
    $f = $this->_fields;
    if(!array_key_exists("origin_store_name", $f) || !array_key_exists("origin_country", $f) || 
      !array_key_exists("origin_street", $f) || !array_key_exists("origin_subthoroughfare", $f) || 
      !array_key_exists("origin_zip", $f) || !array_key_exists("origin_city", $f))
      return false;
    return !($f["origin_store_name"] == "" || $f["origin_country"] == "" || 
      $f["origin_street"] == "" || $f["origin_subthoroughfare"] == "" || $f["origin_zip"] == "" || $f["origin_city"] == "");
  }
  public static function catageryOnly()
  {
    $fields = Mage::getStoreConfig('carriers/' . self::NAME);
    return empty($fields['category_only']) ? false : explode(",",$fields['category_only']);
  }
  public static function attributesOnly()
  {
    $fields = Mage::getStoreConfig('carriers/' . self::NAME);
    return empty($fields['attributes_only']) ? false : explode(",",$fields['attributes_only']);
  }
  public static function getAddressOrigin()
  {
    $fields = Mage::getStoreConfig('carriers/' . self::NAME);
    $country_iso2 = $fields["origin_country"];
    $country = Mage::getModel('directory/country')->loadByCode($country_iso2);
    return Localexpress_Shipping_Helper_Data::buildAddress($country_iso2, $country->getName(), $fields["origin_street"], 
      $fields["origin_zip"], $fields["origin_store_name"], $fields["origin_city"], $fields['origin_subthoroughfare']);
  }

  public static function getOrderTotals($order,$categories=false,$attributes=false)
  {
    $ret = array("price" => 0.0, "qty" => 0, "weight" => 0);
    if(is_null($order)) return $ret;
    // product details
    foreach($order->getAllItems() as $item)
    {
      $qty = $item->getQty();
      if($categories || $attributes){
         $product = Mage::getModel('catalog/product')->load($item->getProduct()->getId());
      }
      
      if($attributes){
         $pa = array();
         $productAttributes = $product->getAttributes();
         foreach ($productAttributes as $productAtribute) 
         {
           $pa[$productAtribute->getName()] = $productAtribute->getFrontend()->getValue($product);
         }
         foreach($attributes as $attribute){
            $att = explode("=",$attribute);
            if($pa[$att[0]] != $att[1]){
               $ret['notPossible'] = true;
               return $ret;
            }  
         }

      }

      if($categories){
         $cats = $product->getCategoryIds();
         foreach ($cats as $category_id) {
             $_cat = Mage::getModel('catalog/category')->load($category_id) ;
             if (!in_array($_cat->getName(),$categories)){
               $ret['notPossible'] = true;
               return $ret;
             }
         } 
      }

      //$product = Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())->load($productId);
      $ret["price"] += $item->getPriceInclTax() * $qty;
      $ret["weight"] += $item->getWeight() * $qty;
      $ret["qty"] += $qty;
    }
    $ret["weight"] = ceil($ret["weight"]);
    $ret["qty"] = ceil($ret["qty"]);
    return $ret;
  }

  public static function buildDimensions($length, $width, $height)
  {
    return array("length" => $length, "width" => $width, "height" => $height);
  }

  public function buildAddress($country_iso_2, $country_name, $street, $postal_code, $recipient, $city, $subThoroughfare=false,$long=0.0,$lat=0.0)
  {
    if(!$subThoroughfare)
    {

    }
    if(!$subThoroughfare)
    {
       $address_formatted = self::shipmentGeo($country_iso_2, $country_name, $street, $postal_code, $recipient, $city);  
       $street = $address_formatted['road'];
       self::log($address_formatted);
       $subThoroughfare    = $address_formatted['house_number'];
       $address_formatted  = "$street".(!$subThoroughfare ? "" : " $subThoroughfare")."\n$postal_code $city\n$country_name";
       
    } 
    else 
    {
      $address_formatted = "$street".(!$subThoroughfare ? "" : " $subThoroughfare")."\n$postal_code $city\n$country_name";
    }
    
    // unable to provide: altitude, course, latitude, longitude, speed, sub_thoroughfare, throughfare
    $address = array(
        "email" => "info@example.com",
        "altitude" => 0,
        "course" => 0,
        "latitude" => $lat,
        "longitude" => $long,
        "speed" => 0,
        "iso_country_code" => $country_iso_2,
        "postal_code" => $postal_code,
        "administrative_area_code" => null,
        "formatted_address" => $address_formatted,
        "sub_administrative_area" => $recipient,
        "administrative_area" => null,
        "locality" => $city,
        "subLocality" => null,
        "country" => $country_name,
        "sub_thoroughfare" => $subThoroughfare,
        "thoroughfare" => $street);
    return $address;
  }

  public function shipmentGeo($country_iso_2, $country_name, $street, $postal_code, $recipient, $city){
      $return = self::addressSplitter($country_iso_2,$street,$postal_code);
      self::log("buildAddress  based on API\n\n");
      self::log($return["result"]);

      if(!empty($return["result"])){

         $result = json_encode($return["result"]);
         $return['house_number'] = $result->subThoroughfare;
         $return['road']         = $result->thoroughfare;
      }
      if(empty($return['road']) && empty($return['house_number'])){
         self::log("buildAddress  based on nomi\n\n");
         $return = self::geoAddress($country_iso_2, $country_name, $street, $postal_code, $recipient, $city);
         if(!empty($return['road']) && empty($return['house_number']))
            $return['house_number'] = trim(str_replace($return['road'],"",$street));
      }
      return $return;
  }

   public function shipmentQuote($quantity, $weight, $price, $currency, array $dimensions, array $origin, array $destination, $comment, $insurance,$human_id =false)
  {
    return $this->shipmentHelper("shipment_quotes", $quantity, $weight, $price, $currency, $dimensions, $origin, $destination, $comment, $insurance,"shipment_quote",$human_id);
  }

  public function shipment($quantity, $weight, $price, $currency, array $dimensions, array $origin, array $destination, $comment, $insurance,$human_id=false)
  {
    return $this->shipmentHelper("shipments", $quantity, $weight, $price, $currency, $dimensions, $origin, $destination, $comment, $insurance,"shipment",$human_id);
  }
  public function shipmentAvailable($country_iso_2, $address,$zip)
  {
      self::log("shipmentAvailable  \n\n");
      self::log($country_iso_2." ".$address." ".$zip);

      $api_boxture   = $this->addressSplitter($country_iso_2,$address,$zip);
      self::log("shipmentAvailable result:  \n");
   
      self::log($api_boxture['result']);

      $json_boxture  = json_decode($api_boxture['result'],true);
   
      if(empty($json_boxture['lat']))
         return false;
      
      $fields     = Mage::getStoreConfig('carriers/' . self::NAME);
      $ch         = curl_init($fields["server"]."/available_features?latitude=".$json_boxture['lat']."&purpose=dropoff&longitude=".$json_boxture['lon']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
      curl_setopt($ch, CURLOPT_HTTPHEADER,array(
             'Content-Type: application/json',
             'Accept-Language: en',
             'Connection: Keep-Alive',
             'Authorization: Boxture '.$fields['api_key']));
      $result = curl_exec($ch);
      $info = curl_getinfo($ch);
      curl_close($ch);
      if($info['http_code']=='404' || $info['http_code']=='422')
         return false;
      else
         return true;
  }

  public static function getItemQtys(Mage_Sales_Model_Order $order)
  {
      $qty = array();
      foreach ($order->getAllItems() as $eachItem) 
      {
          if ($eachItem->getParentItemId()) 
          {
              $qty[$eachItem->getParentItemId()] = $eachItem->getQtyOrdered();
          } 
          else 
          {
              $qty[$eachItem->getId()] = $eachItem->getQtyOrdered();
          }
      }
      return $qty;
  }
  
  public function addressSplitter($country_iso_2,$address,$postal_code,$debug=false){
      $fields  = Mage::getStoreConfig('carriers/' . self::NAME);
      $ch      = curl_init($fields["address_server"]."/convert_address.php");     

      $post = json_encode(array("postal_code" => $postal_code,"address"=> $address,"iso_country_code"=> $country_iso_2));
      
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      self::log("server: ".$fields["address_server"]."/convert_address.php");
      self::log("post: ".$post);
      self::log(array("postal_code" => $postal_code,"address"=> $address,"iso_country_code"=> $country_iso_2));

      $result = curl_exec($ch);
      $info = curl_getinfo($ch);
      self::log($result);
      return array("info" => $info,"result" => $result);
  }

  public function geoAddress($country_iso_2, $country_name, $street, $postal_code, $recipient, $city)
  { 
    $fields = Mage::getStoreConfig('carriers/' . self::NAME);
    $geo    = $fields["geo_server"];
    if($geo)
    {
       $xml    = simplexml_load_file($geo."/search/$street,$postal_code,$city $country_name?format=xml&polygon=1&addressdetails=1");
       if(empty($xml->place)){         
         $xml    = simplexml_load_file($geo."/search/$street,$city $country_name?format=xml&polygon=1&addressdetails=1");
       }
       return (array)$xml->place;
    }
    return false;
    
  }

  // create shipment
  private function shipmentHelper($target, $quantity, $weight, $price, $currency, array $dimensions, array $origin, array $destination, $comment, $insurance,$arr_name='shipment',$human_id)
  {
    $apikey = $this->_fields["api_key"];
    $url    = $this->_fields["server"];

    // unable to provide value
    $json[$arr_name] = array(
      "human_id"        => $human_id,
      "quantity"        => $quantity,
      "comments"        => $comment,
      "value"           => (int)ceil($price),
      "price"           => $price,
      "weight"          => $weight,
      "insurance"       => $insurance,
      "dimensions"      => $dimensions,
      "originates_from" => "plugin/magento",
      "origin"          => $origin,
      "destination"     => $destination);
    $json = json_encode($json);
    $shipment_quote = self::request($url . "/" . $target, $json, null, $apikey);       
    
    self::log($shipment_quote);

    // invalidate cookies
    $this->_last_cookies = null;
    
    return $shipment_quote;
  }

  // send request to url with payload and cookie auth
  private static function request($url, $data, $cookies = null, $apikey = null)
  {
   self::log("\n\n\n\nREQUEST ".$url."\n");
    
    $ret = array();
    // prepare curl
    $c = curl_init(); 
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 1); 
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
    if(self::DEBUG) $ret["request"]["data"] = array("raw" => $data, "json" => json_decode($data));
//    curl_setopt($c, CURLOPT_HEADER, 1);
    if (self::DEBUG) curl_setopt($c, CURLOPT_VERBOSE, 1);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 0);
    if($cookies != null)
      curl_setopt($c, CURLOPT_COOKIE, $cookies);
    // default headers
    $headers = array("Content-Type: application/json", "Content-length: ".strlen($data), 
        "Magento: ".self::SERVER,
        "Accept: application/vnd.com.boxture.v2+json");
    // auth (optional)
    if($apikey != null) 
    {
      $headers[] = "Authorization: Boxture " . $apikey;
    }
    $ret["request"]["headers"] = $headers;
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers); 
    self::log(json_decode($data));
    // handle request
    $info = null;
    $ret["raw"] = curl_exec($c);

    if(!curl_errno($c))
    {
      $ret["info"] = curl_getinfo($c);
      // parsing body on json return
      if(is_int(strpos($ret["info"]["content_type"], "application/json")))
      {
        //list(,$body) = explode("\r\n\r\n", $ret["raw"], 2);
        $body = $ret['raw'];
        $ret["body"] = json_decode($body);
      }
    }
    else {
      $ret["error"] = curl_error($c);
   }
    curl_close($c);
    return $ret;
  }

}
