<?php

//writes $articleID,$hashed_output and timestamp into table2base -table in Mediawiki installation
//this data is needed when we check if t2b -table is updated
//$hashed_output = hashed contents from t2b -tags, ie. data to be written into Opasnet base in raw form
//$articleID = articleID from Mediawiki
function writeToWikitable($hashed_output)
{
	global $wgTitle;
	$dbw = wfGetDB( DB_MASTER );
	$articleID = $wgTitle->getArticleID();
	$articleID = mysql_real_escape_string($articleID);
	$hashed_output=$hashed_output;
	$dbw->query("insert into table2base values('$articleID','$hashed_output',now());");
}

//writes data into Opasnet Base
//$indices = indices (array) of the data
//$inputs = actual result data (array)
//$v_unit = unit of the data
//$articleIdent = identifier of the article data belongs to. May be just article_id from wiki (ie. 1234) or contain also wiki_identifier (ie. op_en1234)
//$v_comment = comment that is uploaded as a run description to Opasnet Base. t2b doesn't use that but csv-importer uses.
function writeToOB($indices,$inputs,$v_unit,$articleIdent,$v_comment="not set")
{
	include(dirname(__FILE__).'/config.php');
	//we have to look for wikiprefix and remove it if it's found, this is used for csv-uploader purposes
	$articleIdent = str_replace($pagePrefix,"",$articleIdent);
	//if prefix couldn't be removed
	if(is_numeric($articleIdent)==false) throw new Exception('ArticleIdent is not valid!');
	//checking if articlename exists...
	$dbw = wfGetDB( DB_SLAVE );
	$res = $dbw->select('page',array('page_title','page_id'),"page_id={$articleIdent}",__METHOD__);
	foreach( $res as $row ) 
	{
       	$articleName = $row->page_title;
	}
	//exception, if articleIdent is not found from the Wiki database
	if (count($res)<1) throw new Exception('ArticleIdent name not found!'); 
	//setting up some variables
	global $wgUser;
	$articleID = $articleIdent;
	$articleID = mysql_real_escape_string($articleID);
	$wikiID = mysql_real_escape_string($wikiID);
	//if v_comment is not given as function parameter then we use comment from config.php
	if($v_comment=="not set")
	{
		$comment = mysql_real_escape_string($comment);
	}
	else
	{
		$comment = mysql_real_escape_string($v_comment);
	}
	$user_name=$wgUser->getName();
	$user_name= mysql_real_escape_string($user_name);
	$articleName = mysql_real_escape_string($articleName);
	$result_rows = $inputs;
	$num_of_rows = count($result_rows);
	$indices =$indices;
	$indices_num = count($indices);
	$unit = $v_unit;
	$unit = mysql_real_escape_string($unit);
	//$pagePrefix comes from config.php
	$pageIdent = $pagePrefix.$articleIdent;
	$pageIdent = mysql_real_escape_string($pageIdent);
	$obcCon = mysql_connect($obcHost, $obcUsername, $obcPassword) or die("Failed to connected to the database");
	mysql_select_db($obcDatabase,$obcCon) or die("Cannot select database!");
	//first we need to find out if variable exists in Opasnet Base...
	$res = mysql_query("select * from obj where ident = '$pageIdent' ;",$obcCon);
	if(mysql_num_rows($res)==0)
	{
		//WRITE new variable into Opasnet Base
		mysql_query("insert into obj(ident,name,objtype_id,page,wiki_id) values('$pageIdent','$articleName',1,'$articleID','$wikiID');",$obcCon);
		//set varId;
		$res = mysql_query("select id from obj where ident = '$pageIdent'",$obcCon);
		$obj_id_v = mysql_result($res,0); //this is not needed
	} 		
	else mysql_query("update obj set name = '$articleName' WHERE ident = '$pageIdent';",$obcCon);
	$res = mysql_query("select id from obj where ident = '$pageIdent'",$obcCon);
	$obj_id_v = mysql_result($res,0);
	$obj_id_v = mysql_real_escape_string($obj_id_v);

	//set up a new act
	//found out max act id
	$res = mysql_query("select MAX(id) from act;",$obcCon);
	$act_id = mysql_result($res,0)+1; //add 1 to current max(id)
	$act_id = mysql_real_escape_string($act_id);
	mysql_query("insert into act(id,acttype_id,who,comments,time) values ('$act_id',4,'$user_name','$comment',now());",$obcCon);
	//next we need id's for indices ($indices_id[])
	foreach ($indices as $value)
	{
		$value = mysql_real_escape_string($value);
		$res=mysql_query("select id from obj where ident = '$value' and objtype_id = 6;",$obcCon);
		//if index does not exist we create it
		if(mysql_num_rows($res)==0)
		{
			mysql_query("insert into obj(ident,name,objtype_id,wiki_id) values ('$value','$value',6,'$wikiID');",$obcCon);
			$res=mysql_query("select id from obj where ident = '$value' and objtype_id = 6;",$obcCon);
			$indices_id[] = mysql_result($res,0);
		}
		else
		{
			$indices_id[] = mysql_result($res,0);
		}
	}
		
	//NEXT to actobj table...	
	$res = mysql_query("select MAX(id) from actobj;",$obcCon); //max(id) from actobj
	$actobj_id = mysql_result($res,0)+1; //add 1 to current max actobj_id
	$actobj_id = mysql_real_escape_string($actobj_id);
	//write new actobj into actobj -table. NOTE extension supports currently only 1 object per act
	mysql_query("insert into actobj(id,act_id,obj_id,series_id,unit) values ('$actobj_id','$act_id','$obj_id_v','$act_id','$unit');",$obcCon);
	$series_id = $actobj_id; 
	
	//NEXT to CELL...	
	//We need as many cells as there are rows. One row one cell one result
	for($i=0;$i<$num_of_rows;$i++)
	{
		//search for max ID from cell...
		$res = mysql_query("select MAX(id) from cell;",$obcCon);
		$cell_id = mysql_result($res,0)+1; //add 1 to current max cell_id
		$cell_id = mysql_real_escape_string($cell_id);
		//write new cell_id to base. First we need to find out result which is in locations array
		$result = $result_rows[$i][$indices_num]; //result is the last cell in each row
		$result = mysql_real_escape_string($result);
		mysql_query("insert into cell(id,actobj_id,mean,n) values ('$cell_id','$actobj_id','$result',1);",$obcCon);
		//NOW we need to find out Locations
		for($j=0;$j<$indices_num;$j++) //we need to find out if there are locations, if not we create them
		{
			$indices_id[$j] = mysql_real_escape_string($indices_id[$j]);
			$location = mysql_real_escape_string($result_rows[$i][$j]);
			$res = mysql_query("select id from loc where obj_id_i = '$indices_id[$j]' and location = '$location';",$obcCon);
			if(mysql_num_rows($res)>0) //IF LOCATION exists... write to loccell and actloc
			{
				$loc_id=mysql_result($res,0);
				$loc_id = mysql_real_escape_string($loc_id);
				mysql_query("insert into loccell(cell_id,loc_id) values ('$cell_id','$loc_id');",$obcCon);
				mysql_query("insert into actloc values ('$actobj_id','$loc_id');",$obcCon);
			}
			else //write to loc, loccell and actloc
			{
				$res = mysql_query("select MAX(id) from loc;",$obcCon);
				$loc_id=mysql_result($res,0)+1;
				$loc_id = mysql_real_escape_string($loc_id);
				$obj_id_i = $indices_id[$j];
				$obj_id_i = mysql_real_escape_string($obj_id_i);
				$location = $result_rows[$i][$j];
				$location = mysql_real_escape_string($location);
				mysql_query("insert into loc(id,obj_id_i,location) values('$loc_id','$obj_id_i','$location');",$obcCon) or die(mysql_error());
				mysql_query("insert into loccell(cell_id,loc_id) values ('$cell_id','$loc_id');",$obcCon);
				mysql_query("insert into actloc values ('$actobj_id','$loc_id');",$obcCon);
			}
		}
		//FINALLY we write results! 		
		if(is_numeric($result)==0) //if result is not numerical we write to RESTEXT
		{
			mysql_query("insert into res(cell_id,obs,restext) values ('$cell_id',1,'$result');",$obcCon) or die(mysql_error());
		}
		else mysql_query("insert into res(cell_id,obs,result) values ('$cell_id',1,'$result');",$obcCon) or die(mysql_error());
	}
	return true;	
}

//converts multiple results per row array into standard one result per row array
function createMultiResultTable_array($indices,$locations,$inputs)
{
	$result_rows = $inputs;
	$num_of_rows = count($result_rows);
	$indices =$indices;
	$indices_num = count($indices);
	$extra_locs = $locations;
	$extra_locs_num = count($extra_locs);
	
	//we need to convert table into single result per row form
	if($extra_locs_num>0)
	{ 
		
		$new_row=0; 		
		$k=0;
		$next_col=0;
		//READ all results into array cells
		for($i=0;$i<$num_of_rows;$i++)
		{
			$next_col=$indices_num-1; //column to start reading results from
			for($j=0;$j<$extra_locs_num;$j++)
			{
				$tmp[$new_row]=$inputs[$i][$next_col];
				$new_row++;
				$next_col++;
			}
		}
		//foreach($tmp as $value) echo "<br/>".$value;
		
		//adds extra locations into tmp2 result array		
		$something=0;
		for($i=0;$i<count($tmp);$i++)
		{
			//echo $i."<br/>"; 
			$tmp2[$i][0]=$extra_locs[$something]; //add location into result array
			$tmp2[$i][1]=$tmp[$i]; //add result into result array
			$something++;
			if($something==$extra_locs_num) $something=0; //back to start
		}
		//foreach($res_rows as $value) echo "<br/>".$value;
		//foreach($tmp2 as $value) echo "<br/>".$value[0].$value[1];
		
		//creating array with given indices from input
		for($i=0;$i<$num_of_rows;$i++)
		{
			for($j=0;$j<=$indices_num-2;$j++)
			{
				$real_indices[$i][$j]= $inputs[$i][$j];
			}
		}
		//foreach($real_indices as $value) echo "<br/>".$value[0].$value[1];
		
		//merging results and indices
		$next = count($tmp2)/$num_of_rows;
		$cnt=0;
		$cnt2=0;
		//echo $next;
		for($i=0;$i<count($tmp2);$i++)
		{
			$result[$i]=array_merge((array)$real_indices[$cnt],(array)$tmp2[$i]);
			$cnt2++;
			if($cnt2==$next)
			{
				$cnt2=0;
				 $cnt++; //move to next index row
			}

		}
		//foreach($result as $value) echo "<br/>".$value[0].$value[1].$value[2];
	
		return $result;
	}
}
?>