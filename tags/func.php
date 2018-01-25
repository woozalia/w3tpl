<?php namespace w3tpl;
/*
  TAG: <func name=function_name> function code </func>
  HISTORY:
    2017-11-02 created (rewriting w3tpl1)
*/
class xcTag_func extends xcTag {

    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'FUNC';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //

/*
  NOTES:
    1. The parser apparently sets the argument's value to its name if no value is specified.
      This is a sort of bug for our purposes, but maybe it makes sense(?) in other contexts.

      One way around this is to use <w3tpl> block syntax instead of the <func> tag, but that
	isn't implemented yet.
	
      Another way around it is to have a "params" attribute with a list of param names, but that
	doesn't allow for default values.
	
      The workaround I came up with for w3tpl1 is to assume that if an attribute's value is
	also its name, then it's intended to be a parameter name (no value). This is workable
	but kind of ugly, and poses problems in edge cases (e.g. we have to have special handling

    2016-03-16 This doesn't work if the func has no other arguments.
  HISTORY:
    2011-07-24 the function data is now stored in page_props
      (previously, the definition had to be on the same page or included)
    2017-11-05 massive rewrite, yadda yadda; function object now handles storing and fetching
*/
    public function Go() {
	$sFuncName = $this->RequireArgument('name');

	$pcnt = 0;
	$arParams = array();	// create var in case there are no parameters
	$arArgs = $this->GetArguments();
	foreach ($arArgs as $sArgName => $sArgValue) {
	    if (($pcnt > 0) || ($sArgName != 'name') ) {
		if ($sArgValue == $sArgName) {
			// see Note 1 above
			$arParams[$sArgName] = null;
		} else {
			$arParamss[$sArgName] = $sArgValue;
		}
	    }
	    $pcnt++;
	}
	if (is_null($sFuncName)) {
	    // if the function name isn't explicitly set, then it's the first key:
	    $arKeys = array_keys($arArgs);
	    $sFuncName = $arKeys[0];
	    if (is_null($sFuncName)) {	// still?
		$sMsg = "<br>FUNC: Internal error - blank function name. Arguments:"
		  .fcArray::Render($args,1)
		  ;
		$this->AddError($sMsg);
	    }
	}

	// create the function object from the tag contents
	$oFunc = new xcFunc($this->GetParser(),$sFuncName);	// create function object
	$oFunc->SetDefinition($arParams,$this->GetInput());	// set the parameters and code
	//xcFunc::SaveFunction($oFunc);	// make the new function accessible	2017-11-03 not actually sure it makes sense to do this

	$oFunc->Store();
	/* 2017-11-05 function object now handles this
	$oProps = new \fcMWSiteProperties($this->GetParser());
	$oProps->SaveArray($oFunc->GetDefinition());
	*/
	return '<br><b>&gt;</b> function <b>'.$oFunc->SummaryLine().'</b>';
    }

    // -- EVENTS -- //
}