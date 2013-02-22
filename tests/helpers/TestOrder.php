<?php
class TestOrder {
    private $quote;

    public function __construct() {
        $this->quote = Mage::getModel("sales/quote")->setStoreId(Mage::app()->getStore("default")->getId());
        $this->quote->setCustomerEmail(TEST_CUSTOMER_EMAIL);
    }

    public function addProduct($productId, $quantity = 1) {
        $product = Mage::getModel("catalog/product")->load($productId);
        $buyInfo = array(
            "qty" => $quantity
        );
        $this->quote->addProduct($product, new Varien_Object($buyInfo));
    }

    public function finalize() {
        // Add address, shipping and payment information
        $addressData = array(
            "firstname" => TEST_CUSTOMER_FIRST_NAME,
            "lastname" => TEST_CUSTOMER_LAST_NAME,
            "street" => TEST_CUSTOMER_STREET,
            "city" => TEST_CUSTOMER_CITY,
            "postcode" => TEST_CUSTOMER_POSTCODE,
            "telephone" => TEST_CUSTOMER_TELEPHONE,
            "email" => TEST_CUSTOMER_EMAIL,
            "country_id" => TEST_CUSTOMER_COUNTRY_ID
        );
        $billingAddress = $this->quote->getBillingAddress()->addData($addressData);
        $shippingAddress = $this->quote->getShippingAddress()->addData($addressData);
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod("flatrate_flatrate")->setPaymentMethod("checkmo");
        $this->quote->getPayment()->importData(array("method" => "checkmo"));
        $this->quote->collectTotals()->save();

        // Create order
        $service = Mage::getModel("sales/service_quote", $this->quote);
        $service->submitAll();
        $order = $service->getOrder();

        // Create invoice
        if ($order->canInvoice()) {
            $invoiceId = Mage::getModel("sales/order_invoice_api")->create($order->getIncrementId(), array());
        }
        $invoice = Mage::getModel("sales/order_invoice")->loadByIncrementId($invoiceId);
        $invoice->save();

        return $order->getIncrementId();
    }
}