<?php
function utf8_decode_ent($string) { 
      /* Only do the slow convert if there are 8-bit characters */ 
    /* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */ 
    if (! ereg("[\200-\237]", $string) and ! ereg("[\241-\377]", $string)) 
        return $string; 

    // decode three byte unicode characters 
    $string = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e", "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'", $string); 

    // decode two byte unicode characters 
    $string = preg_replace("/([\300-\337])([\200-\277])/e", "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'", $string); 

    return $string; 
} 

require_once("Parsedown.php");
require_once("config.php");
header("Content-Type: text/xml; charset=UTF-8");

$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"Accept-language: en\r\n" .
              "Cookie: foo=bar\r\n",
    'user_agent' => "PHP-Appcast-by-github-huksley"
  )
);

$ctx = stream_context_create($opts);
$json = file_get_contents(RELEASES_API_URL, false, $ctx);
$o = json_decode($json, true);

echo "<?" . "xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?><rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle"  xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
<title>Release appcast</title>
<link><?php echo "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";?></link>
<description>Releases from github</description>
<language>en</language>
<?php
for ($i = 0; $i < count($o); $i++) {
	$r = $o[$i];
	$rlink = $r["html_url"];
	$rname = $r["name"];
	$rtext = $r["body"]; //htmlentities($r["body"], ENT_HTML401, "UTF-8");
	$Parsedown = new Parsedown();
	$rtext = $Parsedown->text($rtext); 
	// encode utf8 entites - winsparkle? wininet? does not like utf8
	$rtext = utf8_decode_ent($rtext);

	if (!$r["prerelease"]) {
		for ($j = count($r["assets"]) - 1; $j >= 0; $j--) {
			$a = $r["assets"][$j];
			$url = $a["browser_download_url"];
			$size = $a["size"];
			// Fri, 03 Apr 2015 01:00:00 +0300
			$date = strftime("%a, %d %b %Y %T %z", strtotime($a["created_at"]));
			$version = "1.0";
			if (strstr($url, ".exe") == ".exe") {
				$vv = array();
				if (preg_match("/.*-([0-9]+\\.[0-9]+)[-.]?.*/", $url, $vv)) {
					$version = $vv[1];
				}
				?>
<item>
<title><?echo $rname ?></title>
<pubDate><?php echo $date ?></pubDate>
<description><![CDATA[
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<p><a href="<?php echo $rlink ?>" target="_blank"><?echo $rlink ?></a></p>
<?php echo $rtext ?>
]]>
</description>
<enclosure
	sparkle:os="windows"
	url="<?php echo $url ?>"
	sparkle:version="<?php echo $version ?>"
	length="<?php echo $size ?>"
	type="application/octet-stream" />
</item>
<?php

			}
		}
	}
}
?>
</channel>
</rss>