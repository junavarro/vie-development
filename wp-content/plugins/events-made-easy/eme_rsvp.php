<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function eme_new_booking() {
   $booking = array(
   'event_id' => 0,
   'person_id' => 0,
   'payment_id' => 0,
   'booking_seats' => 0,
   'booking_seats_mp' => '',
   'booking_approved' => 0,
   'booking_comment' => '',
   'booking_price' => 0,
   'booking_paid' => 0,
   'ip' => eme_get_client_ip(),
   'lang' => eme_detect_lang(),
   'discount' => '',
   'discountid' => 0,
   'dgroupid' => 0,
   'creation_date'=>current_time('mysql', false),
   'modif_date'=>current_time('mysql', false),
   'creation_date_gmt'=>current_time('mysql', true),
   'modif_date_gmt'=>current_time('mysql', true)
   );

   return $booking;
}

function eme_add_booking_form($event_id,$show_message=1,$not_registered_only=0) {
   $search_tables=get_option('eme_autocomplete_sources');
   if ($search_tables!='none')
      eme_enqueue_autocomplete();
      //add_action('wp_enqueue_scripts', 'eme_enqueue_autocomplete');

   $event_ids=array(0=>$event_id);
   $is_multibooking=0;
   // we don't worry about the eme_register_empty_seats param, for attendance-like events it is checked later on
   return eme_add_multibooking_form($event_ids,0,0,0,0,$show_message=1,$is_multibooking,$not_registered_only);
}

function eme_add_multibooking_form($event_ids,$template_id_header=0,$template_id_entry=0,$template_id_footer=0,$eme_register_empty_seats=0,$show_message=1,$is_multibooking=1,$not_registered_only=0) {
   global $eme_timezone;
   // we need template ids
   $format_entry ="";
   $format_header="";
   $format_footer="";
   if ($is_multibooking) {
      $format_header = eme_get_template_format($template_id_header);
      $format_entry = eme_get_template_format($template_id_entry);
      $format_footer = eme_get_template_format($template_id_footer);
   }

   // make sure ...
   if ($eme_register_empty_seats != 1) $eme_register_empty_seats=0;

   $events=eme_get_event_arr($event_ids);

   // rsvp not active or no rsvp for this event, then return
   foreach ($events as $event) {
      if (!eme_is_event_rsvp($event)) {
         return;
      }

      $registration_wp_users_only=$event['registration_wp_users_only'];
      if ($registration_wp_users_only) {
         // we require a user to be WP registered to be able to book
         if (!is_user_logged_in()) {
            return;
         }
      }
   }

   #$destination = eme_event_url($event)."#eme-rsvp-message";
   $destination=$_SERVER['REQUEST_URI']."#eme-rsvp-message";

   // after the add or delete booking, we do a POST to the same page using javascript to show just the result
   // this has 2 advantages: you can give arguments in the post, and refreshing the page won't repeat the booking action, just the post showing the result
   // a javascript redir using window.replace + GET would work too, but that leaves an ugly GET url
   //if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'add_bookings' && isset($_POST['eme_event_ids'])) {
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'add_bookings') {
      // lets not re-get the posted event ids, but take the complete list of event ids wanted for this form
      // this can lead to more event ids than bookings (some events might be in the past already),
      // but our booking code takes care of that anyway
      // $events=eme_get_event_arr($_POST['event_ids']);
      $payment_id=0;
      if (!$is_multibooking) {
         if (has_filter('eme_eval_booking_form_post_filter'))
            $eval_filter_return=apply_filters('eme_eval_booking_form_post_filter',$events[0]);
         else
            $eval_filter_return=array(0=>1,1=>'');
      } else {
         if (has_filter('eme_eval_multibooking_form_post_filter'))
            $eval_filter_return=apply_filters('eme_eval_multibooking_form_post_filter',$events);
         else
            $eval_filter_return=array(0=>1,1=>'');
      }
      if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
         // the result of own eval rules failed, so let's use that as a result
         $booking_ids_done = 0;
         $form_result_message = $eval_filter_return[1];
      } else {
         $send_mail=1;
         if ($is_multibooking)
            $booking_res = eme_multibook_seats($events,$send_mail,$format_entry);
         else
            $booking_res = eme_book_seats($events[0],$send_mail);
         $form_result_message = $booking_res[0];
         $payment_id=$booking_res[1];
      }

      // let's decide for the first event wether or not payment is needed
      if ($payment_id && eme_event_can_pay_online($events[0])) {
         $total_price = eme_get_payment_price($payment_id);
         if ($total_price>0) {
            // you did a successfull registration, so now we decide wether to show the form again, or the payment form
            // but to make sure people don't mess with the booking id in the url, we use wp_nonce
            // by default the nonce is valid for 24 hours
            $eme_payment_nonce=wp_create_nonce('eme_payment_id'.$payment_id);
            // create the JS array that will be used to post
            $post_arr = array (
                  "eme_eventAction" => 'pay_bookings',
                  "eme_message" => $form_result_message,
                  "eme_payment_id" => $payment_id,
                  "eme_payment_nonce" => $eme_payment_nonce
                  );
         } else {
            // price=0, so we don't pass the payment id but say booking_done=1
            // so we know not to show the payment form page
            $post_arr = array (
                  "eme_eventAction" => 'message',
                  "eme_message" => $form_result_message,
                  "booking_done" => 1
                  );
         }
      } elseif ($payment_id) {
         $post_arr = array (
               "eme_eventAction" => 'message',
               "eme_message" => $form_result_message,
               "booking_done" => 1
               );
      } else {
         // booking failed: we add $_POST to the json, so we can pre-fill the form so the user can just correct the mistake
         $post_arr = stripslashes_deep($_POST);
         $post_arr['eme_eventAction'] = 'message';
         $post_arr['eme_message'] = $form_result_message;
      }

      // this should not be reposted (useless list of event ids now)
      //if (isset($post_arr['eme_event_ids'])) unset($post_arr['eme_event_ids']);
      // and some parts should be formatted differently in the name (php makes arrays, but we need it as names for javascript)
      if (isset($post_arr['bookings'])) {
         foreach ($post_arr['bookings'] as $key=>$val) {
            foreach ($val as $key2=>$val2) {
               $post_arr['bookings['.$key.']['.$key2.']']=$val2;
            }
         }
         unset($post_arr['bookings']);
      }

      $post_string=json_encode($post_arr);
      ?>
      <script type="text/javascript">
      function postwith (to,p) {
         var myForm = document.createElement("form");
         myForm.method="post" ;
         myForm.action = to ;
         for (var k in p) {
            var myInput = document.createElement("input") ;
            myInput.setAttribute("name", k) ;
            myInput.setAttribute("value", p[k]);
            myForm.appendChild(myInput) ;
         }
         document.body.appendChild(myForm) ;
         myForm.submit() ;
         document.body.removeChild(myForm) ;
      }
      <?php echo "postwith('$destination',$post_string);"; ?>
      </script>
      <?php
      return;
   }

   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'pay_bookings' && isset($_POST['eme_message']) && isset($_POST['eme_payment_id'])) {
      $payment_id = $_POST['eme_payment_id'];
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      // verify the nonce, to make sure people didn't mess with the booking id
      if (!isset($_POST['eme_payment_nonce']) || !wp_verify_nonce($_POST['eme_payment_nonce'], 'eme_payment_id'.$payment_id)) {
         return;
      } else {
         return eme_payment_form($payment_id,$form_result_message);
      }
   }

   $message_is_result_of_booking=0;
   $message_is_errormessage=0;
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'message' && isset($_POST['eme_message'])) {
      // due to the double POST javascript, the eme_message is escaped again, so we need stripslashes
      // but the message may contain html, so no html sanitize
      $form_result_message = eme_translate(stripslashes_deep($_POST['eme_message']));
      if (isset($_POST['booking_done']))
         $message_is_result_of_booking=1;
      else
         $message_is_errormessage=1;
   }

   $ret_string = "<div id='eme-rsvp-message'>";
   if ($show_message && !empty($form_result_message)) {
      if ($message_is_errormessage)
      $ret_string .= "<div class='eme-rsvp-message eme-rsvp-message-error'>$form_result_message</div>";
      else
      $ret_string .= "<div class='eme-rsvp-message eme-rsvp-message-success'>$form_result_message</div>";
   }

   $form_html = "";
   if (!$message_is_result_of_booking || ($message_is_result_of_booking && get_option('eme_rsvp_show_form_after_booking'))) {
      $form_html = "<form id='eme-rsvp-form' name='booking-form' method='post' action='$destination' onsubmit='eme_submit_button.disabled = true; return true;'>";
      // add a nonce for extra security
      $form_html .= wp_nonce_field('add_booking','eme_rsvp_nonce',false,false);
      // also add a honeypot field: if it gets completed with data, 
      // it's a bot, since a humand can't see this (using CSS to render it invisible)
      $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>
      <input type='hidden' name='eme_eventAction' value='add_bookings' />
      <input type='hidden' name='eme_register_empty_seats' value='$eme_register_empty_seats' />
      ";

      if ($is_multibooking)
         $form_html .= eme_replace_extra_multibooking_formfields_placeholders($format_header,$events[0]);

      $eme_date_obj_now = new ExpressiveDate(null,$eme_timezone);
      $current_userid=get_current_user_id();
      foreach ($events as $event) {
         $event_id=$event['event_id'];
         if ($not_registered_only && is_user_logged_in() && eme_get_booking_ids_by_wp_event_id($current_userid,$event_id)) {
            break;
         }
         $event_rsvp_startdatetime = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
         $event_rsvp_enddatetime = new ExpressiveDate($event['event_end_date']." ".$event['event_end_time'],$eme_timezone);
         if ($event['event_properties']['rsvp_end_target']=='start')
            $event_rsvp_datetime = $event_rsvp_startdatetime->copy();
         else
            $event_rsvp_datetime = $event_rsvp_enddatetime->copy();

         if ($event_rsvp_datetime->lessThan($eme_date_obj_now->copy()->modifyDays($event['rsvp_number_days'])->modifyHours($event['rsvp_number_hours'])) ||
               $event_rsvp_enddatetime->lessOrEqualTo($eme_date_obj_now)) {
            // in case of multibooking: don't show anything, but in case of single booking: show the 'no longer allowed' message
            if (!$is_multibooking && !$message_is_result_of_booking) 
               return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'events-made-easy')."</div></div>";
            else
               continue;
         }

         // you can book the available number of seats, with a max of x per time
         $min_allowed = $event['event_properties']['min_allowed'];
         // no seats anymore? No booking form then ... but only if it is required that the min number of
         // bookings should be >0 (it can be=0 for attendance bookings)
         $seats_available=1;
         if (eme_is_multi($min_allowed)) {
            $min_allowed_arr=eme_convert_multi2array($min_allowed);
            // min_allowed can be multi, but the total seats doesn't need to be ...
            if (!eme_is_multi($event['event_seats'])) {
               $seats_available = eme_get_available_seats($event_id);
            } else {
               $avail_seats = eme_get_available_multiseats($event_id);
               foreach ($avail_seats as $key=> $value) {
                  if ($value==0 && $min_allowed_arr[$key]>0)
                     $seats_available=0;
               }
            }
         } else {
            $avail_seats = eme_get_available_seats($event_id);
            if ($avail_seats == 0 && $min_allowed>0)
               $seats_available=0;
         }

         if (!$seats_available) {
            // we show the message concerning 'no more seats' only if it is not after a successful booking
            if (!$is_multibooking && !$message_is_result_of_booking)
               $form_html.="<div class='eme-rsvp-message'>".__('Bookings no longer possible: no seats available anymore', 'events-made-easy')."</div>";
         } else {
            //$form_html .= "<input type='hidden' name='eme_event_ids[]' value='$event_id' />";
            // for autocomplete js and single events, we need the event id (so autocomplete only works for authorized users)
            if (!$is_multibooking)
               $form_html .= "<input type='hidden' name='eme_event_id' value='$event_id' />";
            // regular formfield replacement here, but indicate that it is for multibooking
            $new_booking=eme_new_booking();
            if ($is_multibooking)
               $form_html .= eme_replace_formfields_placeholders ($event,$new_booking,$format_entry,$is_multibooking);
            else
               $form_html .= eme_replace_formfields_placeholders ($event,$new_booking);
         }
      }
      if ($is_multibooking)
         $form_html .= eme_replace_extra_multibooking_formfields_placeholders($format_footer,$events[0]);
      $form_html .= "</form>";
      if (has_filter('eme_add_booking_form_filter')) $form_html=apply_filters('eme_add_booking_form_filter',$form_html);
   }

   return $ret_string.$form_html."</div>";
}

function eme_add_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0), $atts));
   if ($id)
      return eme_add_booking_form($id);
}

function eme_add_multibooking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'recurrence_id'=>0,'category_id'=>0,'template_id_header'=>0,'template_id'=>0,'template_id_footer'=>0,'register_empty_seats'=>0), $atts));
   $ids=explode(",", $id);
   if ($recurrence_id) {
      // we only want future events, so set the second arg to 1
      $ids=eme_get_recurrence_eventids($recurrence_id,1);
   }
   if ($category_id) {
      // we only want future events, so set the second arg to 1
      $ids=eme_get_category_eventids($category_id,1);
   }
   if ($ids && $template_id_header && $template_id && $template_id_footer)
      return eme_add_multibooking_form($ids,$template_id_header,$template_id,$template_id_footer,$register_empty_seats);
}

function eme_booking_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0), $atts));
   $id=intval($id);
   if ($id>0) {
   	$event = eme_get_event($id);
   	if ($event)
      		return eme_get_bookings_list_for_event($event,$template_id,$template_id_header,$template_id_footer);
   }
}

function eme_mybooking_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0,'future'=>1,'approval_status'=>0), $atts));
   if (is_user_logged_in()) {
      $wp_id=get_current_user_id();
      $id=intval($id);
      if ($id && $wp_id) {
         $event = eme_get_event($id);
         return eme_get_bookings_list_for_event($event,$template_id,$template_id_header,$template_id_footer,$wp_id);
      } elseif ($wp_id) {
         return eme_get_bookings_list_for_wp_id($wp_id,$future,'',$template_id,$template_id_header,$template_id_footer,$approval_status);
      } else {
         return "";
      }
   }
}

function eme_attendee_list_shortcode($atts) {
   extract ( shortcode_atts ( array ('id'=>0,'template_id'=>0,'template_id_header'=>0,'template_id_footer'=>0), $atts));
   $event = eme_get_event(intval($id));
   if ($event)
      return eme_get_attendees_list_for($event,$template_id,$template_id_header,$template_id_footer);
   else
      return "";
}

function eme_attendees_report_link_shortcode($atts) {
   extract ( shortcode_atts ( array ('title'=>__('Attendees CSV','events-made-easy'),'scope'=>'this_month','event_template_id'=>0,'attend_template_id'=>0,'category' => '','notcategory' => ''), $atts));
   return eme_attendees_report_link($title,$scope,$category,$notcategory,$event_template_id,$attend_template_id);
}

function eme_attendees_report($scope,$category,$notcategory,$event_template_id,$attend_template_id) {
   $events = eme_get_events("scope=$scope&category=$category&notcategory=$notcategory");
   $attend_format = eme_get_template_format($template_id);
   if (!$attend_format) $attend_format='#_ATTENDFIRSTNAME #_ATTENDLASTNAME';
   if (!$event_format) $event_format='#_EVENTNAME #_STARTTIME';

   nocache_headers();
   header("Content-type: text/csv");
   header("Content-Disposition: attachment; filename=report-".date('Ymd-His').".csv");
   $fp = fopen('php://output', 'w');
   $headers = array(__('Title\Date','events-made-easy'));

   $all_attendees=array();
   $all_attendees_rec=array();
   $all_dates=array();
   foreach ($events as $event) {
	   $event_id=$event['event_id'];
	   $recurrence_id=$event['recurrence_id'];
	   $event_start_date=$event['event_start_date'];
	   if ($recurrence_id)
		   $all_attendees_rec[$recurrence_id][$event_start_date]=eme_get_attendees_for($event_id);
	   else
		   $all_attendees[$event_id][$event_start_date]=eme_get_attendees_for($event_id);
	   $all_dates[$event_start_date]=1;
   }
   ksort($all_dates);
   foreach ($all_dates as $event_start_date=>$val) {
	   $headers[]=$event_start_date;
   }
   eme_fputcsv($fp, $headers);
   $handled_recurrence_ids=array();
   foreach ($events as $event) {
	   $line=array();
	   $event_id=$event['event_id'];
	   $recurrence_id=$event['recurrence_id'];
           if (isset($handled_recurrence_ids[$recurrence_id])) continue;
	   $line[] = eme_replace_placeholders($event_format,$event);
	   foreach ($all_dates as $event_start_date=>$val) {
		   if (isset($all_attendees_rec[$recurrence_id][$event_start_date])) {
			   $list='';
			   foreach ($all_attendees_rec[$recurrence_id][$event_start_date] as $attendee) {
				   $list.= eme_replace_attendees_placeholders($attend_format,$event,$attendee)."\r\n";
			   }
			   $line[]=$list;
		   } elseif (isset($all_attendees[$event_id][$event_start_date])) {
			   $list='';
			   foreach ($all_attendees[$event_id][$event_start_date] as $attendee) {
				   $list.= eme_replace_attendees_placeholders($attend_format,$event,$attendee)."\r\n";
			   }
			   $line[]=$list;
		   } else {
			   $line[]='';
		   }
	   }
           if ($recurrence_id) $handled_recurrence_ids[$recurrence_id]=1;
	   eme_fputcsv($fp, $line);
   }
   fclose($fp);
   exit;
}

function eme_delete_booking_form($event_id,$show_message=1,$registered_only=0) {
   global $eme_timezone;
   
   $form_html = "";
   $form_result_message = "";
   $event = eme_get_event($event_id);
   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only && !is_user_logged_in()) {
      // we require a user to be WP registered to be able to delete a booking
      return;
   }

   #$destination = eme_event_url($event)."#eme-rsvp-message";
   if (isset($_GET['lang'])) {
      $language=eme_strip_tags($_GET['lang']);
      $destination = "?lang=".$language."#eme-rsvp-message";
   } else {
      $destination = "#eme-rsvp-message";
   }
   
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'delete_booking' && isset($_POST['event_id'])) {
      $form_result_message = eme_cancel_seats($event);
      $eme_message_nonce=wp_create_nonce('eme_message'.$form_result_message);
      // post to a page showing the result of the booking
      // create the JS array that will be used to post
      $post_arr = array (
            "eme_eventAction" => 'message',
            "eme_message" => $form_result_message,
            );
      $post_string=json_encode($post_arr);
      ?>
      <script type="text/javascript">
      function postwith (to,p) {
         var myForm = document.createElement("form");
         myForm.method="post" ;
         myForm.action = to ;
         for (var k in p) {
            var myInput = document.createElement("input") ;
            myInput.setAttribute("name", k) ;
            myInput.setAttribute("value", p[k]);
            myForm.appendChild(myInput) ;
         }
         document.body.appendChild(myForm) ;
         myForm.submit() ;
         document.body.removeChild(myForm) ;
      }
      <?php echo "postwith('$destination',$post_string);"; ?>
      </script>
      <?php
      return;
   }
   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'message' && isset($_POST['eme_message'])) {
      $form_result_message = eme_esc_html($_POST['eme_message']);
   }

   $event_rsvp_startdatetime = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
   $event_rsvp_enddatetime = new ExpressiveDate($event['event_end_date']." ".$event['event_end_time'],$eme_timezone);
   if ($event['event_properties']['rsvp_end_target']=='start')
      $event_rsvp_datetime = $event_rsvp_startdatetime->copy();
   else
      $event_rsvp_datetime = $event_rsvp_enddatetime->copy();

   $eme_date_obj_now = new ExpressiveDate(null,$eme_timezone);
   $ret_string="";
   if ($event_rsvp_datetime->lessThan($eme_date_obj_now->copy()->modifyDays($event['rsvp_number_days'])->modifyHours($event['rsvp_number_hours'])) ||
       $event_rsvp_enddatetime->lessOrEqualTo($eme_date_obj_now)) {
      if(!empty($form_result_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_result_message</div>";
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'events-made-easy')."</div>";
   }

   if ($show_message && !empty($form_result_message)) {
      $form_html = "<div id='eme-rsvp-message' class='eme-rsvp-message'>$form_result_message</div>";
   }

   $current_userid=get_current_user_id();
   if (!$registered_only || ($registered_only && is_user_logged_in() && eme_get_booking_ids_by_wp_event_id($current_userid,$event['event_id']))) {
      $form_html .= "<form id='booking-delete-form' name='booking-delete-form' method='post' action='$destination' onsubmit='eme_submit_button.disabled = true; return true;'>
         <input type='hidden' name='eme_eventAction' value='delete_booking' />
         <input type='hidden' name='event_id' value='$event_id' />";
      $form_html .= wp_nonce_field('del_booking','eme_rsvp_nonce',false,false);
      $form_html .= eme_replace_cancelformfields_placeholders($event);
      $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>";
      $form_html .= "</form>";
      if (has_filter('eme_delete_booking_form_filter')) $form_html=apply_filters('eme_delete_booking_form_filter',$form_html);
   }
   return $form_html;
}

function eme_delete_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id' => 0), $atts));
   return eme_delete_booking_form($id);
}

function eme_cancel_confirm_form($payment_randomid) {
   global $eme_timezone;

   $destination = eme_get_events_page();
   $payment=eme_get_payment(0,$payment_randomid);
   $booking_ids=eme_get_payment_booking_ids($payment['id']);
   if ($booking_ids) {
      $format="#_STARTDATE #_STARTTIME: #_EVENTNAME (#_RESPSPACES ".__('places','events-made-easy').").<br />";
      $eme_format_header=get_option('eme_bookings_list_header_format');
      $eme_format_footer=get_option('eme_bookings_list_footer_format');

      $res=__("You're about to cancel the following bookings:",'events-made-easy').$eme_format_header;
      $eme_date_obj_now=new ExpressiveDate(null,$eme_timezone);
      foreach ($booking_ids as $booking_id) {
         // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
         $booking=eme_get_booking($booking_id);
         $event=eme_get_event($booking['event_id']);
         $cancel_cutofftime=new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
         $eme_cancel_rsvp_days=-1*get_option('eme_cancel_rsvp_days');
         $cancel_cutofftime->modifyDays($eme_cancel_rsvp_days);
         if ($cancel_cutofftime->lessThan($eme_date_obj_now)) {
            $res="<p class='eme_no_booking'>".__("You're no longer allowed to cancel this booking",'events-made-easy')."</p>";
            return $res;
         }
         $tmp_format = eme_replace_placeholders($format, $event, "html", 0);
         $res.= eme_replace_booking_placeholders($tmp_format,$event,$booking);
      }
      $res.=$eme_format_footer;
      $res .= "<form id='booking-cancel-form' name='booking-cancel-form' method='post' action='$destination' onsubmit='eme_submit_button.disabled = true; return true;'>
         <input type='hidden' name='eme_confirm_cancel_booking' value='1' />
         <input type='hidden' name='eme_pmt_rndid' value='$payment_randomid' />";
      $res .= wp_nonce_field("cancel booking $payment_randomid",'eme_rsvp_nonce',false,false);
      $res .= "<input name='eme_submit_button' type='submit' value='".__('Cancel the booking','events-made-easy')."' />";
      $res .= "</form>";

   } else {
      $res="<p class='eme_no_booking'>".__('No such booking found!','events-made-easy')."</p>";
   }
   return $res;
}

 // eme_cancel_seats is NOT called from the admin backend, but to be sure: we check for it
function eme_cancel_seats($event) {
   $event_id = $event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];
   $is_admin=is_admin();

   if ($is_admin) {
      return __('This function is not allowed from the admin backend.', 'events-made-easy');
   }

   // check for spammers as early as possible
   if (isset($_POST['honeypot_check'])) {
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   } elseif (!$is_admin && !isset($_POST['honeypot_check'])) {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $honeypot_check = "bad boy";
   } else {
      $honeypot_check = "";
   }

   if (!$is_admin && get_option('eme_captcha_for_booking')) {
      $captcha_err = eme_check_captcha("captcha_check","eme_del_booking");
   } else {
      $captcha_err = "";
   }

   if (!$is_admin && (! isset( $_POST['eme_rsvp_nonce'] ) ||
       ! wp_verify_nonce( $_POST['eme_rsvp_nonce'], 'del_booking' ))) {
      $nonce_err = "bad boy";
   } else {
      $nonce_err = "";
   }

   if(!empty($captcha_err)) {
      return __('You entered an incorrect code','events-made-easy');
   } elseif (!empty($honeypot_check) ||  !empty($nonce_err)) {
      return __("You're not allowed to do this. If you believe you've received this message in error please contact the site owner.",'events-made-easy');
   } 

   $booking_ids=array();
   if ($registration_wp_users_only && is_user_logged_in()) {
      // we require a user to be WP registered to be able to book
      $wp_id=get_current_user_id();
      $booking_ids=eme_get_booking_ids_by_wp_event_id($wp_id,$event_id);
   } elseif (isset($_POST['lastname']) && isset($_POST['email'])) {
      $bookerLastName = eme_strip_tags($_POST['lastname']);
      if (isset($_POST['firstname']))
         $bookerFirstName = eme_strip_tags($_POST['firstname']);
      else
         $bookerFirstName = "";
      $bookerEmail = eme_strip_tags($_POST['email']);
      $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
      $person_id = $booker['person_id'];
      $booking_ids=eme_get_booking_ids_by_person_event_id($person_id,$event_id);
   }
   if (!empty($booking_ids)) {
      foreach ($booking_ids as $booking_id) {
         // first get the booking details, then delete it and then send the mail
         // the mail needs to be sent after the deletion, otherwise the count of free spaces is wrong
         $booking = eme_get_booking ($booking_id);
         eme_delete_booking($booking_id);
         eme_email_rsvp_booking($booking,"cancelRegistration");
         // delete the booking answers after the mail is sent, so the answers can still be used in the mail
         eme_delete_answers($booking_id);
      }
      $result = __('Booking deleted', 'events-made-easy');
   } else {
      $result = __('There are no bookings associated to this name and e-mail', 'events-made-easy');
   }
   return $result;
}

function eme_multibook_seats($events, $send_mail, $format, $is_multibooking=1) {
   $booking_ids = array();
   $result="";
   $is_admin=is_admin();

   // check for spammers as early as possible
   if (isset($_POST['honeypot_check'])) {
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   } elseif (!$is_admin && !isset($_POST['honeypot_check'])) {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $honeypot_check = "bad boy";
   } else {
      $honeypot_check = "";
   }

   if (!$is_admin && get_option('eme_captcha_for_booking')) {
      $captcha_err = eme_check_captcha("captcha_check","eme_add_booking");
   } else {
      $captcha_err = "";
   }

   if (!$is_admin && (! isset( $_POST['eme_rsvp_nonce'] ) ||
       ! wp_verify_nonce( $_POST['eme_rsvp_nonce'], 'add_booking' ))) {
      $nonce_err = "bad boy";
   } else {
      $nonce_err = "";
   }

   if(!empty($captcha_err)) {
      $result = __('You entered an incorrect code','events-made-easy');
      return array(0=>$result,1=>$booking_ids);
   } elseif (!empty($honeypot_check) ||  !empty($nonce_err)) {
      $result = __("You're not allowed to do this. If you believe you've received this message in error please contact the site owner.",'events-made-easy');
      return array(0=>$result,1=>$booking_ids);
   } 

   // now do regular checks
   $all_required_fields=eme_find_required_formfields($format);
   foreach ($events as $event) {
      $bookedSeats = 0;
      $bookedSeats_mp = array();
      $event_id=$event['event_id'];

      // an event without matching booking? Skip it
      // can happen for multibookings where some events are already in the past (because for those the booking form no longer shows)
      if (!isset($_POST['bookings'][$event_id]))
	      continue;
      
      $min_allowed = $event['event_properties']['min_allowed'];
      $max_allowed = $event['event_properties']['max_allowed'];
      $take_attendance=0;
      if ($event['event_properties']['take_attendance'] && !eme_is_multi($event['price'])) {
         // we set min=0,max=1 for regular events, to protect people from stupid mistakes
         // we don't do this for multiprice events since you can say e.g. min=1,max=1 to force exactly 1 seat then ...
         $min_allowed = 0;
         $max_allowed = 1;
         $take_attendance=1;
      }

      $registration_wp_users_only=$event['registration_wp_users_only'];
      if (!eme_is_multi($event['price'])) {
         if (isset($_POST['bookings'][$event_id]['bookedSeats']))
            $bookedSeats = intval($_POST['bookings'][$event_id]['bookedSeats']);
      } else {
         // for multiple prices, we have multiple booked Seats as well
         // the next foreach is only valid when called from the frontend

         // make sure the array contains the correct keys already, since
         // later on in the function eme_record_booking we do a join
         $booking_prices_mp=eme_convert_multi2array($event['price']);
         foreach ($booking_prices_mp as $key=>$value) {
            $bookedSeats_mp[$key] = 0;
         }
         foreach($_POST['bookings'][$event_id] as $key=>$value) {
            if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
               $field_id = intval($matches[1])-1;
               $bookedSeats += $value;
               $bookedSeats_mp[$field_id]=$value;
            }
         }
      }

      // only register empty seats if wanted, this can also be used to turn attendance in a yes/no-type event
      // but of course yes/no and not taking attendance is only usefull in multibooking events, since the eme_register_empty_seats
      // can only be set for multibooking events ...
      // the continue-statement breaks the higher foreach-loop
      if ($bookedSeats==0 && !$take_attendance && (!isset($_POST['eme_register_empty_seats']) || intval($_POST['eme_register_empty_seats'])==0)) {
	 // only add the message if not multibooking
	 if (!$is_multibooking)
            $result .= __('Please select at least 1 seat!','events-made-easy');
         continue;
      }
      if ($bookedSeats==0 && $is_multibooking && $take_attendance && (!isset($_POST['eme_register_empty_seats']) || intval($_POST['eme_register_empty_seats'])==0)) {
         continue;
      }

      if (isset($_POST['bookings'][$event_id]['comment']))
         $bookerComment = eme_sanitize_textarea($_POST['bookings'][$event_id]['comment']);
      elseif (isset($_POST['comment']))
         $bookerComment = eme_sanitize_textarea($_POST['comment']); 
      else
         $bookerComment = "";

      $missing_required_fields=array();
      // check all required fields
      if (!$is_admin && get_option('eme_rsvp_check_required_fields')) {
         foreach ($all_required_fields as $required_field) {
            if (preg_match ("/LASTNAME|EMAIL|SEATS/",$required_field)) {
               // we already check these separately, and EMAIL regex also catches _HTML5_EMAIL
               // since NAME would also match FIRSTNAME (which is not necessarily required), we check the beginning too
               continue;
            } elseif (preg_match ("/PHONE/",$required_field)) {
               // PHONE regex also catches HTML5_PHONE
               if (!isset($_POST['phone']) || empty($_POST['phone'])) array_push($missing_required_fields, __('Phone number','events-made-easy'));
            } elseif (preg_match ("/FIRSTNAME/",$required_field)) {
               // if wp membership is required, this is a disabled field (not submitted via POST) and info got from WP
               if ($registration_wp_users_only)
                  continue;
               elseif (!isset($_POST['firstname']) || empty($_POST['firstname']))
                  array_push($missing_required_fields, __('First name','events-made-easy'));
            } elseif (preg_match ("/(ADDRESS1|ADDRESS2|CITY|STATE|ZIP|COUNTRY)/",$required_field, $matches)) {
               $fieldname=strtolower($matches[1]);
               $fieldname_ucfirst=ucfirst($fieldname);
               if (!isset($_POST[$fieldname])) array_push($missing_required_fields, __($fieldname_ucfirst,'events-made-easy'));
            } elseif (preg_match ("/COMMENT/",$required_field)) {
               if (empty($bookerComment)) array_push($missing_required_fields, __('Comment','events-made-easy'));
            } elseif ((!isset($_POST['bookings'][$event_id][$required_field]) || $_POST['bookings'][$event_id][$required_field]==='') && 
		      (!isset($_POST[$required_field]) || $_POST[$required_field]==='')) {
               if (preg_match('/FIELD(\d+)/', $required_field, $matches)) {
                  $field_id = intval($matches[1]);
                  $formfield = eme_get_formfield_byid($field_id);
                  array_push($missing_required_fields, $formfield['field_name']);
               } else {
                  array_push($missing_required_fields, $required_field);
               }
            }
         }
      }

      $bookerLastName = "";
      $bookerFirstName = "";
      $bookerEmail = "";
      $booker=array();
      if (!$is_admin && $registration_wp_users_only && is_user_logged_in()) {
         // we require a user to be WP registered to be able to book
         $current_user = wp_get_current_user();
         $booker_wp_id=$current_user->ID;
         // we also need name and email for sending the mail
         $bookerLastName = $current_user->user_lastname;
         if (empty($bookerLastName))
            $bookerLastName = $current_user->display_name;
         $bookerFirstName = $current_user->user_firstname;
         $bookerEmail = $current_user->user_email;
         $booker = eme_get_person_by_wp_id($booker_wp_id);
      } elseif (!$is_admin && is_user_logged_in() && isset($_POST['lastname']) && isset($_POST['email'])) {
         $booker_wp_id=get_current_user_id();
         $bookerLastName = eme_strip_tags($_POST['lastname']);
         if (isset($_POST['firstname']))
            $bookerFirstName = eme_strip_tags($_POST['firstname']);
         $bookerEmail = eme_strip_tags($_POST['email']);
         $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
      } elseif (isset($_POST['lastname']) && isset($_POST['email'])) {
         // when called from the admin backend, we don't care about registration_wp_users_only
         if ($is_admin)
            $booker_wp_id=get_current_user_id();
         else
            $booker_wp_id=0;
         $bookerLastName = eme_strip_tags($_POST['lastname']);
         if (isset($_POST['firstname']))
            $bookerFirstName = eme_strip_tags($_POST['firstname']);
         $bookerEmail = eme_strip_tags($_POST['email']);
         $booker = eme_get_person_by_name_and_email($bookerLastName, $bookerFirstName, $bookerEmail); 
      }

      if (has_filter('eme_eval_booking_filter'))
         $eval_filter_return=apply_filters('eme_eval_booking_filter',$event);
      else
         $eval_filter_return=array(0=>1,1=>'');

      if (empty($bookerLastName)) {
         // if any required field is empty: return an error
         $result .= __('Please fill out your last name','events-made-easy');
         // to be backwards compatible, don't require bookerFirstName here: it can be empty for forms that just use #_NAME
      } elseif (empty($bookerEmail)) {
         // if any required field is empty: return an error
         $result .= __('Please fill out your e-mail','events-made-easy');
      } elseif (count($missing_required_fields)>0) {
         // if any required field is empty: return an error
         $missing_required_fields_string=join(", ",$missing_required_fields);
         $result .= sprintf(__('Please make sure all of the following required fields are filled out correctly: %s','events-made-easy'),$missing_required_fields_string);
      } elseif (!is_email($bookerEmail)) {
         $result .= __('Please enter a valid mail address','events-made-easy');
      } elseif (!eme_is_multi($min_allowed) && $bookedSeats < $min_allowed) {
         $result .= __('Please enter a correct number of spaces to reserve','events-made-easy');
      } elseif (eme_is_multi($min_allowed) && eme_is_multi($event['event_seats']) && $bookedSeats_mp < eme_convert_multi2array($min_allowed)) {
         $result .= __('Please enter a correct number of spaces to reserve','events-made-easy');
      } elseif (!eme_is_multi($max_allowed) && $max_allowed>0 && $bookedSeats>$max_allowed) {
         // we check the max, but only is max_allowed>0, max_allowed=0 means no limit
         $result .= __('Please enter a correct number of spaces to reserve','events-made-easy');
      } elseif (eme_is_multi($max_allowed) && eme_is_multi($event['event_seats']) && eme_get_total($max_allowed)>0 && $bookedSeats_mp >  eme_convert_multi2array($max_allowed)) {
         // we check the max, but only is the total max_allowed>0, max_allowed=0 means no limit
         // currently we don't support 0 as being no limit per array element
         $result .= __('Please enter a correct number of spaces to reserve','events-made-easy');
      } elseif (!$is_admin && $registration_wp_users_only && !$booker_wp_id) {
         // spammers might get here, but we catch them
         $result .= __('WP membership is required for registration','events-made-easy');
      } elseif (is_array($eval_filter_return) && !$eval_filter_return[0]) {
         // the result of own eval rules
         $result .= $eval_filter_return[1];
      } else {
	 $waitinglist=0;
         if (eme_is_multi($event['event_seats'])) {
            $seats_available=eme_are_multiseats_available_for($event_id, $bookedSeats_mp);
	 } else {
            $seats_available=eme_are_seats_available_for($event_id, $bookedSeats);
	    $waitinglist_seats = intval($event['event_properties']['waitinglist_seats']);
	    $avail_seats = eme_get_available_seats($event_id);
	    if ($waitinglist_seats>0 && $avail_seats<=$waitinglist_seats) {
		    $waitinglist=1;
	    }
	 }

         if ($seats_available) {
            if (empty($booker))
               $booker = eme_add_person($bookerLastName, $bookerFirstName, $bookerEmail, $booker_wp_id);
            else
               $booker = eme_update_person_with_postinfo($booker['person_id']);

            // ok, just to be safe: check the person_id of the booker
            if ($booker['person_id']>0) {
               // we can only use the filter here, since the booker needs to be created first if needed
               if (has_filter('eme_eval_booking_form_filter'))
                  $eval_filter_return=apply_filters('eme_eval_booking_form_filter',$event,$booker);
               else
                  $eval_filter_return=array(0=>1,1=>'');
               if (is_array($eval_filter_return) && !$eval_filter_return[0]) {
                  // the result of own eval rules failed, so let's use that as a result
                  $result .= $eval_filter_return[1];
               } else {
                  $booking_id=eme_record_booking($event, $booker, $bookedSeats,$bookedSeats_mp,$bookerComment,$waitinglist);
                  $booking_ids[]=$booking_id;
                  // everything ok, so we unset the variables entered, so when the form is shown again, all is defaulted again
                  unset($_POST['bookings'][$event_id]);
               }
            } else {
               $result .= __('No booker ID found, something is wrong here','events-made-easy');
               unset($_POST['bookings'][$event_id]);
            }
         } else {
            $result .= __('Booking cannot be made: not enough seats available!', 'events-made-easy');
            // here we only unset the number of seats entered, so the user doesn't have to fill in the rest again
            unset($_POST['bookings'][$event_id]);
         }
      }
   }

   $booking_ids_done=join(',',$booking_ids);
   $payment_id=0;
   $lang=eme_detect_lang();
   if (!empty($booking_ids_done)) {
      // the payment needs to be created before the mail is sent or placeholders replaced, otherwise you can't send a link to the payment ...
      $payment_id=eme_create_payment($booking_ids_done);

      $action="";
      foreach ($booking_ids as $booking_id) {
         $booking = eme_get_booking ($booking_id);
         $event = eme_get_event ($booking['event_id']);

         if (!empty($event['event_registration_recorded_ok_html']))
            $ok_format = $event['event_registration_recorded_ok_html'];
         elseif ($event['event_properties']['event_registration_recorded_ok_html_tpl']>0)
            $ok_format = eme_get_template_format($event['event_properties']['event_registration_recorded_ok_html_tpl']);
         else
            $ok_format = get_option('eme_registration_recorded_ok_html' );

         // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
         $result = eme_replace_placeholders($ok_format, $event, "html", 0,$lang);
         $result = eme_replace_booking_placeholders($result, $event, $booking,$is_multibooking,"html",$lang);
         // leave the action empty, then regular approval flow is followed (even in admin)
         //if (is_admin()) {
         //   $action="approveRegistration";
         //}
      }
      // send the mail based on the first booking done in the series
      $booking = eme_get_booking ($booking_ids[0]);
      if ($send_mail) {
	      $ret_email = eme_email_rsvp_booking($booking,$action,$is_multibooking);
	      if (!$ret_email) {
		      $result.= "<br />".__('Warning: there was a problem sending you the registration mail, please contact the site admininistrator to sort this out','events-made-easy');
	      }
      }
   }

   $res = array(0=>$result,1=>$payment_id);
   return $res;
}

// the eme_book_seats can also be called from the admin backend, that's why for certain things, we check using is_admin where we are
function eme_book_seats($event, $send_mail) {
   if (!empty($event['event_registration_form_format']))
      $format = $event['event_registration_form_format'];
   elseif ($event['event_properties']['event_registration_form_format_tpl']>0)
      $format = eme_get_template_format($event['event_properties']['event_registration_form_format_tpl']);
   else
      $format = get_option('eme_registration_form_format' );
   $events=array(0=>$event);
   $is_multibooking=0;
   return eme_multibook_seats($events,$send_mail,$format,$is_multibooking);
}

function eme_get_booking($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT * FROM $bookings_table WHERE booking_id = '$booking_id';" ;
   $result = $wpdb->get_row($sql, ARRAY_A);
   return $result;
}

function eme_get_event_price($event_id) {
   global $wpdb; 
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price FROM $events_table WHERE event_id =%d",$event_id);
   $result = $wpdb->get_var($sql);
   return $result;
   }

function eme_get_bookings_by_wp_id($wp_id,$future,$approval_status=0) {
   global $wpdb, $eme_timezone; 
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   if ($future) {
      $eme_date_obj=new ExpressiveDate(null,$eme_timezone);
      $this_time = $eme_date_obj->format('Y-m-d H:i:00');
      if ($approval_status==1) $extra_condition="bookings.booking_approved=1 AND ";
      elseif ($approval_status==2) $extra_condition="bookings.booking_approved=0 AND ";
      else $extra_condition="";
      $sql= $wpdb->prepare("select bookings.* from $bookings_table as bookings,$events_table as events where $extra_condition wp_id = %d AND bookings.event_id=events.event_id AND CONCAT(events.event_start_date,' ',events.event_start_time)>'$this_time'",$wp_id);
   } else {
      if ($approval_status==1) $extra_condition="AND booking_approved=1";
      elseif ($approval_status==2) $extra_condition="AND booking_approved=0";
      else $extra_condition="";
      $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE wp_id = %d $extra_condition",$wp_id);
   }
   $result = $wpdb->get_results($sql, ARRAY_A);
   return $result;
}

function eme_get_booking_by_person_event_id($person_id,$event_id) {
   return eme_get_booking_ids_by_person_event_id($person_id,$event_id);
}
function eme_get_booking_ids_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_booking_ids_by_wp_event_id($wp_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_booked_seats_by_wp_event_id($wp_id,$event_id) {
   global $wpdb;
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats_by_wp_event_id($wp_id,$event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats_by_wp_event_id($wp_id,$event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT booking_seats_mp FROM $bookings_table WHERE event_id = $event_id"; 
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE wp_id = %d AND event_id = %d",$wp_id,$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_booked_seats_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats_by_person_event_id($person_id,$event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats_by_person_event_id($person_id,$event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT booking_seats_mp FROM $bookings_table WHERE event_id = $event_id"; 
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_event_id_by_booking_id($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE booking_id = %d",$booking_id);
   $event_id = $wpdb->get_var($sql);
   return $event_id;
}

function eme_get_event_by_booking_id($booking_id,$force=0) {
   $event_id = eme_get_event_id_by_booking_id($booking_id);
   if ($event_id)
      $event = eme_get_event($event_id,$force);
   else
      $event = eme_new_event();
   return $event;
}

function eme_get_event_ids_by_booker_id($person_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE person_id = %d",$person_id);
   return $wpdb->get_col($sql);
}

function eme_record_booking($event, $booker, $seats, $seats_mp, $comment, $waitinglist) {
   global $wpdb, $plugin_page;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $seats = intval($seats);
   // sanitize not needed: wpdb->insert does it already
   //$comment = eme_sanitize_request($comment);
   $booking=eme_new_booking();
   $booking['event_id']=$event['event_id'];
   $booking['person_id']=intval($booker['person_id']);
   if (!isset($booker['wp_id']) || $booker['wp_id']==0)
	   $booking['wp_id']=get_current_user_id();
   else
	   $booking['wp_id']=$booker['wp_id'];
   $booking['booking_seats']=$seats;
   $booking['booking_seats_mp']=eme_convert_array2multi($seats_mp);
   $booking['booking_price']=$event['price'];
   $booking['booking_comment']=$comment;

   $is_admin=is_admin();
   if (!$is_admin && ($event['registration_requires_approval'] || $waitinglist)) {
      // if we're adding a booking via the frontend, check for approval needed
      // if we're adding a booking via the frontend and the waitinglist is on, also approval needed
      $booking['booking_approved']=0;
   } elseif ($is_admin && $event['registration_requires_approval'] && $plugin_page=='eme-registration-approval') {
      // if we're adding a booking via the backend, check the page we came from to check for approval too
      $booking['booking_approved']=0;
   } else {
      $booking['booking_approved']=1;
   }
   if ($waitinglist) {
      $booking['waitinglist']=1;
   }

   if ($wpdb->insert($bookings_table,$booking)) {
	   $booking_id = $wpdb->insert_id;
	   $booking['booking_id'] = $booking_id;
	   eme_booking_answers($event,$booking);
           eme_booking_discount($event,$booking);
	   // now that everything is (or should be) correctly entered in the db, execute possible actions for the new booking
	   if (has_action('eme_insert_rsvp_action')) do_action('eme_insert_rsvp_action',$booking);
	   return $booking_id;
   } else {
	   return false;
   }
}

function eme_booking_answers($event,$booking, $do_update=1) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $fields_seen=array();

   $extra_charge=0;
   $event_id=$event['event_id'];
   if ($do_update)
	   $booking_id=$booking['booking_id'];
   else
	   $booking_id=0;
   // first do the multibooking answers if any
   if (isset($_POST['bookings'][$event_id])) {
	   foreach($_POST['bookings'][$event_id] as $key =>$value) {
		   if (preg_match('/^FIELD(\d+)$/', $key, $matches)) { 
			   $field_id = intval($matches[1]);
			   $fields_seen[]=$field_id;
			   $formfield = eme_get_formfield_byid($field_id);
			   if ($formfield) {
				   // for multivalue fields like checkbox, the value is in fact an array
				   // to store it, we make it a simple "multi" string using eme_convert_array2multi, so later on when we need to parse the values 
				   // (when editing a booking), we can re-convert it to an array with eme_convert_multi2array (see eme_formfields.php)
				   if (is_array($value)) $value=eme_convert_array2multi($value);
				   if (eme_is_textareafield($formfield['field_type']))
					   $value=eme_sanitize_textarea($value);
				   else
					   $value=sanitize_text_field($value);
				   if ($formfield['extra_charge'] && is_numeric($value))
					   $extra_charge+=$value;
				   if ($do_update) {
				      $sql = $wpdb->prepare("INSERT INTO $answers_table (booking_id,field_id,answer) VALUES (%d,%d,%s)",$booking_id,$field_id,$value);
				      $wpdb->query($sql);
				   }
			   }
		   }
	   }
   }

   foreach($_POST as $key =>$value) {
      if (preg_match('/^FIELD(\d+)$/', $key, $matches)) { 
         $field_id = intval($matches[1]);
	 // the value was already stored for a multibooking, so don't do it again
	 if (in_array($field_id,$fields_seen))
		continue;
         $formfield = eme_get_formfield_byid($field_id);
	 if ($formfield) {
		 // for multivalue fields like checkbox, the value is in fact an array
		 // to store it, we make it a simple "multi" string using eme_convert_array2multi, so later on when we need to parse the values 
		 // (when editing a booking), we can re-convert it to an array with eme_convert_multi2array (see eme_formfields.php)
		 if (is_array($value)) $value=eme_convert_array2multi($value);
		 if (eme_is_textareafield($formfield['field_type']))
			 $value=eme_sanitize_textarea($value);
		 else
			 $value=sanitize_text_field($value);
		 if ($formfield['extra_charge'] && is_numeric($value))
			 $extra_charge+=$value;
		 if ($do_update) {
		    $sql = $wpdb->prepare("INSERT INTO $answers_table (booking_id,field_id,answer) VALUES (%d,%d,%s)",$booking_id,$field_id,$value);
		    $wpdb->query($sql);
		 }
	 }
      }
   }

   // now put the extra charge found in the booking made
   if ($do_update) {
      $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
      $sql = $wpdb->prepare("UPDATE $bookings_table SET extra_charge = %s WHERE booking_id = %d",$extra_charge,$booking_id);
      $wpdb->query($sql);
   }
   return $extra_charge;
}

function eme_get_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $formfield_table_name = $wpdb->prefix.FORMFIELDS_TBNAME;
   $sql = $wpdb->prepare("SELECT a.*, b.field_name FROM $answers_table a, $formfield_table_name b WHERE a.booking_id=%d AND b.field_id=a.field_id",$booking_id);
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id=%d",$booking_id);
   $wpdb->query($sql);
}

function eme_convert_answer2tag($answer) {
   $formfield=eme_get_formfield_byid($answer['field_id']);
   $field_info=$formfield['field_info'];
   $field_tags=$formfield['field_tags'];

   if (!empty($field_tags) && eme_is_multifield($formfield['field_type'])) {
      $answers = eme_convert_multi2array($answer['answer']);
      $values = eme_convert_multi2array($field_info);
      $tags = eme_convert_multi2array($field_tags);
      $my_arr = array();
      foreach ($answers as $ans) {
         foreach ($values as $key=>$val) {
            if ($val==$ans) {
               $my_arr[]=$tags[$key];
            }
         }
      }
      return eme_convert_array2multi($my_arr);
   } else {
      return $answer['answer'];
   }
} 

function eme_get_answer_fieldids($booking_ids) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = "SELECT DISTINCT field_id FROM $answers_table WHERE booking_id IN (".join(",",$booking_ids).")";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_all_bookings_for_event_id($event_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id IN (SELECT booking_id from $bookings_table WHERE event_id = %d)",$event_id);
   $wpdb->query($sql);
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE event_id = %d",$event_id);
   $wpdb->query($sql);
   return 1;
}

function eme_delete_all_bookings_for_person_id($person_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id IN (SELECT booking_id from $bookings_table WHERE person_id = %d)",$person_id);
   $wpdb->query($sql);
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE person_id = %d",$person_id);
   $wpdb->query($sql);
   return 1;
}

function eme_transfer_all_bookings($person_id,$to_person_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $where = array();
   $fields = array();
   $where['person_id'] = $person_id;
   $fields['person_id'] = $to_person_id;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else return true;
}

function eme_move_booking_event($booking_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("UPDATE $bookings_table SET event_id = %d WHERE booking_id = %d",$event_id,$booking_id);
   return $wpdb->query($sql);
}

function eme_delete_booking($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE booking_id = %d",$booking_id);
   return $wpdb->query($sql);
}

function eme_set_booking_paid($booking_id,$approve_pending=0) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   
   $where = array();
   $fields = array();
   $where['booking_id'] = $booking_id;
   $fields['booking_paid'] = 1;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($approve_pending)
      $fields['booking_approved'] = 1;
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;
   if ($res && $approve_pending) {
      // the booking changed, so get it again
      $booking = eme_get_booking ($booking_id);
      eme_email_rsvp_booking($booking,"approveRegistration");
   }
   return $res;
}

function eme_invert_booking_paid($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   
   $booking=eme_get_booking($booking_id);
   $where = array();
   $where['booking_id'] = $booking['booking_id'];
   if ($booking['booking_paid']==1)
      $fields['booking_paid']= 0;
   else
      $fields['booking_paid']= 1;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;
   return $res;
}

function eme_revert_booking_approval($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   
   $where = array();
   $where['booking_id'] = $booking_id;
   $fields['booking_approved']= 0;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;
   return $res;
}

function eme_remove_from_waitinglist($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 

   $where = array();
   $fields = array();
   $where['booking_id'] = $booking_id;
   $fields['waitinglist'] = 0;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else
      return true;
   //$sql = "UPDATE $bookings_table SET booking_approved='1' WHERE booking_id = $booking_id";
   //$wpdb->query($sql);
   //return __('Booking approved', 'events-made-easy');
}

function eme_move_on_waitinglist($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 

   $where = array();
   $fields = array();
   $where['booking_id'] = $booking_id;
   $fields['waitinglist'] = 1;
   $fields['booking_approved'] = 0;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else
      return true;
}

function eme_approve_booking($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 

   $where = array();
   $fields = array();
   $where['booking_id'] = $booking_id;
   $where['waitinglist'] = 0;
   $fields['booking_approved'] = 1;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      return false;
   else
      return true;
   //$sql = "UPDATE $bookings_table SET booking_approved='1' WHERE booking_id = $booking_id";
   //$wpdb->query($sql);
   //return __('Booking approved', 'events-made-easy');
}

function eme_update_booking($booking_id,$seats,$booking_price,$comment="") {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $where = array();
   $fields = array();
   $where['booking_id'] =$booking_id;

   # if it is a multi-price event, the total number of seats is the sum of the other ones
   if (eme_is_multi($booking_price)) {
      $fields['booking_seats']=0;
      # make sure the correct amount of seats is defined for multiprice
      $booking_prices_mp=eme_convert_multi2array($booking_price);
      $booking_seats_mp=eme_convert_multi2array($seats);
      foreach ($booking_prices_mp as $key=>$value) {
         if (!isset($booking_seats_mp[$key]))
            $booking_seats_mp[$key] = 0;
         $fields['booking_seats'] += intval($booking_seats_mp[$key]);
      }
      $fields['booking_seats_mp'] = eme_convert_array2multi($booking_seats_mp);
   } else {
      $fields['booking_seats'] = intval($seats);
   }
   $fields['booking_comment']=$comment;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   if ($wpdb->update($bookings_table, $fields, $where) === false)
      $res=false;
   else
      $res=true;

   if ($res) {
      $booking=eme_get_booking($booking_id);
      $event=eme_get_event($booking['event_id']);
      eme_delete_answers($booking_id);
      eme_booking_answers($event,$booking);
      eme_booking_discount($event,$booking);
   
      // now that everything is (or should be) correctly entered in the db, execute possible actions for the booking
      if (has_action('eme_update_rsvp_action')) {
         do_action('eme_update_rsvp_action',$booking);
      }
   }
   return $res;
}

function eme_get_available_seats($event_id) {
   $event = eme_get_event($event_id);
   if (eme_is_multi($event['event_seats']))
      return array_sum(eme_get_available_multiseats($event_id));

   if ($event['event_properties']['ignore_pending'] == 1)
      $available_seats = $event['event_seats'] - eme_get_approved_seats($event_id);
   else
      $available_seats = $event['event_seats'] - eme_get_booked_seats($event_id);
   // the number of seats left can be <0 if more than one booking happened at the same time and people fill in things slowly
   if ($available_seats<0) $available_seats=0;
   return $available_seats;
}

function eme_get_available_multiseats($event_id) {
   $event = eme_get_event($event_id);
   $multiseats = eme_convert_multi2array($event['event_seats']);
   $available_seats=array();
   if ($event['event_properties']['ignore_pending'] == 1) {
      $used_multiseats=eme_get_approved_multiseats($event_id);
   } else {
      $used_multiseats=eme_get_booked_multiseats($event_id);
   }
   foreach ($multiseats as $key=>$value) {
      if (isset($used_multiseats[$key]))
         $available_seats[$key] = $value - $used_multiseats[$key];
      else
         $available_seats[$key] = $value;
      // the number of seats left can be <0 if more than one booking happened at the same time and people fill in things slowly
      if ($available_seats[$key]<0) $available_seats[$key]=0;
   }
   return $available_seats;
}

function eme_get_booked_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_booked_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_booked_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_approved_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_approved_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d and booking_approved=1",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_approved_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d and booking_approved=1",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_pending_seats($event_id) {
   global $wpdb; 
   if (eme_is_event_multiseats($event_id))
      return array_sum(eme_get_pending_multiseats($event_id));
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_pending_multiseats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_seats_mp FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   $booking_seats_mp = $wpdb->get_col($sql);
   $result=array();
   foreach($booking_seats_mp as $booked_seats) {
      $multiseats = eme_convert_multi2array($booked_seats);
      foreach ($multiseats as $key=>$value) {
         if (!isset($result[$key]))
            $result[$key]=$value;
         else
            $result[$key]+=$value;
      }
   }
   return $result;
}

function eme_get_pending_bookings($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COUNT(*) AS pending_bookings FROM $bookings_table WHERE event_id = %d and booking_approved=0",$event_id);
   return $wpdb->get_var($sql);
}

function eme_are_seats_available_for($event_id, $seats) {
   $available_seats = eme_get_available_seats($event_id);
   $remaining_seats = $available_seats - $seats;
   return ($remaining_seats >= 0);
} 

function eme_are_multiseats_available_for($event_id, $multiseats) {
   $available_seats = eme_get_available_multiseats($event_id);
   foreach ($available_seats as $key=> $value) {
   	$remaining_seats = $value - $multiseats[$key];
      if ($remaining_seats<0)
         return 0;
   }
   return 1;
} 
 
function eme_bookings_compact_table($event_id) {
   $bookings =  eme_get_bookings_for($event_id);
   $destination = admin_url("edit.php"); 
   $available_seats = eme_get_available_seats($event_id);
   $approved_seats = eme_get_approved_seats($event_id);
   $pending_seats = eme_get_pending_seats($event_id);
   $booked_seats = eme_get_booked_seats($event_id);
   if (eme_is_event_multiseats($event_id)) {
	   $available_seats_ms=eme_convert_array2multi(eme_get_available_multiseats($event_id));
	   $approved_seats_ms=eme_convert_array2multi(eme_get_approved_multiseats($event_id));
	   $booked_seats_ms=eme_convert_array2multi(eme_get_booked_multiseats($event_id));
	   $pending_seats_ms=eme_convert_array2multi(eme_get_pending_multiseats($event_id));
	   if ($pending_seats>0) {
		   $booked_seats_info="$booked_seats: $booked_seats_ms ($approved_seats_ms ".__('approved','events-made-easy').", $pending_seats_ms ".__('pending','events-made-easy');
	   } else {
	      $booked_seats_info="$booked_seats: $booked_seats_ms";
	   }
	   $available_seats_info="$available_seats: $available_seats_ms";
   } else {
	   if ($pending_seats>0) {
		   $booked_seats_info="$booked_seats ($approved_seats ".__('approved','events-made-easy').", $pending_seats ".__('pending','events-made-easy');
	   } else {
		   $booked_seats_info=$booked_seats;
	   }
	   $available_seats_info=$available_seats;
   }
   $count_bookings=count($bookings);
   if ($count_bookings>0) { 
      $printable_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_printable&amp;event_id=$event_id");
      $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=$event_id");
      $nonce = wp_create_nonce('eme_rsvp');
      $table = 
      "<div class='wrap'>
            <h4>$count_bookings ".__('bookings so far','events-made-easy').":</h4>
            <table id='eme-bookings-table-$nonce' class='widefat post fixed'>
               <thead>
                  <tr>
                     <th class='manage-column column-cb check-column' scope='col'>&nbsp;</th>
                     <th class='manage-column ' scope='col'>".__('Respondent', 'events-made-easy')."</th>
                     <th scope='col'>".__('Spaces', 'events-made-easy')."</th>
                  </tr>
               </thead>
               <tfoot>
                  <tr>
                     <th scope='row' colspan='2'>".__('Booked spaces','events-made-easy').":</th><td class='booking-result' id='booked-seats'>$booked_seats_info</td></tr>
                  <tr><th scope='row' colspan='2'>".__('Available spaces','events-made-easy').":</th><td class='booking-result' id='available-seats'>$available_seats_info</td>
                  </tr>
               </tfoot>
               <tbody>" ;
      foreach ($bookings as $booking) {
         $person  = eme_get_person ($booking['person_id']);
         $person_name = $person['lastname'];
         if (!empty($person['firstname'])) $person_name.= " ".$person['firstname'];
         ($booking['booking_comment']) ? $baloon = " <img src='".EME_PLUGIN_URL."images/baloon.png' title='".__('Comment:','events-made-easy')." ".$booking['booking_comment']."' alt='comment'/>" : $baloon = "";
         if (eme_is_event_multiprice($event_id))
            $booking_info = $booking['booking_seats'].': '.$booking['booking_seats_mp'];
         else
            $booking_info = $booking['booking_seats'];
         if (eme_event_needs_approval($event_id) && !$booking['booking_approved']) {
            $booking_info.=" ".__('(pending)','events-made-easy');
         }
         $table .= 
         "<tr id='booking-".$booking['booking_id']."'> 
            <td><a id='booking-check-".$booking['booking_id']."' class='bookingdelbutton'>X</a></td>
            <td><a title=\"".eme_esc_html($person['email'])." - ".eme_esc_html($person['phone'])."\">".eme_esc_html($person_name)."</a>$baloon</td>
            <td>$booking_info</td>
          </tr>";
      }
    
      $table .=  "</tbody>
         </table>
         </div>
         <br class='clear'/>
         <div id='major-publishing-actions'>
         <div id='publishing-action'> 
            <a id='printable'  target='' href='$printable_address'>".__('Printable view','events-made-easy')."</a>
            <br class='clear'/>
         </div>
         <div id='publishing-action-csv'> 
            <a id='printable'  target='' href='$csv_address'>".__('CSV export','events-made-easy')."</a>
            <br class='clear'/>
         </div>
         <br class='clear'/>
         </div> ";
   } else {
      $table = "<p><em>".__('No responses yet!','events-made-easy')."</em></p>";
   } 
   echo $table;
}

function eme_get_bookingids_for($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE event_id=%d",$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_bookings_for($event_ids,$pending_approved=0,$only_unpaid=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   
   $bookings = array();
   if (!$event_ids)
      return $bookings;
   
   if (is_array($event_ids) && eme_array_integers($event_ids)) {
      $where="event_id IN (".join(",",$event_ids).")";
   } elseif (is_numeric($event_ids)) {
      $where="event_id = $event_ids";
   } else {
      $where="event_id = 0";
   }
   $sql = "SELECT * FROM $bookings_table WHERE $where";
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpaid) {
      $sql .= " AND booking_paid=0";
   }
   $sql .= " ORDER BY booking_id";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_bookings_for_event_wp_id($event_id,$wp_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   
   $bookings = array();
   if (!$event_id || !$wp_id)
      return $bookings;
   
   $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE event_id = %d AND wp_id = %d", $event_id,$wp_id);
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_bookings($payment_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $bookings = array();
   if (!$payment_id)
      return $bookings;
   $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE payment_id = %d", $payment_id);
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_wp_ids_for($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT wp_id FROM $bookings_table WHERE event_id = %d AND wp_id != 0",$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_event_ids_for($wp_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE wp_id = %d AND wp_id != 0",$wp_id);
   return $wpdb->get_col($sql);
}

function eme_get_attendees_for($event_id,$pending_approved=0,$only_unpaid=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT person_id FROM $bookings_table WHERE event_id = %d",$event_id);
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpaid) {
      $sql .= " AND booking_paid=0";
   }

   $person_ids = $wpdb->get_col($sql);
   if ($person_ids) {
      $attendees = eme_get_persons($person_ids);
   } else {
      $attendees= array();
   }
   return $attendees;
}

function eme_get_attendees_list_for($event,$template_id=0,$template_id_header=0,$template_id_footer=0) {
   $ignore_pending=get_option('eme_attendees_list_ignore_pending');
   $attendees = eme_get_attendees_for($event['event_id'],$ignore_pending);
   $format=get_option('eme_attendees_list_format');
   $eme_format_header=DEFAULT_BOOKINGS_LIST_HEADER_FORMAT;
   $eme_format_footer=DEFAULT_BOOKINGS_LIST_FOOTER_FORMAT;

   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   
   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   if ($template_id_header) {
      $format_header = eme_get_template_format($template_id_header);
      $eme_format_header=eme_replace_placeholders($format_header, $event);
   }
   if ($template_id_footer) {
      $format_footer = eme_get_template_format($template_id_footer);
      $eme_format_footer=eme_replace_placeholders($format_footer, $event);
   }

   if ($attendees) {
      $res=$eme_format_header;
      // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_attendees_placeholders finish and that will do it
      $format = eme_replace_placeholders($format, $event, "html", 0);
      foreach ($attendees as $attendee) {
         $res.=eme_replace_attendees_placeholders($format,$event,$attendee);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','events-made-easy')."</p>";
   }
   return $res;
}

function eme_get_bookings_list_for_event($event,$template_id=0,$template_id_header=0,$template_id_footer=0,$wp_id=0) {
   $ignore_pending=get_option('eme_bookings_list_ignore_pending');
   if ($wp_id)
	   $bookings=eme_get_bookings_for_event_wp_id($event['event_id'],$wp_id);
   else
	   $bookings=eme_get_bookings_for($event['event_id'],$ignore_pending);
   $format=get_option('eme_bookings_list_format');
   $eme_format_header=get_option('eme_bookings_list_header_format');
   $eme_format_footer=get_option('eme_bookings_list_footer_format');

   // rsvp not active or no rsvp for this event, then return
   if (!eme_is_event_rsvp($event)) {
      return;
   }
   
   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   if ($template_id_header) {
      $format_header = eme_get_template_format($template_id_header);
      $eme_format_header=eme_replace_placeholders($format_header, $event);
   }
   if ($template_id_footer) {
      $format_footer = eme_get_template_format($template_id_footer);
      $eme_format_footer=eme_replace_placeholders($format_footer, $event);
   }

   if ($bookings) {
      $res=$eme_format_header;
      // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
      $format = eme_replace_placeholders($format, $event, "html", 0);
      foreach ($bookings as $booking) {
         $res.= eme_replace_booking_placeholders($format,$event,$booking);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','events-made-easy')."</p>";
   }
   return $res;
}

function eme_get_bookings_list_for_wp_id($wp_id,$future=0,$template="",$template_id=0,$template_id_header=0,$template_id_footer=0,$approval_status=0) {
   $bookings=eme_get_bookings_by_wp_id($wp_id, $future,$approval_status);

   if ($template) {
      $format=$template;
      $eme_format_header="";
      $eme_format_footer="";
   } else {
      $format=get_option('eme_bookings_list_format');
      $eme_format_header=get_option('eme_bookings_list_header_format');
      $eme_format_footer=get_option('eme_bookings_list_footer_format');
   }

   if ($template_id) {
      $format = eme_get_template_format($template_id);
   }

   // header and footer can't contain per booking info, so we don't replace booking placeholders there
   // but for a person, no event info in header/footer either, so no replacement at all
   if ($template_id_header) {
      $eme_format_header = eme_get_template_format($template_id_header);
   }
   if ($template_id_footer) {
      $eme_format_footer = eme_get_template_format($template_id_footer);
   }

   if ($bookings) {
      $res=$eme_format_header;
      foreach ($bookings as $booking) {
         $event = eme_get_event($booking['event_id']);
      	// don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
      	$tmp_format = eme_replace_placeholders($format, $event, "html", 0);
         $res.= eme_replace_booking_placeholders($tmp_format,$event,$booking);
      }
      $res.=$eme_format_footer;
   } else {
      $res="<p class='eme_no_bookings'>".__("No bookings found.",'events-made-easy')."</p>";
   }
   return $res;
}

function eme_replace_booking_placeholders($format, $event, $booking, $is_multibooking=0, $target="html",$lang='') {
   global $eme_timezone;

   preg_match_all("/#(ESC)?_?[A-Za-z0-9_]+(\{[A-Za-z0-9_]+\})?/", $format, $placeholders);
   $person  = eme_get_person ($booking['person_id']);
   $current_userid=get_current_user_id();
   $answers = eme_get_answers($booking['booking_id']);
   $payment_id = $booking['payment_id'];
   $payment = eme_get_payment($payment_id);

   usort($placeholders[0],'eme_sort_stringlenth');
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      $need_escape=0;
      $orig_result = $result;
      if (strstr($result,'#ESC')) {
         $result = str_replace("#ESC","#",$result);
         $need_escape=1;
      }
      if (preg_match('/#_RESPID/', $result)) {
         $replacement = $person['person_id'];
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_text', $replacement); 
      } elseif (preg_match('/#_RESP(NAME|LASTNAME|FIRSTNAME|ZIP|CITY|STATE|COUNTRY|ADDRESS1|ADDRESS2|PHONE|EMAIL)/', $result)) {
         $field = preg_replace("/#_RESP/","",$result);
         $field = strtolower($field);
         if ($field=="name") $field="lastname";
         $replacement = $person[$field];
         if ($target == "html") {
            $replacement = eme_esc_html($replacement);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_RESPNICKNAME$/', $result)) {
         if ($person['wp_id']>0) {
            $user = get_userdata( $person['wp_id']);
            if ($user)
               $replacement=$user->user_nicename;
            if ($target == "html") {
               $replacement = eme_esc_html($replacement);
               $replacement = apply_filters('eme_general', $replacement); 
   	    } else {
               $replacement = apply_filters('eme_text', $replacement); 
	    }
         }
      } elseif (preg_match('/#_RESPDISPNAME$/', $result)) {
         if ($person['wp_id']>0) {
            $user = get_userdata( $person['wp_id']);
            if ($user)
               $replacement=$user->display_name;
            if ($target == "html") {
               $replacement = eme_esc_html($replacement);
               $replacement = apply_filters('eme_general', $replacement); 
	    } else {
               $replacement = apply_filters('eme_text', $replacement); 
	    }
         }
      } elseif (preg_match('/#_(RESPCOMMENT|COMMENT)/', $result)) {
         $replacement = $booking['booking_comment'];
         if ($target == "html") {
            $replacement = eme_esc_html($replacement);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = apply_filters('eme_text', $replacement); 
	 }
       } elseif (preg_match('/#_RESPCANCELCOMMENT/', $result)) {
         if (isset($_POST['cancelcomment']))
             $replacement = eme_strip_tags($_POST['cancelcomment']);
         if ($target == "html") {
            $replacement = eme_esc_html($replacement);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_RESPSPACES\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1])-1;
         if (eme_is_multi($booking['booking_price'])) {
             $seats=eme_convert_multi2array($booking['booking_seats_mp']);
             if (array_key_exists($field_id,$seats))
                $replacement = $seats[$field_id];
         }
      } elseif (preg_match('/#_TOTALPRICE$/', $result)) {
         $price = eme_get_total_booking_price($booking);
         $replacement = eme_localized_price($price,$event['currency'],$target);
      } elseif (preg_match('/#_TOTALDISCOUNT$/', $result)) {
         $replacement = eme_localized_price($booking['discount'],$event['currency'],$target);
      } elseif (preg_match('/#_BOOKINGPRICEPERSEAT$/', $result)) {
         $price = eme_get_seat_booking_price($booking);
         $replacement = eme_localized_price($price,$event['currency'],$target);
      } elseif (preg_match('/#_BOOKINGPRICEPERSEAT\{(\d+)\}/', $result, $matches)) {
         // total price to pay per price if multiprice
         $total_prices=eme_get_seat_booking_multiprice_arr($booking);
         $field_id = intval($matches[1])-1;
         if (array_key_exists($field_id,$total_prices)) {
            $price = $total_prices[$field_id];
            $replacement = eme_localized_price($price,$event['currency'],$target);
         }
       } elseif (preg_match('/#_TOTALPRICE\{(\d+)\}/', $result, $matches)) {
         // total price to pay per price if multiprice
         $total_prices=eme_get_total_booking_multiprice_arr($booking);
         $field_id = intval($matches[1])-1;
         if (array_key_exists($field_id,$total_prices)) {
            $price = $total_prices[$field_id];
            $replacement = eme_localized_price($price,$event['currency'],$target);
         }
      } elseif (preg_match('/#_CHARGE\{(.+)\}$/', $result, $matches)) {
         $price = eme_get_total_booking_price($booking);
         $replacement = eme_localized_price(eme_payment_gateway_extra_charge($price,$matches[1]),$event['currency'],$target);
      } elseif (preg_match('/#_RESPSPACES$/', $result)) {
         $replacement = eme_get_total($booking['booking_seats']);
      } elseif (preg_match('/#_BOOKINGCREATIONDATE/', $result)) {
         $replacement = eme_localized_date($booking['creation_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGMODIFDATE/', $result)) {
         $replacement = eme_localized_date($booking['modif_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGCREATIONTIME/', $result)) {
         $replacement = eme_localized_time($booking['creation_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGMODIFTIME/', $result)) {
         $replacement = eme_localized_time($booking['modif_date']." ".$eme_timezone);
      } elseif (preg_match('/#_BOOKINGID/', $result)) {
         $replacement = $booking['booking_id'];
      } elseif (preg_match('/#_TRANSFER_NBR_BE97/', $result)) {
         $replacement = $booking['transfer_nbr_be97'];
      } elseif (preg_match('/#_BOOKINGFIELD\{(.+)\}/', $result, $matches)) {
         $tmp_attkey=$matches[1];
         if (isset($booking[$tmp_attkey]) && !is_array($booking[$tmp_attkey])) {
            $replacement = $booking[$tmp_attkey];
            if ($target == "html") {
               $replacement = eme_trans_esc_html($replacement,$lang);
               $replacement = apply_filters('eme_general', $replacement);
            } elseif ($target == "rss")  {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_general_rss', $replacement);
            } else {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_text', $replacement);
            }
         }
       } elseif (preg_match('/#_RESPFIELD\{(.+)\}/', $result, $matches)) {
         $tmp_attkey=$matches[1];
         if (isset($person[$tmp_attkey]) && !is_array($person[$tmp_attkey])) {
            $replacement = $person[$tmp_attkey];
            if ($target == "html") {
               $replacement = eme_trans_esc_html($replacement,$lang);
               $replacement = apply_filters('eme_general', $replacement);
            } elseif ($target == "rss")  {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_general_rss', $replacement);
            } else {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_text', $replacement);
            }
         }
      } elseif (preg_match('/#_PAYMENT_URL/', $result)) {
         if ($payment_id && eme_event_can_pay_online($event))
            $replacement = eme_payment_url($payment_id);
      } elseif (preg_match('/#_CANCEL_URL$/', $result)) {
         $replacement = eme_cancel_url($payment['random_id']);
      } elseif (preg_match('/#_CANCEL_LINK$/', $result)) {
         $url = eme_cancel_url($payment['random_id']);
         $replacement="<a href='$url'>".__('Cancel booking','events-made-easy')."</a>";
      } elseif (preg_match('/#_CANCEL_OWN_URL$/', $result)) {
         if ($booking['wp_id']==$current_userid || $event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid)
            $replacement = eme_cancel_url($payment['random_id']);
      } elseif (preg_match('/#_CANCEL_OWN_LINK$/', $result)) {
         if ($booking['wp_id']==$current_userid || $event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid) {
            $url = eme_cancel_url($payment['random_id']);
            $replacement="<a href='$url'>".__('Cancel booking','events-made-easy')."</a>";
         }
      } elseif (preg_match('/#_CANCEL_CODE$/', $result)) {
         $replacement = $payment['random_id'];
      } elseif (preg_match('/#_FIELDS/', $result)) {
         $field_replace = "";
         foreach ($answers as $answer) {
            $tmp_answer=eme_convert_answer2tag($answer);
            $field_replace.=$answer['field_name'].": $tmp_answer\n";
         }
         if ($target == "html") {
            $replacement = eme_trans_esc_html($field_replace,$lang);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = eme_translate($field_replace,$lang);
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_PAYED/', $result)) {
         $replacement = ($booking['booking_paid'])? __('Yes', 'events-made-easy') : __('No', 'events-made-easy');
      } elseif (preg_match('/#_IS_PAYED/', $result)) {
         $replacement = ($booking['booking_paid'])? 1 : 0;
      } elseif (preg_match('/#_ON_WAITINGLIST/', $result)) {
         $replacement = ($booking['waitinglist'])? 1 : 0;
      } elseif (preg_match('/#_FIELDNAME\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         if ($target == "html") {
            $replacement = eme_trans_esc_html($formfield['field_name'],$lang);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = eme_translate($formfield['field_name'],$lang);
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_FIELD\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $field_replace = "";
         foreach ($answers as $answer) {
            if ($answer['field_id'] == $field_id) {
               $tmp_answer=eme_convert_answer2tag($answer);
               $field_replace=$tmp_answer;
            }
         }
         if ($target == "html") {
            $replacement = eme_trans_esc_html($field_replace,$lang);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = eme_translate($field_replace,$lang);
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_FIELDVALUE\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $field_replace = "";
         foreach ($answers as $answer) {
            if ($answer['field_id'] == $field_id) {
               if (is_array($answer['answer']))
                  $tmp_answer=eme_convert_array2multi($answer['answer']);
               else
                  $tmp_answer=$answer['answer'];
               $field_replace=$tmp_answer;
            }
         }
         if ($target == "html") {
            $replacement = eme_trans_esc_html($field_replace,$lang);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = eme_translate($field_replace,$lang);
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_MULTIBOOKING_SEATS$/', $result)) {
         if ($is_multibooking) {
            // returns the total of all seats for all bookings in the payment id related to this booking
            $replacement = eme_get_payment_seats($payment_id);
         }
      } elseif (preg_match('/#_MULTIBOOKING_TOTALPRICE$/', $result)) {
         if ($is_multibooking) {
            // returns the price for all bookings in the payment id related to this booking
            $price = eme_get_payment_price($payment_id);
            $replacement = sprintf("%01.2f",$price);
         }
      } elseif (preg_match('/#_MULTIBOOKING_DETAILS_TEMPLATE\{(\d+)\}$/', $result, $matches)) {
         $template_id = intval($matches[1]);
         $template=eme_get_template_format($template_id);
         $res="";
         if ($template && $is_multibooking) {
            // don't let eme_replace_placeholders replace other shortcodes yet, let eme_replace_booking_placeholders finish and that will do it
            $bookings = eme_get_bookings($payment_id);
            foreach ($bookings as $tmp_booking) {
               $tmp_event = eme_get_event_by_booking_id($tmp_booking['booking_id']);
               $tmp_res = eme_replace_placeholders($template, $tmp_event, "text", 0);
               $res .= eme_replace_booking_placeholders($tmp_res,$tmp_event,$tmp_booking,$is_multibooking,"text")."\n";
            }
         }
         $replacement = $res;
      } elseif (preg_match('/#_IS_MULTIBOOKING/', $result)) {
         $replacement=$is_multibooking;
       } else {
         $found = 0;
      }

      if ($found) {
         if ($need_escape)
            $replacement = eme_sanitize_request(eme_esc_html(preg_replace('/\n|\r/','',$replacement)));
         $format = str_replace($orig_result, $replacement ,$format );
      }
   }

   // now, replace any language tags found in the format itself
   $format = eme_translate($format,$lang);

   return do_shortcode($format);   
}

function eme_replace_attendees_placeholders($format, $event, $attendee, $target="html", $lang='') {
   preg_match_all("/#_?[A-Za-z0-9_]+(\{.*?\})?(\{.*?\})?/", $format, $placeholders);

   usort($placeholders[0],'eme_sort_stringlenth');
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      $orig_result = $result;
      if (preg_match('/#_(ATTEND)?ID/', $result)) {
         $replacement = $attendee['person_id'];
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_text', $replacement); 
      } elseif (preg_match('/#_(ATTEND)?(NAME|LASTNAME|FIRSTNAME|ZIP|CITY|STATE|COUNTRY|ADDRESS1|ADDRESS2|PHONE|EMAIL)/', $result)) {
         $field = preg_replace("/#_ATTEND|#_/","",$result);
         $field = strtolower($field);
         if ($field=="name") $field="lastname";
         $replacement = $attendee[$field];
         if ($target == "html") {
            $replacement = eme_esc_html($replacement);
            $replacement = apply_filters('eme_general', $replacement); 
	 } else {
            $replacement = apply_filters('eme_text', $replacement); 
	 }
      } elseif (preg_match('/#_ATTENDSPACES$/', $result)) {
         $replacement = eme_get_booked_seats_by_person_event_id($attendee['person_id'],$event['event_id']);
      } elseif (preg_match('/#_ATTENDSPACES\{(\d+)\}$/', $result, $matches)) {
         $field_id = intval($matches[1])-1;
         $replacement = 0;
         if (eme_is_multi($event['event_seats'])) {
	    $seats = eme_get_booked_multiseats_by_person_event_id($attendee['person_id'],$event['event_id']);
            if (array_key_exists($field_id,$seats))
               $replacement = $seats[$field_id];
         }
      } elseif (preg_match('/#_ATTENDNICKNAME$/', $result)) {
         if ($attendee['wp_id']>0) {
            $user = get_userdata( $attendee['wp_id']);
            if ($user)
               $replacement=$user->user_nicename;
            if ($target == "html") {
               $replacement = eme_esc_html($replacement);
               $replacement = apply_filters('eme_general', $replacement); 
	    } else {
               $replacement = apply_filters('eme_text', $replacement); 
	    }
         }
      } elseif (preg_match('/#_ATTENDDISPNAME$/', $result)) {
         if ($attendee['wp_id']>0) {
            $user = get_userdata( $attendee['wp_id']);
            if ($user)
               $replacement=$user->display_name;
            if ($target == "html") {
               $replacement = eme_esc_html($replacement);
               $replacement = apply_filters('eme_general', $replacement); 
	    } else {
               $replacement = apply_filters('eme_text', $replacement); 
	    }
         }
      } elseif (preg_match('/#_ATTENDFIELD\{(.+)\}/', $result, $matches)) {
         $tmp_attkey=$matches[1];
         if (isset($attendee[$tmp_attkey]) && !is_array($attendee[$tmp_attkey])) {
            $replacement = $attendee[$tmp_attkey];
            if ($target == "html") {
               $replacement = eme_trans_esc_html($replacement,$lang);
               $replacement = apply_filters('eme_general', $replacement);
            } elseif ($target == "rss")  {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_general_rss', $replacement);
            } else {
               $replacement = eme_translate($replacement,$lang);
               $replacement = apply_filters('eme_text', $replacement);
            }
         }
      } else {
         $found = 0;
      }
      if ($found)
         $format = str_replace($orig_result, $replacement ,$format );
   }

   // now, replace any language tags found in the format itself
   $format = eme_translate($format,$lang);

   return do_shortcode($format);   
}

function eme_email_rsvp_booking($booking,$action, $is_multibooking=0) {
   // first check if a mail should be send at all
   $mailing_is_active = get_option('eme_rsvp_mail_notify_is_active');
   if (!$mailing_is_active) return;

   $mailing_pending = get_option('eme_rsvp_mail_notify_pending');
   if ($booking['booking_approved']==0 && !$mailing_pending && $action!= 'ipnReceived') return;

   $mailing_approved = get_option('eme_rsvp_mail_notify_approved');
   if ($booking['booking_approved']==1 && !$mailing_approved && $action!= 'ipnReceived') return;

   $person = eme_get_person ($booking['person_id']);
   $event = eme_get_event($booking['event_id']);
   $contact = eme_get_contact ($event);
   $contact_email = $contact->user_email;
   $contact_name = $contact->display_name;
   $mail_text_html=get_option('eme_rsvp_send_html')?"html":"text";
   
   $booker_body_vars=array('confirmed_body','updated_body','pending_body','denied_body','cancelled_body','pending_reminder_body');
   $booker_subject_vars=array('confirmed_subject','updated_subject','pending_subject','denied_subject','cancelled_subject','pending_reminder_subject');
   $contact_body_vars=array('contact_body','contact_cancelled_body','contact_pending_body','contact_ipn_body');
   $contact_subject_vars=array('contact_subject','contact_cancelled_subject','contact_pending_subject','contact_ipn_subject');

   // first get the initial values
   $confirmed_subject = get_option('eme_respondent_email_subject' );
   if (!empty($event['event_respondent_email_body']))
      $confirmed_body = $event['event_respondent_email_body'];
   elseif ($event['event_properties']['event_respondent_email_body_tpl']>0)
      $confirmed_body = eme_get_template_format($event['event_properties']['event_respondent_email_body_tpl']);
   else
      $confirmed_body = get_option('eme_respondent_email_body' );

   $pending_subject = get_option('eme_registration_pending_email_subject' );
   if (!empty($event['event_registration_pending_email_body']))
      $pending_body = $event['event_registration_pending_email_body'];
   elseif ($event['event_properties']['event_registration_pending_email_body_tpl']>0)
      $pending_body = eme_get_template_format($event['event_properties']['event_registration_pending_email_body_tpl']);
   else
      $pending_body = get_option('eme_registration_pending_email_body' );
   $pending_reminder_subject=eme_get_template_format(intval(get_option('eme_cron_reminder_unpaid_subject')));
   $pending_reminder_body=eme_get_template_format(intval(get_option('eme_cron_reminder_unpaid_body')));

   $denied_subject = get_option('eme_registration_denied_email_subject' );
   if (!empty($event['event_registration_denied_email_body']))
      $denied_body = $event['event_registration_denied_email_body'];
   elseif ($event['event_properties']['event_registration_denied_email_body_tpl']>0)
      $denied_body = eme_get_template_format($event['event_properties']['event_registration_denied_email_body_tpl']);
   else
      $denied_body = get_option('eme_registration_denied_email_body' );

   $updated_subject = get_option('eme_registration_updated_email_subject' );
   if (!empty($event['event_registration_updated_email_body']))
      $updated_body = $event['event_registration_updated_email_body'];
   elseif ($event['event_properties']['event_registration_updated_email_body_tpl']>0)
      $updated_body = eme_get_template_format($event['event_properties']['event_registration_updated_email_body_tpl']);
   else
      $updated_body = get_option('eme_registration_updated_email_body' );

   $cancelled_subject = get_option('eme_registration_cancelled_email_subject' );
   if (!empty($event['event_registration_cancelled_email_body']))
      $cancelled_body = $event['event_registration_cancelled_email_body'];
   elseif ($event['event_properties']['event_registration_cancelled_email_body_tpl']>0)
      $cancelled_body = eme_get_template_format($event['event_properties']['event_registration_cancelled_email_body_tpl']);
   else
      $cancelled_body = get_option('eme_registration_cancelled_email_body' );

   $contact_subject = get_option('eme_contactperson_email_subject' );
   if (!empty($event['event_contactperson_email_body']))
      $contact_body = $event['event_contactperson_email_body'];
   elseif ($event['event_properties']['event_contactperson_email_body_tpl']>0)
      $contact_body = eme_get_template_format($event['event_properties']['event_contactperson_email_body_tpl']);
   else
      $contact_body = get_option('eme_contactperson_email_body' );
   $contact_cancelled_subject = get_option('eme_contactperson_cancelled_email_subject' );
   $contact_cancelled_body = get_option('eme_contactperson_cancelled_email_body' );
   $contact_pending_subject = get_option('eme_contactperson_pending_email_subject' );
   $contact_pending_body = get_option('eme_contactperson_pending_email_body' );
   $contact_ipn_subject = get_option('eme_contactperson_ipn_email_subject' );
   $contact_ipn_body = get_option('eme_contactperson_ipn_email_body' );

   // replace needed placeholders
   foreach ($contact_subject_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, "text",0);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, "text");
      // possible translations are handled last 
      $$var = eme_translate($$var);
      $filtername='eme_rsvp_email_'.$mail_text_html.'_'.$var.'_filter';
      if (has_filter($filtername))
         $$var=apply_filters($filtername,$$var);
   }
   foreach ($contact_body_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, $mail_text_html,0);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, $mail_text_html);
      // possible translations are handled last 
      $$var = eme_translate($$var);
      $filtername='eme_rsvp_email_'.$mail_text_html.'_'.$var.'_filter';
      if (has_filter($filtername))
         $$var=apply_filters($filtername,$$var);
   }
   foreach ($booker_subject_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, "text",0,$booking['lang']);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, "text",$booking['lang']);
      // possible translations are handled last 
      $$var = eme_translate($$var,$booking['lang']);
      $filtername='eme_rsvp_email_'.$mail_text_html.'_'.$var.'_filter';
      if (has_filter($filtername))
         $$var=apply_filters($filtername,$$var);
   }
   foreach ($booker_body_vars as $var) {
      $$var = eme_replace_placeholders($$var, $event, $mail_text_html,0,$booking['lang']);
      $$var = eme_replace_booking_placeholders($$var, $event, $booking, $is_multibooking, $mail_text_html,$booking['lang']);
      // possible translations are handled last 
      $$var = eme_translate($$var,$booking['lang']);
      $filtername='eme_rsvp_email_'.$mail_text_html.'_'.$var.'_filter';
      if (has_filter($filtername))
         $$var=apply_filters($filtername,$$var);
   }

   // possible mail body filter: eme_rsvp_email_body_text_filter or eme_rsvp_email_body_html_filter
   $filtername='eme_rsvp_email_body_'.$mail_text_html.'_filter';
   if (has_filter($filtername)) {
      foreach ($contact_body_vars as $var) {
         $$var=apply_filters($filtername,$$var);
      }
      foreach ($booker_body_vars as $var) {
         $$var=apply_filters($filtername,$$var);
      }
   }

   // and now send the wanted mails
   $person_name=$person['lastname'].' '.$person['firstname'];
   if ($action == 'approveRegistration' && $event['registration_requires_approval']) {
      // can only be called from within the backend interface, to switch from pending to approved
      // so we don't send the mail to the event contact 
      return eme_send_mail($confirmed_subject,$confirmed_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'pendingRegistration' && $event['registration_requires_approval']) {
      // can only be called from within the backend interface, to switch from approved to pending
      // so we don't send the mail to the event contact 
      return eme_send_mail($pending_subject,$pending_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'denyRegistration') {
      return eme_send_mail($denied_subject,$denied_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'updateRegistration') {
      return eme_send_mail($updated_subject,$updated_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif ($action == 'cancelRegistration') {
      eme_send_mail($cancelled_subject,$cancelled_body, $person['email'], $person_name, $contact_email, $contact_name);
      return eme_send_mail($contact_cancelled_subject, $contact_cancelled_body, $contact_email, $contact_name, $contact_email, $contact_name);
   } elseif ($action == 'ipnReceived') {
      return eme_send_mail($contact_ipn_subject, $contact_ipn_body, $contact_email,$contact_name, $contact_email, $contact_name);
   } elseif ($action == 'pendingReminder') {
      return eme_send_mail($pending_reminder_subject,$pending_reminder_body, $person['email'], $person_name, $contact_email, $contact_name);
   } elseif (empty($action)) {
      // send different mails depending on approval or not
      if ($event['registration_requires_approval'] || $booking['waitinglist']) {
         eme_send_mail($contact_pending_subject, $contact_pending_body, $contact_email, $contact_name, $contact_email, $contact_name);
         return eme_send_mail($pending_subject,$pending_body, $person['email'], $person_name, $contact_email, $contact_name);
      } else {
         eme_send_mail($contact_subject, $contact_body, $contact_email,$contact_name, $contact_email, $contact_name);
         return eme_send_mail($confirmed_subject,$confirmed_body, $person['email'], $person_name, $contact_email, $contact_name);
      }
   }
}

function eme_registration_approval_page() {
   eme_registration_seats_page(1);
}

function eme_registration_seats_page($pending=0) {
   global $plugin_page,$eme_timezone;

   // do the actions if required
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "editRegistration" && isset($_GET['booking_id'])) {
      check_admin_referer('eme_rsvp','eme_admin_nonce');
      $booking_id = intval($_GET['booking_id']);
      $booking = eme_get_booking($booking_id);
      $event_id = $booking['event_id'];
      $nonce_field = wp_nonce_field('eme_rsvp','eme_admin_nonce',false,false);
      $event = eme_get_event($event_id);
      // we need to set the action url, otherwise the GET parameters stay and we will fall in this if-statement all over again
      $action_url = admin_url("admin.php?page=$plugin_page");
      $ret_string = "<form id='eme-rsvp-form' name='booking-form' method='post' action='$action_url'>";
      $ret_string.= $nonce_field;
      $ret_string.= __('Send mails for changed registration?','events-made-easy') . eme_ui_select_binary(1,"send_mail");
      $ret_string.= "<br />".__('Move booking to event','events-made-easy');
      $ret_string.= "<input type='hidden' id='transferto_id' name='transferto_id'>";
      $ret_string.= "<input type='text' id='chooseevent' name='chooseevent' class='clearable' placeholder='".__('Start typing an event name','events-made-easy')."'>";
      $ret_string.= eme_replace_formfields_placeholders ($event,$booking);
      $ret_string .= "
         <input type='hidden' name='eme_admin_action' value='updateRegistration' />
         <input type='hidden' id='booking_id' name='booking_id' value='$booking_id' />
         <input type='hidden' id='event_id' name='event_id' value='$event_id' />
         </form>";
      print $ret_string;
      return;
   } else {
      $action = isset($_REQUEST ['eme_admin_action']) ? $_REQUEST ['eme_admin_action'] : '';
      $send_mail = isset($_REQUEST ['send_mail']) ? intval($_REQUEST ['send_mail']) : 1;
      if (!empty($action))
         check_admin_referer('eme_rsvp','eme_admin_nonce');
      if ($action == 'newRegistration') {
         $event_id = intval($_REQUEST['event_id']);
         $event = eme_get_event($event_id);
         $nonce_field = wp_nonce_field('eme_rsvp','eme_admin_nonce',false,false);
         $ret_string = "<form id='eme-rsvp-form' name='booking-form' method='post' action=''>";
         $ret_string.= $nonce_field;
         $ret_string.= __('Send mails for new registration?','events-made-easy') . eme_ui_select_binary(1,"send_mail");
         $new_booking=eme_new_booking();
         $ret_string.= eme_replace_formfields_placeholders ($event,$new_booking);
         $ret_string .= "
            <input type='hidden' name='eme_admin_action' value='addRegistration' />
            <input type='hidden' name='event_id' value='$event_id' />
            </form>";
         print $ret_string;
         return;
      } elseif ($action == 'addRegistration') {
         $event_id = intval($_POST['event_id']);
         $event = eme_get_event($event_id);
         $booking_res = eme_book_seats($event, $send_mail);
         $result=$booking_res[0];
         $payment_id=$booking_res[1];
         if (!$payment_id) {
            print "<div id='message' class='error'><p>$result</p></div>";
         } else {
            print "<div id='message' class='updated notice is-dismissible'><p>".__("Booking has been recorded",'events-made-easy')."</p></div>";
         }
      } elseif ($action == 'updateRegistration') {
         $booking_id = intval($_POST['booking_id']);
         $transferto_id = isset($_POST ['transferto_id']) ? intval($_POST ['transferto_id']) : 0;
         $booking = eme_get_booking ($booking_id);
         // transferto_id is only given for moving a booking to another event
         if ($transferto_id && $booking['event_id']!=$transferto_id)
            eme_move_booking_event($booking_id,$transferto_id);
         // now that the possible move is done, set the event id variable
         $event_id=$booking['event_id'];

         if (isset($_POST['comment']))
            $bookerComment = eme_sanitize_textarea($_POST['comment']);
         else
            $bookerComment = "";

         if (isset($_POST['bookings'][$event_id]['bookedSeats']))
            $bookedSeats = intval($_POST['bookings'][$event_id]['bookedSeats']);
         else
            $bookedSeats = 0;

         // for multiple prices, we have multiple booked Seats as well
         // the next foreach is only valid when called from the frontend
         $bookedSeats_mp = array();
         //if (eme_is_multi($event['price'])) {
         if (eme_is_multi($booking['booking_price'])) {
            // make sure the array contains the correct keys already, since
            // later on in the function eme_record_booking we do a join
            //$booking_prices_mp=eme_convert_multi2array($event['price']);
            $booking_prices_mp=eme_convert_multi2array($booking['booking_price']);
            foreach ($booking_prices_mp as $key=>$value) {
               $bookedSeats_mp[$key] = 0;
            }
            foreach($_POST['bookings'][$event_id] as $key=>$value) {
               if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
                  $field_id = intval($matches[1])-1;
                  $bookedSeats += $value;
                  $bookedSeats_mp[$field_id]=$value;
               }
            }
            eme_update_booking($booking_id,eme_convert_array2multi($bookedSeats_mp),$booking['booking_price'],$bookerComment);
         } else {
            eme_update_booking($booking_id,$bookedSeats,$booking['booking_price'],$bookerComment);
         }
         eme_update_person_with_postinfo($booking['person_id']);

         // now get the changed booking and send mail if wanted
         $booking = eme_get_booking ($booking_id);
         if ($send_mail) eme_email_rsvp_booking($booking,$action);
         print "<div id='message' class='updated notice is-dismissible'><p>".__("Booking updated",'events-made-easy')."</p></div>";
      }
   }

   // now show the menu
   eme_registration_seats_form_table($pending);
}

function eme_registration_seats_form_table($pending=0) {

   $scope_names = array ();
   $scope_names['past'] = __ ( 'Past events', 'events-made-easy');
   $scope_names['all'] = __ ( 'All events', 'events-made-easy');
   $scope_names['future'] = __ ( 'Future events', 'events-made-easy');

   $nonce_field = wp_nonce_field('eme_rsvp','eme_admin_nonce',false,false);

?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h1><?php 
   if ($pending) 
      _e ('Pending Approvals','events-made-easy');
   else
      _e ('Change reserved spaces or cancel registrations','events-made-easy');
   ?>
</h1>

   <div class="tablenav">
   <div class="alignleft">
   <form id="eme-admin-regsearchform" name="eme-admin-regsearchform" action="#" method="post">

   <?php
   if ($pending)
      echo '<input type="hidden" id="booking_status" name="booking_status" value="1">';
   else
      echo '<input type="hidden" id="booking_status" name="booking_status" value="2">';
   ?>
   
   <select id='scope' name='scope'>
   <?php
   foreach ( $scope_names as $key => $value ) {
      $selected = "";
      if ($key == "future")
         $selected = "selected='selected'";
      echo "<option value='$key' $selected>$value</option>  ";
   }
   ?>
   </select>

   <input type="text" name="search_event" id="search_event" placeholder="<?php _e('Filter on event','events-made-easy'); ?>" size=15 />
   <input id="eme_localized_start_date" type="text" name="eme_localized_start_date" value="" style="background: #FCFFAA;" readonly="readonly" placeholder="<?php _e('Filter on startdate','events-made-easy'); ?>" size=15 />
   <input id="search_start_date" type="hidden" name="search_start_date" value="" />
   <input type="text" name="search_person" id="search_person" placeholder="<?php _e('Filter on person','events-made-easy'); ?>" size=15 />
   <input type="text" name="search_unique" id="search_unique" placeholder="<?php _e('Filter on unique nbr','events-made-easy'); ?>" size=15 />
   <button id="BookingsLoadRecordsButton" class="button-secondary action"><?php _e('Filter registrations','events-made-easy'); ?></button>
   </form>
   </div>
   <br />
   <br />
   <form id="eme-admin-regform" name="eme-admin-regform" action="#" method="post">
   <?php echo $nonce_field; ?>
   <select name="eme_admin_action" id="eme_admin_action">
   <option value="" selected="selected"><?php _e ( 'Bulk Actions' , 'events-made-easy'); ?></option>
<?php if ($pending) { ?>
   <option value="approveRegistration"><?php _e ( 'Approve registration','events-made-easy'); ?></option>
   <option value="denyRegistration"><?php _e ( 'Deny registration','events-made-easy'); ?></option>
   <option value="resendPendingRegistration"><?php _e ( 'Resend the mail for pending registration','events-made-easy'); ?></option>
   <option value="invertPaidStatus"><?php _e ( 'Invert paid status','events-made-easy'); ?></option>
   <option value="unsetwaitinglistRegistration"><?php _e ( 'Move registration off the waitinglist','events-made-easy'); ?></option>
   <option value="setwaitinglistRegistration"><?php _e ( 'Put registration on the waitinglist','events-made-easy'); ?></option>
<?php } else { ?>
   <option value="pendingRegistration"><?php _e ( 'Make registration pending','events-made-easy'); ?></option>
   <option value="denyRegistration"><?php _e ( 'Deny registration','events-made-easy'); ?></option>
   <option value="resendApprovedRegistration"><?php _e ( 'Resend the mail for approved registration','events-made-easy'); ?></option>
   <option value="invertPaidStatus"><?php _e ( 'Invert paid status','events-made-easy'); ?></option>
   <option value="setwaitinglistRegistration"><?php _e ( 'Put registration on the waitinglist','events-made-easy'); ?></option>
<?php } ?>
   </select>
   <span id="span_sendmails">
   <?php _e('Send mails to attendees upon changes being made?','events-made-easy'); echo eme_ui_select_binary(1,"send_mail"); ?>
   </span>
   <button id="BookingsActionsButton" class="button-secondary action"><?php _e ( 'Apply' , 'events-made-easy'); ?></button>
   <span class="rightclickhint">
      <?php _e('Hint: rightclick on the column headers to show/hide columns','events-made-easy'); ?>
   </span>
   <div id="BookingsTableContainer" style="width=98%;"></div>

</div>
</div>
<?php
}

function eme_send_mails_page() {
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   $action = isset($_POST ['eme_admin_action']) ? $_POST ['eme_admin_action'] : '';
   $onchange = isset($_POST ['onchange']) ? intval($_POST ['onchange']) : 0;

   if (isset($_POST ['mail_subject']) && !empty($_POST ['mail_subject']))
      $mail_subject = stripslashes_deep($_POST ['mail_subject']);
   elseif (isset($_POST ['subject_template']) && intval($_POST ['subject_template'])>0)
      $mail_subject = eme_get_template_format(intval($_POST ['subject_template']));
   else
      $mail_subject = "";

   if (isset($_POST ['mail_message']) && !empty($_POST ['mail_message']))
      $mail_message = stripslashes_deep($_POST ['mail_message']);
   elseif (isset($_POST ['message_template']) && intval($_POST ['message_template'])>0)
      $mail_message = eme_get_template_format(intval($_POST ['message_template']));
   else
      $mail_message = "";

   if ($action == 'send_testmail') {
      $mail_problems=false;
      $mail_to = stripslashes_deep($_POST ['mail_to']);
      $blank_event = eme_new_event();
      $contact = eme_get_contact($blank_event);
      $contact_email = $contact->user_email;
      $contact_name = $contact->display_name;
      $person_name = "test recipient EME";
      $tmp_subject = "test subject EME";
      $tmp_message = "test message EME";
      $mail_res=eme_send_mail($tmp_subject,$tmp_message, $mail_to, $person_name, $contact_email, $contact_name);
      if ($mail_res) {
         print "<div id='message' class='updated'><p>".__('The mail has been sent.','events-made-easy')."</p></div>";
      } else {
	 $err = error_get_last();
	 print "<div id='message' class='error'><p>".__('There were some problems while sending mail.','events-made-easy')." ".esc_html($err['message'])."</p></div>";
      }
   }
   if (!$onchange && $event_id>0 && $action == 'send_mail') {
      $pending_approved = isset($_POST ['pending_approved']) ? intval($_POST ['pending_approved']) : 0;
      $only_unpaid = isset($_POST ['only_unpaid']) ? intval($_POST ['only_unpaid']) : 0;
      $eme_mail_type = isset($_POST ['eme_mail_type']) ? $_POST ['eme_mail_type'] : 'attendees';
	   if (empty($mail_subject) || empty($mail_message)) {
		   print "<div id='message' class='error'><p>".__('Please enter both subject and message for the mail to be sent.','events-made-easy')."</p></div>";
	   } else {
		   $event = eme_get_event($event_id);
		   $current_userid=get_current_user_id();
                   $mail_problems=false;
		   if (current_user_can( get_option('eme_cap_send_other_mails')) ||
				   (current_user_can( get_option('eme_cap_send_mails')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid))) {  

			   $event_name = $event['event_name'];
			   $contact = eme_get_contact ($event);
			   $contact_email = $contact->user_email;
			   $contact_name = $contact->display_name;
			   $mail_text_html=get_option('eme_rsvp_send_html')?"html":"text";

			   if ($eme_mail_type == 'attendees') {
				   $attendees = eme_get_attendees_for($event_id,$pending_approved,$only_unpaid);
				   foreach ( $attendees as $attendee ) {
					   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text",0,$attendee['lang']);
					   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html,0,$attendee['lang']);
					   $tmp_subject = eme_replace_attendees_placeholders($tmp_subject, $event, $attendee, "text",0,$attendee['lang']);
					   $tmp_message = eme_replace_attendees_placeholders($tmp_message, $event, $attendee, $mail_text_html,0,$attendee['lang']);
					   $tmp_subject = eme_translate($tmp_subject,$attendee['lang']);
					   $tmp_message = eme_translate($tmp_message,$attendee['lang']);
					   $person_name=$attendee['lastname'].' '.$attendee['firstname'];
					   $mail_res=eme_send_mail($tmp_subject,$tmp_message, $attendee['email'], $person_name, $contact_email, $contact_name);
                                           if (!$mail_res) $mail_problems=true;
				   }
			   } elseif ($eme_mail_type == 'bookings') {
				   $bookings = eme_get_bookings_for($event_id,$pending_approved,$only_unpaid);
				   foreach ( $bookings as $booking ) {
					   // we use the language done in the booking for the mails, not the attendee lang in this case
					   $attendee = eme_get_person($booking['person_id']);
					   if ($attendee && is_array($attendee)) {
						   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text",0,$booking['lang']);
						   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html,0,$booking['lang']);
						   $tmp_subject = eme_replace_booking_placeholders($tmp_subject, $event, $booking, "text",0,$booking['lang']);
						   $tmp_message = eme_replace_booking_placeholders($tmp_message, $event, $booking, $mail_text_html,0,$booking['lang']);
						   $tmp_subject = eme_translate($tmp_subject,$booking['lang']);
						   $tmp_message = eme_translate($tmp_message,$booking['lang']);
						   $person_name=$attendee['lastname'].' '.$attendee['firstname'];
						   $mail_res=eme_send_mail($tmp_subject,$tmp_message, $attendee['email'], $person_name, $contact_email, $contact_name);
                                                   if (!$mail_res) $mail_problems=true;
					   }
				   }
			   } elseif ($eme_mail_type == 'all_wp') {
				   $wp_users = get_users();
				   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text");
				   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html);
				   foreach ( $wp_users as $wp_user ) {
					   $mail_res=eme_send_mail($tmp_subject,$tmp_message, $wp_user->user_email, $wp_user->display_name, $contact_email, $contact_name);
                                           if (!$mail_res) $mail_problems=true;
				   }
			   } elseif ($eme_mail_type == 'all_wp_not_registered') {
				   $wp_users = get_users();
				   $attendee_wp_ids = eme_get_wp_ids_for($event_id);
				   $tmp_subject = eme_replace_placeholders($mail_subject, $event, "text");
				   $tmp_message = eme_replace_placeholders($mail_message, $event, $mail_text_html);
				   foreach ( $wp_users as $wp_user ) {
					   if (!in_array($wp_user->ID,$attendee_wp_ids)) {
						   $mail_res=eme_send_mail($tmp_subject,$tmp_message, $wp_user->user_email, $wp_user->display_name, $contact_email, $contact_name);
                                                   if (!$mail_res) $mail_problems=true;
				           }
				   }
			   }
                           if (!$mail_problems)
			      print "<div id='message' class='updated'><p>".__('The mail has been sent.','events-made-easy')."</p></div>";
                           else
			      print "<div id='message' class='error'><p>".__('There were some problems while sending mail.','events-made-easy')."</p></div>";
		   } else {
			   print "<div id='message' class='error'><p>".__('You do not have the permission to send mails for this event.','events-made-easy')."</p></div>";
		   }
	   }
   }

   // now show the form
   eme_send_mail_form($event_id);
}

function eme_send_mail_form($event_id=0) {
   global $eme_timezone;
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h1><?php _e ('Send Mails to attendees or bookings for a event','events-made-easy'); ?></h1>
   <div id='message' class='updated'><p>
<?php
   _e('Warning: using this functionality to send mails to attendees can result in a php timeout, so not everybody will receive the mail then. This depends on the number of attendees, the load on the server, ... . If this happens, use the CSV export link to get the list of all attendees and use mass mailing tools (like OpenOffice) for your mailing.','events-made-easy');
   $all_events=eme_get_events(0,"future");
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   $current_userid=get_current_user_id();
   $templates_array=eme_get_templates_array_by_id();
   $nonce_field = wp_nonce_field('eme_rsvp','eme_admin_nonce',false,false);
?>
   </p></div>
   <form id='send_mail' name='send_mail' action="" method="post">
   <?php echo $nonce_field; ?>
   <input type='hidden' name='page' value='eme-send-mails' />
   <input type='hidden' name='eme_admin_action' value='send_mail' />
   <input type='hidden' id='onchange' name='onchange' value='0' />
   <select name="event_id" onchange="document.getElementById('onchange').value='1';this.form.submit();">
   <option value='0' ><?php _e('Select the event','events-made-easy') ?></option>
   <?php
   foreach ( $all_events as $tmp_event ) {
      $option_text=$tmp_event['event_name']." (".eme_localized_date($tmp_event['event_start_date']." ".$tmp_event['event_start_time']." ".$eme_timezone).")";
      if ($tmp_event['event_rsvp'] && current_user_can( get_option('eme_cap_send_other_mails')) ||
            (current_user_can( get_option('eme_cap_send_mails')) && ($tmp_event['event_author']==$current_userid || $tmp_event['event_contactperson_id']==$current_userid))) {  
         if ($tmp_event['event_id'] == $event_id) {
            echo "<option selected='selected' value='".$tmp_event['event_id']."' >".$option_text."</option>  ";
         } else {
            echo "<option value='".$tmp_event['event_id']."' >".$option_text."</option>  ";
         }
      }
   }
   ?>
   </select>
   <p>
   <?php if ($event_id>0) {
      $event = eme_get_event($event_id);
   ?>
      <table>
      <tr>
	   <td><label><?php _e('Select the type of mail','events-made-easy'); ?></td>
      <td>
           <select name="eme_mail_type" required='required'>
           <option value=''></option>
           <option value='attendees'><?php _e('Attendee mails','events-made-easy'); ?></option>
           <option value='bookings'><?php _e('Booking mails','events-made-easy'); ?></option>
           <option value='all_wp'><?php _e('Mail to all WP users','events-made-easy'); ?></option>
           <option value='all_wp_not_registered'><?php _e('All WP users except those registered for the event','events-made-easy'); ?></option>
           </select>
      </td>
      </tr>
      <tr id="eme_pending_approved_row">
	   <td><label><?php _e('Select your target audience','events-made-easy'); ?></td>
      <td>
           <select name="pending_approved">
           <option value=0><?php _e('All registered persons','events-made-easy'); ?></option>
           <option value=2><?php _e('Exclude pending registrations','events-made-easy'); ?></option>
           <option value=1><?php _e('Only pending registrations','events-made-easy'); ?></option>
           </select></p><p>
      </td>
      </tr>
      <tr id="eme_only_unpaid_row">
      <td><?php _e('Only send mails to attendees who did not pay yet','events-made-easy'); ?>&nbsp;</td>
      <td>
           <input type="checkbox" name="only_unpaid" value="1" />
      </td>
      </tr>
      </table>
	   <div id="titlediv" class="form-field form-required"><p>
      <b><?php _e('Subject','events-made-easy'); ?></b><br />
      <?php _e('Either choose from a template: ','events-made-easy'); echo eme_ui_select(0,'subject_template',$templates_array); ?><br />
      <?php _e('Or enter your own (if anything is entered here, it takes precedence over the selected template): ','events-made-easy');?>
      <input type="text" name="mail_subject" id="mail_subject" value="" /></p>
	   </div>
	   <div class="form-field form-required"><p>
	   <b><?php _e('Message','events-made-easy'); ?></b><br />
      <?php _e('Either choose from a template: ','events-made-easy'); echo eme_ui_select(0,'message_template',$templates_array); ?><br />
      <?php _e('Or enter your own (if anything is entered here, it takes precedence over the selected template): ','events-made-easy');
          if (get_option('eme_rsvp_send_html')) {
             // for mails, let enable the full html editor
             $eme_editor_settings = eme_get_editor_settings(true);
             wp_editor('','mail_message',$eme_editor_settings);
          } else {
             echo '<textarea name="mail_message" id="mail_message" value="" rows=10></textarea>';
          }
      ?>
           </p>
	   </div>
	   <div>
	   <?php _e('You can use any placeholders mentioned here:','events-made-easy');
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=25'>".__('Event placeholders','events-made-easy')."</a>";
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=48'>".__('Attendees placeholders','events-made-easy')."</a> (".__('for ','events-made-easy').__('Attendee mails','events-made-easy').")";
	   print "<br /><a href='http://www.e-dynamics.be/wordpress/?cat=45'>".__('Booking placeholders','events-made-easy')."</a> (".__('for ','events-made-easy').__('Booking mails','events-made-easy').")";
	   ?>
	   </div>
      <br />
	   <input type="submit" value="<?php _e ( 'Send Mail', 'events-made-easy'); ?>" class="button-primary action" />

   <?php
	   $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=".$event['event_id']);
	   $available_seats = eme_get_available_seats($event['event_id']);
	   $total_seats = $event ['event_seats'];
	   if ($total_seats!=$available_seats)
		   echo "<br /><br /> <a id='booking_csv_".$event['event_id']."'  target='' href='$csv_address'>".__('CSV export','events-made-easy')."</a>";
   }
   ?>
   </form>
   <?php if (!$event_id) { ?>
   <br />
   <br />
   <?php _e ( 'Use the below form to send a test mail', 'events-made-easy'); ?>
   <form id='send_testmail' name='send_testmail' action="" method="post">
   <?php echo $nonce_field; ?>
   <input type='hidden' name='eme_admin_action' value='send_testmail' />
   <label for="mail_to"><?php _e('Enter the recipient','events-made-easy'); ?>
   <input type="text" name="mail_to" id="mail_to" value="" /></p>
   <input type="submit" value="<?php _e ( 'Send Test Mail', 'events-made-easy'); ?>" class="button-primary action" />
   </form>
   <?php
   }
}

// template function
function eme_is_event_rsvpable() {
   if (eme_is_single_event_page() && isset($_REQUEST['event_id'])) {
      $event = eme_get_event(intval($_REQUEST['event_id']));
      if($event)
         return $event['event_rsvp'];
   }
   return 0;
}

function eme_event_needs_approval($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT registration_requires_approval from $events_table where event_id=%d",$event_id);
   return $wpdb->get_var( $sql );
}

// the next function returns the price for 1 booking, not taking into account the number of seats or anything
function eme_get_booking_event_price($booking) {
   return $booking['booking_price'];
}

// the next function returns the price for a specific booking, multiplied by the number of seats booked and multiprice taken into account
function eme_get_total_booking_price($booking,$ajax=0) {
   $basic_price=eme_get_booking_event_price($booking);

   if (eme_is_multi($basic_price)) {
      $price = array_sum(eme_get_total_booking_multiprice_arr($booking));
   } else {
      $price = $basic_price*$booking['booking_seats'];
   }
   if (!$ajax) {
      $price += $booking['extra_charge'];
      $price -= $booking['discount'];
      if ($price<0) $price=0;
   }
   return $price;
}

function eme_bookings_total_booking_seats($bookings) {
   $seats=0;
   foreach ($bookings as $booking) {
      $seats += $booking['booking_seats'];
   }
   return $seats;
}

function eme_get_seat_booking_price($booking) {
   $price = eme_get_total_booking_price($booking) - $booking['extra_charge'] + $booking['discount'];
   $price /= $booking['booking_seats'];
   return $price;
}

function eme_get_total_booking_multiprice_arr($booking) {
   $price=array();
   $basic_price=eme_get_booking_event_price($booking);

   if (eme_is_multi($basic_price)) {
      $prices=eme_convert_multi2array($basic_price);
      $seats=eme_convert_multi2array($booking['booking_seats_mp']);
      foreach ($prices as $key=>$val) {
         $price[] = $val*$seats[$key];
      }
   }
   return $price;
}

function eme_get_seat_booking_multiprice_arr($booking) {
   $price=array();
   $basic_price=eme_get_booking_event_price($booking);

   if (eme_is_multi($basic_price)) {
      $price=eme_convert_multi2array($basic_price);
   }
   return $price;
}

function eme_is_event_rsvp ($event) {
   $rsvp_is_active = get_option('eme_rsvp_enabled');
   if ($rsvp_is_active && $event['event_rsvp'])
      return 1;
   else
      return 0;
}

function eme_is_event_multiprice($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price from $events_table where event_id=%d",$event_id);
   $price = $wpdb->get_var( $sql );
   return eme_is_multi($price);
}

function eme_is_event_multiseats($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT event_seats from $events_table where event_id=%d",$event_id);
   $seats = $wpdb->get_var( $sql );
   return eme_is_multi($seats);
}

function eme_get_total($multistring) {
   if (eme_is_multi($multistring))
      return array_sum(eme_convert_multi2array($multistring));
   else
      return $multistring;
}

add_action( 'wp_ajax_eme_bookings_list', 'eme_ajax_bookings_list' );
add_action( 'wp_ajax_eme_manage_bookings', 'eme_ajax_manage_bookings' );

function eme_ajax_bookings_list() {
   global $wpdb, $eme_timezone;
   $jtStartIndex= (isset($_REQUEST['jtStartIndex'])) ? intval($_REQUEST['jtStartIndex']) : 0;
   $jtPageSize= (isset($_REQUEST['jtPageSize'])) ? intval($_REQUEST['jtPageSize']) : 10;
   $jtSorting = (isset($_REQUEST['jtSorting'])) ? esc_sql($_REQUEST['jtSorting']) : 'creation_date ASC';
   // booking_status shows us if we are in the approved window (=2) or 'to approve' window (=1)
   $booking_status = (isset($_REQUEST['booking_status'])) ? intval($_REQUEST['booking_status']) : 2;
   $search_event = isset($_REQUEST['search_event']) ? esc_sql($_REQUEST['search_event']) : '';
   $search_person = isset($_REQUEST['search_person']) ? esc_sql($_REQUEST['search_person']) : '';
   $search_unique = isset($_REQUEST['search_unique']) ? esc_sql($_REQUEST['search_unique']) : '';
   $search_start_date = isset($_REQUEST['search_start_date']) ? esc_sql($_REQUEST['search_start_date']) : '';
   $scope = (isset($_REQUEST['scope'])) ? esc_sql($_REQUEST['scope']) : 'future';
   $person_id = isset($_REQUEST['person_id']) ? intval($_REQUEST['person_id']) : 0;
   $event_id = isset($_REQUEST['event_id']) ? intval($_REQUEST['event_id']) : 0;

   if (($booking_status==1 && !current_user_can(get_option('eme_cap_approve'))) || 
       ($booking_status==2 && !current_user_can(get_option('eme_cap_registrations')))) {
      $jTableResult['Result'] = "Error";
      $jTableResult['Message'] = __('Access denied!','events-made-easy');
      print json_encode($jTableResult);
      wp_die();
   }

   // The toolbar search input
   $q = isset($_REQUEST['q'])?$_REQUEST['q']:"";
   $opt = isset($_REQUEST['opt'])?$_REQUEST['opt']:"";
   $where ='';
   $where_arr=array();
   if ($q) {
        for ($i = 0; $i < count($opt); $i++) {
                $fld = esc_sql($opt[$i]);
                if ($fld == "booker") {
                   $where_arr[] = "(lastname like '%".esc_sql($q[$i])."%' OR firstname '%".esc_sql($q[$i])."%')";
                } else {
                   $where_arr[] = $fld." like '%".esc_sql($q[$i])."%'";
                }
        }
   }

   // match some non-existing column names in the ajax for sorting to good ones
   $jtSorting = str_replace("booker ASC","lastname ASC, firstname ASC",$jtSorting);
   $jtSorting = str_replace("booker DESC","lastname DESC, firstname DESC",$jtSorting);
   $jtSorting = str_replace("datetime ASC","event_start_date ASC, event_start_time ASC",$jtSorting);
   $jtSorting = str_replace("datetime DESC","event_start_date DESC, event_start_time DESC",$jtSorting);

   $eme_date_obj_now=new ExpressiveDate(null,$eme_timezone);
   $today=$eme_date_obj_now->getDate();
   if (!empty($search_start_date) && eme_validateDate($search_start_date,'Y-m-d')) {
      $where_arr[] = "event_start_date = '$search_start_date'";
   } else {
      if ($scope=='past') {
         $where_arr[] = "event_end_date < '$today'";
      } elseif ($scope=='future') {
         $where_arr[] = "event_end_date >= '$today'";
      }
   }
   if ($booking_status==1) {
      $where_arr[] = "booking_approved=0";
   } elseif ($booking_status==2) {
      $where_arr[] = "booking_approved=1";
   }
   // if we search for an event, do that too
   if (!empty($search_event)) {
      $where_arr[] = "event_name like '%".esc_sql($search_event)."%'";
   }
   // for a person, we don't override the settings
   if (!empty($search_person)) {
      $where_arr[] = "(lastname like '%".esc_sql($search_person)."%' OR firstname like '%".esc_sql($search_person)."%' OR email like '%".esc_sql($search_person)."%')";
   }
   if (!empty($search_unique)) {
      $where_arr[] = "transfer_nbr_be97 like '%".esc_sql($search_unique)."%'";
   }

   if (!empty($where_arr))
      $where = " WHERE ".implode(" AND ",$where_arr);

   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   $people_table = $wpdb->prefix.PEOPLE_TBNAME;

   // if we search for a person_id, override the other settings, except for pending or not
   if (!empty($person_id)) {
      $where = " WHERE bookings.person_id=$person_id";
      if ($booking_status==1) {
         $where .= " AND booking_approved=0";
      } elseif ($booking_status==2) {
         $where .= " AND booking_approved=1";
      }
   }
   // if we search for a event_id, override the other settings, except for pending or not
   if (!empty($event_id)) {
      $where = " WHERE bookings.event_id=$event_id";
      if ($booking_status==1) {
         $where .= " AND booking_approved=0";
      } elseif ($booking_status==2) {
         $where .= " AND booking_approved=1";
      }
   }
   $sql1 = "SELECT count(bookings.booking_id) FROM $bookings_table as bookings LEFT JOIN $events_table as events ON bookings.event_id=events.event_id LEFT JOIN $people_table as people ON bookings.person_id=people.person_id $where";
   $sql2 = "SELECT bookings.* FROM $bookings_table as bookings LEFT JOIN $events_table as events ON bookings.event_id=events.event_id LEFT JOIN $people_table as people ON bookings.person_id=people.person_id $where ORDER BY $jtSorting LIMIT $jtStartIndex,$jtPageSize";
   $recordCount = $wpdb->get_var($sql1);
   $bookings = $wpdb->get_results($sql2, ARRAY_A);
   $rows=array();
   foreach ($bookings as $booking) {
      $person = eme_get_person ($booking['person_id']);
      $person_info_shown = eme_esc_html($person['lastname']);
      if ($person['firstname'])
         $person_info_shown .= " ".eme_esc_html($person['firstname']);
      $person_info_shown .= " (". eme_esc_html($person['email']).")";

      $event = eme_get_event($booking['event_id']);
      $date_obj = new ExpressiveDate($event['event_start_date']." ".$event['event_start_time'],$eme_timezone);
      $localized_start_date = eme_localized_date($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone);
      $localized_start_time = eme_localized_time($event['event_start_date']." ".$event['event_start_time']." ".$eme_timezone);
      $localized_end_date = eme_localized_date($event['event_end_date']." ".$event['event_end_time']." ".$eme_timezone);
      $localized_end_time = eme_localized_time($event['event_end_date']." ".$event['event_end_time']." ".$eme_timezone);
      $localized_booking_date = eme_localized_date($booking['creation_date']." ".$eme_timezone);
      $localized_booking_time = eme_localized_time($booking['creation_date']." ".$eme_timezone);

      $line['booking_id']=$booking['booking_id'];
      if ($booking_status==1)
            $page="eme-registration-approval";
      elseif ($booking_status==2)
            $page="eme-registration-seats";
      $line['edit_link'] = "<a href='".wp_nonce_url(admin_url("admin.php?page=$page&amp;eme_admin_action=editRegistration&amp;booking_id=".$booking ['booking_id']),'eme_rsvp','eme_admin_nonce')."' title='".__('Click here to see and/or edit the details of the booking.','events-made-easy')."'>"."<img src='".EME_PLUGIN_URL."images/edit.png' alt='".__('Edit','events-made-easy')."'> "."</a>";
      $line['event_name'] = "<strong><a href='".wp_nonce_url(admin_url("admin.php?page=eme-manager&amp;eme_admin_action=edit_event&amp;event_id=".$event['event_id']),'eme_events','eme_admin_nonce')."' title='".__('Edit event','events-made-easy')."'>".eme_trans_esc_html($event['event_name'])."</a></strong>";
         $categories = explode(',', $event['event_category_ids']);
         foreach($categories as $cat){
            $category = eme_get_category($cat);
            if($category)
               $line['event_name'] .= "<br /><span title='".__('Category','events-made-easy').": ".eme_trans_esc_html($category['category_name'])."'>".eme_trans_esc_html($category['category_name'])."</span>";
         }
         if ($event['event_rsvp']) {
            $booked_seats = eme_get_booked_seats($event['event_id']);
            $available_seats = eme_get_available_seats($event['event_id']);
            $pending_seats = eme_get_pending_seats($event['event_id']);
            $total_seats = eme_get_total($event['event_seats']);
            if (eme_is_multi($event['event_seats'])) {
               $available_seats_string = $available_seats.' ('.eme_convert_array2multi(eme_get_available_multiseats($event['event_id'])).')';
               $pending_seats_string = $pending_seats.' ('.eme_convert_array2multi(eme_get_pending_multiseats($event['event_id'])).')';
               $total_seats_string = $total_seats .' ('.$event['event_seats'].')';
            } else {
               $available_seats_string = $available_seats;
               $pending_seats_string = $pending_seats;
               $total_seats_string = $total_seats;
            }
            if ($pending_seats >0) 
               $line['event_name'] .= "<br />".__('RSVP Info: ','events-made-easy').__('Free: ','events-made-easy').$available_seats_string.", ".__('Pending: ','events-made-easy').$pending_seats_string.", ".__('Max: ','events-made-easy').$total_seats_string;
            else
               $line['event_name'] .= "<br />".__('RSVP Info: ','events-made-easy').__('Free: ','events-made-easy').$available_seats_string.", ".__('Max: ','events-made-easy').$total_seats_string;
            if ($booked_seats>0 || $pending_seats >0) {
               $printable_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_printable&amp;event_id=".$event['event_id']);
               $csv_address = admin_url("admin.php?page=eme-people&amp;eme_admin_action=booking_csv&amp;event_id=".$event['event_id']);               $line['event_name'] .= " <br />(<a id='booking_printable_".$event['event_id']."' href='$printable_address'>".__('Printable view','events-made-easy')."</a>)"; 
               $line['event_name'] .= " (<a id='booking_csv_".$event['event_id']."' href='$csv_address'>".__('CSV export','events-made-easy')."</a>)";
            }
         }
      if ($event['event_rsvp']) {
         if ($event['registration_requires_approval'])
            $page="eme-registration-approval";
         else
            $page="eme-registration-seats";

         $line['rsvp'] = "<a href='".wp_nonce_url(admin_url("admin.php?page=$page&amp;eme_admin_action=newRegistration&amp;event_id=".$event['event_id']),'eme_rsvp','eme_admin_nonce')."' title='".__('Add registration for this event','events-made-easy')."'>".__('RSVP','events-made-easy')."</a>";
      } else {
         $line['rsvp'] = "";
      }


      $line['datetime']= $localized_start_date;
      if ($localized_end_date !='' && $localized_end_date!=$localized_start_date)
         $line['datetime'] .=" - " . $localized_end_date;
      $line['datetime'] .= "<br />";
      if ($event['event_properties']['all_day']==1)
         $line['datetime'] .=__('All day','events-made-easy');
      else
         $line['datetime'] .= "$localized_start_time - $localized_end_time";
      if ($date_obj->lessThan($eme_date_obj_now)) {
         $line['datetime'] = "<span style='text-decoration: line-through;'>".$line['datetime']."</span>";
      }

      $person_url=admin_url("admin.php?page=eme-people&person_id=".$person['person_id']);
      $line['booker'] = "<a href='$person_url' title='".__('Click the name of the booker in order to see and/or edit the details of the booker.','events-made-easy')."'> $person_info_shown </a>";
      $line['creation_date']="$localized_booking_date $localized_booking_time";
      if (eme_is_multi(eme_get_booking_event_price($booking))) {
         $line['seats']=$booking['booking_seats_mp'] .'<br />'. __('(Multiprice)','events-made-easy');
      } else {
         $line['seats']=$booking['booking_seats'];
      }
      if ($booking['waitinglist']) {
         $line['seats'].='<br />'. __('(On waitinglist)','events-made-easy');
      }
      $line['eventprice']=eme_localized_price(eme_get_booking_event_price($booking),$event['currency']);
      $line['totalprice']=eme_localized_price(eme_get_total_booking_price($booking),$event['currency']);
      $line['transfer_nbr_be97']= "<span title='".sprintf(__('This is based on the payment ID of the booking: %d','events-made-easy'),$booking ['payment_id'])."'>".eme_esc_html($booking['transfer_nbr_be97'])."</span>";
      $line['booking_paid']=$booking['booking_paid'];

      $rows[]=$line;
   }
   $jTableResult['Result'] = "OK";
   $jTableResult['TotalRecordCount'] = $recordCount;
   $jTableResult['Records'] = $rows;
   print json_encode($jTableResult);
   wp_die();
}

function eme_ajax_manage_bookings() {
   check_ajax_referer('eme_rsvp','eme_admin_nonce');
   if (isset($_POST['do_action'])) {
     $ids_arr=explode(',',$_POST['booking_id']);
     $do_action=eme_sanitize_request($_POST['do_action']);
     $send_mail=(isset($_POST['send_mail'])) ? intval($_POST['send_mail']) : 1;
     switch ($do_action) {
         case 'paidandapprove':
	      // shortcut button to do 2 things at once, mail will always be sent
              eme_ajax_action_rsvp_paidandapprove($ids_arr);
              break;
         case 'approveRegistration':
              eme_ajax_action_rsvp_aprove($ids_arr,$do_action,$send_mail);
              break;
         case 'denyRegistration':
              eme_ajax_action_rsvp_deny($ids_arr,$do_action,$send_mail);
              break;
         case 'invertPaidStatus':
	      // no mail will be sent
              eme_ajax_action_invert_paid($ids_arr);
              break;
         case 'resendApprovedRegistration':
              eme_ajax_action_resend_mail($ids_arr,'approveRegistration');
              break;
         case 'resendPendingRegistration':
              eme_ajax_action_resend_mail($ids_arr,'pendingRegistration');
              break;
         case 'pendingRegistration':
              eme_ajax_action_revert_approval($ids_arr,$do_action,$send_mail);
              break;
         case 'unsetwaitinglistRegistration':
              eme_ajax_action_remove_waitinglist($ids_arr,$do_action,$send_mail);
              break;
         case 'setwaitinglistRegistration':
              eme_ajax_action_move_waitinglist($ids_arr,$do_action,$send_mail);
              break;
      }
   }
   wp_die();
}

function eme_ajax_action_rsvp_paidandapprove($ids_arr) {
   foreach ($ids_arr as $booking_id) {
      eme_set_booking_paid($booking_id,1);
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_remove_waitinglist($ids_arr,$action,$send_mail) {
   foreach ($ids_arr as $booking_id) {
      // waiting list can only be removed for pending bookings
      // so set the action to "pending"
      $action = "pendingRegistration";
      $booking = eme_get_booking ($booking_id);
      if ($booking['waitinglist']) {
         eme_remove_from_waitinglist($booking_id);
         // don't get the booking again, avoid an extra sql call
         // $booking = eme_get_booking ($booking_id);
         $booking['waitinglist']=0;
         if ($send_mail) eme_email_rsvp_booking($booking,$action);
      }
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_move_waitinglist($ids_arr,$action,$send_mail) {
   foreach ($ids_arr as $booking_id) {
      // waiting list can only be removed for pending bookings
      // so set the action to "pending"
      $action = "pendingRegistration";
      $booking = eme_get_booking ($booking_id);
      if (!$booking['waitinglist']) {
         eme_move_on_waitinglist($booking_id);
         // don't get the booking again, avoid an extra sql call
         // $booking = eme_get_booking ($booking_id);
         $booking['waitinglist']=1;
         if ($send_mail) eme_email_rsvp_booking($booking,$action);
      }
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_rsvp_aprove($ids_arr,$action,$send_mail) {
   foreach ($ids_arr as $booking_id) {
      // let's make sure to do this only for non-approved bookings
      $booking = eme_get_booking ($booking_id);
      if (!$booking['booking_approved']) {
         eme_approve_booking($booking_id);
         // don't get the booking again, avoid an extra sql call
         // $booking = eme_get_booking ($booking_id);
         $booking['booking_approved']=1;
         if ($send_mail) eme_email_rsvp_booking($booking,$action);
      }
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_rsvp_deny($ids_arr,$action,$send_mail) {
   foreach ($ids_arr as $booking_id) {
      // the mail needs to be sent after the deletion, otherwise the count of free spaces is wrong
      $booking = eme_get_booking ($booking_id);
      eme_delete_booking($booking_id);
      if ($send_mail) eme_email_rsvp_booking($booking,$action);
      // delete the booking answers after the mail is sent, so the answers can still be used in the mail
      eme_delete_answers($booking_id);
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_invert_paid($ids_arr) {
   foreach ($ids_arr as $booking_id) {
       eme_invert_booking_paid($booking_id);
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_resend_mail($ids_arr,$action) {
   foreach ($ids_arr as $booking_id) {
       $booking = eme_get_booking ($booking_id);
       eme_email_rsvp_booking($booking,$action);
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

function eme_ajax_action_revert_approval($ids_arr,$action,$send_mail) {
   foreach ($ids_arr as $booking_id) {
       $res = eme_revert_booking_approval($booking_id);
       if ($res && $send_mail) {
	       $booking = eme_get_booking ($booking_id);
	       eme_email_rsvp_booking($booking,$action);
       }
   }
   $jTableResult['Result'] = "OK";
   print json_encode($jTableResult);
}

?>
