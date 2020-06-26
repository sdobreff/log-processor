<?php

try {
	set_time_limit(1);

	if (file_exists('log-processor.log.err')) unlink('log-processor.log.err');
	if (file_exists('log-processor.json')) unlink('log-processor.json');

	$fileUrls = [];
	$lineNum = 0;

    if (is_file($argv[1]) === true) {
		$file_handle = fopen($argv[1], "r");
		while (!feof($file_handle)) {
		   $line = fgets($file_handle);
		   if (trim($line) === '') continue;
		   $lineNum ++;
		   if (strpos($line,"#") === 0) {
		   		continue;
		   }

		   $pattern = '/^([^ ]+) ([^ ]+) [^ ]+ [^ ]+ [^ ]+ [^ ]+ [^ ]+ ([^ ]+) ([^ ]+) [^ ]+ ("?[^ ]+\"?)/';

		   if (preg_match($pattern,$line,$matches)) {
		   		list($line, $date, $time, $CacheStatus, $Bytes, $URL) = $matches;

		   		$hst = parse_url($URL)['host'];

		   		$fileUrls[$hst] = $hst;

		   		file_put_contents($hst, json_encode(['date' => $date, 'time' => $time, 'status' => $CacheStatus, 'bytes' => $Bytes, 'host' => $hst, 'line' => $lineNum])."\n", FILE_APPEND);
		   } else {
		   	file_put_contents('log-processor.log.err', 'Invalid parameters in "'.$line.'" on line '.$lineNum."\n", FILE_APPEND);
		   		continue;
		   }

		}
		fclose($file_handle);

	} else {
		print 'File not found'."\n";
		die();
	}

	$aggrData = [];

	foreach ($fileUrls as &$value) {
		$file_handle = fopen($value, "r");

		while (!feof($file_handle)) {
		   $line = fgets($file_handle);

		   if (trim($line) === '') continue;

		   $line = json_decode($line, true);

		   $status = explode('/', $line['status']);

		   $hitMiss = strtolower(explode('_', $status[0])[1]);

		   if ($hitMiss != 'hit' && $hitMiss != 'miss') {
		   	file_put_contents('log-processor.log.err', 'Invalid cache status in '.$line['status'].' on line '.$line['line']."\n", FILE_APPEND);
		   		continue;
		   }

		   $time = explode(':', $line['time']);
		   $mins = (floor(($time[1]/10))*10);
		   if ($mins == 0) $mins = '00';
		   $time = $time[0].':'.$mins;

		   if (isset($aggrData[$line['host']][$line['date']][$time][$status[1]][$hitMiss]['bytes'])) {
			   $aggrData[$line['host']][$line['date']][$time][$status[1]][$hitMiss]['bytes'] += $line['bytes'];
			   $aggrData[$line['host']][$line['date']][$time][$status[1]][$hitMiss]['requests'] ++;
			} else {
				$aggrData[$line['host']][$line['date']][$time][$status[1]][$hitMiss]['bytes'] = $line['bytes'];
				$aggrData[$line['host']][$line['date']][$time][$status[1]][$hitMiss]['requests'] = 1;
			}
		}
		fclose($file_handle);

		unlink($value);
	}
	unset($value);

	file_put_contents('log-processor.json', json_encode($aggrData));
} catch (Exception $e) {
	print $e->message();
}
?>