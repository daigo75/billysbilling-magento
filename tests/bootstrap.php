<?php
ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . dirname(__FILE__) . "/../app" . PATH_SEPARATOR . dirname(__FILE__));
ini_set("memory_limit", "512M");
require_once("Mage.php");
Mage::app("admin");
session_start();

require_once(dirname(__FILE__) . "/config.php");

$site_to_test = TEST_URL;