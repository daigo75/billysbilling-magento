<?php
class BillysBilling_Invoicer_Model_Accounts {
    public function toOptionArray() {
        if (strlen(Mage::getStoreConfig("billy/api/api_key")) > 10) {
            // Create CURL instance
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, Mage::getStoreConfig("billy/api/api_key") . ":");

            // Get all accounts
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_URL, "https://api.billysbilling.dk/v1/accounts");
            $response = json_decode(curl_exec($ch));

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
                array('value'=>'', 'label'=>'Please enter API key above and Save Config')
            );
        }
    }
}