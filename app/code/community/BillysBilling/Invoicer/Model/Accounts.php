<?php
class BillysBilling_Invoicer_Model_Accounts {
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

        // Get all accounts
        try {
            $response = $client->get("accounts");
        } catch (Billy_Exception $e) {
            BillysBilling_Invoicer_Helper_Data::printError($e, "Error occurred on getting accounts data");
            return array(
                array(
                    "value" => "",
                    "label" => "Could not retrieve accounts from Billy API"
                )
            );
        }

        // Map accounts to account types and sort account types
        $results = array();
        foreach ($response->accounts AS $account) {
            $results[$account->accountType->name][$account->name] = array(
                "value" => $account->id,
                "label" => $account->name
            );
        }
        ksort($results);

        // Create optgroups and options containing account types and accounts
        $options = array();
        foreach ($results AS $accountType => $accounts) {
            ksort($accounts);
            $options[] = array(
                "value" => $accounts,
                "label" => $accountType
            );
        }

        return $options;
    }
}