<?php
/*
Plugin Name: 輔仁網: 稿費計算器
Description: 聽講輔仁媒體冇稿費(好似係)
Version: 1.0
Author: <a href="http://www.vjmedia.com.hk">技術組</a>
GitHub Plugin URI: https://github.com/VJMedia/vj-payment
*/

defined('WPINC') || (header("location: /") && die());

include_once ( 'gapi.inc.php' );

add_action( 'init', array("VJPayment","exportcsv"));
add_action( 'init', array("VJPayment","exportauthortable"));

function vjpayment_addmenu() {
	if ( current_user_can( 'manage_options' ) ) {
		add_menu_page( "稿費計算器", "稿費計算器", 'manage_options', 'vjpayment_home', array( 'VJPayment', 'home' ), 'dashicons-editor-ol');
		add_submenu_page( 'vjpayment_home', '更新全部數據', '更新全部數據', 'manage_options', 'vjpayment_home',array( 'VJPayment', 'home' ), 'dashicons-editor-ol');
		add_submenu_page( 'vjpayment_home', '一般稿費狀況', '一般稿費狀況', 'manage_options', 'vjpayment_status',array( 'VJPayment', 'status' ), 'dashicons-editor-ol');
		add_submenu_page( 'vjpayment_home', '特殊稿費狀況', '特殊稿費狀況', 'manage_options', 'vjpayment_status_special_all',array( 'VJPayment', 'status' ), 'dashicons-editor-ol');
		add_submenu_page( 'vjpayment_home', '過往清算記錄', '過往清算記錄', 'manage_options', 'vjpayment_record',array( 'VJPayment', 'record' ), 'dashicons-editor-ol');
		add_submenu_page( 'vjpayment_home', '下載作者三欄表', '下載作者三欄表', 'manage_options', 'vjpayment_authortable',array( 'VJPayment', 'authortable' ), 'dashicons-editor-ol');
	}
} add_action( 'admin_menu', 'vjpayment_addmenu' );

function vjpayment_addmycolumns() {
	add_action( 'manage_posts_custom_column', 'vjpayment_pccolumn', 10, 2 );
} if(is_admin()){add_action( 'admin_init', 'vjpayment_addmycolumns', 1 );}

function vjpayment_pccolumn($key, $id) {
	if($key == "vj_gaview"){     
		$get=get_post_meta( $id,"vj_gaview", TRUE );
		echo $get ? strip_tags( stripslashes( $get ) ) : "-";
	}
}

function vjpayment_addcolumns( $columns ) {
	$columns['vj_gaview'] = "點擊";
	return $columns;
} add_filter( 'manage_posts_columns', 'vjpayment_addcolumns' );

/*------------------------------------------------------------------------------------------------*/

function vjpayment_paymenttype_metabox_render( $post ) {
	if(get_the_ID()){ $type=get_post_meta(get_the_ID(), "vjmedia_paymenttype", true); }
	echo "<div><select name=\"vjmedia_paymenttype\">";
	
	
	
	foreach(VJPayment::$mapper as $key=>$data){
		echo "<option value=\"{$key}\"";
		if($type === $key){ echo " selected"; }
		echo ">{$data["name"]}</option>";
	}
			
	echo "</select></div>";
	wp_nonce_field(basename(__FILE__), "vjmedia_paymenttype-nonce");
}

function vjpayment_metabox_add() {
	$screens = array( 'post' );
	foreach ( $screens as $screen ) {
		add_meta_box("vjmedia_sheeppost","Payment Type",'vjpayment_paymenttype_metabox_render',$screen);
	}
} add_action( 'add_meta_boxes', 'vjpayment_metabox_add' );

function vjpayment_metabox_save($post_id, $post, $update){
    if (!isset($_POST["vjmedia_paymenttype-nonce"]) || !wp_verify_nonce($_POST["vjmedia_paymenttype-nonce"], basename(__FILE__))) return $post_id;
    if(!current_user_can("edit_post", $post_id)) return $post_id;
    if(defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) return $post_id;
    if("post" != $post->post_type) return $post_id;
	
	if($_POST["vjmedia_paymenttype"]=="general" || ! $_POST["vjmedia_paymenttype"]){
		unset($_POST["vjmedia_paymenttype"]);
	}
	
    if(isset($_POST["vjmedia_paymenttype"])){
        update_post_meta($post_id, "vjmedia_paymenttype",$_POST["vjmedia_paymenttype"]);
    }else{
		delete_post_meta($post_id, "vjmedia_paymenttype");
	}
    
} add_action("save_post", "vjpayment_metabox_save", 10, 3);

/*------------------------------------------------------------------------------------------------*/

final class VJPayment {
	
	public static $algo = [
					"general" => ["multiplier"=>0.0142857142857143,"minview"=>7000,"maxpayment"=>100],
					"1000to10" => ["multiplier"=>0.01],
					"1000to15" => ["multiplier"=>0.015],
					"1000to20" => ["multiplier"=>0.02],
					];
	
	public static $mapper = [
					"general"=>["name"=>"一般文章","algo"=>"general"], /* 一定要放最前 */
					"sheepsheep"=>["name"=>"中央專案組文章","algo"=>"1000to10"],
					"yiuyiu"=>["name"=>"瑤瑤文章","algo"=>"1000to10"],
					"sophia"=>["name"=>"Sophia文章","algo"=>"1000to15"],
					"snowing"=>["name"=>"霏子文章","algo"=>"1000to20"],
					];
					
	public static function type2algo($paymenttype){
		return self::$mapper[$paymenttype]["algo"];
	}
	
	public static function algo2type($paymentalgo){
		$algo=[]; foreach(self::$mapper as $key=>$value){
			$algo[$value["algo"]][]=$key;
		}
		return $algo[$paymentalgo];
	}

/*------------------------------------------------------------------------------------------------*/
	
	public static function authortable(){ ?>
		<div class="wrap"><h2>下載作者三欄表</h2></div>
		<form method="post" action="" enctype="multipart/form-data" style="display: inline;">
		<?php wp_nonce_field( 'vjpayment_downloadauthortable', 'vjpayment_downloadauthortable' ); ?>
		<input type="submit" class="button-primary" value="下載作者三欄表" />
		</form>
		<?php
	}
	
	public static function record(){
		global $wpdb;

		echo "<div class=\"wrap\"><h2>過往清算記錄".($_GET["clearid"] ? ": ".$_GET["clearid"] : "")."</h2></div>";
		
		if(! $_GET["clearid"]){
			$result=$wpdb->get_results("SELECT * FROM `wp_options` WHERE option_name LIKE 'vjpayment_exportcsvclear_%'");
			
						
			foreach($result as $row){
				preg_match("/^vjpayment_exportcsvclear_(.*)(\d\d\d\d\d\d\d\d\d\d\d\d\d\d)$/",$row->option_name,$matches);
				$row->clearid=($matches[1] ? $matches[1] : '').$matches[2];
				$row->yyyymmddhhiiss=$matches[2];
				
				$sorted_result[$row->yyyymmddhhiiss]=$row;
			}
			
			ksort($sorted_result);
			echo "<ul>";
				foreach($sorted_result as $key=>$row){
					echo "<li>{$row->yyyymmddhhiiss} <a href=\"?page=vjpayment_record&clearid={$row->clearid}\">".$row->option_name."</a></li>";
				}
			echo "</ul>";
		}else{
			$result=$wpdb->get_results("SELECT * FROM `wp_options` WHERE option_name LIKE 'vjpayment_exportcsvclear_{$_GET["clearid"]}' LIMIT 1");
			$result=unserialize($result[0]->option_value);
			
			if(! is_array($result)){
				$result=unserialize($result); //old version bug
			}
			
			foreach($result[0] as $key=>$value){
				$keys[]=$key;
			}
			
			echo "<table><tr>";
			foreach($keys as $key){ echo "<td>{$key}</td>"; }
			echo "</tr>";
			
			foreach($result as $row){
				echo "<tr>";
				foreach($keys as $key){
					if(! is_object($row->{$key})){
						echo "<td>".$row->{$key}."</td>";
					}else{
						echo "<td>";
						echo "Object: ".get_class($row->{$key});
						echo "</td>";
					}
				}
				echo "</tr>";
			}
			
			echo "</table>";
			
			?><div class="wrap"><?php echo "<h2>下載CSV</h2>"; ?></div>
			<form method="post" action="" enctype="multipart/form-data" style="display: inline;">
			<?php wp_nonce_field( 'vjpayment_downloadcsv_history','vjpayment_downloadcsv_history'); ?>			
			<input type="submit" class="button-primary" value="下載CSV)" />
			</form><?php
		}
	}

	private static function update_options( $who ) {
		$gadwp = GADWP();
		$options = $gadwp->config->options;
		return $options;
	}

	public static function scan(){
		$rowperpage=500; $ignorebelowview=1000;
		global $wp_version; $gadwp = GADWP();
		if ( ! current_user_can( 'manage_options' ) ) {	return;	}
		$page=$_GET["pagenum"] ?? 1;
		
		$gapi=new VJPayment_GAPI_Controller; $gadwp = GADWP();
		$gadwp->projectId = $gadwp->config->options['ga_dash_tableid_jail'] ? $gadwp->config->options['ga_dash_tableid_jail'] : wp_die( - 25 );
		$scanresult = $gapi->scan($gadwp->projectId,1,$rowperpage,$page);
		$return=[];
		
		foreach($scanresult as $row){ if($row[2]>=$ignorebelowview){
			
			if(preg_match('/^\/articles\/\d+\/\d+\/\d+\/(\d+)/',$row[1],$matchresult)){
				$id=$matchresult[1];
				$title=get_the_title( $matchresult[1]);
				$view=$row[2];
				$path=$row[1];
				if(! $lowestview){ $lowestview=$row[2]; }else{ $lowestview = $lowestview > $row[2] ? $row[2] : $lowestview; }
				if ( ! add_post_meta($id, 'vj_gaview', $view, true ) ) { update_post_meta($id, 'vj_gaview', $view); }
				
				$vj_gaview2=get_post_meta($id,"vj_gaview2",true); if(is_array($vj_gaview2) && count($vj_gaview2)){
					$vj_gaview2[$row[1]]=$view;
					update_post_meta($id, 'vj_gaview2', $vj_gaview2);
				}else{
					add_post_meta($id, 'vj_gaview2', array($row[1] => $view), true );
				}
				
				$havereturn=true;
				$return[]=(object)array("id"=>$id,"title"=>$title,"view"=>$view,"path"=>$path);
			}
		}else{ /* <$ignorebelowview */ break;}}
		
		if($lowestview >= $ignorebelowview && count($scanresult)>=$rowperpage){
			echo "<script>window.location='?page=vjpayment_home&pagenum=".($page+1)."';</script>";
			//echo "<a href=\"?page=vjpayment_home&pagenum=".($page+1)."\">Next Page</a>";
		}
	
		if(! add_option("vjpayment_lastscan",current_time("Y-m-d H:i:s"),"","no")){ update_option("vjpayment_lastscan",current_time("Y-m-d H:i:s")); }
		
		if($havereturn){ return (object)$return; }else{ return false;	}
	}
	
	public static function home() {
		$scanresult=self::scan();
		?>
	
	<div id="poststuff" class="gadwp"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><div class="settings-wrapper"><div class="inside">
	<div class="wrap"><?php echo "<h2>VJPayment：Scan Google Analytics</h2>"; ?></div>
<?php
if($scanresult){
	echo "<table><tr><th>ID</th><th>Title</th><th>View</th><th>Path</th></tr>";
	foreach($scanresult as $row){ 
			echo "<tr><td>{$row->id}</td><td>{$row->title}</td><td>{$row->view}</td><td>{$row->path}</td></tr>";	
	}
	echo "</table>";
}else{
	echo "<h1>DONE!</h1>";
}
?>
				</div>
			</div>
		</div>
	</div>
	</div>
		<?php
		
	}
	
	public static function csvquote($args){
		foreach($args as $arg){
			$return[]="\"".str_replace('"','\"',$arg)."\"";
		}
		return implode(",",$return);
	}

	
	public static function exportauthortable() {
		if ( isset( $_POST['vjpayment_downloadauthortable'] )) {
				check_admin_referer( 'vjpayment_downloadauthortable', 'vjpayment_downloadauthortable' );
		}else{
			return; //Exit this function
		}
		
		global $wpdb;
		error_reporting(0);
		
		ob_end_clean();
		$filename = "vjauthortable-".current_time( 'YmdHis' ).'.csv';
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );
		echo self::csvquote(array("作者ID","作者Slug","作者名稱"))."\n";
		
		
		$authordata = $wpdb->get_results( 'SELECT ID, user_login, display_name FROM wp_users ORDER BY ID', ARRAY_A );
		
		foreach($authordata as $key => $row) {
			echo self::csvquote(array($row["ID"],$row["user_login"],$row["display_name"]))."\n";
		}
		
		exit();
	}
	
	public static function exportcsv() {
		if( isset($_POST['vjpayment_downloadcsv_history'])){
			global $wpdb; $clear=false;
			$result=$wpdb->get_results("SELECT * FROM `wp_options` WHERE option_name LIKE 'vjpayment_exportcsvclear_{$_GET["clearid"]}' LIMIT 1");
			$filename = "vjpayment-history-".$_GET["clearid"].'.csv';
			$status=unserialize(unserialize($result[0]->option_value));
			//var_dump($status); exit();
		}elseif ( isset( $_POST['vjpayment_downloadcsv'] ) || isset( $_POST['vjpayment_downloadcsvclear'] ) ) {
			$mode="general";
			if(isset( $_POST['vjpayment_downloadcsv'])){
			check_admin_referer( 'vjpayment_downloadcsv', 'vjpayment_downloadcsv' );
			$clear=false;
			}elseif(isset( $_POST['vjpayment_downloadcsvclear'] )){
			check_admin_referer( 'vjpayment_downloadcsvclear', 'vjpayment_downloadcsvclear' );
			$clear=true;
			}
			$status=self::getstatus("general",7000);
			$filename = "vjpayment-".($mode)."-".current_time( 'YmdHis' ).($clear ? "(清算)" : "(參考)").'.csv';
			
		}elseif ( isset( $_POST['vjpayment_downloadcsv_special_all'] ) || isset( $_POST['vjpayment_downloadcsvclear_special_all'] ) ) {
			$mode="special_all";
			if(isset( $_POST['vjpayment_downloadcsv_special_all'])){
			check_admin_referer( 'vjpayment_downloadcsv_special_all', 'vjpayment_downloadcsv_special_all' );
			$clear=false;
			}elseif(isset( $_POST['vjpayment_downloadcsvclear_special_all'] )){
			check_admin_referer( 'vjpayment_downloadcsvclear_special_all', 'vjpayment_downloadcsvclear_special_all' );
			$clear=true;
			}
			$status=self::getstatus(["1000to10","1000to15","1000to20"],100);
			$filename = "vjpayment-".($mode)."-".current_time( 'YmdHis' ).($clear ? "(清算)" : "(參考)").'.csv';
			
		}else{
			return; //exit this function
		}
		
		error_reporting(0);
		ob_end_clean();
		
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );
		
		echo self::csvquote(array("文章ID","文章標題","Type","Algo","Multiplier","Minview","MaxPayment","點擊","已達成","已支付","尚欠","作者ID","作者Slug"))."\n";
		
		if($status !== null){
			foreach($status as $key => $row) {
				if($row->needpay>0){
					echo self::csvquote(array($row->id,$row->title,$row->paymenttype,$row->paymentalgo,$row->multiplier,$row->minview ?? 0,$row->maxpayment ?? "unlimited",$row->view,$row->reach,$row->paid,$row->needpay,$row->author->ID,$row->author->user_nicename))."\n";					
					if ( $clear && ! add_post_meta($row->id, 'vj_paid', $row->reach, true ) ) {
						update_post_meta($row->id, 'vj_paid', $row->reach);
					}
				}else{
					unset($status[$key]); //For Serializae Save to DB					
				}
			}
		}
		
		if(! isset($_POST['vjpayment_downloadcsv_history'])){
			$optionname=$clear ? "vjpayment_exportcsvclear_".$mode."_" : "vjpayment_exportcsv_".$mode."_";
			add_option($optionname.current_time( 'YmdHis' ),serialize($status),"","no");
		}
		exit();
	}
	
	public static function getstatus($paymentalgo,$viewbiggerthan){
		if(! is_array($paymentalgo)){
			$paymentalgo=[$paymentalgo];
		}
		
		$paymenttypequery=[]; foreach($paymentalgo as $algo){
			if($algo !== "general"){
				$result=self::algo2type($algo);
				$paymenttypequery = array_merge($paymenttypequery,$result);
			}else{
				$paymenttypequery=false; //so general不能混合於special
			}
		}
		$paymenttypequery= is_array($paymenttypequery) ? implode(",",$paymenttypequery) : $paymenttypequery;
		
		$meta_query=[];
		array_push($meta_query,['key' => 'vj_gaview2','compare' => 'EXISTS']);
		
		if($paymenttypequery===false){
			array_push($meta_query,['key' => 'vjmedia_paymenttype','compare' => 'NOT EXISTS',]);
		}elseif($paymenttypequery){
			array_push($meta_query,['key' => 'vjmedia_paymenttype','value' => $paymenttypequery,'compare' => 'IN',]);
		}
	
		$the_query = new WP_Query(['posts_per_page' => 1000, 'post_type' => 'post','meta_query' => $meta_query]);
		$vj_gaview2=array(); while ( $the_query->have_posts() ) {
			$the_query->the_post();
			$row=new stdClass(); 
			$row->id=get_the_ID();
			$row->title=get_the_title();
			if(function_exists('coauthors_posts_links')) {
				$i = new CoAuthorsIterator($row->id);
				if(count($i->authordata_array) == 1){
					$row->author=$i->authordata_array[0];
				}else{
						
				}
			}else{
				$row->author=get_userdata(get_post_field( 'post_author', $post->id ));
			}
			
			$row->paymenttype = get_post_meta($row->id, "vjmedia_paymenttype",true);
			$row->paymenttype = $row->paymenttype=="" ? "general" : $row->paymenttype;
			$row->paymentalgo=self::type2algo($row->paymenttype);
			$row->multiplier=self::$algo[$row->paymentalgo]["multiplier"];
			$row->minview=self::$algo[$row->paymentalgo]["minview"] ?? 0;
			$row->maxpayment=self::$algo[$row->paymentalgo]["maxpayment"];
			$row->view=(int)array_sum(get_post_meta($row->id,"vj_gaview2",true));
			$row->reach = ($row->view >= $row->minview) ? floor($row->view * $row->multiplier) : 0;
			
			if($row->maxpayment && ($row->reach > $row->maxpayment)){
				$row->reach = $row->maxpayment;
			}

			$row->paid=get_post_meta($row->id,"vj_paid")[0] ? (int)get_post_meta($row->id,"vj_paid")[0] : 0;
			$row->needpay=$row->reach-$row->paid;
			$row->debug=get_post_meta($row->id,"vj_gaview2",true);
			
			if($row->needpay >0 && $row->view >= $viewbiggerthan){
				$return[]=$row;
			}
		}
		return $return;
	}
	
	public static function status() {
		if($_GET["page"]=="vjpayment_status"){
			$mode="general"; $mode_text="一般";
		}elseif($_GET["page"]=="vjpayment_status_special_all"){
			$mode="special_all"; $mode_text="特殊";
		}else{
			exit(0);
		}
		?><div id="poststuff" class="gadwp"><div id="post-body" class="metabox-holder columns-2"><div id="post-body-content"><div class="settings-wrapper"><div class="inside">
		<div class="wrap"><?php echo "<h2>VJPayment：{$mode_text}稿費狀況 (數據更新日期:".get_option("vjpayment_lastscan").")</h2>"; ?></div>
		
		<style>#vjpayment_status td{ vertical-align:top; border-bottom: 1px solid #CCC;}</style>
		<?php
		echo "<style>#vjpayment_status td{ padding: 2px 4px;}</style>";
		echo "<table id=\"vjpayment_status\"><tr><th>文章ID</th><th>文章標題</th><th>Type</th><th>Algo</th><th>Multiplier</th><th>點擊</th><th>已達成</th><th>已付</th><th>尚欠</th><th>作者ID</th><th>作者Slug</th><th>Debug</th></tr>";
		

		if($mode=="general"){
			$status=self::getstatus("general",7000);
		}elseif($mode=="special_all"){
			$status=self::getstatus(["1000to10","1000to15","1000to20"],1000);
        }
		if($status===NULL){
			echo "<h3 style=\"color: red\">小動物：沒有撓到任何符合的資料</h3>";
		}else{
			$totalneedpay=0; foreach($status as $row) {
				if($row->needpay>0){
					$totalneedpay+=$row->needpay;
					echo "<tr><td>".$row->id."</td><td>".$row->title."</td><td>{$row->paymenttype}</td><td>{$row->paymentalgo}</td><td>{$row->multiplier}</td><td>{$row->view}</td><td>{$row->reach}</td><td>{$row->paid}</td><td>{$row->needpay}</td><td>{$row->author->ID}</td><td>{$row->author->user_nicename}</td><td>";
					echo "<pre style=\"margin: 0;\">"; foreach($row->debug as $key=>$value){ echo "<div>{$value}\t{$key}</div>"; } echo "</pre>";
					echo "</td></tr>";
				}
			}
		}
		
		echo "<tr><td></td><td></td><td></td><td></td><td></td><td>{$totalneedpay}</td><td></td><td></td><td></td></tr>";
		echo "</table>";
		
		?>
		<div class="wrap"><?php echo "<h2>下載CSV參考用({$mode_text})</h2>"; ?></div>
		<form method="post" action="" enctype="multipart/form-data" style="display: inline;">
		<?php wp_nonce_field( 'vjpayment_downloadcsv'.(preg_match("/^special_(.*)/",$mode,$match) ? "_special_".$match[1] : null),
			'vjpayment_downloadcsv'.(preg_match("/^special_(.*)/",$mode,$match) ? "_special_".$match[1] : null) ); ?>
		<input type="submit" class="button-primary" value="下載CSV參考用(<?=$mode_text; ?>文章)" />
		</form>
		<div class="wrap"><?php echo "<h2>下載CSV並清算({$mode_text})</h2>"; ?></div>
		<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'vjpayment_downloadcsvclear'.(preg_match("/^special_(.*)/",$mode,$match) ? "_special_".$match[1] : null),
			'vjpayment_downloadcsvclear'.(preg_match("/^special_(.*)/",$mode,$match) ? "_special_".$match[1] : null) ); ?>
		<input type="submit" class="button-secondary" value="下載CSV並清算(<?=$mode_text; ?>文章)" style="display: inline;" />
		</form>
		</div></div></div></div></div>
	<?php
	}
	
}
?>
