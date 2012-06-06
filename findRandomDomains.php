<?php
// This file is a part of the Domain Search App, a PHPExperts.pro Project.
//
// Copyright (c) 2012 Theodore R.Smith (theodore@phpexperts.pro)
// DSA-1024 Fingerprint: 10A0 6372 9092 85A2 BB7F  907B CB8B 654B E33B F1ED
// Provided by the PHP University (www.phpu.cc) and PHPExperts.pro (www.phpexperts.pro)
//
// This file is dually licensed under the terms of the following licenses:
// * Primary License: OSSAL v1.0 - Open Source Software Alliance License
//   * Key points:
//       5.Redistributions of source code in any non-textual form (i.e.
//          binary or object form, etc.) must not be linked to software that is
//          released with a license that requires disclosure of source code
//          (ex: the GPL).
//       6.Redistributions of source code must be licensed under more than one
//          license and must not have the terms of the OSSAL removed.
//   * See LICENSE.ossal for complete details.
//
// * Secondary License: Creative Commons Attribution License v3.0
//   * Key Points:
//       * You are free:
//           * to copy, distribute, display, and perform the work
//           * to make non-commercial or commercial use of the work in its original form
//       * Under the following conditions:
//           * Attribution. You must give the original author credit. You must retain all
//             Copyright notices and you must include the sentence, "Based upon work from
//             PHPExperts.pro (www.phpexperts.pro).", wherever you list contributors.
//   * See LICENSE.cc_by for complete details.


include 'thrive/Autoloader.php';

class MemcacheMock
{
	public function get($key) { return null; }
	public function set($key, $data, $ttl='') {	}
}

$memcache = injectMemcache();
$loader = new Thrive_Autoloader(new fCache('memcache', $memcache));


if (!isset($argv[1]))
{
	echo "ERROR: Provide a TLD to search.\n";
	exit;
}

$tld = $argv[1];
if (isset($argv[2]) && $argv[2] == 'show')
{
	$domains = $memcache->get("availableDomains-$tld");
	print_r($domains);
	exit;
}

function createDomainNames($nameLength, $tld)
{
	$alphas = range('a', 'z');
	$numbers = range(0, 9);
	$digits = array_merge($alphas, $numbers, array('-'));

	$fills = array();
	for ($a = 0; $a < $nameLength; ++$a)
	{
		$fills[] = $digits;
	}

	$result = array(array()); // We need to start with one element already, because thats the identity element of the cartesian product
	foreach ($fills as $arr)
	{
		array_push($arr, null); // Add a null element to the array to get tuples with less than all arrays

		// This is the cartesian product:
		$new_result = array();
		foreach ($result as $old_element)
		{
			foreach ($arr as $el)
			{
				if ($el != '')
				{
					//$new_result[] = array_merge($old_element, array($el));
					$new_result[] = array_merge($old_element, array($el));
				}
			}
		}
		$result = $new_result;
	}

	$domains = array();
	foreach ($result as $r)
	{
		if ($r[0] == '-') { continue; }
		$domains[] = join('', $r) . ".$tld";
	}

	return $domains;
}

function injectMemcache($mode = '')
{
	static $memcache;

	if ($mode == 'test')
	{
		return new MemcacheMock;
	}

	if ($memcache === null)
	{
		$memcache = new Memcached;
		$memcache->addServer('localhost', 11211);
	}

	return $memcache;
}

// TEST API
$accessCreds = new Model_APIAccessCredentials;
$accessCreds->username = 'hopeseekr';
$accessCreds->apiKey = '33d1f7295601432794736256a342f416';
$accessCreds->ipAddress = '68.233.253.127';
$client = new DomainSearcher(new NameCheapAPI_Sandbox_DomainAvailablility($accessCreds, new Thrive_URL_Downloader, new MemcacheMock));
$memcache = injectMemcache('test');

/*
// Prod API
$accessCreds = new Model_APIAccessCredentials;
$accessCreds->username = 'hopeseekr';
$accessCreds->apiKey = 'c2eccaf232ee4f289f118cd043e07be4';
$accessCreds->ipAddress = '68.233.253.127';
$client = new NameCheapClient(new NameCheapAPI_DomainAvailablility($accessCreds));
*/


//header('Content-Type: text/plain');

$cacheKey = "domainList-$tld";

if (!($domainList = $memcache->get($cacheKey)))
{
	$domainList = createDomainNames(3, $tld);
	$memcache->set($cacheKey, $domainList);
}

//print_r($domainList); exit;
$size = count($domainList);
echo "Size: $size\n";

$startTime = time();
$processed = 0;

// Do 30 max per minute.
$numToSearch = 10;
for ($a = 0; $a < $size && $processed < 30 && time() - $startTime < 60; ++$a)
{
	// Search in bulk of 10 random domains at once.
	// - Randomize the domain list:
	shuffle($domainList);

	$domainsToSearch = array_splice($domainList, 0, $numToSearch);
	// - Still search alphabetically.
	sort($domainsToSearch);

	echo "Querying " . join(', ', $domainsToSearch) . ":\n";
	$queriedDomains = $client->checkDomainAvailability($domainsToSearch);
	$availableDomains = array_keys(array_filter($queriedDomains, function($v) { return ($v === true); }));

	// Update the cache.
	// - Realphabetize the domain list.
	sort($domainList);
	$memcache->set($cacheKey, $domainList);


	$timeToWait = 20 + $startTime;
	if ($timeToWait - time() == 0) { continue; }

	echo "Sleeping...";
	for ($b = ($timeToWait - time()); $b > 0; --$b)
	{
		echo $b . '...';
		sleep(1);
	}
	echo "\n";

	if (!empty($availableDomains))
	{
		echo "The following domains are available:\n\n";
		foreach ($availableDomains as $domain)
		{
			echo "  * $domain\n";
		}

		$prevFound = $memcache->get("availableDomains-$tld");
		if (!empty($prevFound))
		{
			$availableDomains = array_merge($prevFound, $availableDomains);
		}
		$memcache->set("availableDomains-$tld", $availableDomains);
		unset($prevFound);
		unset($availableDomains);
		break;
	}

	$processed += $numToSearch;
}






