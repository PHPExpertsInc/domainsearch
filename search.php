#!/bin/env php
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
	echo "ERROR: Please provide at least one domain name to search.\n";
	exit;
}

$domainList = array();
for ($a = 1; $a < $argc; ++$a)
{
	$domainList[] = $argv[$a];
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
/*
$accessCreds = new Model_APIAccessCredentials;
$accessCreds->username = 'hopeseekr';
$accessCreds->apiKey = '33d1f7295601432794736256a342f416';
$accessCreds->ipAddress = '68.233.253.127';
$client = new DomainSearcher(new NameCheapAPI_Sandbox_DomainAvailablility($accessCreds, new Thrive_URL_Downloader, new MemcacheMock));
$memcache = injectMemcache('test');
*/

// Prod API
$accessCreds = new Model_APIAccessCredentials;
$accessCreds->username = 'hopeseekr';
$accessCreds->apiKey = 'c2eccaf232ee4f289f118cd043e07be4';
$accessCreds->ipAddress = '68.233.253.127';
$client = new DomainSearcher(new NameCheapAPI_DomainAvailablility($accessCreds));


//header('Content-Type: text/plain');

//print_r($domainList); exit;
$size = count($domainList);

$startTime = time();
$processed = 0;

// Do 30 max per minute.
$numToSearch = 10;
for ($a = 0; $a < $size && $processed < 30 && time() - $startTime < 60; $a += $numToSearch)
{
	$domainsToSearch = array_splice($domainList, 0, $numToSearch);
	// - Still search alphabetically.
	sort($domainsToSearch);

	echo "Querying " . join(', ', $domainsToSearch) . ":\n";
	$queriedDomains = $client->checkDomainAvailability($domainsToSearch);

	// Update the cache.
	// - Realphabetize the domain list.
	sort($domainList);

	$timeToWait = 20 + $startTime;
	if ($timeToWait - time() == 0) { continue; }

	echo "Status:\n\n";
	$count = 1;
	foreach ($queriedDomains as $domain => $status)
	{
		$status = ($status === true) ? 'Available' : 'Not available';
		echo "  {$count}. $domain: $status\n";

		++$count;
	}

	$processed += $numToSearch;
}






