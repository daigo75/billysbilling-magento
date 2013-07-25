<?php
class BillysBilling_Invoicer_Model_Observer {

    private $testMode = false;

    private $apiKey = "";
    private $shippingId = "";
    private $accountId = "";
    private $vatModelId = "";
    private $bankAccountId = "";
    private $disablePayments = false;
    private $dueDateOffset = 0;
    private $contactMessage = "";

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
        $this->bankAccountId = Mage::getStoreConfig("billy/invoicer/bank_account");
        $this->disablePayments = Mage::getStoreConfig("billy/invoicer/disable_payments");
        $this->dueDateOffset = Mage::getStoreConfig("billy/invoicer/due_date_offset");
        $this->contactMessage = Mage::getStoreConfig("billy/invoicer/contact_message");

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
        $dueDate = date("Y-m-d", $order->getCreatedAtDate()->getTimestamp() + $this->dueDateOffset * 86400);
        // Set invoice data
        $invoice = array(
            "type" => "invoice",
            "contactId" => $contactId,
            "contactMessage" => str_replace("{order_id}", $order->getIncrementId(), $this->contactMessage),
            "entryDate" => $date,
            "dueDate" => $dueDate,
            "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
            "state" => "approved",
            "lines" => $products
        );

        // Create new invoice
        try {
            if ($this->testMode) {
                $response = $this->client->fakePost(Mage::getBaseDir() . "/tests/output.log", "invoices", $invoice);
            }
            $response = $this->client->post("invoices", $invoice);
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on invoice creation.");
        }
        if ($response->success && !$this->disablePayments) {
            $payment = array(
                "paidDate" => $date,
                "accountId" => $this->bankAccountId,
                "amount" => $order->getTotalPaid(),
                "invoiceIds" => array(
                    $response->id
                )
            );
            if ($this->testMode) {
                return $this->client->fakePost(Mage::getBaseDir() . "/tests/output.log", "payments", $payment);
            }
            return $this->client->post("payments", $payment);
        }
        return $response;
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
            $address = $type . "?externalId=" . urlencode($data['externalId']);
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
                'externalId' => $data->getEmail(),
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
                "productNo" => $data->sku,
                "externalId" => $data->sku,
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