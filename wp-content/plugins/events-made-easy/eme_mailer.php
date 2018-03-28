<?php
// phpmailer support
function eme_send_mail($subject,$body, $receiveremail, $receivername='', $replytoemail='', $replytoname='') {

   // don't send empty mails
   $subject = preg_replace("/(^\s+|\s+$)/m","",$subject);
   if (empty($body) || empty($subject)) return;

   $eme_rsvp_mail_send_method = get_option('eme_rsvp_mail_send_method');
   if (get_option('eme_mail_sender_address') == "") {
      $fromMail = $replytoemail;
      $fromName = $replytoname;
   } else {
      $fromMail = get_option('eme_mail_sender_address');
      $fromName = get_option('eme_mail_sender_name'); // This is the from name in the email, you can put anything you like here
   }
   $eme_bcc_address= get_option('eme_mail_bcc_address');

   if ($eme_rsvp_mail_send_method == 'wp_mail') {
      // Set the correct mail headers
      $headers[] = "From: $fromName <$fromMail>";
      if ($replytoemail != "")
         $headers[] = "ReplyTo: $replytoname <$replytoemail>";
      if (!empty($eme_bcc_address))
         $headers[] = "Bcc: $eme_bcc_address";

      // set the correct content type
      if (get_option('eme_rsvp_send_html') == '1')
          add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));

      // now send it
      $res = wp_mail( $receiveremail, $subject, $body, $headers );  

      // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
      if (get_option('eme_rsvp_send_html') == '1')
         remove_filter('wp_mail_content_type', 'set_html_content_type' );

      return $res;

   } else {
      require_once(ABSPATH . WPINC . "/class-phpmailer.php");
      // In the past there was a bug in class-phpmailer from wordpress, so we needed to copy class-smtp.php
      // in this dir for smtp to work, but no longer
      
      if (class_exists('PHPMailer')) {
         $mail = new PHPMailer();
         $mail->ClearAllRecipients();
         $mail->ClearAddresses();
         $mail->ClearAttachments();
         $mail->CharSet = 'utf-8';
         $mail->SetLanguage('en', dirname(__FILE__).'/');

         $mail->PluginDir = dirname(__FILE__).'/';
         if ($eme_rsvp_mail_send_method == 'qmail')
            $mail->IsQmail();
         else
            $mail->Mailer = $eme_rsvp_mail_send_method;

         if ($eme_rsvp_mail_send_method == 'smtp') {
            // let us keep a normal smtp timeout ...
            $mail->Timeout = 10;
            if (get_option('eme_smtp_host'))
               $mail->Host = get_option('eme_smtp_host');
            else
               $mail->Host = "localhost";

	    // we set optional encryption and port settings
	    // but if the Host contains ssl://, tls:// or port info, it will take precedence over these anyway
	    // so it is not bad at all :-)
	    if (get_option('eme_smtp_encryption') && get_option('eme_smtp_encryption') != "none") {
	       $mail->SMTPSecure=get_option('eme_smtp_encryption');
	       if (!get_option('eme_smtp_verify_cert')) {
	          $mail->SMTPOptions = array(
                     'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                     )
                  );
               }
            }

            if (get_option('eme_smtp_port'))
               $mail->Port = get_option('eme_smtp_port');
            else
               $mail->Port = 25;

            if (get_option('eme_rsvp_mail_SMTPAuth') == '1') {
               $mail->SMTPAuth = true;
               $mail->Username = get_option('eme_smtp_username');
               $mail->Password = get_option('eme_smtp_password');
            }
            if (get_option('eme_smtp_debug'))
               $mail->SMTPDebug = 2;
         }
         $mail->setFrom($fromMail,$fromName);
         if (get_option('eme_rsvp_send_html') == '1') {
	    $mail->isHTML(true);
            // Convert all message body line breaks to CRLF, makes quoted-printable encoding work much better
            $mail->AltBody = $mail->normalizeBreaks($mail->html2text($body));
            $mail->Body = eme_nl2br_save_html($mail->normalizeBreaks($body));
	 } else {
            $mail->Body = $mail->normalizeBreaks($body);
	 }
         $mail->Subject = $subject;
         if (!empty($replytoemail))
            $mail->AddReplyTo($replytoemail,$replytoname);
         if (!empty($eme_bcc_address))
            $mail->AddBCC($eme_bcc_address);

         if (!empty($receiveremail)) {
            $mail->AddAddress($receiveremail,$receivername);
            if(!$mail->send()){
               #echo "<br />Message was not sent<br/ >";
               #echo "Mailer Error: " . $mail->ErrorInfo;
               return false;
            } else {
               return true;
            }
         } else {
            return false;
         }
      }
   }
}
?>
