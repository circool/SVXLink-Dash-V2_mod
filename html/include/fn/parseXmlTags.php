<?php

/** 
 * @author vladimir@tsurkanenko.ru
 * @filesource /include/fn/parseXmlTags.php
 * @date 2025.11.26
 * @version 0.1.5.release
 * 
 */
function parseXmlTags(string $_xml_data) : array
{
	
	$result = [];

	
	$start_pos = strpos($_xml_data, '<');
	if ($start_pos !== false) {
		
		$_xml_data = substr($_xml_data, $start_pos);
	}


	$pattern = '/<([A-Za-z0-9_]+)>(.*?)<\/\1>/s';

	if (preg_match_all($pattern, $_xml_data, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$tag_name = $match[1];
			$payload_content = $match[2];
			$result[$tag_name] = $payload_content;
		}
	}
	
	return $result;
}
