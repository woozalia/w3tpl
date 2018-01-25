<?php namespace w3tpl;
/*
  PURPOSE: specialized string template
  HISTORY:
    2017-11-09 rewriting for w3tpl2
*/

class xcStringTemplate_w3tpl extends \fcTemplate_array {

    // OVERRIDE
    public function GetVariableValue($sName) {
	return xcVar::GetVariableValue($sName);
    }
}
