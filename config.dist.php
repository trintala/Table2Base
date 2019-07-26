<?php

	//$obc_to_utf8 = true;
	define('pagePrefix', ""); // Set data ID prefix
	define('t2bcomment', "Uploaded using table2base extension");
	
	// $wikis = array('Opasnet' => 1, 'FI Opasnet' => 2, 'Heande' => 3, 'TEST_ERAC' => 4);
	
	define('wikiID', 1);

	// wikibase variables
	// It is recommended to create a new user restricted to the table2base tables of your wiki(s) 
	define('obcWiki', 'localhost'); // host
	define('obcWikiUsername', ''); // t2b user
 	define('obcWikiPassword', ''); // t2b user password
 	define('obcWikiDatabase', ''); // the wiki database name corresponding to this wiki

	define('wikiServer', ''); // your wiki url
	
	// Path to Opasnet Base Upload module required!!!
	define('OpasnetBaseUploadPath', dirname(__FILE__)."/../OpasnetBaseImport2/lib/OpasnetBaseUpload.php");	
	
 	
?>