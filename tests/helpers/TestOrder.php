<?php
class TestOrder {
    private $quote;

    public function __construct() {
        $this->quote = Mage::getModel("sales/quote")->setStoreId(Mage::app()->getStore("default")->getId());
    }

    public function addProduct($productId, $quantity = 1) {
        // Add product
        $product = Mage::getModel("catalog/product")->load($productId);
        $buyInfo = array(
            "qty" => $quantity
        );
        $this->quote->addProduct($product, new Varien_Object($buyInfo));
    }

    public function setShipping($addressData, $addressData2 = null, $freeShipping = false) {
        // Add addresses
        $this->quote->setCustomerEmail($addressData["email"]);
        $this->quote->getBillingAddress()->addData($addressData);
        $shippingAddress = $this->quote->getShippingAddress()->addData($addressData2 ? $addressData2 : $addressData);
        // Handle possible free shipping
        if ($freeShipping) {
            $shippingAddress->setFreeShipping(true);
        }
        // Set shipping and payment
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod("flatrate_flatrate")->setPaymentMethod("checkmo");
        $this->quote->getPayment()->importData(array("method" => "checkmo"));
    }

    public function addDiscountCode($code) {
        // Add discount codes and update totals
        $this->quote->setCouponCode($code)->save();
        if ($code != $this->quote->getCouponCode()) {
            throw new Exception('Coupon code is not valid.');
        }
        $this->quote->setTotalsCollectedFlag(false)->collectTotals()->save();
    }

    public function finalize() {
        // Update totals
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