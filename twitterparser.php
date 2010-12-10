<?
$debug = false;

if ($debug) {
	error_reporting(E_ALL);
}

/*
** Copyright 2009, 2010 Double Rebel
** WTFPL Licensed
** 
** Works for any twitter username.  Parses the public XML feed, no login necessary.
** Requires a writeable cache directory.  Defaults is subdirectory cache/.
** If twitter is failwhaling it will load from cache.  Recaches once a minute (60), unless
** already processing.
**
** Maxtweets is the number of tweets to pull.
*/

$username = 'username';
$cachefile = 'cache/twitter_cache.html';
$processingflagfile = 'cache/.processingflag';
$maxcachetime = 60;
$timeout = 300;
$maxtweets = 5;
$skipdirectreplies = true;

if ( (file_exists($cachefile) && $cachefiletime = filemtime($cachefile)) && ( ((time() - $cachefiletime) < $maxcachetime) || (file_exists($processingflagfile) && ((time() - $cachefiletime) < $timeout)) ) ) {
	echo file_get_contents($cachefile);
} else {
	touch($processingflagfile);
	$feed = @file_get_contents('http://api.twitter.com/1/statuses/user_timeline/'.$username.'.rss');
	if (!$feed) {
		echo file_get_contents($cachefile);
		@unlink($processingflagfile);
		die();
	}
	
	$parser = xml_parser_create('UTF-8');
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $feed, $vals, $index);
	xml_parser_free($parser);
	
	$j=0;
	$timeline = array();
	$index = $index['DESCRIPTION'];
	
	for($i = 1, $ii = count($index); $i < $ii; $i++) {
		$statusindex = $index[$i];
		if ($skipdirectreplies && $vals[$statusindex]['value'][13] == '@') continue; //Skip direct replies
		$timeline[$j] = array('status' => substr($vals[$statusindex]['value'], 13),
							  'timestamp' => from_twitterdate($vals[++$statusindex]['value']),
							  'id' => $vals[++$statusindex]['value']);
		if (++$j >= $maxtweets) break;
	}
	
	if ($debug) print_r($timeline); //dumps timeline before parsing to assist with debugging
	
	$output = '<ul>';
	
	//Formatting to comply with http://dev.twitter.com/pages/display_guidelines
	for ($i = 0, $ii = count($timeline); $i < $ii; $i++) {
		//Add links -- http://www.phpro.org/examples/URL-to-Link.html
		$status = preg_replace("/([\w]+:\/\/[\w-?&;#~=\.\/\@]+[\w\/])/i", "<a target=\"_blank\" href=\"$1\">$1</A>", $timeline[$i]['status']);
		//Add @ links -- //http://neverusethisfont.com/blog/2008/10/automatically-linking-twitter-usernames/
		$status = preg_replace('/(^|[^a-z0-9_])@([a-z0-9_]+)/i', '$1<a href="http://twitter.com/$2">@$2</a>', $status);
		//Add # links -- http://granades.com/2009/04/06/using-regular-expressions-to-match-twitter-users-and-hashtags/
		$status = preg_replace('/(^|\s)#(\w+)/', '\1<a href="http://search.twitter.com/search?q=%23\2">#\2</a>', $status);
		
		$output .= '<li>"'.$status.'"';
		$output .= '<span class="pubDate"><a href="'.$timeline[$i]['id'].'" target="blank">['.timeSince($timeline[$i]['timestamp']).' ago]</a></span>';
		$output .= '</li>';
	}
	
	$output .= '</ul>';
	echo $output;

	$handle = @fopen($cachefile, "w");
	fwrite($handle, $output);
	@unlink($processingflagfile);
}


// Converts time to Unix timestamp from Twitter format
// http://groups.google.com/group/twitter-development-talk/browse_thread/thread/e2c44b47f5ec2125
function from_twitterdate($date) {
        list($D, $d, $M, $y, $h, $m, $s, $z) = sscanf($date, "%3s, %2d %3s %4d %2d:%2d:%2d %5s");
        return strtotime("$d $M $y $h:$m:$s $z");
}


// Works out the time since the entry post, takes a an argument in unix time (seconds)
// http://viralpatel.net/blogs/2009/06/twitter-like-n-min-sec-ago-timestamp-in-php-mysql.html
function timeSince($original) {
    // array of time period chunks
    $chunks = array(
		array(60 * 60 * 24 * 365 , 'year'),
		array(60 * 60 * 24 * 30 , 'month'),
		array(60 * 60 * 24 * 7, 'week'),
		array(60 * 60 * 24 , 'day'),
		array(60 * 60 , 'hr'),
		array(60 , 'min'),
		array(1 , 'sec'),
    );

    $today = time(); /* Current unix time  */
    $since = $today - $original;

    // $j saves performing the count function each time around the loop
    for ($i = 0, $j = count($chunks); $i < $j; $i++) {

		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];
	
		// finding the biggest chunk (if the chunk fits, break)
		if (($count = floor($since / $seconds)) != 0) {
			break;
		}
    }

    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";

    if ($i + 1 < $j) {
		// now getting the second item
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];
	
		// add second item if its greater than 0
		if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
			$print .= ($count2 == 1) ? ', 1 '.$name2 : " $count2 {$name2}s";
		}
    }
    return $print;
}

?>
