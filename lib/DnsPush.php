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

use GuzzleHttp\Client as GuzzleHttpClient;

class DnsPush
{
	public $DNSRECORDS;		//array of switches from Network Management Platform
	public $NMRECORDS;
	public $psclient;		//array of switches from Network Management Platform
	public $nmclient;
	public $apiurl;
	private $apiusername;
	private $apipassword;
	public $dnsrecordsurl;
	public $dnsserver;
	public $ZONE;
	
    public function __construct($apiurl, $apiusername, $apipassword, $dnsrecordsurl, $dnsserver, $zone)
	{
		$this->apiurl = $apiurl;
		$this->apiusername = $apiusername;
		$this->apipassword = $apipassword;
		$this->dnsrecordsurl = $dnsrecordsurl;
		$this->dnsserver = $dnsserver;
		$this->ZONE = $zone;

		$this->psclient = new GuzzleHttpClient([
			'base_uri' => $this->apiurl,
			'verify'	=>	false,
		]);
		$this->nmclient = new GuzzleHttpClient([
			'base_uri' => $this->dnsrecordsurl,
			'verify'	=>	false,
		]);
	}

	public function GetAllDnsRecords($zone, $name = null)
	{
		if(!$this->DNSRECORDS[$zone])
		{
			$postparams = [
				'action'	=>	'DnsGetRecords',
				'zone'		=>	$this->ZONE,
				'server'	=>	$this->dnsserver,
			];
			
			if($name)
			{
				$postparams['name'] = $name;
			}

			//Build a Guzzle POST request
			$apiRequest = $this->psclient->request('POST', "", [
					'form_params' => $postparams,
					'auth' => [
						$this->apiusername,
						$this->apipassword
					],
			]);
			$response = $apiRequest->getBody()->getContents();
			$array = json_decode($response,true);
			$this->DNSRECORDS[$zone] = $array['psresponse']['data'];
		}
		return $this->DNSRECORDS[$zone];
	}
	
	public function GetCustomDnsRecords($zone, $filter)
	{
		foreach($this->GetAllDnsRecords($zone) as $record)
		{
			if(preg_match("/".$filter."/",$record['value']))
			{
				$customrecords[] = $record;
			}
		}
		return $customrecords;
	}
	
	public function GetNMDeviceDns()
	{
		if(!$this->NMRECORDS)
		{
			$apiRequest = $this->nmclient->request('GET');
			$response = $apiRequest->getBody()->getContents();
			
			$array = json_decode($response,true);
			$this->NMRECORDS = $array;
		}
		return $this->NMRECORDS;
	}

	public function ForwardDnsRecordsToAdd()
	{
		foreach($this->GetNMDeviceDns() as $nmrecord)
		{
			if($nmrecord['zone'] == $this->ZONE)
			{
				$match = 0;
				//print_r($nmrecord);
				foreach($this->GetAllDnsRecords($this->ZONE) as $dnsrecord)
				{
					//print_r($dnsrecord);
					if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']) && strtolower($dnsrecord['type']) == strtolower($nmrecord['type']) && strtolower($dnsrecord['zone']) == strtolower($nmrecord['zone']) && strtolower($dnsrecord['value']) == strtolower($nmrecord['value']))
					{
						$match = 1;
						//print $dnsrecord['name'] . "\n";
						break;
					}
				}
				if($match == 0)
				{
					//print $nmrecord['name'] . "\n";
					//print_r($nmrecord);
					$array[] = $nmrecord;
				}
			}
		}
		return $array;
	}
	
	public function ForwardDnsRecordsToRemove()
	{
		foreach($this->GetAllDnsRecords($this->ZONE) as $dnsrecord)
		{
			$match = 0;
			foreach($this->GetNMDeviceDns() as $nmrecord)
			{
				if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']) && strtolower($dnsrecord['type']) == strtolower($nmrecord['type']) && strtolower($dnsrecord['zone']) == strtolower($nmrecord['zone']) && strtolower($dnsrecord['value']) == strtolower($nmrecord['value']))
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
	
	public function ReverseDnsRecordsToAdd()
	{
		$rzone = '10.in-addr.arpa';
		$reverserecords = $this->GetCustomDnsRecords($rzone,$this->ZONE);
		foreach($this->GetNMDeviceDns() as $nmrecord)
		{
			//print ".";
			$match = 0;
			//print_r($nmrecord);
			if($nmrecord['zone'] == $rzone)
			{
				foreach($reverserecords as $dnsrecord)
				{
					//print_r($dnsrecord);
					if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']) && strtolower($dnsrecord['type']) == strtolower($nmrecord['type']) && strtolower($dnsrecord['zone']) == strtolower($nmrecord['zone']) && strtolower($dnsrecord['value']) == strtolower($nmrecord['value']))
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
		}
		return $array;
	}
	
	public function ReverseDnsRecordsToRemove()
	{
		$rzone = '10.in-addr.arpa';
		foreach($this->GetCustomDnsRecords($rzone,$this->ZONE) as $dnsrecord)
		{
			//print ".";
			$match = 0;
			foreach($this->GetNMDeviceDns() as $nmrecord)
			{
				if(strtolower($dnsrecord['name']) == strtolower($nmrecord['name']) && strtolower($dnsrecord['type']) == strtolower($nmrecord['type']) && strtolower($dnsrecord['zone']) == strtolower($nmrecord['zone']) && strtolower($dnsrecord['value']) == strtolower($nmrecord['value']))
				{
					$match = 1;
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
					$this->apiusername,
					$this->apipassword
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
					$this->apiusername,
					$this->apipassword
				],
		]);
		$response = $apiRequest->getBody()->getContents();
		$array = json_decode($response,true);
		return $array['success'];
	}
	
	public function DnsAddForwardRecords()
	{
		print "*************ADDING FORWARD RECORDS*************\n";
		if($records = $this->ForwardDnsRecordsToAdd())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . " VALUE: " . $record['value'] . "......";
				if($this->DnsAddRecord($this->zone,$dnsserver,$record['name'], $record['type'], $record['value']))
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
	
	public function DnsRemoveForwardRecords()
	{
		print "*************REMOVING FORWARD RECORDS*************\n";
		if($records = $this->ForwardDnsRecordsToRemove())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . "......";
				if($this->DnsRemoveRecord($this->ZONE,$dnsserver,$record['name'], $record['type']))
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
	
	public function DnsAddReverseRecords()
	{
		print "*************ADDING REVERSE RECORDS*************\n";
		if($records = $this->ReverseDnsRecordsToAdd())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . " VALUE: " . $record['value'] . "......";
				if($this->DnsAddRecord($record['zone'],$this->dnsserver,$record['name'], $record['type'], $record['value']))
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
	
	public function DnsRemoveReverseRecords()
	{
		print "*************REMOVING REVERSE RECORDS*************\n";
		if($records = $this->ReverseDnsRecordsToRemove())
		{
			foreach($records as $record)
			{
				print "NAME: " . $record['name'] . " TYPE: " . $record['type'] . " VALUE " . $record['value'] . " ......";
				if($this->DnsRemoveRecord($record['zone'],$this->dnsserver,$record['name'], $record['type']))
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
