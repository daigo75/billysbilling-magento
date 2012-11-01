<?php
class BillysBilling_Invoicer_Model_VatModels {
    public function toOptionArray() {
        if (strlen(Mage::getStoreConfig("billy/api/api_key")) > 10) {
            // Include Billy's PHP SDK
            if (!class_exists('Billy_Client')) {
                require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
            }

            // Create new client with API key
            $client = new Billy_Client(Mage::getStoreConfig("billy/api/api_key"));

            // Get all VAT models
            $response = $client->get("vatModels");

            // Create options containing VAT models
            $options = array();
            foreach ($response->vatModels AS $vatModel) {
                $options[] = array(
                    "value" => $vatModel->id,
                    "label" => $vatModel->name
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