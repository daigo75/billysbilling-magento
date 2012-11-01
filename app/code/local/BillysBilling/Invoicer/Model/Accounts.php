<?php
class BillysBilling_Invoicer_Model_Accounts {
    public function toOptionArray() {
        if (strlen(Mage::getStoreConfig("billy/api/api_key")) > 10) {
            // Include Billy's PHP SDK
            if (!class_exists('Billy_Client')) {
                require(dirname(__FILE__) . "/billysbilling-php/bootstrap.php");
            }

            // Create new client with API key
            $client = new Billy_Client(Mage::getStoreConfig("billy/api/api_key"));

            // Get all accounts
            $response = $client->get("accounts");

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