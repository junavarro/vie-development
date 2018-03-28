<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function eme_new_person() {
   $person = array(
   'lastname' => '',
   'firstname' => '',
   'email' => 1,
   'phone' => 0,
   'address1' => '',
   'address2' => '',
   'city' => '',
   'state' => 1,
   'zip' => 0,
   'country' => 0
   );
   return $person;
}

function eme_people_page() {
   $message="";
   if (!current_user_can( get_option('eme_cap_people')) && isset($_POST['eme_admin_action'])) {
      $message = __('You have no right to update people!','events-made-easy');

   } elseif (isset($_POST['eme_admin_action']) && $_POST['eme_admin_action'] == 'deleteunusedpeople') {
      eme_delete_persons_without_bookings();
      $message = __("People without bookings have been deleted.",'events-made-easy');
   }

   eme_people_table($message);
}

function eme_global_map_json($eventful = false, $scope = "all", $category = '', $map_id, $offset = 0) {
   $eventful = ($eventful==="true" || $eventful==="1") ? true : $eventful;
   $eventful = ($eventful==="false" || $eventful==="0") ? false : $eventful;

   $locations = eme_get_locations((bool)$eventful,$scope,$category,$offset);
   $json_locations = array();
   foreach($locations as $location) {
      $json_location = array();

      # first we set the balloon info
      $tmp_loc=eme_replace_locations_placeholders(get_option('eme_location_baloon_format'), $location);
      # no newlines allowed, otherwise no map is shown
      $tmp_loc=eme_nl2br($tmp_loc);
      $json_location[] = '"location_balloon":"'.eme_trans_esc_html($tmp_loc).'"';

      # second, we fill in the rest of the info
      foreach($location as $key => $value) {
         # we skip some keys, since json is limited in size we only return what's needed in the javascript
         if (preg_match('/location_balloon|location_id|location_latitude|location_longitude/', $key)) {
            # no newlines allowed, otherwise no map is shown
            $value=eme_nl2br($value);
            $json_location[] = '"'.$key.'":"'.eme_trans_esc_html($value).'"';
         }
      }
      $json_locations[] = "{".implode(",",$json_location)."}";
   }

   $zoom_factor=get_option('eme_global_zoom_factor');
   $maptype=get_option('eme_global_maptype');
   if ($zoom_factor >14) $zoom_factor=14;

   $json = '{"locations":[';
   $json .= implode(",", $json_locations); 
   $json .= '],"enable_zooming":"';
   $json .= get_option('eme_gmap_zooming') ? 'true' : 'false';
   $json .= '","zoom_factor":"' ;
   $json .= $zoom_factor;
   $json .= '","maptype":"' ;
   $json .= $maptype;
   $json .= '","map_id":"' ;
   $json .= $map_id;
   $json .= '"}' ;
   echo $json;
}

// a eme_fputcsv function to replace the original fputcsv
// reason: we want to enclose all fields with $enclosure
function eme_fputcsv ($fh, $fields, $delimiter = ';', $enclosure = '"', $mysql_null = false) {
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $output = array();
    foreach ($fields as $field) {
        if ($field === null && $mysql_null) {
            $output[] = 'NULL';
            continue;
        }

        $output[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s|\r|\t|\n)/", $field) ? (
            $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure
        ) : $enclosure . $field . $enclosure;
    }

    fwrite($fh, join($delimiter, $output) . "\n");
}

function eme_csv_booking_report($event_id) {
   global $eme_timezone;
   $event = eme_get_event($event_id);
   $is_multiprice = eme_is_multi($event['price']);
   $current_userid=get_current_user_id();
   if (!(current_user_can( get_option('eme_cap_edit_events')) || current_user_can( get_option('eme_cap_list_events')) ||
        (current_user_can( get_option('eme_cap_author_event')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid)))) {
        echo "No access";
        die;
   }

   $delimiter = get_option('eme_csv_separator');
    if (empty($delimiter))
      $delimiter = ',';

   header("Content-type: application/octet-stream");
   header("Content-Disposition: attachment; filename=\"export.csv\"");
   $bookings =  eme_get_bookings_for($event_id);
   $answer_columns = eme_get_answer_fieldids(eme_get_bookingids_for($event_id));
   $out = fopen('php://output', 'w');
   if (has_filter('eme_csv_header_filter')) {
      $line=apply_filters('eme_csv_header_filter',$event);
      eme_fputcsv($out,$line,$delimiter);
   }
   $line=array();
   $line[]=__('ID', 'events-made-easy');
   $line[]=__('Last Name', 'events-made-easy');
   $line[]=__('First Name', 'events-made-easy');
   $line[]=__('Address1', 'events-made-easy');
   $line[]=__('Address2', 'events-made-easy');
   $line[]=__('City', 'events-made-easy');
   $line[]=__('State', 'events-made-easy');
   $line[]=__('Zip', 'events-made-easy');
   $line[]=__('Country', 'events-made-easy');
   $line[]=__('E-mail', 'events-made-easy');
   $line[]=__('Phone number', 'events-made-easy');
   if ($is_multiprice)
      $line[]=__('Seats (Multiprice)', 'events-made-easy');
   else
      $line[]=__('Seats', 'events-made-easy');
   $line[]=__('Paid', 'events-made-easy');
   $line[]=__('Booking date','events-made-easy');
   $line[]=__('Discount','events-made-easy');
   $line[]=__('Total price','events-made-easy');
   $line[]=__('Unique nbr','events-made-easy');
   $line[]=__('Comment', 'events-made-easy');
   foreach($answer_columns as $col) {
      $tmp_formfield=eme_get_formfield_byid($col['field_id']);
      $line[]=$tmp_formfield['field_name'];
   }
   $line_nbr=1;
   if (has_filter('eme_csv_column_filter'))
      $line=apply_filters('eme_csv_column_filter',$line,$event,$line_nbr);

   eme_fputcsv($out,$line,$delimiter);
   foreach($bookings as $booking) {
      $localized_booking_date = eme_localized_date($booking['creation_date']." ".$eme_timezone);
      $localized_booking_time = eme_localized_time($booking['creation_date']." ".$eme_timezone);
      $person = eme_get_person ($booking['person_id']);
      $line=array();
      $pending_string="";
      if ($booking['waitinglist']) {
         $pending_string=__('(waiting list)','events-made-easy');
      } elseif (eme_event_needs_approval($event_id) && !$booking['booking_approved']) {
         $pending_string=__('(pending)','events-made-easy');
      }
      $line[]=$booking['booking_id'];
      $line[]=$person['lastname'];
      $line[]=$person['firstname'];
      $line[]=$person['address1'];
      $line[]=$person['address2'];
      $line[]=$person['city'];
      $line[]=$person['state'];
      $line[]=$person['zip'];
      $line[]=$person['country'];
      $line[]=$person['email'];
      $line[]=$person['phone'];
      if ($is_multiprice) {
         // in cases where the event switched to multiprice, but somebody already registered while it was still single price: booking_seats_mp is then empty
         if ($booking['booking_seats_mp'] == "")
            $booking['booking_seats_mp']=$booking['booking_seats'];
         $line[]=$booking['booking_seats']." (".$booking['booking_seats_mp'].") ".$pending_string;
      } else {
         $line[]=$booking['booking_seats']." ".$pending_string;
      }
      $line[]=$booking['booking_paid']? __('Yes', 'events-made-easy'): __('No', 'events-made-easy');
      $line[]=$localized_booking_date." ".$localized_booking_time;
      $discount_name="";
      if ($booking['dgroupid']) {
         $dgroup=eme_get_discountgroup($booking['dgroupid']);
         if ($dgroup && isset($dgroup['name']))
		 $discount_name='('.$dgroup['name'].')';
         else
		 $discount_name='('.__('Applied discount no longer exists','events-made-easy').')';
      } elseif ($booking['discountid']) {
         $discount=eme_get_discount($booking['discountid']);
         if ($discount && isset($discount['name']))
		 $discount_name='('.$discount['name'].')';
         else
		 $discount_name='('.__('Applied discount no longer exists','events-made-easy').')';
      }
      $line[]=eme_localized_price($booking['discount'],$event['currency'],"text").$discount_name;
      $line[]=eme_localized_price(eme_get_total_booking_price($booking),$event['currency'],"text");
      $line[]=$booking['transfer_nbr_be97'];
      $line[]=$booking['booking_comment'];
      $answers = eme_get_answers($booking['booking_id']);
      foreach($answer_columns as $col) {
         $found=0;
         foreach ($answers as $answer) {
            if ($answer['field_id'] == $col['field_id']) {
               $line[]=eme_convert_answer2tag($answer);
               $found=1;
               break;
            }
         }
         # to make sure the number of columns are correct, we add an empty answer if none was found
         if (!$found)
            $line[]="";
      }
      $line_nbr++;
      if (has_filter('eme_csv_column_filter'))
	      $line=apply_filters('eme_csv_column_filter',$line,$event,$line_nbr);
      eme_fputcsv($out,$line,$delimiter);
   }
   if (has_filter('eme_csv_footer_filter')) {
      $line=apply_filters('eme_csv_footer_filter',$event);
      eme_fputcsv($out,$line,$delimiter);
   }
   fclose($out);
   die();
}

function eme_printable_booking_report($event_id) {
   global $eme_timezone;
   $event = eme_get_event($event_id);
   $current_userid=get_current_user_id();
   if (!(current_user_can( get_option('eme_cap_edit_events')) || current_user_can( get_option('eme_cap_list_events')) ||
        (current_user_can( get_option('eme_cap_author_event')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid)))) {
        echo "No access";
        die;
   }

   $is_multiprice = eme_is_multi($event['price']);
   $is_multiseat = eme_is_multi($event['event_seats']);
   $bookings = eme_get_bookings_for($event_id);
   $answer_columns = eme_get_answer_fieldids(eme_get_bookingids_for($event_id));
   $available_seats = eme_get_available_seats($event_id);
   $booked_seats = eme_get_booked_seats($event_id);
   $pending_seats = eme_get_pending_seats($event_id);
   if ($is_multiseat) {
      $available_seats_ms=eme_convert_array2multi(eme_get_available_multiseats($event_id));
      $booked_seats_ms=eme_convert_array2multi(eme_get_booked_multiseats($event_id));
      $pending_seats_ms=eme_convert_array2multi(eme_get_pending_multiseats($event_id));
   }

   $stylesheet = EME_PLUGIN_URL."events_manager.css";
   ?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
      <html>
      <head>
         <meta http-equiv="Content-type" content="text/html; charset=utf-8">
         <title><?php echo __('Bookings for', 'events-made-easy')." ".eme_trans_esc_html($event['event_name']);?></title>
          <link rel="stylesheet" href="<?php echo $stylesheet; ?>" type="text/css" media="screen" />
          <?php
            $file_name= get_stylesheet_directory()."/eme.css";
            if (file_exists($file_name))
               echo "<link rel='stylesheet' href='".get_stylesheet_directory_uri()."/eme.css' type='text/css' media='screen' />\n";
            $file_name= get_stylesheet_directory()."/eme_print.css";
            if (file_exists($file_name))
               echo "<link rel='stylesheet' href='".get_stylesheet_directory_uri()."/eme_print.css' type='text/css' media='print' />\n";
          ?>
      </head>
      <body id="eme_printable_body">
         <div id="eme_printable_container">
         <h1><?php echo __('Bookings for', 'events-made-easy')." ".eme_trans_esc_html($event['event_name']);?></h1> 
         <p><?php echo eme_localized_date($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone); ?></p>
         <p><?php if ($event['location_id']) echo eme_replace_event_location_placeholders("#_LOCATIONNAME, #_ADDRESS, #_TOWN", $event); ?></p>
         <?php if ($event['price']) ?>
            <p><?php _e ( 'Price: ','events-made-easy'); echo eme_replace_placeholders("#_PRICE", $event); ?></p>
         <h1><?php _e('Bookings data', 'events-made-easy');?></h1>
         <table id="eme_printable_table">
            <tr>
               <th scope='col' class='eme_print_id'><?php _e('ID', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_name'><?php _e('Last Name', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_name'><?php _e('First Name', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_email'><?php _e('E-mail', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_phone'><?php _e('Phone number', 'events-made-easy'); ?></th> 
               <th scope='col' class='eme_print_seats'><?php if ($is_multiprice) _e('Seats (Multiprice)', 'events-made-easy'); else _e('Seats', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_paid'><?php _e('Paid', 'events-made-easy')?></th>
               <th scope='col' class='eme_print_booking_date'><?php _e('Booking date', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_discount'><?php _e('Discount', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_total_price'><?php _e('Total price', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_unique_nbr'><?php _e('Unique nbr', 'events-made-easy'); ?></th>
               <th scope='col' class='eme_print_comment'><?php _e('Comment', 'events-made-easy'); ?></th> 
            <?php
            $nbr_columns=11;
            foreach($answer_columns as $col) {
               $class="eme_print_formfield".$col['field_id'];
               $tmp_formfield=eme_get_formfield_byid($col['field_id']);
               print "<th scope='col' class='$class'>".$tmp_formfield['field_name']."</th>";
               $nbr_columns++;
            }
            ?>
            </tr>
            <?php
            foreach($bookings as $booking) {
               $localized_booking_date = eme_localized_date($booking['creation_date']." ".$eme_timezone);
               $localized_booking_time = eme_localized_time($booking['creation_date']." ".$eme_timezone);
               $person = eme_get_person ($booking['person_id']);
               $pending_string="";
               if ($booking['waitinglist']) {
                  $pending_string=__('(waiting list)','events-made-easy');
	       } elseif (eme_event_needs_approval($event_id) && !$booking['booking_approved']) {
                  $pending_string=__('(pending)','events-made-easy');
               }
                ?>
            <tr>
               <td class='eme_print_id'><?php echo $booking['booking_id']?></td> 
               <td class='eme_print_name'><?php echo $person['lastname']?></td> 
               <td class='eme_print_name'><?php echo $person['firstname']?></td> 
               <td class='eme_print_email'><?php echo $person['email']?></td>
               <td class='eme_print_phone'><?php echo $person['phone']?></td>
               <td class='eme_print_seats' class='seats-number'><?php 
               if ($is_multiprice) {
                  // in cases where the event switched to multiprice, but somebody already registered while it was still single price: booking_seats_mp is then empty
                  if ($booking['booking_seats_mp'] == "")
                     $booking['booking_seats_mp']=$booking['booking_seats'];
                  echo $booking['booking_seats']." (".$booking['booking_seats_mp'].") ".$pending_string;
               } else {
                  echo $booking['booking_seats']." ".$pending_string;
               }
               ?>
               </td>
               <td class='eme_print_paid'><?php if ($booking['booking_paid']) _e('Yes', 'events-made-easy'); else _e('No', 'events-made-easy'); ?></td>
               <td class='eme_print_booking_date'><?php echo $localized_booking_date." ".$localized_booking_time; ?></td>
               <td class='eme_print_discount'><?php
	       $discount_name="";
	       if ($booking['dgroupid']) {
		       $dgroup=eme_get_discountgroup($booking['dgroupid']);
		       if ($dgroup && isset($dgroup['name']))
			       $discount_name='<br />'.$dgroup['name'];
		       else
			       $discount_name='<br />'.__('Applied discount no longer exists','events-made-easy');
	       } elseif ($booking['discountid']) {
		       $discount=eme_get_discount($booking['discountid']);
		       if ($discount && isset($discount['name']))
			       $discount_name='<br />'.$discount['name'];
		       else
			       $discount_name='<br />'.__('Applied discount no longer exists','events-made-easy');
	       }
               echo eme_localized_price($booking['discount'],$event['currency']).$discount_name; ?>
               </td>
               <td class='eme_print_total_price'><?php echo eme_localized_price(eme_get_total_booking_price($booking),$event['currency']); ?></td>
               <td class='eme_print_unique_nbr'><?php echo $booking['transfer_nbr_be97']; ?></td>
               <td class='eme_print_comment'><?=$booking['booking_comment'] ?></td> 
               <?php
                  $answers = eme_get_answers($booking['booking_id']);
                  foreach($answer_columns as $col) {
                     $found=0;
                     foreach ($answers as $answer) {
                        if ($answer['field_id'] == $col['field_id']) {
                           $class="eme_print_formfield".$answer['field_id'];
                           print "<td class='$class'>".eme_esc_html(eme_convert_answer2tag($answer))."</td>";
                           $found=1;
                           break;
                        }
                     }
                     # to make sure the number of columns are correct, we add an empty answer if none was found
                     if (!$found)
                        print "<td class='$class'>&nbsp;</td>";
                  }
               ?>
            </tr>
               <?php } ?>
            <tr id='eme_printable_booked-seats'>
               <td colspan='<?php echo $nbr_columns-4;?>'>&nbsp;</td>
               <td class='total-label'><?php _e('Booked', 'events-made-easy')?>:</td>
               <td colspan='3' class='seats-number'><?php
               print $booked_seats;
               if ($is_multiseat) print " ($booked_seats_ms)";
			      if ($pending_seats>0) {
                  if ($is_multiseat)
                     print " ".sprintf( __('(%d pending)','events-made-easy'), $pending_seats) . " ($pending_seats_ms)";
                  else
                     print " ".sprintf( __('(%d pending)','events-made-easy'), $pending_seats);
               }
               ?>
            </td>
            </tr>
            <tr id='eme_printable_available-seats'>
               <td colspan='<?php echo $nbr_columns-4;?>'>&nbsp;</td>
               <td class='total-label'><?php _e('Available', 'events-made-easy')?>:</td>
               <td colspan='3' class='seats-number'><?php print $available_seats; if ($is_multiseat) print " ($available_seats_ms)"; ?></td>
            </tr>
         </table>
         </div>
      </body>
      </html>
      <?php
      die();
} 

function eme_people_table($message="") {
   $destination = admin_url("admin.php?page=eme-people");
   $nonce_field = wp_nonce_field('eme_people','eme_admin_nonce',false,false);

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

         <h1><?php _e('People', 'events-made-easy') ?></h1>

   <form id='people-deleteunused' method='post' action='<?php print $destination; ?>'>
   <p><?php _e('If you want, click this button to delete all people that have no bookings registered in the system anymore', 'events-made-easy'); ?> </p> 
   <input type="hidden" name="eme_admin_action" value="deleteunusedpeople" />
   <input type="submit" value="<?php _e ( 'Delete people without bookings','events-made-easy'); ?>" name="doaction" id="doaction" class="button-primary action" />
   </form>

   <p><?php _e('This table shows the data about the people who responded to your events', 'events-made-easy'); ?> </p> 
   <form action="#" method="post">
   <input type="text" name="search_name" id="search_name" size=10>
   <button id="PeopleLoadRecordsButton" class="button-secondary action"><?php _e('Filter people','events-made-easy'); ?></button>
   </form>

   <form action="#" method="post">
   <?php echo $nonce_field; ?>
   <select id="eme_admin_action" name="eme_admin_action">
   <option value="" selected="selected"><?php _e ( 'Bulk Actions' , 'events-made-easy'); ?></option>
   <option value="deletePeople"><?php _e ( 'Delete selected persons','events-made-easy'); ?></option>
   </select>
   <span id="span_transferto">
   <?php _e('Transfer associated bookings to (leave empty for deleting those too):','events-made-easy'); ?>
   <input type='hidden' id='transferto_id' name='transferto_id'>
   <input type='text' id='chooseperson' name='chooseperson' class="clearable" placeholder="<?php _e('Start typing a name','events-made-easy'); ?>">
   </span>
   <button id="PeopleActionsButton" class="button-secondary action"><?php _e ( 'Apply' , 'events-made-easy'); ?></button>
   <span class="rightclickhint">
      <?php _e('Hint: rightclick on the column headers to show/hide columns','events-made-easy'); ?>
   </span>
   </form>
         <div id="PeopleTableContainer" style="width=98%;"></div>
      </div>
   </div>
<?php
}

// API function for people wanting to check if somebody is already registered
function eme_get_person_by_post() {
   $booker=array();
   if (isset($_POST['lastname']) && isset($_POST['email'])) {
      $bookerLastName = eme_strip_tags($_POST['lastname']);
      if (isset($_POST['firstname']))
         $bookerFirstName = eme_strip_tags($_POST['firstname']);
      else
         $bookerFirstName = "";
      $bookerEmail = eme_strip_tags($_POST['email']);
      $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail);
   }
   return $booker;
}

function eme_get_person_by_name_and_email($lastname, $firstname, $email) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   if (!empty($firstname))
      $sql = $wpdb->prepare("SELECT * FROM $people_table WHERE lastname = %s AND firstname = %s AND email = %s",$lastname,$firstname,$email);
   else
      $sql = $wpdb->prepare("SELECT * FROM $people_table WHERE lastname = %s AND email = %s",$lastname,$email);
   $result = $wpdb->get_row($sql, ARRAY_A);
   return $result;
}

function eme_get_person_by_wp_id($wp_id) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $people_table WHERE wp_id = %d",$wp_id);
   $result = $wpdb->get_row($sql, ARRAY_A);
   if (!is_null($result['wp_id']) && $result['wp_id']) {
      $user_info = get_userdata($result['wp_id']);
      $result['lastname']=$user_info->user_lastname;
      if (empty($result['lastname']))
         $result['lastname']=$user_info->display_name;
      $result['firstname']=$user_info->user_firstname;
      $result['email']=$user_info->user_email;
   }
   return $result;
}

function eme_delete_person($person_id) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   $sql = "DELETE FROM $people_table WHERE person_id = '".intval($person_id)."'";
   $wpdb->query($sql);
   return 1;
}

function eme_delete_persons_without_bookings() {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   // first, clean up unreferenced bookings
   $sql = "DELETE FROM $bookings_table WHERE event_id NOT IN (SELECT DISTINCT event_id FROM $events_table)";
   $wpdb->query($sql);
   // now, delete unreferenced persons
   $sql = "DELETE FROM $people_table WHERE person_id NOT IN (SELECT DISTINCT person_id FROM $bookings_table)";
   $wpdb->query($sql);
   return 1;
}

function eme_get_person($person_id) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $people_table WHERE person_id = %d",$person_id);
   $result = $wpdb->get_row($sql, ARRAY_A);
   return $result;
}

function eme_get_persons($person_ids="",$not_person_ids="",$extra_search="",$wp_info_in_lastname=1) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   if (!empty($person_ids) && eme_array_integers($person_ids)) {
      $tmp_ids=join(",",$person_ids);
      $sql = "SELECT * FROM $people_table WHERE person_id IN ($tmp_ids)";
      if (!empty($extra_search)) $sql.=" AND $extra_search";
   } elseif (!empty($not_person_ids) && eme_array_integers($not_person_ids)) {
      $tmp_ids=join(",",$not_person_ids);
      $sql = "SELECT * FROM $people_table WHERE person_id NOT IN ($tmp_ids)" ;
      if (!empty($extra_search)) $sql.=" AND $extra_search";
   } else {
      $sql = "SELECT * FROM $people_table";
      if (!empty($extra_search)) $sql.=" WHERE $extra_search";
   }
   $lines = $wpdb->get_results($sql, ARRAY_A);
   $result = array();
   foreach ($lines as $line) {
      # to be able to sort on person names, we need a hash starting with the name
      # but some people might have the same name (or register more than once),
      # so we add the ID to it
      $unique_id=$line['lastname']."_".$line['firstname']."_".$line['person_id'];
      $result[$unique_id]=$line;
   }
   # now do the sorting
   ksort($result);
   return $result;
}

function eme_add_person($lastname, $firstname, $email, $wp_id) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;
   $person=array();
   $person['lastname'] = eme_strip_tags($lastname);
   $person['firstname'] = eme_strip_tags($firstname);
   $person['email'] = eme_strip_tags($email);
   if (isset($_POST['address1'])) $person['address1'] = eme_strip_tags($_POST['address1']);
   if (isset($_POST['address2'])) $person['address2'] = eme_strip_tags($_POST['address2']);
   if (isset($_POST['city'])) $person['city'] = eme_strip_tags($_POST['city']);
   if (isset($_POST['state'])) $person['state'] = eme_strip_tags($_POST['state']);
   if (isset($_POST['zip'])) $person['zip'] = eme_strip_tags($_POST['zip']);
   if (isset($_POST['country'])) $person['country'] = eme_strip_tags($_POST['country']);
   if (isset($_POST['phone'])) $person['phone'] = eme_strip_tags($_POST['phone']);
   $person['wp_id'] = intval($wp_id);
   $person['lang'] = eme_detect_lang();
   $wpdb->insert($people_table,$person);
   $person_id = $wpdb->insert_id;
   return eme_get_person($person_id);
}

function eme_update_person_with_postinfo($person_id,$basic_info_too=0) {
   global $wpdb; 
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;

   $where = array();
   $where['person_id'] = intval($person_id);
   $fields = array();
   if (isset($_POST['address1'])) $fields['address1'] = eme_strip_tags($_POST['address1']);
   if (isset($_POST['address2'])) $fields['address2'] = eme_strip_tags($_POST['address2']);
   if (isset($_POST['city'])) $fields['city'] = eme_strip_tags($_POST['city']);
   if (isset($_POST['state'])) $fields['state'] = eme_strip_tags($_POST['state']);
   if (isset($_POST['zip'])) $fields['zip'] = eme_strip_tags($_POST['zip']);
   if (isset($_POST['country'])) $fields['country'] = eme_strip_tags($_POST['country']);
   if (isset($_POST['phone'])) $fields['phone'] = eme_strip_tags($_POST['phone']);
   if ($basic_info_too) {
      $fields['lastname'] = eme_strip_tags($_POST['lastname']);
      $fields['email'] = eme_strip_tags($_POST['email']);
      if (isset($_POST['firstname'])) $fields['firstname'] = eme_strip_tags($_POST['firstname']);
   }

   // take into account that $fields can be empty too (if $basic_info_too=0 and the other fields are not set)
   if (!empty($fields) && $wpdb->update($people_table, $fields, $where) === false)
      return false;
   else
      return eme_get_person($person_id);
}

function eme_user_profile($user) {
   //$eme_phone=get_user_meta($user,'eme_phone',true);
   $eme_phone=$user->eme_phone || '';
   // only show future bookings
   $future=1;
   // define a simple template
   $template="#_STARTDATE #_STARTTIME: #_EVENTNAME (#_RESPSPACES ".__('places','events-made-easy')."). #_CANCEL_LINK<br />";
   ?>
   <h3><?php _e('Events Made Easy settings', 'events-made-easy')?></h3>
   <table class='form-table'>
      <tr>
         <th><label for="eme_phone"><?php _e('Phone number','events-made-easy');?></label></th>
         <td><input type="text" name="eme_phone" id="eme_phone" value="<?php echo $eme_phone; ?>" class="regular-text" /> <br />
         <?php _e('The phone number used by Events Made Easy when the user is indicated as the contact person for an event.','events-made-easy');?></td>
      </tr>
      <tr>
         <th><label for="eme_phone"><?php _e('Future bookings made','events-made-easy');?></label></th>
	 <td><?php echo eme_get_bookings_list_for_wp_id($user->ID,$future,$template); ?>
      </tr>
   </table>
   <?php
}

function eme_update_user_profile($wp_user_ID) {
   if(isset($_POST['eme_phone'])) {
      update_user_meta($wp_user_ID,'eme_phone', $_POST['eme_phone']);
   }
}

function eme_get_indexed_users() {
   global $wpdb;
   $sql = "SELECT display_name, ID FROM $wpdb->users";
   $users = $wpdb->get_results($sql, ARRAY_A);
   $indexed_users = array();
   foreach($users as $user) 
      $indexed_users[$user['ID']] = $user['display_name'];
   return $indexed_users;
}

function eme_get_wp_users($search) {
	$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => 'first_name',
				'value'   => $search,
				'compare' => 'LIKE'
			     ),
			array(
				'key'     => 'last_name',
				'value'   => $search,
				'compare' => 'LIKE'
			     )
			);
	$args = array(
			'meta_query'   =>$meta_query,
			'orderby'      => 'ID',
			'order'        => 'ASC',
			'count_total'  => false,
			'fields'       => array('ID'),
		     );
	$users = get_users($args);
	return $users;
}

function eme_people_search_ajax($no_wp_die=0) {
   $return = array();
   if (isset($_REQUEST['q']))
      $q = strtolower($_REQUEST['q']);
   header("Content-type: application/json; charset=utf-8");
   if (!isset($_REQUEST['q']) || empty($q)) {
      echo json_encode($return);
      return;
   }

   $search_tables=get_option('eme_autocomplete_sources');
   if (isset($_REQUEST['eme_searchlimit']) && $_REQUEST['eme_searchlimit']=="people") {
      $search_tables="people";
   }
   if ($search_tables=='people' || $search_tables=='both') {
	   $search="lastname LIKE '%".esc_sql($q)."%' OR firstname LIKE '%".esc_sql($q)."%'";
           $wp_info_in_lastname=0;
	   $persons = eme_get_persons('','',$search,$wp_info_in_lastname);
	   foreach($persons as $item) {
		   $record = array();
		   $record['lastname']  = esc_html($item['lastname']); 
		   $record['firstname'] = esc_html($item['firstname']); 
		   $record['address1']  = esc_html($item['address1']); 
		   $record['address2']  = esc_html($item['address2']); 
		   $record['city']      = esc_html($item['city']); 
		   $record['state']     = esc_html($item['state']); 
		   $record['zip']       = esc_html($item['zip']); 
		   $record['country']   = esc_html($item['country']); 
		   $record['email']     = esc_html($item['email']);
		   $record['phone']     = esc_html($item['phone']); 
		   $record['person_id'] = esc_html($item['person_id']); 
		   $record['wp_id']     = esc_html($item['wp_id']); 
		   $return[]  = $record;
	   }
   }
   if ($search_tables=='wp_users' || $search_tables=='both') {
	   $persons = eme_get_wp_users($q);
	   foreach($persons as $item) {
		   $record = array();
		   $user_info = get_userdata($item->ID);
		   $record['lastname']  = esc_html($user_info->user_lastname);
		   if (empty($record['lastname']))
			   $record['lastname']=esc_html($user_info->display_name);
		   $record['firstname'] = esc_html($user_info->user_firstname);
		   $record['email']     = esc_html($user_info->user_email);
		   $record['address1']  = ''; 
		   $record['address2']  = ''; 
		   $record['city']      = ''; 
		   $record['state']     = ''; 
		   $record['zip']       = ''; 
		   $record['country']   = ''; 
		   $record['phone']     = ''; 
		   $record['wp_id']     = esc_html($item->ID); 
		   $return[]  = $record;
	   }
   }

   echo json_encode($return);
   if (!$no_wp_die)
      wp_die();
}

add_action( 'wp_ajax_eme_people_list', 'eme_ajax_people_list' );
add_action( 'wp_ajax_eme_manage_people', 'eme_ajax_manage_people' );
add_action( 'wp_ajax_eme_people_edit', 'eme_ajax_people_edit' );
add_action( 'wp_ajax_eme_autocomplete_people', 'eme_people_search_ajax' );

function eme_ajax_people_list() {
   $person_id = isset($_REQUEST['person_id']) ? intval($_REQUEST['person_id']) : 0;
   $search_name = isset($_REQUEST['search_name']) ? eme_sanitize_request($_REQUEST['search_name']) : '';
   $where ='';
   $where_arr = array();
   if(!empty($search_name)) {
      $where_arr[] = "(lastname like '%".$search_name."%' OR firstname like '%".$search_name."%' OR email like '%".$search_name."%')";
   }
   if ($person_id) {
      $where_arr[] = "person_id = $person_id";
   }
   if ($where_arr)
      $where = "WHERE ".implode(" AND ",$where_arr);
   eme_ajax_record_list(PEOPLE_TBNAME, 'eme_cap_people',$where);
}
function eme_ajax_manage_people() {
   check_ajax_referer('eme_people','eme_admin_nonce');
   if (isset($_POST['do_action'])) {
      $do_action=eme_sanitize_request($_POST['do_action']);
      switch ($do_action) {
         case 'deletePeople':
              $ids_arr=explode(',',$_POST['person_id']);
              if (eme_array_integers($ids_arr) && current_user_can( get_option('eme_cap_people'))) {
                 if (!empty($_POST['chooseperson']) && !empty($_POST['transferto_id'])) {
                    $to_person_id=intval($_POST['transferto_id']);
                    foreach ($ids_arr as $person_id) {
                       eme_transfer_all_bookings($person_id,$to_person_id);
                    }
                 } else {
                    foreach ($ids_arr as $person_id) {
                       eme_delete_all_bookings_for_person_id($person_id);
                    }
                 }
              }
              eme_ajax_record_delete(PEOPLE_TBNAME, 'eme_cap_people', 'person_id');
              break;
      }
   }
   wp_die();
}

function eme_ajax_people_edit() {
   if (isset($_POST['person_id'])) {
      $person=eme_get_person(intval($_POST['person_id']));
      $update=1;
   } else {
      $person=eme_new_person();
      $update=0;
   }
   foreach ($person as $key=>$val) {
      if (isset($_POST[$key]))
         $person[$key]=eme_sanitize_request(eme_strip_tags($_POST[$key]));
   }
   eme_ajax_record_edit(PEOPLE_TBNAME,'eme_cap_people','person_id',$person,'eme_get_person',$update);
}

?>
