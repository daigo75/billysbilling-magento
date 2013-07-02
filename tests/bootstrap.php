<?php
ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . dirname(__FILE__) . "/../app" . PATH_SEPARATOR . dirname(__FILE__));
ini_set("memory_limit", "512M");
require_once("Mage.php");
Mage::app("admin");
session_start();

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/helpers/TestOrder.php");

checkConfig($testConfig);

define("TEST_OUTPUT_LOG_FILE", Mage::getBaseDir() . "/tests/output.log");

function checkConfig($config) {
    $errors = array();
    if (!is_array($config)) {
        $errors[] = "Pre-test error: Config is missing";
    }
    if (!$config["currency"]) {
        $errors[] = "Pre-test error: Currency is missing";
    }
    if (!is_array($config["products"])) {
        $errors[] = "Pre-test error: Products are missing";
    }
    if (count($config["products"]) < 2) {
        $errors[] = "Pre-test error: Too few products";
    }
    foreach ($config["products"] as $i => $product) {
        foreach (array("id", "name", "sku", "price", "shipping_price") as $unit) {
            if (!$product[$unit] && !is_numeric($product[$unit])) {
                $errors[] = "Pre-test error: Missing " . $unit . " for product " . ($i+1);
            }
        }
    }
    if (!is_array($config["addresses"])) {
        $errors[] = "Pre-test error: Addresses are missing";
    }
    foreach ($config["addresses"] as $i => $address) {
        foreach (array("firstname", "lastname", "street", "city", "postcode", "telephone", "email", "country_id") as $unit) {
            if (!$address[$unit] && !is_numeric($address[$unit])) {
                $errors[] = "Pre-test error: Missing " . $unit . " for address " . ($i+1);
            }
        }
    }
    if (count($errors) > 0) {
        echo implode("\n", $errors);
        exit;
    }
}

function formatNum($num, $dec = 4) {
    return number_format($num, $dec, ".", "");
}

function getOutput() {
    $handle = fopen(TEST_OUTPUT_LOG_FILE, "r");
    $contents = fread($handle, filesize(TEST_OUTPUT_LOG_FILE));
    $lines = explode("\n", $contents);
    fclose($handle);
    $commands = array();
    foreach ($lines as $line) {
        if (!$line) continue;
        $commands[] = json_decode($line, true);
    }
    return $commands;
}