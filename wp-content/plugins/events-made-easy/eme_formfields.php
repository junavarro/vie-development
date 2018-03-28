<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function eme_formfields_page() {      
   global $wpdb;
   
   if (!current_user_can( get_option('eme_cap_forms')) && (isset($_GET['eme_admin_action']) || isset($_POST['eme_admin_action']))) {
      $message = __('You have no right to update form fields!','events-made-easy');
      eme_formfields_table_layout($message);
      return;
   }
   
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "edit_formfield") { 
      check_admin_referer('eme_formfields','eme_admin_nonce');
      // edit formfield  
      $field_id = intval($_GET['field_id']);
      eme_formfields_edit_layout($field_id);
      return;
   }

   // Insert/Update/Delete Record
   $formfields_table = $wpdb->prefix.FORMFIELDS_TBNAME;
   $validation_result = '';
   $message = '';
   if (isset($_POST['eme_admin_action'])) {
      check_admin_referer('eme_formfields','eme_admin_nonce');
      if ($_POST['eme_admin_action'] == "do_editformfield") {
         $formfield = array();
         $field_id = intval($_POST['field_id']);
         $formfield['field_name'] = trim(stripslashes($_POST['field_name']));
         $formfield['field_type'] = intval($_POST['field_type']);
         $formfield['extra_charge'] = intval($_POST['extra_charge']);
         $formfield['field_info'] = trim(stripslashes($_POST['field_info']));
         $formfield['field_tags'] = trim(stripslashes($_POST['field_tags']));
         $formfield['field_attributes'] = trim(stripslashes($_POST['field_attributes']));
         if (eme_is_multifield($formfield['field_type']) && empty($formfield['field_info'])) {
            $message = __('Error: the field value can not be empty for this type of field.','events-made-easy');
            eme_formfields_edit_layout($field_id,$message);
            return;
         } elseif (eme_is_multifield($formfield['field_type']) &&
               eme_is_multi($formfield['field_info']) && !empty($formfield['field_tags']) && 
               count(eme_convert_multi2array($formfield['field_info'])) != count(eme_convert_multi2array($formfield['field_tags']))) {
            $message = __('Error: if you specify field tags, there need to be exact the same amount of tags as values.','events-made-easy');
            eme_formfields_edit_layout($field_id,$message);
            return;
         } else {
            $validation_result = $wpdb->update( $formfields_table, $formfield, array('field_id' => $field_id) );
            if ($validation_result !== false )
               $message = __("Successfully edited the field", 'events-made-easy');
	    else
               $message = __("There was a problem editing the field", 'events-made-easy');
         }
      } elseif ($_POST['eme_admin_action'] == "do_addformfield" ) {
         // Add a new formfield
         $formfield = array();
         $formfield['field_name'] = trim(stripslashes($_POST['field_name']));
         $formfield['field_type'] = intval($_POST['field_type']);
         $formfield['extra_charge'] = intval($_POST['extra_charge']);
         $formfield['field_info'] = trim(stripslashes($_POST['field_info']));
         $formfield['field_tags'] = trim(stripslashes($_POST['field_tags']));
         $formfield['field_attributes'] = trim(stripslashes($_POST['field_attributes']));
         if (eme_is_multifield($formfield['field_type']) && empty($formfield['field_info'])) {
            $message = __('Error: the field value can not be empty for this type of field.','events-made-easy');
            $validation_result = false;
         } elseif (eme_is_multifield($formfield['field_type']) &&
               eme_is_multi($formfield['field_info']) && !empty($formfield['field_tags']) && 
               count(eme_convert_multi2array($formfield['field_info'])) != count(eme_convert_multi2array($formfield['field_tags']))) {
            $message = __('Error: if you specify field tags, there need to be exact the same amount of tags as values.','events-made-easy');
            $validation_result = false;
         } else {
            $validation_result = $wpdb->insert( $formfields_table, $formfield );
            if ($validation_result !== false )
               $message = __("Successfully added the field", 'events-made-easy');
	    else
               $message = __("There was a problem adding the field", 'events-made-easy');
         }
      } elseif ($_POST['eme_admin_action'] == "do_deleteformfield" && isset($_POST['formfields'])) {
         // Delete formfield or multiple
         $formfields = $_POST['formfields'];
         if (!empty($formfields) && eme_array_integers($formfields)) {
               $validation_result = $wpdb->query( "DELETE FROM $formfields_table WHERE field_id IN (". implode(",", $fields).")" );
               if ($validation_result !== false ) {
                  $message = __("Successfully deleted the field(s)", 'events-made-easy');
		  $answers_table = $wpdb->prefix.ANSWERS_TBNAME;
                  $wpdb->query( "DELETE FROM $answers_table WHERE field_id IN (". implode(",", $fields).")" );
	       } else {
                  $message = __("There was a problem deleting the selected field(s), please try again.",'events-made-easy');
	       }
         } else {
            $message = __("Couldn't delete the field(s). Incorrect IDs supplied. Please try again.",'events-made-easy');
         }
      }
   }

   eme_formfields_table_layout($message);
} 

function eme_formfields_table_layout($message="") {
   $formfields = eme_get_formfields();
   $used_formfield_ids = eme_get_used_formfield_ids();
   $fieldtypes = eme_get_fieldtypes();
   $nonce_field = wp_nonce_field('eme_formfields','eme_admin_nonce',false,false);
   $destination = admin_url("admin.php?page=eme-formfields"); 
   $table = "
      <div class='wrap nosubsub'>\n
         <div id='icon-edit' class='icon32'>
            <br />
         </div>
         <h1>".__('Form fields', 'events-made-easy')."</h1>\n ";   
         
   if(!empty($message)) {
      $table .= "
         <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
         <p>$message</p>
         </div>";
   }
         
         $table .= "
         <div id='col-container'>
         
            <?-- begin col-right -->
            <div id='col-right'>
             <div class='col-wrap'>
                <form id='bookings-filter' method='post' action='$destination'>
                  <input type='hidden' name='eme_admin_action' value='do_deleteformfield' />";
                  $table .= $nonce_field;
                  if (count($formfields)>0) {
                     $table .= "<table class='widefat'>
                        <thead>
                           <tr>
                              <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1' /></th>
                              <th>".__('ID', 'events-made-easy')."</th>
                              <th>".__('Name', 'events-made-easy')."</th>
                              <th>".__('Type', 'events-made-easy')."</th>
                              <th>".__('Extra charge', 'events-made-easy')."</th>
                              <th>".__('Used in RSVP replies', 'events-made-easy')."</th>
                           </tr>
                        </thead>
                        <tfoot>
                           <tr>
                              <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1' /></th>
                              <th>".__('ID', 'events-made-easy')."</th>
                              <th>".__('Name', 'events-made-easy')."</th>
                              <th>".__('Type', 'events-made-easy')."</th>
                              <th>".__('Extra charge', 'events-made-easy')."</th>
                              <th>".__('Used in RSVP replies', 'events-made-easy')."</th>
                           </tr>
                        </tfoot>
                        <tbody>";
                     foreach ($formfields as $this_formfield) {
                        $used=in_array($this_formfield['field_id'],$used_formfield_ids) ? __('Yes', 'events-made-easy'): __('No', 'events-made-easy');
                        $extra_charge=($this_formfield['extra_charge']==1) ? __('Yes', 'events-made-easy'): __('No', 'events-made-easy');
                        $table .= "    
                           <tr>
                           <td><input type='checkbox' class ='row-selector' value='".$this_formfield['field_id']."' name='formfields[]' /></td>
                           <td><a href='".wp_nonce_url(admin_url("admin.php?page=eme-formfields&amp;eme_admin_action=edit_formfield&amp;field_id=".$this_formfield['field_id']),'eme_formfields','eme_admin_nonce')."'>".$this_formfield['field_id']."</a></td>
                           <td><a href='".wp_nonce_url(admin_url("admin.php?page=eme-formfields&amp;eme_admin_action=edit_formfield&amp;field_id=".$this_formfield['field_id']),'eme_formfields','eme_admin_nonce')."'>".eme_esc_html($this_formfield['field_name'])."</a></td>
                           <td><a href='".wp_nonce_url(admin_url("admin.php?page=eme-formfields&amp;eme_admin_action=edit_formfield&amp;field_id=".$this_formfield['field_id']),'eme_formfields','eme_admin_nonce')."'>".eme_get_fieldtype($this_formfield['field_type'])."</a></td>
                           <td>".$extra_charge."</td>
                           <td>".$used."</td>
                           </tr>
                        ";
                     }
                     $delete_text=__("Are you sure you want to delete these form fields? If these fields are used in RSVP answers, then those will be deleted too!",'events-made-easy');
                     $delete_button_text=__("Delete",'events-made-easy');
                     $table .= <<<EOT
                        </tbody>
                        </table>

                        <div class='tablenav'>
                        <div class='alignleft actions'>
                        <input class='button-primary action' type='submit' name='doaction' value='$delete_button_text' onclick="return areyousure('$delete_text');" />
                        <br class='clear'/>
                        </div>
                        <br class='clear'/>
                        </div>
EOT;
                  } else {
                        $table .= "<p>".__('No fields defined yet!', 'events-made-easy');
                  }
                   $table .= "
                  </form>
               </div>
            </div> 
            <?-- end col-right -->
            
            <?-- begin col-left -->
            <div id='col-left'>
            <div class='col-wrap'>
                  <div class='form-wrap'>
                     <div id='ajax-response'/>
                  <h3>".__('Add field', 'events-made-easy')."</h3>
                      <form name='add' id='add' method='post' action='$destination' class='add:the-list: validate'>
                        <input type='hidden' name='eme_admin_action' value='do_addformfield' />
                        $nonce_field 
                         <div class='form-field form-required'>
                           <label for='field_name'>".__('Field name', 'events-made-easy')."</label>
                           <input name='field_name' id='field_name' type='text' value='' size='40' />
                           <label for='field_type'>".__('Field type', 'events-made-easy')."</label>
			". eme_ui_select("","field_type",$fieldtypes) ."
                           <label for='extra_charge'>".__('Extra charge', 'events-made-easy')."</label>
			". eme_ui_select_binary(0,"extra_charge") ."
                           <br />".__('Use this is the field indicates an extra charge to the total price','events-made-easy')."
                           <label for='field_info'>".__('Field values', 'events-made-easy')."</label>
                           <input name='field_info' id='field_info' type='text' value='' size='40' />
                           <br />".__('Tip: for multivalue field types (like Drop Down), use "||" to separate the different values (e.g.: a1||a2||a3)','events-made-easy')."
                           <label for='field_tags'>".__('Field tags', 'events-made-easy')."</label>
                           <input name='field_tags' id='field_tags' type='text' value='' size='40' />
                           <br />".__('For multivalue fields, you can here enter the "visible" tags people will see. If left empty, the field values will be used. Use "||" to separate the different tags (e.g.: a1||a2||a3)','events-made-easy')."
                           <br />".__('For text-type fields, this can be used to enter a default value.','events-made-easy')."
                           <label for='field_attributes'>".__('HTML field attributes', 'events-made-easy')."</label>
                           <input name='field_attributes' id='field_attributes' type='text' value='' size='40' />
                           <br />".__('Here you can specify extra html attributes for your field (like size, maxlength, pattern, ...','events-made-easy')."
                         </div>
                         <p class='submit'><input type='submit' class='button-primary' name='submit' value='".__('Add field', 'events-made-easy')."' /></p>
                      </form>
                 </div>
                 <p>".__('For more information about form fields, see ', 'events-made-easy')."<a target='_blank' href='http://www.e-dynamics.be/wordpress/?cat=44'>".__('the documentation', 'events-made-easy')."</a></p>
               </div>
            </div>
            <?-- end col-left -->
         </div>
   </div>";
   echo $table;  
}

function eme_formfields_edit_layout($field_id,$message = "") {
   $formfield = eme_get_formfield_byid($field_id);
   $fieldtypes = eme_get_fieldtypes();
   $nonce_field = wp_nonce_field('eme_formfields','eme_admin_nonce',false,false);
   $layout = "
   <div class='wrap'>
      <div id='icon-edit' class='icon32'>
         <br />
      </div>
         
      <h1>".__('Edit field', 'events-made-easy')."</h1>";   
      
   if($message != "") {
      $layout .= "
      <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
         <p>$message</p>
      </div>";
   }
   $layout .= "
      <div id='warning' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
         <p>".__('Warning: changing the field type or values for a field that is already used in RSVP replies might result in unwanted side effects.', 'events-made-easy')."</p>
      </div>
      <div id='ajax-response'></div>

      <form name='edit_formfield' id='edit_formfield' method='post' action='".admin_url("admin.php?page=eme-formfields")."' class='validate'>
      <input type='hidden' name='eme_admin_action' value='do_editformfield' />
      $nonce_field
      <input type='hidden' name='field_id' value='".$formfield['field_id']."' />
      
      <table class='form-table'>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='field_name'>".__('Field name', 'events-made-easy')."</label></th>
               <td><input name='field_name' id='field_name' type='text' value='".eme_esc_html($formfield['field_name'])."' size='40' /></td>
            </tr>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='field_type'>".__('Field type', 'events-made-easy')."</label></th>
               <td>".eme_ui_select($formfield['field_type'],"field_type",$fieldtypes)."</td>
            </tr>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='extra_charge'>".__('Extra charge', 'events-made-easy')."</label></th>
	       <td>". eme_ui_select_binary($formfield['extra_charge'],"extra_charge") ."
                  <br />".__('Use this is the field indicates an extra charge to the total price','events-made-easy')."
            </tr>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='field_info'>".__('Field values', 'events-made-easy')."</label></th>
               <td><input name='field_info' id='field_info' type='text' value='".eme_esc_html($formfield['field_info'])."' size='40' />
                  <br />".__('Tip: for multivalue field types (like Drop Down), use "||" to separate the different values (e.g.: a1||a2||a3)','events-made-easy')."
               </td>
            </tr>
            <tr class='form-tags form-required'>
               <th scope='row' valign='top'><label for='field_tags'>".__('Field tags', 'events-made-easy')."</label></th>
               <td><input name='field_tags' id='field_tags' type='text' value='".eme_esc_html($formfield['field_tags'])."' size='40' />
                  <br />".__('For multivalue fields, you can here enter the "visible" tags people will see. If left empty, the field values will be used. Use "||" to separate the different tags (e.g.: a1||a2||a3)','events-made-easy')."
               </td>
            </tr>
            <tr class='form-tags form-required'>
               <th scope='row' valign='top'><label for='field_attributes'>".__('HTML field attributes', 'events-made-easy')."</label></th>
               <td><input name='field_attributes' id='field_attributes' type='text' value='".eme_esc_html($formfield['field_attributes'])."' size='40' />
                   <br />".__('Here you can specify extra html attributes for your field (like size, maxlength, pattern, ...','events-made-easy')."
               </td>
            </tr>
      </table>
      <p class='submit'><input type='submit' class='button-primary' name='submit' value='".__('Update field', 'events-made-easy')."' /></p>
      </form>
         
   </div>
   <p>".__('For more information about form fields, see ', 'events-made-easy')."<a target='_blank' href='http://www.e-dynamics.be/wordpress/?cat=44'>".__('the documentation', 'events-made-easy')."</a></p>
   ";  
   echo $layout;
}

function eme_get_used_formfield_ids(){
   global $wpdb;
   $table = $wpdb->prefix.ANSWERS_TBNAME; 
   return $wpdb->get_col("SELECT DISTINCT field_id FROM $table");
}

function eme_get_formfields(){
   global $wpdb;
   $formfields_table = $wpdb->prefix.FORMFIELDS_TBNAME; 
   return $wpdb->get_results("SELECT * FROM $formfields_table", ARRAY_A);
}

function eme_get_fieldtypes(){
   global $wpdb;
   $fieldtypes_table = $wpdb->prefix.FIELDTYPES_TBNAME; 
   $formfields = array();
   return $wpdb->get_results("SELECT * FROM $fieldtypes_table", ARRAY_N);
}

function eme_get_formfield_byid($field_id) { 
   global $wpdb;
   $formfields_table = $wpdb->prefix.FORMFIELDS_TBNAME; 
   $sql = $wpdb->prepare("SELECT * FROM $formfields_table WHERE field_id=%d",$field_id);
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_get_formfield_id_byname($field_name) { 
   global $wpdb;
   $formfields_table = $wpdb->prefix.FORMFIELDS_TBNAME; 
   $sql = $wpdb->prepare("SELECT field_id  FROM $formfields_table WHERE field_name=%s",$field_name);
   return $wpdb->get_var($sql);
}

function eme_get_fieldtype($type_id){
   global $wpdb;
   $fieldtypes_table = $wpdb->prefix.FIELDTYPES_TBNAME; 
   $formfields = array();
   $sql = "SELECT type_info FROM $fieldtypes_table WHERE type_id ='$type_id'";   
   return $wpdb->get_var($sql);
}

function eme_is_textareafield($type_id){
   return $type_id==3;
}

function eme_is_multifield($type_id){
   global $wpdb;
   $fieldtypes_table = $wpdb->prefix.FIELDTYPES_TBNAME; 
   $formfields = array();
   $sql = "SELECT is_multi FROM $fieldtypes_table WHERE type_id ='$type_id'";   
   return $wpdb->get_var($sql);
}

function eme_get_formfield_html($field_id, $entered_val, $required, $class='') {
   $formfield = eme_get_formfield_byid($field_id);
   if (!$formfield) return;

   if ($class)
	   $class_att="class='$class'";
   else
	   $class_att="";

   if ($required)
      $required_att="required='required'";
   else
      $required_att="";

   $field_info = $formfield['field_info'];
   $field_tags = $formfield['field_tags'];
   $field_attributes = eme_esc_html($formfield['field_attributes']);
   if (empty($field_tags))
      $field_tags=$field_info;
   $field_name='FIELD'.$field_id;
   switch($formfield['field_type']) {
      case 1:
	      # for text field
         $value=$entered_val;
         if (empty($value))
            $value=eme_translate($field_tags);
         if (empty($value))
            $value=$field_info;
         $value = eme_esc_html($value);
         $html = "<input $required_att type='text' name='$field_name' value='$value' $field_attributes $class_att />";
         break;
      case 2:
         # dropdown
         $values = eme_convert_multi2array($field_info);
         $tags = eme_convert_multi2array($field_tags);
         $my_arr = array();
         foreach ($values as $key=>$val) {
            $tag=$tags[$key];
            $my_arr[$val]=eme_translate($tag);
         }
         $html = eme_ui_select($entered_val,$field_name,$my_arr,$required,$class,$field_attributes);
         break;
      case 3:
         # textarea
         $value=$entered_val;
         if (empty($value))
            $value=eme_translate($field_tags);
         if (empty($value))
            $value=$field_info;
         $value = eme_esc_html($value);
         $html = "<textarea $required_att name='$field_name' $field_attributes>$value</textarea>";
         break;
      case 4:
         # radiobox
         $values = eme_convert_multi2array($field_info);
         $tags = eme_convert_multi2array($field_tags);
         $my_arr = array();
         foreach ($values as $key=>$val) {
            $tag=$tags[$key];
            $my_arr[$val]=eme_translate($tag);
         }
         $html = eme_ui_radio($entered_val,$field_name,$my_arr,true,$required,$class);
         break;
      case 5:
         # radiobox, vertical
         $values = eme_convert_multi2array($field_info);
         $tags = eme_convert_multi2array($field_tags);
         $my_arr = array();
         foreach ($values as $key=>$val) {
            $tag=$tags[$key];
            $my_arr[$val]=eme_translate($tag);
         }
         $html = eme_ui_radio($entered_val,$field_name,$my_arr,false,$required,$class);
         break;
      case 6:
      	# checkbox
         $values = eme_convert_multi2array($field_info);
         $tags = eme_convert_multi2array($field_tags);
         $my_arr = array();
         foreach ($values as $key=>$val) {
            $tag=$tags[$key];
            $my_arr[$val]=eme_translate($tag);
         }
         $html = eme_ui_checkbox($entered_val,$field_name,$my_arr,true,$required,$class);
         break;
      case 7:
      	# checkbox, vertical
         $values = eme_convert_multi2array($field_info);
         $tags = eme_convert_multi2array($field_tags);
         $my_arr = array();
         foreach ($values as $key=>$val) {
            $tag=$tags[$key];
            $my_arr[$val]=eme_translate($tag);
         }
         $html = eme_ui_checkbox($entered_val,$field_name,$my_arr,false,$required,$class);
         break;
      case 8:
	      # for date field
         $value=$entered_val;
         if (empty($value))
            $value=eme_translate($field_tags);
         if (empty($value))
            $value=$field_info;
         $value = eme_esc_html($value);
         $html = "<input $required_att type='date' name='$field_name' value='$value' $field_attributes />";
         break;
   }
   return $html;
}

function eme_replace_cancelformfields_placeholders ($event) {
   // not used from the admin backend, but we check to be sure
   if (is_admin()) return;

   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only && !is_user_logged_in()) return '';

   if ($registration_wp_users_only) {
      $readonly="disabled='disabled'";
   } else {
      $readonly="";
   }

   if (!empty($event['event_cancel_form_format']))
      $format = $event['event_cancel_form_format'];
   elseif ($event['event_properties']['event_cancel_form_format_tpl']>0)
      $format = eme_get_template_format($event['event_properties']['event_cancel_form_format_tpl']);
   else
      $format = get_option('eme_cancel_form_format' );


   $eme_captcha_for_booking=get_option('eme_captcha_for_booking');

   $required_fields_count = 0;
   // We need at least #_NAME, #_EMAIL and #_SUBMIT
   $required_fields_min = 3;
   // if we require the captcha: add 1
   if ($eme_captcha_for_booking)
      $required_fields_min++;

   $bookerLastName="";
   $bookerFirstName="";
   $bookerEmail="";
   $bookerCancelComment="";
   if (is_user_logged_in()) {
      $current_user = wp_get_current_user();
      $bookerLastName=$current_user->user_lastname;
      if (empty($bookerLastName))
               $bookerLastName=$current_user->display_name;
      $bookerFirstName=$current_user->user_firstname;
      $bookerEmail=$current_user->user_email;
   }
   // check for previously filled in data
   // this in case people entered a wrong captcha
   if (isset($_POST['lastname'])) $bookerLastName = eme_esc_html(stripslashes_deep($_POST['lastname']));
   if (isset($_POST['firstname'])) $bookerFirstName = eme_esc_html(stripslashes_deep($_POST['firstname']));
   if (isset($_POST['email'])) $bookerEmail = eme_esc_html(stripslashes_deep($_POST['email']));
   if (isset($_POST['cancelcomment'])) $bookerCancelComment = eme_esc_html(stripslashes_deep($_POST['cancelcomment']));

   // the 2 placeholders that can contain extra text are treated separately first
   // the question mark is used for non greedy (minimal) matching
   if (preg_match('/#_CAPTCHAHTML\{.+\}/', $format)) {
      // only show the captcha when booking via the frontend, not the admin backend
      if ($eme_captcha_for_booking)
         $format = preg_replace('/#_CAPTCHAHTML\{(.+?)\}/', '$1' ,$format );
      else
         $format = preg_replace('/#_CAPTCHAHTML\{(.+?)\}/', '' ,$format );
   }

   if (preg_match('/#_SUBMIT\{.+\}/', $format)) {
      $format = preg_replace('/#_SUBMIT\{(.+?)\}/', "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".eme_trans_esc_html('$1')."' />" ,$format );
      $required_fields_count++;
   }

   // now the normal placeholders
   preg_match_all("/#(REQ)?_[A-Z0-9_]+/", $format, $placeholders);
   // make sure we set the largest matched placeholders first, otherwise if you found e.g.
   // #_LOCATION, part of #_LOCATIONPAGEURL would get replaced as well ...
   usort($placeholders[0],'eme_sort_stringlenth');
   # we need 3 required fields: #_NAME, #_EMAIL and #_SEATS
   # if these are not present: we don't replace anything and the form is worthless
   foreach($placeholders[0] as $result) {
      $orig_result = $result;
      $found=1;
      $required=0;
      $required_att="";
      $replacement = "";
      if (strstr($result,'#REQ')) {
         $result = str_replace("#REQ","#",$result);
         $required=1;
         $required_att="required='required'";
      }

      // also support RESPNAME, RESPEMAIL, ...
      if (strstr($result,'#_RESP')) {
         $result = str_replace("#_RESP","#_",$result);
      }

      if (preg_match('/#_NAME|#_LASTNAME/', $result)) {
         $replacement = "<input required='required' type='text' name='lastname' id='lastname' value='$bookerLastName' $readonly />";
         $required_fields_count++;
         // #_NAME is always required
         $required=1;
      } elseif (preg_match('/#_FIRSTNAME/', $result)) {
         if (!empty($bookerFirstName))
            $replacement = "<input $required_att type='text' name='firstname' id='firstname' value='$bookerFirstName' $readonly />";
         else
            $replacement = "<input $required_att type='text' name='firstname' id='firstname' value='$bookerFirstName' />";
      } elseif (preg_match('/#_HTML5_EMAIL/', $result)) {
         $replacement = "<input required='required' type='email' name='email' id='email' value='$bookerEmail' $readonly />";
         $required_fields_count++;
         // #_EMAIL is always required
         $required=1;
      } elseif (preg_match('/#_EMAIL/', $result)) {
         $replacement = "<input required='required' type='text' name='email' id='email' value='$bookerEmail' $readonly />";
         $required_fields_count++;
         // #_EMAIL is always required
         $required=1;
      } elseif (preg_match('/#_CANCELCOMMENT/', $result)) {
         $replacement = "<textarea $required_att name='cancelcomment'>$bookerCancelComment</textarea>";
      } elseif (preg_match('/#_CAPTCHA/', $result) && $eme_captcha_for_booking) {
         $captcha_url=eme_captcha_url("eme_del_booking");
         $replacement = "<img src='$captcha_url'><br /><input required='required' type='text' name='captcha_check' autocomplete='off' />";
         $required_fields_count++;
      } elseif (preg_match('/#_SUBMIT/', $result, $matches)) {
         $replacement = "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".eme_trans_esc_html(get_option('eme_rsvp_delbooking_submit_string'))."' />";
         $required_fields_count++;
      } else {
         $found = 0;
      }

      if ($required)
         $replacement .= "<div class='eme-required-field'>".get_option('eme_rsvp_required_field_string')."</div>";

      if ($found) {
         $format = str_replace($orig_result, $replacement ,$format );
      }
   }

   // now any leftover event placeholders
   $format = eme_replace_placeholders($format, $event);

   // now, replace any language tags found in the format itself
   $format = eme_translate($format);

   if ($required_fields_count >= $required_fields_min) {
      return $format;
   } else {
      return __('Not all required fields are present in the cancel form.', 'events-made-easy');
   }
}

// the event param in eme_replace_extra_multibooking_formfields_placeholders
// is only there for generic replacements, like e.g. currency
function eme_replace_extra_multibooking_formfields_placeholders ($format,$event) {
   $bookerLastName="";
   $bookerFirstName="";
   $bookerAddress1="";
   $bookerAddress2="";
   $bookerCity="";
   $bookerState="";
   $bookerZip="";
   $bookerCountry="";
   $bookerEmail="";
   $bookerComment="";
   $bookerPhone="";

   if (is_user_logged_in()) {
      $current_user = wp_get_current_user();
      $bookerLastName=$current_user->user_lastname;
      if (empty($bookerLastName))
               $bookerLastName=$current_user->display_name;
      $bookerFirstName=$current_user->user_firstname;
      $bookerEmail=$current_user->user_email;
      $bookerPhone=$current_user->eme_phone;
   }

   if (isset($_POST['lastname'])) $bookerLastName = eme_esc_html(stripslashes_deep($_POST['lastname']));
   if (isset($_POST['firstname'])) $bookerFirstName = eme_esc_html(stripslashes_deep($_POST['firstname']));
   if (isset($_POST['address1'])) $bookerAddress1 = eme_esc_html(stripslashes_deep($_POST['address1']));
   if (isset($_POST['address2'])) $bookerAddress2 = eme_esc_html(stripslashes_deep($_POST['address2']));
   if (isset($_POST['city'])) $bookerCity = eme_esc_html(stripslashes_deep($_POST['city']));
   if (isset($_POST['state'])) $bookerState = eme_esc_html(stripslashes_deep($_POST['state']));
   if (isset($_POST['zip'])) $bookerZip = eme_esc_html(stripslashes_deep($_POST['zip']));
   if (isset($_POST['country'])) $bookerCountry = eme_esc_html(stripslashes_deep($_POST['country']));
   if (isset($_POST['email'])) $bookerEmail = eme_esc_html(stripslashes_deep($_POST['email']));
   if (isset($_POST['phone'])) $bookerPhone = eme_esc_html(stripslashes_deep($_POST['phone']));
   if (isset($_POST['comment'])) $bookerComment = eme_esc_html(stripslashes_deep($_POST['comment']));

   $eme_captcha_for_booking=get_option('eme_captcha_for_booking');

   // the class is used for dynamic price calculation
   if (get_option('eme_calc_price_dynamically'))
      $dynamic_price_class='seatsordiscount';
   else 
      $dynamic_price_class='';

   // the 2 placeholders that can contain extra text are treated separately first
   // the question mark is used for non greedy (minimal) matching
   if (preg_match('/#_CAPTCHAHTML\{.+\}/', $format)) {
      // only show the captcha when booking via the frontend, not the admin backend
      if ($eme_captcha_for_booking)
         $format = preg_replace('/#_CAPTCHAHTML\{(.+?)\}/', '$1' ,$format );
   }

   if (preg_match('/#_SUBMIT\{.+\}/', $format)) {
      $format = preg_replace('/#_SUBMIT\{(.+?)\}/', "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".eme_trans_esc_html('$1')."' />" ,$format );
   }

   // now the normal placeholders
   preg_match_all("/#(REQ)?_?[A-Z0-9_]+(\{[A-Z0-9_]+\})?/", $format, $placeholders);
   // make sure we set the largest matched placeholders first, otherwise if you found e.g.
   // #_LOCATION, part of #_LOCATIONPAGEURL would get replaced as well ...
   usort($placeholders[0],'eme_sort_stringlenth');
   # we need 3 required fields: #_NAME, #_EMAIL and #_SEATS
   # if these are not present: we don't replace anything and the form is worthless
   foreach($placeholders[0] as $result) {
      $orig_result = $result;
      $found=1;
      $required=0;
      $required_att="";
      $replacement = "";
      if (strstr($result,'#REQ')) {
         $result = str_replace("#REQ","#",$result);
         $required=1;
         $required_att="required='required'";
      }

      // also support RESPNAME, RESPEMAIL, ...
      if (strstr($result,'#_RESP')) {
         $result = str_replace("#_RESP","#_",$result);
      }

      if (preg_match('/#_NAME|#_LASTNAME/', $result)) {
         $replacement = "<input required='required' type='text' name='lastname' id='lastname' value='$bookerLastName' />";
         // #_NAME is always required
         $required=1;
      } elseif (preg_match('/#_FIRSTNAME/', $result)) {
         $replacement = "<input $required_att type='text' name='firstname' id='firstname' value='$bookerFirstName' />";
      } elseif (preg_match('/#_ADDRESS1/', $result)) {
         $replacement = "<input $required_att type='text' name='address1' id=address1' value='$bookerAddress1' />";
      } elseif (preg_match('/#_ADDRESS2/', $result)) {
         $replacement = "<input $required_att type='text' name='address2' id='address2' value='$bookerAddress2' />";
      } elseif (preg_match('/#_CITY/', $result)) {
         $replacement = "<input $required_att type='text' name='city' id='city' value='$bookerCity' />";
      } elseif (preg_match('/#_STATE/', $result)) {
         $replacement = "<input $required_att type='text' name='state' id='state' value='$bookerState' />";
      } elseif (preg_match('/#_ZIP/', $result)) {
         $replacement = "<input $required_att type='text' name='zip' id='zip' value='$bookerZip' />";
      } elseif (preg_match('/#_COUNTRY/', $result)) {
         $replacement = "<input $required_att type='text' name='country' id='country' value='$bookerCountry' />";
      } elseif (preg_match('/#_HTML5_EMAIL/', $result)) {
         $replacement = "<input required='required' type='email' name='email' id='email' value='$bookerEmail' />";
         // #_EMAIL is always required
         $required=1;
      } elseif (preg_match('/#_EMAIL/', $result)) {
         $replacement = "<input required='required' type='text' name='email' id='email' value='$bookerEmail' />";
         // #_EMAIL is always required
         $required=1;
      } elseif (preg_match('/#_HTML5_PHONE/', $result)) {
         $replacement = "<input $required_att type='tel' name='phone' id='phone' value='$bookerPhone' />";
      } elseif (preg_match('/#_PHONE/', $result)) {
         $replacement = "<input $required_att type='text' name='phone' id='phone' value='$bookerPhone' />";
      } elseif (preg_match('/#_COMMENT/', $result)) {
         $replacement = "<textarea $required_att name='comment'>$bookerComment</textarea>";
      } elseif (preg_match('/#_CAPTCHA/', $result) && $eme_captcha_for_booking) {
         $captcha_url=eme_captcha_url("eme_add_booking");
         $replacement = "<img src='$captcha_url'><br /><input required='required' type='text' name='captcha_check' autocomplete='off' />";
      } elseif (preg_match('/#_SUBMIT/', $result, $matches)) {
         $replacement = "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".eme_trans_esc_html(get_option('eme_rsvp_addbooking_submit_string'))."' />";
      } elseif (!empty($dynamic_price_class) && preg_match('/#_DYNAMICPRICE$/', $result)) {
	 $replacement = "<span id='eme_calc_price'></span>";
      } elseif (preg_match('/#_FIELDNAME\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $replacement = eme_trans_esc_html($formfield['field_name']);
      } elseif (preg_match('/#_FIELD\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $postfield_name="FIELD".$field_id;
         if (isset($_POST[$postfield_name]))
            $entered_val = stripslashes_deep($_POST[$postfield_name]);
         else
            $entered_val = "";
	 if ($formfield['extra_charge'])
		 $replacement = eme_get_formfield_html($field_id,$entered_val,$required,$dynamic_price_class);
	 else
		 $replacement = eme_get_formfield_html($field_id,$entered_val,$required);

      } else {
         $found = 0;
      }

      if ($required)
         $replacement .= "<div class='eme-required-field'>".get_option('eme_rsvp_required_field_string')."</div>";

      if ($found) {
         $format = str_replace($orig_result, $replacement ,$format );
      }
   }

   // now any leftover event placeholders
   $format = eme_replace_placeholders($format, $event);

   // now, replace any language tags found in the format itself
   $format = eme_translate($format);
   return $format;
}

function eme_replace_formfields_placeholders ($event,$booking,$format="",$is_multibooking=0) {

   $event_id=$event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only && !is_user_logged_in()) {
      return '';
   }

   $is_admin=is_admin();
   if ($is_admin && isset($booking['booking_id'])) {
      $editing_booking_from_backend=1;
   } else {
      $editing_booking_from_backend=0;
   }

   $current_user = wp_get_current_user();
   // if not in the backend and wp membership is required
   // or when editing an existing booking via backend (not a new)
   if ($registration_wp_users_only && !$is_admin) {
      $search_tables=get_option('eme_autocomplete_sources');
      if ($search_tables=='none') {
         $readonly="disabled='disabled'";
      } elseif (current_user_can( get_option('eme_cap_edit_events')) ||
         (current_user_can( get_option('eme_cap_author_event')) && ($event['event_author']==$current_user->ID || $event['event_contactperson_id']==$current_user->ID))) {
         $readonly="";
      } else {
         $readonly="disabled='disabled'";
      }
   } elseif ($editing_booking_from_backend) {
      $readonly="disabled='disabled'";
   } else {
      $readonly="";
   }

   if (empty($format)) {
      if (!empty($event['event_registration_form_format']))
         $format = $event['event_registration_form_format'];
      elseif ($event['event_properties']['event_registration_form_format_tpl']>0)
         $format = eme_get_template_format($event['event_properties']['event_registration_form_format_tpl']);
      else
         $format = get_option('eme_registration_form_format' );
   }

   $min_allowed = $event['event_properties']['min_allowed'];
   $max_allowed = $event['event_properties']['max_allowed'];
   //if ($event['event_properties']['take_attendance']) {
   //   $min_allowed = 0;
   //   $max_allowed = 1;
   //}

   $waitinglist=0;
   $waitinglist_seats = intval($event['event_properties']['waitinglist_seats']);
   if ($editing_booking_from_backend) {
      // in the admin itf, and editing a booking
      // then the avail seats are the total seats
      $avail_seats = eme_get_total($event['event_seats']);
   } else {
      // the next gives the number of available seats, even for multiprice
      $avail_seats = eme_get_available_seats($event_id);
      if ($waitinglist_seats>0 && $avail_seats>$waitinglist_seats && !eme_is_multi($event['event_seats'])) {
	      $avail_seats-=$waitinglist_seats;
      } elseif ($waitinglist_seats>0 && $avail_seats<=$waitinglist_seats && !eme_is_multi($event['event_seats'])) {
	      $waitinglist=1;
      }
   }

   $booked_places_options = array();
   if (eme_is_multi($max_allowed)) {
      $multi_max_allowed=eme_convert_multi2array($max_allowed);
      $max_allowed_is_multi=1;
   } else {
      $max_allowed_is_multi=0;
   }
   if (eme_is_multi($min_allowed)) {
      $multi_min_allowed=eme_convert_multi2array($min_allowed);
      $min_allowed_is_multi=1;
   } else {
      $min_allowed_is_multi=0;
   }
   if (eme_is_multi($event['event_seats'])) {
      // in the admin itf, and editing a booking
      // then the avail seats are the total seats
      if ($editing_booking_from_backend)
         $multi_avail = eme_convert_multi2array($event['event_seats']);
      else
         $multi_avail = eme_get_available_multiseats($event_id);

      foreach ($multi_avail as $key => $avail_seats) {
         $booked_places_options[$key] = array();
         if ($max_allowed_is_multi)
            $real_max_allowed=$multi_max_allowed[$key];
         else
            $real_max_allowed=$max_allowed;
         
         // don't let people choose more seats than available
         if ($real_max_allowed>$avail_seats || $real_max_allowed==0)
            $real_max_allowed=$avail_seats;

         if ($min_allowed_is_multi)
            $real_min_allowed=$multi_min_allowed[$key];
         else
            // it's no use to have a non-multi minimum for multiseats
            $real_min_allowed=0;
         
         for ( $i = $real_min_allowed; $i <= $real_max_allowed; $i++) 
            $booked_places_options[$key][$i]=$i;
      }
   } elseif (eme_is_multi($event['price'])) {
      // we just need to loop through the same amount of seats as there are prices
      foreach (eme_convert_multi2array($event['price']) as $key => $value) {
         $booked_places_options[$key] = array();
         if ($max_allowed_is_multi)
            $real_max_allowed=$multi_max_allowed[$key];
         else
            $real_max_allowed=$max_allowed;

         // don't let people choose more seats than available
         if ($real_max_allowed>$avail_seats || $real_max_allowed==0)
            $real_max_allowed=$avail_seats;

         if ($min_allowed_is_multi)
            $real_min_allowed=$multi_min_allowed[$key];
         else
            // it's no use to have a non-multi minimum for multiseats/multiprice
            $real_min_allowed=0;

         for ( $i = $real_min_allowed; $i <= $real_max_allowed; $i++)
            $booked_places_options[$key][$i]=$i;
      }
   } else {
      if ($max_allowed_is_multi)
         $real_max_allowed=$multi_max_allowed[0];
      else
         $real_max_allowed=$max_allowed;

      // don't let people choose more seats than available
      if ($real_max_allowed > $avail_seats || $real_max_allowed==0)
         $real_max_allowed = $avail_seats;

      if ($min_allowed_is_multi)
         $real_min_allowed=$multi_min_allowed[0];
      else
         $real_min_allowed=$min_allowed;

      for ( $i = $real_min_allowed; $i <= $real_max_allowed; $i++) 
         $booked_places_options[$i]=$i;
   }

   $required_fields_count = 0;
   $discount_fields_count = 0;
   $eme_captcha_for_booking=get_option('eme_captcha_for_booking');
   # we need 4 required fields: #_NAME, #_EMAIL, #_SEATS and #_SUBMIT
   # for multiprice: 3 + number of possible prices (we add those later on)
   if (eme_is_multi($event['price']))
      $required_fields_min = 3;
   else
      $required_fields_min = 4;
   // if we require the captcha: add 1
   if (!$is_admin && $eme_captcha_for_booking)
      $required_fields_min++;

   // for multi booking forms, the required field count per booking form is 1 (SEATS)
   if (!$is_admin && $is_multibooking) {
      if (eme_is_multi($event['price']))
         $required_fields_min = 0;
      else
         $required_fields_min =1;
   }

   $bookerLastName="";
   $bookerFirstName="";
   $bookerAddress1="";
   $bookerAddress2="";
   $bookerCity="";
   $bookerState="";
   $bookerZip="";
   $bookerCountry="";
   $bookerEmail="";
   $bookerComment="";
   $bookerPhone="";
   $bookedSeats=0;

   if (is_user_logged_in()) {
      $bookerLastName=$current_user->user_lastname;
      if (empty($bookerLastName))
               $bookerLastName=$current_user->display_name;
      $bookerFirstName=$current_user->user_firstname;
      $bookerEmail=$current_user->user_email;
      $bookerPhone=$current_user->eme_phone;
   }

   if ($editing_booking_from_backend) {
      $person = eme_get_person ($booking['person_id']);
      // when editing a booking
      $bookerLastName = eme_esc_html($person['lastname']);
      $bookerFirstName = eme_esc_html($person['firstname']);
      $bookerAddress1 = eme_esc_html($person['address1']);
      $bookerAddress2 = eme_esc_html($person['address2']);
      $bookerCity = eme_esc_html($person['city']);
      $bookerState = eme_esc_html($person['state']);
      $bookerZip = eme_esc_html($person['zip']);
      $bookerCountry = eme_esc_html($person['country']);
      $bookerEmail = eme_esc_html($person['email']);
      $bookerPhone = eme_esc_html($person['phone']);
      $bookerComment = eme_esc_html($booking['booking_comment']);
      $bookedSeats = eme_esc_html($booking['booking_seats']);
      if ($booking['booking_seats_mp']) {
         $booking_seats_mp=eme_convert_multi2array($booking['booking_seats_mp']);
         foreach ($booking_seats_mp as $key=>$val) {
            $field_index=$key+1;
            ${"bookedSeats".$field_index}=eme_esc_html($val);
         }
      }
   } else {
      // check for previously filled in data
      // this in case people entered a wrong captcha
      if (isset($_POST['lastname'])) $bookerLastName = eme_esc_html(stripslashes_deep($_POST['lastname']));
      if (isset($_POST['firstname'])) $bookerFirstName = eme_esc_html(stripslashes_deep($_POST['firstname']));
      if (isset($_POST['address1'])) $bookerAddress1 = eme_esc_html(stripslashes_deep($_POST['address1']));
      if (isset($_POST['address2'])) $bookerAddress2 = eme_esc_html(stripslashes_deep($_POST['address2']));
      if (isset($_POST['city'])) $bookerCity = eme_esc_html(stripslashes_deep($_POST['city']));
      if (isset($_POST['state'])) $bookerState = eme_esc_html(stripslashes_deep($_POST['state']));
      if (isset($_POST['zip'])) $bookerZip = eme_esc_html(stripslashes_deep($_POST['zip']));
      if (isset($_POST['country'])) $bookerCountry = eme_esc_html(stripslashes_deep($_POST['country']));
      if (isset($_POST['email'])) $bookerEmail = eme_esc_html(stripslashes_deep($_POST['email']));
      if (isset($_POST['phone'])) $bookerPhone = eme_esc_html(stripslashes_deep($_POST['phone']));
      if (isset($_POST['comment'])) $bookerComment = eme_esc_html(stripslashes_deep($_POST['comment']));
   }

   // first we do the custom attributes, since these can contain other placeholders
   preg_match_all("/#(ESC|URL)?_ATT\{.+?\}(\{.+?\})?/", $format, $results);
   foreach($results[0] as $resultKey => $result) {
      $need_escape = 0;
      $need_urlencode = 0;
      $orig_result = $result;
      if (strstr($result,'#ESC')) {
         $result = str_replace("#ESC","#",$result);
         $need_escape=1;
      } elseif (strstr($result,'#URL')) {
         $result = str_replace("#URL","#",$result);
         $need_urlencode=1;
      }
      $replacement = "";
      //Strip string of placeholder and just leave the reference
      $attRef = substr( substr($result, 0, strpos($result, '}')), 6 );
      if (isset($event['event_attributes'][$attRef])) {
         $replacement = $event['event_attributes'][$attRef];
      }
      if( trim($replacement) == ''
            && isset($results[2][$resultKey])
            && $results[2][$resultKey] != '' ) {
         //Check to see if we have a second set of braces;
         $replacement = substr( $results[2][$resultKey], 1, strlen(trim($results[2][$resultKey]))-2 );
      }

      if ($need_escape)
         $replacement = eme_sanitize_request(eme_esc_html(preg_replace('/\n|\r/','',$replacement)));
      if ($need_urlencode)
         $replacement = rawurlencode($replacement);
      $format = str_replace($orig_result, $replacement ,$format );
   }

   // the 2 placeholders that can contain extra text are treated separately first
   // the question mark is used for non greedy (minimal) matching
   if (preg_match('/#_CAPTCHAHTML\{.+\}/', $format)) {
      // only show the captcha when booking via the frontend, not the admin backend
      if (!$is_admin && $eme_captcha_for_booking)
         $format = preg_replace('/#_CAPTCHAHTML\{(.+?)\}/', '$1' ,$format );
      else
         $format = preg_replace('/#_CAPTCHAHTML\{(.+?)\}/', '' ,$format );
   }

   if (preg_match('/#_SUBMIT\{.+\}/', $format)) {
      if ($editing_booking_from_backend)
         $format = preg_replace('/#_SUBMIT\{(.+?)\}/', "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".__('Update booking','events-made-easy')."' />" ,$format );
      else
         $format = preg_replace('/#_SUBMIT\{(.+?)\}/', "<input name='eme_submit_button' class='eme_submit_button' type='submit' value='".eme_trans_esc_html('$1')."' />" ,$format );
      if (!$is_multibooking)
         $required_fields_count++;
   }

   // the class is used for dynamic price calculation
   if (get_option('eme_calc_price_dynamically'))
      $dynamic_price_class='seatsordiscount';
   else 
      $dynamic_price_class='';

   // now the normal placeholders
   preg_match_all("/#(REQ)?_?[A-Z0-9_]+(\{[A-Z0-9_]+\})?/", $format, $placeholders);
   // make sure we set the largest matched placeholders first, otherwise if you found e.g.
   // #_LOCATION, part of #_LOCATIONPAGEURL would get replaced as well ...
   usort($placeholders[0],'eme_sort_stringlenth');
   # we need 3 required fields: #_NAME, #_EMAIL and #_SEATS
   # if these are not present: we don't replace anything and the form is worthless
   foreach($placeholders[0] as $result) {
      $orig_result = $result;
      $found=1;
      $required=0;
      $required_att="";
      $replacement = "";
      if (strstr($result,'#REQ')) {
         $result = str_replace("#REQ","#",$result);
         $required=1;
         $required_att="required='required'";
      }

      // also support RESPNAME, RESPEMAIL, ...
      if (strstr($result,'#_RESP')) {
         $result = str_replace("#_RESP","#_",$result);
      }

      if ($is_multibooking) {
         $var_prefix="bookings[$event_id][";
         $var_postfix="]";
      } else {
         $var_prefix='';
         $var_postfix='';
      }

      if (preg_match('/#_NAME|#_LASTNAME/', $result)) {
         if (!$is_multibooking) {
            $replacement = "<input required='required' type='text' name='${var_prefix}lastname${var_postfix}' value='$bookerLastName' $readonly />";
            $required_fields_count++;
            // #_NAME is always required
            $required=1;
         }
      } elseif (preg_match('/#_FIRSTNAME/', $result)) {
         if (!empty($bookerFirstName))
            $replacement = "<input $required_att type='text' name='${var_prefix}firstname${var_postfix}' value='$bookerFirstName' $readonly />";
         else
            $replacement = "<input $required_att type='text' name='${var_prefix}firstname${var_postfix}' value='$bookerFirstName' />";
      } elseif (preg_match('/#_ADDRESS1/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}address1${var_postfix}' value='$bookerAddress1' />";
      } elseif (preg_match('/#_ADDRESS2/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}address2${var_postfix}' value='$bookerAddress2' />";
      } elseif (preg_match('/#_CITY/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}city${var_postfix}' value='$bookerCity' />";
      } elseif (preg_match('/#_STATE/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}state${var_postfix}' value='$bookerState' />";
      } elseif (preg_match('/#_ZIP/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}zip${var_postfix}' value='$bookerZip' />";
      } elseif (preg_match('/#_COUNTRY/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}country${var_postfix}' value='$bookerCountry' />";
      } elseif (preg_match('/#_HTML5_EMAIL/', $result)) {
         if (!$is_multibooking) {
            $replacement = "<input required='required' type='email' name='${var_prefix}email${var_postfix}' value='$bookerEmail' $readonly />";
            $required_fields_count++;
            // #_EMAIL is always required
            $required=1;
         }
      } elseif (preg_match('/#_EMAIL/', $result)) {
         if (!$is_multibooking) {
            $replacement = "<input required='required' type='text' name='${var_prefix}email${var_postfix}' value='$bookerEmail' $readonly />";
            $required_fields_count++;
            // #_EMAIL is always required
            $required=1;
         }
      } elseif (preg_match('/#_HTML5_PHONE/', $result)) {
         $replacement = "<input $required_att type='tel' name='${var_prefix}phone${var_postfix}' value='$bookerPhone' />";
      } elseif (preg_match('/#_PHONE/', $result)) {
         $replacement = "<input $required_att type='text' name='${var_prefix}phone${var_postfix}' value='$bookerPhone' />";
      } elseif (!empty($dynamic_price_class) && preg_match('/#_DYNAMICPRICE$/', $result)) {
         if (!$is_multibooking)
		 $replacement = "<span id='eme_calc_price'></span>";
      } elseif (preg_match('/#_SEATS$|#_SPACES$/', $result)) {
         $var_prefix="bookings[$event_id][";
         $var_postfix="]";
         $postfield_name="${var_prefix}bookedSeats${var_postfix}";
         if ($editing_booking_from_backend && isset($bookedSeats))
            $entered_val=$bookedSeats;
         elseif (isset($_POST['bookings'][$event_id]) && isset($_POST['bookings'][$event_id]['bookedSeats']))
            $entered_val = intval($_POST['bookings'][$event_id]['bookedSeats']);
         else
            $entered_val=0;

         if ($event['event_properties']['take_attendance']) {
            // if we require 1 seat at the minimum, we set it to that
            // it could even be a hidden field then ...
            if (!$min_allowed_is_multi && $min_allowed>0) {
               $replacement = "<input type='hidden' name='$postfield_name' value='1' class='$dynamic_price_class'>";
            } else {
               $replacement = eme_ui_select_binary($entered_val,$postfield_name,0,$dynamic_price_class);
            }
         } else {
            $replacement = eme_ui_select($entered_val,$postfield_name,$booked_places_options,0,$dynamic_price_class);
	    if ($waitinglist) {
		    $replacement.="<span id='eme_waitinglist'>".__("This reservation will be put on the waiting list","events-made-easy")."</span>";
	    }
         }
         $required_fields_count++;

      } elseif (preg_match('/#_(SEATS|SPACES)\{(\d+)\}/', $result, $matches)) {
         $var_prefix="bookings[$event_id][";
         $var_postfix="]";
         $field_id = intval($matches[2]);
         $postfield_name="${var_prefix}bookedSeats".$field_id.$var_postfix;

         if ($editing_booking_from_backend && isset(${"bookedSeats".$field_id}))
            $entered_val=${"bookedSeats".$field_id};
         elseif (isset($_POST['bookings'][$event_id]) && isset($_POST['bookings'][$event_id]['bookedSeats'.$field_id]))
            $entered_val = intval($_POST['bookings'][$event_id]['bookedSeats'.$field_id]);
         else
            $entered_val=0;

         if (eme_is_multi($event['event_seats']) || eme_is_multi($event['price'])) {
            if ($event['event_properties']['take_attendance'])
               $replacement = eme_ui_select_binary($entered_val,$postfield_name,0,$dynamic_price_class);
            else
               $replacement = eme_ui_select($entered_val,$postfield_name,$booked_places_options[$field_id-1],0,$dynamic_price_class);
         } else {
            if ($event['event_properties']['take_attendance'])
               $replacement = eme_ui_select_binary($entered_val,$postfield_name,0,$dynamic_price_class);
            else
               $replacement = eme_ui_select($entered_val,$postfield_name,$booked_places_options,0,$dynamic_price_class);
         }
         $required_fields_count++;
      } elseif (preg_match('/#_COMMENT/', $result)) {
         if (!$is_multibooking)
            $replacement = "<textarea $required_att name='${var_prefix}comment${var_postfix}'>$bookerComment</textarea>";
      } elseif (preg_match('/#_CAPTCHA/', $result) && $eme_captcha_for_booking) {
         if (!$is_multibooking) {
            $captcha_url=eme_captcha_url("eme_add_booking");
            $replacement = "<img src='$captcha_url'><br /><input required='required' type='text' name='captcha_check' autocomplete='off' />";
            $required_fields_count++;
         }
      } elseif (preg_match('/#_FIELDNAME\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $replacement = eme_trans_esc_html($formfield['field_name']);
      } elseif (preg_match('/#_FIELD\{(\d+)\}/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $postfield_name="${var_prefix}FIELD".$field_id.$var_postfix;
         $entered_val = "";
         if ($editing_booking_from_backend) {
            $answers = eme_get_answers($booking['booking_id']);
            foreach ($answers as $answer) {
               if ($answer['field_id'] == $field_id) {
                  // the entered value for the function eme_get_formfield_html needs to be an array for multiple values
                  // since we store them with "||", we can use the good old eme_is_multi function and split in an array then
                  $entered_val = $answer['answer'];
                  if (eme_is_multi($entered_val)) {
                     $entered_val = eme_convert_multi2array($entered_val);
                  }
               }
            }
         } elseif (isset($_POST[$postfield_name])) {
            $entered_val = stripslashes_deep($_POST[$postfield_name]);
         }
	 if ($formfield['extra_charge'])
		 $replacement = eme_get_formfield_html($field_id,$entered_val,$required,$dynamic_price_class);
	 else
		 $replacement = eme_get_formfield_html($field_id,$entered_val,$required);
      } elseif (preg_match('/#_DISCOUNT$/', $result, $matches)) {
         $var_prefix="bookings[$event_id][";
         $var_postfix="]";
         // we need an ID to have a unique name per DISCOUNT input field
         $discount_fields_count++;
         if (!$is_admin) {
            $postfield_name="${var_prefix}DISCOUNT${discount_fields_count}${var_postfix}";
            if (isset($_POST['bookings'][$event_id]) && isset($_POST['bookings'][$event_id]['DISCOUNT'.$discount_fields_count])) {
               $entered_val = stripslashes_deep($_POST['bookings'][$event_id]['DISCOUNT'.$discount_fields_count]);
	    } else {
               $entered_val = "";
	    }
            $replacement = "<input class='$dynamic_price_class' type='text' name='$postfield_name' value='$entered_val' />";
         } else {
            if ($discount_fields_count==1) {
               // only 1 (fixed) discount field in the admin itf
               $postfield_name="DISCOUNT";
               $replacement = "<input type='text' name='$postfield_name' value='' /><br />".sprintf(__('Enter a new fixed discount value if wanted, or leave empty to keep the calculated value %s %s','events-made-easy'),eme_localized_price($booking['discount']),$event['currency']);
            } else {
               $replacement = __('Only one discount field can be used in the admin backend, the others are not rendered','events-made-easy');
            }
         }
      } elseif (preg_match('/#_SUBMIT/', $result, $matches)) {
         if (!$is_multibooking) {
            if ($editing_booking_from_backend)
               $replacement = "<input name='eme_submit_button' type='submit' value='".__('Update booking','events-made-easy')."' />";
            else
               $replacement = "<input name='eme_submit_button' type='submit' value='".eme_trans_esc_html(get_option('eme_rsvp_addbooking_submit_string'))."' />";
            $required_fields_count++;
         }
      } else {
         $found = 0;
      }

      if ($required)
         $replacement .= "<div class='eme-required-field'>".get_option('eme_rsvp_required_field_string')."</div>";

      if ($found) {
         // $format = str_replace($orig_result, $replacement ,$format );
         // only replace first found occurence, this helps to e.g. replace 2 occurences of #_DISCOUNT by 2 different things
         // preg_replace could do it too, but is less performant
	 $pos = strpos($format, $orig_result);
	 if ($pos !== false) {
		 $format = substr_replace($format, $replacement, $pos, strlen($orig_result));
	 }
      }
   }

   // now any leftover event placeholders
   $format = eme_replace_placeholders($format, $event);

   // now, replace any language tags found in the format itself
   $format = eme_translate($format);

   # we need 4 required fields: #_NAME, #_EMAIL, #_SEATS and #_SUBMIT
   # for multiprice: 3 + number of possible prices
   # if these are not present: we don't replace anything and the form is worthless
   if (eme_is_multi($event['price'])) {
      $matches=eme_convert_multi2array($event['price']);
      $count=count($matches);

      // the count can be >3+$count if conditional tags are used to combine a form for single and multiple prices
      if ($required_fields_count >= $required_fields_min+$count) {
         return $format;
      } else {
         $res = __('Not all required fields are present in the booking form.', 'events-made-easy');
         $res.= '<br />'.__("Since this is a multiprice event, make sure you changed the setting 'Registration Form Format' for the event to include #_SEATxx placeholders for each price.",'events-made-easy');
         $res.= '<br />'.__("See the documentation about multiprice events.",'events-made-easy');
         return "<div id='message' class='eme-rsvp-message'>$res</div>";
      }
   } elseif ($required_fields_count >= $required_fields_min) {
      // the count can be > 4 if conditional tags are used to combine a form for single and multiple prices
      return $format;
   } else {
      return __('Not all required fields are present in the booking form.', 'events-made-easy');
   }
}

function eme_find_required_formfields ($format) {
   preg_match_all("/#REQ_?[A-Z0-9_]+(\{[A-Z0-9_]+\})?/", $format, $placeholders);
   usort($placeholders[0],'eme_sort_stringlenth');
   // #_NAME and #REQ_NAME should be using _LASTNAME
   $result=preg_replace("/_NAME/","_LASTNAME",$placeholders[0]);
   // We just want the fieldnames: FIELD1, FIELD2, ... like they are POST'd via the form
   $result=preg_replace("/#REQ_|\{|\}/","",$result);
   // just to be sure: remove leading zeros in the names: FIELD01 should be FIELD1
   $result=preg_replace("/FIELD0+/","FIELD",$result);
   return $result;
}

?>
