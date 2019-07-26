<?php
# OBSOLETE
/**
 * To activate this extension, add the following into your LocalSettings.php file:
 * require_once('$IP/extensions/survey.php');
 */
 
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) ) {
        echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
        die( -1 );
}
 
// Extension credits that will show up on Special:Version    
$wgExtensionCredits['specialpage'][] = array(
        'name' => 'Table2Base',
        'version' => 1.01,
        'author' => 'Juha Villman',
        'url' => 'http://en.opasnet.org/w/Table2Base',
        'description' => 'enables direct upload of data from wikipages to Opasnet Base',
        'descriptionmsg' => 'foobar-desc',
);
 
//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	       $wgHooks['ParserFirstCallInit'][] = 'efT2BParserInit';
} else { // Otherwise do things the old fashioned way
        $wgExtensionFunctions[] = 'efT2BParserInit';
}

$wgExtensionFunctions[] = 'efT2BParserFunction_Setup';
# Add a hook to initialise the magic word
//$wgHooks['LanguageGetMagic'][]       = 'efT2BParserFunction_Magic';
 
function efT2BParserInit() {
        global $wgParser;
        $wgParser->setHook( 't2b', 'efT2BRender' );
        return true;
}

function efT2BParserFunction_Setup() {
        global $wgParser;
        # Set a function hook associating the "t2b" magic word with our function
        $wgParser->setFunctionHook( 't2b', 'efT2BParserFunction_Render' );
}

function write2base($inputs,$output,$args, $parser)
{
	require_once('t2bFunctions.php');
	global $wgUser;
	global $wgTitle;
	include('config.php');
	$articleID = $wgTitle->getArticleID();
	$articleID = mysql_real_escape_string($articleID);
	$user_name=$wgUser->getName();
	$user_name= mysql_real_escape_string($user_name);
	$articleName = $wgTitle->getText();
	$articleName = mysql_real_escape_string($articleName);
	$wikiID = mysql_real_escape_string($wikiID);
	$comment = mysql_real_escape_string($comment);
	$pageIdent = $pagePrefix.$wgTitle->getArticleID();

	$result_rows = explode("\n",$inputs);
	//we need to remove 2 empty rows from the calculation (empty rows at start and at the end)
	array_pop($result_rows);
	array_shift($result_rows);
	$unit = $args['unit'];
	$unit = mysql_real_escape_string($unit);
	//creating a result table
	for($i=0;$i<count($result_rows);$i++)
	{
		$result_array[$i]=explode("|",$result_rows[$i]);
	}
	$indices = explode(",",$args['index']); //Reads all indices into array
	if(isset($args['locations']))
	{ 
		$locations = explode(",",$args['locations']);
		$result_array = createMultiResultTable_array($indices,$locations,$result_array);
	}
	
	//hashing
	$output = mysql_real_escape_string($output); 	
	$hashed_output=hash('md5',$output);
	$dbw = wfGetDB( DB_MASTER );
	//finding out if this data already exists (we don't want to write same data twice into Base)
	$obcWikiCon = mysql_connect($obcWiki, $obcWikiUsername, $obcWikiPassword) or die("Failed to connected to the database");
	mysql_select_db($obcWikiDatabase,$obcWikiCon) or die("Couldn't select database");
	$wres = mysql_query("select tag_text from table2base where page_id = '$articleID' order by time desc limit 1;",$obcWikiCon);
	$wros = mysql_num_rows($wres);
	//we set go default as 1, means that we will write to base
	$go = 1;
	//if there is already data for this variable
	if($wros>0)
	{
		$current = mysql_result($wres,0);
		//we compare current data to output, if they are same we set go to 0 (we won't write to base)
		if($current==$hashed_output) $go=0;	
	}
	if($go==1) //if data is not uploaded before then this is 1
	{
		writeToWikitable($hashed_output);
		writeToOB($indices,$result_array,$unit,$pageIdent);
		return "<strong>Data updated successfully!</strong>";	
	}
	return $true;
}
 
function efT2BRender( $input, $args, $parser ) 
{
	global $wgUser;
	global $wgTitle;
	$message="";
	include('config.php');
	$pageIdent = $pagePrefix.$wgTitle->getArticleID();
	$variableName = $wgTitle->getText();
	//we need your username
	$user_name=$wgUser->getName();
	$user_id=$wgUser->getID();
	//first we need to calculate the number of rows (enter marks the spot!)
	$rows = explode("\n",$input);
	//we need to remove 2 empty rows from the calculation (empty rows at start and at the end)
	$num_of_rows = count($rows)-2;

	//default number of observations
	$obs_num = 1;
	if(isset($args['obsnum'])) $obs_num = $args['obsnum'];
	
	//getting indexes if defined
	if($args['index']!="")
	{
		$indexes = explode(",",$args['index']);
		$headers = explode(",",$args['index']);
	}
	else $message = "You must define at least one index. Please use syntax: index=\"index1,index2\"";
	
	//if we have more than 1 observations we need to pop last index out because that is not displayed
	//if($obs_num>1) array_pop($headers);
		
	//getting extra locations for results if defined, these are used in display purposes also
	if($args['locations']!="")
	{
		array_pop($headers); //popping out last index because it is not displayed
		$extra_locs = explode(",",$args['locations']);
		foreach ($extra_locs as $value) $headers[] = $value;
	}

	//getting observation name if it is defined, otherwise use result.
	if(isset($args['obs'])) $observation = $args['obs'];
	else $observation = "Result";
		
	//getting unit
	$unit = $args['unit'];
	if($unit=="") $message.="<br/>You must define unit. Please use syntax: unit=\"unit\"";
	
	//counting number of headers (which have titles)
	$num_of_headers = count($indexes)+$obs_num;
	//this is needed if there is only one result per row...
	if($args['locations']=="") $headers[] = $observation;
	//number of columns in data (only from 1 row...)
	$num_of_columns = count(explode("|",$rows[1]));
	//this checks if every data row have equal number of cells
	for($i=1;$i<=$num_of_rows;$i++)
	{
		$this_row_count = count(explode("|",$rows[$i]));
		if($num_of_columns!=$this_row_count) $message.= "<br/>You have invalid number of data cells in row $i";
	}

	if(count($headers)-$num_of_columns!=0) $message.= "<br/>Number of indices and result cells does not match";
	if($message!="") $output.="<strong>You have error(s) in your data:<br/></strong>".$message;
	if($num_of_rows>0)
	{
		$output.= "<table class=\"wikitable sortable\" border=\"2\" cellspacing=\"0\" cellpadding=\"3\" style=\"margin:0em 1em 1em 1em; border:solid 1px #AAAAAA; border-collapse:collapse;background-color:#F9F9F9; font-size:90%; empty-cells:show;\">";
		$output.= "<caption><a href=\"".$wikiServer."/Special:Opasnet_Base?id=".$pageIdent."\">".$variableName."(".$unit.")</a></caption>";
		$output.= "<tr>";
		foreach ($headers as $value) $output.= "<th>".$value."</th>";
		$output.= "</tr>";
		for($i=1;$i<=$num_of_rows;$i++)
		{
			$output.= "<tr>";
			$inputs = explode("|",$rows[$i]);
			$num_of_elements = count($inputs);
			for($j=0;$j<$num_of_elements;$j++)
			{
				$output.= "<td>".$inputs[$j]."</td>";
			}
			$output.=  "</tr>";
		}
		$output.=  "</table>";
	}
	//This checks if user is in edit, preview or history mode -> we don't want to write that into Base...
	if($_GET['action']!="submit" && $message=="" && $_GET['oldid']=="") $output=write2base($input,$output,$args).$output;
       return $output;
}
