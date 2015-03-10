<?php
$redis = new Redis();
$redis->pconnect("127.0.0.1", 6379, 600);

$script = <<<EOF

local entries   = ARGV[2]
local precision = ARGV[3]
local hash      = redis.sha1hex(ARGV[4])
local countkey  = ARGV[1] .. ':count'
local count     = redis.call('GET', countkey)
	if not count then
  count = 1
else
  count = count + 1
end

local factor = math.ceil((entries + count) / entries) 

local index  = math.ceil(math.log(factor) / 0.69314718055995)
local scale  = math.pow(2, index - 1) * entries
local key    = ARGV[1] .. ':' .. index

local bits = math.floor(-(scale * math.log(precision * math.pow(0.5, index))) / 0.4804530139182)

local k = math.floor(0.69314718055995 * bits / scale)

local h = { }
h[0] = tonumber(string.sub(hash, 1 , 8 ), 16)
h[1] = tonumber(string.sub(hash, 9 , 16), 16)
h[2] = tonumber(string.sub(hash, 17, 24), 16)
h[3] = tonumber(string.sub(hash, 25, 32), 16)

local found = true
for i=1, k do
  if redis.call('SETBIT', key, (h[i % 2] + i * h[2 + (((i + (i % 2)) % 4) / 2)]) % bits, 1) == 0 then
    found = false
  end
end

if found == false then
  redis.call('INCR', countkey)
end

EOF;

$check_script = <<<EOF

local entries   = ARGV[2]
local precision = ARGV[3]
local count     = redis.call('GET', ARGV[1] .. ':count')

if not count then
  return 0
end

local factor = math.ceil((entries + count) / entries)
-- 0.69314718055995 = ln(2)
local index = math.ceil(math.log(factor) / 0.69314718055995)
local scale = math.pow(2, index - 1) * entries

local hash = redis.sha1hex(ARGV[4])

-- This uses a variation on:
-- 'Less Hashing, Same Performance: Building a Better Bloom Filter'
-- http://www.eecs.harvard.edu/~kirsch/pubs/bbbf/esa06.pdf
local h = { }
h[0] = tonumber(string.sub(hash, 1 , 8 ), 16)
h[1] = tonumber(string.sub(hash, 9 , 16), 16)
h[2] = tonumber(string.sub(hash, 17, 24), 16)
h[3] = tonumber(string.sub(hash, 25, 32), 16)

-- Based on the math from: http://en.wikipedia.org/wiki/Bloom_filter#Probability_of_false_positives
-- Combined with: http://www.sciencedirect.com/science/article/pii/S0020019006003127
-- 0.4804530139182 = ln(2)^2
local maxbits = math.floor((scale * math.log(precision * math.pow(0.5, index))) / -0.4804530139182)

-- 0.69314718055995 = ln(2)
local maxk = math.floor(0.69314718055995 * maxbits / scale)
local b    = { }

for i=1, maxk do
  table.insert(b, h[i % 2] + i * h[2 + (((i + (i % 2)) % 4) / 2)])
end

for n=1, index do
  local key    = ARGV[1] .. ':' .. n
  local found  = true
  local scalen = math.pow(2, n - 1) * entries

  -- 0.4804530139182 = ln(2)^2
  local bits = math.floor((scalen * math.log(precision * math.pow(0.5, n))) / -0.4804530139182)

  -- 0.69314718055995 = ln(2)
  local k = math.floor(0.69314718055995 * bits / scalen)

  for i=1, k do
    if redis.call('GETBIT', key, b[i] % bits) == 0 then
      found = false
      break
    end
  end

  if found then
    return 1
  end
end

return 0
EOF;

//echo $redis->script('load',$check_script);
$time = microtime(true);
$range = 30000000;
for( $i = 0; $i < $range ; $i+=2){
$val = $redis->evalSha("1f22037d7c696a7996416f92199a7f65ef3cc9f0",array("test",3000000,0.01,$i.'a'));
$val = $redis->evalSha("1f22037d7c696a7996416f92199a7f65ef3cc9f0",array("test",3000000,0.01,$i.'b'));
$val = $redis->evalSha("1f22037d7c696a7996416f92199a7f65ef3cc9f0",array("test",3000000,0.01,$i.'c'));
//echo "Add";
//var_dump($val);
}

echo "\n\nAdd Time = ".(((microtime(true) - $time)*2000/ ($range *3) )) . "\n\n"; 
$success = $wrong = 0;
$time = microtime(true);
$i = $range/2;
$range1 = $range + $range/2;
for(; $i < $range ; $i+=1){
	
$val = $redis->evalSha("b6abf4bf13211242d498a05dc6efde420e74c442",array("test",3000000,0.01,$i.'a'));
$val = $redis->evalSha("b6abf4bf13211242d498a05dc6efde420e74c442",array("test",3000000,0.01,$i.'b'));
$val = $redis->evalSha("b6abf4bf13211242d498a05dc6efde420e74c442",array("test",3000000,0.01,$i.'c'));

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

