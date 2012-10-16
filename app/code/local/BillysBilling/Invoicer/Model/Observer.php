<?php
class BillysBilling_Invoicer_Model_Observer {

    private $apiKey = "";
    private $shippingId = "";
    private $accountId = "";
    private $vatModelId = "";
    //private $apiKey = "0rA6XS2blX4EEa8u20retof1OLdGaotQ"; // API key
    //private $shippingId = "190674-lTXK8T8qf2NzF"; // Fragt (product)
    //private $vatModelId = "190597-1B4Efvc9XXV3R"; // Salgsmoms (vat model)
    //private $accountId = "190614-4OR6MXKLjZ27A"; // Salg (account)

    /**
     * Save invoice to BB with contact and product details.
     *
     * @event sales_model_service_quote_submit_success
     *
     * @param $observer
     */
    public function saveInvoiceOnSuccess($observer) {
        $order = $observer->getOrder();
        if($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE) {
            // Set variables
            $this->apiKey = Mage::getStoreConfig("billy/api/api_key");
            $this->shippingId = Mage::getStoreConfig("billy/invoicer/shipping_account");
            $this->accountId = Mage::getStoreConfig("billy/invoicer/sales_account");
            $this->vatModelId = Mage::getStoreConfig("billy/invoicer/vat_model");

            // Create CURL instance
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ":");

            // Format contact details
            $contact = $this->getContactArray($order->getBillingAddress());
            // Check for existing contact
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/contacts?q=" . urlencode($contact['name']));
            $response = json_decode(curl_exec($ch));
            if (count($response->contacts) > 0) {
                // If existing contact, then save ID
                $contactId = $response->contacts[0]->id;
            } else {
                // Create new contact and contact person, then save ID
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contact));
                curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/contacts");
                $response = json_decode(curl_exec($ch));
                $contactId = $response->id;
            }

            // Run through each order item
            $items = $order->getItemsCollection(array(), true);
            $products = array();
            $responses = array(); // debug
            foreach ($items as $item) {
                // Format product details
                $product = $this->getProductArray($item);
                // Check for existing product
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/products?q=" . urlencode($product['name']));
                $response = json_decode(curl_exec($ch));
                if (count($response->products) > 0) {
                    // If existing product, then save ID
                    $productId = $response->products[0]->id;
                } else {
                    // Create new product, then save ID
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product));
                    curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/products");
                    $response = json_decode(curl_exec($ch));
                    $productId = $response->id;
                }

                // Add item to product array
                $products[] = array(
                    "productId" => $productId,
                    "quantity" => $item->getQtyShipped(),
                    "unitPrice" => $item->getPrice()
                );
            }
            // Add shipping costs to product array
            $products[] = array(
                "productId" => $this->shippingId,
                "quantity" => 1,
                "unitPrice" => $order->getShippingAmount()
            );

            // Current date
            $date = date("Y-m-d", $order->getCreatedAtDate()->getTimestamp());

            // Format invoice details
            $invoice = array(
                "type" => "invoice",
                "contactId" => $contactId,
                "entryDate" => $date,
                "dueDate" => $date,
                "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                "exchangeRate" => 5.7785,
                "state" => "approved",
                "lines" => $products
            );

            // Send debug to local script
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, "invoice_post=" . json_encode($invoice));
            curl_setopt($ch, CURLOPT_URL, 'http://posttest.dev/index.php');
            curl_exec($ch);

            // Create new invoice
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice));
            curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/invoices");
            $rawResponse = curl_exec($ch);
            //$response = json_decode($rawResponse);

            // Send debug to local script
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, "invoice_response=" . $rawResponse . "&state=" . $order->getState());
            curl_setopt($ch, CURLOPT_URL, 'http://posttest.dev/index.php');
            curl_exec($ch);

            curl_close($ch);
        }
    }

    /**
     * Take an Address object from Magento and convert it into something usable by BB API.
     *
     * @param $contact Address object
     * @return array of contact details with contact person
     */
    private function getContactArray($contact) {
        // Set name depending on company or not
        if ($contact->getCompany()) {
            $name = $contact->getCompany();
        } else {
            $name = $contact->getName();
        }

        return array(
            'name' => $name,
            'street' => $contact->getStreetFull(),
            'zipcode' => $contact->getPostcode(),
            'city' => $contact->getCity(),
            'countryId' => $contact->getCountry_id(),
            'state' => $contact->getRegion(),
            'phone' => $contact->getTelephone(),
            'fax' => $contact->getFax(),
            'persons' => array(
                array(
                    'name' => $contact->getName(),
                    'email' => $contact->getEmail(),
                    'phone' => $contact->getTelephone()
                )
            )
        );
    }

    /**
     * Take an Item object from Magento and convert it to something usable by BB API.
     *
     * @param $item Item object
     * @return array of product details
     */
    private function getProductArray($item) {
        return array(
            "name" => $item->getName(),
            "accountId" => $this->accountId,
            "vatModelId" => $this->vatModelId,
            "productType" => "product",
            "productNo" => $item->product_id,
            "suppliersProductNo" => $item->sku,
            "prices" => array(
                array(
                    "currencyId" => Mage::app()->getStore()->getCurrentCurrencyCode(),
                    "unitPrice" => $item->getPrice()
                )
            )
        );
    }

    /**
     * Mage::dispatchEvent($this->_eventPrefix.'_save_after', $this->_getEventData());
     * protected $_eventPrefix = 'sales_order';
     * protected $_eventObject = 'order';
     * event: sales_order_save_after
     */
    public function automaticallyInvoiceShipCompleteOrder($observer) {
        $order = $observer->getEvent()->getOrder();
        $orders = Mage::getModel('sales/order_invoice')->getCollection()
            ->addAttributeToFilter('order_id', array('eq'=>$order->getId()));
        $orders->getSelect()->limit(1);
        if ((int)$orders->count() !== 0) {
            return $this;
        }
        if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
            try {
                if(!$order->canInvoice()) {
                    $order->addStatusHistoryComment('Inchoo_Invoicer: Order cannot be invoiced.', false);
                    $order->save();
                }
                //START Handle Invoice
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(false);
                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically INVOICED by Inchoo_Invoicer.', false);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
                //END Handle Invoice
                //START Handle Shipment
                $shipment = $order->prepareShipment();
                $shipment->register();
                $order->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically SHIPPED by Inchoo_Invoicer.', false);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
                //END Handle Shipment
            } catch (Exception $e) {
                $order->addStatusHistoryComment('Inchoo_Invoicer: Exception occurred during automaticallyInvoiceShipCompleteOrder action. Exception message: '.$e->getMessage(), false);
                $order->save();
            }
        }
        return $this;
    }
}