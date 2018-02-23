<?php namespace w3tpl;
/*
  TAG: <let>
*/
class xcTag_let extends xcTag_Var {

    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'LET';
    }
    
    // -- PROPERTIES -- //
    // ++ INTERNAL OBJECTS ++ //

    private $oVar = NULL;
    protected function SetVariableObject(xcVar $oVar) {
	$this->oVar = $oVar;
    }
    protected function GetVariableObject() {
	return $this->oVar;
    }
    
    // -- INTERNAL OBJECTS -- //
    // ++ EVENTS ++ //

    /*----
      HISTORY:
	2017-11-01 rewrite
	  Removing support for "load" attribute until we have a usage case.
	  It doesn't make sense to load a value you'd have to save anyway.
	    (Different page, perhaps? But when would we need that? Need usage case.)
    */
    public function Go() {
	$sVarName = $this->RequireArgument('name');

	$rtn = '';
	if ($this->IsOkay()) {
	    // get argument values
	    $doEcho = $this->ArgumentExists('echo');
	    //$doLoad = $this->ArgExists('load');
	    $doLoad = FALSE;	// unsupported until we have a usage case
	    $isNull = $this->ArgumentExists('null');
	    $doOParse = $this->ArgumentExists('oparse');
	    $sPage = $this->ArgumentValueNz('page');
	    $doPage = !is_null($sPage);
	    $doSave = $this->ArgumentExists('save');
	    
	    $oVar = xcVar::GetVariable_FromExpression($sVarName,TRUE);
	    
	    //global $wgOut;
	    //$wgOut->addHTML("FETCHED VARIABLE FROM [$sVarName] - VALUE IS [".$oVar->GetValue()."]<br>");

	    $this->SetVariableObject($oVar);
	    $sCopy = NULL;

	    if ($isNull) {

		// if "null" option, then no other inputs matter
		$oVar->ClearLocal();

/* 2018-02-10 can't think of usage case for this
	    } elseif ($doLoad) {

		// This could be either array or scalar, so we have to handle it here.
		// This means other options won't work on a load; oh well, fix later.
		$oVar->ClearLocal();

		if ($doPage) {
		    // load value(s) from a specific page
		    $sTitleRaw = $sPage;			// get namespec of page to access
		    $ovTitle = xcVar::SpawnVariable();		// create new temp Variable object
		    $ovTitle->ParseName($sTitleRaw);		// have the Variable parse the namespec and act accordingly
		    $sTitle = $ovTitle->Name;			// retrieve the Variable's name, as calculated
		    $mwoTitle = \Title::newFromText($sTitle);	// create a MW Title object
		    if (!is_object($mwoTitle)) {
			echo 'Params:'.\fcArray::Render($arParams);
			throw new exception('Did not get a title object for the page named ['.$sTitle.'], parsed from ['.$sTitleRaw.']');
		    }
		    // create a wrapper object for this MW Title's Properties
		    $oProps = new \fcMWPageProperties($mwoParser,$mwoTitle);
		    if ($oVar->IsArray()) {			// if the output var is an array...
			$oVar->LoadArray($oProps);			// ...load all the Props into it
		    } else {					// Otherwise:
			$oVar->LoadAll($oProps);			// 2016-09-22 not sure what this means
		    }
		} else {
		    // retrieve a single site-wide property value
		    $oProps = new fcMWSiteProperties($parser);
		    $sVal = $Props->LoadVal($strName);
		    $oVar->Load($sVal);
		}
		*/
	    } else {
		if (!is_object($oVar)) {
		    $this->AddError('w3tpl internal error');
		    return '';
		}
		

		$this->MainProcess();
		
	    }
    // (option) store the results in Page Properties:
	    if ($doSave) {
		global $wgTitle;	// 2018-02-10 surely there is somewhere better to get this?
	    
		$oProps = new \fcMWProperties_page($this->GetParser(),$wgTitle);
		//$oVar->Save($oProps);
		$oProps->SaveValue($oVar->GetName(),$oVar->GetValue());
	    }

    // (option) print the results:
	    if ($doEcho) {
		$sVal = $oVar->GetValue();
		if ($doOParse) {
		    $rtn = $mwoParser->recursiveTagParse($sVal);
		} else {
		    $rtn = $sVal;
		}
	    } else {
		$rtn = '';
	    }
	}
	
	return $rtn;
    }

    // -- EVENTS -- //
    // ++ EVENT SUBPROCESS ++ //	- replacing HELPER FUNCTIONS

    /*----
      RULES:
	STAGE 1: input is retrieved from somewhere and put in $sInput
	STAGE 2: operations are done on the input
      ATTRIBUTES:
	val: value to use as tag input
	arg: name of POST field to retrieve as tag input
	chr: ASCII number of character to use as tag input
	self: start with current value of specified variable
	  e.g. for incremental operations
      HISTORY:
	2011-05-31 added "tag" attribute
	2016-09-22 adapting as method of LET tag object
	2017-10-30 major rewrite of everything
	  * changing from static to dynamic
	  * using internal properties
	2017-11-01 copied code from DoFromScalar() to MainProcess(), which will replace it
    */
    protected function MainProcess() {
	global $wgRequest;

	$oVar = $this->GetVariableObject();
	
	$sStartingValue = $oVar->GetValue();	// save variable's current value before doing any processing

	// STAGE 1: get input
	
	if ($this->ArgumentExists('copy')) {
	    $sCopyName = $this->ArgumentValue('copy');		// get name of variable to copy
	    $sCopyValue = xcVar::GetVariableValue($sCopyName);	// get value of named variable
	    
	    //$this->SetInput($sCopyValue);			// replace tag input with value of other variable
	    $sInput = $sCopyValue;				// this becomes the input for tag operations
	} else {
	    // no "copy" attrib, so get input from somewhere else

	    if ($this->ArgumentExists('val')) {
		$sInput = $this->ArgumentValue('val');
	    } elseif ($this->ArgumentExists('arg')) {
		// usage case: form processing
		$sCopy = $this->ArgumentValue('arg');	// don't do any indirection from user input (possible security hole)
		$this->GetParser()->disableCache();
		$sInput = $wgRequest->getVal($sCopy); // , $strDefault) -- maybe add feature later
/* 2017-11-07 need to document how this is supposed to work
	    } elseif ($this->ArgumentExists('farg')) {
		    if (is_null($wgW3_func)) {
			$this->AddError("w3tpl error: no function active to provide arg [$sName]");
		    } else {
			$sName = strtolower($this->ArgumentValue('farg'));
			if ($wgW3_func->HasArg($strName)) {
			    $oVar->SetValue($wgW3_func->ArgVal($sName));
			} else {
			    $this->AddError('w3tpl error: function ['.$wgW3_func->Name.'] has no argument named ['.$sName.'].');
			}
		    } */
	    } elseif ($this->ArgumentExists('chr')) {
		$sInput = chr($this->ArgumentValue('chr'));
	    } elseif ($this->ArgumentExists('self')) {
		$sInput = $oVar->GetValue();
	    } else {
		// starting value is tag-pair's contents
		$sInput = $this->GetInput();
	    }

	}
	
	// appropriate input value is now in $sInput
	//$oVar->SetValue($sInput);

	// STAGE 2: operate on the input
	
	  // BINARY OPERATIONS
	
// 2011-06-01 these functions will probably need some debugging, especially in how they interact with other functions
// 2017-10-30 what do we even want these functions to do?
// 2017-11-07 also they'll need rewriting to operate on $sInput
	if ($this->ArgumentExists('plus')) {
	    throw new \exception('2017-10-30 This operation needs updating and a documented usage case.');
	    $valNew = $oVar->GetValue();	// save the newly-calculated value off to one side
	    $oVar->Fetch();			// restore the prior value
	    $oVar->SetValue += $valNew;	// add the new value to the prior value
	}
	if ($this->ArgumentExists('minus')) {
	    throw new \exception('2017-10-30 This operation needs updating and a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    $iVar->Value -= $valNew;	// subtract the new value from the prior value
	}
	if ($this->ArgumentExists('min')) {
	    throw new \exception('2017-10-30 This operation needs updating and a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    if ($valNew < $iVar->Value) {
		$iVar->Value = $valNew;
	    }
	}
	if ($this->ArgumentExists('max')) {
	    throw new \exception('2017-10-30 This operation needs updating and a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    if ($valNew > $iVar->Value) {
		$iVar->Value = $valNew;
	    }
	}

	if ($this->ArgumentExists('fmt')) {
	    throw new \exception('2017-11-07 This operation needs updating and a documented usage case.');
	    $fmt = $this->ArgumentValue('fmt');
	    $oVar->SetValue(sprintf($fmt,$oVar->GetValue()));
	}
	if ($this->ArgumentExists('encode')) {
	    throw new \exception('2017-11-07 This operation needs updating and a documented usage case.');
	    $fmt = $this->ArgumentValue('encode');
	    switch ($fmt) {
	      case 'sql':	// make safe for use as an SQL value
		$oVar->SetValue(mysql_real_escape_string($iVar->Value));	// 2017-10-30 this will need fixing, or just remove it
		break;
	    }
	}
	if ($this->ArgumentExists('tag')) {
	    throw new \exception('2017-11-07 This operation needs updating and a documented usage case.');
	    // surround result with <> to make it into an HTML tag
	    // 2017-11-07 Do we need two variants -- one to *show* a tag (&lt;tagname&gt;) and one to generate an actual tag?
	    $sOld = trim($oVar->GetValue());
	    $oVar->SetValue("<$sOld>");
	}

	    // string substitution
	$sRepl = $this->ArgumentValueNz('repl');
	$sWith = $this->ArgumentValueNz('with');
	$doRepl = !is_null($sRepl) || !is_null($sWith);
	if ($doRepl) {
	    throw new \exception('2017-11-07 This operation needs updating and a documented usage case.');
	    if (is_null($sRepl)) {
		$sRepl = $input;
	    } elseif (is_null($sWith)) {
		$sWith = $input;
	    }
	    $doIncluding = TRUE;
	    if ($this->ArgumentExists('before')) {
	    // replace everything before the mark
	    // TODO
		    $doIncluding = FALSE;
	    }
	    if ($this->ArgumentExists('after')) {
	    // replace everything after the mark
	    // TODO
		    $doIncluding = FALSE;
	    }
	    if ($this->ArgumentExists('including')) {
		    $doIncluding = TRUE;
	    }
	    if ($doIncluding) {
		    $sRes = str_replace($sRepl,$sWith,$oVar->GetValue());
	    }
	    $oVar->SetValue($sRes);
	}
	
	  // UNARY OPERATIONS
	
// 2017-12-14 IGNORE OLD COMMENT: "AT THIS POINT, $this->Value is loaded with the value we want to operate on"

	    // DEBUG
	    //$sVal = $oVar->GetValue();
	    //if ($sVal != '') {
		//echo "INPUT VALUE: [$sVal]<br>";
	    //}

// later, we may want inc/dec to imply self-operation if there is no other input...
//	but this needs to be thought through carefully. For now, require "self" to increment self.

// do processing on current value:
	if ($this->ArgumentExists('inc')) {
	    $sInput++;
	}
	if ($this->ArgumentExists('dec')) {
	    $sInput--;
	}
	if ($this->ArgumentExists('not')) {
	    $sInput = !$sInput;
	}

	if ($this->ArgumentExists('parse') || $this->ArgumentExists('pre')) {	// restoring "pre"(parse) for backwards compatibility
	    $mwoParser = $this->GetParser();
	    
	    // debugging
	    //$sVal = bin2hex($oVar->GetValue());
	    //$out = "HEX ENCODED: [$sVal]";
	    //$sVal = $oVar->GetValue();
	    $sVal = $sInput;
	    
	    $sStore = $mwoParser->recursiveTagParse($sVal);		// 2017-12-14 this *does* handle templates
	    //$sStore = $mwoParser->recursiveTagParseFully($sVal);	// not sure what else this does
	    //$sStore = $mwoParser->replaceVariables($sVal);		// *just* templates
	    //$oVar->SetValue($sStore);
	    $sInput = $sStore;
	    //if ($sVal != '') {
		//$out = "<br>STORING VALUE [$sStore]<br>";
		//die($out);
	    //}
	}
	if ($this->ArgumentExists('vars')) {
	    throw new \exception('2017-10-30 this will need some work');
	    // It needs to pull variable values from somewhere.
	    //  It previously used a w3tpl-specific descendant class, so maybe that needs to be resurrected.
	    $oTplt = new fcTemplate_array($wgOptCP_SubstStart,$wgOptCP_SubstFinish,$oVar->GetValue());
	    $oVar->SetValue($oTplt->Render());
	}
	if ($this->ArgumentExists('ucase')) {
	    $sInput = strtoupper($sInput);
	}
	if ($this->ArgumentExists('lcase')) {
	    $sInput = strtolower($sInput);
	}
	if ($this->ArgumentExists('ucfirst')) {
	    $sInput = ucfirst($sInput);
	}
	if ($this->ArgumentExists('lcfirst')) {
	    $sInput = lcfirst($sInput);
	}
	if ($this->ArgumentExists('trim')) {
	    $sInput = trim($sInput);
	}
	if ($this->ArgumentExists('len')) {
	    $sLen = $this->ArgumentValue('len');
	    if (is_numeric($sLen)) {
		$sInput = substr($sInput,0,$sLen);
	    }
	}

	  // APPEND is a BINARY op, but should probably go last

	if ($this->ArgumentExists('append')) {
	    $sOutput = $sStartingValue.$sInput;	// append processed input to the starting value
	} else {
	    $sOutput = $sInput;
	    
	}
	//echo "OUTPUT VALUE=[$sOutput]<br>";
	$oVar->SetValue($sOutput);	// store final result
	
	if ($this->ArgumentExists('save')) {
	    $oVar->SaveValue_toCurrentPage($this->GetParser());
	}
    }
    
    // -- EVENT SUBPROCESS -- //
    // ++ HELPER FUNCTIONS ++ //

    /*----
      HISTORY:
	2011-05-31 added "tag" attribute
	2016-09-22 adapting as method of LET tag object
	2017-10-30 major rewrite of everything
	  * changing from static to dynamic
	  * using internal properties
    */
    protected function DoFromScalar() {
	throw new \exception('2017-11-01 replacing this with MainProcess().');
	global $wgRequest;
	global $wgW3_func;
	global $wgOptCP_SubstStart, $wgOptCP_SubstFinish;
	
	$sRepl = $this->ArgumentValueNz('repl');
	$sWith = $this->ArgumentValueNz('with');

	$doRepl = !is_null($sRepl) || !is_null($sWith);
	$doAppend = $this->ArgumentExists('append');

	$oVar = $this->GetVariableObject();
	if ($this->ArgumentExists('val')) {
		$oVar->SetValue($this->ArgumentValue('val'));
	} elseif ($this->ArgumentExists('arg')) {
		$sCopy = $this->ArgValue['arg'];	// don't do any indirection from user input (possible security hole)
		$this->GetParser()->disableCache();
		$oVar->SetValue($wgRequest->getVal($sCopy)); // , $strDefault) -- maybe add feature later
	} elseif ($this->ArgumentExists('farg')) {
		if (is_null($wgW3_func)) {
		    $this->AddError("w3tpl error: no function active to provide arg [$sName]");
		} else {
		    $sName = strtolower($this->ArgumentValue('farg'));
		    if ($wgW3_func->HasArg($strName)) {
			$oVar->SetValue($wgW3_func->ArgVal($sName));
		    } else {
			$this->AddError('w3tpl error: function ['.$wgW3_func->Name.'] has no argument named ['.$sName.'].');
		    }
		}
	} elseif ($this->ArgumentExists('chr')) {
		$oVar->SetValue(chr($this->ArgumentValue('chr')));
	}

// AT THIS POINT, $this->Value is loaded with the value we want to operate on.

// later, we may want inc/dec to imply self-operation if there is no other input...
//	but this needs to be thought through carefully. For now, require "self" to increment self.

// do processing on current value:
	if ($this->ArgumentExists('inc')) {
	    $oVar->Increment();
	}
	if ($this->ArgumentExists('dec')) {
	    $oVar->Decrement();
	}
	if ($this->ArgumentExists('not')) {
	    $oVar->SetValue(!$oVar->GetValue());
	}

	if ($this->ArgumentExists('parse') || $this->ArgumentExists('pre')) {		// restoring "pre" for backwards compatibility
	    $oVar->SetValue($mwoParser->recursiveTagParse($oVar->GetValue()));
	}
	if ($this->ArgumentExists('vars')) {
	    throw new \exception('2017-10-30 this will need some work');
	    // It needs to pull variable values from somewhere.
	    //  It previously used a w3tpl-specific descendant class, so maybe that needs to be resurrected.
	    $oTplt = new fcTemplate_array($wgOptCP_SubstStart,$wgOptCP_SubstFinish,$oVar->GetValue());
	    $oVar->SetValue($oTplt->Render());
	}
	if ($this->ArgumentExists('ucase')) {
	    $oVar->ToUpper();
	}
	if ($this->ArgumentExists('lcase')) {
	    $oVar->ToLower();
	}
	if ($this->ArgumentExists('ucfirst')) {
	    $oVar->SetValue(ucfirst($oVar->GetValue()));
	}
	if ($this->ArgumentExists('lcfirst')) {
	    $oVar->SetValue(lcfirst($oVar->GetValue()));
	}
	if ($this->ArgumentExists('trim')) {
	    $oVar->SetValue(trim($oVar->GetValue()));
	}
	if ($this->ArgumentExists('len')) {
	    $sLen = $this->ArgValue('len');
	    if (is_numeric($sLen)) {
		$oVar->SetValue(substr($oVar->GetValue(),0,$sLen));
	    }
	}

// 2011-06-01 these functions will probably need some debugging, especially in how they interact with other functions
// 2017-10-30 what do we even want these functions to do?
	if ($this->ArgumentExists('plus')) {
	    throw new \exception('2017-10-30 This operation needs a documented usage case.');
	    $valNew = $oVar->GetValue();	// save the newly-calculated value off to one side
	    $oVar->Fetch();			// restore the prior value
	    $oVar->SetValue += $valNew;	// add the new value to the prior value
	}
	if ($this->ArgumentExists('minus')) {
	    throw new \exception('2017-10-30 This operation needs a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    $iVar->Value -= $valNew;	// subtract the new value from the prior value
	}
	if ($this->ArgumentExists('min')) {
	    throw new \exception('2017-10-30 This operation needs a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    if ($valNew < $iVar->Value) {
		$iVar->Value = $valNew;
	    }
	}
	if ($this->ArgumentExists('max')) {
	    throw new \exception('2017-10-30 This operation needs a documented usage case.');
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    $iVar->Fetch();			// restore the prior value
	    if ($valNew > $iVar->Value) {
		$iVar->Value = $valNew;
	    }
	}

	if ($this->ArgumentExists('fmt')) {
	    $fmt = $this->ArgumentValue('fmt');
	    $oVar->SetValue(sprintf($fmt,$oVar->GetValue()));
	}
	if ($this->ArgumentExists('encode')) {
	    $fmt = $this->ArgumentValue('encode');
	    switch ($fmt) {
	      case 'sql':	// make safe for use as an SQL value
		$oVar->SetValue(mysql_real_escape_string($iVar->Value));	// 2017-10-30 this will need fixing, or just remove it
		break;
	    }
	}
	if ($this->ArgumentExists('tag')) {
	    // surround result with <> to make it into an HTML tag
	    $sOld = trim($oVar->GetValue());
	    $oVar->SetValue("<$sOld>");
	}
	if ($doRepl) {
	    if (is_null($sRepl)) {
		$sRepl = $input;
	    } elseif (is_null($sWith)) {
		$sWith = $input;
	    }
	    $doIncluding = TRUE;
	    if ($this->ArgumentExists('before')) {
	    // replace everything before the mark
	    // TODO
		    $doIncluding = FALSE;
	    }
	    if ($this->ArgumentExists('after')) {
	    // replace everything after the mark
	    // TODO
		    $doIncluding = FALSE;
	    }
	    if ($this->ArgumentExists('including')) {
		    $doIncluding = TRUE;
	    }
	    if ($doIncluding) {
		    $sRes = str_replace($sRepl,$sWith,$oVar->GetValue());
	    }
	    $oVar->SetValue($sRes);
	}

// AT THIS POINT, we have the semi-final value to be stored
// -- if it's being appended, then get the old value and prepend it to the new:

	if ($doAppend) {
	    $valNew = $oVar->GetValue();	// save the newly-calculated value off to one side
	    $oVar->Fetch();			// restore the prior value
	    $oVar->Append($valNew);		// append the new value to the prior value
	}
    }
    protected function DoFromArray () {
	throw new \exception('2017-11-01 does anything actually use this now?');
	$doSort = $this->ArgumentExists('sort');

	if ($doSort) {
	    $oVar->DoSort($this->ArgumentExists('rev'),$this->ArgumentExists('val'));
	}
    }
}