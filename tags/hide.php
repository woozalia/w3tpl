<?php namespace w3tpl;
/*
  TAG: <hide>
  ACTION: Doesn't display anything between the tags. Basically does nothing.
    Any tag that wants to be able to display certain things *anyway* must handle output directly.
  HISTORY:
    2012-01-15 returning NULL now causes UNIQ-QINU tag to be emitted; changing to '' seems to fix this.
    2017-10-29 adapting from old-style code
*/
class xcTag_hide {	// doesn't descend from xcW3Tag because it doesn't need any services
    static public function Call($sInput, array $arArgs, \Parser $mwoParser = NULL, $mwoFrame = FALSE) {
	$mwoParser->recursiveTagParse( $sInput );	// process any invisible stuff (e.g. SMW, categories)
	return '';
    }
}
