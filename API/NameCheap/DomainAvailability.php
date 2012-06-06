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

class NameCheapAPI_DomainAvailablility extends API_DownloadingObject implements Interface_DomainAvailability
{
	const API_CHECKDOMAIN_FORMAT = 'https://api.namecheap.com/xml.response?ApiUser={username}&ApiKey={apiKey}&UserName={username}&clientIp={ipAddress}&Command=namecheap.domains.check&DomainList={domainList}';

	/**
	 * @param $domainList mixed Can either be a single string or an array of strings.
	 * @return array list of available domains, if any.
	 */
	public function query($domainList)
	{
		$domainList = (is_array($domainList)) ? join(',', $domainList) : $domainList;
		$cacheKey = 'domainQuery-' . md5($domainList);
		//apc_delete($apcKey);

		if (!($data = $this->memcache->get($cacheKey)))
		{
			$params = array('{username}'   => $this->accessCreds->username,
							'{apiKey}'     => $this->accessCreds->apiKey,
							'{ipAddress}'  => $this->accessCreds->ipAddress,
							'{domainList}' => $domainList
			);

			$apiURL = $this->transformURL(static::API_CHECKDOMAIN_FORMAT, $params);
			$urlContent = $this->downloader->fetch($apiURL);
			$data = $urlContent->content;
			$this->memcache->set($cacheKey, $data, 7200);
		}

		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($data);
		$xpath = new DOMXPath($doc);

		// God! I fucking hate how obviously broken XPath 1.1 XML Namespacing support is! -Ted 2012-06-03
		$xpath->registerNamespace('x', $doc->lookupNamespaceUri($doc->namespaceURI));
		$nodes = $xpath->query('//x:DomainCheckResult');

		//header('Content-Type: text/plain');
		//print_r($data);

		$domains = array();
		foreach ($nodes as $node)
		{
			/** @var DOMElement $node **/
			//echo "Domain: " . $node->getAttribute('Domain') . "\tAvailable? " . ($node->getAttribute('Available') == 'true' ? 'Yes' : 'No') . "\n";
			$name = $node->getAttribute('Domain');
			$domains[$name] = ($node->getAttribute('Available') == 'true' ? true : false);
		}

		return $domains;
	}
}
