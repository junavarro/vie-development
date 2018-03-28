<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


// add an action for the cronjob to map to the reminder function,
add_action('eme_cron_reminder_unpaid', 'eme_cron_reminder_unpaid_function');
function eme_cron_reminder_unpaid_function() {
   global $wpdb, $eme_timezone;

   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;

   $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
   $today = $eme_date_obj->getDate();
   $eme_number=intval(get_option('eme_cron_reminder_unpaid_minutes'));
   // the min is 5 minutes
   if ($eme_number<5) $eme_number=5;
   $sql = $wpdb->prepare("SELECT bookings.booking_id FROM $bookings_table as bookings LEFT JOIN $events_table as events ON bookings.event_id=events.event_id WHERE bookings.booking_paid = 0 AND bookings.booking_approved=0 AND UNIX_TIMESTAMP(bookings.creation_date)<UNIX_TIMESTAMP()-60*%d AND events.event_start_date > %s AND reminder<UNIX_TIMESTAMP()",$eme_number,$today);
   $booking_ids=$wpdb->get_col($sql);
   foreach ($booking_ids as $booking_id) {
       $booking=eme_get_booking($booking_id);
       $ret= eme_email_rsvp_booking($booking,"pendingReminder");
       if ($ret) {
          $sql = $wpdb->prepare("UPDATE $bookings_table SET reminder = UNIX_TIMESTAMP()+60*%d  WHERE booking_id=%d",$eme_number,$booking_id); 
          $wpdb->query($sql);
       }
   }
}

// add an action for the cronjob to map to the cleanup function,
add_action('eme_cron_cleanup_unpaid', 'eme_cron_cleanup_unpaid_function');
function eme_cron_cleanup_unpaid_function() {
   $eme_number=intval(get_option('eme_cron_cleanup_unpaid_minutes'));
   eme_cleanup_unpaid($eme_number);
}

function eme_cleanup_unpaid($eme_number) {
   global $wpdb, $eme_timezone;

   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;

   $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
   $today = $eme_date_obj->getDate();
   // the min is 5 minutes
   if ($eme_number<5) $eme_number=5;
   #$sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE booking_paid = 0 AND booking_approved=0 AND UNIX_TIMESTAMP(creation_date)<UNIX_TIMESTAMP()-60*%d",$eme_number);
   $sql = $wpdb->prepare("SELECT bookings.booking_id FROM $bookings_table as bookings LEFT JOIN $events_table as events ON bookings.event_id=events.event_id WHERE bookings.booking_paid = 0 AND bookings.booking_approved=0 AND events.event_start_date > %s AND UNIX_TIMESTAMP(bookings.creation_date)<UNIX_TIMESTAMP()-60*%d",$today,$eme_number);
   $booking_ids=$wpdb->get_col($sql);
   foreach ($booking_ids as $booking_id) {
       $booking=eme_get_booking($booking_id);
       eme_email_rsvp_booking($booking,"cancelRegistration");
       eme_delete_booking($booking_id);
       eme_delete_answers($booking_id);
   }
}

function eme_cleanup_events($eme_number,$eme_period) {
   global $wpdb, $eme_timezone;

   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $recurrence_table = $wpdb->prefix.RECURRENCE_TBNAME;

   if ($eme_number<1) $eme_number=1;
   $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
   switch ($eme_period) {
	   case 'day':
		   $eme_date_obj->minusDays($eme_number);
		   break;
	   case 'week':
		   $eme_date_obj->minusWeeks($eme_number);
		   break;
	   default:
		   $eme_date_obj->minusMonths($eme_number);
		   break;
   }
   $end_date=$eme_date_obj->getDate();
   $wpdb->query("DELETE FROM $bookings_table where event_id in (SELECT event_id from $events_table where event_end_date<'$end_date')");
   $wpdb->query("DELETE FROM $events_table where event_end_date<'$end_date'");
   $wpdb->query("DELETE FROM $recurrence_table where recurence_freq <> 'specific' AND recurrence_end_date<'$end_date'");
}

function eme_cleanup_all_event_related_data($other_data) {
   global $wpdb;

   $tables=array(EVENTS_TBNAME,BOOKINGS_TBNAME,LOCATIONS_TBNAME,RECURRENCE_TBNAME,ANSWERS_TBNAME,PAYMENTS_TBNAME,PEOPLE_TBNAME);
   if ($other_data) {
      $tables2=array(CATEGORIES_TBNAME,HOLIDAYS_TBNAME,TEMPLATES_TBNAME,FORMFIELDS_TBNAME);
      $tables = array_merge($tables,$tables2);
   }
   foreach ($tables as $table) {
      $wpdb->query("DELETE FROM ".$wpdb->prefix.$table);
   }
}

function eme_cleanup_page() {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;

   $message="";
   if (is_admin() && current_user_can( get_option('eme_cap_cleanup'))) {
      // do the actions if required
      if (isset($_POST['eme_admin_action'])) {
         check_admin_referer('eme_cleanup','eme_admin_nonce');
         if ($_POST['eme_admin_action'] == "eme_cleanup_events" && isset($_POST['eme_number']) && isset($_POST['eme_period'])) {
            $eme_number = intval($_POST['eme_number']);
            $eme_period = $_POST['eme_period'];
            if ( !in_array( $eme_period, array( 'day', 'week', 'month' ) ) ) 
               $eme_period = "month";

            if ($eme_number>1) {
		    eme_cleanup_events($eme_number,$eme_period);
		    $message = sprintf ( __ ( "Cleanup done: events (and corresponding booking data) older than %d %s(s) have been removed.",'events-made-easy'),$eme_number,$eme_period);
	    }
         } elseif ($_POST['eme_admin_action'] == "eme_cleanup_unpaid") {
            $eme_number = 0;
            if (isset($_POST['eme_number']))
               $eme_number = intval($_POST['eme_number']);
            if ($eme_number>=5) {
               eme_cleanup_unpaid($eme_number);
               $message = sprintf ( __ ( "Cleanup done: unpaid pending bookings older than %d minutes have been removed.",'events-made-easy'),$eme_number);
            }
         } elseif ($_POST['eme_admin_action'] == "eme_cron_reminder_unpaid") {
            $eme_cron_reminder_schedule = $_POST['eme_cron_reminder_schedule'];
            $eme_cron_reminder_unpaid_minutes = intval($_POST['eme_cron_reminder_unpaid_minutes']);
            $eme_cron_reminder_unpaid_subject = intval($_POST['eme_cron_reminder_unpaid_subject']);
            $eme_cron_reminder_unpaid_body = intval($_POST['eme_cron_reminder_unpaid_body']);
	    if (wp_next_scheduled('eme_cron_reminder_unpaid'))
		    wp_clear_scheduled_hook('eme_cron_reminder_unpaid');
	    if ($eme_cron_reminder_unpaid_minutes>=5) {
		    if ($eme_cron_reminder_unpaid_minutes != get_option('eme_cron_reminder_unpaid_minutes'))
			    $diff_found=1;
		    else
			    $diff_found=0;

		    if ($eme_cron_reminder_schedule) {
			    $schedules = wp_get_schedules();
			    if (isset($schedules[$eme_cron_reminder_schedule])) {
				    $schedule = $schedules[$eme_cron_reminder_schedule];
				    wp_schedule_event(time(), $eme_cron_reminder_schedule, 'eme_cron_reminder_unpaid');
				    update_option('eme_cron_reminder_unpaid_minutes',$eme_cron_reminder_unpaid_minutes);
				    update_option('eme_cron_reminder_unpaid_subject',$eme_cron_reminder_unpaid_subject);
				    update_option('eme_cron_reminder_unpaid_body',$eme_cron_reminder_unpaid_body);
				    $message = sprintf ( __ ( "A reminder mail will be sent to unpaid pending bookings every %d minutes. This will be checked for '%s'",'events-made-easy'),$eme_cron_reminder_unpaid_minutes,$schedule['display']);
				    if ($diff_found) {
					    $sql = "UPDATE $bookings_table SET reminder = 0";
					    $wpdb->query($sql);
					    $message .= "<br />".__("Since the new reminder setting is different than the old one in minutes, all old reminder info has been reset.");
				    }
			    }
		    } else {
			    $message = __ ( "No automatic reminder of unpaid pending bookings will be sent.",'events-made-easy');
		    }
            } else {
                  $message = __ ( "No automatic reminder of unpaid pending bookings will be sent.",'events-made-easy');
            }
          } elseif ($_POST['eme_admin_action'] == "eme_cron_cleanup_unpaid") {
            $eme_cron_cleanup_schedule = $_POST['eme_cron_cleanup_schedule'];
            $eme_cron_cleanup_unpaid_minutes = intval($_POST['eme_cron_cleanup_unpaid_minutes']);
	    if (wp_next_scheduled('eme_cron_cleanup_unpaid'))
		    wp_clear_scheduled_hook('eme_cron_cleanup_unpaid');
	    if ($eme_cron_cleanup_unpaid_minutes>=5) {
		    if ($eme_cron_cleanup_schedule) {
			    $schedules = wp_get_schedules();
			    if (isset($schedules[$eme_cron_cleanup_schedule])) {
				    $schedule = $schedules[$eme_cron_cleanup_schedule];
				    wp_schedule_event(time(), $eme_cron_cleanup_schedule, 'eme_cron_cleanup_unpaid');
				    update_option('eme_cron_cleanup_unpaid_minutes',$eme_cron_cleanup_unpaid_minutes);
				    $message = sprintf ( __ ( "Cleanup of unpaid pending bookings older than %d minutes will be done %s",'events-made-easy'),$eme_cron_cleanup_unpaid_minutes,$schedule['display']);
			    }
		    } else {
			    $message = __ ( "No automatic cleanup of unpaid pending bookings will be done.",'events-made-easy');
		    }
	    } else {
		    $message = __ ( "No automatic cleanup of unpaid pending bookings will be done.",'events-made-easy');
	    }
         } elseif ($_POST['eme_admin_action'] == 'deleteunusedpeople') {
            eme_delete_persons_without_bookings();
            $message = __("People without bookings have been deleted.",'events-made-easy');
         } elseif ($_POST['eme_admin_action'] == "eme_cleanup_all_event_related_data") {
            $other_data=0;
            if (isset($_POST['other_data'])) $other_data=1;
            eme_cleanup_all_event_related_data($other_data);
            $message = __ ( "Cleanup done: all data concerning events, locations and bookings have been removed.",'events-made-easy');
         }
      }
   }

   eme_cleanup_form($message);
}

function eme_cleanup_form($message = "") {
$areyousure = __('Are you sure you want to do this?','events-made-easy');
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h1><?php _e ('Cleanup actions','events-made-easy'); ?></h1>
<?php if($message != "") { ?>
   <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
   <p><?php echo $message; ?></p>
   </div>
<?php } ?>

   <form action="" method="post">
<label for="eme_number"><?php _e('Remove events older than','events-made-easy'); ?></label>
<?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type='hidden' name='eme_admin_action' value='eme_cleanup_events' />
   <input type="number" id="eme_number" name="eme_number" size="3" maxlength="3" min="1" max="999" step="1"  />
   <select name="eme_period">
   <option value="day" selected="selected"><?php _e ( 'Day(s)','events-made-easy'); ?></option>
   <option value="week"><?php _e ( 'Week(s)','events-made-easy'); ?></option>
   <option value="month"><?php _e ( 'Month(s)','events-made-easy'); ?></option>
   </select>
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" onclick="return areyousure('<?php echo $areyousure; ?>');" />
   </form>

<br /><br />
   <form action="" method="post">
<label for="eme_number"><?php _e('Remove unpaid pending bookings older than','events-made-easy'); ?></label>
<?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type='hidden' name='eme_admin_action' value='eme_cleanup_unpaid' />
   <input type="number" id="eme_number" name="eme_number" size="6" maxlength="6" min="5" max="999999" step="1" />
   <?php _e ( 'minutes','events-made-easy'); ?>
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" onclick="return areyousure('<?php echo $areyousure; ?>');" />
   </form>
   
<br /><br />
   <form action="" method="post">
   <label for="eme_cron_cleanup_unpaid_minutes">
   <?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false);
      $minutes=intval(get_option('eme_cron_cleanup_unpaid_minutes'));
      print sprintf(__('Schedule the automatic removal of unpaid pending bookings older than','events-made-easy'),$minutes);
   ?></label>
   <input type="number" id="eme_cron_cleanup_unpaid_minutes" name="eme_cron_cleanup_unpaid_minutes" size="6" maxlength="6" min="5" max="999999" step="1" value="<?php echo $minutes; ?>" />
   <?php _e ( 'minutes','events-made-easy'); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type='hidden' name='eme_admin_action' value='eme_cron_cleanup_unpaid' />
   <select name="eme_cron_cleanup_schedule">
   <option value=""><?php _e ( 'Not scheduled','events-made-easy'); ?></option>
   <?php
   $schedules = wp_get_schedules();
   $scheduled= wp_get_schedule('eme_cron_cleanup_unpaid');
   foreach ($schedules as $key=>$schedule) {
      $selected=($key==$scheduled)? 'selected="selected"':'';
      print "<option $selected value='$key'>".$schedule['display']."</option>";
   }
   ?>
   </select>
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" />
   </form>

<br /><br />
   <form action="" method="post">
   <label for="eme_cron_reminder_unpaid_minutes">
   <?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false);
      $templates_array=eme_get_templates_array_by_id();
      $minutes=intval(get_option('eme_cron_reminder_unpaid_minutes'));
      $subject=intval(get_option('eme_cron_reminder_unpaid_subject'));
      $body=intval(get_option('eme_cron_reminder_unpaid_body'));
      _e('Send a reminder mail to unpaid pending bookings every','events-made-easy');
   ?></label>
   <input type="number" id="eme_cron_reminder_unpaid_minutes" name="eme_cron_reminder_unpaid_minutes" size="6" maxlength="6" min="5" max="999999" step="1" value="<?php echo $minutes; ?>" />
   <?php _e ( 'minutes','events-made-easy'); ?>
   <?php _e ( 'Mail subject','events-made-easy'); echo eme_ui_select($subject,'eme_cron_reminder_unpaid_subject',$templates_array); ?>
   <?php _e ( 'Mail body','events-made-easy'); echo eme_ui_select($body,'eme_cron_reminder_unpaid_body',$templates_array); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type='hidden' name='eme_admin_action' value='eme_cron_reminder_unpaid' />
   <select name="eme_cron_reminder_schedule">
   <option value=""><?php _e ( 'Not scheduled','events-made-easy'); ?></option>
   <?php
   $schedules = wp_get_schedules();
   $scheduled= wp_get_schedule('eme_cron_reminder_unpaid');
   foreach ($schedules as $key=>$schedule) {
      $selected=($key==$scheduled)? 'selected="selected"':'';
      print "<option $selected value='$key'>".$schedule['display']."</option>";
   }
   ?>
   </select>
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" />
   </form>

<br /><br />
   <form action="" method="post">
<label><?php _e ( 'Delete people without bookings','events-made-easy'); ?></label>
<?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type="hidden" name="eme_admin_action" value="deleteunusedpeople" />
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" onclick="return areyousure('<?php echo $areyousure; ?>');" />
   </form>

<br /><br />
<?php _e('Remove all data concerning events, locations, people and bookings','events-made-easy'); ?>
   <form action="" method="post">
<?php echo wp_nonce_field('eme_cleanup','eme_admin_nonce',false,false); ?>
   <input type='hidden' name='page' value='eme-cleanup' />
   <input type='hidden' name='eme_admin_action' value='eme_cleanup_all_event_related_data' />
   <input id="other_data" type="checkbox" value="1" name="other_data"> <?php _e ( 'Also delete defined categories, templates, holidays and form fields' , 'events-made-easy'); ?><br />
   <input type="submit" value="<?php _e ( 'Apply' , 'events-made-easy'); ?>" name="doaction" id="eme_doaction" class="button-primary action" onclick="return areyousure('<?php echo $areyousure; ?>');" />
   </form>
</div>
<?php
}

?>
