<?php

require_once(dirname(__FILE__).'/config.php');

class Table2BaseParser
{
	public static function efT2BParserInit(&$parser) {
			//global $wgParser;
			$parser->setHook( 't2b', 'Table2BaseParser::efT2BRender' );
			return true;
	}

	static function writeToWikitable($hashed_output,$name)
	{
		global $wgOut;
		$dbw = wfGetDB( DB_MASTER );
		$articleID = $wgOut->getTitle()->getArticleId();	
		//$articleID = mysql_real_escape_string($articleID);
		$hashed_output = $hashed_output;
		//$dbw->query("insert into table2base values('$articleID','$hashed_output',now(),'$name');");
		$dbw->insert('table2base', ['page_id' => $articleID, 'tag_text' => $hashed_output, 'name' => $name]);
	}


	static function write2base($inputs,$output,$args,$name)
	{
		global $wgUser, $wgOut;
		//include('config.php');
		require_once(OpasnetBaseUploadPath);
		$obcWikiCon = mysqli_connect(obcWiki, obcWikiUsername, obcWikiPassword, obcWikiDatabase) or die("Failed to connected to the database");
		//mysqli_select_db($obcWikiCon, obcWikiDatabase) or die("Couldn't select database");
		$articleID = $wgOut->getTitle()->getArticleId();
		$articleID = mysqli_real_escape_string($obcWikiCon, $articleID);
		$user_name = $wgUser->getName();
		$user_name = mysqli_real_escape_string($obcWikiCon, $user_name);
		$articleName = $wgOut->getPageTitle();
		$articleName = mysqli_real_escape_string($obcWikiCon, $articleName);
		$wikiID = mysqli_real_escape_string($obcWikiCon, wikiID);
		$comment = mysqli_real_escape_string($obcWikiCon, t2bcomment);
		$pageIdent = pagePrefix.$articleID;
		$result_rows = explode("\n",$inputs);
		
		//hashing
		//echo $output;
		$output = mysqli_real_escape_string($obcWikiCon, $output); 	
		$hashed_output = hash('md5', $output);
		//$dbw = wfGetDB( DB_MASTER );

		//finding out if this data already exists (we don't want to write same data twice into Base)
		$wres = mysqli_query($obcWikiCon, "select tag_text from table2base where page_id = '$articleID' and name = '$name' order by time desc limit 1;");
		$wros = mysqli_num_rows($wres);

		//if there is already data for this variable
		if($wros>0)
		{
			//$current = mysqli_result($wres,0);
			$current = mysqli_fetch_assoc($wres)['tag_text'];
			//we compare current data to output, if they are same we set go to 0 (we won't write to base)
			if($current === $hashed_output) return '';
		}
		
		Table2BaseParser::writeToWikitable($hashed_output, $name);
		
		
		for( $i = 0; $i < count( $result_rows ); $i++ )
		{
			$result_rows[$i] = $i."|".$result_rows[$i];
			//echo $result_rows[$i]."<br>";

		}
		//we need to remove 2 empty rows from the calculation (empty rows at start and at the end)
		array_pop($result_rows);
		array_shift($result_rows);
		$unit = $args['unit'];
		$unit = mysqli_real_escape_string($obcWikiCon, $unit);
		//creating a result table ADD HERE number of OBS?
		for( $i = 0; $i < count( $result_rows ); $i++ )
		{
			$result_array[$i] = explode( "|", $result_rows[$i] );
		}
		//ADD HERE OBS as index
		$obsnum[] = "Obs";
		$indices = explode(",",$args['index']); //Reads all indices into array
		$indices = array_merge((array)$obsnum, (array)$indices);

		if(isset($args['locations']))
		{ 
			$locations = explode( ",", $args['locations'] );
			$result_array = OpasnetBaseUpload::create_multiresult_table($indices, $locations, $result_array);
		}
		//jos on m??ritelty desc mutta ei locationeja niin result -riveist? t?ytyy pois descien m??r? soluja.
		if(!isset($args['locations']) && isset($args['desc']))
		{
			$descs = explode( ",", $args['desc'] );
			$desc_count = count($descs);
			for( $i = 1; $i <= $desc_count; $i++ )
			{
				for( $j = 0; $j < count($result_rows); $j++ )
				{	
					array_pop($result_array[$j]);
				}
			}


		}
			
		// Complete indices
		$inds = array();
		$i = 1;
		foreach ($indices as $ind)
		{
			$inds[$i] = array('type' => 'entity', 'name' => $ind, 'page' => 0, 'wiki_id' => $wikiID, 'order_index' => $i, 'hidden' => 0);
			$i ++;
		}
		// echo $name;
		OpasnetBaseUpload::upload_to_base($pageIdent, $unit, $inds, $result_array, $comment, $name);
		return "<strong>Data updated successfully!<br/></strong>";	

	}
	 
	public static function efT2BRender( $input, $args, $parser ) 
	{
		global $wgUser, $wgOut;
		$message = "";
		//include('config.php');
		require_once(OpasnetBaseUploadPath);
		if (intval($wgOut->getTitle()->getArticleId()) == 0) return '';
		
		$pageIdent = pagePrefix.$wgOut->getTitle()->getArticleId();
		$variableName = $wgOut->getPageTitle();
		//echo $variableName;
		//we need your username
		$user_name = $wgUser->getName();
		$user_id = $wgUser->getID();
		//first we need to calculate the number of rows (enter marks the spot!)
		$rows = explode("\n",$input);
		//we need to remove 2 empty rows from the calculation (empty rows at start and at the end)
		$num_of_rows = count($rows)-2;
		//global $counter;
		//echo "counter: ".++$counter;
		//name of datatable
		$name ='';
		if(isset($args['name'])&& $args['name']!='')
		{	
			$name = $args['name'];
			$variableName = $name;
		}
		//echo "Name is:".$name;

		$desc_count = 0;
		$descs = '';
		if( isset($args['desc']) && $args['desc'] != '' )
		{	
			
			$descs = explode(",",$args['desc']);
			$desc_count = count($descs);
			
		}
		//echo "Desc is:".$descs[0];
		//default number of observations
		$obs_num = 1;
		if(isset($args['obsnum'])) $obs_num = $args['obsnum'];
		
		//getting indexes if defined
		if($args['index'] != "")
		{
			$indexes = explode(",", $args['index']);
			$headers = explode(",", $args['index']);
		}
		else $message = "You must define at least one index. Please use syntax: index=\"index1,index2\"";
		//ADDING obligatory row numbering to indices
		$obsnum[] = "Obs";
		$headers = array_merge( (array)$obsnum, (array)$headers );
		//if we have more than 1 observations we need to pop last index out because that is not displayed
		//if($obs_num>1) array_pop($headers);
			
		//getting extra locations for results if defined, these are used in display purposes also
		if(isset($args['locations']) && $args['locations']!="")
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
		if($unit=="") $message.="<br/>You must define units. Please use syntax: unit=\"unit\"";
		
		//counting number of headers (which have titles)
		$num_of_headers = count($indexes) + $obs_num;
		//this is needed if there is only one result per row...
		if(! isset($args['locations']) || $args['locations']=="") $headers[] = $observation;
		//number of columns in data (only from 1 row...)
		$num_of_columns = count(explode("|",$rows[1]));
		//this checks if every data row have equal number of cells
		for( $i = 1; $i <= $num_of_rows; $i++ )
		{
			$this_row_count = count(explode("|",$rows[$i]));
			if($num_of_columns != $this_row_count) $message.= "<br/>You have invalid number of data cells in row $i";
		}
		//ADDED -1 here because of one added index (Obs)
		$output = '';
		if(count($headers) + $desc_count - 1 - $num_of_columns != 0) $message.= "<br/>Number of indices and result cells does not match";
		if($message != "") $output.= "<strong>You have error(s) in your data:<br/></strong>".$message;
		if($num_of_rows>0)
		{
			$output.= "<table class=\"wikitable sortable\" border=\"2\" cellspacing=\"0\" cellpadding=\"3\" style=\"margin:0em 1em 1em 1em; border:solid 1px #AAAAAA; border-collapse:collapse;background-color:#F9F9F9; font-size:90%; empty-cells:show;\">";
			$link = wikiServer."/Special:Opasnet_Base?id=".strtolower($pageIdent);
			if (! empty($name))
			{
				$link .= '.' . OpasnetBaseUpload::sanitize_subset_name($name);
			}
			$output.= "<caption><a href=\"".$link."\">".$variableName."(".$unit.")</a></caption>";
			$output.= "<tr>";
			foreach ($headers as $value) $output.= "<th>".$value."</th>";
			if($desc_count>0) foreach ($descs as $value) $output.= "<th>".$value."</th>";
			$output.= "</tr>";
			for( $i = 1; $i <= $num_of_rows; $i++ )
			{
				$output.= "<tr><td>".$i."</td>";
				$inputs = explode("|",$rows[$i]);
				$num_of_elements = count($inputs);
				for( $j = 0; $j < $num_of_elements; $j++ )
				{
					$output.= "<td>".$inputs[$j]."</td>";
				}
				$output.=  "</tr>";
			}
			$output.=  "</table>";
		}
		//echo 'Action Information: '.$_GET['action'].$_GET['oldid'].$message.'\n';
		//This checks if user is in edit, preview or history mode -> we don't want to write that into Base...
		if((! isset($_GET['action']) || $_GET['action']!="submit") && $message=="" && (! isset($_GET['oldid']) || $_GET['oldid']=="")) 
		{
			$ret = Table2BaseParser::write2base($input,$output,$args,$name);
			$output = $ret.$output;
		}
		return $output;
		
	}
	
	public static function onDatabaseUpdate( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'table2base',
			__DIR__ . '/table2base.sql' );
		return true;
	}
}
