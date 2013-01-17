<?php
class BillysBilling_Invoicer_Model_Observer {

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
        $order = $observer->getOrder();

        // Include Billy's PHP SDK
        if (!class_exists('Billy_Client')) {
            require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
        }

        // Set variables
        $this->apiKey = Mage::getStoreConfig("billy/api/api_key");
        $this->shippingId = Mage::getStoreConfig("billy/invoicer/shipping_account");
        $this->accountId = Mage::getStoreConfig("billy/invoicer/sales_account");
        $this->vatModelId = Mage::getStoreConfig("billy/invoicer/vat_model");

        // Create new client with API key
        try {
            $this->client = new Billy_Client($this->apiKey);
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e);
            return false;
        }

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
            $products[] = array(
                "productId" => $productId,
                "quantity" => $item->getQtyInvoiced(),
                "unitPrice" => $item->getPrice()
            );
        }
        // Add shipping costs to product array
        $products[] = array(
            "productId" => $this->shippingId,
            "quantity" => 1,
            "unitPrice" => $order->getShippingAmount()
        );

        // Order date
        $date = date("Y-m-d", $order->getCreatedAtDate()->getTimestamp());

        // Create new invoice
        try {
            $response = $this->client->post("invoices", array(
                "type" => "invoice",
                "contactId" => $contactId,
                "entryDate" => $date,
                "dueDate" => $date,
                "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                "state" => "approved",
                "lines" => $products
            ));
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
            $response = $this->client->get($type . "?q=" . urlencode($data['name']));
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
                $response = $this->client->post($type, $data);
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