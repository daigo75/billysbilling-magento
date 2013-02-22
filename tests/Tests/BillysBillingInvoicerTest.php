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

        // Create/reset output log
        $handle = fopen(OUTPUT_LOG_FILE, "w");
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

    public function testSimpleOrder() {
        $order = new TestOrder();
        $order->addProduct(TEST_PRODUCT_ID);
        $this->orderId = $order->finalize();

        $commands = getOutput();
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
                    "unitPrice" => formatNum(TEST_PRODUCT_PRICE)
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum(TEST_PRODUCT_SHIPPING_PRICE)
                )
            )
        ), $commands[4]["params"]);
    }

    public function testOrderWithMultipleProducts() {
        $order = new TestOrder();
        $order->addProduct(TEST_PRODUCT_ID, 2);
        $order->addProduct(TEST_PRODUCT2_ID);
        $this->orderId = $order->finalize();

        $commands = getOutput();
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
        // [4] GET products
        $this->assertEquals("GET", $commands[4]["mode"]);
        $this->assertEquals("products?q=" . urlencode(TEST_PRODUCT2_NAME), $commands[4]["address"]);
        // [5] POST products
        $this->assertEquals("POST", $commands[5]["mode"]);
        $this->assertEquals("products", $commands[5]["address"]);
        $this->assertEquals(array(
            "name" => TEST_PRODUCT2_NAME,
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => TEST_PRODUCT2_ID,
            "suppliersProductNo" => TEST_PRODUCT2_SUPPLIER_ID,
            "prices" => array(
                array(
                    "currencyId" => TEST_CURRENCY,
                    "unitPrice" => TEST_PRODUCT2_PRICE
                )
            )
        ), $commands[5]["params"]);
        // [6] POST invoices
        $this->assertEquals("POST", $commands[6]["mode"]);
        $this->assertEquals("invoices", $commands[6]["address"]);
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
                    "quantity" => 2,
                    "unitPrice" => formatNum(TEST_PRODUCT_PRICE)
                ),
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum(TEST_PRODUCT2_PRICE)
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum(TEST_PRODUCT_SHIPPING_PRICE + TEST_PRODUCT2_SHIPPING_PRICE)
                )
            )
        ), $commands[6]["params"]);
    }

    public function testOrderWithPercentageDiscount() {
        // Not yet implemented
    }

    public function testOrderWithCashDiscount() {
        // Not yet implemented
    }

    public function testOrderWithFreeShipping() {
        // Not yet implemented
    }

    public function testOrderWithDifferentAddresses() {
        // Not yet implemented
    }
}