<?php
declare(strict_types=1);
// Remote photos are linked in the browser, never downloaded by the server.
function remoteImageUrl(string $url):bool {
 if(strlen($url)>800 || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || !filter_var($url,FILTER_VALIDATE_URL))return false;
 $parts=parse_url($url);
 if(!$parts || strtolower($parts['scheme']??'')!=='https' || isset($parts['user']) || isset($parts['pass']))return false;
 $host=strtolower($parts['host']??'');
 if($host==='localhost' || str_ends_with($host,'.localhost') || !str_contains($host,'.'))return false;
 if(filter_var(trim($host,'[]'),FILTER_VALIDATE_IP))return (bool)filter_var(trim($host,'[]'),FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
 return true;
}
function dishImagePath(string $url):bool {
 return $url==='' || (bool)preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~D',$url) || remoteImageUrl($url);
}
