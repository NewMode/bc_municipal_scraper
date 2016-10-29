<?php
set_time_limit(0);
error_reporting(E_ERROR | E_PARSE);
require_once("config.php");
require_once("functions.php");
require_once("advanced_html_dom.php");


if (ob_get_level() == 0) ob_start();
function flushOutput()
{
	echo str_pad('',4096)."\n";

    ob_flush();
    flush();
}

/*
$url = "https://www.civicinfo.bc.ca/people?pn=1&stext=&type=ss&lgid=1&agencyid=+";
$content = scrapURLWithCurl($url);
//$total = getTotalRecords($content);
//echo $total;
$types = ["Mayor", "Councillor", "Commissioner"];
$records = getRecords($content, $types);
echo "<pre>";
print_r($records);
echo "</pre>";
exit;
$url = "https://www.civicinfo.bc.ca/people";
$content = scrapURLWithCurl($url);
$lgs = getLocalGovernments($content);
echo "<pre>";
print_r($lgs);
echo "</pre>";

exit;
*/

echo "Starting the scraping process..<br />";
flushOutput();

//get local governments
echo "Getting local governments..<br />";
flushOutput();
$url = "https://www.civicinfo.bc.ca/people";
$content = scrapURLWithCurl($url);
$lgs = getLocalGovernments($content);
echo "There are ". count($lgs) ." local governments<br />";
flushOutput();
if(count($lgs) == 0)
	die("No local government found.");

$output_file = "outputs/". date("Y-m-d H-i-s") .".csv";
if(!file_exists($output_file))
	touch($output_file);
$line = array();
$line[] = "District name";
$line[] = "Government Type";
$line[] = "Primary role";
$line[] = "Name";
$line[] = "Phone";
$line[] = "Email";
$line[] = "Source URL";
//write to the csv file
$fp = fopen($output_file, 'w');
fputcsv($fp, $line);
fclose($fp);

foreach($lgs as $lg)
{
	$lgid = $lg["lgid"];
	$local_government_name = $lg["place_name"];
	$local_government_type = $lg["place_type"];
	echo "Getting local government record for ". $local_government_name ." (". $local_government_type .")..<br />";
	flushOutput();
	$record_url = "https://www.civicinfo.bc.ca/people?pn=1&stext=&type=ss&lgid=". $lgid ."&agencyid=+";
	$content = scrapURLWithCurl($record_url);
	//$types = ["Mayor", "Councillor", "Commissioner"];
	$records = getRecords($content, $RECORD_TYPES);
	$total_records = getTotalRecords($content);
	if($total_records > 10)
	{
		$max_pages = ceil($total_records / 10);
		for($page = 2; $page <= $max_pages; $page++)
		{
			echo "Page no ". $page ."<br />";
			flushOutput();
			$record_url = "https://www.civicinfo.bc.ca/people?pn=". $page ."&stext=&type=ss&lgid=". $lgid ."&agencyid=+";
			$content = scrapURLWithCurl($record_url);
			$temp_records = getRecords($content, $RECORD_TYPES);
			$records = array_merge($records, $temp_records);
		}
	}
	echo "Records (as per record types) found = ". count($records) ."<br />";
	flushOutput();
	//write to the csv file
	$fp = fopen($output_file, 'a');
	foreach($records as $record)
	{
		$line = array();
		$line[] = $local_government_name;
		$line[] = $local_government_type;
		$line[] = $record["type"];
		$line[] = $record["name"];
		$line[] = $record["phone"];
		$line[] = $record["email"];
		$line[] = $record_url;

		fputcsv($fp, $line);
	}
	fclose($fp);

	/*
	echo "<pre>";
	print_r($records);
	echo "</pre>";
	*/
	/*
	++$i;
	if($i == 5)
		break;
		*/
}
echo "All done";
exit;

$accounts_csv = INPUT_DIR ."/accounts.csv";
$accounts = getAccounts($accounts_csv);
if(empty($accounts))
	die("There is no account to scrape for. Upload a non-empty list of accounts with login credentials first.");

echo "Starting the scraping process..<br />";
flushOutput();

$output_file = OUTPUT_DIR ."/". date("Y-m-d H-i-s") .".csv";
if(!file_exists($output_file))
	touch($output_file);

$driver = null;
$accounts_done = array(); //tracks the accounts already scraped for

$account_index = 0;
$login_url = "https://account.t-mobile.com/";

while(true)
{
	if($account_index >= count($accounts))
		break; //we are done
	//every time start with a new session
	if($driver != null)
	{
		//qutit the driver
		$driver->quit();
		$driver = null;
	}
	$account = $accounts[$account_index];
	try
	{
		echo "Logging ". $account["username"] ." in and scraping details...<br />";
		flushOutput();
		$proxyOptions = array();
		if(!empty($account["proxy"]))
		{
			$proxyOptions = array("httpProxy" => $account["proxy"], "sslProxy" => $account["proxy"]);
			echo "Using proxy ". $account["proxy"] ."<br />";
			flushOutput();
		}
		startSelenium($driver, 4444, $proxyOptions);
		loginToAccount($driver, $login_url, $account["username"], $account["password"]);
		sleep(5);
		$content = $driver->getPageSource();
		if(checkIfStillLoginPage($content))
		{
			echo "Could not log in. Please make sure the account login credential is correct. Trying once more...<br />";
			flushOutput();
			loginToAccount($driver, $login_url, $account["username"], $account["password"]);
			sleep(5);
			$content = $driver->getPageSource();
			//die();
			//$account_index++;
			//continue;
		}

		$details = getAccountDetails($content);
		$line = array();
		$line[] = $details["number"];
		$line[] = $details["usage"];
		$line[] = $details["account_status"];
		$line[] = $account["username"];
		$line[] = $details["plan"];
		$line[] = $details["next_charge_date"];
		//write to the csv file
		$fp = fopen($output_file, 'a');
		fputcsv($fp, $line);
		fclose($fp);
		//to the next account
		$account_index++;
	}
	catch(Exception $e)
	{
		echo "Some error. Will resume from the last scraped account.<br />";
		flushOutput();
		$driver = null;
	}
}
if($driver != null)
{
	$driver->quit();
}
echo "Done";
?>
