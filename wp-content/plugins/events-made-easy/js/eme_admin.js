function base64_encode(data) {
  if (!data) {
    return data;
  }

  return window.btoa(unescape(encodeURIComponent(data)));
}

function htmlDecode(value){ 
   return jQuery('<div/>').html(value).text(); 
}

function isoStringToDate(s) {
  var b = s.split(/[-t:+]/ig);
    // FB sends date in two formats, sometimes it avoids time
  if(b.length === 3) {
     return new Date(b[0], --b[1], b[2]);
  } else {
     return new Date(b[0], --b[1], b[2], b[3], b[4], b[5]);
  }
  //return new Date(Date.UTC(b[0], --b[1], b[2], b[3], b[4], b[5]));
}

function remove_booking() {
	nonce = (jQuery(this).parents('table:first').attr('id').split("-"))[3]; 
	idToRemove = (jQuery(this).parents('tr:first').attr('id').split("-"))[1];
	jQuery.post(ajaxurl, {"booking_id": idToRemove, "eme_admin_nonce" : nonce, "action": "eme_quick_remove_booking" },
	  function(data){
             // if the nonce check fails, the return is -1, but still a http code 200 ...
             if (data !== -1) {
		jQuery('tr#booking-' + idToRemove).fadeOut('slow');
		jQuery('td#booked-seats').text(data.bookedSeats);
		jQuery('td#available-seats').text(data.availableSeats);
             }
	  }, "json");
}
 
function areyousure(message) {
   if (!confirm(message)) {
      return false;
   } else {
      return true;
   }
}

function areyousuretodeleteevents() {
   if (jQuery('select[name=eme_admin_action]').val() == "deleteEvents" ||
         jQuery('select[name=eme_admin_action]').val() == "deleteRecurrence") {
      return window.confirm(this.title || 'Do you really want to delete these events?');
   } else {
      return true;
   }
}

jQuery(document).ready( function($) {
    // Managing bookings delete operations 
   jQuery('a.bookingdelbutton').click(remove_booking);
   jQuery('#eme_events_listform').bind("submit", areyousuretodeleteevents);

   jQuery('input.select-all').change(function() {
         if (jQuery(this).is(':checked')) {
            jQuery('input.row-selector').attr('checked', true);
         } else {
            jQuery('input.row-selector').attr('checked', false);
         }
   });

	jQuery('#mtm_add_tag').click( function(event) {
		event.preventDefault();
		//Get All meta rows
			var metas = jQuery('#mtm_body').children();
		//Copy first row and change values
			var metaCopy = jQuery(metas[0]).clone(true);
			newId = metas.length + 1;
			metaCopy.attr('id', 'mtm_'+newId);
			metaCopy.find('a').attr('rel', newId);
			metaCopy.find('[name=mtm_1_ref]').attr({
				name:'mtm_'+newId+'_ref' ,
				value:'' 
			});
			metaCopy.find('[name=mtm_1_content]').attr({ 
				name:'mtm_'+newId+'_content' , 
				value:'' 
			});
			metaCopy.find('[name=mtm_1_name]').attr({ 
				name:'mtm_'+newId+'_name' ,
				value:'' 
			});
		//Insert into end of file
			jQuery('#mtm_body').append(metaCopy);
		//Duplicate the last entry, remove values and rename id
	});
	
	jQuery('#mtm_body a').click( function(event) {
		event.preventDefault();
		//Only remove if there's more than 1 meta tag
		if(jQuery('#mtm_body').children().length > 1){
			//Remove the item
			jQuery(jQuery(this).parent().parent().get(0)).remove();
			//Renumber all the items
			jQuery('#mtm_body').children().each( function(i){
				metaCopy = jQuery(this);
				oldId = metaCopy.attr('id').replace('mtm_','');
				newId = i+1;
				metaCopy.attr('id', 'mtm_'+newId);
				metaCopy.find('a').attr('rel', newId);
				metaCopy.find('[name=mtm_'+ oldId +'_ref]').attr('name', 'mtm_'+newId+'_ref');
				metaCopy.find('[name=mtm_'+ oldId +'_content]').attr('name', 'mtm_'+newId+'_content');
				metaCopy.find('[name=mtm_'+ oldId +'_name]').attr( 'name', 'mtm_'+newId+'_name');
			});
		} else {
			metaCopy = jQuery(jQuery(this).parent().parent().get(0));
			metaCopy.find('[name=mtm_1_ref]').attr('value', '');
			metaCopy.find('[name=mtm_1_content]').attr('value', '');
			metaCopy.find('[name=mtm_1_name]').attr( 'value', '');
			alert("If you don't want any meta tags, just leave the text boxes blank and submit");
		}
	});

        jQuery('div[data-dismissible] button.notice-dismiss').click(function (event) {
            event.preventDefault();
            var $el = jQuery('div[data-dismissible]');

            var attr_value, option_name, dismissible_length, data;

            attr_value = $el.attr('data-dismissible').split('-');

            // remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();

            option_name = attr_value.join('-');

            var ajaxdata = {
                'action': 'eme_dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': emeadmin.translate_nonce
            };

            // We can also pass the url value separately from ajaxurl for front end AJAX implementations
            jQuery.post(ajaxurl, ajaxdata);

        });
 
   // the next code adds an "X" to input fields, and removes possible readonly-attr
   function tog(v){return v?'addClass':'removeClass';}
   jQuery(document).on('input', '.clearable', function(){
      jQuery(this)[tog(this.value)]('x');
   }).on('mousemove', '.x', function( e ){
      jQuery(this)[tog(this.offsetWidth-18 < e.clientX-this.getBoundingClientRect().left)]('onX');
   }).on('touchstart click', '.onX', function( ev ){
      ev.preventDefault();
      jQuery(this).removeClass('x onX').val('').change();
      jQuery(this).attr("readonly", false)
   });
});

   // the next is a Jtable CSV export function
   function jtable_csv(container) {
      // create a copy to avoid messing with visual layout
      var newTable = jQuery(container).clone();

      // fix HTML table

      // th - remove attributes and header divs from jTable
      newTable.find('th').each(function (pos, el) {
         val = jQuery(this).find('.jtable-column-header-text').text();
         jQuery(this).html(val);
         jQuery(this).removeAttr('width');
         jQuery(this).removeAttr('class');
         jQuery(this).removeAttr('style');
      });

      // tr - remove attributes
      newTable.find('tr').each(function (pos, el) {
         jQuery(this).removeAttr('class');
         jQuery(this).removeAttr('style');
         jQuery(this).removeAttr('width');
         jQuery(this).removeAttr('title');
         jQuery(this).removeAttr('data-record-key');
      });

      // td - remove attributes, images and buttons
      newTable.find('td').each(function (pos, el) {
         jQuery(this).removeAttr('class');
         jQuery(this).removeAttr('style');
         jQuery(this).removeAttr('width');
         if (jQuery(this).find('img').length > 0)
           jQuery(this).html("");
         if (jQuery(this).find('button').length > 0)
           jQuery(this).html("");
         // to make sure: we convert html to text
         val = jQuery(this).text();
         jQuery(this).html(val);
      });

      // fix incompatible HTML (th to td, white spaces)
      var table = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Worksheet</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>' + newTable.find('thead').html().replace(/th/g, 'td') + newTable.find('tbody').html() + '</table></body></html>';
      table = base64_encode(table.replace(/ /g, ' ')); // deal with spaces and encode base64
      //console.log(table); // debugging
      window.open('data:application/vnd.ms-excel;base64,' + table);
   }

