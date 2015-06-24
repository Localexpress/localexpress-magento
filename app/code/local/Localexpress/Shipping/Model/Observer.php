<?php
class Localexpress_Shipping_Model_Observer
{
  // handle order save
  public function salesOrderSaveAfter(Varien_Event_Observer $observer)
  {
    try
    {
      $order = $observer->getEvent()->getOrder();
      self::submitOrder($order);
    }
    catch(\Exception $e)
    {
      Localexpress_Shipping_Helper_Data::log($e->getMessage());
      Localexpress_Shipping_Helper_Data::log($e->getTraceAsString());
      return false;
    }
    // continue order
    return true;
  }

  // handle checkout finished successful
  public function salesModelServiceQuoteSubmitSuccess(Varien_Event_Observer $observer)
  {
    try
    {
      $order = $observer->getEvent()->getOrder();
      self::submitOrder($order);
    }
    catch(\Exception $e)
    {
      Localexpress_Shipping_Helper_Data::log($e->getMessage());
      Localexpress_Shipping_Helper_Data::log($e->getTraceAsString());
      return false;
    }
    // continue order
    return true;
  }

  // override theme, to custom skepr theme (NOTE: must be configured in backend!)
  public function overrideTheme()
  {
    Mage::getDesign()->setArea('adminhtml')
      ->setTheme((string)Mage::getStoreConfig('design/admin/theme'));
  }

  private static function submitOrder($order)
  {
    // $helper->log($order);
    // collect shipping info 
    $helper = Mage::helper('localexpress_shipping');
    $status = $order->getStatus();
    $shipping_method = $order->getShippingMethod();
    $shipping_service_id = $helper->getShippingMethodId($shipping_method);
    // shippment allowed?
    if($helper->canShipOrder($order))
    {
      // get order totals
      // $order_info = Localexpress_Shipping_Helper_Data::getOrderTotals($order);
      $order_info = array();
      $order_info["qty"] = ceil($order->total_qty_ordered);
      $order_info["weight"] = ceil($order->weight);
      $order_info["price"] = (float)$order->grand_total;
      $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
      $shipping_address = $order->getShippingAddress();
      $firstname = $shipping_address['firstname'];
      $lastname = $shipping_address['lastname'];
      $street = $shipping_address['street'];
      $city = $shipping_address['city'];
      $zip = $shipping_address['postcode'];
      $country_iso2 = $shipping_address['country_id'];
      $o_country = Mage::getModel('directory/country')->loadByCode($country_iso2);
      $country_name = $o_country->getName();
      // prepare shipment
      $dim = Localexpress_Shipping_Helper_Data::buildDimensions(1, 1, 1);
      $addr_origin = Localexpress_Shipping_Helper_Data::getAddressOrigin();
      $addr_dest = Localexpress_Shipping_Helper_Data::buildAddress($country_iso2, $country_name, 
        $street, $zip, $firstname . " " . $lastname, $city);
      $comment = "Creating shipment";
      $insurance = false;
      
      $tmpShipment = Mage::getModel('sales/service_order', $order);
      
      // create shipment
      $shipment_resp = $helper->shipment($order_info["qty"], $order_info["weight"], $order_info["price"], 
        $currency, $dim, $addr_origin, $addr_dest, $comment, $insurance,$tmpShipment->getData('number'));
      $shipment_resp = $shipment_resp["body"]->shipment;
      // create magento shipment
      $track_data = array(
        'carrier_code' => $order->shipping_method,
        'title' => $order->shipping_description,
        'number' => $shipment_resp->humanId,
        'description' => $shipment_resp->humanId, //TODO: add description (link to boxture)
        'qty' => ceil($shipment_resp->quantity), 
        'weight' => ceil($shipment_resp->weight));
      $pre_shipment = Localexpress_Shipping_Helper_Data::getItemQtys($order);
      $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($pre_shipment);
      $track = Mage::getModel('sales/order_shipment_track')->addData($track_data);
      $shipment->addTrack($track);
      $shipment->register();
      $order->setIsInProcess(true);
      $transactionSave = Mage::getModel('core/resource_transaction')
        ->addObject($shipment)->addObject($order)->save();
      $emailSentStatus = $shipment->getData('email_sent');
      if (!is_null($customerEmailComments) && !$emailSentStatus) {
          $shipment->sendEmail(true, $customerEmailComments);
          $shipment->setEmailSent(true);
      }
    }
  }

}
?>