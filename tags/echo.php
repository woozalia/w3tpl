<?php namespace w3tpl;
/*
  TAG: <echo>
  NOTE: Maybe <echo> is basically the same thing as <get>, and they should be merged?
*/
class xcTag_echo extends xcTag_Var {
    // ++ PROPERTIES ++ //

    // CEMENT
    protected function TagName() {
	return 'ECHO';
    }
    
    // -- PROPERTIES -- //
    // ++ EVENTS ++ //

    /*----
      HISTORY:
	2017-11-08 rewriting as tag driver, based on w3tpl1 code
    */
    public function Go() {
    
	// INPUT SOURCES
    
	// this probably has a lot in common with <let>
	if ($this->ArgumentExists('chr')) {
	    $sValueIn = chr($this->ArgumentValue('chr'));
	} else if ($this->ArgumentExists('var')) {
	    $sValueIn = xcVar::GetVariableValue('var');
	} else if ($this->ArgumentExists('val')) {
	    $sValueIn = $this->ArgumentValue('val');
	} else {
	    $sValueIn = $this->GetInput();
	}

	// MAIN OPERATIONS (convert input to output)
	
	if ($this->ArgumentExists('strip')) {	// should be something like "reveal", really
	    throw new \exception('2017-11-09 Where is this even used?');
	    // QUERY: should this be mutually exclusive with 'vars'? Depends what it's for, I guess.
	    $sContentOut = $this->DisplayMarkup($sValueIn);
	} else {
	    if ($this->ArgumentExists('vars')) {
		$oTplt = new xcStringTemplate_w3tpl($wgOptCP_SubstStart,$wgOptCP_SubstFinish,$sValueIn);
		$sContentOut = $oTplt->Render();	// *possibly* we will want RenderRecursive(), but let's keep it simple for now...
	    } else {
		// no operations
		$sContentOut = $sValueIn;
	    }
	}
	
	// POST-PROCESSING (operate on output value)
	$sOut = NULL;
	
	$doRaw = FALSE;
	$doRawReq = $this->ArgumentExists('raw');
	if ($doRawReq) {
	    $doRaw = xcW3TPL::GetAllowRawHTML();
	}
	if ($doRaw) {
	    $sOut = $sContentOut;
	} else {
	    // not doing raw output
	    if ($doRawReq) {
		// raw output was requested - display error indicator
		$sOut = 
		  '<span title="raw output not permitted on this page" style="color:red; font-weight:bold;">(i)</span>'
		  ;
	    }
	    $sOut .= $this->GetParser()->recursiveTagParse($sContentOut);
	}
	if ($this->ArgumentExists('tag')) {	// surround result with <> to make it into an HTML tag
	    $sOut = "<$sOut>";
	}
	if ($this->ArgumentExists('nocrlf')) {
	    $sValueOut = strtr($sValueOut,"\n\r",'  ');	// replace newline chars with spaces
	}
	// added 2017-12-15 for debugging
	if ($this->ArgumentExists('encode')) {
	    $sValueOut = htmlspecialchars($sValueOut);	// encode all HTML-significant characters so we can see what is being sent
	}

	/* 2017-11-09 This was always kinda klugey; I think I want further tests before re-implementing. Might not be needed.
	$doNow = 'now');
	if (!$doNow) {
	    W3AddEcho($out);	// output when appropriate
	    $out = '';		// don't output directly
	} */
	
	// maybe this should go in xcTag
	$this->SetIsolateOutput($this->ArgumentExists('isolate'));

	return $sOut;
    }
    
    // -- EVENTS -- //
    // ++ POST-PROCESSING ++ //

    // ACTION: make sure markup characters are displayed rather than interpreted
    protected function DisplayMarkup($s) {
	$out = htmlspecialchars($s);
//	$out = str_replace ( '<','&lt;',$out );
//	$out = str_replace ( '>','&gt;',$out);
	$out = str_replace ( '[','&#91;',$out );
	$out = str_replace ( ']','&#93;',$out );
	$out = str_replace ( chr(7),'<b>^G</b>',$out);
	$out = str_replace ( chr(127),'<b>del</b>',$out);
	if ($iRepNewLine) {
		$out = str_replace ( chr(10),'<b>^J</b>',$out);
		$out = str_replace ( chr(13),'<b>^M</b>',$out);
	}
	return $out;
    }
    
    // -- POST-PROCESSING -- //

}
