<?php
if($mode=="general"){
	$status=self::getstatus("general",7000);
}elseif($mode=="special"){
	$status=self::getstatus("1000to10",1000);
}
?>
		