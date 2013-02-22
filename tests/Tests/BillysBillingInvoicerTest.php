<?php
class BillysBillingInvoicerTest extends PHPUnit_Framework_TestCase {

    protected $outputFile;
    protected $addressData;
    protected $orderId;

    protected function setUp() {
        // Enable test mode
        Mage::getConfig()->saveConfig("billy/invoicer/mode", "test");
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();

        // Set test customer data
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

        // Create/reset output log
        $this->outputFile = Mage::getBaseDir() . "/tests/output.log";
        $handle = fopen($this->outputFile, "w");
        fclose($handle);
    }

    protected function tearDown() {
        // Remove test order
        Mage::getModel("sales/order")->loadByIncrementId($this->orderId)->delete();

        // Remove output log
        unlink($this->outputFile);

        // Switch away from test mode
        Mage::getConfig()->saveConfig("billy/invoicer/mode", "");
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
    }

    public function testCreateOrder() {
        $quote = Mage::getModel("sales/quote")->setStoreId(Mage::app()->getStore("default")->getId());

        if ("do customer orders") {
            // For specific customer
            $customer = Mage::getModel("customer/customer")->setWebsiteId(1)->loadByEmail(TEST_CUSTOMER_EMAIL);
            $quote->assignCustomer($customer);
        } else {
            // For guest customer
            $quote->setCustomerEmail(TEST_CUSTOMER_EMAIL);
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
        $this->orderId = $order->getIncrementId();

        // Invoice order
        if ($order->canInvoice()) {
            $invoiceId = Mage::getModel("sales/order_invoice_api")->create($order->getIncrementId(), array());
        }
        $invoice = Mage::getModel("sales/order_invoice")->loadByIncrementId($invoiceId);
        $invoice->save();

        // Process output.log
        $handle = fopen($this->outputFile, "r");
        $contents = fread($handle, filesize($this->outputFile));
        $lines = explode("\n", $contents);
        fclose($handle);
        $commands = array();
        foreach ($lines as $line) {
            if (!$line) continue;
            $commands[] = json_decode($line, true);
        }
        // [0] GET contacts
        $this->assertEquals("GET", $commands[0]["mode"]);
        $this->assertEquals("contacts?q=" . urlencode(TEST_CUSTOMER_FIRST_NAME . " " . TEST_CUSTOMER_LAST_NAME), $commands[0]["address"]);
        // [1] POST contacts
        $this->assertEquals("POST", $commands[1]["mode"]);
        $this->assertEquals("contacts", $commands[1]["address"]);
        $this->assertEquals(array(
            "name" => TEST_CUSTOMER_FIRST_NAME . " " . TEST_CUSTOMER_LAST_NAME,
            "street" => TEST_CUSTOMER_STREET,
            "zipcode" => TEST_CUSTOMER_POSTCODE,
            "city" => TEST_CUSTOMER_CITY,
            "countryId" => TEST_CUSTOMER_COUNTRY_ID,
            "state" => null,
            "phone" => TEST_CUSTOMER_TELEPHONE,
            "fax" => null,
            "persons" => array(
                array(
                    "name" => TEST_CUSTOMER_FIRST_NAME . " " . TEST_CUSTOMER_LAST_NAME,
                    "email" => TEST_CUSTOMER_EMAIL,
                    "phone" => TEST_CUSTOMER_TELEPHONE
                )
            )
        ), $commands[1]["params"]);
        // [2] GET products
        $this->assertEquals("GET", $commands[2]["mode"]);
        $this->assertEquals("products?q=" . urlencode(TEST_PRODUCT_NAME), $commands[2]["address"]);
        // [3] POST products
        $this->assertEquals("POST", $commands[3]["mode"]);
        $this->assertEquals("products", $commands[3]["address"]);
        $this->assertEquals(array(
            "name" => TEST_PRODUCT_NAME,
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => TEST_PRODUCT_ID,
            "suppliersProductNo" => TEST_PRODUCT_SUPPLIER_ID,
            "prices" => array(
                array(
                    "currencyId" => TEST_CURRENCY,
                    "unitPrice" => TEST_PRODUCT_PRICE
                )
            )
        ), $commands[3]["params"]);
        // [4] POST invoices
        $this->assertEquals("POST", $commands[4]["mode"]);
        $this->assertEquals("invoices", $commands[4]["address"]);
        $this->assertEquals(array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => TEST_CURRENCY,
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => number_format(TEST_PRODUCT_PRICE, 4)
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => number_format(TEST_SHIPPING_PRICE, 4)
                )
            )
        ), $commands[4]["params"]);
    }
}