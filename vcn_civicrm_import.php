#!/usr/bin/php -q

<?php

//=================================================================//
//                       CIVICRM IMPORT SCRIPT                     //
//-----------------------------------------------------------------//
//                                                                 //
// USAGE: ./vcn_civicrm_import OFFSET, COUNT [--log]               //
//                                                                 //
// USAGE EXAMPLES:                                                 //
//                                                                 //
// ./vcn_civicrm_import 0 100                                      //
//                                                                 //
// Imports the first 100 records found in the mysql database with  //
// civicrm                                                         //
//                                                                 //
//                                                                 //
// ./vcn_civicrm_import 5 10 --log                                 //
//                                                                 //
// Imports 10 records starting from OFFSET 5 with logging turned   //
// on                                                              //
//                                                                 //
//                                                                 //
//=================================================================//

// add optional parameters

// load the civicrm api
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/civicrm.config.php';
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/CRM/Core/Config.php';
require_once '/home/wp/public_html/wp-content/plugins/civicrm/civicrm/api/v3/Tag.php';

// set database access variables: $HOST, $USERNAME, $PASS 
include 'db_config.php';

// directory path for script log file
$LOG_DIR = 'log/civicrm_import.log';

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
 * Do some checking on argv parameters                              *
 ********************************************************************/

function check_params($argc, $argv)
{

   if ($argc == 4)
   {

      if ( is_integer( (int) $argv[1] ) && is_integer( (int) $argv[2] ) )
      {
         if ( $argv[3] == "--log" || $argv[3] == "-l" )
         {
            $PARAMS["OFFSET"] = (int)$argv[1];
            $PARAMS["COUNT"] = (int)$argv[2];
            $PARAMS["LOGGING"] = TRUE;       
         }
         else
         {
            echo "Error, invalid argument. Program exiting.\n";
            exit();
         }
      }
      else
      {
         echo "Error, invalid argument. Program exiting.\n";
         exit();
      }
   }
   elseif ($argc == 3)
   {
      if ( is_integer( (int) $argv[1] ) && is_integer( (int) $argv[2] ) )
      {
         $PARAMS["OFFSET"] = (int)$argv[1];
         $PARAMS["COUNT"] = (int)$argv[2];
         
      }
      else
      {
         echo "Error, invalid argument. Program exiting\n";
         exit();
      } 
   }
   else
   {
      echo "Wrong number of arguments. Program exiting.\n";
      exit();
   }

   return $PARAMS;

}

/********************************************************************
 *                              MAIN                                *
 ********************************************************************/

$PARAMS = check_params($argc, $argv);

// create connection
$con=mysql_connect($HOST, $USERNAME, $PASS);

if (!$con)
{
   die('Not connected: ' . mysql_error());
}

mysql_select_db("import_test", $con) or die(mysql_error());
$result = mysql_query("SELECT username, fullname FROM users ORDER BY
                       username LIMIT {$PARAMS["OFFSET"]},{$PARAMS["COUNT"]}")
                       or die(mysql_error());  


$update_civicrm_record_count = 0;
$create_civicrm_record_count = 0;

$start_time = time(true);


// store the record of the users table into $row
while ($row = mysql_fetch_array( $result ))
{
   $email_address = $row['username'] . "@vcn.bc.ca";
   $list = get_contacts($email_address);
   $values = $list['values'];
   
      if ($list['count'] == 1)
   {
      $update_civicrm_record_count += 1;
      update_civicrm_record($values, $row);
   }
   elseif ($list['count'] == 0)
   {
      $create_civicrm_record_count += 1;
      create_civicrm_record($row, $email_address);
   }
   else
   {
      echo "Error, Duplicate Records Found: {$email_address}";
   }
}

$end_time = time(true);
$total_time = $end_time - $start_time; 
$total_seconds = $total_time % 60; 
$total_mins = floor($total_time / 60);

if (array_key_exists("LOGGING", $PARAMS))
{
   if ($PARAMS["LOGGING"] == TRUE)
   {
      $file_name = "{$LOG_DIR}";
      $file_handle = fopen($file_name, 'a') or die("can't open file");
      $date = date('[Y-m-d H:i:s]');
      fwrite($file_handle, "{$date} {$create_civicrm_record_count} records created." );
      fwrite($file_handle, " {$update_civicrm_record_count} records updated." );
      fwrite($file_handle, " Operation has taken {$total_mins} minute(s) and {$total_seconds} second(s) to execute.\n" );
      fclose($file_handle);
   }
}

?>
