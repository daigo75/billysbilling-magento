<?php
class BillysBilling_Invoicer_Helper_Data extends Mage_Core_Helper_Abstract {
    public static function printError($e, $msg = null) {
        Mage::getSingleton("core/session")->addError("Billy Exception: " . $e->getMessage() . " (" . $e->getHelpUrl() . ")" . ($msg == null ? "" : "<br>Message: " . $msg));
    }
}