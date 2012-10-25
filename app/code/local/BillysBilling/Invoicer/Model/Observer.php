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
        if($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE) {
            // Include Billy's PHP SDK
            require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");

            // Set variables
            $this->apiKey = Mage::getStoreConfig("billy/api/api_key");
            $this->shippingId = Mage::getStoreConfig("billy/invoicer/shipping_account");
            $this->accountId = Mage::getStoreConfig("billy/invoicer/sales_account");
            $this->vatModelId = Mage::getStoreConfig("billy/invoicer/vat_model");

            // Create new client with API key
            $this->client = new Billy_Client($this->apiKey);

            // Get contact ID
            $contactId = $this->insertIgnore("contacts", $order->getBillingAddress());

            // Run through each order item
            $items = $order->getItemsCollection(array(), true);
            $products = array();
            foreach ($items as $item) {
                // Get product ID
                $productId = $this->insertIgnore("products", $item);

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

            // Order date
            $date = date("Y-m-d", $order->getCreatedAtDate()->getTimestamp());

            // Format invoice details
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
            $response = $this->client->post("invoices", $invoice);

            // Send debug to local script
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, "invoice_response=" . json_encode($response) . "&state=" . $order->getState() . "&invoice_data=" . json_encode($invoice));
            curl_setopt($ch, CURLOPT_URL, 'http://posttest.dev/index.php');
            curl_exec($ch);
            curl_close($ch);
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
        $response = $this->client->get($type . "?q=" . urlencode($data['name']));
        $responseArray = $response->$type;
        if (count($responseArray) > 0) {
            // If existing contact, then save ID
            $id = $responseArray[0]->id;
        } else {
            // Create new contact and contact person, then save ID
            $response = $this->client->post($type, $data);
            $id = $response->id;
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