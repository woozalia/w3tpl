<?php
/*
  HISTORY: see http://htyp.org/W3TPL/history
  */

$wgOptCP_SubstStart = '[$';
$wgOptCP_SubstFinish = '$]';

$w3step = FALSE;
$w3stop = FALSE;

$wgExtensionCredits['other'][] = array(
	'name' => 'W3TPL',
	'description' => 'Woozle\'s Wacky Wiki Text Processing Language',
	'author' => 'Woozle (Nick) Staddon',
	'url' => 'http://htyp.org/W3TPL',
	'version' => '0.66 2016-03-16'
);
$dir = dirname(__FILE__) . '/';

define('ksFuncInit','efW3TPLInit');

//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
        $wgHooks['ParserFirstCallInit'][] = ksFuncInit;
} else { // Otherwise do things the old fashioned way
        $wgExtensionFunctions[] = ksFuncInit;
}
$wgHooks['LanguageGetMagic'][] = 'efW3_LanguageGetMagic';
$wgHooks['ParserAfterTidy'][] = 'efW3_ParserAfterTidy';
$wgHooks['OutputPageBeforeHTML'][] = 'efW3_OutputPageBeforeHTML';

$wgAutoloadClasses['SpecialW3TPL']		= $dir . 'SpecialW3TPL.class.php';
$wgAutoloadClasses['w3cStoredFunctions']	= $dir . 'w3tpl.body.php';
$wgAutoloadClasses['clsW3Var']			= $dir . 'w3tpl.vars.php';

$wgW3_echoBuffer = '';

function efW3TPLInit() {
        global $wgParser;
	global $wgExtW3TPL;
	global $wgW3RawOk;

// hook in <tag>-style functions:
        $wgParser->setHook( 'arg',	'efW3Arg' );
        $wgParser->setHook( 'call',	'efW3Call' );
        $wgParser->setHook( 'class',	'efW3Class' );
        $wgParser->setHook( 'dump',	'efW3Dump' );
        $wgParser->setHook( 'echo',	'efW3Echo' );
        $wgParser->setHook( 'else',	'efW3Else' );
        $wgParser->setHook( 'exec',	'efW3Exec' );
        $wgParser->setHook( 'for',	'efW3For' );
        $wgParser->setHook( 'func',	'efW3Func' );
        $wgParser->setHook( 'get',	'efW3Get' );
        $wgParser->setHook( 'hide',	'efW3Hide' );
        $wgParser->setHook( 'if',	'efW3If' );
        $wgParser->setHook( 'let',	'efW3Let' );
        $wgParser->setHook( 'load',	'efW3Load' );
        $wgParser->setHook( 'save',	'efW3Save' );
        //$wgParser->setHook( 'trace',	'efW3Trace' );
        $wgParser->setHook( 'w3tpl',	'efW3TPLRender' );
        $wgParser->setHook( 'xploop',	'efW3Xploop' );

        return true;
}
// 2015-09-28 I don't remember what this does. It may have been an attempt at {{#this}} style of tag, but I don't think it worked.
function efW3_LanguageGetMagic( &$magicWords, $langCode = "en" ) {
    switch ( $langCode ) {
        default:
            $magicWords['w3xploop']	= array ( 0, 'w3xploop' );
            $magicWords['w3xpcount']	= array ( 0, 'w3xpcount' );
    }
    return true;
}


function TrueFalse($iVar) {
	return $iVar?'TRUE':'FALSE';
}
function W3VarExists($iName) {
	global $wgW3Vars;

	$strName = strtolower($iName);
	return isset($wgW3Vars[$strName]);
}
function W3KillVar($iName) {
	global $wgW3Vars;

	$strName = strtolower($iName);
	unset($wgW3Vars[$strName]);
}
function W3SetVar($iName, $iValue, $iAppend = FALSE) {
	global $wgW3Vars, $wgW3_doTrace_vars;

	$strName = strtolower($iName);
	if ($iAppend && isset($wgW3Vars[$strName])) {
		$wgW3Vars[$strName] .= $iValue;
		if ($wgW3_doTrace_vars) {
			W3AddTrace(' $['.ShowHTML($strName).'] += ['.$iValue.'] => ['.$wgW3Vars[$strName].']');
		}
	} else {
		$wgW3Vars[$strName] = $iValue;
		if ($wgW3_doTrace_vars) {
			W3AddTrace(' $['.ShowHTML($strName).'] = ['.$iValue.']');
		}
	}
}
function W3GetSysData($iName) {
    global $wgTitle,$wgUser,$wgRequest;
    global $wgW3_doTrace_vars;
    global $wgW3_data;
    global $sql;

    W3AddTrace('W3GetSysData('.$iName.')');
    W3TraceIndent();

    $out = '';

    $strName = $iName;
    $strParts =  explode('.', $strName);
    if (isset($strParts[1])) {
	$strParam = strtolower($strParts[1]);
    } else {
	$strParam = NULL;
    }
    $strPart0 = $strParts[0];

    $sDbg = "PART 0: [$strPart0] PART 1: [$strParam]";
    W3AddTrace($sDbg);
    switch ($strPart0) {
      case 'title':
	switch ($strParam) {
	  case 'id':
	    $out = $wgTitle->getArticleID();
	    break;
	  case 'full':	// namespace:subject
	    $out = $wgTitle->getPrefixedText();
	    break;
	  case 'subject':	// just the part after the namespace and before any slashes
	    $out = $wgTitle->getBaseText();
	    break;
	  case 'name':		// just the part after the namespace
	    $out = $wgTitle->getText();
	    break;
	  case 'url':
	    $out = $wgTitle->getFullURL();
	    break;
	  case 'dbkey':		// as stored in db tables
	    $out = $wgTitle->getDBkey();
	    break;
	}
	break;
      case 'row':
	$strFld = $strParts[1];	// field name
	$strSet = '@@row@@';

	$out = NULL;
// This is a horrible kluge necessitated by the 2 different ways of accessing data in <for>
	//clsModule::LoadFunc('NzArray');
	$val = clsArray::Nz($wgW3_data,$strSet);
	if (!is_null($val)) {
	    $vRow = $val;
	    if (is_array($vRow)) {
		if (array_key_exists($strFld,$vRow)) {
		    $out = $vRow[$strFld];
		} else {
		    $strErr = 'Dataset ['.$strSet.'] has no field named ['.$strFld.'].';
		    //throw new exception($strErr);
		    $out = '<b>Error</b>: '.$strErr;
		}
	    } else {
		if (isset($vRow->$strFld)) {
		    $out = $vRow->$strFld;
		}
	    }
	}
	W3AddTrace('DATA['.$strSet.']['.$strFld.'] => ['.$out.']');
	break;
      case 'mem':
	$out = memory_get_usage(TRUE);
	break;
      case 'user':
	switch ($strParam) {
	  case 'login':
	    $out = $wgUser->getName();
	    break;
	  case 'dbkey':
	    $out = $wgUser->getTitleKey();
	    break;
	  case 'id':
	    $out = $wgUser->getID();
	    break;
	  case 'can':
	    $out = $wgUser->isAllowed($strParts[2]);
	    break;
	  case 'rights':
	    $arrRights = $wgUser->getRights();
	    $out = '';
	    foreach ($arrRights as $key=>$val) {
		$out .= '\\'.$val;
	    }
	    break;
	  case 'email':
	    $out = $wgUser->getEmail();
	    break;
	  case 'name':
	    $out = $wgUser->getRealName();
	    break;
	  default:
	    $out = '?'.$strParam;
	}
	break;
      case 'http':
	$strName = clsArray::Nz($strParts,2);
	switch ($strParam) {
	  case 'get':
	    if (empty($strName)) {
		    # TO DO: return raw query
	    } else {
		    //$out = $wgRequest->getText($strParam);
		    if (isset($_GET[$strName])) {
			$out = $_GET[$strName];
		    } else {
			$out = NULL;	// maybe this should be a list? or raw query, unparsed?
		    }
	    }
	    break;
	  case 'post':
	    if ($strParam) {
		    if (isset($_POST[$strParam])) {
			    $out = $_POST[$strParam];
		    } else {
			    $out = NULL;	// maybe this should be a list?
		    }
	    }
	    W3AddTrace(' GET POST ['.ShowHTML($strParam).']: ['.$out.']');
	    break;
	  case 'req':
	    if ($strParam) {
		//$out = $wgRequest->getVal($strName);
		$out = clsArray::Nz($_REQUEST,$strName);
		W3AddTrace('NAME=['.$strName.'] RESULT=['.$out.']');
	    } else {
		$out = NULL;	// maybe this should be a list?
	    }
	    break;
	}
	break;
      case 'query':	// DEPRECATED -- same as http.get; eliminate eventually
	if ($strParam == '') {
		// never used
	} else {
	    //$out = $wgRequest->getText($strParam);
	    if (isset($_GET[$strParam])) {
		$out = $_GET[$strParam];
	    } else {
		$out = '';
	    }
	}
	break;
      case 'post':	// DEPRECATED -- same as http.post; eliminate eventually
	if ($strParam) {
	    if (array_key_exists($strParam,$_POST)) {
		$out = $_POST[$strParam];
	    } else {
		$out = NULL;
	    }
	}
	W3AddTrace(' GET POST ['.ShowHTML($strParam).']: ['.$out.']');
	break;
      case 'env':
	  if ($strParam) {
		  if (array_key_exists($strParam,$_ENV)) {
			  $out = $_ENV[$strParam];
		  } else {
			  $out = NULL;
		  }
	  }
	  break;
      case 'db':
	switch ($strParam) {
	  case 'sql':
	    $out = $sql;
	    break;
	  default:
	    $out = 'unknown subtype for db: ['.$strParam.']';
	}
	break;
      /* This is mainly for debugging (later it may be useful for library maintenance), so I'm
	not going to try to make it forward-compatible with the changes I expect to make later,
	i.e. having functions as an object type.
	SYNTAX: @func.name.def|page
      */
      case 'func':
      global $wgW3_funcs;

	$fname = $strParam;
	$fobj = $wgW3_funcs[$fname];

	switch ($strParts[2]) {
	  case 'def':
	    $out = $fobj->dump();
	    break;
	  case 'page':
	    // not implemented yet
	    break;
	}
	break;
    }
    W3AddTrace('GETSYSDATA-'.$strParts[0].' ['.ShowHTML($iName).']: ['.$out.']');

    W3AddTrace('/W3GetSysData('.$iName.')');
    W3TraceOutdent();

    return $out;
}
function W3GetExpr($iExpr) {
// check expression for $, meaning it's actually a reference to a variable
// If found, return value of variable - otherwise return original string.
	global $wgW3Trace_indent;

	W3AddTrace('W3GetExpr('.$iExpr.')');
	$wgW3Trace_indent++;

	$objVar = new clsW3Var();
	$objVar->ParseExpr_toValue($iExpr);
	$objVar->Trace();
	//$objVar->Fetch();	// this overwrites - don't use!
	$strOut = $objVar->Value;

	$wgW3Trace_indent--;
	W3AddTrace('/W3GetExpr('.$iExpr.')');

	return $strOut;
}
function W3GetVal($iName,$iIndex=NULL) {
// gets value of given variable
// checks function arguments, if function is defined
	$objVar = new clsW3Var();
	$objVar->ParseExpr_toName($iName);
	if (!is_null($iIndex)) {
		$objVar->SetIndex($iIndex);
	}
	$objVar->Trace();
	$objVar->Fetch();

	$strVal = $objVar->Value;
	return $strVal;
}
function W3GetEcho() {
	global $wgW3_echoBuffer;

	$out = $wgW3_echoBuffer;
	$wgW3_echoBuffer = '';
	return $out;
}
/*----
  TODO: When there's no hiding active, this should output immediately.
*/
function W3AddEcho($iVal) {
	global $wgW3_echoBuffer;

	$wgW3_echoBuffer .= $iVal;
}
function W3AddTrace($iLine,$iInd=0) {
	global $wgW3Trace, $wgW3Trace_indents, $wgW3Trace_indent;
	global $wgW3_doTrace;
	global $wgW3_TraceCount;

	if ($wgW3_doTrace) {
		if ($wgW3_TraceCount < 2000) {
			$wgW3Trace[] = $iLine;
			$wgW3Trace_indents[] = $wgW3Trace_indent;
			$wgW3Trace_indent += $iInd;
		}
		$wgW3_TraceCount++;
	}
/**/
}
function W3TraceIndent() {
    global $wgW3Trace_indent;

    $wgW3Trace_indent++;
}
function W3TraceOutdent() {
    global $wgW3Trace_indent;

    $wgW3Trace_indent--;
}
function W3EnterTag($iTag, array $iArgs) {
    $strTrace = '&lt;'.$iTag;
    foreach ($iArgs as $name => $value) {
	$strTrace .= ' '.$name.'="'.$value.'"';
    }
    $strTrace .= '&gt;';
    W3AddTrace($strTrace);
    W3TraceIndent();
}
function W3ExitTag($iTag) {
    $strTrace = '&lt;/'.$iTag.'&gt;';
    W3TraceOutdent();
    W3AddTrace($strTrace);
}

function W3Status_RawOk() {
	global $wgTitle;
	global $wgW3_func;
	global $wgW3TPLSettings;
	global $wgW3_Override_RawOk;

	if (isset($wgW3_Override_RawOk)) {
	    $isProt = $wgW3_Override_RawOk;
	} else {
	    if ($wgW3TPLSettings['raw-ok']) {
		    $isProt = TRUE;
	    } else {
		    $isProt = $wgTitle->isProtected ('edit');
	    }
	    if (!$isProt) {
		    if (is_object($wgW3_func)) {
			    $isProt = $wgW3_func->isOkRaw;
		    }
	    }
	    W3AddTrace('IS RAW ok in ['.$wgTitle->getFullText().']: '.TrueFalse($isProt));
	}
	return $isProt;
}
function W3Status_SQLOk() {
    global $wgTitle;
    global $wgW3_func;
    global $wgW3TPLSettings;

    if ($wgW3TPLSettings['sql-ok']) {
	$isOk = TRUE;
    } else {
	$isProt = $wgTitle->isProtected ('edit');
	if ($isProt) {
	    $isOk = TRUE;
	} else {
	    if (is_object($wgW3_func)) {
		$isOk = $wgW3_func->isOkSQL;
	    } else {
		$isOk = FALSE;
	    }
	}
    }
    // FUTURE: print a message if SQL is forbidden
    //W3AddTrace('IS SQL allowed in ['.$wgTitle->getFullText().']: '.TrueFalse($isProt));
    return $isOk;
}
/*----
  HISTORY:
    2011-05-31 added "tag" attribute
*/
function W3Let_scalar( $iVar, $iArgs, $input, $parser ) {
	global $wgRequest;
	global $wgW3_func;
	global $wgOptCP_SubstStart, $wgOptCP_SubstFinish;
/*
	$strRepl = W3GetExpr($iArgs->GetVal('repl'));
	$strWith = W3GetExpr($iArgs->GetVal('with'));
*/
	$strRepl = $iArgs->GetArgVal('repl');
	$strWith = $iArgs->GetArgVal('with');

	$doRepl = !is_null($strRepl) || !is_null($strWith);
	$doAppend = $iArgs->Exists('append');

// TRACING:
	$strTrace = ' - &lt;LET&gt; scalar:';
	if (!is_null($strRepl)) {
		$strTrace .= ' repl=&ldquo;'.$strRepl.'&rdquo;';
	}
	if (!is_null($strWith)) {
		$strTrace .= ' repl=&ldquo;'.$strWith.'&rdquo;';
	}
	if ($doAppend) {
		$strTrace .= ' APPEND';
	}
	W3AddTrace($strTrace);

	if ($iArgs->Exists('val')) {
		$iVar->Value = $iArgs->GetArgVal('val');
		$strTrace = ' - LET VAL: expr=['.ShowHTML($iVar->Value).']';
		W3AddTrace($strTrace);
	} elseif ($iArgs->Exists('arg')) {
		$strCopy = $iArgs->vArgs['arg'];	// don't do any indirection from user input (possible security hole)
		$parser->disableCache();
		$iVar->Value = $wgRequest->getVal($strCopy); // , $strDefault) -- maybe add feature later
	} elseif ($iArgs->Exists('farg')) {
		if (is_null($wgW3_func)) {
			W3AddTrace(' - ERROR: no function active to provide arg ['.$strName.']');
		} else {
			$strName = strtolower($iArgs->GetArgVal('farg'));
			if ($wgW3_func->HasArg($strName)) {
				$iVar->Value = $wgW3_func->ArgVal($strName);
				W3AddTrace(' - ARG['.$strName.'] => &ldquo;'.$iVar->Value.'&rdquo;');
			} else {
				W3AddTrace(' - ERROR: function ['.$wgW3_func->Name.'] has no argument named ['.$strName.'].');
			}
		}
	} elseif ($iArgs->Exists('chr')) {
		$iVar->Value = chr($iArgs->GetArgVal('chr'));
	}

// AT THIS POINT, $this->Value is loaded with the value we want to operate on.

// later, we may want inc/dec to imply self-operation if there is no other input...
//	but this needs to be thought through carefully. For now, require "self" to increment self.

// do processing on current value:
	if ($iArgs->Exists('inc')) {
		$iVar->Value++;
	}
	if ($iArgs->Exists('dec')) {
		$iVar->Value--;
	}
	if ($iArgs->Exists('not')) {
	    $iVar->Value = !$iVar->Value;
	}

	if ($iArgs->Exists('parse') || $iArgs->Exists('pre')) {		// restoring "pre" for backwards compatibility
		$iVar->Value = $parser->recursiveTagParse($iVar->Value);
	}
	if ($iArgs->Exists('vars')) {
		W3AddTrace(' LET VARS before ['.ShowHTML($iVar->Value).']');
		$objTplt = new clsStringTemplate_w3tpl($wgOptCP_SubstStart,$wgOptCP_SubstFinish);
		$objTplt->Value = $iVar->Value;
		$iVar->Value = $objTplt->Replace();
		W3AddTrace(' LET VARS after ['.ShowHTML($iVar->Value).']');
	}
	if ($iArgs->Exists('ucase')) {
		$iVar->Value = strtoupper($iVar->Value);
	}
	if ($iArgs->Exists('lcase')) {
		$iVar->Value = strtolower($iVar->Value);
	}
	if ($iArgs->Exists('ucfirst')) {
		$iVar->Value = ucfirst($iVar->Value);
	}
	if ($iArgs->Exists('lcfirst')) {
		$iVar->Value = lcfirst($iVar->Value);
	}
	if ($iArgs->Exists('trim')) {
		$iVar->Value = trim($iVar->Value);
	}
	if ($iArgs->Exists('len')) {
		$strLen = $iArgs->GetArgVal('len');
		if (is_numeric($strLen)) {
			$iVar->Value = substr($iVar->Value,0,$strLen);
		}
	}

// 2011-06-01 these functions will probably need some debugging, especially in how they interact with other functions
	if ($iArgs->Exists('plus')) {
		$valNew = $iVar->Value;		// save the newly-calculated value off to one side
		$iVar->Fetch();			// restore the prior value
		$iVar->Value += $valNew;	// add the new value to the prior value
	}
	if ($iArgs->Exists('minus')) {
		$valNew = $iVar->Value;		// save the newly-calculated value off to one side
		$iVar->Fetch();			// restore the prior value
		$iVar->Value -= $valNew;	// subtract the new value from the prior value
	}
	if ($iArgs->Exists('min')) {
		$valNew = $iVar->Value;		// save the newly-calculated value off to one side
		$iVar->Fetch();			// restore the prior value
		if ($valNew < $iVar->Value) {
		    $iVar->Value = $valNew;
		}
	}
	if ($iArgs->Exists('max')) {
		$valNew = $iVar->Value;		// save the newly-calculated value off to one side
		$iVar->Fetch();			// restore the prior value
		if ($valNew > $iVar->Value) {
		    $iVar->Value = $valNew;
		}
	}

	if ($iArgs->Exists('fmt')) {
	    $fmt = $iArgs->GetArgVal('fmt');
	    $iVar->Value = sprintf($fmt,$iVar->Value);
	}
	if ($iArgs->Exists('encode')) {
	    $fmt = $iArgs->GetArgVal('encode');
	    switch ($fmt) {
	      case 'sql':	// make safe for use as an SQL value
		$iVar->Value = mysql_real_escape_string($iVar->Value);
		break;
	    }
	}
	if ($iArgs->Exists('tag')) {
	    // surround result with <> to make it into an HTML tag
	    $iVar->Value = '<'.trim($iVar->Value).'>';
	}
	if ($doRepl) {
		if (is_null($strRepl)) {
			$strRepl = $input;
		} elseif (is_null($strWith)) {
			$strWith = $input;
		}
		$doIncluding = TRUE;
		if ($iArgs->Exists('before')) {
		// replace everything before the mark
		// TODO
			$doIncluding = FALSE;
		}
		if ($iArgs->Exists('after')) {
		// replace everything after the mark
		// TODO
			$doIncluding = FALSE;
		}
		if ($iArgs->Exists('including')) {
			$doIncluding = TRUE;
		}
		if ($doIncluding) {
			$strRes = str_replace($strRepl,$strWith,$iVar->Value);
		}
		W3AddTrace('LET REPLACE ['.$strRepl.'] WITH ['.$strWith.'] => ['.$strRes.'] in &ldquo;'.$iVar->Value.'&rdquo;');
		$iVar->Value = $strRes;
	}
	W3AddTrace('LET ['.$iVar->Name.'] &larr; &ldquo;'.$iVar->Value.'&rdquo;');

// AT THIS POINT, we have the semi-final value to be stored
// -- if it's being appended, then get the old value and prepend it to the new:

	if ($doAppend) {
	    $valNew = $iVar->Value;		// save the newly-calculated value off to one side
	    W3AddTrace(' - APPEND &ldquo;'.$valNew.'&rdquo;');
	    $iVar->Fetch();			// restore the prior value
	    $iVar->Value .= $valNew;	// append the new value to the prior value
	}
}
function W3Let_array ( $iVar, $iArgs, $input, $parser ) {
	$doSort = $iArgs->Exists('sort');

	if ($doSort) {
		$iVar->DoSort($iArgs->Exists('rev'),$iArgs->Exists('val'));
	}
}
// **********
// === BEGIN tag functions
/*-----
  TAG: <arg>
  HOW DOES THIS WORK? Maybe this is another way of passing tag-bracketed data? I forgot I created this tag...
*/
function efW3Arg( $input, $args, $parser ) {
	global $wgW3_func;

	if (isset($args['name'])) {
		$name = $args['name'];
	} else {
		$name = '';
	}
	if (isset($args['pre'])) {
		$value = $parser->recursiveTagParse($input);
	} else {
		$value = $input;
	}
	$strTrace = ' +ARG: '.$wgW3_func->LoadArg($value,$name);
	W3AddTrace($strTrace);
}
/*-----
  TAG: <call>
  NOTES: We might later split this into two tags, one for already-loaded functions and one that
    looks them up in the database, but I'm thinking that using already-loaded functions will actually
    be the exception rather than the rule, so no need to optimize for it.
    ...and actually, if the function has been loaded, then access is almost as quick as before, so
    why optimize further?
*/
function efW3Call( $input, $args, $parser ) {
	global $wgW3_funcs,$wgW3_func;
	global $wgOut;

	W3AddTrace('{CALL:');
	$pcnt = 0;
	$strTrace = NULL;

	foreach ($args as $name => $value) {
	    $strName = strtolower(trim($name));
	    if ($pcnt) {
		// every arg except the first one
		$strVal = W3GetExpr($value);
		$strTrace .= $objFunc->LoadArg($strVal,$strName);
		$strTrace .= ' ';
		$strTrace .= ')';
	    } else {
		// we're looking at the very first arg -- must be the function's name
		if ($strName == 'func') {
		    $funcName = strtolower(W3GetExpr($value));
		} else {
		    $funcName = strtolower(W3GetExpr($strName));
		}
		W3AddTrace(' - (CALL) '.$funcName);

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
	    }
	    $pcnt++;
	}
	if ($input) {
//		$strTrace .= ' INPUT: {'.ShowHTML($input).'}';
//		$res = $parser->recursiveTagParse($input);
		// pass the data between <call ...> and </call> to the function as the final parameter:
		$objFunc->LoadArg($input);
//		$strTrace .= ' PARSED: {'.ShowHTML($res).'}';
	}
	if ($pcnt) {
	    $out = $objFunc->execute();
	} else {
	    $wgOut->AddHTML('<span class=previewnote><strong>W3TPL ERROR</strong>: Function "'.$strName.'" not loaded; probably an internal error.</span>');
	}
	W3AddTrace($strTrace.' CALL}');
	$out = W3GetEcho();
	$wgW3_func = NULL;		// no active function (is this still used?)
	return $out;
}
/*-----
  TAG: <class>
*/
function efW3Class( $input, $args, $parser ) {
    global $wgW3_funcs;

    $valIn = $input;	// this will contain the class definition
    $arFuncsGlobal = $wgW3_funcs;	// save global functions
    $wgW3_funcs = NULL;			// reset function list
    $out = $parser->recursiveTagParse($valIn);	// adds functions defined within class to global function list
    $arFuncsClass = $wgW3_funcs;	// save class function list
    $wgW3_funcs = $arFuncsGlobal;	// restore global functions

/*
    foreach ($arFuncsClass as $name => $code) {
    }
*/
}
/*-----
  TAG: <dump>
*/
function efW3Dump( $input, $args, $parser ) {
	global $wgW3Vars, $wgW3_funcs;
	global $wgW3_doTrace, $wgW3_doTrace_vars;	// tracing options
	global $wgW3Trace, $wgW3Trace_indents;	// tracing data
	global $wgW3_TraceCount;
	global $wgW3_opt_fpLogs;

	$out = '<ul class="dump">';
	$doPost = isset($args['post']);	// show posted data
	$doTrace = isset($args['trace']);	// show trace log
	$doVars = isset($args['vars']);	// show all variables
	$doFuncs = isset($args['funcs']);	// show function definitions
	$doMem = isset($args['mem']);	// show memory usage

	$wgW3_doTrace = $doTrace;
	$wgW3_doTrace_vars = $doTrace && $doVars;

	if ($doPost) {
		$out .= '<li><b>Posted:</b>:<ul>';
		foreach ($_POST AS $key => $value) {
			$out .= ("\n<li>[<b>$key</b>]: [$value]");
		}
		$out .= '</ul>';
	}

	if ($doMem) {
		$out .= '<li> <b>Memory usage before</b>: '.memory_get_usage(TRUE).' bytes';
	}

	if ($input != '') {
		$out .= $parser->recursiveTagParse($input);
	}
	if ($doMem) {
		$out .= '<li> <b>Memory usage after</b>: '.memory_get_usage(TRUE).' bytes';
	}
	if ($doVars) {
		if (is_array($wgW3Vars)) {
			$out .= '<li><b>Variables</b>:<ul>';
			foreach ($wgW3Vars as $name => $value) {
				if (is_array($value)) {
					$out .= '<li> ['.$name.']: array';
					$out .= '<ul>';
					foreach ($value as $akey => $aval) {
						$out .= '<li> '.$name.'['.$akey.'] = ['.ShowHTML($aval).']';
					}
					$out .= '</ul>';
				} else {
					$out .= '<li> ['.$name.'] = ['.$value.']';
				}
			}
			$out .= '</ul>';
		} else {
			$out .= '<li><i>No variables set</i>';
		}
	}
	if ($doFuncs) {
		if (is_array($wgW3_funcs)) {
			$out .= '<li><b>Functions</b>:<ul>';
			foreach ($wgW3_funcs as $name => $obj) {
				$out .= '<li>'.$obj->dump();
			}
			$out .= '</ul>';
		} else {
			$out .= '<li><i>No functions defined</i>';
		}
	}
	if ($doTrace) {
		if (is_array($wgW3Trace)) {
			$out .= '<b>Trace</b> ('.$wgW3_TraceCount.' events):<ul>';
			$indCur = 0;
			foreach ($wgW3Trace as $idxLine => $line) {

				$indLine = $wgW3Trace_indents[$idxLine];
				if ($indLine > $indCur) {
					$cntDiff = $indLine - $indCur;
					for ($idx=1; $idx<=$cntDiff; $idx++) {
					    $out .= '<ul>';
					}
				} elseif ($indLine < $indCur) {
					$cntDiff = $indCur - $indLine;
					for ($idx=1; $idx<=$cntDiff; $idx++) {
					    $out .= '</ul>';
					}
				}
				$out .= "\n";
				$indCur = $indLine;
/**/
				$out .= '<li><b>'.$idxLine.'</b> '.$line;
			}
			$out .= '</ul>';
		} else {
			$out .= '<li><i>'.$wgW3_TraceCount.' trace events</i>';
		}
	}
	$out .= '</ul>';
	if (isset($args['file'])) {
		$strFile = $args['file'];	// dump to a file instead of screen
		$fh = fopen($wgW3_opt_fpLogs.$strFile, 'a');
//		$dt = new DateTime();
//		$outPfx = $dt->format('Y-m-d H:i:s') . "\n";
		$outPfx = date('Y-m-d H:i:s');
		if (isset($args['msg'])) {
			$outPfx .= ' - '.$args['msg'];
		}
		$outPfx .= '<br>';
		$qb = fwrite($fh, $outPfx.$out);
		fclose($fh);
		if (isset($args['hide'])) {
			return NULL;
		} else {
			return "$qb bytes logged to '''$strFile'''.";
		}
	} else {
		return $out;
	}
}
/*-----
  TAG: <echo>
*/
function efW3Echo( $input, $args, $parser ) {
	global $wgOptCP_SubstStart,$wgOptCP_SubstFinish;

	W3AddTrace('&lt;ECHO&gt;');
	W3TraceIndent();

	$objArgs = new W3HookArgs($args);

	if ($objArgs->Exists('chr')) {
		$valIn = chr($objArgs->GetArgVal('chr'));
	} else if ($objArgs->Exists('var')) {
		$objVar = $objArgs->GetVarObj_Named_byArgVal('var');
		$valIn = $objVar->Value;	// untested as of 2012-06-11
	} else if ($objArgs->Exists('val')) {
		$valIn = $objArgs->GetArgVal('val');
	} else {
		$valIn = $input;
	}

	if (isset($args['strip'])) {
		$out = ShowHTML($valIn);
	}
	$doRaw = FALSE;
	if (isset($args['raw'])) {
		if (W3Status_RawOk()) {
			$doRaw = TRUE;
		}
	}
	$doNow = isset($args['now']);
	W3AddTrace(' input:['.ShowHTML($valIn).']');
	$out = $valIn;


	if (isset($args['vars'])) {
		W3AddTrace(' VARS before ['.ShowHTML($out).']');
		$objTplt = new clsStringTemplate_w3tpl($wgOptCP_SubstStart,$wgOptCP_SubstFinish);
		$objTplt->Value = $out;
		$out = $objTplt->Replace();
		W3AddTrace(' VARS after ['.ShowHTML($out).']');
	}

	if ($doRaw) {
		// no further processing
	} else {
		$out = $parser->recursiveTagParse($out);
		W3AddTrace(' PARSING returned ['.ShowHTML($out).']');
	}
	if (isset($args['isolate'])) {
		$out = IsolateOutput($out);
	}
	if (isset($args['tag'])) {	// surround result with <> to make it into an HTML tag
	    $out = '<'.$out.'>';
	}

	W3AddTrace(' output: ['.ShowHTML($out).'] ECHO}');
	if (!$doNow) {
	    W3AddEcho($out);	// output when appropriate
	    $out = '';		// don't output directly
	}

	W3TraceOutdent();
	W3AddTrace('&lt;/ECHO&gt;');

	return $out;
}
/*-----
  TAG: <else>
*/
function efW3Else( $input, $args, $parser ) {
	global $wgW3_ifFlag, $wgW3_ifDepth;

	$doHide = isset($args['hide']);	// only output <echo> sections

	$ifFlag = $wgW3_ifFlag[$wgW3_ifDepth];
	W3AddTrace(' ELSE('.$wgW3_ifDepth.'): ['.$ifFlag.']');
	if ($ifFlag) {
		W3AddTrace('ELSE skipped');
		$out = '';	// MW1.19 parser gags on NULL
	} else {
		W3AddTrace('ELSE executed');
		$wgW3_ifDepth++;
		$out = $parser->recursiveTagParse($input);
		$wgW3_ifDepth--;
		W3AddTrace('ELSE: OUT = ['.$out.']('.ShowHTML($out).')');
	}

	if ($doHide) {
		$out = W3GetEcho();
		return $out;
	} else {
		return $out;
	}
}
/*-----
  TAG: <exec>
  NOTES:
    This may eventually supercede <call>; for now, it is for calling plugin functions.
  2011-10-16 created
*/
function efW3Exec( $input, $args, $parser ) {
    global $wgW3Vars,$wgW3Mods;

    $strOutVar = NULL;
    $oArgs = array();

    foreach ($args as $name => $val) {
	$xval = W3GetExpr($val);	// parse the value in case it is not a constant
	switch ($name) {
	  case 'f':
	  case 'func':
	    $strFName = $xval;
	    break;
	  case 'mod':
	  case 'module':
	    $strMName = $xval;
	    break;
	  case 'content':	// the named argument's value is what's between the tags
	    $oArgs[$xval] = $input;
	    break;
	  case 'output':	// write output to the named variable; otherwise try to display it
	    $strOutVar = $xval;
	    break;
	  default:		// all others are named arguments
	    $oArgs[$name] = $xval;
	}
    }
    $res = $wgW3Mods->Dispatch($strMName,$strFName,$oArgs,$parser);
    if (is_null($strOutVar)) {
	return $res;	// output the function's return value
    } else {
	$wgW3Vars[$strOutVar] = $res;
    }
}
/*----
  USED BY:
    efW3For()
    API (to be documented)
*/
function ProcessRows($iDB,$iRes,$iName,$iParser,$input,$doHide, $iCallback=NULL) {
    global $wgW3_data;

    $dbr = $iDB;
    $res = $iRes;
    $strName = $iName;
    $parser = $iParser;
    $out = NULL;

    while( $row = $dbr->fetchObject ( $res ) ) {
	W3AddTrace('FOR: row->['.$strName.']');
	$wgW3_data[$strName] = $row;
	if (!is_null($iCallback)) {
	    $out .= $iCallback($row);
	}
	$strParsed = $parser->recursiveTagParse($input);
	if ($doHide) {
		$out .= W3GetEcho();
		W3AddTrace(' - FOR echo: ['.ShowHTML($out).']');
	} else {
		$out .= $strParsed;
		W3AddTrace(' - FOR parse: ['.ShowHTML($out).']');
	}
    }
    return $out;
}
/*-----
  TAG: <for>
  TO DO: There needs to be a descendent data class which can handle MW databases so we can
	switch between internal and external data just by selecting the appropriate class.
	As it is, there's a lot of untidy and almost-duplicate code for each case.
*/
function efW3For( $input, $args, $parser ) {
	global $wgW3Trace_indent;
	global $wgW3_data;
	global $wgW3Vars;
	global $wgW3DBs;
	global $w3stop;
	global $sql;

	if ($w3stop) { return; }

	$objArgs = new W3HookArgs($args);

	W3AddTrace('&lt;FOR&gt;');
	$wgW3Trace_indent++;

	$out = '';	// newer MediaWiki parser burps if NULL is returned
	$txtErr = NULL;

	$doHide = isset($args['hide']);	// only output <echo> sections
	$doArr = isset($args['array']);

	if ($objArgs->Exists('xps')) {
	    $doXps = TRUE;
	    $strXps = $objArgs->GetArgVal('xps');	// get the value of the xps parameter
	    W3AddTrace(' - XPS=['.$strXps.']');
	} else {
	    $doXps = FALSE;
	    $strXps = NULL;
	}

	if ($doArr) {
		$strArr = $args['array'];
	}
	if ($doArr || $doXps) {
	    $strIdxName = $objArgs->GetArgVal('index');
	    $strLetName = $objArgs->GetArgVal('let');
	    $strCntName = $objArgs->GetArgVal('count');

	    $hasIdxName = !is_null($strIdxName);
	    $hasLetName = !is_null($strLetName);
	    $hasCntName = !is_null($strCntName);

	    if ($hasIdxName) {
		W3AddTrace(' - INDEX goes in ['.$strIdxName.']');
	    }
	    if ($hasLetName) {
		W3AddTrace(' - VALUE goes in ['.$strLetName.']');
	    }
	    if ($hasCntName) {
		W3AddTrace(' - COUNT goes in ['.$strCntName.']');
	    }

	    if (isset($args['sep'])) {
		    $strSep = $args['sep'];
	    } else {
		    $strSep = NULL;
	    }
	}
	if (W3Status_SQLOk()) {
	// for now, only look for SQL stuff if page is protected
	// TO DO: display error message on unprotected pages if SQL is used
	    $doSql = isset($args['sql']);
	    if ($doSql) {
		    //$sqlQry = W3GetVal($args['sql']);
		    $sqlQry = $objArgs->GetArgVal('sql');
	    }
	    $doTbl = isset($args['table']);
	    if ($doTbl) {
		    $strTbl = $args['table'];
	    }
	    $doDb = $doSql || $doTbl;
	    $strWhere = $objArgs->GetArgVal('where');
	} else {
	    $doDb = FALSE;
	    W3AddTrace(' - page is not protected; cannot use database functions');
	    $txtErr = '<b>W3TPL</b>: database operation not allowed on unprotected page.';
	}
	// these parameters can be used for other types of data (implemented yet? probably not)
	$strSort = $objArgs->GetArgVal('sort');
	$strLimit = $objArgs->GetArgVal('limit');
//	$strName = $objArgs->GetVal('name');	// name of variable for storing data
	$strName = '@@row@@';			// v0.35
	$strEmpty = $objArgs->GetArgVal('empty');	// string to return if there is no data
	$strWhat = '';
	if ($doDb) {
	// doing something with a database
		$out = "\n".'&lt;for&gt; WARNING: no output created';	// default message (TO DO: make this configurable)
		if ($objArgs->Exists('db')) {
			$useMWDB = FALSE;
			$strDBName = $objArgs->GetArgVal('db');
			if (array_key_exists($strDBName,$wgW3DBs)) {
			    $strDBSpec = $wgW3DBs[$strDBName];
			    $dbr = new clsDatabase($strDBSpec);
			    $dbr->Open();
			    if ($doTbl) {
				$sqlWhat = $strTbl;	// TO DO: make sure table exists
			    }
			} else {
			    throw new exception('$wgW3DBs does not have a database named "'.$strDBSpec.'"');
			}
		} else {
			$useMWDB = TRUE;
			$dbr =& wfGetDB( DB_SLAVE );
			if ($doTbl) {
			    if ($dbr->tableExists($strTbl)) {
				    $sqlWhat = $strTbl;
			    } else {
				    $txtErr = "<b>W3TPL</b>: the table [$strTbl] does not exist. Use 'sql=' for more complex expressions.";
				    return $out;
			    }
			}
		}
		if ($strWhere != '') {
	//		$sqlWhere = $dbr->addQuotes($strWhere);
			$sqlWhere = $strWhere;		// TODO: need some way to harden against injection attack
		} else {
			$sqlWhere = FALSE;
		}
		if ($doSql) {
		    $sqlFull = $sqlQry;
		} else {
		    $sqlFull = 'SELECT * FROM '.$sqlWhat;
		    if ($sqlWhere) {
			    $sqlFull .= ' WHERE '.$sqlWhere;
		    }
		    if ($strSort) {
			    $sqlFull .= ' ORDER BY '.$strSort;
		    }
		    if ($strLimit) {
			    $sqlFull .= ' LIMIT '.$strLimit;
		    }
		}
		$sql = $sqlFull;
		W3AddTrace(' - SQL=[<b>'.$sqlFull.'</b>]');

		if ($useMWDB) {
			try {
		//		$res = $dbr->query($sqlWhat,$sqlWhere);
				$res = $dbr->query($sqlFull);
			}
			catch (Exception $e) {
/*
				$sqlSim = 'SELECT * FROM '.$sqlWhat;
				if ($sqlWhere) {
					$sqlSim .= ' WHERE '.$sqlWhere;
				}
*/
				$txtErr = "\n".'<br>W3TPL had a database error:<br>'."\n"
				  .'<i>'.$dbr->lastError().'</i><br>'."\n"
				  .'from this SQL:<br>'."\n"
				  .'<i>'.$sqlFull.'</i><br>';
				return $parser->recursiveTagParse($out);
			}
			W3AddTrace(' - rows: '.$dbr->numRows( $res ));
			if ($dbr->numRows( $res ) <= 0) {
				$dbr->freeResult( $res );
				return $parser->recursiveTagParse($strEmpty);
			}

/*
			while( $row = $dbr->fetchObject ( $res ) ) {
				W3AddTrace('FOR: row->['.$strName.']');
				$wgW3_data[$strName] = $row;
				$strParsed = $parser->recursiveTagParse($input);
				if ($doHide) {
					$out .= W3GetEcho();
					W3AddTrace(' - FOR echo: ['.ShowHTML($out).']');
				} else {
					$out .= $strParsed;
					W3AddTrace(' - FOR parse: ['.ShowHTML($out).']');
				}
			}
*/
			$out .= ProcessRows($dbr,$res,$strName,$parser,$input,$doHide);
			$dbr->freeResult( $res );
		} else {
			$res = $dbr->_api_query($sqlFull);
			if (is_resource($res)) {
			    if (mysql_num_rows( $res ) <= 0) {
				    return $parser->recursiveTagParse($strEmpty);
			    }
			    while ($row = mysql_fetch_assoc($res)) {
				    $wgW3_data[$strName] = $row;
				    $strParsed = $parser->recursiveTagParse($input);
				    if ($doHide) {
					    $out .= W3GetEcho();
					    W3AddTrace(' - FOR echo: ['.ShowHTML($out).']');
				    } else {
					    $out .= $strParsed;
					    W3AddTrace(' - FOR parse: ['.ShowHTML($out).']');
				    }
			    }
			} else {
			    throw new exception('Problem executing SQL: ['.$sqlFull.']');
			}
		}
		//$dbr->freeResult( $res );	// this actually causes *more* memory to be used
		//unset($wgW3_data);		// and so does this!
	}
	if ($doArr) {
		W3AddTrace(' - FOR with ARRAY');
/* this is done earlier
		if (isset($args['index'])) {
			$strIdxName = $args['index'];
		}
*/
		//$wgW3Vars[$strArr][0] = 'zero';
		$arr = W3GetVal($strArr);
		//$arr = $wgW3Vars[$strArr];
//echo 'strArr=['.$strArr.'] ARR:<pre>'.print_r($arr,TRUE).'</pre>';
		if (is_array($arr)) {
			$idx = 0;
			foreach ($arr as $name => $value) {
				$idx++;
				if ($strLimit) {
					if ($idx > $strLimit) {
						break;
					}
				}
				$strTrace = 'FOR: row->['.$strArr.']';
				if ($strName) {
					$wgW3Vars[$strName] = (string)$name;
					$strTrace .= ' INDEX=['.$name.']=>['.$strName.']';
				}
				if ($objArgs->Exists('value')) {
/*
 "value" here is short for "put value in this var"
 For deconfusion and consistency, we need a better name for this argument.
 Is "let=" already used for something else?
*/
					$strName = $objArgs->GetArgVal('value');
					$wgW3Vars[$strName] = (string)$value;
					$strTrace .= ' VALUE=['.$value.']=>['.$strName.']';
				}

				$strParsed = $parser->recursiveTagParse($input);
				W3AddTrace($strTrace);
				if (!isset($out)) { $out = NULL; }
				if ($doHide) {
					$out .= W3GetEcho();
					W3AddTrace('FOR echo: ['.ShowHTML($out).']');
				} else {
					$out .= $strParsed;
					W3AddTrace('FOR parse: ['.ShowHTML($out).']');
				}
			}
		} else {
			W3AddTrace('FOR array=['.$strArr.']: not an array!');
		}
	}
	if ($doXps) {
	    W3AddTrace('XPLOOP: xps=['.$strXps.']');
	    $tok = substr ( $strXps, 0, 1);	// token for splitting
	    W3AddTrace(' - tok ['.$tok.']');
	    $out = NULL;
	    if ($tok) {
		$tks = substr ( $strXps, 1 );	// tokenized string
		$list = explode ( $tok, $tks );	// split the string

		if ($hasCntName) {
			$cnt = (string)count($list);
			$wgW3Vars[$strCntName] = $cnt;
			W3AddTrace(' STORING COUNT ['.$cnt.'] in ['.$strCntName.']');
		}

		$idx = 0;
		foreach ($list as $value) {
		    $idx++;
		    if (!is_null($out)) {
			    $out .= $strSep;
		    }

		    $strTrace = NULL;
		    if ($hasIdxName) {
			    $wgW3Vars[$strIdxName] = (string)$idx;
			    $strTrace .= ' STORING INDEX ['.$idx.'] in ['.$strIdxName.']';
		    }
		    if ($hasLetName) {
			    $wgW3Vars[$strLetName] = (string)$value;
			    $strTrace .= ' STORING VALUE ['.$value.'] in ['.$strLetName.']';
		    }

		    W3AddTrace('XPS iteration:'.$strTrace.' RESULT: ['.$strIdxName.'] <- ['.$value.']');
		    W3AddTrace('[parsing] text=['.ShowHTML($input).']');
		    $wgW3Trace_indent++;
		    $strParsed = $parser->recursiveTagParse($input);
		    $wgW3Trace_indent--;
		    W3AddTrace('[/parsing]');
		    if ($doHide) {
			    W3AddTrace(' - hiding...');
			    $out .= W3GetEcho();
			    W3AddTrace(' - echo: ['.ShowHTML($out).']');
		    } else {
			    $out .= $strParsed;
			    W3AddTrace(' - parse: ['.ShowHTML($out).']');
		    }
		}
	    }
	}
	if (!is_null($txtErr)) {
	    W3AddEcho('<div class="previewnote">'.$txtErr.'</div>');	// what is the *proper* class for error msgs?
	    // or maybe only have error messages show up at preview time
	}

	$wgW3Trace_indent--;
	W3AddTrace('&lt;/FOR&gt;');
	return $out;
}
/*-----
  TAG: <func>
  NOTE: Function output is not sent to web output because typically we want to be able to format it nicely,
    which usually means lots of extra blank lines and indents in the code. We don't want these in the output.
  HISTORY:
    2012-01-15 returning NULL now causes UNIQ-QINU tag to be emitted; changing to '' seems to fix this.
    2016-03-16 $funcName seems to be getting set to a blank -- so now pulling it
      from the first element of $args if it's blank after going through the $args loop.
*/
function efW3Func( $input, $args, $parser ) {
	global $wgW3_funcs;


	$pcnt = 0;
	$funcArgs = array();	// declare var in case there are no args
	foreach ($args as $name => $value) {
		if ($pcnt) {
/*
The parser apparently sets the argument's value to its name if no value is specified.
This is a sort of bug for this purpose, but maybe it makes sense in other contexts.
The real way to get around it is to use <w3tpl> block syntax* instead of the <func> tag.
  *to be implemented

2016-03-16 This doesn't work if the func has no other arguments.
*/
			if ($value != $name) {
				$funcArgs[$name] = $value;
			} else {
				$funcArgs[$name] = null;
			}
		} else {
			$funcName = strtolower($value);	// 2011-07-25 allow name="function name" as 1st param
		}
		$pcnt++;
	}
	if (empty($funcName)) {
	    // if the function name isn't explicitly set, then it's the first key:
	    $akeys = array_keys($args);
	    $funcName = $akeys[0];
	    if (empty($funcName)) {	// still?
		$sMsg = "<br>FUNC: Internal error - blank function name. Arguments:"
		  .fcArray::Render($args,1)
		  ;
		echo $sMsg;
		W3AddTrace($sMsg);
	    }
	}

	W3AddTrace('FUNC &ldquo;'.$funcName.'&rdquo;');
	$objFunc = new clsW3Function($parser,$funcName,$funcArgs,$input);
	$wgW3_funcs[$funcName] = $objFunc;

// store the function in page_props (added 2011-07-24)
// -- we'll use prefix-marked strings to store the function data, using ">" as the prefix
//	because it should never be in a function or argument name
//	    $fkey = '>fx()>'.$funcName;

/*
	    // store the function's permissions
	    $strPerms = NULL;
	    if (W3Status_RawOk()) {
		$strPerms .= ' raw';
	    }
	    if (W3Status_SQLOk()) {
		$strPerms .= ' sql';
	    }
	    $arFProps['perms'] = $strPerms;
	    // store the function's code
	    $arFProps['code'] = $input;
	    // store the argument data
	    foreach ($funcArgs as $name => $val) {
//		$akey = $fkey.'>'.$name;
//		$parser->mOutput->setProperty($akey,$val);
		$arFProps['arg'][$name] = $val;
	    }
	    $arProps['fx()'][$funcName] = $arFProps;
*/

	$objProps = new clsContentProps($parser);
	$objProps->SaveArray($objFunc->GetDef());
	return '';
}
/*-----
  TAG: <get>
*/
function efW3Get( $input, $args, $parser ) {
    global $wgRequest,$wgOut;

    W3AddTrace('&lt;GET&gt;');
    W3TraceIndent();

    $objArgs = new W3HookArgs($args);
    $doRaw = FALSE;

    if (isset($args['default'])) {
	    $strDefault = $args['default'];
    } else {
	    $strDefault = NULL;
    }

    // BUG: <get val=@something /> isn't handled right.
    if ($objArgs->Exists('name')) {
	$strName = strtolower($objArgs->GetArgVal('name'));
    } else {
	$strName = NULL;
    }
    W3AddTrace(' - name=['.$strName.']');

// get the starting value
    if (isset($args['val'])) {
	$strVal = $args['val'];
	$strVal = W3GetExpr($strVal);	// check for redirections
    } elseif (isset($args['arg'])) {
	$parser->disableCache();
	$strVal = $wgRequest->getVal($strName, $strDefault);
    } else {
	//$strVal = W3GetVal($strName,$strIdx);
	$objVar = $objArgs->GetVarObj_Named_byArgVal('name');
	if ($objArgs->Exists('index')) {
	    $strIdx = strtolower($objArgs->GetArgVal('index'));
	    $objVar->Index($strIdx);
	}
	$strVal = $objVar->Value();
//global $wgW3Vars;
//echo 'LINE '.__LINE__.' VAR LIST:<pre>'.print_r($wgW3Vars,TRUE).'</pre>';
    }

    if (isset($args['pfx'])) {
	$strTxt = $objArgs->GetArgVal('pfx');
	$strVal = $strTxt.$strVal;
    }
    if (isset($args['sfx'])) {
	$strTxt = $objArgs->GetArgVal('sfx');
	$strVal .= $strTxt;
    }

    if (isset($args['codes'])) {
	$strVal = ShowHTML($strVal);
    } else {
	$doRaw = FALSE;
	if (isset($args['raw'])) {
	    if (W3Status_RawOk()) {
		$doRaw = TRUE;
	    }
	}
	if (!$doRaw) {
	    if (is_array($strVal)) {
		throw new exception('Internal error: attempting to parse an array.');
	    } else {
		$strVal = $parser->recursiveTagParse($strVal);
	    }
	}
    }

    if (isset($args['isolate'])) {
	    $strVal = IsolateOutput($strVal);
    }
    if (isset($args['len'])) {
	    $strVal = substr($strVal,0,$args['len']);
    }
    if (isset($args['ucase'])) {
	    $strVal = strtoupper($strVal);
    }
    if (isset($args['lcase'])) {
	    $strVal = strtolower($strVal);
    }

    W3TraceOutdent();
    W3AddTrace('&lt;/GET&gt;');

    return $strVal;
}
/*-----
  TAG: <hide>
  ACTION: Doesn't display anything between the tags.
    Any tag that wants to be able to display certain things *anyway* must handle output directly.
  HISTORY:
    2012-01-15 returning NULL now causes UNIQ-QINU tag to be emitted; changing to '' seems to fix this.
*/
function efW3Hide( $input, $args, $parser ) {
	$parser->recursiveTagParse( $input );
	return '';
}
/*-----
  TAG: <if>
  HISTORY:
    2012-06-12 arg-fetching now uses W3HookArgs class
*/
function efW3If( $input, $args, $parser ) {
	global $wgW3_ifFlag,$wgW3_ifDepth;

	W3AddTrace('&lt;IF&gt;');
	W3TraceIndent();

	$objArgs = new W3HookArgs($args);

	$doHide = $objArgs->Exists('hide');	// only output <echo> sections

	$ifFlag = false;
	if (!$wgW3_ifDepth) {
		$wgW3_ifDepth = 0;
	}
	if ($objArgs->Exists('flag')) {
		$strVal = $objArgs->GetArgVal('flag');
		if (is_null($strVal) || ($strVal == '')) {
			$ifFlag = FALSE;
			$dbgType = 'blank';
		} else if (is_numeric($strVal)) {
			$ifFlag = ($strVal != 0);
			$dbgType = 'numeric';
		} else {
			$ifFlag = TRUE;
			$dbgType = '';
		}

		$strTrace = '';
		if ($wgW3_ifDepth != 0) {
		    $strTrace .= '.'.$wgW3_ifDepth;
		}
		$strTrace .= ' val='.'['.$strVal.']';

		W3AddTrace('IF'.$strTrace.' != 0: ['.TrueFalse($ifFlag).']:'.$dbgType);
	} elseif (isset($args['comp'])) {
// 2012-06-12 rewritten to use objArgs, but not tested
		$strVal1 = $objArgs->GetArgVal('comp');

		$strTrace = '';
		if ($wgW3_ifDepth != 0) {
		    $strTrace .= '.'.$wgW3_ifDepth;
		}

		$strTrace .= ' COMP ['.$strVal1.']';
		$strVal2 = $objArgs->GetArgVal('with');
		$strTrace .= ' WITH ['.$strVal2.']';
		if ($objArgs->Exists('pre')) {
			$wgW3_ifDepth++;
			$strVal1 = $parser->recursiveTagParse($strVal1);
			$strVal2 = $parser->recursiveTagParse($strVal2);
			$wgW3_ifDepth--;
//			$strVal1 = $parser->replaceVariables($strVal1);
//			$strVal2 = $parser->replaceVariables($strVal2);

		}
		$ifFlag = ($strVal1 == $strVal2);
		W3AddTrace('IF'.$strTrace.':'.TrueFalse($ifFlag));
	}
	if (isset($args['not'])) {
	    $ifFlag = !$ifFlag;	// invert the flag
	}
	$wgW3_ifFlag[$wgW3_ifDepth] = $ifFlag;
	if ($ifFlag) {
		W3TraceIndent();
		$out = $parser->recursiveTagParse($input);
		W3TraceOutdent();
	} else {
		$out = '';
	}

	if ($doHide) {
		$out = W3GetEcho();
	}

	W3TraceOutdent();
	W3AddTrace('&lt;/IF&gt;');

	return $out;
}
/*-----
  TAG: <let>
  ATTRS:
    array: flag indicating that the output variable is an array
    echo: flag indicating that the output should be echoed
    index = array index to use on the output variable
    load: flag indicating that the contents of <page> should be loaded
    name = name of output variable
    page = name of source page
    parse (not tested)
    save = save the output variable as a page property
    self = when appending, append to self
    oparse
*/
function efW3Let( $input, $args, $parser ) {
        global $wgRequest;
	global $wgTitle; // for "save" option
	global $wgW3Vars,$wgW3_func;
	W3EnterTag('LET',$args);

	$objArgs = new W3HookArgs($args);
	$objVar = $objArgs->GetVarObj_Named_byArgVal('name');

	// set local variables for attributes
	$doArray = $objArgs->Exists('array');
	$doEcho = isset($args['echo']);
	$hasIndex = isset($args['index']);
	if ($hasIndex) {
	    $strIdx = strtolower(W3GetExpr($args['index']));
	    $objVar->SetIndex($strIdx);
	}
	$doLoad = $objArgs->Exists('load');
	$isNull = isset($args['null']);
	$doOParse = isset($args['oparse']);
	$doPage = $objArgs->Exists('page');
	if ($doPage) {
	    $sPage = $objArgs->GetArgVal('page');
	}
	$doParse = isset($args['parse']);
	$doSave = isset($args['save']);
	$doSelf = isset($args['self']);
	//

	$strCopy = NULL;

	W3AddTrace('name=['.($objVar->NameRaw).'] parsed to ['.$objVar->Name.']');
	$objVar->Trace();

	if ($isNull) {
	    // if "null" option, then no other inputs matter
	    $objVar->Clear();
	} elseif ($doLoad) {
	    // This could be either array or scalar, so we have to handle it here.
	    // This means other options won't work on a load; oh well, fix later.
	    $objVar->Clear();

	    if ($doPage) {
		$strTitleRaw = $sPage;				// get namespec of page to access
		$vobjTitle = new clsW3Var();		// create new Variable object
		$vobjTitle->ParseName($strTitleRaw);		// have the Variable parse the namespec and act accordingly
		$strTitle = $vobjTitle->Name;			// retrieve the Variable's name, as calculated
		W3AddTrace(' - from page: ['.$strTitle.']');
		$objTitle = Title::newFromText($strTitle);	// create a MW Title object
		if (!is_object($objTitle)) {
		    echo 'vArgs:<pre>'.print_r($objArgs->vArgs,TRUE).'</pre>';
		    echo 'wgW3Vars:<pre>'.print_r($wgW3Vars,TRUE).'</pre>';
		    throw new exception('Did not get a title object for the page named ['.$strTitle.'], parsed from ['.$strTitleRaw.']');
		}
		$objProps = new clsPageProps($parser,$objTitle);	// create a MW Page Properties object for the Title
		if ($doArray) {			// if the output var is an array...
		    $objVar->LoadArray($objProps);				// ...load all the Props into it
		    W3AddTrace(' -- loading prop as array:'.$objVar->RenderDumpArray());
		} else {						// Otherwise:
		    if ($hasIndex) {
			$strVal = $objProps->LoadVal($strIdx);			// ...just load the specified Prop
			W3AddTrace(' -- value: ['.$strVal.']');
			$objVar->Load($strVal);
		    } else {							// ...load all Props for this page
			$objVar->LoadAll($objProps);
		    }
		}
	    } else {
		// for now, we're not going to support loading global arrays
		//  just because it seems like a recipe for trouble.
		W3AddTrace(' - from global');
		$objProps = new clsContentProps($parser);
		$strVal = $objProps->LoadVal($strName);
		$objVar->Load($strVal);
	    }
	} else {
	    // preload variable with whatever it needs, then use standard option processing
	    $objVar->Fetch();
	    // "copy" option works for any data type:
	    if ($objVar->CheckCopy($objArgs)) {
	    // do nothing; work already done
	    } else {
		if (is_null($input) or isset($args['self'])) {
			$objVar->Fetch();
			W3AddTrace(' - from self: ['.$objVar->SummarizeValue().']');
		} else {
			$objVar->Value = $input;
			W3AddTrace(' - from input: ['.$objVar->SummarizeValue().']');
		}
	    }
	    if ($objVar->IsArray()) {
		    W3Let_array ( $objVar, $objArgs, $input, $parser );
	    } else {
		    W3Let_scalar ( $objVar, $objArgs, $input, $parser );

// 2011-09-20 this code seems to overwrite an array value -- so moving it here
		    if (isset($args['parse'])) {	// 2011-07-22 not tested yet
			$objVar->Value = $parser->recursiveTagParse($objVar->Value);
		    }
		    $objVar->Store();
	    }
	}

// (option) store the results:
	if ($doSave) {
	    $objProps = new clsPageProps($parser,$wgTitle);
	    $objVar->Save($objProps);
	}

// (option) print the results:
	if (isset($args['echo'])) {
		if (isset($args['oparse'])) {
		    $rtn = $parser->recursiveTagParse($objVar->Value);
		} else {
		    $rtn = $objVar->Value;
		}
	} else {
		$rtn = NULL;
	}

	W3ExitTag('LET');

	return is_null($rtn)?'':$rtn;
}
/*-----
  TAG: <load> - load contents of a page
  NOTE: Some pages apparently don't create the parser object; if this code needs to run on one of those pages,
	then this may need to create $wgParser if it doesn't exist. For now, we assume optimistically.
   TO DO:
	Make parsing optional for protected pages
*/
function efW3Load( $input, $args, $parser ) {
	global $wgTitle, $wgOut;
	global $w3stop;

	if ($w3stop) { return; }

	$objArgs = new W3HookArgs($args);
	$strTitle = $objArgs->GetArgVal('page');

	W3AddTrace('LOAD: page={'.$strTitle.'}');
//	$strTitle = W3GetExpr($strTitle);
	$doEcho = isset($args['echo']);

	$objTitle = Title::newFromText($strTitle);
	if (is_object($objTitle)) {
		if (stripos($strTitle, 'special:')===0) {
		/* title is Specialpage; you'd think there would be a general page-loading method which handles these,
		  but I haven't been able to find it. */
		    W3AddTrace('LOAD SpecialPage ID='.$objTitle->getArticleID());
		    //$txtContent = SpecialPage::capturePath($objTitle);


		    $wgTitleOld = $wgTitle;
		    $wgOutOld = $wgOut;
		    $wgOut = new OutputPage;

		    $ret = SpecialPage::executePath( $objTitle, FALSE );
		    if ( $ret === true ) {
			    $ret = $wgOut->getHTML();
		    }
		    $wgTitle = $wgTitleOld;
		    $wgOut = $wgOutOld;

		    //SpecialPage::executePath( $objTitle, false );
		    $txtContent = $ret;
		    if ($txtContent === FALSE) {
			W3AddTrace('LOAD: Content not loadable');
		    } else {
			W3AddTrace('LOAD: Content has '.strlen($txtContent).' bytes');
		    }
		} else {
		    W3AddTrace('LOAD Title ID='.$objTitle->getArticleID());
		    $objArticle = new Article($objTitle);
		    $txtContent = $objArticle->getContent();
		    W3AddTrace('LOAD: page (Title ID='.$objTitle->getArticleID().') has '.strlen($txtContent).' bytes, starting with: '.substr($txtContent,0,40));
		}
		$txtContent .= $input;	// any additional input to be parsed in page's context

// eventually we will have a way to retrieve the contents via a variable
// until then, the "raw" option is just for debugging
		if (isset($args['raw'])) {
			$out = $txtContent;
		} else {
			if (isset($args['local'])) {
			// parse title in its own context, not in the parent page's context
				W3AddTrace(' - LOAD as LOCAL');

				// temporarily replace $wgTitle with the page we're parsing
				$objTitleOuter = $wgTitle;
				$wgTitle = $objTitle;
				$out = $parser->recursiveTagParse($txtContent);
				$wgTitle = $objTitleOuter;
				// restore $wgTitle's original value

			} else {
				$out = $parser->recursiveTagParse($txtContent);
			}
			if (isset($args['nocat'])) {
			// clear out any categories added by parsing of loaded text
				$parser->mOutput->mCategories = array();
				//$parser->mOutput->setCategoryLinks(NULL);
			}
		}
	} else {
		$out = 'Title ['.ShowHTML($strTitle).'] does not exist.';	// change this to a proper system message at some point
	}
	if ($objArgs->Exists('let')) {
	    $strName = $objArgs->GetArgVal('let');
	    W3SetVar($strName, $out);
	    W3AddTrace(' - storing data in {'.$strName.'}');
	}
	W3AddTrace('&lt;/LOAD&gt;');
	if ($doEcho) {
		return $out;
	} else {
		return '';	// newer parsers burp if NULL is returned
	}
}
/*-----
  TAG: <save>
*/
function efW3Save( $input, $args, $parser ) {
	global $wgW3_edit_queue,$w3stop;

	if ($w3stop) { return; }

	$strTitle = $args['page'];
	W3AddTrace('SAVE: page={'.$strTitle.'}');
	$strTitle = W3GetExpr($strTitle);
	W3AddTrace('SAVE -> {'.$strTitle.'}');

	$txtSummary = NULL;
	$intFlags = EDIT_DEFER_UPDATES;	// does this prevent the parser from getting confused?
	if (isset($args['text'])) {
		$txtContent = W3GetExpr($args['text']);
	} else {
		$txtContent = '';
	}
	if (isset($args['insert'])) {
		$txtContent = W3GetExpr($args['insert']).$txtContent;
	}
	if (isset($args['append'])) {
		$txtContent .= W3GetExpr($args['append']);
	}
	if (isset($args['comment'])) {
		$txtSummary = $args['comment'];
	} else {
		$intFlags = $intFlags | EDIT_AUTOSUMMARY;
	}
	if (isset($args['minor'])) {
		$intFlags = $intFlags | EDIT_MINOR;
	}

	$objTitle = Title::newFromText($strTitle);
	$ok = FALSE;
	$strStatus = 'fail - no article ['.$strTitle.']';
	if (is_object($objTitle)) {
		$objArticle = new Article($objTitle);
		if (is_object($objArticle)) {
			$wgW3_edit_queue[] = new w3ArticleEdit($objArticle,$txtContent,$txtSummary,$intFlags);
			$ok = TRUE;
			$strStatus = 'ok';
		} else {
			$ok = FALSE;
			$strStatus = 'fail - could not load ['.$strTitle.']';
		}
/*		$objArticle = new Article($objTitle);
		$ok = $objArticle->doEdit( $txtContent, $txtSummary, $intFlags );
		$strStatus = $ok?'ok':'fail - no save';
*/
	}
	if (isset($args['ok'])) {
		$strName = $args['ok'];
		W3SetVar($strName, $ok);
	}
	if (isset($args['status'])) {
		$strName = $args['status'];
		W3SetVar($strName, $strStatus);
	}
}
/*-----
  TAG: <w3tpl>
  TO DO: inline code parsing/processing
	HISTORY:
		2012-01-20 returning NULL causes problems in MW 1.18
*/
function efW3TPLRender( $input, $args, $parser ) {
	global $wgRestrictDisplayTitle;
	global $w3step,$w3stop;

	$objArgs = new W3HookArgs($args);

	$out = '';

	if ($objArgs->Exists('nocache')) {
	        $parser->disableCache();
	}
	if ($objArgs->Exists('quit')) {
		$w3stop = TRUE;
	}
	if ($objArgs->Exists('title')) {
	    $wgRestrictDisplayTitle = FALSE;
	    $strTitle = $objArgs->GetArgVal('title');
// only one of the following lines works, depending on whether we are previewing or viewing normally
	    $parser->getOutput()->setDisplayTitle($strTitle);	// changes the page header title (display mode)
	    $parser->getOutput()->setTitleText($strTitle);	// changes the HTML <title> text (preview mode)
	}
	if ($objArgs->Exists('step')) {
		$w3step = true;	// show each line of code before it is executed
	}
	if ($input != '') {
		$out .= $input;

		if (isset($args['pre'])) {
			$out = $parser->recursiveTagParse( $out );
		}
		if (!isset($args['notpl'])) {
			$out = ActualRender($out,$args);
		}
		if (isset($args['post'])) {
			$out = $parser->recursiveTagParse( $out );
		}
	}
	return $out;
}
/*-----
  TAG: <xploop>
  NOTE: I *think* this tag is deprecated, in favor of <for> with the appropriate option. It may be discontinued at some point.
*/
function efW3Xploop( $input, $args, $parser ) {
	global $wgW3Vars;

	$objArgs = new W3HookArgs($args);
	$out = ''; 	// default output (MW1.19 parser gags on NULL)
/*
	$strListRaw = $objArgs->GetVal('list');
	$strList = W3GetExpr($strListRaw);
*/
	$strList = $objArgs->GetArgVal('list');
	$strTok = $objArgs->GetArgVal('repl');
	$doEcho = isset($args['echo']);

	if (isset($args['var'])) {
		$strVar = strtolower($args['var']);
	} else {
		$strVar = NULL;
	}
	$sepStr = $objArgs->GetArgVal('sep');

	if ($strTok) {
// doing a straight token replacement
		$strTrace = 'XPLOOP replace ('.ShowHTML($strTok).') &larr; &ldquo;'.ShowHTML($strList).'&rdquo;';
	} else {
// setting variable value
		$strTrace = 'XPLOOP set ('.ShowHTML($strVar).') &larr; &ldquo;'.ShowHTML($strList).'&rdquo;';
	}
/*
	if ($strList != $strListRaw) {
		$strTrace .= '&larr;&ldquo;'.$strListRaw.'&rdquo;';
	}
*/
	W3AddTrace($strTrace);
	if (isset($args['parselist'])) {
		$strList = $parser->recursiveTagParse( $strList );
	}

	$tok = substr ( $strList, 0, 1);	// token for splitting
	if ($tok) {
		$tks = substr ( $strList, 1 );	// tokenized string
		$list = explode ( $tok, $tks );	// split the string
		if ($strTok) {
	// doing a straight token replacement
			foreach ($list as $value) {
				if ($out) {
					$out .= $sepStr;
				}
				$out .= str_replace( $strTok, $value, $input );
			}
			// 2012-01-21 if this is necessary, DOCUMENT it:
			//$out = CharacterEscapes::charUnesc( $out, array(), $parser );
			$out = $parser->recursiveTagParse( $out );
		} else {
			foreach ($list as $value) {
				if ($out != '') {
					$out .= $sepStr;
				}
				$wgW3Vars[$strVar] = $value;
				W3AddTrace(' - XP: ['.$strVar.'] <- ['.$value.']');
				$out .= $parser->recursiveTagParse( $input );
			}
		}
	}
	if (!$doEcho) {
	    $out = W3GetEcho();
	}
	return $out;
}
//**********

class w3ArticleEdit {
	private $vArticle;
	private $vContent;
	private $vSummary;
	private $vFlags;

	public function __construct($iArticle, $iContent, $iSummary, $iFlags) {
		$this->vArticle = $iArticle;
		$this->vContent = $iContent;
		$this->vSummary = $iSummary;
		$this->vFlags = $iFlags;
	}

	public function Exec() {
		$ok = $this->vArticle->doEdit( $this->vContent, $this->vSummary, $this->vFlags );
		$strStatus = $ok?'ok':'fail - no save';
		return $ok;
	}
}

function ActualRender($input) {
// break the code up into separate commands
		if ($doLine) {	// TRUE = line is complete
			//W3AddTrace('TPL: LINE=[<u>'.$strDbgLine.'</u>]');
			$out .= $cmdObj->execute($lines);
		}
// final semicolon not required:
	if ($line) {
		$out .= $cmdObj->execute($line,$clause);
	}
	return $out;
}

// TO DO: Change name to ShowMarkup, because it handles HTML, wikitext, and partly-parsed wikitext
function ShowHTML($iText, $iRepNewLine=TRUE) {

/*/
// DEBUGGING - explode string by inserting space between each character:
	$cpLen = strlen($iText);
	$out2 = '';
	for ($i = 0; $i <= $cpLen; $i++) {
		$out2 .= substr($iText,$i,1).' ';
	}
	$out = $out2;
/**/
//	$out = 'LENGTH='.strlen($iText);

	$out = $iText;
	$out = str_replace ( '<','&lt;',$out );
	$out = str_replace ( '>','&gt;',$out);
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

class clsStringTemplate_w3tpl extends clsStringTemplate {
// This version can be used if the values are in an associative array
	protected function GetValue($iName) {
		return W3GetVal($iName);
	}
}

/*
class w3expr {
	private $text;
	abstract function Parse($iTask);
} */

/*====
  USAGE:
    * This can be initialized in either of two ways:
      1. Construct with all parameters
      2. Construct with just iParser, and call PutDef for everything else
*/
class clsW3Function {

    var $vParser;
    var $isOkRaw, $isOkSQL;
    var $Name, $vParams, $vCode;
    var $vArgs, $vArgIdx;

    public function __construct($iParser, $iName=NULL, $iParams=NULL, $iCode=NULL) {
	if (empty($iName)) {
	    throw new exception('Who is creating a function with no name?');
	}
	$this->vParser = $iParser;
	$this->Name = $iName;
	$this->vParams = $iParams;
	$this->vCode = $iCode;
	$this->isOkRaw = W3Status_RawOk();
	$this->isOkSQL = W3Status_SQLOk();
	$this->vArgs = NULL;
    }
    /*----
      INPUT: $iarDef = array containing complete function definition, as retrieved by clsContentProps
      MIRROR: GetDef();
    */
    public function PutDef(array $arDef) {
	if (!isset($arDef['name'])) {
	    throw new exception('Name not set in function definition.');
	}
	$sName = $arDef['name'];
	if (!array_key_exists('code',$arDef)) {
	    echo 'function definition:'.clsArray::Render($arDef);
	    throw new exception("Code not found for function \"$sName()\".");
	}
	$this->Name = $sName;
	$this->vParams = clsArray::Nz($arDef,'arg');
	$this->vCode = $arDef['code'];
	$sPerms = $arDef['perms'];

	$this->isOkRaw = strpos($sPerms,' raw');
	$this->isOkSQL = strpos($sPerms,' sql');
    }
    /*----
      RETURNS: array containing complete function definition, suitable for storing in clsContentProps
      MIRROR: PutDef();
    */
    public function GetDef() {
	$arArgs = $this->vParams;

	// store the function's permissions
	$strPerms = NULL;

	if ($this->isOkRaw) {
	    $strPerms .= ' raw';
	}
	if ($this->isOkSQL) {
	    $strPerms .= ' sql';
	}
	$arFProps['perms'] = $strPerms;
	// store the function's code
	$arFProps['code'] = $this->vCode;
	// store the argument data
	foreach ($arArgs as $name => $val) {
//		$akey = $fkey.'>'.$name;
//		$parser->mOutput->setProperty($akey,$val);
	    $arFProps['arg'][$name] = $val;
	}
	$arOut['fx()'][$this->Name] = $arFProps;

	return $arOut;
    }
    public function dump() {
	$out = '<b>'.$this->Name.'</b>(';
	if (is_array($this->vParams)) {
	    $pcnt = 0;
	    foreach ($this->vParams as $name => $value) {
		if ($pcnt) {
			$out .= ', ';
		}
		$out .= '<u>'.$name.'</u>';
		if (isset($value)) {
			$out .= '='.$value;
		}
		$pcnt++;
	    }
	}
	$out .= ') {';
	$strCode = ShowHTML($this->vCode,FALSE);
	$out .= '<pre>'.$strCode.'</pre>}';
	return $out;
    }
    public function ResetArgs() {
	$this->vArgIdx = 0;
	$this->vArgs = NULL;
    }
    public function LoadArg($iValue,$iName='') {
	if ($iName) {
	    $strName = strtolower($iName);
	    // (2011-08-21) this line is a kluge that should work most of the time
	    $this->vArgIdx++;
	    // it assumes that only the last parameter will ever be omitted
	    // fix this later if it seems useful to support out-of-order omissions
	} else {
	    $strName = $this->ParamName($this->vArgIdx);
	    $this->vArgIdx++;
	}
	$this->vArgs[$strName] = $iValue;
	return '[<u>'.$strName.'</u>]=&ldquo;'.$iValue.'&rdquo;';
    }
    public function HasArg($iName) {
	return isset($this->vArgs[$iName]);
    }
    public function ArgVal($iName) {
	return $this->vArgs[$iName];
    }
    public function ParamName($iIndex) {
	if (is_null($this->vParams)) {
	    return NULL;	// no params; return NULL
	} else {
	    $keys = array_keys($this->vParams);
	    if (array_key_exists($iIndex,$keys)) {
		$key = $keys[$iIndex];
		return $key;
	    } else {
		echo 'W3TPL: Unexpected argument #<b>'.$iIndex.'</b> encountered in call to function.';
		return NULL;
	    }
	}
    }
    protected function HasArgs() {
	if (!is_null($this->vArgs) && !is_array($this->vArgs)) {
	    echo 'vArgs IS: '.gettype($this->vArgs);
	}
	return !is_null($this->vArgs);
    }
    public function execute() {
    // set variables from passed arguments

	if ($this->HasArgs()) {
	    foreach ($this->vArgs as $name => $value) {
		if (W3VarExists($name)) {
		    $oldVars[$name] = W3GetVal($name);
		}
		W3SetVar($name, $value);
	    }
	}
    // parse (execute) the function code
	$out = $this->vParser->recursiveTagParse( $this->vCode );
    // restore original variables (old value if any, or remove from list)
	if ($this->HasArgs()) {		// apparently the state can change
	    foreach ($this->vArgs as $name => $value) {
		if (isset($oldVars[$name])) {
		    W3SetVar($name, $oldVars[$name]);
		} else {
		    W3KillVar($name);
		}
	    }
	}
	return $out;
    }
}


function TrueFalseHTML($iName, $iVal) {
	if ($iVal) {
		return '<b>'.$iName.'</b>';
	} else {
		return '<font color=#7f7f7f><s>'.$iName.'</s></font>';
	}
}

class W3HookArgs {
    public $vArgs;

    public function __construct($iArgs) {
	    $this->vArgs = $iArgs;
    }
    public function Exists($iName) {
	    return isset($this->vArgs[$iName]);
    }
    /*----
      NOTE: Who uses this?
      HISTORY:
	2012-06-03 renamed from GetVal to GetRawVal for clarity; public->protected until we determine who needs it publicly
    */
/*
    protected function GetRawVal($iName) {
	if (isset($this->vArgs[$iName])) {
	    return $this->vArgs[$iName];
	} else {
	    return NULL;
	}
    }
*/
    /*----
      RETURNS: value of the *expression* given in iName
      ACTION: parses a value:
	$iName="literal value" -- returns "literal value"
	$iName=$variable -- returns value of variable
    */
/*
    public function GetExpr($iName) {
	if ($this->Exists($iName)) {
	    //$strArg = $this->vArgs[$iName];
	    $strArgVal = $this->GetArgVal($iName);
	    $strOut = W3GetExpr($strArg);
	    W3AddTrace(' - GETEXPR: ['.strtoupper($iName).']=[<b>'.$strArg.'</b>] -> [<b>'.$strOut.'</b>]');
	    return $strOut;
	} else {
	    return NULL;
	}
    }
/**/
    /*----
      RETURNS: *value* of the argument given in iName
      EXAMPLE for GetVarVal('thing1'):
	IF $iParse=FALSE:
	  thing1=thing2 - returns 'thing2'
	  thing1=$thing2 - returns '$thing2'
	IF $iParse=TRUE:
	  thing1=thing2 -- returns 'thing2'
	  thing1=$thing2 -- returns value of $thing2
      HISTORY:
	2012-06-03 simplified - does no parsing
	2012-06-04 renamed GetVarVal() -> GetArgVal()
    */
    public function GetArgVal($iName) {
	global $wgW3Trace_indent;

	W3AddTrace('GetArgVal('.$iName.')');
	$wgW3Trace_indent++;

	$strVal = $this->GetArgVal_raw($iName);
	if (!is_null($strVal)) {
	    $strVal = W3GetExpr($strVal);
	    W3AddTrace(' - parsed to ['.$strVal.']');
	}

	$wgW3Trace_indent--;
	W3AddTrace('/GetArgVal('.$iName.')');
	return $strVal;
    }
    public function GetArgVal_raw($iName) {
	$strTrace = 'ARG ['.$iName.']: ';

	if ($this->Exists($iName)) {
	    $strVal = $this->vArgs[$iName];
	    $strTrace .= 'value is ['.$strVal.']';
	} else {
	    $strVal = NULL;
	    $strTrace .= 'not found';
	}
	W3AddTrace($strTrace);
	return $strVal;
    }
    /*----
      RETURNS: a variable object named for the value of the given argument
      EXAMPLES for GetVarObj_byArgName("thing1"):
	"thing1=thing2" -  creates a variable named "thing2"
	"thing1=$thing2" - if $thing2="thing3", then this creates a variable named "thing3"
    */
    public function GetVarObj_Named_byArgVal($iName) {
	W3AddTrace('GetVarObj_Named_byArgVal('.$iName.')');
	W3TraceIndent();

	$strName = trim($iName);
	$strVal = $this->GetArgVal($strName);
	W3AddTrace('NAME=['.$strName.'] VAL=['.$strVal.']');
/*
 Value of "name" has now been parsed to its value, which is the raw name of the variable
  where we'll be storing the output of the <LET> operation.
*/
	$objVar = new clsW3Var();
	$objVar->ParseName($strVal);	// parse any name syntax (usually array brackets)

	W3TraceOutdent();
	W3AddTrace('/GetVarObj_Named_byArgVal('.$iName.')');

	return $objVar;
    }
/*
	public function ParseVal($iName) {
	    if (array_key_exists($iName,$this->vArgs)) {
		$strArg = $this->vArgs[$iName];
echo 'NAME=['.$strArg.']';
		$strOut = W3GetVal($strArg);
		return $strOut;
	    } else {
		return NULL;
	    }
	}
*/
}

/*
class W3TPL_fx {
	function runXploop ( &$parser, $inStr = '', $inTplt = '$s$', $inSep = '' )
	{
		$tok = substr ( $inStr, 0, 1);	// token for splitting
		if ($tok) {
			$tks = substr ( $inStr, 1 );	// tokenized string
			$list = explode ( $tok, $tks );	// split the string
			$sep = CharacterEscapes::charUnesc( $inSep, array(), $parser );
			$tplt = CharacterEscapes::charUnesc( $inTplt, array(), $parser );
			$out = '';
			foreach ($list as $value) {
		//		$lcnt++;
				if ($out) {
					$out .= $sep;
				}
				$out .= str_replace( '$s$', $value, $tplt );
			}
			return $parser->recursiveTagParse($out);
	//		return array($out, 'noparse' => false, 'isHTML' => false);
		} else {
			return NULL;
		}
	}
	function runXpCount ( &$parser, $inStr = '' )
	{
		$tok = substr ( $inStr, 0, 1);
		return substr_count($inStr, $tok);
	}
	function runLet ( &$parser, $iName = '', $iVal = '' ) {
		global $wgW3Vars;

		$strName = strtolower($iName);
		$strName = W3GetVal($strName);	// check for indirection ($)
		$wgW3Vars[$strName] .= $iVal;
	}
	function runGet ( &$parser, $iName = '' ) {
		$strName = strtolower($iName);
		$strVal = W3GetVal('$'.$strName);
		return $parser->recursiveTagParse($strVal);
	}
}
*/

/*
 Code for preventing raw "isolated" output from being further processed by the parser output stage
 See http://www.mediawiki.org/wiki/Manual:Tag_extensions#How_can_I_avoid_modification_of_my_extension.27s_HTML_output.3F
*/
$wgW3_Markers = array();
define('ksW3_parser_marker_start','@@W3TPL##--');
define('ksW3_parser_marker_stop','--##LPT3W@@');
function IsolateOutput($iText) {
	global $wgW3_Markers;

	$markCount = count($wgW3_Markers);
	$mark = ksW3_parser_marker_start.$markCount.ksW3_parser_marker_stop;
	$wgW3_Markers[$markCount] = $iText;
	return $mark;
}

function efW3_ParserAfterTidy(&$parser, &$text) {
	// find markers in $text
	// replace markers with actual output
	global $wgW3_Markers;

// replace markers with isolated text:
	$k = array();
	for ($i = 0; $i < count($wgW3_Markers); $i++)
		$k[] = ksW3_parser_marker_start . $i . ksW3_parser_marker_stop;
	$text = str_replace($k, $wgW3_Markers, $text);

	return true;
}
function efW3_OutputPageBeforeHTML(&$out, &$text) {
	global $wgW3_edit_queue;
// execute deferred edits:

	if (is_array($wgW3_edit_queue)) {
		foreach ($wgW3_edit_queue as $key=>$obj) {
			$obj->Exec();
		}
	}
	return TRUE;
}
class w3tpl_modules {
    private $arMods;
    private $fpBase;
    private $fpExt;
    private $strCurFile;	// file currently being loaded

    /*----
      INPUT: path to modules folder, including terminal slash
    */
    public function __construct($fpModules,$iExt='.php') {
	$fpThis = dirname( __FILE__ );
	$this->arMods = array();
	$this->fpBase = $fpThis.'/'.$fpModules;
	$this->fpExt = $iExt;
    }
    /*----
      INPUT: base name of file - no extension, no path
    */
    public function Register($iFile) {
	$fs = $this->fpBase.$iFile.$this->fpExt;
	$this->strCurFile = $iFile;
	if (file_exists($fs)) {
	    require_once($fs);
	} else {
	    echo '<pre>';
	    throw new exception("Attempted to load nonexistent W3TPL module from file [$fs].");
	}
	$this->strCurFile = NULL;
    }
    public function Register_class(w3tpl_module $iModule) {
	$strKey = $this->strCurFile;
	$this->arMods[$strKey] = $iModule;
    }
    public function Dispatch($iModule, $iFunc, array $iArgs, Parser $iParser) {
	$strMod = $iModule;
	if (!array_key_exists($strMod,$this->arMods)) {
	    // if module is not already registered, attempt to register it based on the name given
	    $this->Register($strMod);
	}
	$objMod = $this->arMods[$strMod];
	$rtn = $objMod->Exec($iFunc,$iArgs,$iParser);
	if (is_null($rtn)) {
	    return '<b>Warning</b>: function '.$iFunc.' returned NULL. Use empty string instead.';
	} else {
	    return $rtn;
	}
    }
}
$wgW3Mods = new w3tpl_modules('plugins/');
abstract class w3tpl_module {
    protected $objParser;
    private $isMethodFound;

    public function __construct() {
	global $wgW3Mods;

	$wgW3Mods->Register_class($this);
    }
    public function Exec($iFName,array $iArgs,Parser $iParser) {
	$strName = 'w3f_'.$iFName;
	$this->objParser = $iParser;

	$this->isMethodFound = method_exists($this, $strName);
	if ($this->isMethodFound) {
	    $out = $this->$strName($iArgs);
	} else {
	    $out = "<b>Error</b>: Function [$iFName] not found in plugin module."
	      .'<br> - <b>class</b>: '.get_class($this)
	      .'<br> - <b>method</b>: '.$strName;
	}
	return $out;
    }
    public function ExecOk() {
	return $this->isMethodFound;
    }
    /*----
      HISTORY:
	2012-09-18 changed from protected to public so that child-objects can use it
    */
    public function Parse_WikiText($iText) {
	$out = $this->objParser->recursiveTagParse($iText);
	return $out;
    }
}
