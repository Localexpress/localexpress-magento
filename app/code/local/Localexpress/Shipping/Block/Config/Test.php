<?php

class Localexpress_Shipping_Block_Config_Test extends Mage_Adminhtml_Block_System_Config_Form_Field
{
  protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
  {
    try
    {
      $helper = Mage::helper('localexpress_shipping');
      if(!$helper->hasOriginAddress()){
        return "Origin address not configured!";
      }

      $dim = $helper->buildDimensions(1,1,1);
      $addr_from = Localexpress_Shipping_Helper_Data::getAddressOrigin();
      if (!extension_loaded('simplexml')) {
        return "SimpleXML module is mandatory and is not installed on this server";
      }
      $xml = $helper->shipmentGeo("NL", "The Netherlands", "Vijzelstraat 68", "1017HL", "Localexpress", "Amsterdam");

      if(empty($xml['house_number']))
         return "No valid GEO server";
      $json = $helper->addressSplitter("NL", "Randstad 21 33", "1314BG");
      $json = json_decode($json['result'],true);
      if($json[0]['lat'])
         return "No valid address server";

      // test with quote shipment
      $addr_to = Localexpress_Shipping_Helper_Data::buildAddress("NL", "The Netherlands", "Vijzelstraat", "1017HL", "Localexpress", "Amsterdam","68");
      $quote = $helper->shipmentQuote(1, 50, 10.01, "EUR", $dim, $addr_from, $addr_to, "Test Quote", false);
      $helper->log("Saving configuration!");
      if(!array_key_exists("info", $quote))
        return "Cannot connect to server.";
      $code = $quote["info"]["http_code"];
      if ($code == 401)
        return "Unauthorized.";
      if ($code != 200 && $code != 201)
        return "Incorrect server Config: " . print_r($quote, true);

      return "Connected successfully!";

    }
    catch(\Exception $e)
    {
      return "Error: '" . $e->getMessage() . "'";
    }
  }
}
