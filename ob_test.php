<?php

	function the_table($results, $indices)
	{
		$kr = array();
		$t = array();
		foreach ($indices as $key => $ind)
			$kr[] = $key;
		$kr[] = 'result';
		
		// Set the top row
		foreach ($kr as $key)
			if ($key != 'result')
				$t[0][] = $indices[$key]['name'];
			else
				$t[0][] = 'result';
		
		// And the rest
		foreach ($results as $row)
		{
			$tmp = array();
			foreach ($kr as $key)
				$tmp[] = $row[$key];
			$t[] = $tmp;
		}
		
		return $t;
	}

	// Mock
	function wfMsg($str)
	{
		return $str;
	}

	error_reporting(E_ALL);
	ini_set("display_errors", "on");

	$ob_path = dirname(__FILE__).'/../OpasnetBase/';
	require_once $ob_path . "config.php";
	echo 'config required ok<br/>';
	require_once $ob_path . "OpasnetConnection.class.php";


	try {
		echo 'connection required ok<br/>';
		$connection = new OpasnetConnection();
		echo 'connection established ok<br/>';
		$obj = $connection->get_object('op_en1390');

		echo "<br/>TABLE<br/>";
		$t = the_table($obj->results(false, false), $obj->indices());
		echo "<table>";
		foreach ($t as $row)
		{
			echo "<tr>";
			foreach ($row as $cell)
				echo "<td>".$cell."</td>";
			echo "</tr>";
		}		
		echo "</table>";

	} catch (Exception $e)
	{
		echo $e;	
	}


?>
