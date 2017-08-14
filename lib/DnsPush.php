<?php

/**
 * lib/DnsPush.php.
 *
 * This class is used to push DNS changes to an active directory system using dnscmd.exe
 * 
 *
 * PHP version 5
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category  default
 *
 * @author    Andrew Jones
 * @copyright 2016 @authors
 * @license   http://www.gnu.org/copyleft/lesser.html The GNU LESSER GENERAL PUBLIC LICENSE, Version 3.0
 */
namespace ohtarr;

use Dotenv\Dotenv;
//use GuzzleHttp\Client;
use GuzzleHttp\Client as GuzzleHttpClient;

class DnsPush
{
	public $DNSRECORDS;		//array of switches from Network Management Platform
	public $NMRECORDS;
	public $psclient;		//array of switches from Network Management Platform
	public $nmclient;

    public function __construct()
	{
		$dotenv = new Dotenv(__DIR__."/../");
		$dotenv->load();
		$this->psclient = new GuzzleHttpClient([
			'base_uri' => getenv('API_URL'),
		]);
		$this->nmclient = new GuzzleHttpClient([
			'base_uri' => getenv('NM_API_URL'),
		]);
		
		$this->DNSRECORDS = $this->GetAllDnsRecords(getenv('DEFAULT_ZONE'),getenv('DNS_SERVER'));		//populate array of switches from Network Management Platform
		$this->NMRECORDS = $this->GetNMDeviceDns();		//populate array of switches from Network Management Platform
	}

	public static function print_env(){
		//(new Dotenv(__DIR__ . '/../'))->load();
		print getenv('API_URL') . "\n";
		print getenv('DNS_SERVER') . "\n";
		print getenv('DEFAULT_ZONE') . "\n";
	}

	public function GetAllDnsRecords($zone, $server, $name = null)
	{
		$postparams = [
			'action'	=>	'DnsGetRecords',
			'zone'		=>	$zone,
			'server'	=>	$server,
		];
		
		if($name)
		{
			$postparams['name'] = $name;
		}

		//Build a Guzzle POST request
		$apiRequest = $this->psclient->request('POST', "", [
				'form_params' => $postparams,
				'auth' => [
					getenv('API_USERNAME'),
					getenv('API_PASSWORD')
				],
		]);
		$response = $apiRequest->getBody()->getContents();
		$array = json_decode($response,true);
		return $array['psresponse']['data'];
	}
	
	public function GetNMDeviceDns()
	{
		$apiRequest = $this->nmclient->request('GET', "tools/dns-json.php");
		$response = $apiRequest->getBody()->getContents();
		
		$array = json_decode($response,true);

		return $array;
	}

	public function DnsRecordsToAdd()
	{
		foreach($this->NMRECORDS as $nmrecord)
		{
			$match = 0;
			foreach($this->DNSRECORDS as $dnsrecord)
			{
				if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']))
				{
					$match = 1;
					//print $dnsrecord['name'] . "\n";
					break;
				}
			}
			if($match == 0)
			{
				$array[] = $nmrecord;
			}
		}
	return $array;
	}
	
	public function DnsRecordsToRemove()
	{
		foreach($this->DNSRECORDS as $dnsrecord)
		{
			$match = 0;
			foreach($this->NMRECORDS as $nmrecord)
			{
				if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']))
				{
					$match = 1;
					//print $dnsrecord['name'] . "\n";
					break;
				}
			}
			if($match == 0)
			{
				$array[] = $dnsrecord;
			}
		}
	return $array;
	}

	public function DnsAddRecord($zone, $server, $name, $type, $value)
	{
		$postparams = [
			'action'	=>	'DnsAddRecord',
			'zone'		=>	$zone,
			'server'	=>	$server,
			'type'		=>	$type,
			'name'		=>	$name,
			'value'		=>	$value,
		];
		//Build a Guzzle POST request
		$apiRequest = $this->psclient->request('POST', "", [
				'form_params' => $postparams,
				'auth' => [
					getenv('API_USERNAME'),
					getenv('API_PASSWORD')
				],
		]);
		$response = $apiRequest->getBody()->getContents();
		$array = json_decode($response,true);
		return $array['success'];
	}

	public function DnsRemoveRecord($zone, $server, $name, $type)
	{
		$postparams = [
			'action'	=>	'DnsRemoveRecord',
			'zone'		=>	$zone,
			'server'	=>	$server,
			'type'		=>	$type,
			'name'		=>	$name,
		];
		//Build a Guzzle POST request
		$apiRequest = $this->psclient->request('POST', "", [
				'form_params' => $postparams,
				'auth' => [
					getenv('API_USERNAME'),
					getenv('API_PASSWORD')
				],
		]);
		$response = $apiRequest->getBody()->getContents();
		$array = json_decode($response,true);
		return $array['success'];
	}
	
	public function DnsAddRecords()
	{
		print "*************ADDING RECORDS*************\n";
		if($records = $this->DnsRecordsToAdd())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . " VALUE: " . $record['value'] . "......";
				if($this->DnsAddRecord(getenv(DEFAULT_ZONE),getenv(DNS_SERVER),$record['name'], $record['type'], $record['value']))
				{
					print "SUCCESS!\n";
				} else {
					print "FAILED!\n";
				}
			}
		print "COMPLETED!\n";
		} else {
			print "NO DNS RECORDS TO ADD!\n";
		}
	}
	
	public function DnsRemoveRecords()
	{
		print "*************REMOVING RECORDS*************\n";
		if($records = $this->DnsRecordsToRemove())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . "......";
				if($this->DnsRemoveRecord(getenv(DEFAULT_ZONE),getenv(DNS_SERVER),$record['name'], $record['type']))
				{
					print "SUCCESS!\n";
				} else {
					print "FAILED!\n";
				}
			}
		print "COMPLETED!\n";
		} else {
			print "NO DNS RECORDS TO REMOVE!\n";
		}
	}
}
