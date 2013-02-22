<?php
ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . dirname(__FILE__) . "/../app" . PATH_SEPARATOR . dirname(__FILE__));
ini_set("memory_limit", "512M");
require_once("Mage.php");
Mage::app("admin");
session_start();

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/helpers/TestOrder.php");

$site_to_test = TEST_URL;

define("OUTPUT_LOG_FILE", Mage::getBaseDir() . "/tests/output.log");

function formatNum($num, $dec = 4) {
    return number_format($num, $dec, ".", "");
}

function getOutput() {
    $handle = fopen(OUTPUT_LOG_FILE, "r");
    $contents = fread($handle, filesize(OUTPUT_LOG_FILE));
    $lines = explode("\n", $contents);
    fclose($handle);
    $commands = array();
    foreach ($lines as $line) {
        if (!$line) continue;
        $commands[] = json_decode($line, true);
    }
    return $commands;
}