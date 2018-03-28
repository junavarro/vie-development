<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function eme_new_discount() {
   $discount = array(
   'name' => '',
   'description' => '',
   'type' => 1,
   'value' => 0,
   'coupon' => '',
   'dgroup' => '',
   'expire' => '',
   'strcase' => 1,
   'count' => 0,
   'maxcount' => 0
   );
   return $discount;
}

function eme_new_discountgroup() {
   $discountgroup = array(
   'name' => '',
   'description' => '',
   'maxdiscounts' => 0
   );

   return $discountgroup;
}

function eme_discounts_page() {      
   global $wpdb;
   
   if (!current_user_can( get_option('eme_cap_discounts')) && (isset($_GET['eme_admin_action']) || isset($_POST['eme_admin_action']))) {
      $message = __('You have no right to manage discounts!','events-made-easy');
      eme_categories_table_layout($message);
      return;
   }
  
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "discounts") { 
      eme_manage_discounts_layout();
      return;
   }
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "dgroups") { 
      eme_manage_dgroups_layout();
      return;
   }
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "edit_discount") { 
      eme_discounts_edit_layout();
      return;
   }
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "edit_dgroup") { 
      eme_dgroups_edit_layout();
      return;
   }

   // Insert/Update/Delete Record
   $message = '';
   if (isset($_POST['eme_admin_action'])) {
      check_admin_referer('eme_discounts','eme_admin_nonce');
      if ($_POST['eme_admin_action'] == "do_importdiscounts" ) {
         $table = $wpdb->prefix.DISCOUNTS_TBNAME;
         if (is_uploaded_file($_FILES['eme_csv']['tmp_name'])) {
            $handle = fopen($_FILES['eme_csv']['tmp_name'], "r");
            // first line is the column headers
	    $headers = fgetcsv($handle);
            // check required columns
            if (!in_array('name',$headers)||!in_array('type',$headers)||!in_array('coupon',$headers)||!in_array('value',$headers)) {
               $message = __("Not all required fields present.",'events-made-easy');
            } else {
               while (($row = fgetcsv($handle)) !== FALSE) {
                  $discount = array();
                  foreach ($headers as $i => $heading_i) {
                    $discount[$heading_i] = $row[$i];
                  } 
                  $wpdb->insert($table, $discount);
               }
               $message = __("Discounts imported, please verify the result.",'events-made-easy');
            }
         }
      } elseif ($_POST['eme_admin_action'] == "do_importdgroups" ) {
         $table = $wpdb->prefix.DISCOUNTGROUPS_TBNAME;
         if (is_uploaded_file($_FILES['eme_csv']['tmp_name'])) {
            $handle = fopen($_FILES['eme_csv']['tmp_name'], "r");
            // first line is the column headers
	    $headers = fgetcsv($handle);
            // check required columns
            if (!in_array('name',$headers)) {
               $message = __("Not all required fields present.",'events-made-easy');
            } else {
               while (($row = fgetcsv($handle)) !== FALSE) {
                  $discount = array();
                  foreach ($headers as $i => $heading_i) {
                    $discount[$heading_i] = $row[$i];
                  } 
                  $wpdb->insert($table, $discount);
               }
               $message = __("Discount groups imported, please verify the result.",'events-made-easy');
            }
         }
      } elseif ($_POST['eme_admin_action'] == "delete_discounts" && isset($_POST['discounts'])) { 
         $discounts = $_POST['discounts'];
         foreach($discounts as $id) {
            eme_delete_discount(intval($id));
         }
         $message = __('Successfully deleted the selected discounts.','events-made-easy');
         eme_manage_discounts_layout($message);
         return;
      } elseif ($_POST['eme_admin_action'] == "delete_dgroups" && isset($_POST['dgroups'])) { 
         $dgroups = $_POST['dgroups'];
         foreach($dgroups as $id) {
            eme_delete_dgroup(intval($id));
         }
         $message = __('Successfully deleted the selected discountgroups.','events-made-easy');
         eme_manage_dgroups_layout($message);
         return;
      }
   }

   eme_discounts_main_layout($message);
}

function eme_discounts_main_layout($message = "") {
   $nonce_field = wp_nonce_field('eme_discounts','eme_admin_nonce',false,false);
   $discounts_destination = admin_url("admin.php?page=eme-discounts&amp;eme_admin_action=discounts");
   $dgroups_destination = admin_url("admin.php?page=eme-discounts&amp;eme_admin_action=dgroups");
   $upload_destination = admin_url("admin.php?page=eme-discounts");
   $table = "
      <div class='wrap nosubsub'>\n
         <div id='icon-edit' class='icon32'>
            <br />
         </div>
         <h1>".__('Discounts', 'events-made-easy')."</h1>
   ";
         
   if($message != "") {
      $table .= "
            <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
               <p>$message</p>
            </div>";
   }
         
   $table .= "<h2>".__('Manage discounts', 'events-made-easy')."</h2>";
   $table .= "<a href='$discounts_destination'>".__("Manage discounts",'events-made-easy')."</a><br />";
   $table .= "<h2>".__('Manage discountgroups', 'events-made-easy')."</h2>";
   $table .= "<a href='$dgroups_destination'>".__("Manage discount groups",'events-made-easy')."</a><br />";
   $table .= "<h2>".__('Upload disounts', 'events-made-easy')."</h2>";
   $table .= "
             <form enctype='multipart/form-data' action='$upload_destination' method='post'>
             <input type='hidden' name='eme_admin_action' value='do_importdiscounts' />
             $nonce_field";
   $table.=__("Discount CSV file to import:",'events-made-easy');
   $table.="
             <br />
             <input size='50' type='file' name='eme_csv'><br />
             <input type='submit' name='submit' value='Upload'><br />";
   $table.=__('The first line of the csv should contain the column header names, you can use these: name (*), description, type (*), coupon (*), dgroup, value (*), maxcount, expire, strcase. The ones marked with "*" are required, see the doc for the meaning of each column','events-made-easy');
   $table.="<br />";
   $table.=__('Example first line: "name","type","coupon","value"','events-made-easy');
   $table.="<br />";
   $table.=__('Example entry line: "testdiscount","1","AZERTY","2"','events-made-easy');
   $table.="
             </form>";
   $table .= "<h2>".__('Upload disountgroups', 'events-made-easy')."</h2>";
   $table .= "
             <form enctype='multipart/form-data' action='$upload_destination' method='post'>
             <input type='hidden' name='eme_admin_action' value='do_importdgroups' />
             $nonce_field";
   $table.=__("Discountgroup CSV file to import:",'events-made-easy');
   $table.="
             <br />
             <input size='50' type='file' name='eme_csv'><br />
             <input type='submit' name='submit' value='Upload'><br />";
   $table.=__('The first line of the csv should contain the column header names, you can use these: name (*), description, maxdiscounts. The ones marked with "*" are required, see the doc for the meaning of each column','events-made-easy');
   $table.="<br />";
   $table.=__('Example first line: "name","description"','events-made-easy');
   $table.="<br />";
   $table.=__('Example entry line: "group1","my discount group1"','events-made-easy');
   $table.="
             </form>
   </div>";
   echo $table;  
}

function eme_manage_discounts_layout($message="") {
   $nonce_field = wp_nonce_field('eme_discounts','eme_admin_nonce',false,false);

   ?>
      <div class="wrap nosubsub">
       <div id="poststuff">
         <div id="icon-edit" class="icon32">
            <br />
         </div>
         
         <?php if ($message != "") { ?>
            <div id="message" class="notice is-dismissible" style="background-color: rgb(255, 251, 204);">
               <p><?php echo $message ?></p>
            </div>
         <?php } ?>

         <h1><?php _e('Discounts', 'events-made-easy') ?></h1>
   <form action="#" method="post">
   <?php echo $nonce_field; ?>
   <select id="eme_admin_action" name="eme_admin_action">
   <option value="" selected="selected"><?php _e ( 'Bulk Actions' , 'events-made-easy'); ?></option>
   <option value="deleteDiscounts"><?php _e ( 'Delete selected discounts','events-made-easy'); ?></option>
   </select>
   <button id="DiscountsActionsButton" class="button-secondary action"><?php _e ( 'Apply' , 'events-made-easy'); ?></button>
   </form>
   <span class="rightclickhint">
      <?php _e('Hint: rightclick on the column headers to show/hide columns','events-made-easy'); ?>
   </span>
         <div id="DiscountsTableContainer" style="width=98%;"></div>
      </div> 
   </div>
   <?php
}

function eme_manage_dgroups_layout($message="") {
   $nonce_field = wp_nonce_field('eme_discounts','eme_admin_nonce',false,false);

   ?>
      <div class="wrap nosubsub">
       <div id="poststuff">
         <div id="icon-edit" class="icon32">
            <br />
         </div>
         
         <?php if ($message != "") { ?>
            <div id="message" class="notice is-dismissible" style="background-color: rgb(255, 251, 204);">
               <p><?php echo $message ?></p>
            </div>
         <?php } ?>

         <h1><?php _e('Discountgroups', 'events-made-easy') ?></h1>
   <form action="#" method="post">
   <?php echo $nonce_field; ?>
   <select id="eme_admin_action" name="eme_admin_action">
   <option value="" selected="selected"><?php _e ( 'Bulk Actions' , 'events-made-easy'); ?></option>
   <option value="deleteDiscountGroups"><?php _e ( 'Delete selected discount groups','events-made-easy'); ?></option>
   </select>
   <button id="DiscountGroupsActionsButton" class="button-secondary action"><?php _e ( 'Apply' , 'events-made-easy'); ?></button>
   </form>
   <p class="search-box">
      <?php _e('Hint: rightclick on the column headers to show/hide columns','events-made-easy'); ?>
   </p>
   <div id="DiscountGroupsTableContainer" style="width=98%;"></div>
   </div> 
   </div>
   <?php
}

function eme_booking_discount($event,$booking,$do_update=1) {
   $total_discount=0;
   $discount_id=0;
   $discountgroup_id=0;
   $event_id=$event['event_id'];
   if (is_admin()) {
      if (isset($_POST['DISCOUNT']) && !empty($_POST['DISCOUNT']))
         $total_discount=sprintf("%01.2f",$_POST['DISCOUNT']);
   } elseif ($event['event_properties']['rsvp_discountgroup']) {
      $discount_group = eme_get_discountgroup_by_name($event['event_properties']['rsvp_discountgroup']);
      $discountgroup_id=$discount_group['id'];
      if (!$discountgroup_id) return false;

      $discount_ids = eme_get_discountids_by_groupname($event['event_properties']['rsvp_discountgroup']);
      $group_count=0;
      $max_discounts=$discount_group['maxdiscounts'];

      $applied_discountids=array();
      foreach ($discount_ids as $id) {
	 // a discount can only be applied once
         if (isset($applied_discountids['id'])) continue;
         if ($max_discounts==0 || $group_count<$max_discounts) {
            $discount = eme_get_discount($id);
            if ($res=eme_calc_booking_discount($discount,$booking)) {
               $total_discount+=$res;
               $group_count++;
               $applied_discountids[$id]=1;
            }
         }
      }
   } elseif ($event['event_properties']['rsvp_discount']) {
      $discount = eme_get_discount_by_name($event['event_properties']['rsvp_discount']);
      $discount_id=$discount['id'];
      if ($res=eme_calc_booking_discount($discount,$booking))
         $total_discount=$res;
   }

   if ($total_discount>0 && $do_update) {
      global $wpdb;
      $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
      $where = array();
      $fields = array();
      $where['booking_id'] = $booking['booking_id'];
      $fields['discount'] = $total_discount;
      $fields['discountid'] = $discount_id;
      $fields['dgroupid'] = $discountgroup_id;
      $wpdb->update($bookings_table, $fields, $where);
   }
   return $total_discount;
}

function eme_get_discounts() {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = "SELECT * FROM $table";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_dgroups() {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTGROUPS_TBNAME;
   $sql = "SELECT * FROM $table";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_discount($id) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $table WHERE id = %d",$id);
   return $wpdb->query($sql);
}

function eme_delete_dgroup($id) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTGROUPS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $table WHERE id = %d",$id);
   return $wpdb->query($sql);
}

function eme_get_discountgroup($id) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTGROUPS_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %s",$id);
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_get_discountgroup_by_name($name) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTGROUPS_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $table WHERE name = %s",$name);
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_get_discountids_by_groupname($name) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = $wpdb->prepare("SELECT id FROM $table WHERE dgroup = %s",$name);
   return $wpdb->get_col($sql);
}

function eme_increase_discount_count($id) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = $wpdb->prepare("UPDATE $table SET count=count+1 WHERE id = %d",$id);
   return $wpdb->query($sql);
}

function eme_get_discount($id) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %d",$id);
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_get_discount_by_name($name) {
   global $wpdb;
   $table = $wpdb->prefix.DISCOUNTS_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $table WHERE name = %s",$name);
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_calc_booking_discount($discount,$booking) {
   // check if not expired
   if ($discount['expire']) {
      global $eme_timezone;
      $eme_date_obj_now=new ExpressiveDate(null,$eme_timezone);
      $eme_expire_obj = new ExpressiveDate($discount['expire'],$eme_timezone);
      if ($eme_expire_obj->lessThan($eme_date_obj_now))
         return false;
   }

   // check if not max usage count reached
   if ($discount['maxcount']>0 && $discount['count']>=$discount['maxcount'])
      return false;

   $event_id=$booking['event_id'];

   $res=0;
   // discount type=code: via own filters, based on the discount name
   if ($discount['type']==3 && has_filter('eme_discount_'.$discount['name'])) {
         $res=apply_filters('eme_discount_'.$discount['name'],$booking);
         if ($res)
            eme_increase_discount_count($discount['id']);
         return sprintf("%01.2f",$res);
   }

   if (isset($_POST['bookings'][$event_id])) {
      foreach($_POST['bookings'][$event_id] as $key =>$value) {
         if (preg_match('/^DISCOUNT/', $key, $matches)) {
            $res=eme_calc_discount($discount,$booking,$value);
            if ($res) break;
         }
      }
   }

   // if the discount matches, increase the usage count
   if ($res) {
      eme_increase_discount_count($discount['id']);
      return $res;
   }
   return 0;
}

function eme_calc_discount($discount,$booking,$coupon) {
   $res=0;
   if  (($discount['strcase'] && strcmp($discount['coupon'],$coupon)===0) ||
       (!$discount['strcase'] && strcasecmp($discount['coupon'],$coupon)===0)) {
      if ($discount['type']==1) {
         $res=$discount['value'];
      } elseif ($discount['type']==2) {
         // eme_get_total_booking_price by default takes the discount into account
         // not that it matters, as for now the discount is 0, but let's make sure
         $ignore_discount=1;
         $price=eme_get_total_booking_price($booking,$ignore_discount);
         $res=sprintf("%01.2f",$price*$discount['value']/100);
      } elseif ($discount['type']==4) {
         $res=$discount['value']*$booking['booking_seats'];
      }
   }
   return $res;
}

function eme_discounttype_to_text($type) {
   switch($type) {
      case 1:
         return __('Fixed','events-made-easy');break;
      case 2:
         return __('Percentage','events-made-easy');break;
      case 3:
         return __('Code','events-made-easy');break;
      case 4:
         return __('Fixed per seat','events-made-easy');break;
   }
}

add_action( 'wp_ajax_eme_discount_list', 'eme_ajax_discount_list' );
add_action( 'wp_ajax_eme_manage_discounts', 'eme_ajax_manage_discounts' );
add_action( 'wp_ajax_eme_discount_edit', 'eme_ajax_discount_edit' );
add_action( 'wp_ajax_eme_discountgroups_list', 'eme_ajax_discountgroups_list' );
add_action( 'wp_ajax_eme_manage_discountgroups', 'eme_ajax_manage_discountgroups' );
add_action( 'wp_ajax_eme_discountgroups_edit', 'eme_ajax_discountgroups_edit' );

function eme_ajax_discount_list() {
   eme_ajax_record_list(DISCOUNTS_TBNAME, 'eme_cap_discounts');
}
function eme_ajax_discountgroups_list() {
   eme_ajax_record_list(DISCOUNTGROUPS_TBNAME, 'eme_cap_discounts');
}
function eme_ajax_manage_discounts() {
   check_ajax_referer('eme_discounts','eme_admin_nonce');
   if (isset($_REQUEST['do_action'])) {
     $do_action=eme_sanitize_request($_REQUEST['do_action']);
     switch ($do_action) {
         case 'deleteDiscounts':
              eme_ajax_record_delete(DISCOUNTS_TBNAME, 'eme_cap_discounts', 'id');
              break;
      }
   }
   wp_die();
}
function eme_ajax_manage_discountgroups() {
   check_ajax_referer('eme_discounts','eme_admin_nonce');
   if (isset($_REQUEST['do_action'])) {
     $do_action=eme_sanitize_request($_REQUEST['do_action']);
     switch ($do_action) {
         case 'deleteDiscountGroups':
              eme_ajax_record_delete(DISCOUNTGROUPS_TBNAME, 'eme_cap_discounts', 'id');
              break;
      }
   }
   wp_die();
}
function eme_ajax_discount_edit() {
   if (isset($_POST['id'])) {
      $discount=eme_get_discount(intval($_POST['id']));
      $update=1;
   } else {
      $discount=eme_new_discount();
      $update=0;
   }
   foreach ($discount as $key=>$val) {
      if (isset($_POST[$key]))
         $discount[$key]=eme_sanitize_request(eme_strip_tags($_POST[$key]));
   }
   // unchecked checkboxes don't get sent in forms
   if (!isset($_POST['strcase']))
      $discount['strcase']=0;
   if (!is_numeric($discount['type']) || $discount['type']<0 || $discount['type']>4)
      $discount['type']=1;
   if (!is_numeric($discount['strcase']) || $discount['strcase']<0 || $discount['strcase']>1)
      $discount['strcase']=1;
   if (empty($discount['expire']) || $discount['expire']=="0000-00-00")
      $discount['expire']=NULL;

   eme_ajax_record_edit(DISCOUNTS_TBNAME,'eme_cap_discounts','id',$discount,'eme_get_discount',$update);
}

function eme_ajax_discountgroups_edit() {
   if (isset($_POST['id'])) {
      $discountgroup=eme_get_discountgroup(intval($_POST['id']));
      $update=1;
   } else {
      $discountgroup=eme_new_discountgroup();
      $update=0;
   }
   foreach ($discountgroup as $key=>$val) {
      if (isset($_POST[$key]))
         $discountgroup[$key]=eme_sanitize_request(eme_strip_tags($_POST[$key]));
   }

   eme_ajax_record_edit(DISCOUNTGROUPS_TBNAME,'eme_cap_discounts','id',$discountgroup,'eme_get_discountgroup',$update);
}

function eme_ajax_record_list($tablename, $cap, $condition="") {
   global $wpdb;
   $table = $wpdb->prefix.$tablename;
   $jTableResult = array();
   // The toolbar search input
   $q = isset($_REQUEST['q'])?$_REQUEST['q']:"";
   $opt = isset($_REQUEST['opt'])?$_REQUEST['opt']:"";
   $where ='';
   if ($q) {
	for ($i = 0; $i < count($opt); $i++) {
		$fld = esc_sql($opt[$i]);
		$where[] = $fld." like '%".esc_sql($q[$i])."%'";
	}
	$where = " WHERE ".implode(" AND ",$where);
   }
   // the $condition param can override the search params
   if (!empty($condition)) {
      $where = $condition;
   }
   if (current_user_can( get_option($cap))) {
      $sql = "SELECT COUNT(*) FROM $table $where";
      $recordCount = $wpdb->get_var($sql);
      $start=intval($_REQUEST["jtStartIndex"]);
      $pagesize=intval($_REQUEST["jtPageSize"]);
      $sorting=esc_sql($_REQUEST["jtSorting"]);
      $sql="SELECT * FROM $table $where ORDER BY $sorting LIMIT $start,$pagesize";
      $rows=$wpdb->get_results($sql);
      $jTableResult['Result'] = "OK";
      $jTableResult['TotalRecordCount'] = $recordCount;
      $jTableResult['Records'] = $rows;
   } else {
      $jTableResult['Result'] = "Error";
      $jTableResult['Message'] = __('Access denied!','events-made-easy');
   }
   print json_encode($jTableResult);
   wp_die();
}

function eme_ajax_record_delete($tablename,$cap,$postvar) {
   global $wpdb;
   $table = $wpdb->prefix.$tablename;
   $jTableResult = array();

   if (current_user_can(get_option($cap)) && isset($_POST[$postvar])) {
      // check the POST var
      $ids_arr=explode(',',$_POST[$postvar]);
      if (eme_array_integers($ids_arr)) {
         $wpdb->query("DELETE FROM $table WHERE $postvar in ( ".$_POST[$postvar].")");
      }
      $jTableResult['Result'] = "OK";
   } else {
      $jTableResult['Result'] = "Error";
      $jTableResult['Message'] = __('Access denied!','events-made-easy');
   }
   print json_encode($jTableResult);
   wp_die();
}

function eme_ajax_record_edit($tablename,$cap,$id_column,$record,$record_function='',$update=0) {
   global $wpdb;
   $table = $wpdb->prefix.$tablename;
   $jTableResult = array();
   if (!$record) {
      $jTableResult['Result'] = "Error";
      $jTableResult['Message'] = "No such record";
      print json_encode($jTableResult);
      wp_die();
   }
   $wpdb->show_errors(false);
   $record=eme_esc_html($record);
   if (current_user_can( get_option($cap))) {
      if ($update)
         $wpdb->update($table,$record,array($id_column => $record[$id_column]));
      else
         $wpdb->insert($table,$record);
      if ($wpdb->last_error !== '') {
         $jTableResult['Result'] = "Error";
         if ($update)
            $jTableResult['Message'] = __('Update failed: ','events-made-easy').$wpdb->last_error;
         else
            $jTableResult['Message'] = __('Insert failed: ','events-made-easy').$wpdb->last_error;
      } else {
         $jTableResult['Result'] = "OK";
         if (!$update) {
            $record_id = $wpdb->insert_id;
            if ($record_function)
               $record=$record_function($record_id);
            else
               $record[$id_column]=$record_id;
            $jTableResult['Record'] = $record;
         }
      }
   } else {
      $jTableResult['Result'] = "Error";
      $jTableResult['Message'] = __('Access denied!','events-made-easy');
   }

   //Return result to jTable
   print json_encode($jTableResult);
   wp_die();
}

?>
