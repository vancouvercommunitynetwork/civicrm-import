#!/usr/bin/php -q

<?php

//=================================================================//
//								 CIVICRM IMPORT SCRIPT	 				  		 //
//-----------------------------------------------------------------//
//																						 //
// USAGE: ./update [OFFSET, COUNT]											 //
//																						 //
// USAGE EXAMPLE:																	 //
// 																					 //
// ./update 0,100																	 //
//																						 //
// updates the first 100 records found in the mysql database with	 //
// civicrm																			 //
//																						 //
//=================================================================//

// add optional parameters

// load the civicrm api
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/civicrm.config.php';
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/CRM/Core/Config.php';
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/api/v3/Tag.php';

// set database access
include 'civicrm_db_config.php';

/********************************************************************
 * Get contacts in CiviCRM database by email address                *
 ********************************************************************/

function get_contacts($email_address)
{
	$params = array(
		'email' => $email_address 
	);

	try
	{
	  $result = civicrm_api3('contact', 'get', $params);
	}
	catch (CiviCRM_API3_Exception $e)
	{
	  // handle error here
	  $errorMessage = $e->getMessage();
	  $errorCode = $e->getErrorCode();
	  $errorData = $e->getExtraParams();
	  return array('error' => $errorMessage, 
						'error_code' => $errorCode, 'error_data' => $errorData);
	}

	return $result;
}

/********************************************************************
 * Split fullname into first  name and last name                    *
 ********************************************************************/

function split_name($fullname)
{
	$split_name = explode(" ", $fullname);
	$name['first_name'] = $split_name[0];
	unset($split_name[0]);
	$name['last_name'] = implode(" ", $split_name);
	return $name;
}

/********************************************************************
 * Update the matching civicrm record                               *
 ********************************************************************/

function update_civicrm_record($values, $row)
{
	
	foreach ($values as $user)
	{

		$name = split_name($row['fullname']);

		$params =array('id' => $user['id'],
							'first_name' => $name['first_name'],
							'last_name' => $name['last_name']
				  		  );	

		$result = civicrm_api3('contact', 'create', $params);
	}

}

/********************************************************************
 * Create new record in civicrm                                     *
 ********************************************************************/

function create_civicrm_record($row, $email_address)
{
	$name = split_name($row['fullname']);
	$params = array('email' => $email_address,
						 'contact_type' => 'Individual',
						 'first_name' => $name['first_name'],
						 'last_name' => $name['last_name']
						);
	$result = civicrm_api3('contact', 'create', $params);

}

/********************************************************************
 *                              MAIN                                *
 ********************************************************************/


if ($argc == 2)
{
	$db_params = explode("," , $argv[1]);
}
else
{
	echo "Wrong number of arguments. Program exiting.\n";
	exit;
}



// create connection
$con=mysql_connect($HOST, $USERNAME, $PASS);

if (!$con)
{
	die('Not connected: ' . mysql_error());
}

mysql_select_db("import_test", $con) or die(mysql_error());
$result = mysql_query("SELECT username, fullname FROM users ORDER BY
							  username LIMIT {$db_params[0]},{$db_params[1]}") or die(mysql_error());  

$start_time = microtime(true);

// store the record of the users table into $row
while ($row = mysql_fetch_array( $result ))
{
	$email_address = $row['username'] . "@vcn.bc.ca";
	$list = get_contacts($email_address);
	$values = $list['values'];
	
	if ($list['count'] == 1)
	{
//		echo "Update Record {$email_address}\n";
		update_civicrm_record($values, $row);
	}
	elseif ($list['count'] == 0)
	{
//		echo "Create Record: {$email_address}\n";
		create_civicrm_record($row, $email_address);
	}
	else
	{
		echo "Error, Duplicate Records Found, Program Terminating";
	}
}


$end_time = microtime(true);

$total_time = $end_time - $start_time; 

echo "\nOperation has taken " . $total_time . " seconds\n";

?>
