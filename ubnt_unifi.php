#!/usr/bin/php
<?php

// Munin monitoring plugin for Ubiquiti Unifi AP system.

//$controller = "unifi.company.com";
//$hosts = "ap01.wireless.company.lan ap02.wireless.company.lan ap03.wireless.company.lan ap04.wireless.company.lan";


$controller = getenv('controller');
$hosts = getenv('devices');
$timeout = getenv('timeout');
$retry = getenv('retry');
//$devnetw = getenv('devnetw');
$replace_chars = array("\"","\$","@","^","`",",","|","%",";",".","~","(",")","/","\\","{","}",":","?","[","]","=","+","#","!","-",);	// Special chars replace

function print_header($inp){ // prints Munin-config data from processed data array

	$cf  = "multigraph unifi_".$inp['g_multi']."\n";
        $cf .= "host_name ".$inp['g_controller']."\n";
        $cf .= "graph_title ".$inp['g_title']."\n";
        $cf .= "graph_args --base 1000 \n";
        $cf .= "graph_vlabel ".$inp['g_vlabel']."\n";
        $cf .= "graph_category ".$inp['g_category']."\n";
        $cf .= "graph_info ".$inp['g_info']."\n";
	if(isset($inp['g_order'])){ $cf .= "graph_order ".$inp['g_order']."\n"; }

	foreach($inp['head'] as $key => $val){
		$cf .= "unifi_".$val['name'].".label ".$val['label']."\n";
		$cf .= "unifi_".$val['name'].".draw  ".$val['draw']."\n";
		$cf .= "unifi_".$val['name'].".info  ".$val['info']."\n";
		if(isset($val['type'])){ $cf .= "unifi_".$val['name'].".type  ".$val['type']."\n"; }
		if(isset($val['min'])) { $cf .= "unifi_".$val['name'].".min  ".$val['min']."\n";  }
		if(isset($val['cdef'])){ $cf .= "unifi_".$val['name'].".cdef  ".$val['cdef']."\n"; }
		if(isset($val['graph'])){ $cf .= "unifi_".$val['name'].".graph  ".$val['graph']."\n"; }
		if(isset($val['max'])){ $cf .= "unifi_".$val['name'].".max  ".$val['max']."\n"; }
		if(isset($val['negative'])){ $cf .= "unifi_".$val['name'].".negative  ".$val['negative']."\n"; }
	}
	$cf .= "\n";
	echo iconv("UTF-8", "ISO-8859-2", $cf), PHP_EOL;
}


function print_data($inp) {
	
	$pf  = "multigraph unifi_".$inp['g_multi']."\n";
	foreach($inp['data'] as $key => $val){
		$pf .= "unifi_".$val['name'].".value ".$val['value']."\n" ;
	}
	$pf .= "\n";
	
	echo iconv("UTF-8", "ISO-8859-2", $pf), PHP_EOL;

}

function count_wl_networks($inp){ //Count wireless networks from interfade data
	$num = -10;
        foreach($inp as $key => $val){          // jump to the end of wl interface list
		if(strpos($key, "iso.3.6.1.4.1.41112.1.6.1.2.1.1.") !== false){
                	if(is_numeric(explode(": ", $val)[1]) and $num < explode(": ", $val)[1]){
                        	$num = explode(": ", $val)[1];
                        }
                }
	}
        $num = $num+1;	//Because the snmp counts from 0
	
	if($num > 0){
		return $num;
	} else {
		return -1;
	}
}


function collect_radio_summary($inp,$host){
	global $controller, $replace_chars;
	$ret = array();
	if(isset($host) and $host !== null and $host != "" ){
                $ret['g_multi'] = "radio_".str_replace( array(".", ":"), "_" ,$controller).".".str_replace( array(".", ":"), "_" ,$host);
		$ret['g_controller'] = $controller;
		$location = str_replace("\"", "", explode(": ", $inp[$host]["iso.3.6.1.2.1.1.6.0"])[1]);
               	if( $location != "Unknown" and $location != "" ){ $ret['g_title'] = "Unifi Clients on: ".$location ; } // if the Location is not filled in Controller settings, use the hostname or ip address
		else { $ret['g_title'] = "Unifi Clients on: ".$host; };
                $ret['g_vlabel'] = "Users";
                $ret['g_category'] = "wl_clients_ap";
                $ret['g_info'] = "ubnt_wireless";
	} else {
		$ret['g_multi'] = "radio_".str_replace( array(".", ":"), "_" ,$controller);
                $ret['g_controller'] = $controller;
		$ret['g_title'] = "Unifi Clients on: $controller (total)";
		$ret['g_vlabel'] = "Users";
		$ret['g_category'] = "Wl_clients_all";
		$ret['g_info'] = "ubnt_wireless";
	}
				
                $ret['head'][0]['name'] = "sum_clients";
                $ret['head'][0]['label'] = "Total clients";
                $ret['head'][0]['draw'] = "LINE1.2";
                $ret['head'][0]['info'] = "Total Clients";
		$ret['head'][0]['type'] = "GAUGE";
		$ret['head'][0]['min']	= "0";

		$ret['head'][1]['name'] = "2g_clients";
		$ret['head'][1]['label'] = "2.4Ghz";
		$ret['head'][1]['draw'] = "LINE1.2";
		$ret['head'][1]['info'] = "2.4Ghz Clients";
                $ret['head'][1]['type'] = "GAUGE";
                $ret['head'][1]['min']  = "0";

		$ret['head'][2]['name'] = "5g_clients";
                $ret['head'][2]['label'] = "5Ghz";
                $ret['head'][2]['draw'] = "LINE1.2";
                $ret['head'][2]['info'] = "2.4Ghz Clients";
                $ret['head'][2]['type'] = "GAUGE";
                $ret['head'][2]['min']  = "0";


	if(isset($host) and $host !== null and $host != "" ){	// trim raw data array to current device (in $host) or use the whole array when calculating controller's data 
		$temp = $inp;
		unset($inp);
		$inp = array($host => $temp[$host]);
		unset($temp);
	}

	foreach($inp as $key => $val){

		for($i=1; $i<=count_wl_networks($inp[$key]); $i++){	//Collect clients by band.
			if( explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.4.".$i])[1] < 15 ){					//2.4Ghz client
				$ret['data'][1]['name'] = "2g_clients";
				@$ret['data'][1]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];
			} else {													// 5Ghz clients
				$ret['data'][2]['name'] = "5g_clients";
                                @$ret['data'][2]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];
			}
		}
                $ret['data'][0]['name'] = "sum_clients";
                $ret['data'][0]['value'] = $ret['data'][1]['value'] + $ret['data'][2]['value'];



                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect clients by SSID.

                        foreach($ret['data'] as $key2 => $val2){	//find if ssid is already used
                                if($ret['data'][$key2]['name'] == str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                        break;
                                }
                        }
					//found, update record
                        if($ret['data'][$key2]['name'] == str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                $ret['data'][$key2]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];

                        } else {	//not found, new record
                                $ret['data'][] = array( "name" => str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "value" => explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1] 
                                                );
                                $ret['head'][] = array( "name" => str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "label" => str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] ),
                                                        "draw"  => "LINE1.2",
                                                        "info"  => explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
							"type"	=> "GAUGE",
							"min"	=> "0",
                                                );

                        }
                }
        }


return $ret;
}




function collect_netw_summary($inp,$host){	//network information
        global $controller, $replace_chars;
        $ret = array();
	
	if(isset($host) and $host !== null and $host != "" ){ // When showing the Ap's summary.
              $temp = $inp;
              unset($inp);
              $inp = array($host => $temp[$host]);
              unset($temp);
              $multiplier = 8;
              $divider = 1;
      	} else {			//because the normal INTEGER(32) would be overflowed
              $multiplier = 8192;	// When showing the controller's summary
              $divider = 1024;
      	}


        if(isset($host) and $host !== null and $host != "" ){
                $ret['g_multi'] = "netw_".str_replace( array(".", ":"), "_" ,$controller).".".str_replace( array(".", ":"), "_" ,$host);
                $ret['g_controller'] = $controller;
                $location = str_replace("\"", "", explode(": ", $inp[$host]["iso.3.6.1.2.1.1.6.0"])[1]);
		if($location != "Unknown" and $location != ""){ $ret['g_title'] = "Network Usage on: ".$location; }
                else{$ret['g_title'] = "Network Usage on: ".$host;}
                $ret['g_vlabel'] = "bits in(-) / out(+) per second";
                $ret['g_category'] = "Wl_netw_ap";
                $ret['g_info'] = "ubnt_network";
		$ret['g_order'] = "rx_all tx_all rx_2g tx_2g rx_5g tx_5g";

        } else {
                $ret['g_multi'] = "netw_".str_replace( array(".", ":"), "_" ,$controller);
                $ret['g_controller'] = $controller;
                $ret['g_title'] = "Netwok Usage on: $controller (total)";
                $ret['g_vlabel'] = "bits in(-) / out(+) per second";
                $ret['g_category'] = "Wl_netw_all";
                $ret['g_info'] = "ubnt_network";
		$ret['g_order'] = "rx_all tx_all rx_2g tx_2g rx_5g tx_5g";
        }
         
                $ret['head'][0]['name'] = "rx_all";
                $ret['head'][0]['label'] = "RxTotal (bps)";
                $ret['head'][0]['draw'] = "LINE1.2";
                $ret['head'][0]['info'] = "Total Received";
                $ret['head'][0]['type'] = "DERIVE";
                $ret['head'][0]['min']  = "0";
		$ret['head'][0]['graph']  = "no";
		$ret['head'][0]['cdef']  = "unifi_rx_all,$multiplier,*";
		$ret['head'][0]['max']  = "1000000000";

                $ret['head'][1]['name'] = "tx_all";
                $ret['head'][1]['label'] = "Total (bps)";
                $ret['head'][1]['draw'] = "LINE1.2";
                $ret['head'][1]['info'] = "Total Sent";
                $ret['head'][1]['type'] = "DERIVE";
                $ret['head'][1]['min']  = "0";
                $ret['head'][1]['cdef']  = "unifi_tx_all,$multiplier,*";
                $ret['head'][1]['max']  = "1000000000";
		$ret['head'][1]['negative']  = "unifi_rx_all";

                $ret['head'][2]['name'] = "rx_2g";
                $ret['head'][2]['label'] = "2G (bps)";
                $ret['head'][2]['draw'] = "LINE1.2";
                $ret['head'][2]['info'] = "Total Received";
                $ret['head'][2]['type'] = "DERIVE";
                $ret['head'][2]['min']  = "0";
                $ret['head'][2]['graph']  = "no";
                $ret['head'][2]['cdef']  = "unifi_rx_2g,$multiplier,*";
                $ret['head'][2]['max']  = "1000000000";

                $ret['head'][3]['name'] = "tx_2g";
                $ret['head'][3]['label'] = "2G (bps)";
                $ret['head'][3]['draw'] = "LINE1.2";
                $ret['head'][3]['info'] = "Total Sent";
                $ret['head'][3]['type'] = "DERIVE";
                $ret['head'][3]['min']  = "0";
                $ret['head'][3]['cdef']  = "unifi_tx_2g,$multiplier,*";
                $ret['head'][3]['max']  = "1000000000";
		$ret['head'][3]['negative']  = "unifi_rx_2g";

                $ret['head'][4]['name'] = "rx_5g";
                $ret['head'][4]['label'] = "5G (bps)";
                $ret['head'][4]['draw'] = "LINE1.2";
                $ret['head'][4]['info'] = "Total Received";
                $ret['head'][4]['type'] = "DERIVE";
                $ret['head'][4]['min']  = "0";
		$ret['head'][4]['graph']  = "no";
                $ret['head'][4]['cdef']  = "unifi_rx_5g,$multiplier,*";
                $ret['head'][4]['max']  = "1000000000";

                $ret['head'][5]['name'] = "tx_5g";
                $ret['head'][5]['label'] = "5G (bps)";
                $ret['head'][5]['draw'] = "LINE1.2";
                $ret['head'][5]['info'] = "Total Sent";
                $ret['head'][5]['type'] = "DERIVE";
                $ret['head'][5]['min']  = "0";
                $ret['head'][5]['cdef']  = "unifi_tx_5g,$multiplier,*";
                $ret['head'][5]['max']  = "1000000000";
		$ret['head'][5]['negative']  = "unifi_rx_5g";


        foreach($inp as $key => $val){

                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect netw_bytes by band and direction.
                        if( explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.4.".$i])[1] < 15 ){                                 //2.4Ghz client
                                $ret['data'][2]['name'] = "rx_2g";		       
                                @$ret['data'][2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);
				$ret['data'][3]['name'] = "tx_2g";
                                @$ret['data'][3]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);

                        } else {                                                                                                        // 5Ghz clients
                                $ret['data'][4]['name'] = "rx_5g";                     
                                @$ret['data'][4]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);
                                $ret['data'][5]['name'] = "tx_5g";
                                @$ret['data'][5]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);
                        }
                }
                $ret['data'][0]['name'] = "rx_all";
                $ret['data'][0]['value'] = $ret['data'][2]['value'] + $ret['data'][4]['value'];
                $ret['data'][1]['name'] = "tx_all";
                $ret['data'][1]['value'] = $ret['data'][3]['value'] + $ret['data'][5]['value'];



                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect netw_bytes by SSID.

                        foreach($ret['data'] as $key2 => $val2){        //find if ssid is already used
                                if($ret['data'][$key2]['name'] == "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                        break;
                                }
                        }
                                        //ssid found, update record
                        if($ret['data'][$key2]['name'] == "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                $ret['data'][$key2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);

                        } else {       //ssid not found, new record
                                $ret['data'][] = array( "name" => "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "value" => round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider) ,
                                                );
                                $ret['head'][] = array( "name"  => "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "label" => "RX_".str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] )."(bps)",
                                                        "draw"  => "LINE1.2",
                                                        "info"  => "Rx_".explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
                                                        "type"  => "DERIVE",
                                                        "min"   => "0",
							"graph"	=> "no",
							"cdef"	=> "unifi_rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]).",$multiplier,*",
							"max"	=> "1000000000",
					);

                        }

			foreach($ret['data'] as $key2 => $val2){        //find if ssid is already used
                    		if($ret['data'][$key2]['name'] == "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                            		break;
                    		}
            		}
                     		       //ssid found, update record
            		if($ret['data'][$key2]['name'] == "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                    		$ret['data'][$key2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);

			} else {       //ssid not found, new record
                    		$ret['data'][] = array( "name" => "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                			"value" => round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider) ,
						);
                    		$ret['head'][] = array( "name"  => "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                        				"label" => "".str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] )." (bps)",
                                        	    	"draw"  => "LINE1.2",
                                            		"info"  => "Tx_".explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
                                	            	"type"  => "DERIVE",
                                        	    	"min"   => "0",
                                            		"cdef"  => "unifi_tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]).",$multiplier,*",
                                            		"max"   => "1000000000",
							"negative" => "unifi_rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                            			);
            		}


                }
        }


return $ret;
}


$hosts = explode(" ", $hosts);
$netw = @explode('/', $devnetw)[0];
$mask = @explode('/', $devnetw)[1];
$hosts2 = array();

if($mask != 0 ){
	for($i=1; $i<(1 << (32 - $mask)); $i++ ){		// fetch ip addresses from given network and mask
		$hosts2[] = long2ip((ip2long($netw) & ~((1 << (32 - $mask)) -1))  +$i  ) ;
	}
}


foreach($hosts as $key => $val){			// delete addresses which are given by hostname
	if(in_array(gethostbyname($val), $hosts2)){
		unset($hosts2[ array_keys($hosts2, gethostbyname($val))[0] ]);
	}

}
$hosts = array_merge($hosts, $hosts2);

$raw=array();
foreach($hosts as $key => $val){			// get raw snmp data from unifi devices
	if($hosts[$key] == ""){
		unset($hosts[$key]);
	}
	if($val != "") {
		$raw[$val] = @snmp2_real_walk($val, "public", ".1.3.6.1.4.1.41112.1.6.1.2.1", $timeout*1000, $retry ); 		// wl network info
		$raw[$val]["iso.3.6.1.2.1.1.6.0"] = @snmp2_get($val, "public", ".1.3.6.1.2.1.1.6.0", $timeout*1000, $retry ) ;	// location info
		$raw[$val]["iso.3.6.1.2.1.1.1.0"] = @snmp2_get($val, "public", ".1.3.6.1.2.1.1.1.0", $timeout*1000, $retry ) ;	// descr. info
	}
        if( !isset($raw[$val]["iso.3.6.1.4.1.41112.1.6.1.2.1.1.1"]) ){
                unset($raw[$val]);
		unset($hosts[$key]);
        }
}


//print_r($raw);
//print_r($hosts);
//$valami = collect_netw_summary($raw,"ap12.wireless.lan");
//print_r($valami);


if (isset($argv[1]) and $argv[1] == "config"){			// munin config
	print_header(collect_radio_summary($raw,null));
	foreach($hosts as $key => $val){
		print_header(collect_radio_summary($raw,$val));
	}
        print_header(collect_netw_summary($raw,null));
        foreach($hosts as $key => $val){
                print_header(collect_netw_summary($raw,$val));
        }


} else {							// munin data
	print_data(collect_radio_summary($raw,null));
        foreach($hosts as $key => $val){
                print_data(collect_radio_summary($raw,$val));
        }
        print_data(collect_netw_summary($raw,null));
        foreach($hosts as $key => $val){
                print_data(collect_netw_summary($raw,$val));
        }

}


echo "\n";

?>
