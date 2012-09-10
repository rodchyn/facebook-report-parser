<?php

namespace Rodchyn\Facebook\Report;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Guzzle\Service\Client;

class Parser 
{
	private $container;
	
	private $companyId;
	
	private $companySecret;
	
	public function __construct(ContainerInterface $container, $companyId, $companySecret)
	{
		$this->container = $container;
		$this->companyId = $companyId;
		$this->companySecret = $companySecret;
	}
	
	public function getDigest($date)
	{
		$client = new Client('https://graph.facebook.com/oauth/access_token?client_id={company_id}&client_secret={company_secret}&grant_type=client_credentials', array(
				'company_id' => $this->companyId,
				'company_secret' => $this->companySecret
		));
		 
		 
		$response = $client->get()->send();
		if(200 !== $response->getStatusCode()) {
			throw new Exception("Can't get access token using passed company id and secret");
		}
		 
		$token = $response->getBody();
		parse_str($token);
		
		$dailyDigestUrl = "https://paymentreports.facebook.com/{company_id}/report?date={date}&type=digest&access_token={access_token}";
		
		$client = new Client($dailyDigestUrl, array(
				'company_id' => $this->companyId,
				'access_token' => $access_token,
				'date' => $date
		));
		 
		$response = $client->get()->send();
		if(200 !== $response->getStatusCode()) {
			throw new \Exception("Can't download daily detail report");
		}
		 
		$cacheDir = $this->container->get('kernel')->getRootDir() . '/cache';
		$extractDir = $cacheDir . '/tmp-facebook-report';
		@mkdir($extractDir, 0755, true);
		$zipFileName = tempnam($extractDir, "fb_detail_report").".zip";
		 
		 
		file_put_contents($zipFileName,  $response->getBody());
		 
		$zip = new \ZipArchive;
		if ($zip->open($zipFileName) === TRUE) {
			$zip->extractTo($extractDir);
			$zip->close();
			@unlink($zipFileName);
			
			
			$finder = new Finder();
			$finder->files()->name($this->companyId . '_digest_*.csv')->date('since 1 minute ago')->in($extractDir);
			
			foreach($finder as $file) {
				$stop = true;
				$header = array();
				$rows = array();
				foreach (file($extractDir . '/' . $file->getFilename()) as $line) {
					if(preg_match('/^SH.*credits_detail$/', $line)) { $stop = false; continue; }
					if(preg_match('/^SF/', $line)) { $stop = true; continue; }
					
					if(preg_match('/^CH/', $line)) {
						$line = preg_replace('/^CH,/', '', $line);
						$header = preg_split('/\s*,\s*/', $line);
					}
					
					if(!$stop) {
						$line = preg_replace('/^CD,/', '', $line);
						$columns = preg_split('/,/', $line);
						$row = array_combine($header, $columns);
						$rows[] = $row;
					}
				}
				
				var_dump($rows);
			}
			
		} else {
			throw new Exception("Can't unzip facebook report archive");
		}
	}
}
