<?php
class BillysBillingInvoicerTest extends PHPUnit_Framework_TestCase {

    protected $addressData;

    protected function setUp() {
        Mage::getConfig()->saveConfig("billy/api/api_key", TEST_API_KEY);
        Mage::getConfig()->saveConfig("billy/invoicer/shipping_account", TEST_SHIPPING_PRODUCT);
        Mage::getConfig()->saveConfig("billy/invoicer/sales_account", TEST_SALES_ACCOUNT);
        Mage::getConfig()->saveConfig("billy/invoicer/vat_model", TEST_VAT_MODEL);
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();

        $this->addressData = array(
            "firstname" => TEST_CUSTOMER_FIRST_NAME,
            "lastname" => TEST_CUSTOMER_LAST_NAME,
            "street" => TEST_CUSTOMER_STREET,
            "city" => TEST_CUSTOMER_CITY,
            "postcode" => TEST_CUSTOMER_POSTCODE,
            "telephone" => TEST_CUSTOMER_TELEPHONE,
            "email" => TEST_CUSTOMER_EMAIL,
            "country_id" => TEST_CUSTOMER_COUNTRY_ID
        );
    }

    public function testCreateOrder() {
        $quote = Mage::getModel("sales/quote")->setStoreId(Mage::app()->getStore("default")->getId());

        if ("do customer orders") {
            // For specific customer
            $customer = Mage::getModel("customer/customer")->setWebsiteId(1)->loadByEmail("customer@example.com");
            $quote->assignCustomer($customer);
        } else {
            // For guest customer
            $quote->setCustomerEmail("customer@example.com");
        }

        // Add product
        $product = Mage::getModel("catalog/product")->load(TEST_PRODUCT_ID);
        $buyInfo = array(
            "qty" => 1
        );
        $quote->addProduct($product, new Varien_Object($buyInfo));

        // Add addresses, shipping information and payment information
        $billingAddress = $quote->getBillingAddress()->addData($this->addressData);
        $shippingAddress = $quote->getShippingAddress()->addData($this->addressData);
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod("flatrate_flatrate")->setPaymentMethod("checkmo");
        $quote->getPayment()->importData(array("method" => "checkmo"));
        $quote->collectTotals()->save();

        // Finalize order
        $service = Mage::getModel("sales/service_quote", $quote);
        $service->submitAll();
        $order = $service->getOrder();

        // Invoice order
        if($order->canInvoice()) {
            $invoiceId = Mage::getModel("sales/order_invoice_api")->create($order->getIncrementId(), array());
        }
        $invoice = Mage::getModel("sales/order_invoice")->loadByIncrementId($invoiceId);
        $invoice->save();
    }
}
?>