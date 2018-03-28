<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function eme_templates_page() {      
   global $wpdb;
   
   if (!current_user_can( get_option('eme_cap_templates')) && (isset($_GET['eme_admin_action']) || isset($_POST['eme_admin_action']))) {
      $message = __('You have no right to update templates!','events-made-easy');
      eme_templates_table_layout($message);
      return;
   }
   if (isset($_GET['eme_admin_action']) && $_GET['eme_admin_action'] == "edit_template") { 
      // edit template  
      check_admin_referer('eme_templates','eme_admin_nonce');
      eme_templates_edit_layout();
      return;
   }

   // Insert/Update/Delete Record
   $templates_table = $wpdb->prefix.TEMPLATES_TBNAME;
   $validation_result = '';
   $message = '';
   if (isset($_POST['eme_admin_action'])) {
      check_admin_referer('eme_templates','eme_admin_nonce');
      if ($_POST['eme_admin_action'] == "do_edittemplate" && isset($_POST['description']) && isset($_POST['template_format']) ) {
         // template update required  
         $template = array();
         $template['description'] = eme_strip_tags($_POST['description']);
         $template['format'] = eme_strip_js($_POST['template_format']);
         $validation_result = $wpdb->update( $templates_table, $template, array('id' => intval($_POST['template_id'])) );
         if ($validation_result !== false) {
            $message = __("Successfully edited the template.", 'events-made-easy');
         } else {
            $message = __("There was a problem editing your template, please try again.",'events-made-easy');
         }
      } elseif ($_POST['eme_admin_action'] == "do_addtemplate" && isset($_POST['description']) && isset($_POST['template_format']) ) {
         // Add a new template
         $template = array();
         $template['description'] = eme_strip_tags($_POST['description']);
         $template['format'] = eme_strip_js($_POST['template_format']);
         $validation_result = $wpdb->insert($templates_table, $template);
         if ($validation_result !== false) {
            $message = __("Successfully added the template.", 'events-made-easy');
         } else {
            $message = __("There was a problem adding your template, please try again.",'events-made-easy');
         }
      } elseif ($_POST['eme_admin_action'] == "do_deletetemplate" && isset($_POST['templates'])) {
         // Delete template or multiple
         $templates = $_POST['templates'];
         if (!empty($templates) && eme_array_integers($templates)) {
               $validation_result = $wpdb->query( "DELETE FROM $templates_table WHERE id IN (". implode(",",$templates) .")" );
               if ($validation_result !== false)
                  $message = __("Successfully deleted the selected template(s).",'events-made-easy');
	       else
                  $message = __("There was a problem deleting the selected template(s), please try again.",'events-made-easy');
         } else {
            $message = __("Couldn't delete the templates. Incorrect template IDs supplied. Please try again.",'events-made-easy');
         }
      }
   }
   eme_templates_table_layout($message);
} 

function eme_templates_table_layout($message = "") {
   $templates = eme_get_templates();
   $destination = admin_url("admin.php?page=eme-templates"); 
   $nonce_field = wp_nonce_field('eme_templates','eme_admin_nonce',false,false);
   $eme_editor_settings = eme_get_editor_settings();
   echo "
      <div class='wrap nosubsub'>\n
         <div id='icon-edit' class='icon32'>
            <br />
         </div>
         <h1>".__('Templates', 'events-made-easy')."</h1>\n ";   
         
         if($message != "") {
            echo "
            <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
               <p>$message</p>
            </div>";
         }
         
    echo "
         <div id='col-container'>
         
            <?-- begin col-right -->
            <div id='col-right'>
             <div class='col-wrap'>
                <form id='bookings-filter' method='post' action='$destination'>
                  <input type='hidden' name='eme_admin_action' value='do_deletetemplate' />";
     echo $nonce_field;
                  if (count($templates)>0) {
                     echo "<table class='widefat'>
                        <thead>
                           <tr>
                              <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1' /></th>
                              <th>".__('ID', 'events-made-easy')."</th>
                              <th>".__('Format description', 'events-made-easy')."</th>
                           </tr>
                        </thead>
                        <tfoot>
                           <tr>
                              <th class='manage-column column-cb check-column' scope='col'><input type='checkbox' class='select-all' value='1' /></th>
                              <th>".__('ID', 'events-made-easy')."</th>
                              <th>".__('Format description', 'events-made-easy')."</th>
                           </tr>
                        </tfoot>
                        <tbody>";
                     foreach ($templates as $this_template) {
                        echo "    
                           <tr>
                           <td><input type='checkbox' class ='row-selector' value='".$this_template['id']."' name='templates[]' /></td>
                           <td><a href='".wp_nonce_url(admin_url("admin.php?page=eme-templates&amp;eme_admin_action=edit_template&amp;template_id=".$this_template['id']),'eme_templates','eme_admin_nonce')."'>".$this_template['id']."</a></td>
                           <td><a href='".wp_nonce_url(admin_url("admin.php?page=eme-templates&amp;eme_admin_action=edit_template&amp;template_id=".$this_template['id']),'eme_templates','eme_admin_nonce')."'>".eme_esc_html($this_template['description'])."</a></td>
                           </tr>
                        ";
                     }
                     $delete_text=__("Are you sure you want to delete these templates?",'events-made-easy');
                     $delete_button_text=__("Delete",'events-made-easy');
                     echo <<<EOT
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
                        echo "<p>".__('No templates have been inserted yet!', 'events-made-easy');
                  }
                   echo "
                  </form>
               </div>
            </div> 
            <?-- end col-right -->
            
            <?-- begin col-left -->
            <div id='col-left'>
            <div class='col-wrap'>
                  <div class='form-wrap'>
                     <div id='ajax-response'/>
                  <h3>".__('Add template', 'events-made-easy')."</h3>
                      <form name='add' id='add' method='post' action='$destination' class='add:the-list: validate'>
                        <input type='hidden' name='eme_admin_action' value='do_addtemplate' />
                        $nonce_field
                         <div class='form-field form-required'>
                           <label for='description'>".__('Template description', 'events-made-easy')."</label>
                           <input type='text' name='description' id='description' value='' size='40' />
                           <label for='template_format'>".__('Format', 'events-made-easy')."</label>";
        wp_editor('','template_format',$eme_editor_settings);
    echo "
                           <p>".__('The format of the template', 'events-made-easy').".</p>
                         </div>
                         <p class='submit'><input type='submit' class='button-primary' name='submit' value='".__('Add template', 'events-made-easy')."' /></p>
                      </form>
                 </div>
               </div>
            </div>
            <?-- end col-left -->
         </div>
   </div>";
}

function eme_templates_edit_layout($message = "") {
   $template_id = intval($_GET['template_id']);
   $template = eme_get_template($template_id);
   $nonce_field = wp_nonce_field('eme_templates','eme_admin_nonce',false,false);
   $eme_editor_settings = eme_get_editor_settings();
   echo "
   <div class='wrap'>
      <div id='icon-edit' class='icon32'>
         <br />
      </div>
         
      <h1>".__('Edit template', 'events-made-easy')."</h1>";   
      
      if($message != "") {
         echo "
      <div id='message' class='updated fade below-h1' style='background-color: rgb(255, 251, 204);'>
         <p>$message</p>
      </div>";
      }
      echo "
      <div id='ajax-response'></div>

      <form name='edit_template' id='edit_template' method='post' action='".admin_url("admin.php?page=eme-templates")."' class='validate'>
      <input type='hidden' name='eme_admin_action' value='do_edittemplate' />
      <input type='hidden' name='template_id' value='".$template['id']."' />
      $nonce_field
      <table class='form-table'>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='description'>".__('Template description', 'events-made-easy')."</label></th>
               <td><input type='text' name='description' id='description' value='".eme_esc_html($template['description'])."' size='40' /><br />
                 ".__('The description of the template', 'events-made-easy')."</td>
            </tr>
            <tr class='form-field form-required'>
               <th scope='row' valign='top'><label for='template_format'>".__('Template format', 'events-made-easy')."</label></th>
               <td>";
        wp_editor($template['format'],'template_format',$eme_editor_settings);
    echo "
               </td>
            </tr>
         </table>
      <p class='submit'><input type='submit' class='button-primary' name='submit' value='".__('Update template', 'events-made-easy')."' /></p>
      </form>
   </div>
   ";  
}

function eme_get_templates() {
   global $wpdb;
   $templates_table = $wpdb->prefix.TEMPLATES_TBNAME;
   return $wpdb->get_results("SELECT * FROM $templates_table ORDER BY description", ARRAY_A);
}

function eme_get_templates_array_by_id() {
   $templates = eme_get_templates();
   $templates_by_id=array();
   if (is_array($templates) && count($templates)>0)
      $templates_by_id[0]='&nbsp;';
   else
      $templates_by_id[0]=__('No templates defined yet!','events-made-easy');
   foreach ($templates as $template) {
      $templates_by_id[$template['id']]=$template['description'];
   }
   return $templates_by_id;
}

function eme_get_template($template_id) {
   global $wpdb;
   $template_id = intval($template_id);
   $templates_table = $wpdb->prefix.TEMPLATES_TBNAME;
   $sql = "SELECT * FROM $templates_table WHERE id ='$template_id'";
   return $wpdb->get_row($sql, ARRAY_A);
}

function eme_get_template_format($template_id) { 
   global $wpdb;
   $template_id = intval($template_id);
   $templates_table = $wpdb->prefix.TEMPLATES_TBNAME;
   $sql = "SELECT format FROM $templates_table WHERE id ='$template_id'";   
   return $wpdb->get_var($sql);
}

?>
