<?php
if ( isset( $_POST['vjpayment_downloadcsv'] ) || isset( $_POST['vjpayment_downloadcsvclear'] ) ) {
	$mode="general";
	if(isset( $_POST['vjpayment_downloadcsv'])){
		check_admin_referer( 'vjpayment_downloadcsv', 'vjpayment_downloadcsv' );
		$clear=false;
	}elseif(isset( $_POST['vjpayment_downloadcsvclear'] )){
		check_admin_referer( 'vjpayment_downloadcsvclear', 'vjpayment_downloadcsvclear' );
		$clear=true;
	}
	$status=self::getstatus("general",7000);
}elseif ( isset( $_POST['vjpayment_downloadcsv_special'] ) || isset( $_POST['vjpayment_downloadcsvclear_special'] ) ) {
	$mode="special";
	if(isset( $_POST['vjpayment_downloadcsv_special'])){
		check_admin_referer( 'vjpayment_downloadcsv_special', 'vjpayment_downloadcsv_special' );
		$clear=false;
	}elseif(isset( $_POST['vjpayment_downloadcsvclear_special'] )){
		check_admin_referer( 'vjpayment_downloadcsvclear_special', 'vjpayment_downloadcsvclear_special' );
		$clear=true;
	}
	$status=self::getstatus("1000to10",1000);
}else{
	return; //Exit this function
}
?>