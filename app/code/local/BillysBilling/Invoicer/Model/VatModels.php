<?php
class BillysBilling_Invoicer_Model_VatModels {
    public function toOptionArray() {
        if (strlen(Mage::getStoreConfig("billy/api/api_key")) > 10) {
            // Create CURL instance
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, Mage::getStoreConfig("billy/api/api_key") . ":");

            // Get all accounts
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/vatModels");
            $response = json_decode(curl_exec($ch));

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
                array('value'=>'', 'label'=>'Please enter API key above and Save Config')
            );
        }
    }
}