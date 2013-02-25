<?php
class BillysBilling_Invoicer_Model_Observer {

    private $testMode = false;

    private $apiKey = "";
    private $shippingId = "";
    private $accountId = "";
    private $vatModelId = "";

    private $client;

    /**
     * Save invoice to BB with contact and product details.
     *
     * @event sales_model_service_quote_submit_success
     *
     * @param $observer
     */
    public function saveInvoiceOnSuccess($observer) {
        if (Mage::getStoreConfig("billy/invoicer/mode") && Mage::getStoreConfig("billy/invoicer/mode") == "test") {
            $this->testMode = true;
        }

        // Set variables
        $this->apiKey = Mage::getStoreConfig("billy/api/api_key");
        $this->shippingId = Mage::getStoreConfig("billy/invoicer/shipping_account");
        $this->accountId = Mage::getStoreConfig("billy/invoicer/sales_account");
        $this->vatModelId = Mage::getStoreConfig("billy/invoicer/vat_model");

        // Include Billy's PHP SDK
        if (!class_exists('Billy_Client', false)) {
            require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
        }

        // Create new client with API key
        try {
            $this->client = new Billy_Client($this->apiKey);
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e);
            return false;
        }

        return $this->createInvoice($observer->getOrder());
    }

    /**
     * Process contacts and products, and create the invoice.
     *
     * @param $order
     * @return array Response from API for invoice creation
     */
    private function createInvoice($order) {
        // Get contact ID
        $contactId = $this->insertIgnore("contacts", $order->getBillingAddress());
        if ($contactId == null) {
            return false;
        }

        // Run through each order item
        $items = $order->getItemsCollection(array(), true);
        $products = array();
        foreach ($items as $item) {
            // Get product ID
            $productId = $this->insertIgnore("products", $item);
            if ($productId == null) {
                return false;
            }

            // Add item to product array
            $product = array(
                "productId" => $productId,
                "quantity" => $item->getQtyInvoiced(),
                "unitPrice" => $item->getPrice()
            );
            // Apply discounts
            if ($item->getDiscountPercent() > 0) {
                $product["discountMode"] = "percent";
                $product["discountValue"] = $item->getDiscountPercent();
            } else if ($item->getDiscountAmount() > 0) {
                $product["discountMode"] = "cash";
                $product["discountValue"] = $item->getDiscountAmount();
            }

            $products[] = $product;
        }
        // Add shipping costs to product array
        $products[] = array(
            "productId" => $this->shippingId,
            "quantity" => 1,
            "unitPrice" => $order->getShippingAmount()
        );

        // Order date
        $date = date("Y-m-d", $order->getCreatedAtDate()->getTimestamp());
        // Set invoice data
        $invoice = array(
            "type" => "invoice",
            "contactId" => $contactId,
            "entryDate" => $date,
            "dueDate" => $date,
            "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
            "state" => "approved",
            "lines" => $products
        );

        // Create new invoice
        try {
            if ($this->testMode) {
                return $this->client->fakePost(Mage::getBaseDir() . "/tests/output.log", "invoices", $invoice);
            }
            return $this->client->post("invoices", $invoice);
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on invoice creation.");
        }
    }

    /**
     * Take a data object from Magento, use it to either search for existing entries in BB and return ID of that, or
     * insert a new entry in BB and return ID of that.
     *
     * @param $type string "contacts" or "products"
     * @param $data BillingAddress object or Item object
     *
     * @return int ID of inserted or found entry
     */
    private function insertIgnore($type, $data) {
        // Format data
        $data = $this->formatArray($type, $data);

        // Check for existing contact
        $responseArray = array();
        $id = null;
        try {
            $address = $type . "?q=" . urlencode($data['name']);
            if ($this->testMode) {
                $response = $this->client->fakeGet(Mage::getBaseDir() . "/tests/output.log", $address);
            } else {
                $response = $this->client->get($address);
            }
            $responseArray = $response->$type;
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on getting " . $type . " data");
        }
        if (count($responseArray) > 0) {
            // If existing contact, then save ID
            $id = $responseArray[0]->id;
        } else {
            // Create new contact and contact person, then save ID
            try {
                if ($this->testMode) {
                    $response = $this->client->fakePost(Mage::getBaseDir() . "/tests/output.log", $type, $data);
                } else {
                    $response = $this->client->post($type, $data);
                }
                $id = $response->id;
            } catch (Billy_Exception $e) {
                BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on posting " . $type . " data");
            }
        }
        return $id;
    }

    /**
     * Take a data object from Magento and convert it into something usable by BB API.
     *
     * @param $type string "contacts" or "products"
     * @param $data BillingAddress object or Item object
     * @return array of either contact or product
     */
    private function formatArray($type, $data) {
        if ($type == "contacts") {
            // Set name depending on company or not
            if ($data->getCompany()) {
                $name = $data->getCompany();
            } else {
                $name = $data->getName();
            }

            return array(
                'name' => $name,
                'street' => $data->getStreetFull(),
                'zipcode' => $data->getPostcode(),
                'city' => $data->getCity(),
                'countryId' => $data->getCountry_id(),
                'state' => $data->getRegion(),
                'phone' => $data->getTelephone(),
                'fax' => $data->getFax(),
                'persons' => array(
                    array(
                        'name' => $data->getName(),
                        'email' => $data->getEmail(),
                        'phone' => $data->getTelephone()
                    )
                )
            );
        } else if ($type == "products") {
            return array(
                "name" => $data->getName(),
                "accountId" => $this->accountId,
                "vatModelId" => $this->vatModelId,
                "productType" => "product",
                "productNo" => $data->product_id,
                "suppliersProductNo" => $data->sku,
                "prices" => array(
                    array(
                        "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                        "unitPrice" => $data->getPrice()
                    )
                )
            );
        }
        return null;
    }
}