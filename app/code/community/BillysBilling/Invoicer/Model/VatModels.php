<?php
class BillysBilling_Invoicer_Model_VatModels {
    public function toOptionArray() {
        if (!Mage::getStoreConfig("billy/api/api_key") || strlen(Mage::getStoreConfig("billy/api/api_key")) < 10) {
            return array(
                array(
                    "value" => "",
                    "label" => "Please enter API key above and Save Config"
                )
            );
        }
        // Include Billy's PHP SDK
        if (!class_exists('Billy_Client', false)) {
            require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
        }

        // Create new client with API key
        try {
            $client = new Billy_Client(Mage::getStoreConfig("billy/api/api_key"));
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e);
            return array(
                array(
                    "value" => "",
                    "label" => "Please use a valid API key"
                )
            );
        }

        // Get all VAT models
        try {
            $response = $client->get("vatModels");
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on getting vat models data");
            return array(
                array(
                    "value" => "",
                    "label" => "Could not retrieve vat models from Billy API"
                )
            );
        }

        // Create options containing VAT models
        $options = array();
        foreach ($response->vatModels AS $vatModel) {
            $options[] = array(
                "value" => $vatModel->id,
                "label" => $vatModel->name
            );
        }

        return $options;
    }
}