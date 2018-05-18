<?php namespace w3tpl;
/*
  TAG: <exec>
  PURPOSE: runs a plug-in module
  HISTORY:
    2017-11-09 adapting for w3tpl2
*/
define('KS_TAG_FX_PFX','TagAPI_');	// all tag-callable fx() start with this

class xcTag_exec extends xcTag {

    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'EXEC';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //
    
    public function Go() {
	$arTagArgs = $this->GetArguments();	// get <exec> tag arguments
	$arModArgs = array();			// in case none found
	$sModName = NULL;
	$sFuncName = NULL;
	$sOutVarName = NULL;
	foreach ($arTagArgs as $sName => $sValueRaw) {
	    $sValue = xcVar::GetValue_fromExpression($sValueRaw);	// parse the value in case it is not a constant
	    //$this->AddMessage("ARGUMENT FOR [$sName] is [$sValueRaw]->[$sValue]<br>");	// debugging
	    switch ($sName) {
	      case 'f':
	      case 'func':
		$sFuncName = $sValue;
		break;
	      case 'mod':
	      case 'module':
		$sModName = $sValue;
		break;
	      case 'content':	// the named argument's value is what's between the tags
		$arModArgs[$sValue] = $input;
		break;
	      case 'output':	// write output to the named variable; otherwise try to display it
		$sOutVarName = $sValue;
		break;
	      default:		// all others are named arguments
		$arModArgs[$sName] = $sValue;
	    }
	}
	
	$ok = TRUE;
	if (is_null($sModName)) {
	    $this->AddError('<b>w3tpl tag error</b>: no module name specified');
	} elseif (is_null($sFuncName)) {
	    $this->AddError('<b>w3tpl tag error</b>: no function name specified in call to module ['.$sModName.']');
	}
	
	if ($this->IsOkay()) {
	    $sOut = xcModule::Call($sModName, KS_TAG_FX_PFX.$sFuncName, $arModArgs, $this->GetParser());
	    if (!is_null($sOutVarName)) {
		xcVar::SetVariableValue($sOutVarName,$sOut);
		$sOut = NULL;
	    }
	} else {
	    $sOut = NULL;
	}
	return $sOut;
    }
    
    // -- EVENTS -- //

}