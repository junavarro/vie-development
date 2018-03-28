jQuery(document).ready(function () { 

function getQueryParams(qs) {
    qs = qs.split("+").join(" ");
    var params = {},
        tokens,
        re = /[?&]?([^=]+)=([^&]*)/g;

    while (tokens = re.exec(qs)) {
        params[decodeURIComponent(tokens[1])]
            = decodeURIComponent(tokens[2]);
    }
    return params;
}
var $_GET = getQueryParams(document.location.search);

   jQuery.datepick.setDefaults( jQuery.datepick.regionalOptions[eme.translate_locale_code] );
   jQuery.datepick.setDefaults({
      changeMonth: true,
      changeYear: true,
      altFormat: "yyyy-mm-dd",
      firstDay: parseInt(eme.translate_firstDayOfWeek)
   });
   jQuery("#eme_localized_start_date").datepick({ altField: "#search_start_date" });

        //Prepare jtable plugin
        jQuery('#BookingsTableContainer').jtable({
            title: eme.translate_bookings,
            paging: true,
            sorting: true,
            toolbarsearch: false,
            toolbarreset: false,
            jqueryuiTheme: true,
            defaultSorting: '',
            selecting: true, //Enable selecting
            multiselect: true, //Allow multiple selecting
            selectingCheckboxes: true, //Show checkboxes on first column
            selectOnRowClick: true, //Enable this to only select using checkboxes
            toolbar: {
                items: [{
                        text: eme.translate_paidandapprove,
                        cssClass: "eme_jtable_button_for_pending_only",
                        click: function () {
			          var selectedRows = jQuery('#BookingsTableContainer').jtable('selectedRows');
				  var do_action = "paidandapprove";
				  var nonce = jQuery('#eme_admin_nonce').val();
				  if (selectedRows.length > 0) {
					  var ids = [];
					  selectedRows.each(function () {
						ids.push(jQuery(this).data('record').booking_id);
					  });
					  var idsjoined = ids.join(); //will be such a string '2,5,7'
					  jQuery.post(ajaxurl, {"booking_id": idsjoined, "action": "eme_manage_bookings", "do_action": do_action, "eme_admin_nonce": nonce }, function() {
						  jQuery('#BookingsTableContainer').jtable('reload');
					  });
				  } else {
					  alert(eme.translate_pleaseselectrecords);
				  }
			       }
                        },
                        {
                        text: eme.translate_csv,
                        click: function () {
                                  jtable_csv('#BookingsTableContainer');
                               }
                        },
                        {
                        text: eme.translate_print,
                        click: function () {
                                  jQuery('#BookingsTableContainer').printElement();
                               }
                        }
                        ]
            },
            actions: {
                listAction: ajaxurl+'?action=eme_bookings_list',
            },
            fields: {
                booking_id: {
                    title: eme.translate_id,
                    key: true,
                    list: true,
                    width: '2%',
                    listClass: 'eme-jtable-center'
                },
                event_name: {
                    title: eme.translate_eventname,
                },
                rsvp: {
                    title: eme.translate_rsvp,
                    searchable: false,
                    sorting: false,
                    width: '2%',
                    listClass: 'eme-jtable-center'
                },
                datetime: {
                    title: eme.translate_datetime,
                    searchable: false,
                    sorting: true
                },
                booker: {
                    title: eme.translate_booker
                },
                creation_date: {
                    title: eme.translate_bookingdate
                },
                seats: {
                    title: eme.translate_seats,
                    searchable: false,
                    sorting: false,
                    listClass: 'eme-jtable-center'
                },
                eventprice: {
                    title: eme.translate_eventprice,
                    searchable: false,
                    sorting: false
                },
                totalprice: {
                    title: eme.translate_totalprice,
                    searchable: false,
                    sorting: false
                },
                transfer_nbr_be97: {
                    title: eme.translate_uniquenbr
                },
                booking_paid: {
                    title: eme.translate_paid,
                    type: 'checkbox',
                    searchable: false,
                    values: { '0' : eme.translate_no, '1' : eme.translate_yes }
                },
                edit_link: {
                    title: eme.translate_edit,
                    searchable: false,
                    sorting: false,
                    visibility: 'fixed',
                    listClass: 'eme-jtable-center'
                },
            }
        });

        // Load list from server, but only if the container is there
        // and only in the initial load we take a possible person id in the url into account
        // This person id can come from the eme_people page when clicking on "view all bookings"
        if (jQuery('#BookingsTableContainer').length) {
           jQuery('#BookingsTableContainer').jtable('load', {
               scope: jQuery('#scope').val(),
               event_id: jQuery('#event_id').val(),
               booking_status: jQuery('#booking_status').val(),
               search_event: jQuery('#search_event').val(),
               search_person: jQuery('#search_person').val(),
               search_unique: jQuery('#search_unique').val(),
               search_start_date: jQuery('#search_start_date').val(),
               event_id: $_GET["event_id"],
               person_id: $_GET["person_id"]
           });
        }

        function updateShowHideSendmails () {
	   var $action=jQuery('select#eme_admin_action').val();
           if (jQuery.inArray($action,['denyRegistration','approveRegistration','pendingRegistration','unsetwaitinglistRegistration','setwaitinglistRegistration']) >= 0) {
              jQuery("span#span_sendmails").show();
           } else {
              jQuery("span#span_sendmails").hide();
           }
        }
        updateShowHideSendmails();
        jQuery('select#eme_admin_action').change(updateShowHideSendmails);

        // hide one toolbar button if not on pending approval
        function hideButtonPaidApprove() {
           if (jQuery('#booking_status').val() == 1) {
              jQuery('.eme_jtable_button_for_pending_only').show();
           } else {
              jQuery('.eme_jtable_button_for_pending_only').hide();
           }
        }
        hideButtonPaidApprove();

        // Actions button
        jQuery('#BookingsActionsButton').button().click(function () {
           var selectedRows = jQuery('#BookingsTableContainer').jtable('selectedRows');
           var do_action = jQuery('#eme_admin_action').val();
           var nonce = jQuery('#eme_admin_nonce').val();
           var send_mail = jQuery('#send_mail').val();
           var action_ok=1;
           if (selectedRows.length > 0) {
              if ((do_action=='denyRegistration') && !confirm(eme.translate_areyousuretodeleteselected)) {
                 action_ok=0;
              }
              if (action_ok==1) {
                 var ids = [];
                 selectedRows.each(function () {
                   ids.push(jQuery(this).data('record').booking_id);
                 });

                 var idsjoined = ids.join(); //will be such a string '2,5,7'
                 jQuery.post(ajaxurl, {"booking_id": idsjoined, "action": "eme_manage_bookings", "do_action": do_action, "send_mail": send_mail, "eme_admin_nonce": nonce }, function() {
	            jQuery('#BookingsTableContainer').jtable('reload');
                 });
              }
           } else {
              alert(eme.translate_pleaseselectrecords);
           }
           // return false to make sure the real form doesn't submit
           return false;
        });

        // Re-load records when user click 'load records' button.
        jQuery('#BookingsLoadRecordsButton').click(function (e) {
           e.preventDefault();
           jQuery('#BookingsTableContainer').jtable('load', {
               scope: jQuery('#scope').val(),
               event_id: jQuery('#event_id').val(),
               booking_status: jQuery('#booking_status').val(),
               search_event: jQuery('#search_event').val(),
               search_person: jQuery('#search_person').val(),
               search_unique: jQuery('#search_unique').val(),
               search_start_date: jQuery('#search_start_date').val(),
           });
           // return false to make sure the real form doesn't submit
           return false;
        });

    // for autocomplete to work, the element needs to exist, otherwise JS errors occur
    // we check for that using length
    if (jQuery("input[name=chooseevent]").length) {
          jQuery("input[name=chooseevent]").autocomplete({
            source: function(request, response) {
                         jQuery.post(ajaxurl,
                                  { q: request.term,
                                    not_event_id: jQuery('#event_id').val(),
                                    action: 'eme_autocomplete_event'
                                  },
                                  function(data){
                                       response(jQuery.map(data, function(item) {
                                          return {
                                             eventinfo: htmlDecode(item.eventinfo),
                                             transferto_id: htmlDecode(item.event_id),
                                          };
                                       }));
                                  }, "json");
                    },
            select:function(evt, ui) {
                         // when a person is selected, populate related fields in this form
                         jQuery('input[name=transferto_id]').val(ui.item.transferto_id);
                         jQuery('input[name=chooseevent]').val(ui.item.eventinfo).attr("readonly", true);
                         return false;
                   },
            minLength: 2
          }).data( "ui-autocomplete" )._renderItem = function( ul, item ) {
            return jQuery( "<li></li>" )
            .append("<a><strong>"+htmlDecode(item.eventinfo)+'</strong></a>')
            .appendTo( ul );
          };

          // if manual input: set the hidden field empty again
          jQuery('input[name=chooseevent]').keyup(function() {
             jQuery('input[name=transferto_id]').val('');
          }).change(function() {
             if (jQuery('input[name=chooseevent]').val()=='') {
                jQuery('input[name=transferto_id]').val('');
             }
          });
    }

});
