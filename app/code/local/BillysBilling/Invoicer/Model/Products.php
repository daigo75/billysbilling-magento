<?php
class BillysBilling_Invoicer_Model_Products {
    public function toOptionArray() {
        if (strlen(Mage::getStoreConfig("billy/api/api_key")) > 10) {
            // Include Billy's PHP SDK
            if (!class_exists('Billy_Client')) {
                require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
            }

            // Create new client with API key
            $client = new Billy_Client(Mage::getStoreConfig("billy/api/api_key"));

            // Get all products
            $response = $client->get("products");

            // Map accounts to account types and sort account types
            $options = array();
            foreach ($response->products AS $product) {
                $options[] = array(
                    "value" => $product->id,
                    "label" => $product->name
                );
            }

            return $options;
        } else {
            return array(
                array(
                    "value" => "",
                    "label" => "Please enter API key above and Save Config"
                )
            );
        }
    }
}