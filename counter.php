<?php
if($paymenttype=="general"){
	$row->reach = array_sum(get_post_meta($row->id,"vj_gaview2",true)) >= 7000 ? 100 : 0;
}elseif($paymenttype=="1000to10"){
	$row->reach = floor(array_sum(get_post_meta($row->id,"vj_gaview2",true)) * 0.01);
}
?>