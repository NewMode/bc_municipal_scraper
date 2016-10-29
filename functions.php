<?php
function scrapURLWithCurl($url, $postData = array())
{
	$cookies = 'cookie.txt';
	$uagent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:47.0) Gecko/20100101 Firefox/47.0';




    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
    curl_setopt($ch, CURLOPT_USERAGENT, $uagent);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); //60 seconds timeout for connection
	curl_setopt($ch, CURLOPT_TIMEOUT, 60); //60 seconds timeout for transfer
	if(!empty($postData))
	{
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
	}
	$scraped = curl_exec($ch);
	//echo $scraped;
	curl_close($ch);
	return trim($scraped);
}
function getLocalGovernments($content)
{
	$dom = new AdvancedHtmlDom();
	$dom->load($content);
	$select_options = $dom->find("#lgid option");

	$lgs = [];
	foreach($select_options as $option)
	{
		if(trim($option->getattribute("value")) == "")
			continue;
		$name = trim($option->plaintext);
		$name_pattern = "/(.+?) \((.+?)\)/";
		preg_match($name_pattern, $name, $match_name);
		$place_name = $match_name[1];
		$place_type = $match_name[2];
		$lg = ["lgid" => $option->getattribute("value"), "place_name" => $place_name, "place_type" => $place_type];
		$lgs[] = $lg;
	}
	return $lgs;
}
function getTotalRecords($content)
{
	$total_records = 0;
	$dom = new AdvancedHtmlDom();
	$dom->load($content);
	$record_count_h4_text = $dom->find("a[name='sresults'] ~ h4")->plaintext;
	$record_count_h4_text = trim($record_count_h4_text);
	//echo $record_count_h4_text;
	$pattern = '/([0-9]+) record/';
	preg_match($pattern, $record_count_h4_text, $match);
	if(!empty($match[1]))
		return $match[1];

	return $total_records;
}
function getRecords($content, $types = [])
{
	$records = [];
	$dom = new AdvancedHtmlDom();
	$dom->load($content);
	$r_lis = $dom->find("ol li");
	foreach ($r_lis as $li)
	{
		$name = trim($li->find("a:first")->plaintext);
		$r_chunks = explode("<br>", $li->save());
		$type = trim($r_chunks[1]);
		$phone = $r_chunks[2];
		$phone = str_replace(["Phone:", "&nbsp;"], ["", ""], $phone);
		$phone = trim($phone);
		$email = $li->find("a:last")->plaintext;
		//if type is among the required types
		if(in_array(strtolower($type), array_map('strtolower', $types)))
			$records[] = ["name" => $name, "type" => $type, "phone" => $phone, "email" => $email];
	}
	return $records;
}



//some utility
function removeNonUTF8Chars($string)
{
	$string = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
	 '|[\x00-\x7F][\x80-\xBF]+'.
	 '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
	 '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
	 '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
	 '?', $string );

	//reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
	$string = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
	 '|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $string );
	return $string;
}
?>
