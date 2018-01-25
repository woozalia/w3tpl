<?php namespace w3tpl;
/*
  TAG: <call>
*/
class xcTag_call extends xcTag {
    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'CALL';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //
    
    public function Go() {
	$arArgsRaw = $this->GetArguments();
	$pcnt = 0;
	$arArgs = array();
	foreach ($arArgsRaw as $sNameRaw => $sValueRaw) {
	    $sName = strtolower(trim($sNameRaw));
	    if ($pcnt) {
		// every arg except the first one
		$sValue = xcVar::GetValue_fromExpression($sValueRaw);
		$arArgs[$sName] = $sValue;
	    } else {
		// we're looking at the very first arg -- must be the function's name
		if ($sName == 'func') {	// might be given as "func=funcname"
		    $sFuncName = strtolower(xcVar::GetValue_fromExpression($sValueRaw));
		} else {		// ...but just naming the function as the first arg (no value) is also ok
		    $sFuncName = strtolower(xcVar::GetValue_fromExpression($sNameRaw));
		}

		/* 2017-11-05 OLD CODE
		// there's probably a smoother way to do this...
		if (!is_array($wgW3_funcs)) {
		    $wgW3_funcs = array();
		}
		if (array_key_exists($funcName,$wgW3_funcs)) {
		  // function already loaded
		    $objFunc = $wgW3_funcs[$funcName];
		    $wgW3_func = $objFunc;
		} else {
		    $objFunc = NULL;	// in case we can't load it

		  // try to find it in page_props
		    $objProps = new w3cStoredFunctions($parser);
		    if ($funcName == '') {
			echo 'Calling function with no name. Arguments:<pre>'.print_r($args,TRUE).'</pre>';
			throw new exception('Missing parameter in tag.');
		    }
		    $objFunc = $objProps->LoadFunc($funcName);
		    $wgW3_func = $objFunc;
		    $wgW3_funcs[$funcName] = $objFunc;
		}
		if (!is_object($objFunc)) {
		    $wgOut->AddHTML("<span class=previewnote><strong>W3TPL ERROR</strong>: Function [$funcName] is undefined.</span><br>* SQL: $sql");
		    return NULL;
		}
		$objFunc->ResetArgs();
		*/
	    }
	    $pcnt++;
	}
	$out = NULL;
	$oFunc = new xcFunc($this->GetParser(),$sFuncName);
	$oFunc->Fetch();		// retrieve fx() definition from database
	
	$sInput = $this->GetInput();
	if ($sInput) {
	    // pass the data between <call ...> and </call> to the function as a special argument
	    $arArgs['*tag_input'] = $sInput;	// TODO: make *tag_input a constant
	}
	
	$oFunc->SetArguments($arArgs);	// pass arguments to use for parameters

//	if ($pcnt) {
	    $out .= $oFunc->Execute();
/*	} else {
	    $wgOut->AddHTML('<span class=previewnote><strong>W3TPL ERROR</strong>: Function "'.$strName.'" not loaded; probably an internal error.</span>');
	}	// 2017-11-05 this was skipping fx() execution if there were no arguments, which doesn't make sense */
	
	return $out;
    }
}
