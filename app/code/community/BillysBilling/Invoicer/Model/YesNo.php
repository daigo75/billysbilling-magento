<?php
class BillysBilling_Invoicer_Model_YesNo {
    public function toOptionArray() {
        return array(
            array(
                "value" => false,
                "label" => "No"
            ),
            array(
                "value" => true,
                "label" => "Yes"
            )
        );
    }
}