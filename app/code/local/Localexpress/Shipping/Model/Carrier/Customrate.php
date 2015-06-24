<?php

class Localexpress_Shipping_Model_Carrier_Customrate extends Mage_Shipping_Model_Carrier_Abstract
  implements Mage_Shipping_Model_Carrier_Interface
{
  protected $_code = Localexpress_Shipping_Helper_Data::NAME;
  protected $_isFixed = false;
  
  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
  {
    try
    {
      $helper = Mage::helper('localexpress_shipping');
      $fields = $helper->getFields();
      // skip if not processable
      if(!$helper->isProcessable())
      {
        return false;
      }
      // return 
      $result = Mage::getModel('shipping/rate_result');
      $human_id = Mage::getSingleton('checkout/session')->getData('localexpress_shipping_human_id');
      // collect order information
      $quote = Mage::getSingleton('checkout/session')->getQuote();
      $firstname = $quote->getShippingAddress()->getFirstname();
      $lastname = $quote->getShippingAddress()->getLastname();
      $address = $request->getDestStreet();
      $city = $request->getDestCity();
      $zip = $request->getDestPostcode();
      $country_iso639_2 = $request->getDestCountryId();
      $country = Mage::getModel('directory/country')->loadByCode($country_iso639_2);
      $country_name = $country->getName();
      $store_currency = Mage::app()->getStore()->getCurrentCurrencyCode();
      // get order totals
      $order_info = Localexpress_Shipping_Helper_Data::getOrderTotals($request,Localexpress_Shipping_Helper_Data::catageryOnly(),Localexpress_Shipping_Helper_Data::attributesOnly());
      if($order_info['notPossible'])
         return false;
      // addresses
      $addr_origin = Localexpress_Shipping_Helper_Data::getAddressOrigin();
      $addr_dest = Localexpress_Shipping_Helper_Data::buildAddress($country_iso639_2, $country_name, $address, $zip,
        $firstname . " " . $lastname, $city); 
      //TODO: demension attribute!!
      $dimensions = Localexpress_Shipping_Helper_Data::buildDimensions(1, 1, 1);
      // request quote
      $available = $helper->shipmentAvailable($country_iso639_2, $address, $zip);
      if(!$available)
        return false;
      
      $quote = $helper->shipmentQuote($order_info["qty"], $order_info["weight"], $order_info["price"], $store_currency, 
        $dimensions, $addr_origin, $addr_dest, "Quote request!", false, $human_id);
      // invalid server response
      if($quote["info"]["http_code"] != 201 && $quote["info"]["http_code"] != 200)
        return false;
      // list details
      $human_id = Mage::getSingleton('checkout/session')->setData('localexpress_shipping_human_id',$quote['body']->shipment_quote->human_id);


      $carrier_name = $fields["title"];
      $service_name = $fields["service_name"];
      $service_id = "1";
      $service_price = $quote["body"]->shipment_quote->price;
      // build response
      $method = Mage::getModel('shipping/rate_result_method');
      // carrier
      $method->setCarrier(Localexpress_Shipping_Helper_Data::NAME);
      $method->setCarrierTitle($fields['title'] . " (".$carrier_name.")");
      // methods
      $method->setMethod(Localexpress_Shipping_Helper_Data::SERVICE_NAME.'_'.$service_id);
      $method->setMethodTitle($service_name);
      $method->setPrice($service_price);
      $method->setCost($service_price);
      $result->append($method);
      return $result;
    }
    catch(\Exception $e)
    {
      Localexpress_Shipping_Helper_Data::log($e->getMessage());
      Localexpress_Shipping_Helper_Data::log($e->getTraceAsString());
      // halt order checkout on error
      die();
      return false;
    }
  }


  public function getAllowedMethods()
  {
      return array(Localexpress_Shipping_Helper_Data::NAME => $this->getConfigData('name'));
  }

}
  
?>