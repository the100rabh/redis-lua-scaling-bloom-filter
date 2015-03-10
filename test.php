<?php
$redis = new Redis();
$redis->pconnect("127.0.0.1", 6379, 600);

$script = file_get_contents("add.lua");

$check_script = file_get_contents("check.lua");
$add_sha1 = $redis->script('load', $script);
$check_sha1 = $redis->script('load', $check_script);

//echo $redis->script('load',$check_script);
$time = microtime(true);
$range = 30000000;
for( $i = 0; $i < $range ; $i+=2){
$val = $redis->evalSha($add_sha1,array("test",3000000,0.01,$i.'a'));
$val = $redis->evalSha($add_sha1,array("test",3000000,0.01,$i.'b'));
$val = $redis->evalSha($add_sha1,array("test",3000000,0.01,$i.'c'));
//echo "Add";
//var_dump($val);
}

echo "\n\nAdd Time = ".(((microtime(true) - $time)*2000/ ($range *3) )) . "\n\n"; 
$success = $wrong = 0;
$time = microtime(true);
$i = $range/2;
$range1 = $range + $range/2;
for(; $i < $range ; $i+=1){
	
$val = $redis->evalSha($check_sha1,array("test",3000000,0.01,$i.'a'));
$val = $redis->evalSha($check_sha1,array("test",3000000,0.01,$i.'b'));
$val = $redis->evalSha($check_sha1,array("test",3000000,0.01,$i.'c'));

if($i % 2 == 1 && $val == 1){
	$wrong += 1;
}
else if($i % 2 == 0 && $val == 0){
		echo "BOOOM This Bloomfilter fails\n\n";
}
else{
	$success += 1;
}

//echo "Check";
//var_dump($val);

}

echo "\n\nCheck Time = ".((((microtime(true) - $time)*1000)/$range)/3) . "\n\n"; 

echo "Sucess : Failure = $success : $wrong ==== ".(($wrong*100)/($success+$wrong))."\n\n";
