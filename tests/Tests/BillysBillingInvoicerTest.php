<?php
class BillysBillingInvoicerTest extends PHPUnit_Framework_TestCase {

    public static $testConfig;
    protected $addressData;
    protected $addressData2;
    protected $productStock = array();
    protected $orderId;

    public static function setUpBeforeClass() {
        global $testConfig;

        // Enable test mode
        Mage::getConfig()->saveConfig("billy/invoicer/mode", "test");
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();

        // Set test config
        BillysBillingInvoicerTest::$testConfig = $testConfig;
    }

    public static function tearDownAfterClass() {
        // Switch away from test mode
        Mage::getConfig()->saveConfig("billy/invoicer/mode", "");
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
    }

    protected function setUp() {
        // Create/reset output log
        $handle = fopen(TEST_OUTPUT_LOG_FILE, "w");
        fclose($handle);

        // Set stock data
        foreach (BillysBillingInvoicerTest::$testConfig["products"] as $product) {
            $this->productStock[$product["id"]] = Mage::getModel("cataloginventory/stock_item")->loadByProduct($product["id"])->getQty();
        }
    }

    protected function tearDown() {
        // Reset stock
        foreach (BillysBillingInvoicerTest::$testConfig["products"] as $product) {
            Mage::getModel("cataloginventory/stock_item")->loadByProduct($product["id"])->setQty($this->productStock[$product["id"]])->save();
        }

        // Remove test order
        Mage::getModel("sales/order")->loadByIncrementId($this->orderId)->delete();

        // Remove output log
        unlink(TEST_OUTPUT_LOG_FILE);
    }

    public function testSimpleOrder() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"]);
        $order->setShipping($address1);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] POST invoices
        $this->assertCall($commands[4], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["price"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"])
                )
            )
        ));
        // [5] POST payments
        $this->assertCall($commands[5], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum($product1["price"] * 1.25 + $product1["shipping_price"], 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithMultipleProducts() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $product2 = BillysBillingInvoicerTest::$testConfig["products"][1];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"], 2);
        $order->addProduct($product2["id"]);
        $order->setShipping($address1);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] GET products
        $this->assertCall($commands[4], "GET", "products?q=" . urlencode($product2["name"]));
        // [5] POST products
        $this->assertCall($commands[5], "POST", "products", array(
            "name" => $product2["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product2["id"],
            "suppliersProductNo" => $product2["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product2["price"]
                )
            )
        ));
        // [6] POST invoices
        $this->assertCall($commands[6], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 2,
                    "unitPrice" => formatNum($product1["price"])
                ),
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product2["price"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"] + $product2["shipping_price"])
                )
            )
        ));
        // [7] POST payments
        $this->assertCall($commands[7], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum((2*$product1["price"] + $product2["price"]) * 1.25 + $product1["shipping_price"] + $product2["shipping_price"], 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithPercentageDiscount() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"]);
        $order->setShipping($address1);
        $order->addDiscountCode(BillysBillingInvoicerTest::$testConfig["discounts"]["percentage"]["code"]);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] POST invoices
        $this->assertCall($commands[4], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["price"]),
                    "discountMode" => "percent",
                    "discountValue" => formatNum(BillysBillingInvoicerTest::$testConfig["discounts"]["percentage"]["amount"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"])
                )
            )
        ));
        // [5] POST payments
        $this->assertCall($commands[5], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum(($product1["price"] - $product1["price"] * 1.25 * BillysBillingInvoicerTest::$testConfig["discounts"]["percentage"]["amount"] / 100) * 1.25 + $product1["shipping_price"], 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithCashDiscount() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"]);
        $order->setShipping($address1);
        $order->addDiscountCode(BillysBillingInvoicerTest::$testConfig["discounts"]["cash"]["code"]);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] POST invoices
        $this->assertCall($commands[4], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["price"]),
                    "discountMode" => "cash",
                    "discountValue" => formatNum(BillysBillingInvoicerTest::$testConfig["discounts"]["cash"]["amount"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"])
                )
            )
        ));
        // [5] POST payments
        $this->assertCall($commands[5], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum($product1["price"] * 1.25 + $product1["shipping_price"] - BillysBillingInvoicerTest::$testConfig["discounts"]["cash"]["amount"] * 1.25, 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithCartCashDiscount() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $product2 = BillysBillingInvoicerTest::$testConfig["products"][1];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"], 2);
        $order->addProduct($product2["id"]);
        $order->setShipping($address1);
        $order->addDiscountCode(BillysBillingInvoicerTest::$testConfig["discounts"]["cart_cash"]["code"]);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] GET products
        $this->assertCall($commands[4], "GET", "products?q=" . urlencode($product2["name"]));
        // [5] POST products
        $this->assertCall($commands[5], "POST", "products", array(
            "name" => $product2["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product2["id"],
            "suppliersProductNo" => $product2["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product2["price"]
                )
            )
        ));
        // [6] POST invoices
        $this->assertCall($commands[6], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 2,
                    "unitPrice" => formatNum($product1["price"]),
                    "discountMode" => "cash",
                    "discountValue" => formatNum(round($product1["price"] * 2 / ($product1["price"] * 2 + $product2["price"]) * BillysBillingInvoicerTest::$testConfig["discounts"]["cart_cash"]["amount"], 2))
                ),
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product2["price"]),
                    "discountMode" => "cash",
                    "discountValue" => formatNum(round($product2["price"] / ($product1["price"] * 2 + $product2["price"]) * BillysBillingInvoicerTest::$testConfig["discounts"]["cart_cash"]["amount"], 2))
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"] + $product2["shipping_price"])
                )
            )
        ));
        // [7] POST payments
        $this->assertCall($commands[7], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum((2*$product1["price"] + $product2["price"]) * 1.25 + $product1["shipping_price"] + $product2["shipping_price"] - BillysBillingInvoicerTest::$testConfig["discounts"]["cart_cash"]["amount"] * 1.25, 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithFreeShipping() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $product2 = BillysBillingInvoicerTest::$testConfig["products"][1];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];

        $order = new TestOrder();
        $order->addProduct($product1["id"], 2);
        $order->addProduct($product2["id"]);
        $order->setShipping($address1, null, true);
        $order->addDiscountCode(BillysBillingInvoicerTest::$testConfig["discounts"]["free_shipping"]["code"]);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] GET products
        $this->assertCall($commands[4], "GET", "products?q=" . urlencode($product2["name"]));
        // [5] POST products
        $this->assertCall($commands[5], "POST", "products", array(
            "name" => $product2["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product2["id"],
            "suppliersProductNo" => $product2["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product2["price"]
                )
            )
        ));
        // [6] POST invoices
        $this->assertCall($commands[6], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 2,
                    "unitPrice" => formatNum($product1["price"])
                ),
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product2["price"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum(0)
                )
            )
        ));
        // [7] POST payments
        $this->assertCall($commands[7], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum((2*$product1["price"] + $product2["price"]) * 1.25, 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    public function testOrderWithDifferentAddresses() {
        $product1 = BillysBillingInvoicerTest::$testConfig["products"][0];
        $address1 = BillysBillingInvoicerTest::$testConfig["addresses"][0];
        $address2 = BillysBillingInvoicerTest::$testConfig["addresses"][1];

        $order = new TestOrder();
        $order->addProduct($product1["id"]);
        $order->setShipping($address1, $address2);
        $this->orderId = $order->finalize();

        $commands = getOutput();
        // [0] GET contacts
        $this->assertCall($commands[0], "GET", "contacts?q=" . urlencode($address1["firstname"] . " " . $address1["lastname"]));
        // [1] POST contacts
        $this->assertCall($commands[1], "POST", "contacts", array(
            "name" => $address1["firstname"] . " " . $address1["lastname"],
            "street" => $address1["street"],
            "zipcode" => $address1["postcode"],
            "city" => $address1["city"],
            "countryId" => $address1["country_id"],
            "state" => null,
            "phone" => $address1["telephone"],
            "fax" => null,
            "persons" => array(
                array(
                    "name" => $address1["firstname"] . " " . $address1["lastname"],
                    "email" => $address1["email"],
                    "phone" => $address1["telephone"]
                )
            )
        ));
        // [2] GET products
        $this->assertCall($commands[2], "GET", "products?q=" . urlencode($product1["name"]));
        // [3] POST products
        $this->assertCall($commands[3], "POST", "products", array(
            "name" => $product1["name"],
            "accountId" => Mage::getStoreConfig("billy/invoicer/sales_account"),
            "vatModelId" => Mage::getStoreConfig("billy/invoicer/vat_model"),
            "productType" => "product",
            "productNo" => $product1["id"],
            "suppliersProductNo" => $product1["supplier_id"],
            "prices" => array(
                array(
                    "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
                    "unitPrice" => $product1["price"]
                )
            )
        ));
        // [4] POST invoices
        $this->assertCall($commands[4], "POST", "invoices", array(
            "type" => "invoice",
            "contactId" => "12345-ABCDEFGHIJKLMNOP",
            "entryDate" => date("Y-m-d"),
            "dueDate" => date("Y-m-d"),
            "currencyId" => BillysBillingInvoicerTest::$testConfig["currency"],
            "state" => "approved",
            "lines" => array(
                array(
                    "productId" => "12345-ABCDEFGHIJKLMNOP",
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["price"])
                ),
                array(
                    "productId" => Mage::getStoreConfig("billy/invoicer/shipping_account"),
                    "quantity" => 1,
                    "unitPrice" => formatNum($product1["shipping_price"])
                )
            )
        ));
        // [5] POST payments
        $this->assertCall($commands[5], "POST", "payments", array(
            "paidDate" => date("Y-m-d"),
            "accountId" => Mage::getStoreConfig("billy/invoicer/bank_account"),
            "amount" => formatNum($product1["price"] * 1.25 + $product1["shipping_price"], 2),
            "invoiceIds" => array(
                "12345-ABCDEFGHIJKLMNOP"
            )
        ));
    }

    private function assertCall($call, $method, $address, $params = null) {
        $this->assertEquals($method, $call["mode"]);
        $this->assertEquals($address, $call["address"]);
        $this->assertEquals($params, $call["params"]);
    }
}